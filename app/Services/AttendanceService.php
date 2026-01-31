<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\AttendanceLog;
use App\Models\EmploymentRecord;

class AttendanceService
{
    /**
     * Calculate late minutes based on Employment Record policy.
     *
     * @param Carbon $scheduledStart
     * @param Carbon $actualTimeIn
     * @param EmploymentRecord $record
     * @return int
     */
    public function calculateLateMinutes(Carbon $scheduledStart, Carbon $actualTimeIn, EmploymentRecord $record): int
    {
        // 1. Calculate raw difference in minutes
        // If early or on time, diffInMinutes might be positive but we check if actual > scheduled
        if ($actualTimeIn->lte($scheduledStart)) {
            return 0;
        }

        $rawLate = $scheduledStart->diffInMinutes($actualTimeIn);

        // 2. Check Grace Period
        // If within grace period, no late
        if ($rawLate <= $record->grace_period_minutes) {
            return 0;
        }

        // 3. Apply Late Policy
        // 'exact': Just the raw minutes
        // 'block_30': If late > grace, round up to nearest 30 mins
        //   Example: Grace 5.
        //   8:05 -> 0 late.
        //   8:06 -> 6 mins raw -> Round up to 30.
        //   8:30 -> 30 mins raw -> Round up to 30 (or kept as 30).
        //   8:31 -> 31 mins raw -> Round up to 60.
        
        if ($record->late_policy === 'block_30') {
            // New "Fair" Logic:
            // If late is between grace period (exclusive) and 30 mins (inclusive) -> 30 mins late.
            // If late > 30 mins -> exact minutes late.
            
            if ($rawLate <= 30) {
                return 30;
            }
            
            return $rawLate;
        }

        // Default 'exact'
        return $rawLate;
    }

    /**
     * Calculate overtime minutes based on Employment Record policy.
     *
     * @param Carbon $scheduledEnd
     * @param Carbon $actualTimeOut
     * @param EmploymentRecord $record
     * @return int
     */
    public function calculateOvertimeMinutes(Carbon $scheduledEnd, Carbon $actualTimeOut, EmploymentRecord $record): int
    {
        if (!$record->is_ot_allowed) {
            return 0;
        }

        // If timed out before or at scheduled end, no OT
        if ($actualTimeOut->lte($scheduledEnd)) {
            return 0;
        }

        $rawOT = $scheduledEnd->diffInMinutes($actualTimeOut);

        // Optional: Could add a minimum OT duration here (e.g., must be > 30 mins to count)
        // For now, we'll return raw minutes.
        return $rawOT;
    }

    /**
     * Record time in for an employee.
     * This is a helper to simulate how it would be used.
     */
    public function recordTimeIn($employeeId, $datetime)
    {
        // Logic to find schedule, get active employment record, etc.
        // This is a placeholder for the actual implementation.
        // ...
    }
}
