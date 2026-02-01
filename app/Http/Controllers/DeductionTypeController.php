<?php

namespace App\Http\Controllers;

use App\Models\DeductionType;
use Illuminate\Http\Request;

class DeductionTypeController extends Controller
{
    public function index()
    {
        return response()->json(DeductionType::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        DeductionType::create($validated);

        return redirect()->back()->with('success', 'Deduction Type created.');
    }

    public function update(Request $request, DeductionType $deductionType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $deductionType->update($validated);

        return redirect()->back()->with('success', 'Deduction Type updated.');
    }

    public function destroy(DeductionType $deductionType)
    {
        $deductionType->delete();
        return redirect()->back()->with('success', 'Deduction Type deleted.');
    }
}