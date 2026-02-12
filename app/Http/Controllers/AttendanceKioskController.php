<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AttendanceKioskController extends Controller
{
    public function index()
    {
        $employees = Employee::with('user')
            ->whereNotNull('face_data')
            ->get()
            ->map(function ($employee) {
                $descriptor = null;
                if ($employee->face_data) {
                    $decoded = json_decode($employee->face_data, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['descriptor'])) {
                        $descriptor = $decoded['descriptor'];
                    }
                }

                return [
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->user ? $employee->user->name : 'Unknown',
                    'descriptor' => $descriptor,
                ];
            })
            ->filter(fn($e) => !empty($e['descriptor']))
            ->values();

        return Inertia::render('Attendance/Kiosk', [
            'employees' => $employees
        ]);
    }

    public function store(Request $request, AttendanceService $attendanceService)
    {
        $request->validate([
            'employee_code' => 'required|string|exists:employees,employee_code',
            'image' => 'nullable|string',
            'type' => 'required|in:time_in,time_out',
        ]);

        $employee = Employee::where('employee_code', $request->employee_code)->first();
        
        return $this->logAttendance($employee, $request->type, $attendanceService);
    }

    public function scan(Request $request, AttendanceService $attendanceService)
    {
        $request->validate([
            'image' => 'required|string',
            'type' => 'required|in:time_in,time_out',
        ]);

        // Browser handles identification, this is a fallback for server-side if needed later
        return response()->json(['message' => 'Face not recognized.'], 404);
    }

    private function logAttendance($employee, $type, $attendanceService)
    {
        $now = Carbon::now();
        $dateStr = $now->format('Y-m-d');

        // Check if employee has a filed leave for today (Pending or Approved)
        $hasLeave = \App\Models\LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->exists();

        if ($hasLeave) {
            return response()->json(['message' => 'Attendance disabled. You have a filed leave request for today.'], 422);
        }

        // Check if log exists for today
        $log = AttendanceLog::firstOrNew([
            'employee_id' => $employee->id,
            'date' => $dateStr
        ]);

        if ($type === 'time_in') {
            if ($log->time_in) {
                return response()->json(['message' => 'Already timed in for today.'], 422);
            }
            $log->time_in = $now;
            
            // Calculate Late
            if ($employee->activeEmploymentRecord && $employee->activeEmploymentRecord->defaultShift) {
                $shiftStart = Carbon::parse($dateStr . ' ' . $employee->activeEmploymentRecord->defaultShift->start_time);
                $lateMinutes = $attendanceService->calculateLateMinutes($shiftStart, $now, $employee->activeEmploymentRecord);
                
                // If late is in the afternoon amnesty window (10:01 AM - 1:00 PM), mark as Half Day
                $rawLate = $shiftStart->diffInMinutes($now);
                if ($rawLate > 120 && $rawLate <= 300) {
                    $log->status = 'Half Day';
                    $log->late_minutes = 0; // It's a half day, late doesn't apply
                } elseif ($lateMinutes > 0) {
                    $log->status = 'Late';
                    $log->late_minutes = $lateMinutes;
                } else {
                    $log->status = 'Present';
                }
            } else {
                $log->status = 'Present';
            }

        } else { // Time Out
            if ($log->time_out) {
                return response()->json(['message' => 'Already timed out for today.'], 422);
            }

            $log->time_out = $now;

            // Calculate OT
            if ($employee->activeEmploymentRecord && $employee->activeEmploymentRecord->defaultShift) {
                $shiftEnd = Carbon::parse($dateStr . ' ' . $employee->activeEmploymentRecord->defaultShift->end_time);
                
                $shiftStart = Carbon::parse($dateStr . ' ' . $employee->activeEmploymentRecord->defaultShift->start_time);
                if ($shiftEnd->lt($shiftStart)) {
                    $shiftEnd->addDay();
                }

                $otMinutes = $attendanceService->calculateOvertimeMinutes($shiftEnd, $now, $employee->activeEmploymentRecord);
                $log->ot_minutes = $otMinutes;
            }
        }

        $log->save();

        return response()->json([
            'message' => 'Successfully ' . ($type === 'time_in' ? 'Timed In' : 'Timed Out'),
            'employee' => $employee->user->name,
            'time' => $now->format('h:i A')
        ]);
    }
}
