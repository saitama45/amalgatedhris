<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_rest_day' => 'boolean',
        'is_holiday' => 'boolean',
        'hours_requested' => 'decimal:2',
        'payable_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Helper to calculate total payable based on dynamic rates in database
     */
    public static function calculatePayable($basicSalary, $hours, $isRestDay, $isHoliday, $holidayType = null)
    {
        // hourly rate = monthly salary / 26 days / 8 hours
        $hourlyRate = $basicSalary / 26 / 8;
        
        // Fetch all active rates
        $rates = OvertimeRate::where('is_active', true)->pluck('rate', 'key');

        $multiplierKey = 'regular_ot'; // Default

        if ($isHoliday && $isRestDay) {
            $multiplierKey = 'holiday_rest_day_ot';
        } elseif ($isHoliday) {
            $multiplierKey = 'holiday_ot';
        } elseif ($isRestDay) {
            $multiplierKey = 'rest_day_ot';
        } else {
            $multiplierKey = 'regular_ot';
        }

        // Get multiplier from DB, fallback to hardcoded if key missing (failsafe)
        $multiplier = $rates->get($multiplierKey, match($multiplierKey) {
            'holiday_rest_day_ot' => 3.38,
            'holiday_ot' => 2.60,
            'rest_day_ot' => 1.69,
            default => 1.25
        });

        return [
            'multiplier' => $multiplier,
            'hourly_rate' => $hourlyRate,
            'total' => $hourlyRate * $multiplier * $hours
        ];
    }
}
