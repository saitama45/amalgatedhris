<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\User;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\SalaryHistory;
use App\Models\Company;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

use App\Models\DocumentType;

class ApplicantController extends Controller
{
    public function index(Request $request)
    {
        $query = Applicant::query();

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('middle_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: Hide hired applicants as they are now employees
            $query->where('status', '!=', 'hired');
        }

        $applicants = $query->with(['documents' => function($q) {
            $q->whereHas('documentType', function($dq) {
                $dq->where('name', 'Resume / CV');
            });
        }])->latest()->paginate(10)->withQueryString();

        return Inertia::render('Applicants/Index', [
            'applicants' => $applicants,
            'filters' => $request->only(['search', 'status']),
            'options' => [
                'companies' => Company::select('id', 'name')->where('is_active', true)->orderBy('name')->get(),
                'departments' => Department::select('id', 'name')->orderBy('name')->get(),
                'positions' => Position::select('id', 'name')->orderBy('name')->get(),
                'document_types' => DocumentType::where('is_active', true)->orderBy('name')->get(),
            ],
        ]);
    }
    
    public function exams(Request $request)
    {
        // View for exam results - filtering applicants with scores or in exam stage
        $query = Applicant::whereNotNull('exam_score')
            ->orWhere('status', 'exam');

         if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('middle_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%");
            });
        }

        $applicants = $query->latest()->paginate(10)->withQueryString();

        return Inertia::render('Applicants/Exams', [
            'applicants' => $applicants,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:applicants,email',
            'phone' => 'required|string|max:20',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
        ]);

        $path = null;
        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $targetPath = public_path('uploads/resumes');
            if (!file_exists($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
            $file->move($targetPath, $fileName);
            $path = 'uploads/resumes/' . $fileName;
        }

        Applicant::create([
            'first_name' => strtoupper($request->first_name),
            'middle_name' => $request->middle_name ? strtoupper($request->middle_name) : null,
            'last_name' => strtoupper($request->last_name),
            'email' => $request->email,
            'phone' => $request->phone,
            'status' => 'pool',
            'resume_path' => $path,
        ]);

        return redirect()->back()->with('success', 'Applicant added successfully.');
    }

    public function update(Request $request, Applicant $applicant)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:applicants,email,' . $applicant->id,
            'phone' => 'required|string|max:20',
            'status' => 'required|string|in:pool,exam,interview,passed,failed,hired,backed_out',
            'exam_score' => 'nullable|numeric|min:0|max:100',
            'interviewer_notes' => 'nullable|string',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
        ]);

        if ($request->hasFile('resume')) {
            if ($applicant->resume_path && file_exists(public_path($applicant->resume_path))) {
                unlink(public_path($applicant->resume_path));
            }
            $file = $request->file('resume');
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $targetPath = public_path('uploads/resumes');
            if (!file_exists($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
            $file->move($targetPath, $fileName);
            $applicant->resume_path = 'uploads/resumes/' . $fileName;
        }

        $data = $request->except('resume');
        $data['first_name'] = strtoupper($request->first_name);
        if ($request->has('middle_name')) {
            $data['middle_name'] = $request->middle_name ? strtoupper($request->middle_name) : null;
        }
        $data['last_name'] = strtoupper($request->last_name);

        $applicant->update($data);

        return redirect()->back()->with('success', 'Applicant updated successfully.');
    }

    public function destroy(Applicant $applicant)
    {
        if ($applicant->resume_path) {
            Storage::disk('public')->delete($applicant->resume_path);
        }
        $applicant->delete();
        return redirect()->back()->with('success', 'Applicant deleted successfully.');
    }

    public function hire(Request $request, Applicant $applicant)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'required|exists:departments,id',
            'position_id' => 'required|exists:positions,id',
            'start_date' => 'required|date',
            'basic_rate' => 'required|numeric',
            'allowance' => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($request, $applicant) {
            // Create User
            $fullName = strtoupper($applicant->first_name . ' ' . ($applicant->middle_name ? $applicant->middle_name . ' ' : '') . $applicant->last_name);
            
            // Handle potential email conflict if user was already created manually
            $user = User::where('email', $applicant->email)->first();
            if (!$user) {
                $user = User::create([
                    'name' => $fullName,
                    'email' => $applicant->email,
                    'password' => Hash::make('password123'),
                    'company_id' => $request->company_id,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('Employee');
            }

            // Create Employee
            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_code' => 'EMP-' . now()->year . '-' . str_pad($user->id, 4, '0', STR_PAD_LEFT),
                'qr_code' => 'QR-' . strtoupper(bin2hex(random_bytes(8))),
            ]);

            // Create Employment Record (Consolidated Assignment & Pay)
            $record = EmploymentRecord::create([
                'employee_id' => $employee->id,
                'company_id' => $request->company_id,
                'department_id' => $request->department_id,
                'position_id' => $request->position_id,
                'basic_rate' => $request->basic_rate,
                'allowance' => $request->allowance ?? 0,
                'employment_status' => 'Probationary',
                'start_date' => $request->start_date,
                'is_active' => true,
            ]);

            // Transfer uploaded documents to new employee
            \App\Models\EmployeeDocument::where('applicant_id', $applicant->id)
                ->update(['employee_id' => $employee->id, 'applicant_id' => null]);

            // Update Applicant
            $applicant->update(['status' => 'hired']);
        });

        return redirect()->back()->with('success', 'Applicant successfully hired and converted to Employee.');
    }

    public function uploadDocument(Request $request, Applicant $applicant)
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        
        $targetPath = public_path('uploads/employee-documents');
        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0755, true);
        }
        
        $file->move($targetPath, $fileName);
        $relativePath = 'uploads/employee-documents/' . $fileName;

        // Check if exists
        $existing = \App\Models\EmployeeDocument::where('applicant_id', $applicant->id)
            ->where('document_type_id', $request->document_type_id)
            ->first();

        if ($existing) {
             // Delete old file
            if (file_exists(public_path($existing->file_path))) {
                unlink(public_path($existing->file_path));
            }
            $existing->update(['file_path' => $relativePath]);
        } else {
            \App\Models\EmployeeDocument::create([
                'applicant_id' => $applicant->id,
                'document_type_id' => $request->document_type_id,
                'file_path' => $relativePath,
            ]);
        }

        // Sync resume_path if document is Resume / CV
        $docType = DocumentType::find($request->document_type_id);
        if ($docType && $docType->name === 'Resume / CV') {
            $applicant->update(['resume_path' => $relativePath]);
        }

        return redirect()->back()->with('success', 'Document uploaded successfully.');
    }

    public function getDocuments(Applicant $applicant)
    {
        $documents = \App\Models\EmployeeDocument::where('applicant_id', $applicant->id)->get();
        return response()->json($documents);
    }
}