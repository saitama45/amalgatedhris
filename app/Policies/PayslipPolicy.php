<?php

namespace App\Policies;

use App\Models\Payslip;
use App\Models\User;

class PayslipPolicy
{
    /**
     * Determine whether the user can view the payslip.
     */
    public function view(User $user, Payslip $payslip): bool
    {
        // HR/Admin with payroll.view permission can see all
        if ($user->can('payroll.view')) {
            return true;
        }

        // Employee can see their own payslip
        return $user->employee && $user->employee->id == $payslip->employee_id;
    }
}
