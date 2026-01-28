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
        'ot_pay',
        'sss_deduction',
        'philhealth_ded',
        'pagibig_ded',
        'tax_withheld',
        'loan_deductions',
        'net_pay',
        'months_worked',
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
