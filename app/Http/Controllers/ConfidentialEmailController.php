<?php

namespace App\Http\Controllers;

use App\Models\ConfidentialEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConfidentialEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if ($request->user()->email !== 'gmcloud45@gmail.com') {
                abort(403, 'Unauthorized access to security management.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        return Inertia::render('Settings/ConfidentialEmails', [
            'emails' => ConfidentialEmail::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:confidential_emails,email',
            'can_view_salary' => 'required|boolean',
            'can_manage_payroll' => 'required|boolean',
        ]);

        ConfidentialEmail::create($request->all());

        return redirect()->back()->with('success', 'Authorized email added successfully.');
    }

    public function update(Request $request, ConfidentialEmail $confidentialEmail)
    {
        $request->validate([
            'can_view_salary' => 'required|boolean',
            'can_manage_payroll' => 'required|boolean',
        ]);

        // Prevent self-lockout of GM account from seeing salary/payroll if edited
        if ($confidentialEmail->email === 'gmcloud45@gmail.com') {
            $confidentialEmail->update([
                'can_view_salary' => true,
                'can_manage_payroll' => true
            ]);
        } else {
            $confidentialEmail->update($request->only(['can_view_salary', 'can_manage_payroll']));
        }

        return redirect()->back()->with('success', 'Permissions updated.');
    }

    public function destroy(ConfidentialEmail $confidentialEmail)
    {
        if ($confidentialEmail->email === 'gmcloud45@gmail.com') {
            return redirect()->back()->with('error', 'Cannot remove the primary administrator account.');
        }

        $confidentialEmail->delete();
        return redirect()->back()->with('success', 'Email removed from authorized list.');
    }
}
