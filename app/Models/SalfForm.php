<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalfForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'department_id',
        'company_id',
        'period_covered',
        'approved_by',
        'status',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalfItem::class)->orderBy('order');
    }

    public function getOverallEfficiencyAttribute(): float
    {
        $items = $this->items;
        if ($items->isEmpty()) {
            return 0;
        }

        $totalTarget = $items->sum('target_value');
        if ($totalTarget == 0) {
            return 0;
        }

        $totalActual = $items->sum('actual_value');
        return round(($totalActual / $totalTarget) * 100, 2);
    }
}
