<?php

namespace App\Http\Controllers;

use App\Models\SalfForm;
use App\Models\SalfItem;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class SalfController extends Controller
{
    public function index(Request $request)
    {
        $query = SalfForm::with(['employee.applicant', 'employee.department', 'employee.company', 'employee.position', 'department', 'company']);
        $user = auth()->user();

        // If it's a portal request, ALWAYS scope to the logged-in employee only
        if ($request->routeIs('portal.*') || ($request->headers->get('referer') && str_contains($request->headers->get('referer'), '/portal/salf'))) {
            $employee = $user->employee;
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                return Inertia::render('Salf/Index', [
                    'forms' => [],
                    'filters' => $request->all('search'),
                    'isPortal' => true,
                ]);
            }
        } 
        // Admin/HR Manager can see everyone's SALF in the main Workforce menu
        else if (!$user->hasRole('Admin') && !$user->hasRole('HR Manager')) {
            $employee = $user->employee;
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                return Inertia::render('Salf/Index', [
                    'forms' => [],
                    'filters' => $request->all('search'),
                    'isPortal' => false,
                ]);
            }
        }

        if ($request->search) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%");
            });
        }

        return Inertia::render('Salf/Index', [
            'forms' => $query->latest()->paginate(10)->withQueryString(),
            'filters' => $request->all('search'),
            'isPortal' => $request->has('portal') || $request->routeIs('portal.*') || ($request->headers->get('referer') && str_contains($request->headers->get('referer'), '/portal/salf')),
        ]);
    }

    public function create(Request $request)
    {
        $employees = Employee::with(['department', 'company'])->get();
        return Inertia::render('Salf/Create', [
            'employees' => $employees,
            'currentUserEmployee' => auth()->user()->employee?->load(['department', 'company']),
            'isPortal' => $request->has('portal') || $request->routeIs('portal.*') || ($request->headers->get('referer') && str_contains($request->headers->get('referer'), '/portal/salf')),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
            'company_id' => 'nullable|exists:companies,id',
            'period_covered' => 'required|string',
            'approved_by' => 'nullable|string',
            'status' => 'required|string|in:draft,submitted,approved',
            'items' => 'required|array|min:1',
            'items.*.is_header' => 'boolean',
            'items.*.section' => 'nullable|string',
            'items.*.area_of_concern' => 'required_if:items.*.is_header,false|nullable|string',
            'items.*.action_plan' => 'required_if:items.*.is_header,false|nullable|string',
            'items.*.support_group' => 'nullable|string',
            'items.*.target_date' => 'required_if:items.*.is_header,false|nullable|date',
            'items.*.actual_value' => 'required_if:items.*.is_header,false|nullable|numeric|min:0',
            'items.*.target_value' => 'required_if:items.*.is_header,false|nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            $form = SalfForm::create([
                'employee_id' => $validated['employee_id'],
                'department_id' => $validated['department_id'],
                'company_id' => $validated['company_id'],
                'period_covered' => $validated['period_covered'],
                'approved_by' => $validated['approved_by'],
                'status' => $validated['status'],
            ]);

            foreach ($validated['items'] as $index => $itemData) {
                $itemData['salf_form_id'] = $form->id;
                $itemData['order'] = $index;
                SalfItem::create($itemData);
            }
        });

        $isPortal = $request->headers->get('referer') && str_contains($request->headers->get('referer'), '/portal/salf');
        return redirect()->route($isPortal ? 'portal.salf' : 'salf.index')->with('success', 'SALF created successfully.');
    }

    public function show(Request $request, SalfForm $salf)
    {
        $user = auth()->user();
        if (!$user->hasRole('Admin') && !$user->hasRole('HR Manager') && $salf->employee_id !== $user->employee?->id) {
            abort(403, 'Unauthorized access to SALF record.');
        }

        $salf->load(['employee.applicant', 'employee.department', 'employee.company', 'department', 'company', 'items']);
        return Inertia::render('Salf/Show', [
            'salf' => $salf,
            'overallEfficiency' => $salf->overall_efficiency,
            'isPortal' => $request->has('portal') || $request->routeIs('portal.*') || ($request->headers->get('referer') && str_contains($request->headers->get('referer'), '/portal/salf')),
        ]);
    }

    public function edit(Request $request, SalfForm $salf)
    {
        $user = auth()->user();
        if (!$user->hasRole('Admin') && !$user->hasRole('HR Manager') && $salf->employee_id !== $user->employee?->id) {
            abort(403, 'Unauthorized access to SALF record.');
        }

        $salf->load(['items', 'employee.applicant', 'employee.department', 'employee.company']);
        $employees = Employee::with(['department', 'company'])->get();
        return Inertia::render('Salf/Edit', [
            'salf' => $salf,
            'employees' => $employees,
            'isPortal' => $request->has('portal') || $request->routeIs('portal.*') || ($request->headers->get('referer') && str_contains($request->headers->get('referer'), '/portal/salf')),
        ]);
    }

    public function update(Request $request, SalfForm $salf)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
            'company_id' => 'nullable|exists:companies,id',
            'period_covered' => 'required|string',
            'approved_by' => 'nullable|string',
            'status' => 'required|string|in:draft,submitted,approved',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:salf_items,id',
            'items.*.section' => 'nullable|string',
            'items.*.area_of_concern' => 'required|string',
            'items.*.action_plan' => 'required|string',
            'items.*.support_group' => 'nullable|string',
            'items.*.target_date' => 'required|date',
            'items.*.actual_value' => 'required|numeric|min:0',
            'items.*.target_value' => 'required|numeric|min:0',
            'items.*.remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $salf) {
            $salf->update([
                'employee_id' => $validated['employee_id'],
                'department_id' => $validated['department_id'],
                'company_id' => $validated['company_id'],
                'period_covered' => $validated['period_covered'],
                'approved_by' => $validated['approved_by'],
                'status' => $validated['status'],
            ]);

            $existingItemIds = collect($validated['items'])->pluck('id')->filter()->toArray();
            $salf->items()->whereNotIn('id', $existingItemIds)->delete();

            foreach ($validated['items'] as $index => $itemData) {
                if (isset($itemData['id'])) {
                    $item = SalfItem::find($itemData['id']);
                    $item->update($itemData);
                    $item->update(['order' => $index]);
                } else {
                    $itemData['salf_form_id'] = $salf->id;
                    $itemData['order'] = $index;
                    SalfItem::create($itemData);
                }
            }
        });

        $isPortal = $request->headers->get('referer') && str_contains($request->headers->get('referer'), '/portal/salf');
        return redirect()->route($isPortal ? 'portal.salf' : 'salf.index')->with('success', 'SALF updated successfully.');
    }

    public function destroy(SalfForm $salf)
    {
        $salf->delete();
        return redirect()->route('salf.index')->with('success', 'SALF deleted successfully.');
    }

    public function exportPdf(Request $request, SalfForm $salf)
    {
        $salf->load(['employee.applicant', 'employee.position', 'department', 'company', 'items']);
        
        $pdf = Pdf::loadView('pdf.salf', [
            'salf' => $salf,
            'overallEfficiency' => $salf->overall_efficiency,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("SALF_{$salf->employee->last_name}_{$salf->period_covered}.pdf");
    }
}
