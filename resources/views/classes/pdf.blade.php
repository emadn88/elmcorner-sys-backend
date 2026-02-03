<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes Schedule - {{ $startDate }} @if($endDate && $endDate !== $startDate) to {{ $endDate }} @endif</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            direction: ltr;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #059669;
        }
        .header h1 {
            color: #059669;
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .header p {
            color: #666;
            font-size: 11px;
        }
        .info-box {
            margin-bottom: 15px;
            padding: 12px;
            background-color: #f0fdf4;
            border-left: 4px solid #059669;
            border-radius: 4px;
        }
        .info-box p {
            margin: 3px 0;
            font-size: 10px;
        }
        .classes-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .classes-table thead {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
        }
        .classes-table th {
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            border: 1px solid #047857;
        }
        .classes-table td {
            padding: 6px 10px;
            border: 1px solid #e5e7eb;
            font-size: 9px;
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
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-attended {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-absent {
            background-color: #fed7aa;
            color: #9a3412;
        }
        .summary-box {
            margin-top: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #059669;
            border-radius: 6px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 10px;
        }
        .summary-row.total {
            margin-top: 8px;
            padding-top: 10px;
            border-top: 2px solid #059669;
            font-weight: bold;
            font-size: 14px;
            color: #059669;
        }
        .footer {
            margin-top: 25px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ElmCorner Academy - Classes Schedule</h1>
        <p>Generated: {{ now()->format('F d, Y \a\t h:i A') }}</p>
    </div>

    <div class="info-box">
        <p><strong>Date Range:</strong> 
            @if($startDate && $endDate && $startDate !== $endDate)
                {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
            @elseif($startDate)
                {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}
            @else
                All Classes
            @endif
        </p>
        <p><strong>Total Classes:</strong> {{ $classes->count() }}</p>
    </div>

    @if($classes->count() > 0)
    <table class="classes-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th style="width: 80px;">Date</th>
                <th style="width: 100px;">Time</th>
                <th style="width: 60px;">Duration</th>
                <th style="width: 120px;">Student</th>
                <th style="width: 120px;">Teacher</th>
                <th style="width: 100px;">Course</th>
                <th style="width: 80px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($classes as $index => $class)
            @php
                $durationHours = $class->duration / 60;
                $statusClass = 'status-pending';
                $statusText = 'Pending';
                
                if ($class->status === 'attended') {
                    $statusClass = 'status-attended';
                    $statusText = 'Attended';
                } elseif (in_array($class->status, ['cancelled_by_student', 'cancelled_by_teacher'])) {
                    $statusClass = 'status-cancelled';
                    $statusText = 'Cancelled';
                } elseif ($class->status === 'absent_student') {
                    $statusClass = 'status-absent';
                    $statusText = 'Absent';
                }
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($class->class_date)->format('M d, Y') }}</td>
                <td>
                    {{ \Carbon\Carbon::parse($class->start_time)->format('h:i A') }} - 
                    {{ \Carbon\Carbon::parse($class->end_time)->format('h:i A') }}
                </td>
                <td class="text-center">{{ $class->duration }} min</td>
                <td>{{ $class->student->full_name ?? 'N/A' }}</td>
                <td>{{ $class->teacher->user->name ?? 'N/A' }}</td>
                <td>{{ $class->course->name ?? 'N/A' }}</td>
                <td class="text-center">
                    <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        @php
            $totalDuration = $classes->sum('duration');
            $totalHours = $totalDuration / 60;
            $attendedCount = $classes->where('status', 'attended')->count();
            $pendingCount = $classes->where('status', 'pending')->count();
            $cancelledCount = $classes->whereIn('status', ['cancelled_by_student', 'cancelled_by_teacher'])->count();
        @endphp
        <div class="summary-row">
            <span>Total Classes:</span>
            <span><strong>{{ $classes->count() }}</strong></span>
        </div>
        <div class="summary-row">
            <span>Total Hours:</span>
            <span><strong>{{ number_format($totalHours, 2) }} hours</strong></span>
        </div>
        <div class="summary-row">
            <span>Attended:</span>
            <span><strong>{{ $attendedCount }}</strong></span>
        </div>
        <div class="summary-row">
            <span>Pending:</span>
            <span><strong>{{ $pendingCount }}</strong></span>
        </div>
        <div class="summary-row">
            <span>Cancelled:</span>
            <span><strong>{{ $cancelledCount }}</strong></span>
        </div>
    </div>
    @else
    <div style="text-align: center; padding: 40px; color: #666;">
        <p style="font-size: 14px;">No classes found for the selected date range.</p>
    </div>
    @endif

    <div class="footer">
        <p><strong>ElmCorner Academy</strong></p>
        <p>This is an automated schedule report generated by ElmCorner Management System</p>
    </div>
</body>
</html>
