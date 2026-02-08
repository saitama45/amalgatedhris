<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SssContribution;
use App\Models\PhilhealthContribution;
use App\Models\PagibigContribution;

class ContributionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:government_deductions.view')->only(['index']);
        $this->middleware('can:government_deductions.edit')->only(['generateSSS', 'updatePhilHealth', 'updatePagIBIG', 'updateSchedules', 'syncTaxBrackets']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get all unique years from all tables
        $sssYears = SssContribution::distinct()->pluck('effective_year')->toArray();
        $phYears = PhilhealthContribution::distinct()->pluck('effective_year')->toArray();
        $piYears = PagibigContribution::distinct()->pluck('effective_year')->toArray();
        $taxYears = \App\Models\WithholdingTaxBracket::distinct()->pluck('effective_year')->toArray();
        
        $years = array_unique(array_merge($sssYears, $phYears, $piYears, $taxYears));
        rsort($years); // Latest first

        // Default to latest year if no filter, or current year if list empty
        $latestYear = $years[0] ?? date('Y');
        $selectedYear = $request->input('year', $latestYear);

        $companies = \App\Models\Company::where('is_active', true)->get();
        $selectedCompanyId = $request->input('company_id', $companies->first()?->id);

        return Inertia::render('Contributions/Index', [
            'sss' => SssContribution::where('effective_year', $selectedYear)->orderBy('min_salary')->get(),
            'philhealth' => PhilhealthContribution::where('effective_year', $selectedYear)->first(),
            'pagibig' => PagibigContribution::where('effective_year', $selectedYear)->orderBy('min_salary')->get(),
            'taxBrackets' => \App\Models\WithholdingTaxBracket::where('effective_year', $selectedYear)->orderBy('min_salary')->get(),
            'companies' => $companies,
            'filters' => [
                'year' => (int)$selectedYear,
                'available_years' => $years,
                'company_id' => (int)$selectedCompanyId
            ]
        ]);
    }

    public function syncTaxBrackets(Request $request)
    {
        $this->authorize('government_deductions.edit');
        
        $request->validate(['year' => 'required|integer']);
        $year = $request->year;

        // Current TRAIN Law brackets (2023 onwards)
        $standardBrackets = [
            ['min' => 0, 'max' => 20833, 'base' => 0, 'perc' => 0, 'over' => 0],
            ['min' => 20833.01, 'max' => 33333, 'base' => 0, 'perc' => 15, 'over' => 20833],
            ['min' => 33333.01, 'max' => 66667, 'base' => 1875, 'perc' => 20, 'over' => 33333],
            ['min' => 66667.01, 'max' => 166667, 'base' => 8541.67, 'perc' => 25, 'over' => 66667],
            ['min' => 166667.01, 'max' => 666667, 'base' => 33541.67, 'perc' => 30, 'over' => 166667],
            ['min' => 666667.01, 'max' => 9999999, 'base' => 183541.67, 'perc' => 35, 'over' => 666667],
        ];

        \DB::transaction(function () use ($year, $standardBrackets) {
            \App\Models\WithholdingTaxBracket::where('effective_year', $year)->delete();
            foreach ($standardBrackets as $b) {
                \App\Models\WithholdingTaxBracket::create([
                    'effective_year' => $year,
                    'min_salary' => $b['min'],
                    'max_salary' => $b['max'],
                    'base_tax' => $b['base'],
                    'percentage' => $b['perc'],
                    'excess_over' => $b['over'],
                    'is_active' => true
                ]);
            }
        });

        return redirect()->back()->with('success', "Withholding Tax brackets for $year have been synced to TRAIN law standards.");
    }

    public function updateSchedules(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'sss_payout_schedule' => 'required|in:first_half,second_half,both',
            'philhealth_payout_schedule' => 'required|in:first_half,second_half,both',
            'pagibig_payout_schedule' => 'required|in:first_half,second_half,both',
            'withholding_tax_payout_schedule' => 'required|in:first_half,second_half,both',
        ]);

        $company = \App\Models\Company::findOrFail($request->company_id);
        $company->update([
            'sss_payout_schedule' => $request->sss_payout_schedule,
            'philhealth_payout_schedule' => $request->philhealth_payout_schedule,
            'pagibig_payout_schedule' => $request->pagibig_payout_schedule,
            'withholding_tax_payout_schedule' => $request->withholding_tax_payout_schedule,
        ]);

        return redirect()->back()->with('success', 'Deduction schedules updated for ' . $company->name);
    }

    public function generateSSS(Request $request)
    {
        $request->validate([
            'effective_year' => 'required|integer|min:2024|max:2030',
            'min_msc' => 'required|numeric|min:1000',
            'max_msc' => 'required|numeric|gte:min_msc',
            'increment' => 'required|numeric|min:100',
            'rate' => 'required|numeric|min:1|max:30', // Percent
            'ec_low' => 'required|numeric|min:0',
            'ec_high' => 'required|numeric|min:0',
            'ec_threshold' => 'required|numeric|min:0',
        ]);

        \DB::transaction(function () use ($request) {
            // 1. Deactivate ALL records to ensure clean slate for "Active" status
            SssContribution::query()->update(['is_active' => false]);
            
            // 2. Delete existing records for THIS effective year to allow regeneration (Update logic)
            SssContribution::where('effective_year', $request->effective_year)->delete();

            $minMSC = $request->min_msc;
            $maxMSC = $request->max_msc;
            $increment = $request->increment;
            $rate = $request->rate / 100;
            
            $currentMSC = $minMSC;
            $startRange = 0;

            while ($currentMSC <= $maxMSC) {
                $endRange = $currentMSC + ($increment / 2) - 0.01;

                if ($currentMSC == $maxMSC) {
                    $endRange = 9999999;
                }

                $total = $currentMSC * $rate;
                $er_rate = ($rate / 3) * 2;
                $ee_rate = $rate / 3;
                
                $er = round($currentMSC * $er_rate, 2);
                $ee = round($currentMSC * $ee_rate, 2);
                
                if (abs(($er + $ee) - $total) > 0.01) {
                    $er = $total - $ee; 
                }

                $ec = ($currentMSC < $request->ec_threshold) ? $request->ec_low : $request->ec_high;

                SssContribution::create([
                    'effective_year' => $request->effective_year,
                    'is_active' => true,
                    'min_salary' => $startRange,
                    'max_salary' => $endRange,
                    'msc' => $currentMSC,
                    'er_share' => $er,
                    'ee_share' => $ee,
                    'ec_share' => $ec,
                    'total_contribution' => $total + $ec,
                    'wisp_er_share' => 0,
                    'wisp_ee_share' => 0
                ]);

                $startRange = $endRange + 0.01;
                $currentMSC += $increment;
            }
        });

        return redirect()->back()->with('success', 'SSS Contribution Table generated successfully.');
    }

    public function updatePhilHealth(Request $request)
    {
        $request->validate([
            'effective_year' => 'required|integer|min:2024|max:2030',
            'rate' => 'required|numeric|min:0.1|max:10', // Percent
            'min_salary' => 'required|numeric|min:0',
            'max_salary' => 'required|numeric|gt:min_salary',
        ]);

        \DB::transaction(function () use ($request) {
            // Deactivate all
            PhilhealthContribution::query()->update(['is_active' => false]);

            // Update existing or Create new for this year
            PhilhealthContribution::updateOrCreate(
                ['effective_year' => $request->effective_year],
                [
                    'is_active' => true,
                    'min_salary' => $request->min_salary,
                    'max_salary' => $request->max_salary,
                    'rate' => $request->rate / 100,
                    'er_share_percent' => 0.50,
                    'ee_share_percent' => 0.50,
                ]
            );
        });

        return redirect()->back()->with('success', 'PhilHealth settings updated.');
    }

    public function updatePagIBIG(Request $request)
    {
        $request->validate([
            'effective_year' => 'required|integer|min:2024|max:2030',
            'max_fund_salary' => 'required|numeric|min:1000',
        ]);

        \DB::transaction(function () use ($request) {
            // Deactivate all
            PagibigContribution::query()->update(['is_active' => false]);

            // Remove existing brackets for this year to prevent duplicates
            PagibigContribution::where('effective_year', $request->effective_year)->delete();

            // Create standard bracket 1: Below 1500 (1% EE / 2% ER)
            PagibigContribution::create([
                'effective_year' => $request->effective_year,
                'is_active' => true,
                'min_salary' => 0,
                'max_salary' => 1500,
                'ee_rate' => 0.01,
                'er_rate' => 0.02,
                'max_fund_salary' => $request->max_fund_salary
            ]);

            // Create standard bracket 2: 1500 and up (2% EE / 2% ER)
            PagibigContribution::create([
                'effective_year' => $request->effective_year,
                'is_active' => true,
                'min_salary' => 1500.01,
                'max_salary' => 9999999,
                'ee_rate' => 0.02,
                'er_rate' => 0.02,
                'max_fund_salary' => $request->max_fund_salary
            ]);
        });

        return redirect()->back()->with('success', 'Pag-IBIG settings updated.');
    }
}