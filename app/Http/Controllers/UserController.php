<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::has('employee')->with([
            'roles:id,name',
            'employee.activeEmploymentRecord.department',
            'employee.activeEmploymentRecord.position'
        ]);
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhereHas('employee.activeEmploymentRecord.department', function($dq) use ($request) {
                      $dq->where('name', 'like', "%{$request->search}%");
                  })
                  ->orWhereHas('employee.activeEmploymentRecord.position', function($pq) use ($request) {
                      $pq->where('name', 'like', "%{$request->search}%");
                  });
            });
        }
        
        $users = $query->paginate($request->get('per_page', 10))->withQueryString();
        
        // Transform the collection to include department and position from the employment record
        $users->getCollection()->transform(function($user) {
            $activeRecord = $user->employee?->activeEmploymentRecord;
            $user->department = $activeRecord?->department?->name ?? 'General';
            $user->position = $activeRecord?->position?->name ?? 'Unassigned';
            $user->rank = $activeRecord?->position?->rank ?? 'N/A';
            return $user;
        });

        $roles = \Spatie\Permission\Models\Role::select('id', 'name')->get();
        
        // Get all employees who are already hired
        $employees = \App\Models\Employee::with([
            'user',
            'activeEmploymentRecord.department',
            'activeEmploymentRecord.position'
        ])->get()->map(function($emp) {
            return [
                'name' => $emp->user->name,
                'employee_id' => $emp->id,
                'email' => $emp->user->email,
                'department_name' => $emp->activeEmploymentRecord?->department?->name ?? 'N/A',
                'position_name' => $emp->activeEmploymentRecord?->position?->name ?? 'N/A',
                'rank' => $emp->activeEmploymentRecord?->position?->rank ?? 'N/A'
            ];
        });

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|exists:roles,name',
            'applicant_id' => 'nullable|exists:applicants,id',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);

            $user->assignRole($request->role);

            if ($request->filled('applicant_id')) {
                $applicant = \App\Models\Applicant::find($request->applicant_id);
                
                // Create Employee record if it's an applicant
                $employee = \App\Models\Employee::create([
                    'user_id' => $user->id,
                    'employee_code' => 'EMP-' . now()->year . '-' . str_pad($user->id, 4, '0', STR_PAD_LEFT),
                ]);

                // Transfer documents
                \App\Models\EmployeeDocument::where('applicant_id', $applicant->id)
                    ->update(['employee_id' => $employee->id, 'applicant_id' => null]);

                $applicant->update(['status' => 'hired']);
            } elseif ($request->filled('employee_id')) {
                // Link existing employee to this new user? 
                // Wait, Employee already has a user_id which is unique.
                // This case should probably not happen if we filter the dropdown.
            }
        });

        return redirect()->back()->with('success', 'Personnel registered successfully.');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string|exists:roles,name',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->department = $request->department;
        $user->position = $request->position;
        $user->save();

        $user->syncRoles([$request->role]);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Password reset successfully.');
    }
}