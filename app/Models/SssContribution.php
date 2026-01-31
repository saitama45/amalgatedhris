<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SssContribution extends Model
{
    protected $fillable = [
        'effective_year',
        'is_active',
        'min_salary',
        'max_salary',
        'msc',
        'er_share',
        'ee_share',
        'ec_share',
        'wisp_er_share',
        'wisp_ee_share',
        'total_contribution',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
