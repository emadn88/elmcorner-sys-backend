<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #{{ $bill->id }} - {{ $bill->student->full_name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
            direction: ltr;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #059669;
        }
        .header h1 {
            color: #059669;
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .header p {
            color: #666;
            font-size: 12px;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .main-table th {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            border: 1px solid #047857;
        }
        .main-table td {
            padding: 10px 15px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .main-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .label-cell {
            font-weight: bold;
            color: #374151;
            width: 180px;
            background-color: #f3f4f6;
        }
        .value-cell {
            color: #111827;
        }
        .classes-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .classes-table thead {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
        }
        .classes-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            border: 1px solid #047857;
        }
        .classes-table td {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .classes-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .classes-table tbody tr:hover {
            background-color: #f3f4f6;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary-box {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #059669;
            border-radius: 8px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 12px;
        }
        .summary-row.total {
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #059669;
            font-weight: bold;
            font-size: 16px;
            color: #059669;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-sent {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-paid {
            background-color: #d1fae5;
            color: #065f46;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        .note-box {
            margin: 15px 0;
            padding: 12px;
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 4px;
            font-size: 11px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ElmCorner Academy - Invoice</h1>
        <p>Generated: {{ now()->format('F d, Y') }}</p>
    </div>

    <table class="main-table">
        <tr>
            <th colspan="2" style="text-align: center;">Student & Bill Information</th>
        </tr>
        <tr>
            <td class="label-cell">Student Name</td>
            <td class="value-cell"><strong>{{ $bill->student->full_name }}</strong></td>
        </tr>
        @if($bill->student->whatsapp)
        <tr>
            <td class="label-cell">WhatsApp</td>
            <td class="value-cell">{{ $bill->student->whatsapp }}</td>
        </tr>
        @endif
        <tr>
            <td class="label-cell">Billing Period</td>
            <td class="value-cell">
                @if($classes && $classes->count() > 0)
                    @php
                        $dates = $classes->pluck('class_date')->sort();
                        $startDate = $dates->first();
                        $endDate = $dates->last();
                    @endphp
                    {{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}
                @else
                    {{ $bill->bill_date->format('M d, Y') }}
                @endif
            </td>
        </tr>
    </table>

    @if($bill->is_custom && $bill->description)
    <div class="note-box">
        <strong>Note:</strong> {{ $bill->description }}
    </div>
    @endif

    @if($classes && $classes->count() > 0)
    <table class="classes-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Time</th>
                <th>Duration</th>
                <th>Teacher</th>
                <th>Course</th>
                <th class="text-right">Hourly Rate</th>
                <th class="text-right">Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach($classes as $index => $class)
            @php
                $hours = $class->duration / 60;
                $hourlyRate = $class->teacher->hourly_rate ?? 0;
                $cost = $hours * $hourlyRate;
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $class->class_date->format('M d, Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($class->start_time)->format('h:i A') }}</td>
                <td>{{ number_format($hours, 2) }} hrs</td>
                <td>{{ $class->teacher->user->name ?? 'N/A' }}</td>
                <td>{{ $class->course->name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($hourlyRate, 2) }} {{ $bill->currency }}</td>
                <td class="text-right"><strong>{{ number_format($cost, 2) }} {{ $bill->currency }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="summary-box">
        <div class="summary-row">
            <span>Total Classes:</span>
            <span><strong>{{ $classes ? $classes->count() : 1 }}</strong></span>
        </div>
        <div class="summary-row">
            <span>Total Hours:</span>
            <span><strong>{{ number_format($bill->total_hours ?? ($bill->duration / 60), 2) }} hours</strong></span>
        </div>
        @if($bill->is_custom)
        <div class="summary-row">
            <span>Bill Type:</span>
            <span><strong>Custom Bill</strong></span>
        </div>
        @endif
        <div class="summary-row total">
            <span>Total Amount:</span>
            <span>{{ number_format($bill->amount, 2) }} {{ $bill->currency }}</span>
        </div>
    </div>

    <div class="footer">
        <p><strong>ElmCorner Academy</strong></p>
        <p>Thank you for trusting us</p>
        <p>This is an automated invoice generated by ElmCorner Management System</p>
    </div>
</body>
</html>
