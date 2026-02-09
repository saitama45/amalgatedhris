<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();
        
        // 1. Determine the Role-Based Landing Page
        $landingPage = 'dashboard';
        $role = $user->roles()->whereNotNull('landing_page')->first();
        if ($role) {
            $landingPage = $role->landing_page;
        }

        // 2. Priority Logic:
        // If the user was trying to go to a specific page (intended), let them go there.
        // UNLESS the intended page is dashboard and the assigned landing page is different.
        $intended = $request->session()->get('url.intended');
        
        if ($intended && str_contains($intended, '/dashboard') && $landingPage !== 'dashboard') {
            return redirect()->route($landingPage);
        }

        return redirect()->intended(route($landingPage, absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
