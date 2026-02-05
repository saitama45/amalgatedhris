<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\OvertimeRequest;
use App\Models\Company;
use App\Models\EmployeeDeduction;
use App\Models\Loan;
use App\Models\LeaveRequest;
use App\Services\AttendanceService;
use App\Services\ContributionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollController extends Controller
{
    protected $attendanceService;
    protected $contributionService;

    public function __construct(AttendanceService $attendanceService, ContributionService $contributionService)
    {
        $this->attendanceService = $attendanceService;
        $this->contributionService = $contributionService;
    }

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
                $dailyRate = $basicRate / 26; // Monthly / 26 days
                $hourlyRate = $dailyRate / 8; // Daily / 8 hours
                $minuteRate = $hourlyRate / 60;

                // 1. Calculate Cut-off Multiplier (Semi-monthly vs Monthly)
                $start = Carbon::parse($validated['cutoff_start']);
                $end = Carbon::parse($validated['cutoff_end']);
                $daysInPeriod = $start->diffInDays($end) + 1;
                $periodFactor = $daysInPeriod >= 25 ? 1 : 0.5;
                
                $basicPay = $basicRate * $periodFactor;

                // 2. Fetch Attendance Logs & Calculate Deductions/Absences
                $totalLateMinutes = 0;
                $totalUTMinutes = 0;
                $daysPresent = 0;
                $absentDays = 0;
                $paidLeaveDays = 0;
                
                $periodStart = Carbon::parse($validated['cutoff_start']);
                $periodEnd = Carbon::parse($validated['cutoff_end']);
                
                // Iterate through every day in the cut-off to check for work schedule
                for ($d = $periodStart->copy(); $d <= $periodEnd; $d->addDay()) {
                    $dayName = $d->format('D'); // Mon, Tue, etc.
                    $record = $employee->activeEmploymentRecord;
                    $isWorkDay = str_contains($record->work_days, $dayName);
                    
                    // Check for Holiday
                    $holiday = \App\Models\Holiday::where('date', $d->format('Y-m-d'))->first();
                    
                    // Check for Approved Leave
                    $onLeave = LeaveRequest::where('employee_id', $employee->id)
                        ->where('status', 'Approved')
                        ->where('start_date', '<=', $d->format('Y-m-d'))
                        ->where('end_date', '>=', $d->format('Y-m-d'))
                        ->first();

                    $log = AttendanceLog::where('employee_id', $employee->id)
                        ->where('date', $d->format('Y-m-d'))
                        ->first();

                    if ($log) {
                        $daysPresent++;
                        $totalLateMinutes += $log->late_minutes;
                        
                        if ($log->time_out && $record->defaultShift) {
                            $scheduledEnd = Carbon::parse($d->format('Y-m-d') . ' ' . $record->defaultShift->end_time);
                            if ($log->time_out->lt($scheduledEnd)) {
                                $totalUTMinutes += $log->time_out->diffInMinutes($scheduledEnd);
                            }
                        }
                    } elseif ($onLeave) {
                        $paidLeaveDays++;
                    } elseif ($isWorkDay && !$holiday) {
                        // Supposed to work but no log, not on leave, and not a holiday = Absent
                        $absentDays++;
                    }
                }

                $lateDeduction = $totalLateMinutes * $minuteRate;
                $utDeduction = $totalUTMinutes * $minuteRate;
                $absenceDeduction = $absentDays * $dailyRate;

                // 3. Fetch Approved Overtime
                $approvedOT = OvertimeRequest::where('user_id', $employee->user_id)
                    ->where('status', 'Approved')
                    ->whereBetween('date', [$validated['cutoff_start'], $validated['cutoff_end']])
                    ->sum('payable_amount');

                // 4. Calculate Contributions (Only on 2nd cut-off / Full Month)
                $sss = 0; $philhealth = 0; $pagibig = 0;
                if ($periodFactor === 1 || $periodStart->day > 15) {
                    $contributions = $this->contributionService->calculate($basicRate);
                    $sss = $contributions['sss']['ee'];
                    $philhealth = $contributions['philhealth']['ee'];
                    $pagibig = $contributions['pagibig']['ee'];
                }

                // 5. Fetch Fixed Recurring Deductions
                $otherDeductionsAmount = EmployeeDeduction::where('employee_id', $employee->id)
                    ->where('status', 'Active')
                    ->where('effective_date', '<=', $validated['cutoff_end'])
                    ->sum('amount');

                // 6. Fetch Active Loans
                $loans = Loan::where('employee_id', $employee->id)
                    ->where('status', 'Active')
                    ->where('balance', '>', 0)
                    ->get();
                
                $totalLoanDeduction = 0;
                $loanBreakdown = [];

                foreach ($loans as $loan) {
                    $deduct = min($loan->amortization, $loan->balance);
                    $totalLoanDeduction += $deduct;
                    $loanBreakdown[] = [
                        'id' => $loan->id,
                        'type' => $loan->loan_type,
                        'amount' => $deduct
                    ];
                }

                $grossPay = ($basicPay + $salary->allowance + $approvedOT) - ($lateDeduction + $utDeduction + $absenceDeduction);
                $netPay = $grossPay - ($sss + $philhealth + $pagibig + $otherDeductionsAmount + $totalLoanDeduction);

                Payslip::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'basic_pay' => $basicPay,
                    'gross_pay' => $grossPay,
                    'allowances' => $salary->allowance,
                    'ot_pay' => $approvedOT,
                    'late_deduction' => $lateDeduction,
                    'undertime_deduction' => $utDeduction,
                    'sss_deduction' => $sss,
                    'philhealth_ded' => $philhealth,
                    'pagibig_ded' => $pagibig,
                    'tax_withheld' => 0, // Simplified tax
                    'loan_deductions' => $totalLoanDeduction,
                    'other_deductions' => $otherDeductionsAmount + $absenceDeduction, // Absence is grouped here for simplicity
                    'net_pay' => $netPay,
                    'details' => [
                        'days_worked' => $daysPresent,
                        'absent_days' => $absentDays,
                        'leave_days' => $paidLeaveDays,
                        'late_minutes' => $totalLateMinutes,
                        'ut_minutes' => $totalUTMinutes,
                        'period_factor' => $periodFactor,
                        'loans' => $loanBreakdown
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
        
        $payroll->load(['company']);
        
        $payslips = Payslip::where('payroll_id', $payroll->id)
            ->with('employee.user')
            ->paginate(20);

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
                'edit_payslip' => auth()->user()->can('payroll.edit_payslip'),
            ]
        ]);
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

            // Update Loan Balances
            $payslips = Payslip::where('payroll_id', $payroll->id)->get();
            foreach ($payslips as $slip) {
                if (isset($slip->details['loans']) && count($slip->details['loans']) > 0) {
                    foreach ($slip->details['loans'] as $loanItem) {
                        $loan = Loan::find($loanItem['id']);
                        if ($loan) {
                            $newBalance = max(0, $loan->balance - $loanItem['amount']);
                            $loan->update([
                                'balance' => $newBalance,
                                'status' => $newBalance <= 0 ? 'Paid' : 'Active'
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