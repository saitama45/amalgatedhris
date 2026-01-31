<?php

namespace Tests\Feature;

use App\Imports\AttendanceImport;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_logic_transforms_data_correctly()
    {
        // Setup Employee
        $user = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_code' => 'EMP001',
        ]);
        
        // Mock Employment Record and Shift for calculations
        $shift = Shift::create([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
        ]);
        
        $company = \App\Models\Company::create(['name' => 'Test Company', 'code' => 'TC', 'is_active' => true]);
        $department = \App\Models\Department::create(['name' => 'Test Dept', 'code' => 'TD']);
        $position = \App\Models\Position::create(['name' => 'Test Pos', 'department_id' => $department->id]);

        $record = EmploymentRecord::create([
            'employee_id' => $employee->id,
            'start_date' => now(),
            'default_shift_id' => $shift->id,
            'department_id' => $department->id, 
            'position_id' => $position->id, 
            'company_id' => $company->id, 
            'employment_status' => 'Regular',
        ]);

        $service = new AttendanceService();
        $import = new AttendanceImport($service);

        // Simulate Row Data (Slugified Headers)
        $rows = new Collection([
            new Collection([
                'employee_id' => $employee->id,
                'date_yyyy_mm_dd' => '1/31/2026', // Testing m/d/Y format
                'time_in_hhmm' => '08:00',
                'time_out_hhmm' => '17:00',
            ])
        ]);

        // Run Import Logic
        $import->collection($rows);

        // Verify Database
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $employee->id,
            'date' => '2026-01-31',
        ]);
        
        $log = \App\Models\AttendanceLog::where('employee_id', $employee->id)->first();
        
        dump('Time In: ' . $log->time_in);
        dump('Time Out: ' . $log->time_out);

        $this->assertNotNull($log->time_in);
        $this->assertNotNull($log->time_out);
    }
}
