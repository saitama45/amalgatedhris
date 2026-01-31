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
        $logs = $query->orderBy('date', 'desc')
            ->join('employees', 'attendance_logs.employee_id', '=', 'employees.id')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('attendance_logs.*') // Avoid column collision
            ->paginate(20)
            ->withQueryString();

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
            'time_out' => 'nullable|date_format:H:i|after:time_in',
        ]);

        $employee = Employee::find($request->employee_id);
        
        // Convert time strings to Carbon objects on the specific date
        $date = Carbon::parse($request->date);
        $timeIn = $request->time_in ? Carbon::parse($request->date . ' ' . $request->time_in) : null;
        $timeOut = $request->time_out ? Carbon::parse($request->date . ' ' . $request->time_out) : null;

        // Auto-compute status and late
        $status = 'Present';
        $lateMinutes = 0;
        $otMinutes = 0; // Logic for OT can be added here or in service

        if (!$timeIn && !$timeOut) {
            $status = 'Absent';
        } elseif (!$timeIn || !$timeOut) {
            $status = 'Incomplete'; // Or 'No Time Out'
        }

        // Calculate Late if Time In is present
        if ($timeIn && $employee->activeEmploymentRecord && $employee->activeEmploymentRecord->defaultShift) {
            $shiftStart = Carbon::parse($request->date . ' ' . $employee->activeEmploymentRecord->defaultShift->start_time);
            $lateMinutes = $attendanceService->calculateLateMinutes($shiftStart, $timeIn, $employee->activeEmploymentRecord);
            
            if ($lateMinutes > 0) {
                $status = 'Late';
            }
        }

        // Calculate OT if Time Out is present
        if ($timeOut && $employee->activeEmploymentRecord && $employee->activeEmploymentRecord->defaultShift) {
            $shiftEnd = Carbon::parse($request->date . ' ' . $employee->activeEmploymentRecord->defaultShift->end_time);
            
            // Handle cross-day shift if timeOut < shiftStart (very basic)
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }

            $otMinutes = $attendanceService->calculateOvertimeMinutes($shiftEnd, $timeOut, $employee->activeEmploymentRecord);
        }

        AttendanceLog::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date->format('Y-m-d')],
            [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'ot_minutes' => $otMinutes,
            ]
        );

        return redirect()->back()->with('success', 'Attendance log saved successfully.');
    }

    public function update(Request $request, AttendanceLog $attendanceLog, AttendanceService $attendanceService)
    {
        $request->validate([
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i', // removed after:time_in for flexible editing/night shift handling need logic
        ]);

        // Same logic as store, essentially recalculating
        $dateStr = $attendanceLog->date->format('Y-m-d');
        $timeIn = $request->time_in ? Carbon::parse($dateStr . ' ' . $request->time_in) : null;
        $timeOut = $request->time_out ? Carbon::parse($dateStr . ' ' . $request->time_out) : null;
        
        // Handle Night Shift cross-day (simplified: if out < in, assume next day)
        if ($timeIn && $timeOut && $timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        $employee = $attendanceLog->employee;
        $status = 'Present';
        $lateMinutes = 0;
        $otMinutes = 0;

        if (!$timeIn && !$timeOut) $status = 'Absent';
        elseif (!$timeIn || !$timeOut) $status = 'Incomplete';

        if ($timeIn && $employee->activeEmploymentRecord && $employee->activeEmploymentRecord->defaultShift) {
            $shiftStart = Carbon::parse($dateStr . ' ' . $employee->activeEmploymentRecord->defaultShift->start_time);
            $lateMinutes = $attendanceService->calculateLateMinutes($shiftStart, $timeIn, $employee->activeEmploymentRecord);
             if ($lateMinutes > 0) $status = 'Late';
        }

        // Calculate OT if Time Out is present
        if ($timeOut && $employee->activeEmploymentRecord && $employee->activeEmploymentRecord->defaultShift) {
            $shiftStart = Carbon::parse($dateStr . ' ' . $employee->activeEmploymentRecord->defaultShift->start_time);
            $shiftEnd = Carbon::parse($dateStr . ' ' . $employee->activeEmploymentRecord->defaultShift->end_time);
            
            // Handle cross-day shift
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }

            $otMinutes = $attendanceService->calculateOvertimeMinutes($shiftEnd, $timeOut, $employee->activeEmploymentRecord);
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
