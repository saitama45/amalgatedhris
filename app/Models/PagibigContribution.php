<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagibigContribution extends Model
{
    protected $fillable = [
        'effective_year',
        'is_active',
        'min_salary',
        'max_salary',
        'ee_rate',
        'er_rate',
        'max_fund_salary',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
