<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Shift;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        // View for generating/managing schedules
        // We need options for the filter dropdowns (Departments, Shifts)
        
        $employees = Employee::with('user', 'activeEmploymentRecord.department', 'activeEmploymentRecord.defaultShift')
            ->when($request->department_id, function($q) use ($request) {
                $q->whereHas('activeEmploymentRecord', function($sq) use ($request) {
                    $sq->where('department_id', $request->department_id);
                });
            })
            ->when($request->search, function($q) use ($request) {
                $q->whereHas('user', function($sq) use ($request) {
                    $sq->where('name', 'like', "%{$request->search}%");
                });
            })
            ->get();

        return Inertia::render('Schedules/Index', [
            'employees' => $employees,
            'shifts' => Shift::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'filters' => $request->only(['department_id', 'search']),
        ]);
    }

    public function show(Request $request, Employee $employee)
    {
        $start = $request->start ? Carbon::parse($request->start) : now()->startOfMonth();
        $end = $request->end ? Carbon::parse($request->end) : now()->endOfMonth();

        // 1. Get Daily Overrides/Specific Schedules
        $schedules = EmployeeSchedule::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->with('shift')
            ->get()
            ->keyBy('date');

        // 2. Generate Calendar View based on Default Pattern + Overrides
        $calendarData = [];
        $activeRecord = $employee->activeEmploymentRecord;
        $defaultShift = $activeRecord ? $activeRecord->defaultShift : null;
        
        // Parse work_days string "1,2,3,4,5" into array
        $workDays = $activeRecord && $activeRecord->work_days 
            ? explode(',', $activeRecord->work_days) 
            : [];

        $currentDate = $start->copy();
        while ($currentDate->lte($end)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->dayOfWeek; // 0 (Sun) - 6 (Sat)
            
            // Check for override
            if (isset($schedules[$dateStr])) {
                $schedule = $schedules[$dateStr];
                $calendarData[] = [
                    'id' => $schedule->id,
                    'title' => $schedule->is_rest_day ? 'REST DAY' : ($schedule->shift ? $schedule->shift->name : 'Custom'),
                    'start' => $dateStr . 'T' . ($schedule->start_time ?? '00:00:00'),
                    'end' => $dateStr . 'T' . ($schedule->end_time ?? '23:59:59'),
                    'className' => $schedule->is_rest_day ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-100 text-blue-700 border-blue-200',
                    'extendedProps' => [
                        'type' => 'Override',
                        'start_time' => $schedule->start_time ? Carbon::parse($schedule->start_time)->format('H:i') : null,
                        'end_time' => $schedule->end_time ? Carbon::parse($schedule->end_time)->format('H:i') : null,
                        'break' => $schedule->break_minutes,
                        'grace' => $schedule->grace_period_minutes,
                        'ot' => $schedule->is_ot_allowed,
                    ]
                ];
            } else {
                // Use Default Pattern
                // If day is in workDays, assign default shift. Else, Rest Day.
                $isWorkDay = in_array($dayOfWeek, $workDays);
                
                if ($isWorkDay && $defaultShift) {
                    $calendarData[] = [
                        'id' => 'default-' . $dateStr,
                        'title' => $defaultShift->name,
                        'start' => $dateStr . 'T' . $defaultShift->start_time,
                        'end' => $dateStr . 'T' . $defaultShift->end_time,
                        'className' => 'bg-slate-50 text-slate-600 border-slate-200 border-dashed', // Different style for default
                        'extendedProps' => [
                            'type' => 'Standard',
                            'start_time' => Carbon::parse($defaultShift->start_time)->format('H:i'),
                            'end_time' => Carbon::parse($defaultShift->end_time)->format('H:i'),
                            'break' => $defaultShift->break_minutes,
                            'grace' => $activeRecord->grace_period_minutes,
                            'ot' => $activeRecord->is_ot_allowed,
                        ]
                    ];
                } else {
                    // Implicit Rest Day - Optional to show or hide
                    $calendarData[] = [
                        'id' => 'rest-' . $dateStr,
                        'title' => 'Rest Day',
                        'start' => $dateStr,
                        'allDay' => true,
                        'display' => 'background', // Full background color
                        'className' => 'bg-slate-100 opacity-50',
                        'extendedProps' => ['type' => 'Standard Rest']
                    ];
                }
            }
            
            $currentDate->addDay();
        }

        return response()->json($calendarData);
    }

    public function store(Request $request)
    {
        // Update Standard Schedule (Employment Record)
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'days_of_week' => 'required|array', // [1,2,3,4,5]
            'grace_period_minutes' => 'nullable|integer|min:0',
            'is_ot_allowed' => 'boolean',
        ]);

        $workDaysStr = implode(',', $request->days_of_week);

        DB::transaction(function () use ($request, $workDaysStr) {
            foreach ($request->employee_ids as $employeeId) {
                $employee = Employee::find($employeeId);
                if ($employee && $employee->activeEmploymentRecord) {
                    $employee->activeEmploymentRecord->update([
                        'default_shift_id' => $request->shift_id,
                        'work_days' => $workDaysStr,
                        'grace_period_minutes' => $request->grace_period_minutes ?? 0,
                        'is_ot_allowed' => $request->is_ot_allowed ?? false,
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Standard schedule updated successfully.');
    }
}