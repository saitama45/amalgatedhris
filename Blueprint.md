# Amalgated HRIS: Comprehensive System Blueprint

## 1. System Overview
**Amalgated HRIS** is an enterprise-grade Human Resource Information System designed to manage the full employee lifecycle—from recruitment to retirement. It supports multi-company structures, historical tracking of rates and positions, complex payroll computations (government benefits, loans, deductions), and employee self-service.

---

## 2. Core Modules & Features

### A. Recruitment & Onboarding
*   **Applicant Pooling:** Centralized database of applicants.
*   **Process Flow:** `Pooled` -> `For Exam` -> `For Interview` -> `Passed/Failed` -> `Hired`.
*   **Exam & Resume:** Uploading of resumes and recording of exam scores.
*   **Conversion:** One-click conversion from "Applicant" to "Employee" (migrates data to 201 file).

### B. Employee Management (201 File)
*   **Multi-Company Support:** Employees can belong to multiple companies over time (or concurrently).
*   **History Tracking:** Full audit trail of:
    *   **Positions/Ranks:** (Rank & File, Supervisor, Manager, GM).
    *   **Salary Rates:** Historical progression of basic pay and allowances.
    *   **Transfers:** Movement between companies/departments.
*   **Document Management:** Uploading scanned requirements (ID, Diploma, NBI Clearance).

### C. Timekeeping (DTR)
*   **Daily Time Record:** Log In/Out capture.
*   **Shift Management:** Assigning schedules (Morning, Mid, Night).
*   **Approvals:** Supervisor approval for Overtime (OT), Official Business (OB), and manual adjustments.
*   **Auto-Computation:** System calculates late, undertime, and overtime hours based on shift logic.

### D. Leave Management
*   **Types:** VL (Vacation), SL (Sick), EL (Emergency), Maternity/Paternity.
*   **Policy Engine (Configurable):**
    *   Convertible to Cash (Yes/No).
    *   Cumulative/Carry-over (Yes/No).
    *   Accrual logic (e.g., 1.25 days per month).
*   **Workflow:** Employee requests -> Manager approves -> HR notified.

### E. Payroll & Benefits
*   **Automated Processing:** Computes Gross -> Deductions -> Net Pay.
*   **Government Tables:** Configurable brackets for SSS, PhilHealth, and HDMF (Pag-IBIG).
*   **Deductions:**
    *   **Loans:** Tracking of loan types (Company, SSS, Salary Loan), amortization schedules, and decreasing balances.
    *   **Tardiness/Absences:** Auto-deducted from DTR.
*   **13th Month Pay:** Pro-rated calculation (e.g., 5/12 months) visible in payslip generation.

### F. Employee Portal (Self-Service)
*   **Dashboard:** View remaining leave credits, announcements.
*   **Actions:** File Leaves, File OT, View/Download Payslips (PDF).

---

## 3. Database Schema (Frontend & Backend Data Types)

### 3.1 Recruitment
**Table: `applicants`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | Primary Key |
| `first_name` | STRING | |
| `last_name` | STRING | |
| `email` | STRING | Unique |
| `phone` | STRING | |
| `status` | ENUM | 'pool', 'exam', 'interview', 'passed', 'failed', 'hired' |
| `resume_path` | STRING | File path to storage |
| `exam_score` | DECIMAL(5,2) | Optional |
| `interviewer_notes`| TEXT | |

### 3.2 Employee Information
**Table: `employees`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | Primary Key |
| `user_id` | BIGINT | FK to `users` table (for login) |
| `employee_code` | STRING | Unique (e.g., 2026-001) |
| `sss_no` | STRING | |
| `philhealth_no` | STRING | |
| `pagibig_no` | STRING | |
| `tin_no` | STRING | |
| `civil_status` | STRING | Single, Married, Widowed |
| `birthday` | DATE | |

**Table: `employee_documents`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `employee_id` | BIGINT | FK |
| `doc_type` | STRING | 'NBI', 'Diploma', 'ID' |
| `file_path` | STRING | |

### 3.3 Employment History (Multi-Company)
**Table: `employment_records`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `employee_id` | BIGINT | FK |
| `company_id` | BIGINT | FK |
| `department_id` | BIGINT | FK |
| `position_id` | BIGINT | FK (Links to Ranks) |
| `rank` | ENUM | 'RankAndFile', 'Supervisor', 'Manager', 'Exec' |
| `employment_status`| STRING | 'Regular', 'Probationary', 'Project' |
| `start_date` | DATE | |
| `end_date` | DATE | Nullable (if active) |
| `is_active` | BOOLEAN | Only one active record per timeline |

**Table: `salary_history`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `employment_record_id`| BIGINT | FK |
| `basic_rate` | DECIMAL(12,2)| Monthly/Daily rate |
| `allowance` | DECIMAL(12,2)| Non-taxable |
| `effective_date` | DATE | |

### 3.4 Attendance & Leaves
**Table: `attendance_logs`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `employee_id` | BIGINT | FK |
| `date` | DATE | |
| `time_in` | DATETIME | |
| `time_out` | DATETIME | |
| `status` | ENUM | 'Present', 'Late', 'Absent', 'Leave' |
| `late_minutes` | INTEGER | Auto-computed |
| `ot_minutes` | INTEGER | Approved OT only |

**Table: `leave_types`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `name` | STRING | 'Vacation', 'Sick' |
| `days_per_year` | INTEGER | e.g., 15 |
| `is_convertible` | BOOLEAN | Cash conversion allowed? |
| `is_cumulative` | BOOLEAN | Carries over to next year? |

**Table: `leave_requests`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `employee_id` | BIGINT | FK |
| `leave_type_id` | BIGINT | FK |
| `start_date` | DATE | |
| `end_date` | DATE | |
| `reason` | TEXT | |
| `status` | ENUM | 'Pending', 'Approved', 'Rejected' |
| `approved_by` | BIGINT | FK (User ID) |

### 3.5 Payroll & Loans
**Table: `loans`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `employee_id` | BIGINT | FK |
| `loan_type` | STRING | 'SSS', 'Company', 'PagIBIG' |
| `principal` | DECIMAL | Total loan amount |
| `amortization` | DECIMAL | Deduction per cutoff |
| `balance` | DECIMAL | Remaining balance |
| `status` | STRING | 'Active', 'Paid' |

**Table: `payrolls`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `cutoff_start` | DATE | |
| `cutoff_end` | DATE | |
| `payout_date` | DATE | |
| `status` | STRING | 'Draft', 'Finalized' |

**Table: `payslips`**
| Column | Data Type | Details |
| :--- | :--- | :--- |
| `id` | BIGINT | PK |
| `payroll_id` | BIGINT | FK |
| `employee_id` | BIGINT | FK |
| `basic_pay` | DECIMAL | |
| `ot_pay` | DECIMAL | |
| `sss_deduction` | DECIMAL | |
| `philhealth_ded` | DECIMAL | |
| `pagibig_ded` | DECIMAL | |
| `tax_withheld` | DECIMAL | |
| `loan_deductions` | DECIMAL | Sum of loans |
| `net_pay` | DECIMAL | Final amount |
| `months_worked` | DECIMAL | For 13th month tracking (e.g., 5/12) |

---

## 4. Implementation Guide

### 4.1 Sidebar Structure (@resources/js/Components/Sidebar.vue)
The sidebar should be organized into these collapsible groups:

1.  **Dashboard** (`dashboard`)
2.  **Recruitment**
    *   Applicants (`recruitment.applicants`)
    *   Exam Results (`recruitment.exams`)
3.  **Employee Management**
    *   Employee Directory (`employees.index`)
    *   201 Files / Documents (`employees.files`)
    *   Movement/History (`employees.history`)
4.  **Timekeeping**
    *   Daily Time Records (`dtr.index`)
    *   Shift Scheduling (`shifts.index`)
    *   Overtime Requests (`dtr.overtime`)
5.  **Leave Management**
    *   Leave Requests (`leaves.requests`)
    *   Leave Balances (`leaves.balances`)
    *   Configuration (`leaves.types`)
6.  **Payroll**
    *   Generate Payroll (`payroll.generate`)
    *   Loans Management (`payroll.loans`)
    *   Govt. Tables (SSS/PHIC) (`payroll.settings`)
7.  **Administration**
    *   Users & Roles
    *   Companies

### 4.2 Permissions Blueprint (@database/seeders/RolesAndPermissionSeeder.php)
Define these exact permission keys in the seeder and check them in the Sidebar using `can()`.

**Recruitment:**
*   `recruitment.view` - View applicant list
*   `recruitment.create` - Add new applicant
*   `recruitment.hire` - Convert applicant to employee

**Employees:**
*   `employees.view` - View directory
*   `employees.view_sensitive` - View salary history/201 files
*   `employees.edit` - Update details/documents

**Timekeeping:**
*   `dtr.view` - View DTRs
*   `dtr.approve` - Approve manual logs/OT
*   `shifts.manage` - Assign schedules

**Leaves:**
*   `leaves.view_all` - View everyone's leaves (HR/Manager)
*   `leaves.approve` - Approve requests
*   `leaves.config` - Configure leave types/policies

**Payroll:**
*   `payroll.view` - View summaries
*   `payroll.process` - Execute calculations
*   `payroll.manage_loans` - Add/Edit loans
*   `payroll.settings` - Edit SSS/Tax tables

**Portal (For "User" Role):**
*   `portal.view` - Access self-service dashboard
*   `portal.file_leave` - Submit leave
*   `portal.file_ot` - Submit OT
*   `portal.view_payslip` - See own payslips

### 4.3 Service Layer Logic (@app/Http/Services/RoleService.php)
The service layer must support checking permissions dynamically for the "Employee Portal" logic.
*   **Method:** `getEmployeePermissions($userId)` -> Returns specifically `portal.*` permissions.
*   **Method:** `isManager($userId)` -> Checks if user has subordinates for approval workflows.

---

## 5. Automation Requirements
1.  **DTR to Payroll:** A scheduled trigger that sums up "Approved" hours from `attendance_logs` to `payslips` when generating payroll.
2.  **Leave Conversion:** Scheduled task (Year-End) to check `leave_types.is_convertible`. If true, calculate remaining balance * daily rate and add to `payroll_items` as "Leave Conversion".
3.  **Loan Amortization:** When payroll is finalized, deduct the amortization amount from `loans.balance`. If balance <= 0, set status to `Paid`.
