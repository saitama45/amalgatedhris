<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'company_id',
        'department_id',
        'position_id',
        'basic_rate',
        'allowance',
        'allowance_15th',
        'allowance_30th',
        'default_shift_id',
        'work_days',
        'grace_period_minutes',
        'late_policy', // 'exact' or 'block_30'
        'is_ot_allowed',
        'employment_status',
        'start_date',
        'end_date',
        'is_active',
        'is_sss_deducted',
        'is_philhealth_deducted',
        'is_pagibig_deducted',
        'is_withholding_tax_deducted',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'is_ot_allowed' => 'boolean',
        'is_sss_deducted' => 'boolean',
        'is_philhealth_deducted' => 'boolean',
        'is_pagibig_deducted' => 'boolean',
        'is_withholding_tax_deducted' => 'boolean',
        'basic_rate' => 'float',
        'allowance' => 'float',
        'allowance_15th' => 'float',
        'allowance_30th' => 'float',
    ];

    public function defaultShift()
    {
        return $this->belongsTo(Shift::class, 'default_shift_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function salaryHistories()
    {
        return $this->hasMany(SalaryHistory::class);
    }
}