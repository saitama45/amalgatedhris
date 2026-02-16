<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeaveRequestPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        // Admin/HR can see all
        if ($user->can('leave_requests.view_all')) {
            return true;
        }

        // Owner can see their own
        if ($leaveRequest->employee->user_id === $user->id) {
            return true;
        }

        // Immediate Head can see subordinates
        if ($user->employee && $leaveRequest->employee->immediate_head_id === $user->employee->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can approve/reject the model.
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        // Admin/HR can approve all
        if ($user->can('leave_requests.approve')) {
            return true;
        }

        // Immediate Head can approve their subordinates
        if ($user->employee && $leaveRequest->employee->immediate_head_id === $user->employee->id) {
            return true;
        }

        return false;
    }
}
