<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRate;
use App\Models\Payslip;
use App\Models\EmployeeDeduction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PortalController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $employee = $user->employee;

        // Fetch finalized payrolls to determine what has been "processed"
        $finalizedPayrolls = $employee ? \App\Models\Payroll::whereIn('status', ['Finalized', 'Paid'])
            ->whereHas('payslips', function($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->get() : collect([]);

        // Helper to check if a date range is within any finalized payroll
        $isFinalized = function($start, $end) use ($finalizedPayrolls) {
            $start = is_string($start) ? $start : $start->format('Y-m-d');
            $end = is_string($end) ? $end : $end->format('Y-m-d');
            
            return $finalizedPayrolls->contains(function($p) use ($start, $end) {
                return $start <= $p->cutoff_end->format('Y-m-d') && $end >= $p->cutoff_start->format('Y-m-d');
            });
        };

        $recentLeaves = $employee ? LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->latest()->take(5)->get()
            ->map(function($l) use ($isFinalized) {
                $l->processed = $isFinalized($l->start_date, $l->end_date);
                return $l;
            }) : [];

        $recentOvertime = OvertimeRequest::where('user_id', $user->id)
            ->latest()->take(5)->get()
            ->map(function($ot) use ($isFinalized) {
                $ot->processed = $isFinalized($ot->date, $ot->date);
                return $ot;
            });

        $usedLeaves = $employee ? LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'Approved')
            ->get()
            ->filter(function($l) use ($isFinalized) {
                return $isFinalized($l->start_date, $l->end_date);
            })->count() : 0;

        // Calculate dynamic leave credits per type
        $leaveTypes = LeaveType::all();
        $leaveCreditsBreakdown = $leaveTypes->map(function($type) use ($employee) {
            $used = $employee ? LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('status', 'Approved')
                ->whereYear('start_date', now()->year)
                ->count() : 0;
            
            return [
                'name' => $type->name,
                'total' => $type->days_per_year,
                'used' => $used,
                'balance' => max(0, $type->days_per_year - $used),
                'is_cumulative' => $type->is_cumulative,
                'is_convertible' => $type->is_convertible
            ];
        });

        return Inertia::render('Portal/Dashboard', [
            'employee' => $employee?->load('user', 'activeEmploymentRecord.department', 'activeEmploymentRecord.position'),
            'recentLeaves' => $recentLeaves,
            'recentOvertime' => $recentOvertime,
            'leaveCredits' => [
                'total' => $leaveCreditsBreakdown->sum('total'),
                'used' => $leaveCreditsBreakdown->sum('used'),
                'balance' => $leaveCreditsBreakdown->sum('balance'),
                'breakdown' => $leaveCreditsBreakdown
            ]
        ]);
    }

    public function leaves()
    {
        $employee = Auth::user()->employee;

        $finalizedPayrolls = $employee ? \App\Models\Payroll::whereIn('status', ['Finalized', 'Paid'])
            ->whereHas('payslips', function($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->get() : collect([]);

        $isFinalized = function($start, $end) use ($finalizedPayrolls) {
            $start = is_string($start) ? $start : $start->format('Y-m-d');
            $end = is_string($end) ? $end : $end->format('Y-m-d');
            return $finalizedPayrolls->contains(function($p) use ($start, $end) {
                return $start <= $p->cutoff_end->format('Y-m-d') && $end >= $p->cutoff_start->format('Y-m-d');
            });
        };

        $requests = $employee ? LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->latest()
            ->paginate(10) : collect([]);

        if ($employee && $requests->count() > 0) {
            $requests->getCollection()->transform(function($l) use ($isFinalized) {
                $l->processed = $isFinalized($l->start_date, $l->end_date);
                return $l;
            });
        }

        return Inertia::render('Portal/Leaves', [
            'requests' => $requests,
            'leaveTypes' => LeaveType::all(),
        ]);
    }

    public function storeLeave(Request $request)
    {
        $employee = Auth::user()->employee;
        if (!$employee) abort(403, 'No employee record found.');

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:255',
        ]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $validated['leave_type_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'status' => 'Pending'
        ]);

        return back()->with('success', 'Leave request filed successfully.');
    }

    public function updateLeave(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $employee = Auth::user()->employee;
        if ($leaveRequest->employee_id != $employee?->id) abort(403);
        if ($leaveRequest->status !== 'Pending') abort(403, 'Only pending requests can be edited.');

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:255',
        ]);

        $leaveRequest->update($validated);

        return back()->with('success', 'Leave request updated.');
    }

    public function destroyLeave($id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $employee = Auth::user()->employee;
        if ($leaveRequest->employee_id != $employee?->id) abort(403);
        if ($leaveRequest->status !== 'Pending') abort(403, 'Only pending requests can be cancelled.');

        $leaveRequest->delete();

        return back()->with('success', 'Leave request cancelled.');
    }

    public function overtime()
    {
        $user = Auth::user();
        $employee = $user->employee;

        $finalizedPayrolls = $employee ? \App\Models\Payroll::whereIn('status', ['Finalized', 'Paid'])
            ->whereHas('payslips', function($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->get() : collect([]);

        $isFinalized = function($date) use ($finalizedPayrolls) {
            $date = is_string($date) ? $date : $date->format('Y-m-d');
            return $finalizedPayrolls->contains(function($p) use ($date) {
                return $date >= $p->cutoff_start->format('Y-m-d') && $date <= $p->cutoff_end->format('Y-m-d');
            });
        };

        $requests = OvertimeRequest::where('user_id', $user->id)
                ->latest()
                ->paginate(10);

        $requests->getCollection()->transform(function($ot) use ($isFinalized) {
            $ot->processed = $isFinalized($ot->date);
            return $ot;
        });

        return Inertia::render('Portal/Overtime', [
            'requests' => $requests,
            'rates' => OvertimeRate::where('is_active', true)->get(),
        ]);
    }

    public function storeOvertime(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'reason' => 'required|string|max:255',
        ]);

        $start = Carbon::parse($request->date . ' ' . $request->start_time);
        $end = Carbon::parse($request->date . ' ' . $request->end_time);
        $hours = $start->diffInMinutes($end) / 60;

        if ($hours < 1) {
            return back()->withErrors(['end_time' => 'Overtime must be at least 1 hour.']);
        }

        OvertimeRequest::create([
            'user_id' => Auth::id(),
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'hours_requested' => $hours,
            'reason' => $request->reason,
            'status' => 'Pending'
        ]);

        return back()->with('success', 'Overtime request submitted.');
    }

    public function updateOvertime(Request $request, $id)
    {
        $overtimeRequest = OvertimeRequest::findOrFail($id);

        if ($overtimeRequest->user_id != Auth::id()) {
            abort(403, 'You do not own this request.');
        }
        
        if ($overtimeRequest->status !== 'Pending') {
            return back()->with('error', 'Only pending requests can be edited.');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'reason' => 'required|string|max:255',
        ]);

        $start = Carbon::parse($request->date . ' ' . $request->start_time);
        $end = Carbon::parse($request->date . ' ' . $request->end_time);
        $hours = $start->diffInMinutes($end) / 60;

        if ($hours < 1) {
            return back()->withErrors(['end_time' => 'Overtime must be at least 1 hour.']);
        }

        $overtimeRequest->update([
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'hours_requested' => $hours,
            'reason' => $request->reason,
        ]);

        return back()->with('success', 'Overtime request updated.');
    }

    public function destroyOvertime($id)
    {
        $overtimeRequest = OvertimeRequest::findOrFail($id);

        if ($overtimeRequest->user_id != Auth::id()) abort(403);
        if ($overtimeRequest->status !== 'Pending') abort(403, 'Only pending requests can be cancelled.');

        $overtimeRequest->delete();

        return back()->with('success', 'Overtime request cancelled.');
    }

    public function payslips()
    {
        $employee = Auth::user()->employee;

        return Inertia::render('Portal/Payslips', [
            'payslips' => $employee ? Payslip::where('employee_id', $employee->id)
                ->whereHas('payroll', function($q) {
                    $q->whereIn('status', ['Finalized', 'Paid']);
                })
                ->with('payroll')
                ->latest()
                ->paginate(12) : [],
        ]);
    }

    public function deductions()
    {
        $employee = Auth::user()->employee;
        $deductions = $employee ? EmployeeDeduction::where('employee_id', $employee->id)
            ->with('deductionType')
            ->latest()
            ->get() : collect([]);

        // Fetch finalized payslips to build the ledger
        $finalizedPayslips = $employee ? Payslip::where('employee_id', $employee->id)
            ->whereHas('payroll', function($q) {
                $q->whereIn('status', ['Finalized', 'Paid']);
            })
            ->with('payroll')
            ->get() : collect([]);

        $deductionsWithHistory = $deductions->map(function($ded) use ($finalizedPayslips) {
            $history = [];
            foreach ($finalizedPayslips as $slip) {
                $breakdown = $slip->details['deductions'] ?? [];
                foreach ($breakdown as $item) {
                    if ($item['id'] == $ded->id) {
                        $history[] = [
                            'date' => $slip->payroll->payout_date,
                            'amount' => $item['amount'],
                            'installment' => $item['installment'] ?? null,
                            'payroll_name' => $slip->payroll->name
                        ];
                    }
                }
            }
            $ded->payment_history = $history;
            return $ded;
        });

        return Inertia::render('Portal/Deductions', [
            'deductions' => $deductionsWithHistory,
        ]);
    }
}
