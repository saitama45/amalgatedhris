<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'loan_type',
        'principal',
        'amortization',
        'balance',
        'status'
    ];

    protected $casts = [
        'principal' => 'decimal:2',
        'amortization' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}