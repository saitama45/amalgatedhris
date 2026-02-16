<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfidentialEmail extends Model
{
    protected $fillable = [
        'email',
        'can_view_salary',
        'can_manage_payroll',
    ];

    protected $casts = [
        'can_view_salary' => 'boolean',
        'can_manage_payroll' => 'boolean',
    ];
}
