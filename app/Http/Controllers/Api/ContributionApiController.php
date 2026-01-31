<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContributionService;
use Illuminate\Http\Request;

class ContributionApiController extends Controller
{
    protected $service;

    public function __construct(ContributionService $service)
    {
        $this->service = $service;
    }

    /**
     * Get contribution calculations for a specific salary.
     * This acts as the centralized API for all systems.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'salary' => 'required|numeric|min:0'
        ]);

        $salary = $request->salary;
        $contributions = $this->service->calculate($salary);

        return response()->json([
            'salary' => $salary,
            'year' => date('Y'),
            'contributions' => $contributions,
            'meta' => [
                'source' => 'Internal Database (Seeded with 2025/2026 Mandates)',
                'updated_at' => now(), // In a real system, fetch last update of tables
                'note' => 'Rates are based on Philippine 2025 Contributions: SSS 15%, PhilHealth 5%, Pag-IBIG 2% (Max 10k MFS).'
            ]
        ]);
    }
}