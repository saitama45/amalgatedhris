<?php

return [
    'sidebar_structure' => [
        'Overview' => ['dashboard'],
        'Recruitment' => ['applicants', 'exams'],
        'Workforce' => ['employees'],
        'Time & Attendance' => ['attendance.kiosk', 'dtr', 'shifts', 'schedules', 'holidays', 'overtime', 'overtime_rates', 'leave_requests'],
        'Compensation' => ['payroll', 'government_deductions', 'deductions'],
        'Reports' => ['government_remittances'],
        'My Portal' => ['portal.dashboard', 'portal.leaves', 'portal.overtime', 'portal.attendance', 'portal.ob-attendance', 'portal.payslips', 'portal.deductions'],
        'System Administration' => ['users', 'companies', 'departments', 'positions', 'document_types', 'roles']
    ],

    'permission_descriptions' => [
        // Dashboard
        'dashboard.view' => 'Access Dashboard',

        // Recruitment - Applicants
        'applicants.view' => 'View Applicants',
        'applicants.create' => 'Add New Applicant',
        'applicants.edit' => 'Edit Applicant Details',
        'applicants.delete' => 'Delete Applicant',
        'applicants.hire' => 'Hire/Convert to Employee',
        'applicants.manage_requirements' => 'Manage Applicant Documents',
        
        // Recruitment - Exams
        'exams.view' => 'View Exam Results',

        // Workforce - Employees
        'employees.view' => 'View Employee Directory',
        'employees.create' => 'Add Employee (Manual)',
        'employees.edit' => 'Edit Employee Profile',
        'employees.delete' => 'Delete Employee Record',
        
        // Workforce - Salary
        'employees.view_salary' => 'View Salary Rates',
        'employees.create_salary' => 'Add Salary History',
        'employees.edit_salary' => 'Update Salary Rates',
        'employees.delete_salary' => 'Delete Salary History',

        // Workforce - Documents
        'employees.view_documents' => 'View Employee Documents',
        'employees.edit_documents' => 'Upload/Manage Documents',

        // Time & Attendance - DTR
        'dtr.view' => 'View DTR Logs',
        'dtr.create' => 'Create/Add DTR Log',
        'dtr.edit' => 'Edit DTR Logs',
        'dtr.delete' => 'Delete DTR Logs',
        'dtr.approve' => 'Approve Manual Logs/OT',
        'attendance.kiosk' => 'Access Attendance Kiosk',
        'attendance.kiosk.manual_input' => 'Enable Manual Input in Kiosk',

        // Time & Attendance - Overtime
        'overtime.view' => 'View Overtime',
        'overtime.create' => 'Create Overtime',
        'overtime.edit' => 'Edit Overtime',
        'overtime.delete' => 'Delete Overtime',
        'overtime.approve' => 'Approve Overtime',
        'overtime.reject' => 'Reject Overtime',

        // Time & Attendance - Overtime Rates
        'overtime_rates.view' => 'View OT Multipliers',
        'overtime_rates.manage' => 'Manage OT Multipliers',

        // Time & Attendance - Leave Management
        'leave_requests.view' => 'View Leave',
        'leave_requests.create' => 'Create Leave',
        'leave_requests.edit' => 'Edit Leave',
        'leave_requests.delete' => 'Delete Leave',
        'leave_requests.approve' => 'Approve Leave',
        'leave_requests.reject' => 'Reject Leave',
        
        // Time & Attendance - Shifts
        'shifts.view' => 'View Shift Templates',
        'shifts.create' => 'Create Shift Template',
        'shifts.edit' => 'Edit Shift Template',
        'shifts.delete' => 'Delete Shift Template',
        
        // Time & Attendance - Schedules
        'schedules.view' => 'View Assignments',
        'schedules.create' => 'Assign/Generate Shifts',
        'schedules.delete' => 'Remove Shift Assignments',

        // Time & Attendance - Holidays
        'holidays.view' => 'View Holiday Calendar',
        'holidays.create' => 'Add New Holiday',
        'holidays.edit' => 'Edit Holiday Details',
        'holidays.delete' => 'Delete Holiday',

        // Compensation - Payroll
        'payroll.view' => 'View Payroll',
        'payroll.create' => 'Process Payroll (Create)',
        'payroll.approve' => 'Approve/Finalize Payroll',
        'payroll.revert' => 'Unlock/Revert Finalized Payroll',
        'payroll.edit_payslip' => 'Edit Payslip Adjustments',
        'payroll.delete' => 'Rollback Payroll (Delete)',
        'payroll.manage_loans' => 'Manage Employee Loans',
        'payroll.settings' => 'Edit Payroll Settings',

        // Compensation - Government Deductions
        'government_deductions.view' => 'View Government Deductions',
        'government_deductions.edit' => 'Update Government Deductions',

        // Compensation - Other Deductions
        'deductions.view' => 'View Other Deductions',
        'deductions.create' => 'Add/Assign Deduction',
        'deductions.edit' => 'Edit Deduction Details',
        'deductions.delete' => 'Delete/Stop Deduction',

        // Reports
        'government_remittances.view' => 'View Government Remittances',

        // My Portal
        'portal.dashboard' => 'Access My Portal Dashboard',
        'portal.leaves' => 'File/View Own Leaves',
        'portal.overtime' => 'Request/View Own Overtime',
        'portal.attendance' => 'View Own Attendance Logs',
        'portal.ob-attendance' => 'Submit OB Attendance (Selfie + GPS)',
        'portal.payslips' => 'View Own Payslips',
        'portal.deductions' => 'View Own Deductions Ledger',

        // System - Users
        'users.view' => 'View Users',
        'users.create' => 'Create User',
        'users.edit' => 'Edit User',
        'users.delete' => 'Delete User',
        
        // System - Roles
        'roles.view' => 'View Roles',
        'roles.create' => 'Create Role',
        'roles.edit' => 'Edit Role',
        'roles.delete' => 'Delete Role',

        // System - Companies
        'companies.view' => 'View Companies',
        'companies.create' => 'Create Company',
        'companies.edit' => 'Edit Company',
        'companies.delete' => 'Delete Company',

        // System - Departments
        'departments.view' => 'View Departments',
        'departments.create' => 'Create Department',
        'departments.edit' => 'Edit Department',
        'departments.delete' => 'Delete Department',

        // System - Positions
        'positions.view' => 'View Positions',
        'positions.create' => 'Create Position',
        'positions.edit' => 'Edit Position',
        'positions.delete' => 'Delete Position',

        // System - Doc Requirements
        'document_types.view' => 'View Doc Setup',
        'document_types.create' => 'Create Doc Requirement',
        'document_types.edit' => 'Edit Doc Requirement',
        'document_types.delete' => 'Delete Doc Requirement',
    ],

    // Mapping for UI Category Labels
    'module_labels' => [
        'applicants' => 'Applicants',
        'exams' => 'Exams',
        'employees' => 'Employees (201)',
        'attendance.kiosk' => 'Attendance Kiosk',
        'dtr' => 'Attendance Logs',
        'shifts' => 'Shifts',
        'schedules' => 'Schedules',
        'holidays' => 'Holidays',
        'overtime' => 'Overtime',
        'overtime_rates' => 'OT Multipliers',
        'leave_requests' => 'Leave Requests',
        'payroll' => 'Payroll',
        'government_deductions' => 'Government Deductions',
        'deductions' => 'Other Deductions',
        'government_remittances' => 'Govt Remittances',
        'portal.dashboard' => 'Overview',
        'portal.leaves' => 'My Leaves',
        'portal.overtime' => 'My Overtime',
        'portal.attendance' => 'My Attendance',
        'portal.ob-attendance' => 'My OB Attendance',
        'portal.payslips' => 'My Payslips',
        'portal.deductions' => 'Deductions Ledger',
        'users' => 'User Management',
        'companies' => 'Companies',
        'departments' => 'Departments',
        'positions' => 'Positions',
        'document_types' => 'Document Types',
        'roles' => 'Access Control'
    ],

    // Available Landing Pages after login
    'landing_pages' => [
        'dashboard' => 'Dashboard',
        'applicants.index' => 'Applicants Pool',
        'applicants.exams' => 'Exam Results',
        'employees.index' => 'Employee Directory',
        'attendance.kiosk' => 'Attendance Kiosk',
        'dtr.index' => 'DTR Logs',
        'overtime.index' => 'Overtime Requests',
        'leave-requests.index' => 'Leave Management',
        'payroll.index' => 'Payroll Processing',
        'contributions.index' => 'Government Contributions',
        'users.index' => 'User Management',
        'roles.index' => 'Roles & Permissions',
        'portal.dashboard' => 'Portal: Overview',
        'portal.leaves' => 'Portal: My Leaves',
        'portal.overtime' => 'Portal: My Overtime',
        'portal.attendance' => 'Portal: My Attendance',
        'portal.ob-attendance' => 'Portal: My OB Attendance',
        'portal.payslips' => 'Portal: My Payslips',
        'portal.deductions' => 'Portal: Deductions Ledger',
    ]
];
