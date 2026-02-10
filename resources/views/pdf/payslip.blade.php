<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $slip->employee->user->name }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 20px;
        }
        .payslip-container {
            border: 1px solid #e2e8f0;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #334155;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #64748b;
            font-weight: bold;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 25px;
        }
        .info-grid table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-grid td {
            padding: 4px 0;
            vertical-align: top;
        }
        .label {
            color: #64748b;
            font-weight: bold;
            width: 120px;
            text-transform: uppercase;
            font-size: 9px;
        }
        .value {
            font-weight: bold;
            color: #1e293b;
        }
        .main-content {
            width: 100%;
            border-collapse: collapse;
        }
        .section-title {
            background-color: #f1f5f9;
            padding: 8px 15px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }
        .item-row td {
            padding: 8px 15px;
            border-bottom: 1px solid #f1f5f9;
        }
        .amount {
            text-align: right;
            font-family: monospace;
            font-size: 12px;
        }
        .total-row {
            background-color: #f8fafc;
            font-weight: bold;
        }
        .total-row td {
            padding: 12px 15px;
            border-top: 1px solid #cbd5e1;
        }
        .net-pay-container {
            margin-top: 30px;
            background-color: #0f172a;
            color: #ffffff;
            padding: 20px;
            text-align: right;
            border-radius: 8px;
        }
        .net-pay-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
            display: block;
        }
        .net-pay-value {
            font-size: 28px;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
        .signature-section {
            margin-top: 60px;
            width: 100%;
        }
        .signature-box {
            border-top: 1px solid #334155;
            text-align: center;
            padding-top: 8px;
            width: 80%;
            margin: 0 auto;
            font-weight: bold;
            color: #475569;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="payslip-container">
        <div class="header">
            <h1>Payslip</h1>
            <p>{{ $payroll->company->name }}</p>
        </div>

        <div class="info-grid">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <table>
                            <tr>
                                <td class="label">Employee:</td>
                                <td class="value">{{ $slip->employee->user->name }}</td>
                            </tr>
                            <tr>
                                <td class="label">ID Number:</td>
                                <td class="value">{{ $slip->employee->employee_code }}</td>
                            </tr>
                            <tr>
                                <td class="label">Position:</td>
                                <td class="value">{{ $slip->employee->activeEmploymentRecord->position->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Department:</td>
                                <td class="value">{{ $slip->employee->activeEmploymentRecord->department->name ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </td>
                    <td width="50%">
                        <table align="right">
                            <tr>
                                <td class="label">Cut-off Period:</td>
                                <td class="value">{{ \Carbon\Carbon::parse($payroll->cutoff_start)->format('M d') }} - {{ \Carbon\Carbon::parse($payroll->cutoff_end)->format('M d, Y') }}</td>
                            </tr>
                            <tr>
                                <td class="label">Payout Date:</td>
                                <td class="value">{{ \Carbon\Carbon::parse($payroll->payout_date)->format('M d, Y') }}</td>
                            </tr>
                            @if(isset($slip->details['leave_days']) && $slip->details['leave_days'] > 0)
                            <tr>
                                <td class="label">Paid Leave:</td>
                                <td class="value">{{ $slip->details['leave_days'] }} day/s</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="label">Status:</td>
                                <td class="value">{{ $payroll->status }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <table class="main-content" width="100%">
            <tr>
                <td width="50%" style="vertical-align: top; padding-right: 10px;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td colspan="2" class="section-title">Earnings</td>
                        </tr>
                        <tr class="item-row">
                            <td>Basic Pay (Period)</td>
                            <td class="amount">{{ number_format($slip->basic_pay, 2) }}</td>
                        </tr>
                        <tr class="item-row">
                            <td>Allowances</td>
                            <td class="amount">{{ number_format($slip->allowances, 2) }}</td>
                        </tr>
                        <tr class="item-row">
                            <td>Overtime Pay</td>
                            <td class="amount">{{ number_format($slip->ot_pay, 2) }}</td>
                        </tr>
                        <tr class="total-row">
                            <td>Gross Earnings</td>
                            <td class="amount">PHP {{ number_format($slip->gross_pay, 2) }}</td>
                        </tr>
                    </table>
                </td>
                <td width="50%" style="vertical-align: top; padding-left: 10px;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td colspan="2" class="section-title">Deductions</td>
                        </tr>
                        <tr class="item-row">
                            <td>Late / Undertime</td>
                            <td class="amount">{{ number_format($slip->late_deduction + $slip->undertime_deduction, 2) }}</td>
                        </tr>
                        <tr class="item-row">
                            <td>SSS Contribution</td>
                            <td class="amount">{{ number_format($slip->sss_deduction, 2) }}</td>
                        </tr>
                        <tr class="item-row">
                            <td>PhilHealth</td>
                            <td class="amount">{{ number_format($slip->philhealth_ded, 2) }}</td>
                        </tr>
                        <tr class="item-row">
                            <td>Pag-IBIG</td>
                            <td class="amount">{{ number_format($slip->pagibig_ded, 2) }}</td>
                        </tr>
                        <tr class="item-row">
                            <td>Withholding Tax</td>
                            <td class="amount">{{ number_format($slip->tax_withheld, 2) }}</td>
                        </tr>
                        
                        @if(isset($slip->details['deductions']))
                            @foreach($slip->details['deductions'] as $detail)
                                <tr class="item-row">
                                    <td>
                                        {{ $detail['type'] }}
                                        @if(($detail['is_loan'] ?? false) && isset($detail['installment']))
                                            <span style="font-size: 8px; color: #64748b;">(Installment {{ $detail['installment'] }})</span>
                                        @endif
                                    </td>
                                    <td class="amount">{{ number_format($detail['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        @endif

                        @if(isset($slip->details['absence_deduction']) && $slip->details['absence_deduction'] > 0)
                            <tr class="item-row">
                                <td>Absences ({{ $slip->details['absent_days'] }} day/s)</td>
                                <td class="amount">{{ number_format($slip->details['absence_deduction'], 2) }}</td>
                            </tr>
                        @endif

                        @php
                            $totalDeductions = (float)$slip->sss_deduction + 
                                              (float)$slip->philhealth_ded + 
                                              (float)$slip->pagibig_ded + 
                                              (float)$slip->tax_withheld + 
                                              (float)$slip->late_deduction + 
                                              (float)$slip->undertime_deduction + 
                                              (float)$slip->other_deductions;
                        @endphp
                        <tr class="total-row">
                            <td>Total Deductions</td>
                            <td class="amount">PHP {{ number_format($totalDeductions, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="net-pay-container">
            <span class="net-pay-label">Net Take Home Pay</span>
            <span class="net-pay-value">PHP {{ number_format($slip->net_pay, 2) }}</span>
        </div>

        <div class="signature-section">
            <table width="100%">
                <tr>
                    <td width="50%" align="center">
                        <div class="signature-box">Employee Signature</div>
                    </td>
                    <td width="50%" align="center">
                        <div class="signature-box">Authorized Representative</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            This is a computer-generated payslip. No signature is required for validity unless specified by company policy.<br>
            Generated on {{ now()->format('F d, Y h:i A') }}
        </div>
    </div>
</body>
</html>
