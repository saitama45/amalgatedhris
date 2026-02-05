<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('payroll.manage_loans');

        $query = Loan::with('employee.user');

        if ($request->filled('search')) {
            $query->whereHas('employee.user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        $loans = $query->latest()->paginate(10);

        return Inertia::render('Loans/Index', [
            'loans' => $loans,
            'employees' => Employee::with('user')->get()->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->user->name
            ]),
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('payroll.manage_loans');

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'loan_type' => 'required|string',
            'principal' => 'required|numeric|min:0.01',
            'amortization' => 'required|numeric|min:0.01',
        ]);

        $validated['balance'] = $validated['principal'];
        $validated['status'] = 'Active';

        Loan::create($validated);

        return redirect()->back()->with('success', 'Loan added successfully.');
    }

    public function update(Request $request, Loan $loan)
    {
        $this->authorize('payroll.manage_loans');

        $validated = $request->validate([
            'loan_type' => 'required|string',
            'principal' => 'required|numeric|min:0.01',
            'amortization' => 'required|numeric|min:0.01',
            'balance' => 'required|numeric|min:0',
            'status' => 'required|in:Active,Paid,Cancelled'
        ]);

        $loan->update($validated);

        return redirect()->back()->with('success', 'Loan updated successfully.');
    }

    public function destroy(Loan $loan)
    {
        $this->authorize('payroll.manage_loans');
        
        if ($loan->principal != $loan->balance) {
            return back()->with('error', 'Cannot delete loan with existing payments.');
        }

        $loan->delete();

        return redirect()->back()->with('success', 'Loan deleted.');
    }
}