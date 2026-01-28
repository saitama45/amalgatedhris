<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\Position;
use App\Models\SalaryHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalaryHistoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_salary_history_index_returns_correct_date_format()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        
        $company = Company::create(['name' => 'Test Company', 'code' => 'TC']);
        $department = Department::create(['name' => 'IT', 'department_code' => 'IT01']);
        $position = Position::create(['name' => 'Developer']);
        
        $empUser = User::factory()->create();
        $employee = Employee::create([
            'user_id' => $empUser->id,
            'employee_code' => 'EMP001',
        ]);
        
        $record = EmploymentRecord::create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employment_status' => 'Regular',
            'start_date' => '2025-01-01',
            'is_active' => true,
        ]);
        
        // Target Date: 2026-01-28
        // If app timezone is Manila (+8), this is 2026-01-28 00:00:00 PHT
        // In UTC it is 2026-01-27 16:00:00 UTC
        // Standard Laravel Serialization would return "2026-01-27T16:00:00.000000Z"
        // We expect "2026-01-28"
        $targetDate = '2026-01-28';
        
        SalaryHistory::create([
            'employment_record_id' => $record->id,
            'basic_rate' => 50000,
            'allowance' => 2000,
            'effective_date' => $targetDate,
        ]);
        
        // 2. Act
        $response = $this->actingAs($user)->getJson(route('employees.salary.index', $employee->id));
        
        // 3. Assert
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(1, $data);
        
        // Verify we get the plain date string matching input
        $this->assertSame($targetDate, $data[0]['effective_date']);
    }

    public function test_can_delete_salary_history()
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        
        $user = User::factory()->create();
        
        // Setup Permissions
        // Setup Permissions
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'employees.delete_salary']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        // Setup Dependencies
        $company = Company::create(['name' => 'Test Co', 'code' => 'TC']);
        $department = Department::create(['name' => 'IT', 'department_code' => 'IT01']);
        $position = Position::create(['name' => 'Dev']);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_code' => 'EMP_DEL',
        ]);
        
        $record = EmploymentRecord::create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employment_status' => 'Regular',
            'start_date' => '2025-01-01',
            'is_active' => true,
        ]);
        
        $salary = SalaryHistory::create([
            'employment_record_id' => $record->id,
            'basic_rate' => 50000,
            'allowance' => 2000,
            'effective_date' => '2026-01-28',
        ]);
        
        $response = $this->actingAs($user)
            ->from('/employees/' . $employee->id . '/salary') // Fake referrer
            ->delete(route('salary-history.destroy', $salary->id));
        
        $response->assertRedirect('/employees/' . $employee->id . '/salary');
        $this->assertDatabaseMissing('salary_history', ['id' => $salary->id]);
    }

    public function test_deleting_salary_history_reverts_employment_record()
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        
        // Permissions
        // Setup Permissions
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'employees.delete_salary']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $company = Company::create(['name' => 'Test Co', 'code' => 'TC']);
        $department = Department::create(['name' => 'IT', 'department_code' => 'IT01']);
        $posDev = Position::create(['name' => 'Dev']);
        $posMgr = Position::create(['name' => 'Mgr']);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_code' => 'EMP_REV',
        ]);
        
        // 1. First Record (Old)
        $oldRecord = EmploymentRecord::create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'position_id' => $posDev->id,
            'employment_status' => 'Regular',
            'start_date' => '2025-01-01',
            'end_date' => '2026-01-27',
            'is_active' => false,
        ]);
        
        // 2. New Record (Active, caused by promotion)
        $newRecord = EmploymentRecord::create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'position_id' => $posMgr->id,
            'employment_status' => 'Regular',
            'start_date' => '2026-01-28',
            'is_active' => true,
        ]);

        // Salary History for New Record
        $salary = SalaryHistory::create([
            'employment_record_id' => $newRecord->id,
            'basic_rate' => 80000,
            'allowance' => 5000,
            'effective_date' => '2026-01-28',
        ]);
        
        // Act: Delete the salary history that created the new record
        $response = $this->actingAs($user)
            ->from('/employees')
            ->delete(route('salary-history.destroy', $salary->id));
            
        // Assert
        // New record should be gone
        $this->assertDatabaseMissing('employment_records', ['id' => $newRecord->id]);
        
        // Old record should be active again
        $this->assertDatabaseHas('employment_records', [
            'id' => $oldRecord->id,
            'is_active' => true,
            'end_date' => null,
        ]);
    }
}
