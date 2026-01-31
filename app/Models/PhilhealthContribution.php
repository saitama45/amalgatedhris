<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhilhealthContribution extends Model
{
    protected $fillable = [
        'effective_year',
        'is_active',
        'min_salary',
        'max_salary',
        'rate',
        'er_share_percent',
        'ee_share_percent',
        'is_fixed_amount',
        'fixed_amount',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
