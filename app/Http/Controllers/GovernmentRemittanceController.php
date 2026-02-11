<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GovernmentRemittanceExport;

class GovernmentRemittanceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('government_remittances.view');

        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        $companyId = $request->input('company_id');
        $type = $request->input('type', 'sss'); // sss, philhealth, pagibig, tax

        $companies = Company::all();

        $query = Payslip::whereHas('payroll', function($q) use ($year, $month, $companyId) {
            $q->where('status', 'Finalized')
              ->whereYear('payout_date', $year)
              ->whereMonth('payout_date', $month);
            
            if ($companyId) {
                $q->where('company_id', $companyId);
            }
        })->with(['employee.user', 'employee.activeEmploymentRecord.position', 'payroll.company']);

        $payslips = $query->get();

        // Process data based on type
        $reportData = $payslips->map(function($slip) use ($type) {
            $data = [
                'employee_name' => $slip->employee->user->name,
                'employee_code' => $slip->employee->employee_code,
                'company' => $slip->payroll->company->name,
                'payout_date' => $slip->payroll->payout_date->format('Y-m-d'),
            ];

            $details = $slip->details['contributions'] ?? [];

            switch ($type) {
                case 'sss':
                    $data['id_no'] = $slip->employee->sss_no;
                    $data['ee_share'] = $slip->sss_deduction;
                    $data['er_share'] = $details['sss']['er'] ?? 0;
                    $data['ec_share'] = $details['sss']['ec'] ?? 0;
                    $data['total'] = $data['ee_share'] + $data['er_share'];
                    break;
                case 'philhealth':
                    $data['id_no'] = $slip->employee->philhealth_no;
                    $data['ee_share'] = $slip->philhealth_ded;
                    $data['er_share'] = $details['philhealth']['er'] ?? 0;
                    $data['total'] = $data['ee_share'] + $data['er_share'];
                    break;
                case 'pagibig':
                    $data['id_no'] = $slip->employee->pagibig_no;
                    $data['ee_share'] = $slip->pagibig_ded;
                    $data['er_share'] = $details['pagibig']['er'] ?? 0;
                    $data['total'] = $data['ee_share'] + $data['er_share'];
                    break;
                case 'tax':
                    $data['id_no'] = $slip->employee->tin_no;
                    $data['taxable_income'] = $slip->gross_pay - ($slip->sss_deduction + $slip->philhealth_ded + $slip->pagibig_ded);
                    $data['tax_withheld'] = $slip->tax_withheld;
                    break;
            }

            return $data;
        })->filter(function($item) use ($type) {
            if ($type === 'tax') {
                return $item['tax_withheld'] > 0;
            }
            return $item['ee_share'] > 0 || ($item['er_share'] ?? 0) > 0;
        })->values();

        return Inertia::render('Reports/GovernmentRemittances', [
            'reportData' => $reportData,
            'companies' => $companies,
            'filters' => [
                'year' => (int)$year,
                'month' => (int)$month,
                'company_id' => $companyId,
                'type' => $type,
            ]
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('government_remittances.view');
        
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        $companyId = $request->input('company_id');
        $type = $request->input('type', 'sss');

        $filename = strtoupper($type) . "_Remittance_{$year}_{$month}.xlsx";

        return Excel::download(new GovernmentRemittanceExport($year, $month, $companyId, $type), $filename);
    }
}
