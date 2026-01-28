<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'date',
        'start_time',
        'end_time',
        'break_minutes',
        'grace_period_minutes',
        'is_ot_allowed',
        'is_rest_day',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
        'is_rest_day' => 'boolean',
        'is_ot_allowed' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}