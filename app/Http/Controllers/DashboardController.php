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
        $totalEmployees = EmploymentRecord::where('is_active', true)->count();

        // 2. For Evaluation (Hired 5 months ago)
        // We look for employees whose start_date month is exactly 5 months ago from now
        $fiveMonthsAgo = now()->subMonths(5);
        $forEvaluation = EmploymentRecord::where('is_active', true)
            ->whereMonth('start_date', $fiveMonthsAgo->month)
            ->whereYear('start_date', $fiveMonthsAgo->year)
            ->count();

        // 3. New Hires (This Month)
        $newHires = EmploymentRecord::where('is_active', true)
            ->whereMonth('start_date', now()->month)
            ->whereYear('start_date', now()->year)
            ->count();

        // 4. Bar Graph: Count per Dept per Company
        // Structure: { "Company A": { "Dept 1": 10, "Dept 2": 5 }, "Company B": ... }
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
            'colors' => [] // We can generate colors frontend or backend. Let's send labels and data.
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
            'stats' => [
                'totalEmployees' => $totalEmployees,
                'forEvaluation' => $forEvaluation,
                'newHires' => $newHires,
            ],
            'charts' => [
                'barGraph' => $barGraphData,
                'pieChart' => $pieChartData,
            ]
        ]);
    }
}