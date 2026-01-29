<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\DocumentType;
use App\Models\Position;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['user', 'activeEmploymentRecord.position', 'activeEmploymentRecord.department', 'documents']);

        if ($request->filled('search')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            })->orWhere('employee_code', 'like', "%{$request->search}%");
        }

        $employees = $query->paginate(10)->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search']),
            'options' => [
                'document_types' => DocumentType::all(),
                'positions' => Position::select('id', 'name')->orderBy('name')->get(),
                'companies' => Company::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
            ],
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'sss_no' => 'nullable|string|max:20',
            'philhealth_no' => 'nullable|string|max:20',
            'pagibig_no' => 'nullable|string|max:20',
            'tin_no' => 'nullable|string|max:20',
            'civil_status' => 'nullable|string|max:20',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:50',
            'emergency_contact_number' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'employment_status' => 'nullable|string|in:Consultant,Probationary,Regular,Project-Based,Casual',
        ]);

        DB::transaction(function () use ($request, $employee) {
            $employee->update($request->only([
                'sss_no', 'philhealth_no', 'pagibig_no', 'tin_no', 
                'civil_status', 'gender', 'address', 
                'emergency_contact', 'emergency_contact_relationship', 'emergency_contact_number', 
                'birthday'
            ]));

            if ($request->has('employment_status') && $employee->activeEmploymentRecord) {
                $employee->activeEmploymentRecord->update([
                    'employment_status' => $request->employment_status
                ]);
            }
        });

        return redirect()->back()->with('success', 'Employee profile updated successfully.');
    }

    public function resign(Request $request, Employee $employee)
    {
        $request->validate([
            'end_date' => 'required|date',
            'reason' => 'required|string',
        ]);

        DB::transaction(function () use ($employee, $request) {
            $record = $employee->activeEmploymentRecord;
            
            if ($record) {
                $record->update([
                    'end_date' => $request->end_date,
                    'is_active' => false,
                    'employment_status' => 'Resigned', // Or 'Terminated' based on reason context, but 'Resigned' is safe default or we can add a status field input
                ]);
            }
            
            // Optionally disable the user account
            // $employee->user->update(['is_active' => false]);
        });

        return redirect()->back()->with('success', 'Employee status updated to Resigned.');
    }

    public function uploadDocument(Request $request, Employee $employee)
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:51200', // 50MB max (50 * 1024)
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        
        // Save directly to public/uploads/employee-documents
        $targetPath = public_path('uploads/employee-documents');
        
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0755, true);
        }
        
        $file->move($targetPath, $fileName);
        $path = 'uploads/employee-documents/' . $fileName;

        $doc = EmployeeDocument::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'document_type_id' => $request->document_type_id,
            ],
            [
                'file_path' => $path
            ]
        );

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function getDocuments(Employee $employee)
    {
        return response()->json($employee->documents);
    }
}
