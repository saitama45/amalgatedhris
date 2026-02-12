<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Time Record</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            text-transform: uppercase;
        }
        .header p {
            margin: 2px 0;
            font-size: 10px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 3px;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            width: 80px;
        }
        .value {
            border-bottom: 1px solid #ccc;
        }
        .dtr-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .dtr-table th, .dtr-table td {
            border: 1px solid #000;
            padding: 4px 2px;
            text-align: center;
        }
        .dtr-table th {
            background-color: #f2f2f2;
            text-transform: uppercase;
            font-size: 8px;
        }
        .footer {
            margin-top: 20px;
        }
        .signature-section {
            width: 100%;
            margin-top: 30px;
        }
        .signature-box {
            width: 45%;
            display: inline-block;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 30px;
            padding-top: 3px;
        }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Daily Time Record</h1>
            <p>{{ $employee->activeEmploymentRecord->company->name ?? 'AMALGATED HRIS' }}</p>
        </div>

        <table class="info-table">
            <tr>
                <td class="label">Name:</td>
                <td class="value font-bold" style="font-size: 11px;">{{ $employee->user->name }}</td>
                <td class="label">Employee ID:</td>
                <td class="value">{{ $employee->employee_code }}</td>
            </tr>
            <tr>
                <td class="label">Department:</td>
                <td class="value">{{ $employee->activeEmploymentRecord->department->name ?? 'N/A' }}</td>
                <td class="label">Position:</td>
                <td class="value">{{ $employee->activeEmploymentRecord->position->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Period:</td>
                <td class="value font-bold" colspan="3">
                    {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                </td>
            </tr>
        </table>

        <table class="dtr-table">
            <thead>
                <tr>
                    <th rowspan="2" width="15%">Date</th>
                    <th colspan="2">Morning</th>
                    <th colspan="2">Afternoon</th>
                    <th colspan="2">Overtime</th>
                    <th rowspan="2" width="10%">Work Hrs</th>
                    <th rowspan="2" width="8%">Late</th>
                    <th rowspan="2" width="8%">UT</th>
                </tr>
                <tr>
                    <th width="8%">In</th>
                    <th width="8%">Out</th>
                    <th width="8%">In</th>
                    <th width="8%">Out</th>
                    <th width="8%">In</th>
                    <th width="8%">Out</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalWorkHours = 0;
                    $totalLate = 0;
                    $totalUndertime = 0;
                    
                    $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
                    $logsByDate = $logs->keyBy(function($log) {
                        return \Carbon\Carbon::parse($log->date)->format('Y-m-d');
                    });
                @endphp

                @foreach($period as $date)
                    @php
                        $dateStr = $date->format('Y-m-d');
                        $log = $logsByDate->get($dateStr);
                        
                        $morningIn = '';
                        $morningOut = '';
                        $afternoonIn = '';
                        $afternoonOut = '';
                        $workHours = 0;
                        $late = 0;
                        $undertime = 0;

                        if ($log) {
                            $timeIn = $log->time_in ? \Carbon\Carbon::parse($log->time_in) : null;
                            $timeOut = $log->time_out ? \Carbon\Carbon::parse($log->time_out) : null;
                            
                            if ($timeIn) {
                                if ($timeIn->format('A') == 'AM') {
                                    $morningIn = $timeIn->format('h:i');
                                } else {
                                    $afternoonIn = $timeIn->format('h:i');
                                }
                            }

                            if ($timeOut) {
                                if ($timeOut->format('A') == 'AM') {
                                    $morningOut = $timeOut->format('h:i');
                                } else {
                                    $afternoonOut = $timeOut->format('h:i');
                                }
                                
                                // Handle half-day/break transitions
                                if ($timeIn && $timeIn->format('A') == 'AM' && $timeOut->format('A') == 'PM') {
                                    $morningOut = '12:00';
                                    $afternoonIn = '01:00';
                                }
                            }

                            // Work Hours Calculation (Mirroring Portal logic)
                            if ($timeIn && $timeOut) {
                                $shift = $log->employee->activeEmploymentRecord->defaultShift ?? null;
                                if ($shift) {
                                    $shiftStart = \Carbon\Carbon::parse($dateStr . ' ' . $shift->start_time);
                                    $breakStart = $shiftStart->copy()->addHours(4);
                                    $breakMinutes = (int) ($shift->break_minutes ?? 60);
                                    $breakEnd = $breakStart->copy()->addMinutes($breakMinutes);

                                    $calcIn = $timeIn->copy();
                                    $lateMinutes = $shiftStart->diffInMinutes($timeIn, false);
                                    if ($lateMinutes > 120 && $lateMinutes <= 300) {
                                        $calcIn = $breakEnd->copy();
                                    }

                                    $mStart = $calcIn->lt($breakStart) ? $calcIn : $breakStart;
                                    $mEnd = $timeOut->lt($breakStart) ? $timeOut : $breakStart;
                                    $morningMs = max(0, $mStart->diffInSeconds($mEnd));

                                    $aStart = $calcIn->gt($breakEnd) ? $calcIn : $breakEnd;
                                    $aEnd = $timeOut->gt($breakEnd) ? $timeOut : $breakEnd;
                                    $afternoonMs = max(0, $aStart->diffInSeconds($aEnd));

                                    $workHours = ($morningMs + $afternoonMs) / 3600;
                                    
                                    // Late Calculation
                                    if ($lateMinutes > 5 && ($lateMinutes <= 120 || $lateMinutes > 300)) {
                                        $late = ceil($lateMinutes / 30) * 30;
                                    }

                                    // Undertime
                                    $shiftEnd = \Carbon\Carbon::parse($dateStr . ' ' . $shift->end_time);
                                    if ($shiftEnd->lt($shiftStart)) $shiftEnd->addDay();
                                    $expectedHours = ($shiftStart->diffInSeconds($shiftEnd) - ($breakMinutes * 60)) / 3600;
                                    $ut = $expectedHours - $workHours;
                                    if ($ut > 0.02) $undertime = round($ut * 60);
                                } else {
                                    $workHours = $timeIn->diffInMinutes($timeOut) / 60;
                                }
                            }
                            
                            $totalWorkHours += $workHours;
                            $totalLate += $late;
                            $totalUndertime += $undertime;
                        }
                    @endphp
                    <tr>
                        <td class="text-left">{{ $date->format('M d (D)') }}</td>
                        <td>{{ $morningIn }}</td>
                        <td>{{ $morningOut }}</td>
                        <td>{{ $afternoonIn }}</td>
                        <td>{{ $afternoonOut }}</td>
                        <td></td>
                        <td></td>
                        <td class="font-bold">{{ $workHours > 0 ? number_format($workHours, 2) : '' }}</td>
                        <td style="color: #dc2626;">{{ $late > 0 ? $late : '' }}</td>
                        <td style="color: #d97706;">{{ $undertime > 0 ? $undertime : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="7" class="text-right">Totals</th>
                    <th>{{ number_format($totalWorkHours, 2) }}</th>
                    <th>{{ $totalLate }}</th>
                    <th>{{ $totalUndertime }}</th>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            <p>I certify on my honor that the above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival and departure from office.</p>
            
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line">
                        <strong>{{ $employee->user->name }}</strong><br>
                        Employee Signature
                    </div>
                </div>
                <div class="signature-box" style="float: right;">
                    <div class="signature-line">
                        <strong>Verified by</strong><br>
                        Department Manager / HR
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
