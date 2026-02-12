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
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        // AUTO-REPAIR: Ensure employees with records have at least one active record
        $orphanedEmployees = Employee::whereDoesntHave('activeEmploymentRecord')
            ->whereHas('employmentRecords')
            ->get();

        foreach ($orphanedEmployees as $emp) {
            $latest = $emp->employmentRecords()->orderBy('start_date', 'desc')->orderBy('created_at', 'desc')->first();
            if ($latest) {
                $latest->update(['is_active' => true, 'end_date' => null]);
            }
        }

        // AUTO-REPAIR: Ensure all employees have a QR code
        $missingQR = Employee::whereNull('qr_code')->get();
        foreach ($missingQR as $emp) {
            $emp->update([
                'qr_code' => 'QR-' . strtoupper(bin2hex(random_bytes(8))),
            ]);
        }

        $query = Employee::with(['user', 'activeEmploymentRecord.position', 'activeEmploymentRecord.department', 'activeEmploymentRecord.company', 'documents']);

        if ($request->filled('search')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            })->orWhere('employee_code', 'like', "%{$request->search}%");
        }

        // Apply company filter
        if ($request->filled('company_id')) {
            $query->whereHas('activeEmploymentRecord', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        }

        // Apply department filter
        if ($request->filled('department_id')) {
            $query->whereHas('activeEmploymentRecord', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        // Apply position filter
        if ($request->filled('position_id')) {
            $query->whereHas('activeEmploymentRecord', function ($q) use ($request) {
                $q->where('position_id', $request->position_id);
            });
        }

        $employees = $query->paginate(10)->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'company_id', 'department_id', 'position_id']),
            'options' => [
                'document_types' => \App\Models\DocumentType::all(),
                'positions' => \App\Models\Position::select('id', 'name')->orderBy('name')->get(),
                'departments' => \App\Models\Department::select('id', 'name')->orderBy('name')->get(),
                'companies' => \App\Models\Company::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
            ],
        ]);
    }

            public function update(Request $request, Employee $employee)

            {

                Log::info("Updating Employee {$employee->id} Payload Analysis", [

                    'keys' => array_keys($request->all()),

                    'face_data_type' => gettype($request->face_data),

                    'face_data_preview' => $request->face_data ? substr($request->face_data, 0, 50) . '...' : 'NULL',

                    'face_descriptor_count' => $request->face_descriptor ? count($request->face_descriptor) : 0

                ]);

        

                $request->validate([

                    'employee_code' => 'required|string|max:50|unique:employees,employee_code,' . $employee->id,

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

                    'department_id' => 'nullable|exists:departments,id',

                    'employment_status' => 'nullable|string|in:Consultant,Probationary,Regular,Project-Based,Casual',

                    'is_sss_deducted' => 'nullable|boolean',

                    'is_philhealth_deducted' => 'nullable|boolean',

                    'is_pagibig_deducted' => 'nullable|boolean',

                    'is_withholding_tax_deducted' => 'nullable|boolean',

                    'face_data' => 'nullable|string', // Base64

                    'face_descriptor' => 'nullable|array', // Received from MediaPipe in browser

                ]);

        

                DB::transaction(function () use ($request, $employee) {

                    $data = $request->only([

                        'employee_code',

                        'sss_no', 'philhealth_no', 'pagibig_no', 'tin_no', 

                        'civil_status', 'gender', 'address', 

                        'emergency_contact', 'emergency_contact_relationship', 'emergency_contact_number', 

                        'birthday'

                    ]);

        

                    // Direct check for face_data presence and content

                    if ($request->has('face_data') && !empty($request->face_data)) {

                        if ($request->face_data === 'CLEAR') {
                            // Delete physical file if exists
                            if ($employee->face_data) {
                                try {
                                    $oldData = json_decode($employee->face_data, true);
                                    if (isset($oldData['file'])) {
                                        $oldFilePath = public_path('uploads/faces/' . $oldData['file']);
                                        if (file_exists($oldFilePath)) {
                                            unlink($oldFilePath);
                                            Log::info("Deleted old face image: " . $oldFilePath);
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error("Failed to delete old face image: " . $e->getMessage());
                                }
                            }

                            $data['face_data'] = null;

                            Log::info("Clearing face_data for employee {$employee->id}");

                        } else {

                            try {

                                $base64_image = $request->face_data;

                                

                                // Simple parsing: split by comma

                                if (strpos($base64_image, ',') !== false) {

                                    $parts = explode(',', $base64_image);

                                    $imageContent = base64_decode($parts[1]);

                                } else {

                                    // Assume raw base64 if no prefix

                                    $imageContent = base64_decode($base64_image);

                                }

        

                                if ($imageContent === false) {

                                    throw new \Exception('Base64 decode failed.');

                                }

        

                                $filename = 'face_' . $employee->id . '_' . time() . '.jpg';

                                $uploadPath = public_path('uploads/faces');

        

                                if (!file_exists($uploadPath)) {

                                    mkdir($uploadPath, 0777, true);

                                }

        

                                $fullPath = $uploadPath . DIRECTORY_SEPARATOR . $filename;

                                file_put_contents($fullPath, $imageContent);

                                

                                Log::info("Successfully saved face image to: $fullPath");

        

                                // Construct JSON

                                $faceData = [

                                    'file' => $filename,

                                    'descriptor' => $request->face_descriptor ?? null

                                ];

                                

                                $data['face_data'] = json_encode($faceData);

                                

                            } catch (\Exception $e) {

                                Log::error("Error processing face_data: " . $e->getMessage());

                            }

                        }

                    }

        

     else if ($request->has('face_data') && is_null($request->face_data)) {
                 // Explicitly cleared
                 // $data['face_data'] = null;
            } else if ($request->has('face_data') && $request->face_data === 'CLEAR') {
                 // Explicitly cleared via CLEAR command
                 $data['face_data'] = null;
            }

            $employee->update($data);

            if ($employee->activeEmploymentRecord) {
                $employmentData = [];
                if ($request->has('employment_status')) {
                    $employmentData['employment_status'] = $request->employment_status;
                }
                if ($request->has('department_id')) {
                    $employmentData['department_id'] = $request->department_id;
                }
                
                if ($request->has('is_sss_deducted')) {
                    $employmentData['is_sss_deducted'] = $request->is_sss_deducted;
                }
                if ($request->has('is_philhealth_deducted')) {
                    $employmentData['is_philhealth_deducted'] = $request->is_philhealth_deducted;
                }
                if ($request->has('is_pagibig_deducted')) {
                    $employmentData['is_pagibig_deducted'] = $request->is_pagibig_deducted;
                }
                if ($request->has('is_withholding_tax_deducted')) {
                    $employmentData['is_withholding_tax_deducted'] = $request->is_withholding_tax_deducted;
                }
                
                if (!empty($employmentData)) {
                    $employee->activeEmploymentRecord->update($employmentData);
                }
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

    public function regenerateQRCode(Employee $employee)
    {
        $employee->update([
            'qr_code' => 'QR-' . strtoupper(bin2hex(random_bytes(8))),
        ]);

        return redirect()->back()->with('success', 'QR Code regenerated successfully.');
    }
}
