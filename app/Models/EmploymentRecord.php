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
        'default_shift_id',
        'work_days',
        'grace_period_minutes',
        'late_policy', // 'exact' or 'block_30'
        'is_ot_allowed',
        'employment_status',
        'start_date',
        'end_date',
        'is_active',
        'sss_deduction_schedule',
        'philhealth_deduction_schedule',
        'pagibig_deduction_schedule',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_ot_allowed' => 'boolean',
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