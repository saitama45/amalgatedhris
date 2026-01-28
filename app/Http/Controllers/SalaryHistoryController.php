<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryHistory;
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

        // AUTO-REPAIR: Check for orphaned active employment record (Active record with NO salary history)
        // This fixes corrupted states where a salary deletion didn't correctly revert the employment record.
        $activeRecord = $employee->activeEmploymentRecord;
        if ($activeRecord && $activeRecord->salaryHistories()->count() === 0) {
             // Check if there is a previous record to revert to
             $previousRecord = EmploymentRecord::where('employee_id', $employee->id)
                ->where('id', '!=', $activeRecord->id)
                ->orderBy('created_at', 'desc') // Use created_at to be sure it's the latest previous
                ->first();
             
             if ($previousRecord) {
                 DB::transaction(function() use ($activeRecord, $previousRecord) {
                     $activeRecord->delete();
                     $previousRecord->update(['is_active' => true, 'end_date' => null]);
                 });
             }
        }

        // Get all salary history across all employment records for this employee
        // We include the employmentRecord.position to display the historical position
        $history = SalaryHistory::whereHas('employmentRecord', function($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
        ->with(['employmentRecord.position', 'employmentRecord.company'])
        ->orderBy('effective_date', 'desc')
        ->get()
        ->map(function ($item) {
            // Convert to array to avoid Model's date casting to UTC on serialization
            $data = $item->toArray();
            $data['effective_date'] = Carbon::parse($item->effective_date)->format('Y-m-d');
            return $data;
        });

        // Refresh the employee relation to get the latest active record after potential repair
        $employee->load('activeEmploymentRecord.position', 'activeEmploymentRecord.department', 'activeEmploymentRecord.company');

        return response()->json([
            'history' => $history,
            'current_record' => $employee->activeEmploymentRecord
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

            if (!$activeRecord) {
                throw new \Exception('Employee has no active employment record.');
            }

            // Check if Position OR Company Changed
            if ($activeRecord->position_id != $request->position_id || $activeRecord->company_id != $request->company_id) {
                // PROMOTION / MOVEMENT Logic
                
                // 1. Close current record
                $endDate = Carbon::parse($request->effective_date)->subDay();
                $activeRecord->update([
                    'is_active' => false,
                    'end_date' => $endDate,
                ]);

                // 2. Create New Employment Record
                $newRecord = EmploymentRecord::create([
                    'employee_id' => $employee->id,
                    'company_id' => $request->company_id, // NEW Company
                    'department_id' => $activeRecord->department_id, // Inherit (we assume department moves with company structure or add dept select too?)
                    'position_id' => $request->position_id, // NEW Position
                    'employment_status' => $activeRecord->employment_status,
                    'start_date' => $request->effective_date,
                    'is_active' => true,
                ]);

                $targetRecordId = $newRecord->id;

            } else {
                // SAME POSITION Logic
                $targetRecordId = $activeRecord->id;
            }

            // Create Salary History linked to the determined record
            SalaryHistory::create([
                'employment_record_id' => $targetRecordId,
                'basic_rate' => $request->basic_rate,
                'allowance' => $request->allowance ?? 0,
                'effective_date' => $request->effective_date,
            ]);
        });

        return redirect()->back()->with('success', 'Salary and Position updated successfully.');
    }

    public function update(Request $request, SalaryHistory $salaryHistory)
    {
        if (!$request->user()->can('employees.edit_salary')) {
            return redirect()->back()->with('error', 'Unauthorized to update salary rates.');
        }

        $request->validate([
            'basic_rate' => 'required|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'position_id' => 'required|exists:positions,id',
            'company_id' => 'required|exists:companies,id',
        ]);

        // Check for payroll transactions (Payslips) created after this salary's effective date
        $hasPayroll = Payslip::where('employee_id', $salaryHistory->employmentRecord->employee_id)
            ->where('created_at', '>=', $salaryHistory->effective_date)
            ->exists();

        if ($hasPayroll) {
            return redirect()->back()->with('error', 'Cannot edit this record. Payroll transactions have already been processed using this or a subsequent rate.');
        }

        DB::transaction(function () use ($request, $salaryHistory) {
            // Update Rates
            $salaryHistory->update([
                'basic_rate' => $request->basic_rate,
                'allowance' => $request->allowance ?? 0,
                'effective_date' => $request->effective_date,
            ]);

            // Update Linked Position and Company
            $salaryHistory->employmentRecord->update([
                'position_id' => $request->position_id,
                'company_id' => $request->company_id,
            ]);
        });

        return redirect()->back()->with('success', 'Salary record updated successfully.');
    }

    public function destroy(SalaryHistory $salaryHistory)
    {
        if (!auth()->user()->can('employees.delete_salary')) {
            return redirect()->back()->with('error', 'Unauthorized to delete salary rates.');
        }

        // Check for payroll transactions (Payslips) created after this salary's effective date
        $hasPayroll = Payslip::where('employee_id', $salaryHistory->employmentRecord->employee_id)
            ->where('created_at', '>=', $salaryHistory->effective_date)
            ->exists();

        if ($hasPayroll) {
            return redirect()->back()->with('error', 'Cannot delete this record. Payroll transactions have already been processed using this or a subsequent rate.');
        }

        DB::transaction(function () use ($salaryHistory) {
            $employmentRecord = $salaryHistory->employmentRecord;
            
            // Count histories for this specific employment record
            $count = $employmentRecord->salaryHistories()->count();

            if ($count === 1) {
                // This is the only salary record for this employment (position/company assignment).
                // It implies this EmploymentRecord should be removed and we should revert to the previous one.
                
                // Find previous record to re-activate
                $previousRecord = EmploymentRecord::where('employee_id', $employmentRecord->employee_id)
                    ->where('id', '!=', $employmentRecord->id)
                    ->orderBy('start_date', 'desc')
                    ->first();

                if ($previousRecord) {
                    $previousRecord->update([
                        'is_active' => true,
                        'end_date' => null
                    ]);
                }

                // Delete the employment record (cascades to delete this $salaryHistory)
                $employmentRecord->delete();

            } else {
                // Just delete the specific rate entry
                $salaryHistory->delete();
            }
        });

        return redirect()->back()->with('success', 'Salary record deleted successfully.');
    }
}
