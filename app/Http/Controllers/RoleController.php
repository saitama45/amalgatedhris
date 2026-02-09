<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Role;
use App\Models\Company;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::with(['permissions:id,name', 'companies:id,name']);
        
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        $roles = $query->paginate($request->get('per_page', 10))->withQueryString();
        
        $allPermissions = Permission::select('id', 'name')->get();
        $permissions = $allPermissions->groupBy(function($permission) {
            $parts = explode('.', $permission->name);
            // If it's something like portal.dashboard, and we have that in sidebar_structure
            // we might want to group by the first two parts if it matches a category.
            // But for now, let's just use the first part as default, 
            // and we'll fix the frontend to handle the mapping.
            return $parts[0];
        });

        // Special handling for portal permissions to match sidebar structure keys
        foreach ($allPermissions as $p) {
            if (str_starts_with($p->name, 'portal.')) {
                $permissions[$p->name] = [$p];
            }
        }

        $companies = Company::where('is_active', true)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'companies' => $companies,
            'config' => [
                'sidebar_structure' => config('hris.sidebar_structure'),
                'permission_descriptions' => config('hris.permission_descriptions'),
                'module_labels' => config('hris.module_labels'),
                'landing_pages' => config('hris.landing_pages')
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'landing_page' => 'required|string',
            'permissions' => 'array',
            'company_ids' => 'array',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'landing_page' => $request->landing_page,
        ]);
        
        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        if ($request->has('company_ids')) {
            $role->companies()->sync($request->company_ids);
        }

        return redirect()->back()->with('success', 'Role created successfully');
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'landing_page' => 'required|string',
            'permissions' => 'array',
            'company_ids' => 'array',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $role->name = $request->name;
        $role->landing_page = $request->landing_page;
        $role->save();
        $role->syncPermissions($request->permissions ?? []);
        
        if ($request->has('company_ids')) {
            $role->companies()->sync($request->company_ids);
        } else {
            $role->companies()->detach();
        }

        return redirect()->back()->with('success', 'Role updated successfully');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete role with assigned users');
        }

        $role->companies()->detach(); // Clean up pivot table
        $role->delete();
        return redirect()->back()->with('success', 'Role deleted successfully');
    }
}
