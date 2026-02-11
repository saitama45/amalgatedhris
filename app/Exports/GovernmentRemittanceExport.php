<?php

namespace App\Exports;

use App\Models\Payslip;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class GovernmentRemittanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    protected $year;
    protected $month;
    protected $companyId;
    protected $type;

    public function __construct($year, $month, $companyId, $type)
    {
        $this->year = $year;
        $this->month = $month;
        $this->companyId = $companyId;
        $this->type = $type;
    }

    public function collection()
    {
        $query = Payslip::whereHas('payroll', function($q) {
            $q->where('status', 'Finalized')
              ->whereYear('payout_date', $this->year)
              ->whereMonth('payout_date', $this->month);
            
            if ($this->companyId) {
                $q->where('company_id', $this->companyId);
            }
        })->with(['employee.user', 'payroll.company']);

        return $query->get()->filter(function($slip) {
            switch ($this->type) {
                case 'tax':
                    return $slip->tax_withheld > 0;
                case 'sss':
                    return $slip->sss_deduction > 0 || ($slip->details['contributions']['sss']['er'] ?? 0) > 0;
                case 'philhealth':
                    return $slip->philhealth_ded > 0 || ($slip->details['contributions']['philhealth']['er'] ?? 0) > 0;
                case 'pagibig':
                    return $slip->pagibig_ded > 0 || ($slip->details['contributions']['pagibig']['er'] ?? 0) > 0;
            }
            return false;
        });
    }

    public function title(): string
    {
        return strtoupper($this->type) . " Remittance";
    }

    public function headings(): array
    {
        $headings = [
            'Employee Code',
            'Employee Name',
            'Company',
            'Payout Date',
        ];

        switch ($this->type) {
            case 'sss':
                $headings[] = 'SSS Number';
                $headings[] = 'EE Share';
                $headings[] = 'ER Share';
                $headings[] = 'EC Share';
                $headings[] = 'Total';
                break;
            case 'philhealth':
                $headings[] = 'PhilHealth Number';
                $headings[] = 'EE Share';
                $headings[] = 'ER Share';
                $headings[] = 'Total';
                break;
            case 'pagibig':
                $headings[] = 'Pag-IBIG Number';
                $headings[] = 'EE Share';
                $headings[] = 'ER Share';
                $headings[] = 'Total';
                break;
            case 'tax':
                $headings[] = 'TIN';
                $headings[] = 'Gross Pay';
                $headings[] = 'Taxable Income';
                $headings[] = 'Tax Withheld';
                break;
        }

        return $headings;
    }

    public function map($slip): array
    {
        $data = [
            $slip->employee->employee_code,
            $slip->employee->user->name,
            $slip->payroll->company->name,
            $slip->payroll->payout_date->format('Y-m-d'),
        ];

        $details = $slip->details['contributions'] ?? [];

        switch ($this->type) {
            case 'sss':
                $data[] = $slip->employee->sss_no;
                $data[] = $slip->sss_deduction;
                $data[] = $details['sss']['er'] ?? 0;
                $data[] = $details['sss']['ec'] ?? 0;
                $data[] = $slip->sss_deduction + ($details['sss']['er'] ?? 0);
                break;
            case 'philhealth':
                $data[] = $slip->employee->philhealth_no;
                $data[] = $slip->philhealth_ded;
                $data[] = $details['philhealth']['er'] ?? 0;
                $data[] = $slip->philhealth_ded + ($details['philhealth']['er'] ?? 0);
                break;
            case 'pagibig':
                $data[] = $slip->employee->pagibig_no;
                $data[] = $slip->pagibig_ded;
                $data[] = $details['pagibig']['er'] ?? 0;
                $data[] = $slip->pagibig_ded + ($details['pagibig']['er'] ?? 0);
                break;
            case 'tax':
                $data[] = $slip->employee->tin_no;
                $data[] = $slip->gross_pay;
                $taxableIncome = $slip->gross_pay - ($slip->sss_deduction + $slip->philhealth_ded + $slip->pagibig_ded);
                $data[] = $taxableIncome;
                $data[] = $slip->tax_withheld;
                break;
        }

        return $data;
    }
}
