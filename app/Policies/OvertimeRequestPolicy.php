<?php

namespace App\Policies;

use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OvertimeRequestPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($user->can('overtime.view_all')) {
            return true;
        }

        if ($overtimeRequest->user_id === $user->id) {
            return true;
        }

        // Immediate Head check
        if ($user->employee && $overtimeRequest->user->employee && $overtimeRequest->user->employee->immediate_head_id === $user->employee->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can approve/reject the model.
     */
    public function approve(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($user->can('overtime.approve')) {
            return true;
        }

        // Immediate Head can approve their subordinates
        if ($user->employee && $overtimeRequest->user->employee && $overtimeRequest->user->employee->immediate_head_id === $user->employee->id) {
            return true;
        }

        return false;
    }
}
