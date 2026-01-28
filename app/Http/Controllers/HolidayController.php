<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $query = Holiday::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        // Show upcoming holidays first, then past
        $holidays = $query->orderByRaw("CASE WHEN date >= CAST(GETDATE() AS DATE) THEN 0 ELSE 1 END")
                          ->orderBy('date')
                          ->paginate(10)
                          ->withQueryString();

        return Inertia::render('Holidays/Index', [
            'holidays' => $holidays,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:Regular,Special Non-Working,Special Working,Local/Declared',
            'description' => 'nullable|string',
            'is_recurring' => 'boolean',
        ]);

        Holiday::create($request->all());

        return redirect()->back()->with('success', 'Holiday created successfully.');
    }

    public function update(Request $request, Holiday $holiday)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:Regular,Special Non-Working,Special Working,Local/Declared',
            'description' => 'nullable|string',
            'is_recurring' => 'boolean',
        ]);

        $holiday->update($request->all());

        return redirect()->back()->with('success', 'Holiday updated successfully.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return redirect()->back()->with('success', 'Holiday deleted successfully.');
    }
}
