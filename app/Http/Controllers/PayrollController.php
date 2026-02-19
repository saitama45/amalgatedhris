<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\OvertimeRequest;
use App\Models\Company;
use App\Models\EmployeeDeduction;
use App\Models\LeaveRequest;
use App\Services\AttendanceService;
use App\Services\ContributionService;
use App\Services\TaxService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollController extends Controller
{
    protected $attendanceService;
    protected $contributionService;
    protected $taxService;

    public function __construct(AttendanceService $attendanceService, ContributionService $contributionService, TaxService $taxService)
    {
        $this->attendanceService = $attendanceService;
        $this->contributionService = $contributionService;
        $this->taxService = $taxService;
    }

    public function index(Request $request)
    {
        $this->authorize('payroll.view');

        $query = Payroll::with(['company', 'payslips'])
            ->latest('cutoff_end');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $payrolls = $query->paginate($request->get('per_page', 10));

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

            $this->generatePayslips($payroll);

            DB::commit();
            return redirect()->route('payroll.show', $payroll->id)
                ->with('success', 'Payroll generated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to generate payroll: ' . $e->getMessage());
        }
    }

    public function regenerate(Payroll $payroll)
    {
        $this->authorize('payroll.create');

        if ($payroll->status !== 'Draft') {
            return back()->with('error', 'Only draft payrolls can be regenerated.');
        }

        DB::beginTransaction();
        try {
            // Remove existing payslips
            $payroll->payslips()->delete();

            $this->generatePayslips($payroll);

            DB::commit();
            return back()->with('success', 'Payroll successfully recalculated.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to regenerate payroll: ' . $e->getMessage());
        }
    }

    private function generatePayslips(Payroll $payroll)
    {
        // 1. Fetch eligible employees
        $employees = Employee::whereHas('activeEmploymentRecord', function ($q) use ($payroll) {
            $q->where('company_id', $payroll->company_id)
              ->where('is_active', true);
        })->with(['activeEmploymentRecord.company', 'activeEmploymentRecord.defaultShift', 'user'])->get();
        
        if ($employees->isEmpty()) {
            throw new \Exception('No active employees found for this company.');
        }

        $generatedCount = 0;

        foreach ($employees as $employee) {
            $record = $employee->activeEmploymentRecord;
            
            // Late Policy exemption logic: Check position policy OR specific employee exemption (GM)
            $hasLatePolicy = $record->position ? $record->position->has_late_policy : true;
            if ($employee->employee_code === 'EMP-2026-0001') {
                $hasLatePolicy = false;
            }
            
            $basicRate = $record->basic_rate; // Monthly
            $dailyRate = $basicRate / 26; // Monthly / 26 days
            $hourlyRate = $dailyRate / 8; // Daily / 8 hours
            $minuteRate = $hourlyRate / 60;

            // 1. Calculate Cut-off Multiplier (Semi-monthly vs Monthly)
            $start = Carbon::parse($payroll->cutoff_start);
            $end = Carbon::parse($payroll->cutoff_end);
            $daysInPeriod = $start->diffInDays($end) + 1;
            $periodFactor = $daysInPeriod >= 25 ? 1 : 0.5;
            
            $basicPay = $basicRate * $periodFactor;

            // Determine Allowance for this cut-off
            $allowance = 0;
            if ($periodFactor >= 1) {
                // Monthly Payroll
                $allowance = $record->allowance;
            } else {
                // Semi-monthly: Pick based on payout date
                $payoutDay = Carbon::parse($payroll->payout_date)->day;
                if ($payoutDay <= 15) {
                    $allowance = $record->allowance_15th;
                } else {
                    $allowance = $record->allowance_30th;
                }
            }

            // 2. Fetch Attendance Logs & Calculate Deductions/Absences
            $totalLateMinutes = 0;
            $totalUTMinutes = 0;
            $daysPresent = 0;
            $absentDays = 0;
            $paidLeaveDays = 0;
            $holidayPay = 0;
            $holidayWorkPay = 0;
            
            $periodStart = Carbon::parse($payroll->cutoff_start);
            $periodEnd = Carbon::parse($payroll->cutoff_end);
            
            // Iterate through every day in the cut-off to check for work schedule
            for ($d = $periodStart->copy(); $d <= $periodEnd; $d->addDay()) {
                $dayOfWeek = $d->dayOfWeek; // 0 (Sun) to 6 (Sat)
                $workDays = explode(',', $record->work_days ?? '1,2,3,4,5');
                $isWorkDay = in_array((string)$dayOfWeek, $workDays);
                
                // Check for Holiday (Handle Recurring)
                $holiday = \App\Models\Holiday::where(function($q) use ($d) {
                    $q->where('date', $d->format('Y-m-d')) // Specific Date
                      ->orWhere(function($sub) use ($d) {
                          $sub->where('is_recurring', true)
                              ->whereMonth('date', $d->month)
                              ->whereDay('date', $d->day);
                      });
                })->first();
                
                // Check for Approved Leave
                $onLeave = LeaveRequest::where('employee_id', $employee->id)
                    ->where('status', 'Approved')
                    ->where('start_date', '<=', $d->format('Y-m-d'))
                    ->where('end_date', '>=', $d->format('Y-m-d'))
                    ->first();

                $log = AttendanceLog::where('employee_id', $employee->id)
                    ->where('date', $d->format('Y-m-d'))
                    ->first();

                if ($holiday) {
                    if ($holiday->type === 'Regular') {
                        // Regular Holiday: 100% pay if not working, 200% if working
                        if ($log) {
                            $daysPresent++;
                            $holidayWorkPay += $dailyRate; // The extra 100% (Basic already covers the first 100%)
                        } else {
                            // Paid even if absent (Standard for Regular Holiday in PH)
                            $holidayPay += $dailyRate; 
                        }
                    } elseif ($holiday->type === 'Special Non-Working') {
                        // Special Holiday: 0% if not working (No work, no pay), 130% if working
                        if ($log) {
                            $daysPresent++;
                            $holidayWorkPay += ($dailyRate * 0.3); // The extra 30%
                        } else {
                            // No work, no pay. But basic pay is monthly-fixed. 
                            // We need to deduct the daily rate since it's "No work, No pay"
                            $absenceDeduction += $dailyRate;
                        }
                    }
                }

                if ($log) {
                    if (!$holiday) $daysPresent++;
                    $totalLateMinutes += $log->late_minutes;
                    
                    if ($log->time_out && $record->defaultShift) {
                        $scheduledEnd = Carbon::parse($d->format('Y-m-d') . ' ' . $record->defaultShift->end_time);
                        $timeOut = Carbon::parse($log->time_out);
                        if ($timeOut->lt($scheduledEnd)) {
                            $totalUTMinutes += $timeOut->diffInMinutes($scheduledEnd);
                        }
                    }
                } elseif ($onLeave) {
                    $paidLeaveDays++;
                } elseif ($isWorkDay && !$holiday) {
                    $absentDays++;
                }
            }

            $lateDeduction = $hasLatePolicy ? round($totalLateMinutes * $minuteRate, 2) : 0;
            $utDeduction = $hasLatePolicy ? round($totalUTMinutes * $minuteRate, 2) : 0;
            $absenceDeduction += round($absentDays * $dailyRate, 2);

            // 3. Fetch Approved Overtime - MUST have corresponding Attendance Log
            $approvedOTRequests = OvertimeRequest::where('user_id', $employee->user_id)
                ->where('status', 'Approved')
                ->whereBetween('date', [$payroll->cutoff_start, $payroll->cutoff_end])
                ->get();

            $totalApprovedOT = 0;
            foreach ($approvedOTRequests as $otReq) {
                // Verify if there's an attendance log for this OT date
                $hasLog = AttendanceLog::where('employee_id', $employee->id)
                    ->where('date', $otReq->date->format('Y-m-d'))
                    ->exists();
                
                if ($hasLog) {
                    $totalApprovedOT += $otReq->payable_amount;
                }
            }

            // 4. Calculate Contributions based on Employee Schedule (with Company Fallback)
            $payoutDay = Carbon::parse($payroll->payout_date)->day;
            $isFirstHalfPayout = $payoutDay <= 15;
            
            $sss = 0; $philhealth = 0; $pagibig = 0;
            $contributions = $this->contributionService->calculate($basicRate);
            $sched = $employee->activeEmploymentRecord;
            $company = $sched->company;

            // SSS Calculation - Default to 'both' if not set
            if ($sched->is_sss_deducted) {
                $sssSched = $company->sss_payout_schedule ?? 'both';
                if (($sssSched === 'both') || 
                    ($sssSched === 'first_half' && $isFirstHalfPayout) ||
                    ($sssSched === 'second_half' && !$isFirstHalfPayout)) {
                    $sss = $contributions['sss']['ee'] ?? 0;
                    if ($sssSched === 'both') $sss /= 2;
                }
            }
            
            // PhilHealth Calculation - Default to 'both' if not set
            if ($sched->is_philhealth_deducted) {
                $phSched = $company->philhealth_payout_schedule ?? 'both';
                if (($phSched === 'both') || 
                    ($phSched === 'first_half' && $isFirstHalfPayout) ||
                    ($phSched === 'second_half' && !$isFirstHalfPayout)) {
                    $philhealth = $contributions['philhealth']['ee'] ?? 0;
                    if ($phSched === 'both') $philhealth /= 2;
                }
            }
            
            // Pag-IBIG Calculation - Default to 'both' if not set
            if ($sched->is_pagibig_deducted) {
                $piSched = $company->pagibig_payout_schedule ?? 'both';
                if (($piSched === 'both') || 
                    ($piSched === 'first_half' && $isFirstHalfPayout) ||
                    ($piSched === 'second_half' && !$isFirstHalfPayout)) {
                    $pagibig = $contributions['pagibig']['ee'] ?? 0;
                    if ($piSched === 'both') $pagibig /= 2;
                }
            }

            // 5. Fetch Scheduled Deductions
            $deductionQuery = EmployeeDeduction::where('employee_id', $employee->id)
                ->where('status', 'active')
                ->where('effective_date', '<=', $payroll->cutoff_end);

            $activeDeductions = $deductionQuery->get();
            $totalOtherDeductions = 0;
            $deductionBreakdown = [];

            foreach ($activeDeductions as $ded) {
                $apply = false;
                if ($ded->frequency === 'semimonthly') {
                    if ($ded->schedule === 'both' || 
                       ($ded->schedule === 'first_half' && $isFirstHalfPayout) || 
                       ($ded->schedule === 'second_half' && !$isFirstHalfPayout)) {
                        $apply = true;
                    }
                } else if ($ded->frequency === 'once_a_month') {
                    if (!$isFirstHalfPayout) {
                        $apply = true;
                    }
                }

                if ($apply) {
                    $amountToDeduct = $ded->amount;
                    $isLoan = $ded->total_amount > 0;
                    $installmentInfo = null;

                    if ($isLoan) {
                        $amountToDeduct = min($amountToDeduct, $ded->remaining_balance);
                        
                        // Calculate installment number
                        $paidBefore = $ded->total_amount - $ded->remaining_balance;
                        $installmentNo = round($paidBefore / $ded->amount) + 1;
                        $totalTerms = $ded->terms ?: ($ded->amount > 0 ? round($ded->total_amount / $ded->amount) : 0);
                        $installmentInfo = "{$installmentNo}/{$totalTerms}";
                    }

                    if ($amountToDeduct > 0) {
                        $totalOtherDeductions += $amountToDeduct;
                        $deductionBreakdown[] = [
                            'id' => $ded->id,
                            'type' => $ded->deductionType->name,
                            'amount' => $amountToDeduct,
                            'is_loan' => $isLoan,
                            'installment' => $installmentInfo
                        ];
                    }
                }
            }

            // 6. Fetch Payroll Adjustments (Refunds/Missed Pay)
            $adjustments = \App\Models\PayrollAdjustment::where('employee_id', $employee->id)
                ->where('status', 'Pending')
                ->where(function($q) use ($payroll) {
                    $q->whereNull('payout_date')
                      ->orWhere('payout_date', '<=', $payroll->payout_date);
                })
                ->get();

            $adjAdditions = 0;
            $adjDeductions = 0;
            $taxableAdj = 0;
            $adjustmentDetails = [];

            foreach ($adjustments as $adj) {
                if ($adj->type === 'Addition') {
                    $adjAdditions += $adj->amount;
                    if ($adj->is_taxable) {
                        $taxableAdj += $adj->amount;
                    }
                } else {
                    $adjDeductions += $adj->amount;
                }
                
                $adjustmentDetails[] = [
                    'id' => $adj->id,
                    'type' => $adj->type,
                    'amount' => $adj->amount,
                    'reason' => $adj->reason
                ];

                // Link and update status
                $adj->update([
                    'payroll_id' => $payroll->id,
                    'status' => 'Processed',
                    'processed_at' => now()
                ]);
            }

            $grossPay = $basicPay + $allowance + $totalApprovedOT + $adjAdditions + $holidayWorkPay;
            
            // Calculate Taxable Income
            $taxableIncome = ($grossPay - $adjAdditions + $taxableAdj) - ($sss + $philhealth + $pagibig);
            
            // Calculate Withholding Tax based on Company Cycle
            $withholdingTax = 0;
            if ($sched->is_withholding_tax_deducted) {
                $taxSched = $company->withholding_tax_payout_schedule ?: 'both';

                if ($periodFactor < 1) {
                    // Semi-monthly: Estimate monthly tax then apply schedule
                    $monthlyEstimate = $taxableIncome * 2;
                    $monthlyTax = $this->taxService->calculateMonthlyTax($monthlyEstimate);

                    if ($taxSched === 'both') {
                        $withholdingTax = $monthlyTax / 2;
                    } elseif ($taxSched === 'first_half' && $isFirstHalfPayout) {
                        $withholdingTax = $monthlyTax;
                    } elseif ($taxSched === 'second_half' && !$isFirstHalfPayout) {
                        $withholdingTax = $monthlyTax;
                    }
                } else {
                    // Monthly: Calculate directly
                    $withholdingTax = $this->taxService->calculateMonthlyTax($taxableIncome);
                }
            }

            $totalDeductions = $lateDeduction + $utDeduction + $absenceDeduction + $sss + $philhealth + $pagibig + $totalOtherDeductions + $withholdingTax + $adjDeductions;
            $netPay = $grossPay - $totalDeductions;

            Payslip::create([
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'basic_pay' => $basicPay,
                'gross_pay' => $grossPay,
                'allowances' => $allowance,
                'ot_pay' => $totalApprovedOT,
                'adjustments' => $adjAdditions + $holidayWorkPay,
                'late_deduction' => $lateDeduction,
                'undertime_deduction' => $utDeduction,
                'sss_deduction' => $sss,
                'philhealth_ded' => $philhealth,
                'pagibig_ded' => $pagibig,
                'tax_withheld' => $withholdingTax,
                'loan_deductions' => 0,
                'other_deductions' => $totalOtherDeductions + $absenceDeduction + $adjDeductions,
                'net_pay' => $netPay,
                'details' => [
                    'days_worked' => $daysPresent,
                    'absent_days' => $absentDays,
                    'absence_deduction' => $absenceDeduction,
                    'leave_days' => $paidLeaveDays,
                    'late_minutes' => $totalLateMinutes,
                    'ut_minutes' => $totalUTMinutes,
                    'period_factor' => $periodFactor,
                    'holiday_work_pay' => $holidayWorkPay,
                    'deductions' => $deductionBreakdown,
                    'adjustments' => $adjustmentDetails,
                    'contributions' => [
                        'sss' => $contributions['sss'] ?? null,
                        'philhealth' => $contributions['philhealth'] ?? null,
                        'pagibig' => $contributions['pagibig'] ?? null,
                    ]
                ]
            ]);
            $generatedCount++;
        }

        return $generatedCount;
    }

    public function show(Request $request, Payroll $payroll)
    {
        $this->authorize('payroll.view');
        
        $payroll->load(['company']);
        
        $query = Payslip::where('payroll_id', $payroll->id)
            ->with('employee.user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('employee.user', function($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%");
                })->orWhereHas('employee', function($sub) use ($search) {
                    $sub->where('employee_code', 'like', "%{$search}%");
                });
            });
        }
        
        $payslips = $query->paginate($request->get('per_page', 10));

        return Inertia::render('Payroll/Show', [
            'payroll' => $payroll,
            'payslips' => $payslips,
            'summary' => [
                'total_gross' => Payslip::where('payroll_id', $payroll->id)->sum('gross_pay'),
                'total_net' => Payslip::where('payroll_id', $payroll->id)->sum('net_pay'),
                'employee_count' => Payslip::where('payroll_id', $payroll->id)->count(),
            ],
            'can' => [
                'approve' => auth()->user()->can('payroll.approve'),
                'revert' => auth()->user()->can('payroll.revert'),
                'edit_payslip' => auth()->user()->can('payroll.edit_payslip'),
            ]
        ]);
    }

    public function revert(Payroll $payroll)
    {
        $this->authorize('payroll.revert');

        if ($payroll->status !== 'Finalized') {
            return back()->with('error', 'Only finalized payrolls can be reverted.');
        }

        DB::beginTransaction();
        try {
            // Reverse Deduction Balances
            $payslips = Payslip::where('payroll_id', $payroll->id)->get();
            foreach ($payslips as $slip) {
                if (isset($slip->details['deductions']) && count($slip->details['deductions']) > 0) {
                    foreach ($slip->details['deductions'] as $dedItem) {
                        $deduction = EmployeeDeduction::find($dedItem['id']);
                        if ($deduction && $deduction->total_amount > 0) {
                            $newBalance = $deduction->remaining_balance + $dedItem['amount'];
                            $deduction->update([
                                'remaining_balance' => $newBalance,
                                'status' => 'active'
                            ]);
                        }
                    }
                }
            }

            // Revert Adjustments
            \App\Models\PayrollAdjustment::where('payroll_id', $payroll->id)
                ->update([
                    'status' => 'Pending',
                    'payroll_id' => null,
                    'processed_at' => null
                ]);

            $payroll->update(['status' => 'Draft']);

            DB::commit();
            return back()->with('success', 'Payroll has been unlocked and reverted to Draft status.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to revert: ' . $e->getMessage());
        }
    }

    public function exportPdf(Payroll $payroll)
    {
        $this->authorize('payroll.view');
        
        $payroll->load(['company']);
        $payslips = Payslip::where('payroll_id', $payroll->id)
            ->with('employee.user')
            ->get();

        $summary = [
            'total_gross' => $payslips->sum('gross_pay'),
            'total_net' => $payslips->sum('net_pay'),
            'employee_count' => $payslips->count(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payroll', [
            'payroll' => $payroll,
            'payslips' => $payslips,
            'summary' => $summary
        ]);

        $filename = 'Payroll_' . $payroll->company->name . '_' . $payroll->cutoff_start . '_to_' . $payroll->cutoff_end . '.pdf';
        return $pdf->download($filename);
    }

    public function exportExcel(Payroll $payroll)
    {
        $this->authorize('payroll.view');
        $filename = 'Payroll_Summary_' . $payroll->company->name . '_' . $payroll->cutoff_start . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PayrollExport($payroll), $filename);
    }

    public function exportPayslipPdf(Payslip $payslip)
    {
        $this->authorize('payroll.view');
        
        $payslip->load(['employee.user', 'payroll.company', 'employee.activeEmploymentRecord.position', 'employee.activeEmploymentRecord.department']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip', [
            'slip' => $payslip,
            'payroll' => $payslip->payroll
        ]);

        $dateString = $payslip->payroll->cutoff_end->format('Y-m-d');
        $employeeName = str_replace(' ', '_', $payslip->employee->user->name);
        $filename = "Payslip_{$employeeName}_{$dateString}.pdf";
        
        return $pdf->stream($filename);
    }

    public function destroy(Payroll $payroll)
    {
        $this->authorize('payroll.delete');

        if ($payroll->status === 'Finalized') {
            return back()->with('error', 'Cannot rollback finalized payroll. Please contact system admin.');
        }

        $payroll->delete();
        return redirect()->route('payroll.index')->with('success', 'Payroll record deleted.');
    }

    public function approve(Payroll $payroll)
    {
        $this->authorize('payroll.approve');
        
        DB::beginTransaction();
        try {
            $payroll->update(['status' => 'Finalized']);

            // Update Deduction Balances
            $payslips = Payslip::where('payroll_id', $payroll->id)->get();
            foreach ($payslips as $slip) {
                if (isset($slip->details['deductions']) && count($slip->details['deductions']) > 0) {
                    foreach ($slip->details['deductions'] as $dedItem) {
                        $deduction = EmployeeDeduction::find($dedItem['id']);
                        if ($deduction && $deduction->total_amount > 0) {
                            $newBalance = max(0, $deduction->remaining_balance - $dedItem['amount']);
                            $deduction->update([
                                'remaining_balance' => $newBalance,
                                'status' => $newBalance <= 0 ? 'completed' : 'active'
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            return back()->with('success', 'Payroll finalized and loan balances updated.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to finalize: ' . $e->getMessage());
        }
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
            'adjustments' => 'required|numeric',
            'ot_pay' => 'required|numeric',
            'late_deduction' => 'required|numeric',
            'undertime_deduction' => 'required|numeric',
            'sss_deduction' => 'required|numeric',
            'philhealth_ded' => 'required|numeric',
            'pagibig_ded' => 'required|numeric',
            'tax_withheld' => 'required|numeric',
            'other_deductions' => 'required|numeric',
            'details' => 'nullable|array',
        ]);

        $gross = $validated['basic_pay'] + $validated['allowances'] + $validated['ot_pay'] + $validated['adjustments'];
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
