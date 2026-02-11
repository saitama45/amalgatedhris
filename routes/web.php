<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // System Administration
    Route::middleware('permission:users.view')->group(function () {
        Route::resource('users', UserController::class)->except(['destroy']);
        Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    });
    
    Route::middleware('permission:roles.view')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show', 'create', 'edit']);
    });

    Route::middleware('permission:companies.view')->group(function () {
        Route::resource('companies', \App\Http\Controllers\CompanyController::class);
    });

    Route::middleware('permission:departments.view')->group(function () {
        Route::resource('departments', \App\Http\Controllers\DepartmentController::class)->except(['create', 'edit', 'show']);
    });

    Route::middleware('permission:positions.view')->group(function () {
        Route::resource('positions', \App\Http\Controllers\PositionController::class)->except(['create', 'edit', 'show']);
    });

    Route::middleware('permission:document_types.view')->group(function () {
        Route::resource('document-types', \App\Http\Controllers\DocumentTypeController::class)->except(['create', 'edit', 'show']);
    });

    // Recruitment
    Route::middleware('permission:applicants.view')->group(function () {
        Route::get('applicants/exams', [\App\Http\Controllers\ApplicantController::class, 'exams'])->name('applicants.exams');
        Route::post('applicants/{applicant}/hire', [\App\Http\Controllers\ApplicantController::class, 'hire'])->name('applicants.hire');
        
        Route::middleware('permission:applicants.manage_requirements')->group(function () {
            Route::post('applicants/{applicant}/documents', [\App\Http\Controllers\ApplicantController::class, 'uploadDocument'])->name('applicants.upload-document');
            Route::get('applicants/{applicant}/documents', [\App\Http\Controllers\ApplicantController::class, 'getDocuments'])->name('applicants.documents.list');
        });

        Route::resource('applicants', \App\Http\Controllers\ApplicantController::class);
    });

    // Workforce (Employees)
    Route::middleware('permission:employees.view')->group(function () {
        Route::put('employees/{employee}/resign', [\App\Http\Controllers\EmployeeController::class, 'resign'])->name('employees.resign');
        Route::post('employees/{employee}/documents', [\App\Http\Controllers\EmployeeController::class, 'uploadDocument'])->name('employees.upload-document');
        Route::get('employees/{employee}/documents', [\App\Http\Controllers\EmployeeController::class, 'getDocuments'])->name('employees.documents.list');
        Route::get('employees/{employee}/salary', [\App\Http\Controllers\SalaryHistoryController::class, 'index'])->name('employees.salary.index');
        Route::post('employees/{employee}/salary', [\App\Http\Controllers\SalaryHistoryController::class, 'store'])->name('employees.salary.store');
        Route::put('salary-history/{salaryHistory}', [\App\Http\Controllers\SalaryHistoryController::class, 'update'])->name('salary-history.update');
        Route::delete('salary-history/{salaryHistory}', [\App\Http\Controllers\SalaryHistoryController::class, 'destroy'])->name('salary-history.destroy');
        Route::resource('employees', \App\Http\Controllers\EmployeeController::class)->only(['index', 'update']);
    });
    
    // Timekeeping & Attendance
    Route::middleware('permission:dtr.view')->group(function () {
        Route::get('dtr', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('dtr.index');
        Route::post('dtr', [\App\Http\Controllers\AttendanceController::class, 'store'])->name('dtr.store');
        Route::put('dtr/{attendanceLog}', [\App\Http\Controllers\AttendanceController::class, 'update'])->name('dtr.update');
        Route::delete('dtr/{attendanceLog}', [\App\Http\Controllers\AttendanceController::class, 'destroy'])->name('dtr.destroy');
        Route::get('dtr/template', [\App\Http\Controllers\AttendanceController::class, 'downloadTemplate'])->name('dtr.template');
        Route::post('dtr/import', [\App\Http\Controllers\AttendanceController::class, 'import'])->name('dtr.import');
    });

    Route::middleware('permission:shifts.view')->group(function () {
        Route::resource('shifts', \App\Http\Controllers\ShiftController::class)->except(['create', 'edit', 'show']);
    });

    Route::middleware('permission:holidays.view')->group(function () {
        Route::post('holidays/sync', [\App\Http\Controllers\HolidayController::class, 'sync'])->name('holidays.sync');
        Route::resource('holidays', \App\Http\Controllers\HolidayController::class)->except(['create', 'edit', 'show']);
    });

    Route::middleware('permission:schedules.view')->group(function () {
        Route::get('schedules', [\App\Http\Controllers\ScheduleController::class, 'index'])->name('schedules.index');
        Route::get('schedules/{employee}', [\App\Http\Controllers\ScheduleController::class, 'show'])->name('schedules.show');
        Route::post('schedules', [\App\Http\Controllers\ScheduleController::class, 'store'])->name('schedules.store');
    });

    // Compensation & Payroll
    Route::middleware('permission:government_deductions.view')->group(function () {
        Route::resource('contributions', \App\Http\Controllers\ContributionController::class)->only(['index']);
        Route::post('contributions/schedules', [\App\Http\Controllers\ContributionController::class, 'updateSchedules'])->name('contributions.schedules.update');
        Route::post('contributions/tax/sync', [\App\Http\Controllers\ContributionController::class, 'syncTaxBrackets'])->name('contributions.tax.sync');
        Route::post('contributions/sss/generate', [\App\Http\Controllers\ContributionController::class, 'generateSSS'])->name('contributions.sss.generate');
        Route::post('contributions/philhealth/update', [\App\Http\Controllers\ContributionController::class, 'updatePhilHealth'])->name('contributions.philhealth.update');
        Route::post('contributions/pagibig/update', [\App\Http\Controllers\ContributionController::class, 'updatePagIBIG'])->name('contributions.pagibig.update');
    });

    Route::middleware('permission:deductions.view')->group(function () {
        Route::resource('deductions', \App\Http\Controllers\DeductionController::class);
        Route::resource('deduction-types', \App\Http\Controllers\DeductionTypeController::class)->except(['create', 'edit', 'show']);
    });

    Route::middleware('permission:payroll.view')->group(function () {
        Route::get('payroll/{payroll}/export-pdf', [\App\Http\Controllers\PayrollController::class, 'exportPdf'])->name('payroll.export-pdf');
        Route::get('payroll/{payroll}/export-excel', [\App\Http\Controllers\PayrollController::class, 'exportExcel'])->name('payroll.export-excel');
        Route::resource('payroll', \App\Http\Controllers\PayrollController::class)->except(['edit', 'update']);
        Route::post('payroll/{payroll}/regenerate', [\App\Http\Controllers\PayrollController::class, 'regenerate'])->name('payroll.regenerate');
        Route::put('payroll/{payroll}/approve', [\App\Http\Controllers\PayrollController::class, 'approve'])->name('payroll.approve');
        Route::put('payroll/{payroll}/revert', [\App\Http\Controllers\PayrollController::class, 'revert'])->name('payroll.revert');
        Route::get('payslips/{payslip}/export-pdf', [\App\Http\Controllers\PayrollController::class, 'exportPayslipPdf'])->name('payslips.export-pdf');
        Route::put('payslips/{payslip}', [\App\Http\Controllers\PayrollController::class, 'updatePayslip'])->name('payslips.update');
    });

    // Leave & Overtime Requests
    Route::middleware('permission:leave_requests.view')->group(function () {
        Route::resource('leave-types', \App\Http\Controllers\LeaveTypeController::class)->except(['show', 'create', 'edit']);
        Route::resource('leave-requests', \App\Http\Controllers\LeaveRequestController::class)->except(['show', 'create', 'edit']);
        Route::put('leave-requests/{leaveRequest}/approve', [\App\Http\Controllers\LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::put('leave-requests/{leaveRequest}/reject', [\App\Http\Controllers\LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    });

    Route::middleware('permission:overtime.view')->group(function () {
        Route::resource('overtime', \App\Http\Controllers\OvertimeController::class);
        Route::put('overtime/{overtimeRequest}/approve', [\App\Http\Controllers\OvertimeController::class, 'approve'])->name('overtime.approve');
        Route::put('overtime/{overtimeRequest}/reject', [\App\Http\Controllers\OvertimeController::class, 'reject'])->name('overtime.reject');
    });

    Route::middleware('permission:overtime_rates.view')->group(function () {
        Route::resource('overtime-rates', \App\Http\Controllers\OvertimeRateController::class)->except(['show', 'create', 'edit']);
    });

    // My Portal (Self Service)
    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PortalController::class, 'index'])->name('dashboard');
        Route::get('/leaves', [\App\Http\Controllers\PortalController::class, 'leaves'])->name('leaves');
            Route::get('/overtime', [\App\Http\Controllers\PortalController::class, 'overtime'])->name('overtime');
            Route::get('/attendance', [\App\Http\Controllers\PortalController::class, 'attendance'])->name('attendance');
            Route::get('/payslips', [\App\Http\Controllers\PortalController::class, 'payslips'])->name('payslips');        Route::get('/my-payslip/{id}/pdf', [\App\Http\Controllers\PortalController::class, 'exportPayslipPdf'])->name('payslips.pdf');
        Route::get('/deductions', [\App\Http\Controllers\PortalController::class, 'deductions'])->name('deductions');

        // Self-service actions (avoiding 403 from admin routes)
        Route::post('/leaves', [\App\Http\Controllers\PortalController::class, 'storeLeave'])
            ->middleware('permission:portal.leaves')
            ->name('leaves.store');
        Route::put('/leaves/{leaveRequest}', [\App\Http\Controllers\PortalController::class, 'updateLeave'])
            ->middleware('permission:portal.leaves')
            ->name('leaves.update');
        Route::delete('/leaves/{leaveRequest}', [\App\Http\Controllers\PortalController::class, 'destroyLeave'])
            ->middleware('permission:portal.leaves')
            ->name('leaves.destroy');

        Route::post('/overtime', [\App\Http\Controllers\PortalController::class, 'storeOvertime'])
            ->middleware('permission:portal.overtime')
            ->name('overtime.store');
        Route::put('/overtime/{overtimeRequest}', [\App\Http\Controllers\PortalController::class, 'updateOvertime'])
            ->middleware('permission:portal.overtime')
            ->name('overtime.update');
        Route::delete('/overtime/{overtimeRequest}', [\App\Http\Controllers\PortalController::class, 'destroyOvertime'])
            ->middleware('permission:portal.overtime')
            ->name('overtime.destroy');
    });

    // Personal Profile (Accessible to all authenticated users)
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Global Search (Authenticated)
    Route::get('global-search', [\App\Http\Controllers\GlobalSearchController::class, 'search'])->name('global.search');
});

// Public Attendance Kiosk
Route::get('attendance/kiosk', [\App\Http\Controllers\AttendanceKioskController::class, 'index'])->name('attendance.kiosk');
Route::post('attendance/kiosk', [\App\Http\Controllers\AttendanceKioskController::class, 'store'])->name('attendance.kiosk.store');
Route::post('attendance/kiosk/scan', [\App\Http\Controllers\AttendanceKioskController::class, 'scan'])->name('attendance.kiosk.scan');

require __DIR__.'/auth.php';