<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('users', UserController::class);
    Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    
    Route::resource('roles', RoleController::class)->except(['show', 'create', 'edit']);
    Route::resource('companies', \App\Http\Controllers\CompanyController::class);

    // Recruitment
    Route::get('applicants/exams', [\App\Http\Controllers\ApplicantController::class, 'exams'])->name('applicants.exams');
    Route::post('applicants/{applicant}/hire', [\App\Http\Controllers\ApplicantController::class, 'hire'])->name('applicants.hire');
    Route::post('applicants/{applicant}/documents', [\App\Http\Controllers\ApplicantController::class, 'uploadDocument'])->name('applicants.upload-document');
    Route::get('applicants/{applicant}/documents', [\App\Http\Controllers\ApplicantController::class, 'getDocuments'])->name('applicants.documents.list');
    Route::resource('applicants', \App\Http\Controllers\ApplicantController::class);

    // Employees
    Route::put('employees/{employee}/resign', [\App\Http\Controllers\EmployeeController::class, 'resign'])->name('employees.resign');
    Route::post('employees/{employee}/documents', [\App\Http\Controllers\EmployeeController::class, 'uploadDocument'])->name('employees.upload-document');
    Route::get('employees/{employee}/documents', [\App\Http\Controllers\EmployeeController::class, 'getDocuments'])->name('employees.documents.list');
    Route::get('employees/{employee}/salary', [\App\Http\Controllers\SalaryHistoryController::class, 'index'])->name('employees.salary.index');
    Route::post('employees/{employee}/salary', [\App\Http\Controllers\SalaryHistoryController::class, 'store'])->name('employees.salary.store');
    Route::put('salary-history/{salaryHistory}', [\App\Http\Controllers\SalaryHistoryController::class, 'update'])->name('salary-history.update');
    Route::delete('salary-history/{salaryHistory}', [\App\Http\Controllers\SalaryHistoryController::class, 'destroy'])->name('salary-history.destroy');
    Route::resource('employees', \App\Http\Controllers\EmployeeController::class)->only(['index', 'update']);
    // Shifts & Schedules
    Route::resource('shifts', \App\Http\Controllers\ShiftController::class)->except(['create', 'edit', 'show']);
    Route::resource('holidays', \App\Http\Controllers\HolidayController::class)->except(['create', 'edit', 'show']);
    Route::get('schedules', [\App\Http\Controllers\ScheduleController::class, 'index'])->name('schedules.index');
    Route::get('schedules/{employee}', [\App\Http\Controllers\ScheduleController::class, 'show'])->name('schedules.show');
    Route::post('schedules', [\App\Http\Controllers\ScheduleController::class, 'store'])->name('schedules.store');

    // Contributions
    Route::resource('contributions', \App\Http\Controllers\ContributionController::class)->only(['index']);
    Route::post('contributions/sss/generate', [\App\Http\Controllers\ContributionController::class, 'generateSSS'])->name('contributions.sss.generate');
    Route::post('contributions/philhealth/update', [\App\Http\Controllers\ContributionController::class, 'updatePhilHealth'])->name('contributions.philhealth.update');
    Route::post('contributions/pagibig/update', [\App\Http\Controllers\ContributionController::class, 'updatePagIBIG'])->name('contributions.pagibig.update');

    // Attendance (DTR)
    Route::get('dtr', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('dtr.index');
    Route::post('dtr', [\App\Http\Controllers\AttendanceController::class, 'store'])->name('dtr.store');
    Route::put('dtr/{attendanceLog}', [\App\Http\Controllers\AttendanceController::class, 'update'])->name('dtr.update');
    Route::delete('dtr/{attendanceLog}', [\App\Http\Controllers\AttendanceController::class, 'destroy'])->name('dtr.destroy');
    Route::get('dtr/template', [\App\Http\Controllers\AttendanceController::class, 'downloadTemplate'])->name('dtr.template');
    Route::post('dtr/import', [\App\Http\Controllers\AttendanceController::class, 'import'])->name('dtr.import');

    Route::resource('employees', \App\Http\Controllers\EmployeeController::class)->only(['index', 'update']);
    Route::resource('departments', \App\Http\Controllers\DepartmentController::class)->except(['create', 'edit', 'show']);
    Route::resource('positions', \App\Http\Controllers\PositionController::class)->except(['create', 'edit', 'show']);
    Route::resource('document-types', \App\Http\Controllers\DocumentTypeController::class)->except(['create', 'edit', 'show']);
    
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

require __DIR__.'/auth.php';
