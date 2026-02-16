<?php

namespace App\Http\Controllers;

use App\Models\OvertimeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class OvertimeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = OvertimeRequest::with(['user.employee.activeEmploymentRecord.department', 'approver']);

        // HIERARCHY LOGIC
        // 1. If user is Admin/HR, they see all.
        // 2. If user is an Immediate Head, they see their subordinates.
        // 3. Otherwise, they only see their own.
        
        if (!$user->can('overtime.view_all')) {
            $employee = $user->employee;
            
            if ($employee && $employee->subordinates()->exists()) {
                // User is an Immediate Head - See subordinates + own
                $subordinateUserIds = Employee::where('immediate_head_id', $employee->id)->pluck('user_id')->toArray();
                $query->whereIn('user_id', array_merge($subordinateUserIds, [$user->id]));
            } else {
                // Regular user - See only own
                $query->where('user_id', $user->id);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate($request->get('per_page', 10));

        return Inertia::render('Overtime/Index', [
            'requests' => $requests,
            'rates' => \App\Models\OvertimeRate::where('is_active', true)->get(),
            'filters' => $request->all(['status']),
            'can' => [
                'create' => $user->can('overtime.create'),
                'approve' => $user->can('overtime.approve'),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'reason' => 'required|string|max:255',
        ]);

        $start = Carbon::parse($request->date . ' ' . $request->start_time);
        $end = Carbon::parse($request->date . ' ' . $request->end_time);
        
        // Calculate duration in hours
        $hours = $start->diffInMinutes($end) / 60;

        // "minimum 1hr to be considered OT"
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

        return redirect()->route('overtime.index')->with('success', 'Overtime request submitted successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OvertimeRequest $overtime)
    {
        if ($overtime->user_id !== Auth::id() && !Auth::user()->can('overtime.edit')) {
            abort(403);
        }

        if ($overtime->status === 'Approved') {
            return back()->with('error', 'Cannot edit approved overtime.');
        }

        $request->validate([
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

        $overtime->update([
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'hours_requested' => $hours,
            'reason' => $request->reason,
        ]);

        return redirect()->route('overtime.index')->with('success', 'Overtime request updated.');
    }

    /**
     * Approve the specified resource.
     */
    public function approve(Request $request, OvertimeRequest $overtimeRequest)
    {
        // Permission check
        $this->authorize('approve', $overtimeRequest);

        // --- COMPUTATION LOGIC START ---
        $employee = $overtimeRequest->user->employee;
        
        if (!$employee) {
            return back()->with('error', 'User has no associated employee record.');
        }

        $record = $employee->activeEmploymentRecord;

        if (!$record) {
            return back()->with('error', 'Employee has no active employment record.');
        }

        $basicSalary = $record->basic_rate;

        if ($basicSalary <= 0) {
            return back()->with('error', 'Employee basic rate is not set. Cannot calculate OT.');
        }
        
        // Determine Rates based on logic provided
        // Logic: 
        // Regular OT 1.25
        // Rest day 1.3 (This is for regular hours on rest day, prompt says "Rest day OT 1.69")
        // Rest day OT 1.69
        // Regular holiday 2 (Reg hours)
        // Holiday OT 2.6
        // Holiday + Rest day OT 3.38
        
        // For this demo, we rely on flags passed or simple calendar check.
        // In a real app, check Holiday model.
        // We will assume 'is_holiday' and 'is_rest_day' come from a check (omitted for brevity, or we can look up holidays table)
        
        $date = Carbon::parse($overtimeRequest->date);
        
        // Check Holiday Table
        $holiday = \App\Models\Holiday::where('date', $date->format('Y-m-d'))->first();
        $isHoliday = $holiday ? true : false;
        $holidayType = $holiday ? $holiday->type : null;
        
        // Check Rest Day (Check schedule or simple weekend)
        // Ideally checking 'shifts' or 'schedules' table.
        // Simplification: Check if schedule exists, otherwise use default
        // For now, let's assume if it's Sunday it's Rest Day for default
        $isRestDay = $date->isSunday(); // Simplification

        // Calculate
        $computation = OvertimeRequest::calculatePayable(
            $basicSalary, 
            $overtimeRequest->hours_requested, 
            $isRestDay, 
            $isHoliday, 
            $holidayType
        );
        // --- COMPUTATION LOGIC END ---

        $overtimeRequest->update([
            'status' => 'Approved',
            'approver_id' => Auth::id(),
            'approved_at' => now(),
            'is_rest_day' => $isRestDay,
            'is_holiday' => $isHoliday,
            'holiday_type' => $holidayType,
            'multiplier' => $computation['multiplier'],
            'hourly_rate_snapshot' => $computation['hourly_rate'],
            'payable_amount' => $computation['total'],
        ]);

        // Sync with AttendanceLog if exists
        $log = \App\Models\AttendanceLog::where('employee_id', $employee->id)
            ->where('date', $date->format('Y-m-d'))
            ->first();

        if ($log && $log->time_out && $record->defaultShift) {
            $shiftEnd = Carbon::parse($log->date->format('Y-m-d') . ' ' . $record->defaultShift->end_time);
            $shiftStart = Carbon::parse($log->date->format('Y-m-d') . ' ' . $record->defaultShift->start_time);
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }

            $attendanceService = app(\App\Services\AttendanceService::class);
            $otMinutes = $attendanceService->calculateOvertimeMinutes($shiftEnd, Carbon::parse($log->time_out), $record);
            $log->update(['ot_minutes' => $otMinutes]);
        }

        return back()->with('success', 'Overtime approved and synced with DTR. Estimated Pay: ' . number_format($computation['total'], 2));
    }

    /**
     * Reject the specified resource.
     */
    public function reject(Request $request, OvertimeRequest $overtimeRequest)
    {
         $this->authorize('approve', $overtimeRequest);

        $request->validate(['rejection_reason' => 'required|string']);

        $overtimeRequest->update([
            'status' => 'Rejected',
            'approver_id' => Auth::id(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'Overtime request rejected.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OvertimeRequest $overtime)
    {
        if ($overtime->user_id !== Auth::id() && !Auth::user()->can('overtime.delete')) {
            abort(403);
        }
        
        if ($overtime->status === 'Approved') {
             return back()->with('error', 'Cannot delete approved overtime.');
        }

        $overtime->delete();

        return back()->with('success', 'Overtime request deleted.');
    }
}