<?php

namespace App\Imports;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AttendanceImport implements ToCollection, WithHeadingRow
{
    protected $attendanceService;
    protected $results = [
        'imported' => 0,
        'skipped' => 0,
        'errors' => []
    ];

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function getResults()
    {
        return $this->results;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Laravel Excel slugifies headers.
            // "Employee ID" -> employee_id
            // "Date (YYYY-MM-DD)" -> date_yyyy_mm_dd
            // "Time In (HH:MM)" -> time_in_hh_mm
            // "Time Out (HH:MM)" -> time_out_hh_mm

            $employeeId = $row['employee_id'] ?? null;
            
            // Allow flexibility in headers
            // slug('Date (YYYY-MM-DD)', '_') -> date_yyyy_mm_dd
            // slug('Time In (HH:MM)', '_') -> time_in_hhmm (sometimes) or time_in_hh_mm
            $dateVal = $row['date_yyyy_mm_dd'] ?? $row['date'] ?? $row['date_yyyymmdd'] ?? null;
            $timeInVal = $row['time_in_hh_mm'] ?? $row['time_in_hhmm'] ?? $row['time_in'] ?? null;
            $timeOutVal = $row['time_out_hh_mm'] ?? $row['time_out_hhmm'] ?? $row['time_out'] ?? null;

            if (!$employeeId || !$dateVal) {
                $this->results['skipped']++;
                $this->results['errors'][] = "Row skipped: Missing Employee ID or Date";
                Log::warning('Skipping row due to missing ID or Date', ['id' => $employeeId, 'date' => $dateVal]);
                continue;
            }

            try {
                // Priority: Lookup by Employee Code (as per import file spec)
                $employee = Employee::where('employee_code', (string)$employeeId)->first();

                // Fallback: Lookup by ID only if input is numeric to avoid SQL conversion errors
                if (!$employee && is_numeric($employeeId)) {
                    $employee = Employee::find($employeeId);
                }

                if (!$employee) {
                    $this->results['skipped']++;
                    $this->results['errors'][] = "Employee not found: ID/Code $employeeId";
                    Log::warning("Employee not found: $employeeId");
                    continue; 
                }

                $dateOnly = $this->transformDate($dateVal);
                if (!$dateOnly) {
                     $this->results['skipped']++;
                     $this->results['errors'][] = "Invalid Date Format: $dateVal (Employee: $employeeId)";
                     Log::warning("Invalid Date Format: $dateVal");
                     continue;
                }

                $timeIn = $this->transformTime($dateOnly, $timeInVal);
                $timeOut = $this->transformTime($dateOnly, $timeOutVal);

                // Recalculate Logic
                $status = 'Present';
                $lateMinutes = 0;
                $otMinutes = 0;

                if (!$timeIn && !$timeOut) {
                    $status = 'Absent';
                } elseif (!$timeIn || !$timeOut) {
                    $status = 'Incomplete';
                }

                if ($timeIn && $employee->activeEmploymentRecord && $employee->activeEmploymentRecord->defaultShift) {
                    $shiftStart = Carbon::parse($dateOnly . ' ' . $employee->activeEmploymentRecord->defaultShift->start_time);
                    $lateMinutes = $this->attendanceService->calculateLateMinutes($shiftStart, $timeIn, $employee->activeEmploymentRecord);
                    
                    // If late is in the afternoon amnesty window (10:01 AM - 1:00 PM), mark as Half Day
                    $rawLate = $shiftStart->diffInMinutes($timeIn);
                    if ($rawLate > 120 && $rawLate <= 300) {
                        $status = 'Half Day';
                    } elseif ($lateMinutes > 0) {
                        $status = 'Late';
                    }
                }

                if ($timeOut && $employee->activeEmploymentRecord && $employee->activeEmploymentRecord->defaultShift) {
                    $shiftEnd = Carbon::parse($dateOnly . ' ' . $employee->activeEmploymentRecord->defaultShift->end_time);
                    $shiftStart = Carbon::parse($dateOnly . ' ' . $employee->activeEmploymentRecord->defaultShift->start_time);

                    if ($shiftEnd->lt($shiftStart)) {
                        $shiftEnd->addDay();
                    }
                    
                    // Cross-day check for actual time
                    if ($timeIn && $timeOut->lt($timeIn)) {
                        $timeOut->addDay();
                    }

                    $otMinutes = $this->attendanceService->calculateOvertimeMinutes($shiftEnd, $timeOut, $employee->activeEmploymentRecord);
                }

                AttendanceLog::updateOrCreate(
                    ['employee_id' => $employee->id, 'date' => $dateOnly],
                    [
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                        'status' => $status,
                        'late_minutes' => $lateMinutes,
                        'ot_minutes' => $otMinutes,
                    ]
                );

                $this->results['imported']++;

            } catch (\Exception $e) {
                $this->results['skipped']++;
                // Provide a friendly error message, hiding raw SQL details
                $msg = $e->getMessage();
                if (str_contains($msg, 'SQLSTATE')) {
                    $msg = "Database error. Please check the data format.";
                }
                $this->results['errors'][] = "Error for Employee $employeeId: " . $msg;
                Log::error("Error importing row for employee $employeeId: " . $e->getMessage());
            }
        }
    }

    private function transformDate($value)
    {
        if (!$value) return null;
        try {
            if (is_numeric($value)) {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            }
            
            // Try standard parse
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e) {
                // Try specific formats if standard fails (though parse is usually good)
                // e.g. 1/31/2026 (m/d/Y) or 31/1/2026 (d/m/Y) - Parse usually guesses US format m/d/y with slashes
                // Let's try explicitly if needed, but Carbon::parse('1/31/2026') works.
                // Log failure to understand why
                Log::warning("Date parse failed for: " . $value . " Error: " . $e->getMessage());
                return null;
            }
        } catch (\Exception $e) {
            Log::error("TransformDate critical error: " . $e->getMessage());
            return null;
        }
    }

    private function transformTime($dateStr, $value)
    {
        if (!$value) return null;
        try {
            if (is_numeric($value)) {
                $timePart = Date::excelToDateTimeObject($value)->format('H:i:s');
                return Carbon::parse("$dateStr $timePart");
            }
            return Carbon::parse("$dateStr $value");
        } catch (\Exception $e) {
            return null;
        }
    }
}
