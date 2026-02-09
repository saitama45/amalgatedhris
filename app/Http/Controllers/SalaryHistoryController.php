<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalaryHistoryController extends Controller
{
    public function index(Employee $employee)
    {
        if (!auth()->user()->can('employees.view_salary')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Simplified: Employment Records ARE the history now
        $history = EmploymentRecord::where('employee_id', $employee->id)
            ->with(['position', 'company', 'department'])
            ->orderBy('start_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get the single active record
        $activeRecord = $employee->activeEmploymentRecord;
        if ($activeRecord) {
            $activeRecord->load(['position', 'department', 'company']);
        }

        return response()->json([
            'history' => $history,
            'current_record' => $activeRecord
        ]);
    }

    public function store(Request $request, Employee $employee)
    {
        if (!$request->user()->can('employees.create_salary')) {
            return redirect()->back()->with('error', 'Unauthorized to add salary rates.');
        }

        $request->validate([
            'basic_rate' => 'required|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'position_id' => 'required|exists:positions,id',
            'company_id' => 'required|exists:companies,id',
        ]);

        DB::transaction(function () use ($request, $employee) {
            $activeRecord = $employee->activeEmploymentRecord;

            // If we have an active record, we close it
            if ($activeRecord) {
                $endDate = Carbon::parse($request->effective_date)->subDay();
                $activeRecord->update([
                    'is_active' => false,
                    'end_date' => $endDate,
                ]);
            }

            // Create New Employment Record (Consolidated state)
            EmploymentRecord::create([
                'employee_id' => $employee->id,
                'company_id' => $request->company_id,
                'department_id' => $activeRecord ? $activeRecord->department_id : $employee->department_id,
                'position_id' => $request->position_id,
                'basic_rate' => $request->basic_rate,
                'allowance' => $request->allowance ?? 0,
                'employment_status' => $activeRecord ? $activeRecord->employment_status : 'Probationary',
                'start_date' => $request->effective_date,
                'is_active' => true,
                // Inherit flags if active record exists
                'is_sss_deducted' => $activeRecord ? $activeRecord->is_sss_deducted : true,
                'is_philhealth_deducted' => $activeRecord ? $activeRecord->is_philhealth_deducted : true,
                'is_pagibig_deducted' => $activeRecord ? $activeRecord->is_pagibig_deducted : true,
                'is_withholding_tax_deducted' => $activeRecord ? $activeRecord->is_withholding_tax_deducted : true,
            ]);
        });

        return redirect()->back()->with('success', 'Salary and Assignment updated successfully.');
    }

    public function update(Request $request, $employmentRecordId)
    {
        if (!$request->user()->can('employees.edit_salary')) {
            return redirect()->back()->with('error', 'Unauthorized to update salary rates.');
        }

        $record = EmploymentRecord::findOrFail($employmentRecordId);

        $request->validate([
            'basic_rate' => 'required|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'position_id' => 'required|exists:positions,id',
            'company_id' => 'required|exists:companies,id',
        ]);

        // Check for payroll transactions (Payslips) created after this record's effective date
        $hasPayroll = Payslip::where('employee_id', $record->employee_id)
            ->where('created_at', '>=', $record->start_date)
            ->exists();

        if ($hasPayroll) {
            return redirect()->back()->with('error', 'Cannot edit this record. Payroll transactions have already been processed.');
        }

        DB::transaction(function () use ($request, $record) {
            $record->update([
                'basic_rate' => $request->basic_rate,
                'allowance' => $request->allowance ?? 0,
                'start_date' => $request->effective_date,
                'position_id' => $request->position_id,
                'company_id' => $request->company_id,
            ]);
        });

        return redirect()->back()->with('success', 'Employment record updated successfully.');
    }

    public function destroy($employmentRecordId)
    {
        if (!auth()->user()->can('employees.delete_salary')) {
            return redirect()->back()->with('error', 'Unauthorized to delete salary rates.');
        }

        $record = EmploymentRecord::findOrFail($employmentRecordId);

        // Check for payroll transactions
        $hasPayroll = Payslip::where('employee_id', $record->employee_id)
            ->where('created_at', '>=', $record->start_date)
            ->exists();

        if ($hasPayroll) {
            return redirect()->back()->with('error', 'Cannot delete this record. Payroll transactions have already been processed.');
        }

        DB::transaction(function () use ($record) {
            // If deleting an active record, try to re-activate the previous one
            if ($record->is_active) {
                $previousRecord = EmploymentRecord::where('employee_id', $record->employee_id)
                    ->where('id', '!=', $record->id)
                    ->orderBy('start_date', 'desc')
                    ->first();

                if ($previousRecord) {
                    $previousRecord->update([
                        'is_active' => true,
                        'end_date' => null
                    ]);
                }
            }

            $record->delete();
        });

        return redirect()->back()->with('success', 'Record deleted successfully.');
    }
}