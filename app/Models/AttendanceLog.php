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
        'is_ob',
        'in_latitude',
        'in_longitude',
        'in_photo_path',
        'in_location_url',
        'out_latitude',
        'out_longitude',
        'out_photo_path',
        'out_location_url',
        'is_approved',
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
     * Create or update attendance log for Official Business (OB).
     * 
     * @param Employee $employee
     * @param \Carbon\Carbon $dateTime
     * @param string $type 'in' or 'out'
     * @param array $data ['latitude', 'longitude', 'photo_path']
     * @return self
     */
    public static function logOB($employee, $dateTime, $type = 'in', $data = [])
    {
        $date = $dateTime->toDateString();
        
        $log = self::firstOrNew(
            ['employee_id' => $employee->id, 'date' => $date]
        );

        if (!$log->exists) {
            $log->status = 'Absent';
            $log->is_ob = true;
        }

        $lat = $data['latitude'] ?? null;
        $long = $data['longitude'] ?? null;
        $photo = $data['photo_path'] ?? null;
        $locationUrl = ($lat && $long) ? "https://www.google.com/maps?q={$lat},{$long}" : null;

        if ($type === 'in') {
            $log->time_in = $dateTime;
            $log->in_latitude = $lat;
            $log->in_longitude = $long;
            $log->in_photo_path = $photo;
            $log->in_location_url = $locationUrl;
            $log->is_ob = true;
            $log->status = 'Present';

            $activeRecord = $employee->activeEmploymentRecord;
            if ($activeRecord && $activeRecord->defaultShift) {
                $service = new \App\Services\AttendanceService();
                $scheduledStart = \Carbon\Carbon::parse($date . ' ' . $activeRecord->defaultShift->start_time);
                
                $rawLate = $scheduledStart->diffInMinutes($dateTime, false);
                if ($rawLate > 120 && $rawLate <= 300) {
                    $log->status = 'Half Day';
                    $log->late_minutes = 0;
                } else {
                    $log->late_minutes = $service->calculateLateMinutes($scheduledStart, $dateTime, $activeRecord);
                    if ($log->late_minutes > 0) {
                        $log->status = 'Late';
                    }
                }
            }
        } else {
            $log->time_out = $dateTime;
            $log->out_latitude = $lat;
            $log->out_longitude = $long;
            $log->out_photo_path = $photo;
            $log->out_location_url = $locationUrl;
            
            $activeRecord = $employee->activeEmploymentRecord;
            if ($activeRecord && $activeRecord->defaultShift) {
                $service = new \App\Services\AttendanceService();
                $scheduledEnd = \Carbon\Carbon::parse($date . ' ' . $activeRecord->defaultShift->end_time);
                $log->ot_minutes = $service->calculateOvertimeMinutes($scheduledEnd, $dateTime, $activeRecord);
            }
        }

        $log->save();

        return $log;
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
            $log->status = 'Present';

            $activeRecord = $employee->activeEmploymentRecord;
            if ($activeRecord && $activeRecord->defaultShift) {
                $service = new \App\Services\AttendanceService();
                $scheduledStart = \Carbon\Carbon::parse($date . ' ' . $activeRecord->defaultShift->start_time);
                
                $rawLate = $scheduledStart->diffInMinutes($dateTime);
                if ($rawLate > 120 && $rawLate <= 300) {
                    $log->status = 'Half Day';
                    $log->late_minutes = 0;
                } else {
                    $log->late_minutes = $service->calculateLateMinutes($scheduledStart, $dateTime, $activeRecord);
                    if ($log->late_minutes > 0) {
                        $log->status = 'Late';
                    }
                }
            }
        } else {
            $log->time_out = $dateTime;
            
            $activeRecord = $employee->activeEmploymentRecord;
            if ($activeRecord && $activeRecord->defaultShift) {
                $service = new \App\Services\AttendanceService();
                $scheduledEnd = \Carbon\Carbon::parse($date . ' ' . $activeRecord->defaultShift->end_time);
                $log->ot_minutes = $service->calculateOvertimeMinutes($scheduledEnd, $dateTime, $activeRecord);
            }
        }

        $log->save();

        return $log;
    }
}