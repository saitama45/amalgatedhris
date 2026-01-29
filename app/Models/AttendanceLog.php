<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'time_in',
        'time_out',
        'status',
        'late_minutes',
        'ot_minutes',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'time_in' => 'datetime',
        'time_out' => 'datetime',
    ];

    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Create or update attendance log.
     * 
     * @param Employee $employee
     * @param \Carbon\Carbon $dateTime
     * @param string $type 'in' or 'out'
     * @return self
     */
    public static function log($employee, $dateTime, $type = 'in')
    {
        $date = $dateTime->toDateString();
        
        $log = self::firstOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            ['status' => 'Absent'] // Default
        );

        if ($type === 'in') {
            $log->time_in = $dateTime;
            $log->status = 'Present'; // Tentative

            // Calculate Late if Schedule exists
            // This assumes logic to fetch schedule exists.
            // For now, we demonstrate the calculation usage:
            $activeRecord = $employee->activeEmploymentRecord;
            
            // Hypothetical: Fetch today's schedule start time
            // $schedule = ...; 
            // $scheduledStart = ...;

            // Example integration (commented out as it depends on Schedule logic):
            // if ($activeRecord && $scheduledStart) {
            //     $service = new \App\Services\AttendanceService();
            //     $log->late_minutes = $service->calculateLateMinutes($scheduledStart, $dateTime, $activeRecord);
            //     if ($log->late_minutes > 0) {
            //         $log->status = 'Late';
            //     }
            // }
        } else {
            $log->time_out = $dateTime;
            // Calculate OT here if needed
        }

        $log->save();

        return $log;
    }
}