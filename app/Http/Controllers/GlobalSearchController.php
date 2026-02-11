<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Department;
use App\Models\Position;
use App\Models\Employee;
use App\Models\Applicant;
use Illuminate\Support\Facades\Auth;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];
        $user = Auth::user();

        if (!$user) {
            return response()->json([]);
        }

        // 1. Navigation / Menu Search (Dynamic based on permissions)
        $sidebarStructure = config('hris.sidebar_structure', []);
        $moduleLabels = config('hris.module_labels', []);
        
        // Flatten permissions for faster check and avoid Spatie exception
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        foreach ($sidebarStructure as $group => $categories) {
            foreach ($categories as $category) {
                // Check Permission (Safe check)
                $hasAccess = in_array($category . '.view', $userPermissions) || 
                             in_array($category, $userPermissions);
                
                if ($hasAccess) {
                    $label = $moduleLabels[$category] ?? ucfirst(str_replace(['.', '_'], ' ', $category));
                    
                    // Search in Label, Group, or Category key itself (for "dtr", "ot", etc.)
                    if (stripos($label, $query) !== false || 
                        stripos($group, $query) !== false || 
                        stripos($category, $query) !== false) {
                        
                        $results[] = [
                            'group' => 'Navigation',
                            'title' => $label,
                            'subtitle' => "Go to $group",
                            'url' => $this->getSafeRoute($category),
                            'icon' => $this->getIconName($category)
                        ];
                    }
                }
            }
        }

        // 2. People Search (Employees & Applicants)
        if (in_array('employees.view', $userPermissions)) {
            $employees = Employee::with(['user', 'activeEmploymentRecord.position'])
                ->where(function($q) use ($query) {
                    $q->whereHas('user', function($sq) use ($query) {
                        $sq->where('name', 'like', "%{$query}%")
                          ->orWhere('email', 'like', "%{$query}%");
                    })
                    ->orWhere('employee_code', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get();

            foreach ($employees as $emp) {
                $results[] = [
                    'group' => 'Employees',
                    'title' => $emp->user->name,
                    'subtitle' => $emp->employee_code . ' • ' . ($emp->activeEmploymentRecord->position->name ?? 'Staff'),
                    'url' => route('employees.index', ['search' => $emp->user->name]),
                    'icon' => 'UserIcon'
                ];
            }
        }

        if (in_array('applicants.view', $userPermissions)) {
            $applicants = Applicant::where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(3)
                ->get();

            foreach ($applicants as $app) {
                $results[] = [
                    'group' => 'Applicants',
                    'title' => $app->first_name . ' ' . $app->last_name,
                    'subtitle' => 'Applicant • ' . $app->email,
                    'url' => route('applicants.index', ['search' => $app->email]),
                    'icon' => 'UserIcon'
                ];
            }
        }

        // 3. Organization Search
        if (in_array('departments.view', $userPermissions)) {
            $departments = Department::where('name', 'like', "%{$query}%")
                ->limit(3)
                ->get();

            foreach ($departments as $dept) {
                $results[] = [
                    'group' => 'Organization',
                    'title' => $dept->name,
                    'subtitle' => 'Department',
                    'url' => route('departments.index', ['search' => $dept->name]),
                    'icon' => 'BuildingOfficeIcon'
                ];
            }
        }

        if (in_array('positions.view', $userPermissions)) {
            $positions = Position::where('name', 'like', "%{$query}%")
                ->limit(3)
                ->get();

            foreach ($positions as $pos) {
                $results[] = [
                    'group' => 'Organization',
                    'title' => $pos->name,
                    'subtitle' => 'Position',
                    'url' => route('positions.index', ['search' => $pos->name]),
                    'icon' => 'BriefcaseIcon'
                ];
            }
        }

        return response()->json(array_values($results));
    }

    private function getSafeRoute($category)
    {
        $customMappings = [
            'dashboard' => 'dashboard',
            'attendance.kiosk' => 'attendance.kiosk',
            'exams' => 'applicants.exams',
            'government_deductions' => 'contributions.index',
            'overtime_rates' => 'overtime-rates.index',
            'document_types' => 'document-types.index',
            'leave_requests' => 'leave-requests.index',
            'government_remittances' => 'government-remittances.index',
            'portal.dashboard' => 'portal.dashboard',
            'portal.leaves' => 'portal.leaves',
            'portal.overtime' => 'portal.overtime',
            'portal.attendance' => 'portal.attendance',
            'portal.payslips' => 'portal.payslips',
            'portal.deductions' => 'portal.deductions',
        ];

        $routeName = $customMappings[$category] ?? ($category . '.index');
        
        try {
            return route($routeName);
        } catch (\Exception $e) {
            return '#';
        }
    }

    private function getIconName($category)
    {
        $iconMap = [
            'dashboard' => 'ArrowRightCircleIcon',
            'applicants' => 'ArrowRightCircleIcon',
            'exams' => 'ArrowRightCircleIcon',
            'employees' => 'UserIcon',
            'attendance.kiosk' => 'ArrowRightCircleIcon',
            'dtr' => 'ArrowRightCircleIcon',
            'shifts' => 'ArrowRightCircleIcon',
            'schedules' => 'ArrowRightCircleIcon',
            'holidays' => 'ArrowRightCircleIcon',
            'overtime' => 'ArrowRightCircleIcon',
            'overtime_rates' => 'ArrowRightCircleIcon',
            'leave_requests' => 'ArrowRightCircleIcon',
            'government_remittances' => 'TableCellsIcon',
            'payroll' => 'ArrowRightCircleIcon',
            'government_deductions' => 'ArrowRightCircleIcon',
            'deductions' => 'ArrowRightCircleIcon',
            'portal.dashboard' => 'ArrowRightCircleIcon',
            'portal.leaves' => 'ArrowRightCircleIcon',
            'portal.overtime' => 'ArrowRightCircleIcon',
            'portal.attendance' => 'ArrowRightCircleIcon',
            'portal.payslips' => 'ArrowRightCircleIcon',
            'portal.deductions' => 'ArrowRightCircleIcon',
            'users' => 'UserIcon',
            'companies' => 'BuildingOfficeIcon',
            'departments' => 'BuildingOfficeIcon',
            'positions' => 'BriefcaseIcon',
            'document_types' => 'ArrowRightCircleIcon',
            'roles' => 'ArrowRightCircleIcon'
        ];

        return $iconMap[$category] ?? 'ArrowRightCircleIcon';
    }
}
