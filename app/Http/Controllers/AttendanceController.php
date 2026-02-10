<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Company;
use App\Services\AttendanceService;
use App\Exports\AttendanceTemplateExport;
use App\Imports\AttendanceImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        // Defaults
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        
        $query = AttendanceLog::with(['employee.user', 'employee.activeEmploymentRecord.department', 'employee.activeEmploymentRecord.position', 'employee.activeEmploymentRecord.company', 'employee.activeEmploymentRecord.defaultShift'])
            ->whereBetween('date', [$startDate, $endDate]);

        // Filters
        if ($request->filled('search')) {
            $query->whereHas('employee.user', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }
        
        if ($request->filled('department_id')) {
            $query->whereHas('employee.activeEmploymentRecord', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('company_id')) {
            $query->whereHas('employee.activeEmploymentRecord', function($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        }

        // Sort by Date DESC, then Employee Name
        $perPage = $request->input('per_page', 10);
        $logs = $query->orderBy('date', 'desc')
            ->join('employees', 'attendance_logs.employee_id', '=', 'employees.id')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('attendance_logs.*') // Avoid column collision
            ->paginate($perPage)
            ->withQueryString();

        // Check if logs are locked (Finalized Payroll)
        $logs->getCollection()->transform(function($log) {
            $isLocked = \App\Models\Payroll::whereIn('status', ['Finalized', 'Paid'])
                ->where('cutoff_start', '<=', $log->date->format('Y-m-d'))
                ->where('cutoff_end', '>=', $log->date->format('Y-m-d'))
                ->exists();
            $log->is_locked = $isLocked;
            return $log;
        });

        return Inertia::render('DTR/Index', [
            'logs' => $logs,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $request->search,
                'department_id' => $request->department_id,
                'company_id' => $request->company_id,
            ],
            'options' => [
                'employees' => Employee::with('user')->get()->map(fn($e) => ['id' => $e->id, 'name' => $e->user->name]),
                'departments' => Department::select('id', 'name')->orderBy('name')->get(),
                'companies' => Company::select('id', 'name')->where('is_active', true)->orderBy('name')->get(),
            ]
        ]);
    }

    public function store(Request $request, AttendanceService $attendanceService)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
        ]);

        $dateStr = Carbon::parse($request->date)->format('Y-m-d');
        
        // Check for existing record to prevent duplicates
        $exists = AttendanceLog::where('employee_id', $request->employee_id)
            ->where('date', $dateStr)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors([
                'employee_id' => 'An attendance record for this employee on this date already exists.'
            ]);
        }

        $employee = Employee::find($request->employee_id);
        
        // Convert time strings to Carbon objects on the specific date
        $timeIn = $request->time_in ? Carbon::parse($dateStr . ' ' . $request->time_in) : null;
        $timeOut = $request->time_out ? Carbon::parse($dateStr . ' ' . $request->time_out) : null;

        // Handle Manual Entry Logic: If time_out is earlier than time_in, assume it is the next day.
        if ($timeIn && $timeOut && $timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        // Auto-compute status and late
        $status = 'Present';
        $lateMinutes = 0;
        $otMinutes = 0;

        if (!$timeIn && !$timeOut) {
            $status = 'Absent';
        } elseif (!$timeIn || !$timeOut) {
            $status = 'Incomplete';
        }

        $record = $employee->activeEmploymentRecord;
        $shift = $record ? $record->defaultShift : null;

        if ($shift) {
            $shiftStart = Carbon::parse($dateStr . ' ' . $shift->start_time);
            $shiftEnd = Carbon::parse($dateStr . ' ' . $shift->end_time);

            // Handle Overnight Shift Definition (e.g. 22:00 - 06:00)
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }

            // Calculate Late
            if ($timeIn) {
                $lateMinutes = $attendanceService->calculateLateMinutes($shiftStart, $timeIn, $record);
                if ($lateMinutes > 0) {
                    $status = 'Late';
                }
            }

            // Calculate OT
            if ($timeOut) {
                // If the employee worked past the defined shift end
                $otMinutes = $attendanceService->calculateOvertimeMinutes($shiftEnd, $timeOut, $record);
            }
        }

        try {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $dateStr,
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'ot_minutes' => $otMinutes,
            ]);

            return redirect()->back()->with('success', 'Attendance log saved successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Database error: ' . $e->getMessage());
        }
    }

    public function update(Request $request, AttendanceLog $attendanceLog, AttendanceService $attendanceService)
    {
        // Check if locked by payroll
        $isLocked = \App\Models\Payroll::whereIn('status', ['Finalized', 'Paid'])
            ->where('cutoff_start', '<=', $attendanceLog->date->format('Y-m-d'))
            ->where('cutoff_end', '>=', $attendanceLog->date->format('Y-m-d'))
            ->exists();

        if ($isLocked) {
            return redirect()->back()->with('error', 'Cannot update. This attendance record is already part of a finalized payroll.');
        }

        $request->validate([
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
        ]);

        $dateStr = $attendanceLog->date->format('Y-m-d');
        $timeIn = $request->time_in ? Carbon::parse($dateStr . ' ' . $request->time_in) : null;
        $timeOut = $request->time_out ? Carbon::parse($dateStr . ' ' . $request->time_out) : null;
        
        // Handle Manual Entry / Night Shift cross-day
        if ($timeIn && $timeOut && $timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        $employee = $attendanceLog->employee;
        $status = 'Present';
        $lateMinutes = 0;
        $otMinutes = 0;

        if (!$timeIn && !$timeOut) $status = 'Absent';
        elseif (!$timeIn || !$timeOut) $status = 'Incomplete';

        $record = $employee->activeEmploymentRecord;
        $shift = $record ? $record->defaultShift : null;

        if ($shift) {
            $shiftStart = Carbon::parse($dateStr . ' ' . $shift->start_time);
            $shiftEnd = Carbon::parse($dateStr . ' ' . $shift->end_time);

            // Handle Overnight Shift Definition
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }

            if ($timeIn) {
                $lateMinutes = $attendanceService->calculateLateMinutes($shiftStart, $timeIn, $record);
                if ($lateMinutes > 0) $status = 'Late';
            }

            if ($timeOut) {
                $otMinutes = $attendanceService->calculateOvertimeMinutes($shiftEnd, $timeOut, $record);
            }
        }

        $attendanceLog->update([
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'ot_minutes' => $otMinutes,
        ]);

        return redirect()->back()->with('success', 'Attendance log updated.');
    }

    public function destroy(AttendanceLog $attendanceLog)
    {
        // Check if locked by payroll
        $isLocked = \App\Models\Payroll::whereIn('status', ['Finalized', 'Paid'])
            ->where('cutoff_start', '<=', $attendanceLog->date->format('Y-m-d'))
            ->where('cutoff_end', '>=', $attendanceLog->date->format('Y-m-d'))
            ->exists();

        if ($isLocked) {
            return redirect()->back()->with('error', 'Cannot delete. This attendance record is already part of a finalized payroll.');
        }

        $attendanceLog->delete();
        return redirect()->back()->with('success', 'Attendance log deleted.');
    }

    public function downloadTemplate()
    {
        return Excel::download(new AttendanceTemplateExport, 'attendance_import_template.xlsx');
    }

    public function import(Request $request, AttendanceService $attendanceService)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        try {
            $import = new AttendanceImport($attendanceService);
            Excel::import($import, $request->file('file'));
            
            $results = $import->getResults();
            $count = $results['imported'];
            $skipped = $results['skipped'];

            if ($count === 0 && $skipped > 0) {
                 return redirect()->back()->with('error', "Import failed. No records saved. $skipped rows skipped. First error: " . ($results['errors'][0] ?? 'Unknown error'));
            }

            if ($count === 0) {
                 return redirect()->back()->with('error', "Import failed. No valid data found in file.");
            }

            $msg = "Successfully imported $count records.";
            if ($skipped > 0) {
                $msg .= " $skipped rows were skipped due to errors.";
            }

            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
