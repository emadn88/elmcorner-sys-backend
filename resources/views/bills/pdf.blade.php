<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #{{ $bill->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            direction: ltr;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3b82f6;
        }
        .header h1 {
            color: #3b82f6;
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .bill-info {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }
        .bill-info-row {
            display: table-row;
        }
        .bill-info-cell {
            display: table-cell;
            padding: 8px 15px;
            vertical-align: top;
        }
        .bill-info-label {
            font-weight: bold;
            color: #555;
            width: 150px;
        }
        .bill-info-value {
            color: #333;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background-color: #f3f4f6;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 16px;
            color: #1f2937;
            margin-bottom: 15px;
            border-left: 4px solid #3b82f6;
        }
        .student-info {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .student-info h3 {
            color: #3b82f6;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .student-info p {
            margin: 5px 0;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table thead {
            background-color: #3b82f6;
            color: white;
        }
        table th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
            font-size: 13px;
        }
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        table tbody tr:hover {
            background-color: #f9fafb;
        }
        table tbody tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary {
            background-color: #f9fafb;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 16px;
            color: #3b82f6;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #3b82f6;
        }
        .summary-label {
            font-weight: 600;
            color: #555;
        }
        .summary-value {
            font-weight: 600;
            color: #333;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #666;
            font-size: 11px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
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
        .custom-bill-note {
            background-color: #fef3c7;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
        }
        .custom-bill-note p {
            margin: 0;
            color: #92400e;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE / BILL</h1>
        <p>Bill #{{ $bill->id }} | Generated on {{ now()->format('F d, Y') }}</p>
    </div>

    <div class="bill-info">
        <div class="bill-info-row">
            <div class="bill-info-cell bill-info-label">Bill Date:</div>
            <div class="bill-info-cell bill-info-value">{{ $bill->bill_date->format('F d, Y') }}</div>
        </div>
        <div class="bill-info-row">
            <div class="bill-info-cell bill-info-label">Status:</div>
            <div class="bill-info-cell bill-info-value">
                <span class="status-badge status-{{ $bill->status }}">{{ strtoupper($bill->status) }}</span>
            </div>
        </div>
        @if($bill->payment_date)
        <div class="bill-info-row">
            <div class="bill-info-cell bill-info-label">Payment Date:</div>
            <div class="bill-info-cell bill-info-value">{{ $bill->payment_date->format('F d, Y') }}</div>
        </div>
        @endif
        @if($bill->payment_method)
        <div class="bill-info-row">
            <div class="bill-info-cell bill-info-label">Payment Method:</div>
            <div class="bill-info-cell bill-info-value">{{ $bill->payment_method }}</div>
        </div>
        @endif
    </div>

    <div class="student-info">
        <h3>Student Information</h3>
        <p><strong>Name:</strong> {{ $bill->student->full_name }}</p>
        @if($bill->student->email)
        <p><strong>Email:</strong> {{ $bill->student->email }}</p>
        @endif
        @if($bill->student->whatsapp)
        <p><strong>WhatsApp:</strong> {{ $bill->student->whatsapp }}</p>
        @endif
        @if($bill->student->family)
        <p><strong>Family:</strong> {{ $bill->student->family->name }}</p>
        @endif
    </div>

    @if($bill->is_custom && $bill->description)
    <div class="custom-bill-note">
        <p><strong>Note:</strong> {{ $bill->description }}</p>
    </div>
    @endif

    @if($classes && $classes->count() > 0)
    <div class="section">
        <div class="section-title">Lesson Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Duration</th>
                    <th>Teacher</th>
                    <th>Course</th>
                    <th class="text-right">Cost</th>
                </tr>
            </thead>
            <tbody>
                @foreach($classes as $class)
                <tr>
                    <td>{{ $class->class_date->format('M d, Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($class->start_time)->format('h:i A') }}</td>
                    <td>{{ $class->duration }} min ({{ number_format($class->duration / 60, 2) }} hrs)</td>
                    <td>{{ $class->teacher->user->name ?? 'N/A' }}</td>
                    <td>{{ $class->course->name ?? 'N/A' }}</td>
                    <td class="text-right">
                        @php
                            $hours = $class->duration / 60;
                            $cost = $hours * ($class->teacher->hourly_rate ?? 0);
                        @endphp
                        {{ number_format($cost, 2) }} {{ $bill->currency }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="summary">
        <div class="summary-row">
            <span class="summary-label">Total Hours:</span>
            <span class="summary-value">{{ number_format($bill->total_hours ?? ($bill->duration / 60), 2) }} hours</span>
        </div>
        @if($bill->is_custom)
        <div class="summary-row">
            <span class="summary-label">Bill Type:</span>
            <span class="summary-value">Custom Bill</span>
        </div>
        @endif
        <div class="summary-row">
            <span class="summary-label">Total Amount:</span>
            <span class="summary-value">{{ number_format($bill->amount, 2) }} {{ $bill->currency }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>This is an automated invoice generated by ElmCorner Management System</p>
    </div>
</body>
</html>
