<?php

namespace App\Http\Controllers;

use App\Models\IdTemplate;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class IdPrintingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('id_printing.view');

        $templates = IdTemplate::latest()->get();
        
        $employeesQuery = Employee::with(['user', 'activeEmploymentRecord.position', 'activeEmploymentRecord.department']);
        
        if ($request->filled('search')) {
            $employeesQuery->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            })->orWhere('employee_code', 'like', "%{$request->search}%");
        }

        $employees = $employeesQuery->paginate(50);

        return Inertia::render('Workforce/IdPrinting', [
            'templates' => $templates,
            'employees' => $employees,
            'filters' => $request->only(['search']),
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $this->authorize('id_printing.manage_templates');

        $request->validate([
            'name' => 'required|string|max:255',
            'front_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'back_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $frontPath = $request->file('front_image')->store('id_templates', 'public');
        $backPath = $request->hasFile('back_image') ? $request->file('back_image')->store('id_templates', 'public') : null;

        IdTemplate::create([
            'name' => $request->name,
            'front_image_path' => $frontPath,
            'back_image_path' => $backPath,
        ]);

        return back()->with('success', 'ID Template uploaded successfully.');
    }

    public function destroyTemplate(IdTemplate $template)
    {
        $this->authorize('id_printing.manage_templates');

        Storage::disk('public')->delete($template->front_image_path);
        if ($template->back_image_path) {
            Storage::disk('public')->delete($template->back_image_path);
        }

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'template_id' => 'required|exists:id_templates,id',
        ]);

        $employees = Employee::with(['user', 'activeEmploymentRecord.position', 'activeEmploymentRecord.department'])
            ->whereIn('id', $request->employee_ids)
            ->get();

        $template = IdTemplate::findOrFail($request->template_id);

        $pdf = Pdf::loadView('pdf.employee_ids', [
            'employees' => $employees,
            'template' => $template,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Employee_IDs.pdf');
    }
}
