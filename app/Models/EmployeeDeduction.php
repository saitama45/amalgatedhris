<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'deduction_type_id',
        'calculation_type',
        'amount',
        'frequency',
        'schedule',
        'total_amount',
        'remaining_balance',
        'terms',
        'effective_date',
        'status'
    ];

    protected $casts = [
        'effective_date' => 'date',
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductionType()
    {
        return $this->belongsTo(DeductionType::class);
    }
}