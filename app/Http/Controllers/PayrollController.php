<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\OvertimeRequest;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('payroll.view');

        $query = Payroll::with(['company', 'payslips'])
            ->latest('cutoff_end');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $payrolls = $query->paginate(10);

        return Inertia::render('Payroll/Index', [
            'payrolls' => $payrolls,
            'companies' => Company::all(),
            'filters' => $request->only(['company_id']),
            'can' => [
                'create' => auth()->user()->can('payroll.create'),
                'delete' => auth()->user()->can('payroll.delete'),
                'view' => auth()->user()->can('payroll.view'),
            ]
        ]);
    }

    public function create()
    {
        $this->authorize('payroll.create');
        
        return Inertia::render('Payroll/Create', [
            'companies' => Company::all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('payroll.create');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'cutoff_start' => 'required|date',
            'cutoff_end' => 'required|date|after_or_equal:cutoff_start',
            'payout_date' => 'required|date|after_or_equal:cutoff_end',
        ]);

        DB::beginTransaction();
        try {
            $payroll = Payroll::create([
                'company_id' => $validated['company_id'],
                'cutoff_start' => $validated['cutoff_start'],
                'cutoff_end' => $validated['cutoff_end'],
                'payout_date' => $validated['payout_date'],
                'status' => 'Draft'
            ]);

            // 1. Fetch eligible employees
            $employees = Employee::whereHas('activeEmploymentRecord', function ($q) use ($validated) {
                $q->where('company_id', $validated['company_id'])
                  ->where('is_active', true);
            })->with(['activeEmploymentRecord.salaryHistories' => function($q) {
                $q->latest('effective_date');
            }])->get();

            $generatedCount = 0;

            foreach ($employees as $employee) {
                // Get Latest Salary
                $salary = $employee->activeEmploymentRecord->salaryHistories->first();
                if (!$salary) continue;

                $basicRate = $salary->basic_rate; // Monthly
                $dailyRate = $basicRate / 26; // Assuming 26 days
                $hourlyRate = $dailyRate / 8;

                // 2. Calculate Attendance (Late, UT, Absences)
                // Fetch logs within range
                $logs = AttendanceLog::where('employee_id', $employee->id)
                    ->whereBetween('date', [$validated['cutoff_start'], $validated['cutoff_end']])
                    ->get();

                // Simple Calculation Logic (To be expanded)
                $lateMinutes = 0;
                $utMinutes = 0;
                $daysPresent = $logs->count(); 
                // Note: Absences need schedule check. For MVP, we assume worked days = logs count.
                
                // 3. Calculate Overtime
                $approvedOT = OvertimeRequest::where('user_id', $employee->user_id)
                    ->where('status', 'Approved')
                    ->whereBetween('date', [$validated['cutoff_start'], $validated['cutoff_end']])
                    ->sum('payable_amount');

                // 4. Calculate Gross
                // Pro-rated if not full month? For now, assume semi-monthly split (50%) if range is ~15 days
                $start = Carbon::parse($validated['cutoff_start']);
                $end = Carbon::parse($validated['cutoff_end']);
                $daysInPeriod = $start->diffInDays($end) + 1;
                
                $periodFactor = $daysInPeriod >= 25 ? 1 : 0.5; // Rough estimate for MVP
                
                $basicPay = $basicRate * $periodFactor;
                $grossPay = $basicPay + $salary->allowance + $approvedOT;

                // 5. Calculate Deductions (Govt & Tax - Simplified)
                // In real app, look up contribution tables
                $sss = 0; 
                $philhealth = 0;
                $pagibig = 100;
                $tax = 0;

                $totalDeductions = $sss + $philhealth + $pagibig + $tax;
                $netPay = $grossPay - $totalDeductions;

                Payslip::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'basic_pay' => $basicPay,
                    'gross_pay' => $grossPay,
                    'allowances' => $salary->allowance,
                    'ot_pay' => $approvedOT,
                    'sss_deduction' => $sss,
                    'philhealth_ded' => $philhealth,
                    'pagibig_ded' => $pagibig,
                    'tax_withheld' => $tax,
                    'net_pay' => $netPay,
                    'details' => [
                        'days_worked' => $daysPresent,
                        'ot_amount' => $approvedOT
                    ]
                ]);
                $generatedCount++;
            }

            DB::commit();
            return redirect()->route('payroll.show', $payroll->id)
                ->with('success', "Payroll generated for $generatedCount employees.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to generate payroll: ' . $e->getMessage());
        }
    }

    public function show(Payroll $payroll)
    {
        $this->authorize('payroll.view');
        
        $payroll->load(['company', 'payslips.employee.user']);

        return Inertia::render('Payroll/Show', [
            'payroll' => $payroll,
            'summary' => [
                'total_gross' => $payroll->payslips->sum('gross_pay'),
                'total_net' => $payroll->payslips->sum('net_pay'),
                'employee_count' => $payroll->payslips->count(),
            ],
            'can' => [
                'approve' => auth()->user()->can('payroll.approve'),
                'edit_payslip' => auth()->user()->can('payroll.edit_payslip'),
            ]
        ]);
    }

    public function destroy(Payroll $payroll)
    {
        $this->authorize('payroll.delete');
        $payroll->delete();
        return redirect()->route('payroll.index')->with('success', 'Payroll record deleted.');
    }

    public function approve(Payroll $payroll)
    {
        $this->authorize('payroll.approve');
        
        $payroll->update(['status' => 'Finalized']);

        return back()->with('success', 'Payroll finalized and locked.');
    }

    /**
     * Update individual payslip during preview (Draft mode only)
     */
    public function updatePayslip(Request $request, Payslip $payslip)
    {
        $this->authorize('payroll.edit_payslip');

        if ($payslip->payroll->status !== 'Draft') {
            return back()->with('error', 'Cannot edit finalized payroll.');
        }

        $validated = $request->validate([
            'basic_pay' => 'required|numeric',
            'allowances' => 'required|numeric',
            'ot_pay' => 'required|numeric',
            'late_deduction' => 'required|numeric',
            'undertime_deduction' => 'required|numeric',
            'sss_deduction' => 'required|numeric',
            'philhealth_ded' => 'required|numeric',
            'pagibig_ded' => 'required|numeric',
            'tax_withheld' => 'required|numeric',
            'other_deductions' => 'required|numeric',
        ]);

        $gross = $validated['basic_pay'] + $validated['allowances'] + $validated['ot_pay'];
        $deductions = $validated['late_deduction'] + $validated['undertime_deduction'] + 
                      $validated['sss_deduction'] + $validated['philhealth_ded'] + 
                      $validated['pagibig_ded'] + $validated['tax_withheld'] + 
                      $validated['other_deductions'];
        
        $validated['gross_pay'] = $gross;
        $validated['net_pay'] = $gross - $deductions;

        $payslip->update($validated);

        return back()->with('success', 'Payslip updated.');
    }
}