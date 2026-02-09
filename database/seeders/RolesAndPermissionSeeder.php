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
        Permission::whereIn('name', ['payroll.process', 'contributions.view', 'contributions.edit', 'withholding_tax.view', 'withholding_tax.edit'])->delete();

        $permissions = config('hris.permission_descriptions');

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
            'dashboard.view',
            'portal.dashboard',
            'portal.leaves',
            'portal.overtime',
            'portal.payslips',
            'portal.deductions'
        ]);

        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Roles and permissions updated successfully!');
    }
}
