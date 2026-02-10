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
        $landingPage = 'dashboard';
        
        if ($user) {
            // Load roles with permissions to avoid N+1 queries in the loop
            $user->loadMissing(['roles.permissions', 'roles.companies']);
            
            // Get permissions through roles efficiently
            $permissions = $user->roles->flatMap(function ($role) {
                return $role->permissions->pluck('name');
            })->unique()->values()->toArray();

            // Determine landing page from the primary role
            $primaryRole = $user->roles->first();
            if ($primaryRole && $primaryRole->landing_page) {
                $landingPage = $primaryRole->landing_page;
            }
        }
        
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user,
                'permissions' => $permissions,
                'landing_page' => $landingPage,
            ],
            'config' => [
                'sidebar_structure' => config('hris.sidebar_structure'),
                'module_labels' => config('hris.module_labels'),
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