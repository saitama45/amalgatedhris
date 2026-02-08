<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Report</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10px;
            color: #334155;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
        }
        .header p {
            margin: 5px 0 0;
            color: #64748b;
        }
        .summary {
            margin-bottom: 20px;
            width: 100%;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            padding: 5px;
            border: 1px solid #e2e8f0;
        }
        .label {
            font-weight: bold;
            background-color: #f8fafc;
            width: 30%;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table th {
            background-color: #1e293b;
            color: #ffffff;
            padding: 8px 5px;
            text-align: left;
            text-transform: uppercase;
            font-size: 8px;
        }
        .table td {
            border: 1px solid #e2e8f0;
            padding: 6px 5px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .bg-gray {
            background-color: #f8fafc;
        }
        .text-emerald {
            color: #059669;
        }
        .text-rose {
            color: #e11d48;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
            font-style: italic;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payroll Report</h1>
        <p>{{ $payroll->company->name }}</p>
        <p>Period: {{ \Carbon\Carbon::parse($payroll->cutoff_start)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($payroll->cutoff_end)->format('M d, Y') }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td class="label">Total Gross Earnings</td>
                <td class="font-bold">PHP {{ number_format($summary['total_gross'], 2) }}</td>
                <td class="label">Total Net Pay</td>
                <td class="font-bold text-emerald">PHP {{ number_format($summary['total_net'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Employees Processed</td>
                <td>{{ $summary['employee_count'] }}</td>
                <td class="label">Payout Date</td>
                <td>{{ \Carbon\Carbon::parse($payroll->payout_date)->format('M d, Y') }}</td>
            </tr>
        </table>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Employee</th>
                <th class="text-right">Basic</th>
                <th class="text-right">Allowances</th>
                <th class="text-right">OT Pay</th>
                <th class="text-right">Gross Earnings</th>
                <th class="text-right">Total Deductions</th>
                <th class="text-right">Net Pay</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payslips as $slip)
            <tr>
                <td>
                    <div class="font-bold">{{ $slip->employee->user->name }}</div>
                    <div style="font-size: 7px; color: #64748b;">{{ $slip->employee->employee_code }}</div>
                </td>
                <td class="text-right">{{ number_format($slip->basic_pay, 2) }}</td>
                <td class="text-right">{{ number_format($slip->allowances, 2) }}</td>
                <td class="text-right">{{ number_format($slip->ot_pay, 2) }}</td>
                <td class="text-right font-bold">{{ number_format($slip->gross_pay, 2) }}</td>
                <td class="text-right text-rose">
                    @php
                        $totalDeductions = (float)$slip->sss_deduction + 
                                          (float)$slip->philhealth_ded + 
                                          (float)$slip->pagibig_ded + 
                                          (float)$slip->tax_withheld + 
                                          (float)$slip->late_deduction + 
                                          (float)$slip->undertime_deduction + 
                                          (float)$slip->other_deductions;
                    @endphp
                    {{ number_format($totalDeductions, 2) }}
                </td>
                <td class="text-right font-bold text-emerald" style="font-size: 11px;">{{ number_format($slip->net_pay, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ now()->format('M d, Y h:i A') }}
    </div>
</body>
</html>
