<?php

namespace App\Http\Controllers;

use App\Models\PayrollAdjustment;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class PayrollAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('payroll_adjustments.view');

        $query = PayrollAdjustment::with(['employee.user', 'creator', 'payroll'])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('employee.user', function($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                })->orWhere('reason', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $adjustments = $query->paginate($request->get('per_page', 10))->withQueryString();

        return Inertia::render('Payroll/Adjustments', [
            'adjustments' => $adjustments,
            'filters' => $request->only(['search', 'type', 'status']),
            'employees' => Employee::with('user')->get()->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->user->name . ' (' . $e->employee_code . ')'
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('payroll_adjustments.create');

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|gt:0',
            'type' => 'required|in:Addition,Deduction',
            'reason' => 'required|string|max:500',
            'is_taxable' => 'required|boolean',
            'payout_date' => 'nullable|date',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['status'] = 'Pending';

        PayrollAdjustment::create($validated);

        return back()->with('success', 'Adjustment recorded successfully.');
    }

    public function destroy(PayrollAdjustment $adjustment)
    {
        $this->authorize('payroll_adjustments.delete');

        if ($adjustment->status !== 'Pending') {
            return back()->with('error', 'Only pending adjustments can be deleted.');
        }

        $adjustment->delete();

        return back()->with('success', 'Adjustment deleted.');
    }

    /**
     * My Portal View
     */
    public function portalIndex(Request $request)
    {
        $this->authorize('portal.adjustments');
        $employee = Auth::user()->employee;
        
        if (!$employee) {
            return redirect()->route('dashboard')->with('error', 'Employee record not found.');
        }

        $adjustments = PayrollAdjustment::with(['payroll', 'creator'])
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('Portal/Adjustments', [
            'adjustments' => $adjustments,
        ]);
    }
}