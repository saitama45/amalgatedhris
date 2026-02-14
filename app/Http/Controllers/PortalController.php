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

    public function attendance(Request $request)
    {
        $employee = Auth::user()->employee;
        
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        if (!$employee) {
            return Inertia::render('Portal/Attendance', [
                'logs' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            ]);
        }

        $query = \App\Models\AttendanceLog::where('employee_id', $employee->id)
            ->with(['employee.activeEmploymentRecord.department', 'employee.activeEmploymentRecord.company', 'employee.activeEmploymentRecord.defaultShift']);

        $query->whereDate('date', '>=', $startDate);
        $query->whereDate('date', '<=', $endDate);

        $logs = $query->orderBy('date', 'desc')->paginate($request->get('per_page', 10));

        // Mark locked logs (if they fall within finalized payroll)
        $finalizedPayrolls = \App\Models\Payroll::whereIn('status', ['Finalized', 'Paid'])
            ->whereHas('payslips', function($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })->get();

        $logs->getCollection()->transform(function($log) use ($finalizedPayrolls) {
            $logDate = is_string($log->date) ? $log->date : $log->date->format('Y-m-d');
            $log->is_locked = $finalizedPayrolls->contains(function($p) use ($logDate) {
                return $logDate >= $p->cutoff_start->format('Y-m-d') && $logDate <= $p->cutoff_end->format('Y-m-d');
            });
            return $log;
        });

        return Inertia::render('Portal/Attendance', [
            'logs' => $logs,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    public function obAttendance()
    {
        $employee = Auth::user()->employee;
        $todayLog = $employee ? \App\Models\AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', now()->toDateString())
            ->first() : null;

        return Inertia::render('Portal/OBAttendance', [
            'todayLog' => $todayLog,
            'employee' => $employee?->load('activeEmploymentRecord.defaultShift')
        ]);
    }

    public function storeObAttendance(Request $request)
    {
        $employee = Auth::user()->employee;
        if (!$employee) abort(403, 'No employee record found.');

        $request->validate([
            'type' => 'required|in:in,out',
            'photo' => 'required|string', // Base64 image from webcam
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $type = $request->type;
        $lat = $request->latitude;
        $long = $request->longitude;
        $dateTime = now();
        $date = $dateTime->toDateString();

        // Handle base64 photo
        $img = $request->photo;
        $img = str_replace('data:image/jpeg;base64,', '', $img);
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        $filename = "attendance/ob/{$date}/" . uniqid() . '.jpg';
        \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $data);

        $log = \App\Models\AttendanceLog::logOB($employee, $dateTime, $type, [
            'latitude' => $lat,
            'longitude' => $long,
            'photo_path' => $filename
        ]);

        return back()->with('success', "Official Business Time " . ($type === 'in' ? 'In' : 'Out') . " recorded successfully.");
    }

    public function exportAttendancePdf(Request $request)
    {
        $employee = Auth::user()->employee;
        if (!$employee) abort(403, 'No employee record found.');

        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $logs = \App\Models\AttendanceLog::where('employee_id', $employee->id)
            ->with(['employee.activeEmploymentRecord.department', 'employee.activeEmploymentRecord.company', 'employee.activeEmploymentRecord.position', 'employee.activeEmploymentRecord.defaultShift'])
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date', 'asc')
            ->get();

        $employee->load(['user', 'activeEmploymentRecord.department', 'activeEmploymentRecord.company', 'activeEmploymentRecord.position']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.dtr', [
            'logs' => $logs,
            'employee' => $employee,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $filename = "DTR_{$employee->employee_code}_{$startDate}_to_{$endDate}.pdf";
        return $pdf->stream($filename);
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

    public function payslips(Request $request)
    {
        $employee = Auth::user()->employee;
        
        $query = Payslip::where('employee_id', $employee?->id)
            ->whereHas('payroll', function($q) {
                $q->whereIn('status', ['Finalized', 'Paid']);
            })
            ->with('payroll');

        if ($request->filled('payout_date')) {
            $query->whereHas('payroll', function($q) use ($request) {
                $q->whereDate('payout_date', $request->payout_date);
            });
        }

        $payslips = $query->latest()->paginate($request->get('per_page', 10));

        // Fetch unique payout dates for the filter
        $payoutDates = Payslip::where('employee_id', $employee?->id)
            ->whereHas('payroll', function($q) {
                $q->whereIn('status', ['Finalized', 'Paid']);
            })
            ->join('payrolls', 'payslips.payroll_id', '=', 'payrolls.id')
            ->select('payrolls.payout_date')
            ->distinct()
            ->orderBy('payrolls.payout_date', 'desc')
            ->pluck('payout_date');

        return Inertia::render('Portal/Payslips', [
            'payslips' => $payslips,
            'payoutDates' => $payoutDates,
            'filters' => $request->only(['payout_date'])
        ]);
    }

    public function exportPayslipPdf($id)
    {
        $payslip = Payslip::findOrFail($id);
        $user = Auth::user();
        $employee = $user->employee;
        
        $isHROrAdmin = $user->can('payroll.view');
        $isOwner = $employee && $payslip->employee_id == $employee->id;

        if (!$isHROrAdmin && !$isOwner) {
            $userEmpId = $employee ? $employee->id : 'NONE';
            abort(403, "Access Denied. User: {$user->id}, Your Emp ID: {$userEmpId}, Slip Emp ID: {$payslip->employee_id}");
        }

        $payslip->load(['employee.user', 'payroll.company', 'employee.activeEmploymentRecord.position', 'employee.activeEmploymentRecord.department']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payslip', [
            'slip' => $payslip,
            'payroll' => $payslip->payroll
        ]);

        $dateString = $payslip->payroll->cutoff_end->format('Y-m-d');
        $employeeName = str_replace(' ', '_', $payslip->employee->user->name);
        $filename = "Payslip_{$employeeName}_{$dateString}.pdf";
        
        if (request()->has('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
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
