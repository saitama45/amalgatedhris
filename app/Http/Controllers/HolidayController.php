<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $query = Holiday::query();

        if ($request->filled('search')) {
            $search = $request->search;
            
            // Intelligent Month Mapping
            $months = [
                'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
                'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
                'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
                'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12
            ];

            $loweredSearch = strtolower($search);
            $monthNum = $months[$loweredSearch] ?? null;

            $query->where(function($q) use ($search, $monthNum) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
                
                if ($monthNum) {
                    $q->orWhereMonth('date', $monthNum);
                }
            });
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

    public function sync(Request $request)
    {
        $year = $request->input('year', date('Y'));
        
        // Trusted API: Nager.Date (No API Key required)
        // .withoutVerifying() is added to handle local SSL certificate issues (cURL error 60)
        $response = Http::withoutVerifying()->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/PH");

        if ($response->failed()) {
            return back()->with('error', 'Could not connect to holiday service.');
        }

        $holidays = $response->json();
        $addedCount = 0;

        foreach ($holidays as $h) {
            // Check if exists
            $exists = Holiday::where('date', $h['date'])->exists();
            if (!$exists) {
                Holiday::create([
                    'name' => $h['localName'] ?? $h['name'],
                    'date' => $h['date'],
                    'type' => $h['fixed'] ? 'Regular' : 'Special Non-Working',
                    'is_recurring' => $h['fixed'],
                    'description' => 'Automatically synced national holiday'
                ]);
                $addedCount++;
            }
        }

        return back()->with('success', "Sync completed! added {$addedCount} new holidays.");
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
