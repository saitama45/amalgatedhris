<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = LeaveRequest::with(['employee.user', 'leaveType', 'approver']);

        // HIERARCHY LOGIC
        // 1. If user is Admin/HR (has global view permission), they see all.
        // 2. If user is an Immediate Head (has subordinates), they see their subordinates.
        // 3. Otherwise, they only see their own.
        
        if (!$user->can('leave_requests.view_all')) {
            $employee = $user->employee;
            
            if ($employee && $employee->subordinates()->exists()) {
                // User is an Immediate Head - See ONLY subordinates (not own)
                $subordinateIds = $employee->subordinates()->pluck('id')->toArray();
                $query->whereIn('employee_id', $subordinateIds);
            } else {
                // Regular user - See only own
                $query->whereHas('employee', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }
        }

        $requests = $query->latest()->paginate($request->get('per_page', 10));

        // Filter employees list for the creation modal
        $employeesList = [];
        if ($user->can('leave_requests.view_all')) {
            $employeesList = Employee::with('user')->get();
        } elseif ($user->employee && $user->employee->subordinates()->exists()) {
            $employeesList = $user->employee->subordinates()->with('user')->get();
        }

        return Inertia::render('Leave/Index', [
            'requests' => $requests,
            'leaveTypes' => LeaveType::all(),
            'employees' => collect($employeesList)->map(fn($e) => ['id' => $e->id, 'name' => $e->user->name]),
            'filters' => $request->only(['status'])
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $validated = $request->validate([
            'employee_id' => $user->can('leave_requests.create') ? 'required|exists:employees,id' : 'nullable',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        if (!$user->can('leave_requests.create')) {
            $validated['employee_id'] = $employee->id;
        }

        $validated['status'] = 'Pending';

        LeaveRequest::create($validated);

        return back()->with('success', 'Leave request submitted.');
    }

    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        $user = auth()->user();

        if ($leaveRequest->status !== 'Pending' && !$user->can('leave_requests.approve')) {
            return back()->with('error', 'Cannot edit processed leave request.');
        }

        $validated = $request->validate([
            'employee_id' => $user->can('leave_requests.edit') ? 'required|exists:employees,id' : 'nullable',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        if ($user->can('leave_requests.edit')) {
            $leaveRequest->update($validated);
        } else {
            // Regular user can only edit their own pending requests
            if ($leaveRequest->employee->user_id === $user->id && $leaveRequest->status === 'Pending') {
                unset($validated['employee_id']); // Cannot change employee
                $leaveRequest->update($validated);
            } else {
                abort(403);
            }
        }

        return back()->with('success', 'Leave request updated.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorize('approve', $leaveRequest);

        $leaveRequest->update([
            'status' => 'Approved',
            'approved_by' => auth()->id()
        ]);

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorize('approve', $leaveRequest);

        $leaveRequest->update([
            'status' => 'Rejected',
            'approved_by' => auth()->id()
        ]);

        return back()->with('success', 'Leave request rejected.');
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        $user = auth()->user();
        
        if ($leaveRequest->status !== 'Pending' && !$user->can('leave_requests.delete')) {
            return back()->with('error', 'Cannot delete processed leave request.');
        }

        // Check if it's their own or they have management permission
        if ($leaveRequest->employee->user_id === $user->id || $user->can('leave_requests.delete')) {
            $leaveRequest->delete();
            return back()->with('success', 'Leave request deleted.');
        }

        abort(403);
    }
}