<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'cutoff_start',
        'cutoff_end',
        'payout_date',
        'status', // Draft, Finalized, Paid
        'remarks'
    ];

    protected $casts = [
        'cutoff_start' => 'date',
        'cutoff_end' => 'date',
        'payout_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }
}