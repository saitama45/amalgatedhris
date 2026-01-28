<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Cache;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = [];
        
        if ($user) {
            // Fetch permissions directly to ensure immediate updates when roles change
            $user->loadMissing(['roles.companies']);
            $perms = [];
            // Get permissions through roles
            foreach ($user->roles as $role) {
                $rolePermissions = $role->permissions()->pluck('name')->toArray();
                $perms = array_merge($perms, $rolePermissions);
            }
            $permissions = array_unique($perms);
            
            // Ensure necessary relations are loaded for the user object in the frontend
            $user->loadMissing(['roles.companies']);
        }
        
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user,
                'permissions' => array_values($permissions),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ]);
    }
}