<?php

namespace App\Exports;

use App\Models\Payslip;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PayrollExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $payroll;

    public function __construct($payroll)
    {
        $this->payroll = $payroll;
    }

    public function collection()
    {
        return Payslip::where('payroll_id', $this->payroll->id)
            ->with('employee.user')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Employee Code',
            'Basic Pay',
            'Allowances',
            'Adjustments',
            'OT Pay',
            'Gross Earnings',
            'SSS',
            'PhilHealth',
            'Pag-IBIG',
            'Tax Withheld',
            'Late',
            'Undertime',
            'Other Deductions',
            'Total Deductions',
            'Net Pay'
        ];
    }

    public function map($payslip): array
    {
        $totalDeductions = (float)$payslip->sss_deduction + 
                          (float)$payslip->philhealth_ded + 
                          (float)$payslip->pagibig_ded + 
                          (float)$payslip->tax_withheld + 
                          (float)$payslip->late_deduction + 
                          (float)$payslip->undertime_deduction + 
                          (float)$payslip->other_deductions;

        return [
            $payslip->employee->user->name,
            $payslip->employee->employee_code,
            $payslip->basic_pay,
            $payslip->allowances,
            $payslip->adjustments,
            $payslip->ot_pay,
            $payslip->gross_pay,
            $payslip->sss_deduction,
            $payslip->philhealth_ded,
            $payslip->pagibig_ded,
            $payslip->tax_withheld,
            $payslip->late_deduction,
            $payslip->undertime_deduction,
            $payslip->other_deductions,
            $totalDeductions,
            $payslip->net_pay
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate-800
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        $lastRow = $sheet->getHighestRow();

        // Alternate row colors for better readability
        for ($i = 2; $i <= $lastRow; $i++) {
            if ($i % 2 == 0) {
                $sheet->getStyle('A' . $i . ':P' . $i)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8FAFC'); // Slate-50
            }
        }

        // Highlight Net Pay column
        $sheet->getStyle('P2:P' . $lastRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '059669'], // Emerald-600
            ],
        ]);

        // Currency format for columns C to P
        $sheet->getStyle('C2:P' . $lastRow)
            ->getNumberFormat()
            ->setFormatCode('"₱"#,##0.00');

        // Borders
        $sheet->getStyle('A1:P' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'], // Slate-200
                ],
            ],
        ]);

        return [];
    }
}
