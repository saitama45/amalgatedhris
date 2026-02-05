<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Support\Facades\Gate;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        // Search Users/Employees
        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(5)
            ->get();

        foreach ($users as $user) {
                // Determine if user is an employee
                $type = $user->employee ? 'Employee' : 'User';
                $url = $user->employee 
                    ? route('employees.index', ['search' => $user->name]) 
                    : route('users.index', ['search' => $user->name]);

                $results[] = [
                    'group' => 'People',
                    'title' => $user->name,
                    'subtitle' => $user->email,
                    'url' => $url,
                    'icon' => 'UserIcon'
                ];
            }

        // Search Departments
        $departments = Department::where('name', 'like', "%{$query}%")
            ->limit(3)
            ->get();

        foreach ($departments as $dept) {
            $results[] = [
                'group' => 'Departments',
                'title' => $dept->name,
                'subtitle' => 'Department',
                'url' => route('departments.index', ['search' => $dept->name]),
                'icon' => 'BuildingOfficeIcon'
            ];
        }

        // Search Positions
        $positions = Position::where('name', 'like', "%{$query}%")
            ->limit(3)
            ->get();

        foreach ($positions as $pos) {
            $results[] = [
                'group' => 'Positions',
                'title' => $pos->name,
                'subtitle' => 'Position',
                'url' => route('positions.index', ['search' => $pos->name]),
                'icon' => 'BriefcaseIcon'
            ];
        }
        
        // Navigation / Menu Search (Static)
        $menuItems = [
            ['title' => 'Dashboard', 'url' => route('dashboard'), 'keywords' => 'home main stats'],
            ['title' => 'Employees', 'url' => route('employees.index'), 'keywords' => 'staff people directory'],
            ['title' => 'Departments', 'url' => route('departments.index'), 'keywords' => 'offices teams'],
            ['title' => 'Positions', 'url' => route('positions.index'), 'keywords' => 'job titles ranks'],
            ['title' => 'Payroll', 'url' => route('payroll.index'), 'keywords' => 'salary wages'],
            ['title' => 'Attendance', 'url' => route('dtr.index'), 'keywords' => 'time logs dtr'],
            ['title' => 'Leave Requests', 'url' => route('leave-requests.index'), 'keywords' => 'vacation sick time off'],
            ['title' => 'Overtime', 'url' => route('overtime.index'), 'keywords' => 'ot extra hours'],
        ];

        foreach ($menuItems as $item) {
            if (stripos($item['title'], $query) !== false || stripos($item['keywords'], $query) !== false) {
                $results[] = [
                    'group' => 'Navigation',
                    'title' => $item['title'],
                    'subtitle' => 'Go to page',
                    'url' => $item['url'],
                    'icon' => 'ArrowRightCircleIcon'
                ];
            }
        }

        return response()->json($results);
    }
}
