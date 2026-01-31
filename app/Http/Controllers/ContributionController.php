<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SssContribution;
use App\Models\PhilhealthContribution;
use App\Models\PagibigContribution;

class ContributionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get all unique years from all tables
        $sssYears = SssContribution::distinct()->pluck('effective_year')->toArray();
        $phYears = PhilhealthContribution::distinct()->pluck('effective_year')->toArray();
        $piYears = PagibigContribution::distinct()->pluck('effective_year')->toArray();
        
        $years = array_unique(array_merge($sssYears, $phYears, $piYears));
        rsort($years); // Latest first

        // Default to latest year if no filter, or current year if list empty
        $latestYear = $years[0] ?? date('Y');
        $selectedYear = $request->input('year', $latestYear);

        return Inertia::render('Contributions/Index', [
            'sss' => SssContribution::where('effective_year', $selectedYear)->orderBy('min_salary')->get(),
            'philhealth' => PhilhealthContribution::where('effective_year', $selectedYear)->first(),
            'pagibig' => PagibigContribution::where('effective_year', $selectedYear)->orderBy('min_salary')->get(),
            'filters' => [
                'year' => (int)$selectedYear,
                'available_years' => $years
            ]
        ]);
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