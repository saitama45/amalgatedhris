<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithholdingTaxBracket extends Model
{
    protected $fillable = [
        'effective_year',
        'min_salary',
        'max_salary',
        'base_tax',
        'percentage',
        'excess_over',
        'is_active',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
