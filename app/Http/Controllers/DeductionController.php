<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDeduction;
use App\Models\DeductionType;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeductionController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeDeduction::with(['employee.user', 'deductionType']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee.user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('type')) {
            $query->where('deduction_type_id', $request->type);
        }

        $deductions = $query->paginate(10)->withQueryString();

        return Inertia::render('Deductions/Index', [
            'deductions' => $deductions,
            'employees' => Employee::with('user')->get()->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->user->name
            ]),
            'deductionTypes' => DeductionType::where('is_active', true)->get(),
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'deduction_type_id' => 'required|exists:deduction_types,id',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:once_a_month,semimonthly',
            'schedule' => 'required|in:first_half,second_half,both',
            'total_amount' => 'nullable|numeric|min:0',
            'terms' => 'nullable|integer|min:1',
            'effective_date' => 'required|date',
        ]);

        $validated['calculation_type'] = 'fixed_amount';

        if (isset($validated['total_amount'])) {
            $validated['remaining_balance'] = $validated['total_amount'];
        }

        EmployeeDeduction::create($validated);

        return redirect()->back()->with('success', 'Deduction assigned successfully.');
    }

    public function show(EmployeeDeduction $deduction)
    {
        $deduction->load(['employee.user', 'deductionType']);
        
        return Inertia::render('Deductions/Show', [
            'deduction' => $deduction,
        ]);
    }

    public function update(Request $request, EmployeeDeduction $deduction)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:once_a_month,semimonthly',
            'schedule' => 'required|in:first_half,second_half,both',
            'total_amount' => 'nullable|numeric|min:0',
            'remaining_balance' => 'nullable|numeric|min:0',
            'terms' => 'nullable|integer|min:1',
            'effective_date' => 'required|date',
            'status' => 'required|string',
        ]);

        $validated['calculation_type'] = 'fixed_amount';

        // If converting to a loan (adding total_amount) and balance is not tracked yet, set it.
        if (isset($validated['total_amount']) && $validated['total_amount'] > 0) {
            if (is_null($deduction->remaining_balance) && !isset($validated['remaining_balance'])) {
                $validated['remaining_balance'] = $validated['total_amount'];
            }
        }

        $deduction->update($validated);

        return redirect()->back()->with('success', 'Deduction updated successfully.');
    }

    public function destroy(EmployeeDeduction $deduction)
    {
        $deduction->delete();
        return redirect()->back()->with('success', 'Deduction removed successfully.');
    }
}