<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'payroll_id',
        'amount',
        'type',
        'reason',
        'is_taxable',
        'payout_date',
        'status',
        'created_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'is_taxable' => 'boolean',
        'payout_date' => 'date',
        'processed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}