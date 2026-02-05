<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $this->authorize('payroll.settings'); // Borrowing payroll settings for now or create new permission

        return Inertia::render('Leave/Types', [
            'types' => LeaveType::all()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'days_per_year' => 'required|integer|min:0',
            'is_convertible' => 'boolean',
            'is_cumulative' => 'boolean',
        ]);

        LeaveType::create($validated);

        return back()->with('success', 'Leave type created.');
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'days_per_year' => 'required|integer|min:0',
            'is_convertible' => 'boolean',
            'is_cumulative' => 'boolean',
        ]);

        $leaveType->update($validated);

        return back()->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType)
    {
        $leaveType->delete();
        return back()->with('success', 'Leave type deleted.');
    }
}