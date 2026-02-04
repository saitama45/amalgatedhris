<?php

namespace App\Http\Controllers;

use App\Models\OvertimeRate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OvertimeRateController extends Controller
{
    public function index()
    {
        return Inertia::render('OvertimeRates/Index', [
            'rates' => OvertimeRate::all(),
            'can' => [
                'manage' => auth()->user()->can('overtime_rates.manage'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('overtime_rates.manage');

        $validated = $request->validate([
            'key' => 'required|string|unique:overtime_rates,key',
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        OvertimeRate::create($validated);

        return redirect()->back()->with('success', 'OT Rate created successfully.');
    }

    public function update(Request $request, OvertimeRate $overtimeRate)
    {
        $this->authorize('overtime_rates.manage');

        $validated = $request->validate([
            'key' => 'required|string|unique:overtime_rates,key,' . $overtimeRate->id,
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $overtimeRate->update($validated);

        return redirect()->back()->with('success', 'OT Rate updated successfully.');
    }

    public function destroy(OvertimeRate $overtimeRate)
    {
        $this->authorize('overtime_rates.manage');

        // Prevent deleting core keys if you want to protect them
        // For now, allow deletion but maybe show a warning in UI
        $overtimeRate->delete();

        return redirect()->back()->with('success', 'OT Rate deleted successfully.');
    }
}