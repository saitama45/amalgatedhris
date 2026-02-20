<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalfItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'salf_form_id',
        'is_header',
        'section',
        'area_of_concern',
        'action_plan',
        'support_group',
        'target_date',
        'actual_value',
        'target_value',
        'remarks',
        'order',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'target_date' => 'date',
        'actual_value' => 'float',
        'target_value' => 'float',
        'efficiency' => 'float',
    ];

    public function salfForm(): BelongsTo
    {
        return $this->belongsTo(SalfForm::class);
    }

    public function getEfficiencyAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        if ($this->target_value == 0) {
            return 0;
        }

        return round(($this->actual_value / $this->target_value) * 100, 2);
    }
}
