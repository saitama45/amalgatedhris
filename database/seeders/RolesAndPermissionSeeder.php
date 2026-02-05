<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Cleanup legacy permissions if any
        Permission::whereIn('name', ['payroll.process'])->delete();

        $permissions = [
            // Dashboard
            'dashboard.view' => 'Access Dashboard',

            // Recruitment - Applicants
            'applicants.view' => 'View Applicants',
            'applicants.create' => 'Add New Applicant',
            'applicants.edit' => 'Edit Applicant Details',
            'applicants.delete' => 'Delete Applicant',
            'applicants.hire' => 'Hire/Convert to Employee',
            
            // Recruitment - Exams
            'exams.view' => 'View Exam Results',

            // Workforce - Employees
            'employees.view' => 'View Employee Directory',
            'employees.create' => 'Add Employee (Manual)',
            'employees.edit' => 'Edit Employee Profile',
            'employees.delete' => 'Delete Employee Record',
            
            // Workforce - Salary (Sensitive)
            'employees.view_salary' => 'View Salary Rates',
            'employees.create_salary' => 'Add Salary History',
            'employees.edit_salary' => 'Update Salary Rates',
            'employees.delete_salary' => 'Delete Salary History',

            // Workforce - Documents
            'employees.view_documents' => 'View Employee Documents',
            'employees.edit_documents' => 'Upload/Manage Documents',

            // Time & Attendance - DTR
            'dtr.view' => 'View DTR Logs',
            'dtr.create' => 'Create/Add DTR Log',
            'dtr.edit' => 'Edit DTR Logs',
            'dtr.delete' => 'Delete DTR Logs',
            'dtr.approve' => 'Approve Manual Logs/OT',
            'attendance.kiosk' => 'Access Attendance Kiosk',

            // Time & Attendance - Overtime
            'overtime.view' => 'View OT Requests',
            'overtime.create' => 'Request Overtime',
            'overtime.approve' => 'Approve Overtime',
            'overtime.delete' => 'Cancel/Delete Overtime',

            // Time & Attendance - Overtime Rates
            'overtime_rates.view' => 'View OT Rates',
            'overtime_rates.manage' => 'Manage OT Rates',

            // Time & Attendance - Leave Management
            'leave_requests.view' => 'View Leave Requests',
            'leave_requests.create' => 'Create/File Leave',
            'leave_requests.edit' => 'Edit Leave Requests',
            'leave_requests.delete' => 'Delete Leave Requests',
            'leave_requests.approve' => 'Approve Leave Requests',
            'leave_requests.reject' => 'Reject Leave Requests',
            
            // Time & Attendance - Shifts
            'shifts.view' => 'View Shift Templates',
            'shifts.create' => 'Create Shift Template',
            'shifts.edit' => 'Edit Shift Template',
            'shifts.delete' => 'Delete Shift Template',
            
            // Time & Attendance - Schedules
            'schedules.view' => 'View Assignments',
            'schedules.create' => 'Assign/Generate Shifts',
            'schedules.delete' => 'Remove Shift Assignments',

            // Time & Attendance - Holidays
            'holidays.view' => 'View Holiday Calendar',
            'holidays.create' => 'Add New Holiday',
            'holidays.edit' => 'Edit Holiday Details',
            'holidays.delete' => 'Delete Holiday',

            // Compensation - Payroll
            'payroll.view' => 'View Payroll',
            'payroll.create' => 'Process Payroll (Create)',
            'payroll.approve' => 'Approve/Finalize Payroll',
            'payroll.edit_payslip' => 'Edit Payslip Adjustments',
            'payroll.delete' => 'Rollback Payroll (Delete)',
            'payroll.manage_loans' => 'Manage Employee Loans',
            'payroll.settings' => 'Edit Payroll Settings',

            // Compensation - Contributions
            'contributions.view' => 'View Contribution Tables',
            'contributions.edit' => 'Update Contribution Rates',

            // Compensation - Other Deductions
            'deductions.view' => 'View Other Deductions',
            'deductions.create' => 'Add/Assign Deduction',
            'deductions.edit' => 'Edit Deduction Details',
            'deductions.delete' => 'Delete/Stop Deduction',

            // My Portal
            'portal.view' => 'Access My Portal',
            'portal.file_leave' => 'File Leave/OT',
            'portal.view_payslip' => 'View Own Payslips',

            // System - Users
            'users.view' => 'View Users',
            'users.create' => 'Create User',
            'users.edit' => 'Edit User',
            'users.delete' => 'Delete User',
            
            // System - Roles
            'roles.view' => 'View Roles',
            'roles.create' => 'Create Role',
            'roles.edit' => 'Edit Role',
            'roles.delete' => 'Delete Role',

            // System - Companies
            'companies.view' => 'View Companies',
            'companies.create' => 'Create Company',
            'companies.edit' => 'Edit Company',
            'companies.delete' => 'Delete Company',

            // System - Departments
            'departments.view' => 'View Departments',
            'departments.create' => 'Create Department',
            'departments.edit' => 'Edit Department',
            'departments.delete' => 'Delete Department',

            // System - Positions
            'positions.view' => 'View Positions',
            'positions.create' => 'Create Position',
            'positions.edit' => 'Edit Position',
            'positions.delete' => 'Delete Position',

            // System - Doc Requirements
            'document_types.view' => 'View Doc Setup',
            'document_types.create' => 'Create Doc Requirement',
            'document_types.edit' => 'Edit Doc Requirement',
            'document_types.delete' => 'Delete Doc Requirement',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $admin->syncPermissions(Permission::all());
        
        $hr = Role::firstOrCreate(['name' => 'HR Manager']);
        $hr->syncPermissions(Permission::whereNotIn('name', [
            'roles.delete', 'users.delete', 'payroll.delete'
        ])->get());

        $employee = Role::firstOrCreate(['name' => 'Employee']);
        $employee->syncPermissions([
            'dashboard.view', 'portal.view', 'portal.file_leave', 'portal.view_payslip'
        ]);

        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Roles and permissions updated successfully!');
    }
}
