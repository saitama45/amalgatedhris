<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Position::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->filled('rank')) {
            $query->where('rank', $request->rank);
        }

        $positions = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('Positions/Index', [
            'positions' => $positions,
            'filters' => $request->only(['search', 'rank']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:positions,name',
            'rank' => 'required|in:RankAndFile,Supervisor,Manager,Executive',
            'description' => 'nullable|string',
        ]);

        Position::create([
            'name' => strtoupper($request->name),
            'rank' => $request->rank,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Position created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Position $position)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:positions,name,' . $position->id,
            'rank' => 'required|in:RankAndFile,Supervisor,Manager,Executive',
            'description' => 'nullable|string',
        ]);

        $position->update([
            'name' => strtoupper($request->name),
            'rank' => $request->rank,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Position updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position)
    {
        $position->delete();

        return redirect()->back()->with('success', 'Position deleted successfully.');
    }
}
