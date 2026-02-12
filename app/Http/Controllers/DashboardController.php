<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmploymentRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Total Employees
        $totalEmployeesQuery = Employee::whereHas('activeEmploymentRecord')
            ->with(['user', 'activeEmploymentRecord.position', 'activeEmploymentRecord.department']);
        
        $totalEmployeesCount = $totalEmployeesQuery->count();
        $totalEmployeesList = $totalEmployeesQuery->get()->map(fn($emp) => [
            'id' => $emp->id,
            'name' => $emp->user->name,
            'employee_code' => $emp->employee_code,
            'position' => $emp->activeEmploymentRecord->position->name ?? 'N/A',
            'department' => $emp->activeEmploymentRecord->department->name ?? 'N/A',
            'hire_date' => $emp->employmentRecords()->orderBy('start_date', 'asc')->first()?->start_date->format('M d, Y'),
        ]);

        // 2. For Evaluation (Currently on their 5th month of service)
        $fiveMonthsAgo = now()->subMonths(5);
        
        // Find employees whose EARLIEST start_date matches the 5th month
        $forEvaluationEmployees = Employee::whereHas('employmentRecords', function($query) use ($fiveMonthsAgo) {
            $query->whereIn('id', function($sub) {
                $sub->select(DB::raw('MIN(id)'))
                    ->from('employment_records')
                    ->groupBy('employee_id');
            })
            ->whereMonth('start_date', $fiveMonthsAgo->month)
            ->whereYear('start_date', $fiveMonthsAgo->year);
        })
        ->with(['user', 'activeEmploymentRecord.position', 'activeEmploymentRecord.department'])
        ->get()
        ->map(function($emp) {
            $earliestRecord = $emp->employmentRecords()->orderBy('start_date', 'asc')->first();
            return [
                'id' => $emp->id,
                'name' => $emp->user->name,
                'employee_code' => $emp->employee_code,
                'position' => $emp->activeEmploymentRecord->position->name ?? 'N/A',
                'department' => $emp->activeEmploymentRecord->department->name ?? 'N/A',
                'hire_date' => $earliestRecord->start_date->format('M d, Y'),
            ];
        });

        $forEvaluationCount = $forEvaluationEmployees->count();

        // 3. New Hires (This Month)
        $newHiresQuery = Employee::whereHas('employmentRecords', function($query) {
            $query->whereIn('id', function($sub) {
                $sub->select(DB::raw('MIN(id)'))
                    ->from('employment_records')
                    ->groupBy('employee_id');
            })
            ->whereMonth('start_date', now()->month)
            ->whereYear('start_date', now()->year);
        })
        ->with(['user', 'activeEmploymentRecord.position', 'activeEmploymentRecord.department']);

        $newHiresCount = $newHiresQuery->count();
        $newHiresList = $newHiresQuery->get()->map(fn($emp) => [
            'id' => $emp->id,
            'name' => $emp->user->name,
            'employee_code' => $emp->employee_code,
            'position' => $emp->activeEmploymentRecord->position->name ?? 'N/A',
            'department' => $emp->activeEmploymentRecord->department->name ?? 'N/A',
            'hire_date' => $emp->employmentRecords()->orderBy('start_date', 'asc')->first()?->start_date->format('M d, Y'),
        ]);

        // 4. Bar Graph: Count per Dept per Company
        $deptDataRaw = EmploymentRecord::where('is_active', true)
            ->with(['company', 'department'])
            ->select('company_id', 'department_id', DB::raw('count(*) as total'))
            ->groupBy('company_id', 'department_id')
            ->get();

        $barGraphData = [];
        foreach ($deptDataRaw as $record) {
            $companyName = $record->company->name ?? 'Unknown Company';
            $deptName = $record->department->name ?? 'Unknown Dept';
            
            if (!isset($barGraphData[$companyName])) {
                $barGraphData[$companyName] = [];
            }
            $barGraphData[$companyName][$deptName] = $record->total;
        }

        // 5. Pie Chart: Total Employee per Company
        $pieChartRaw = EmploymentRecord::where('is_active', true)
            ->with('company')
            ->select('company_id', DB::raw('count(*) as total'))
            ->groupBy('company_id')
            ->get();
            
        $pieChartData = [
            'labels' => [],
            'data' => [],
        ];

        foreach ($pieChartRaw as $record) {
            $pieChartData['labels'][] = $record->company->name ?? 'Unknown';
            $pieChartData['data'][] = $record->total;
        }

        // Birthdays (Existing)
        $birthdays = Employee::with('user')
            ->whereMonth('birthday', now()->month)
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->user->name,
                    'birthday' => $employee->birthday->format('M d'),
                    'date' => $employee->birthday->format('F j'),
                ];
            });

        return Inertia::render('Dashboard', [
            'birthdays' => $birthdays,
            'evaluationEmployees' => $forEvaluationEmployees,
            'totalEmployeesList' => $totalEmployeesList,
            'newHiresList' => $newHiresList,
            'stats' => [
                'totalEmployees' => $totalEmployeesCount,
                'forEvaluation' => $forEvaluationCount,
                'newHires' => $newHiresCount,
            ],
            'charts' => [
                'barGraph' => $barGraphData,
                'pieChart' => $pieChartData,
            ]
        ]);
    }
}