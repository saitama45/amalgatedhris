<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Strategic Action Layout Form (SALF)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            padding: 5px;
            border: 1px solid #000;
        }
        .label {
            font-weight: bold;
            background-color: #f2f2f2;
        }
        .title-bar {
            background-color: #000;
            color: #fff;
            text-align: center;
            font-weight: bold;
            padding: 5px;
            margin-bottom: 5px;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
        }
        .main-table th {
            background-color: #000;
            color: #fff;
            padding: 5px;
            border: 1px solid #000;
            text-align: center;
        }
        .main-table td {
            padding: 5px;
            border: 1px solid #000;
            vertical-align: top;
        }
        .section-header {
            background-color: #e6e6e6;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .progress-bar {
            width: 100%;
            background-color: #ddd;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 2px;
        }
        .progress-fill {
            height: 100%;
            background-color: #4caf50;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="label" width="15%">NAME</td>
            <td width="35%">{{ strtoupper(($salf->employee->first_name ?? '') . ' ' . ($salf->employee->last_name ?? '')) }}</td>
            <td class="label" width="15%">POSITION/TITLE</td>
            <td width="35%">{{ strtoupper($salf->employee->position?->name ?? 'N/A') }}</td>
        </tr>
        <tr>
            <td class="label">DEPT</td>
            <td>{{ strtoupper($salf->department?->name ?? ($salf->employee->department?->name ?? 'N/A')) }}</td>
            <td class="label">COMPANY</td>
            <td>{{ strtoupper($salf->company?->name ?? ($salf->employee->company?->name ?? 'N/A')) }}</td>
        </tr>
        <tr>
            <td class="label">PERIOD COVERED</td>
            <td>{{ $salf->period_covered }}</td>
            <td class="label">APPROVED BY:</td>
            <td>{{ $salf->approved_by }}</td>
        </tr>
    </table>

    <div class="title-bar">
        STRATEGIC ACTION LAYOUT FORM (SALF)
    </div>

    <table class="main-table">
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="20%">AREAS OF CONCERN</th>
                <th width="25%">ACTION PLAN TO BE TAKEN</th>
                <th width="10%">SUPPORT GROUP</th>
                <th width="10%">TARGET DATE</th>
                <th width="7%">ACTUAL</th>
                <th width="7%">TARGET</th>
                <th width="8%">EFF %</th>
                <th width="10%">REMARKS</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $counter = 1;
            @endphp
            @foreach($salf->items as $item)
                @if($item->is_header)
                    <tr class="section-header">
                        <td colspan="9" style="background-color: #e6e6e6; padding: 8px; font-size: 11px;">
                            {{ strtoupper($item->section) }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td class="text-center">{{ $counter++ }}</td>
                        <td>
                            @if($item->section)
                                <div style="font-weight: bold; font-size: 8px; color: #666; margin-bottom: 2px;">{{ strtoupper($item->section) }}</div>
                            @endif
                            {{ $item->area_of_concern }}
                        </td>
                        <td>{{ $item->action_plan }}</td>
                        <td class="text-center">{{ $item->support_group }}</td>
                        <td class="text-center">{{ $item->target_date ? $item->target_date->format('n/j/Y') : '' }}</td>
                        <td class="text-right">{{ number_format($item->actual_value, 2) }}</td>
                        <td class="text-right">{{ number_format($item->target_value, 2) }}</td>
                        <td class="text-center">
                            {{ number_format($item->efficiency, 0) }}%
                        </td>
                        <td>{{ $item->remarks }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="7" class="text-right">OVERALL EFFICIENCY</td>
                <td class="text-center">{{ number_format($overallEfficiency, 2) }}%</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
