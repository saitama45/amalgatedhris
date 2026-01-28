<?php

namespace App\Http\Services;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;

class RoleService
{
    /**
     * Get all roles with their permissions
     */
    public static function getAllRoles()
    {
        return Role::with('permissions')->get();
    }

    /**
     * Get all permissions
     */
    public static function getAllPermissions()
    {
        return Permission::all();
    }

    /**
     * Get permissions grouped by category
     */
    public static function getPermissionsByCategory()
    {
        $permissions = Permission::all();
        $grouped = [];
        
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->name);
            $category = $parts[0];
            $grouped[ucfirst($category)][] = $permission;
        }
        
        return $grouped;
    }

    /**
     * Create a new role with permissions
     */
    public static function createRole($name, $permissions = [])
    {
        $role = Role::create(['name' => $name]);
        
        if (!empty($permissions)) {
            $role->givePermissionTo($permissions);
        }
        
        return $role;
    }

    /**
     * Update role permissions
     */
    public static function updateRolePermissions($roleId, $permissions)
    {
        $role = Role::findById($roleId);
        $role->syncPermissions($permissions);
        
        return $role;
    }

    /**
     * Delete a role
     */
    public static function deleteRole($roleId)
    {
        $role = Role::findById($roleId);
        return $role->delete();
    }

    /**
     * Check if user has permission
     */
    public static function userHasPermission($user, $permission)
    {
        return $user->can($permission);
    }

    /**
     * Get user roles
     */
    public static function getUserRoles($user)
    {
        return $user->roles;
    }

    /**
     * Assign role to user
     */
    public static function assignRoleToUser($user, $roleName)
    {
        return $user->assignRole($roleName);
    }

    /**
     * Remove role from user
     */
    public static function removeRoleFromUser($user, $roleName)
    {
        return $user->removeRole($roleName);
    }

    /**
     * Get portal specific permissions for the current user
     * Useful for checking what the employee can access in self-service
     */
    public static function getEmployeePermissions($userId)
    {
        $user = \App\Models\User::find($userId);
        if (!$user) return [];

        return $user->getAllPermissions()
            ->filter(function ($permission) {
                return str_starts_with($permission->name, 'portal.');
            })
            ->pluck('name')
            ->toArray();
    }

    /**
     * Check if user is a manager or has subordinates
     * This logic can be expanded to check the 'employment_records' table for 'Manager' rank
     */
    public static function isManager($userId)
    {
        $user = \App\Models\User::with('employee.employmentRecord')->find($userId);
        
        if (!$user || !$user->employee || !$user->employee->employmentRecord) {
            return false;
        }

        // Check if role is explicitly 'HR Manager' or 'Admin'
        if ($user->hasAnyRole(['Admin', 'HR Manager'])) {
            return true;
        }

        // Check employment record rank
        $rank = $user->employee->employmentRecord->rank;
        return in_array($rank, ['Manager', 'Executive', 'Supervisor']);
    }
}
