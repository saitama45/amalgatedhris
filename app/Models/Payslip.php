<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'employee_id',
        'basic_pay',
        'gross_pay',
        'allowances',
        'ot_pay',
        'adjustments',
        'late_deduction',
        'undertime_deduction',
        'sss_deduction',
        'philhealth_ded',
        'pagibig_ded',
        'tax_withheld',
        'loan_deductions',
        'other_deductions',
        'net_pay',
        'months_worked',
        'details' // JSON for breakdown
    ];

    protected $casts = [
        'details' => 'array',
        'basic_pay' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}