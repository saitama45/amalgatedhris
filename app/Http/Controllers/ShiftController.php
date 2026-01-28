<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = Shift::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $shifts = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('Shifts/Index', [
            'shifts' => $shifts,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        Shift::create($request->all());

        return redirect()->back()->with('success', 'Shift template created successfully.');
    }

    public function update(Request $request, Shift $shift)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $shift->update($request->all());

        return redirect()->back()->with('success', 'Shift template updated successfully.');
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();
        return redirect()->back()->with('success', 'Shift template deleted successfully.');
    }
}
