<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Rate Details - {{ $teacher['name'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 14px;
            color: #666;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            background-color: #f9f9f9;
        }
        .stat-card h3 {
            font-size: 11px;
            margin-bottom: 10px;
            color: #666;
            font-weight: normal;
        }
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-card .score {
            font-size: 10px;
            color: #666;
        }
        .stat-card .breakdown {
            font-size: 9px;
            color: #666;
            margin-top: 8px;
            line-height: 1.4;
        }
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .section h2 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }
        table th {
            background-color: #f2f2f2;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        table td {
            border: 1px solid #ddd;
            padding: 6px;
        }
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-on-time, .badge-immediate {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-late {
            background-color: #fff3cd;
            color: #856404;
        }
        .badge-very-late {
            background-color: #f8d7da;
            color: #721c24;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Teacher Rate Details</h1>
        <p><strong>{{ $teacher['name'] }}</strong> - {{ $teacher['email'] }}</p>
        <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Attendance Rate</h3>
            <div class="value">{{ number_format($attendance['rate'], 1) }}%</div>
            <div class="score">Score: {{ number_format($attendance['score'], 1) }}/100</div>
            <div class="breakdown">
                Attended: {{ $attendance['attended'] }}<br>
                Cancelled by Student: {{ $attendance['cancelled_by_student'] }}<br>
                Total: {{ $attendance['total'] }}
            </div>
        </div>

        <div class="stat-card">
            <h3>Punctuality Rate</h3>
            <div class="value">{{ number_format($punctuality['rate'], 1) }}%</div>
            <div class="score">Score: {{ number_format($punctuality['score'], 1) }}/100</div>
            <div class="breakdown">
                On-time: {{ $punctuality['on_time'] }}<br>
                Late: {{ $punctuality['late'] }}<br>
                Very Late: {{ $punctuality['very_late'] }}<br>
                Total Joined: {{ $punctuality['total_joined'] }}
            </div>
        </div>

        <div class="stat-card">
            <h3>Report Submission Rate</h3>
            <div class="value">{{ number_format($report_submission['rate'], 1) }}%</div>
            <div class="score">Score: {{ number_format($report_submission['score'], 1) }}/100</div>
            <div class="breakdown">
                Immediate: {{ $report_submission['immediate'] }}<br>
                Late: {{ $report_submission['late'] }}<br>
                Very Late: {{ $report_submission['very_late'] }}<br>
                Total Reports: {{ $report_submission['total_reports'] }}
            </div>
        </div>

        <div class="stat-card">
            <h3>Total Joined</h3>
            <div class="value">{{ $punctuality['total_joined'] }}</div>
            <div class="score">Classes</div>
        </div>

        <div class="stat-card">
            <h3>Total Submitted</h3>
            <div class="value">{{ $report_submission['total_reports'] }}</div>
            <div class="score">Reports</div>
        </div>
    </div>

    @php
        $punctualityClasses = isset($classes) ? array_filter($classes, function($c) { return isset($c['type']) && $c['type'] === 'punctuality'; }) : [];
        $reportSubmissionClasses = isset($classes) ? array_filter($classes, function($c) { return isset($c['type']) && $c['type'] === 'report_submission'; }) : [];
    @endphp

    @if(count($punctualityClasses) > 0 && (($filters['rate_type'] ?? 'all') === 'all' || ($filters['rate_type'] ?? 'all') === 'punctuality'))
        <div class="section">
            <h2>Punctuality Rate - Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Date</th>
                        <th>Class Start</th>
                        <th>Joined Time</th>
                        <th>Status</th>
                        <th>Minutes Late</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($punctualityClasses as $class)
                    <tr>
                        <td>{{ $class['student_name'] ?? 'N/A' }}</td>
                        <td>{{ $class['course_name'] ?? 'N/A' }}</td>
                        <td>{{ $class['class_date'] ?? 'N/A' }}</td>
                        <td>{{ isset($class['start_time']) ? date('H:i', strtotime($class['start_time'])) : 'N/A' }}</td>
                        <td>{{ isset($class['joined_time']) ? date('Y-m-d H:i', strtotime($class['joined_time'])) : 'N/A' }}</td>
                        <td>
                            <span class="badge badge-{{ $class['status'] ?? 'on_time' }}">
                                @if(($class['status'] ?? '') === 'on_time')
                                    On-time
                                @elseif(($class['status'] ?? '') === 'late')
                                    Late
                                @else
                                    Very Late
                                @endif
                            </span>
                        </td>
                        <td>{{ ($class['minutes_late'] ?? 0) > 0 ? $class['minutes_late'] . ' min' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(count($reportSubmissionClasses) > 0 && (($filters['rate_type'] ?? 'all') === 'all' || ($filters['rate_type'] ?? 'all') === 'report_submission'))
        <div class="section">
            <h2>Report Submission Rate - Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Date</th>
                        <th>Class End</th>
                        <th>Submitted Time</th>
                        <th>Status</th>
                        <th>Minutes After End</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportSubmissionClasses as $class)
                    <tr>
                        <td>{{ $class['student_name'] ?? 'N/A' }}</td>
                        <td>{{ $class['course_name'] ?? 'N/A' }}</td>
                        <td>{{ $class['class_date'] ?? 'N/A' }}</td>
                        <td>{{ isset($class['end_time']) ? date('H:i', strtotime($class['end_time'])) : 'N/A' }}</td>
                        <td>{{ isset($class['submitted_time']) ? date('Y-m-d H:i', strtotime($class['submitted_time'])) : 'N/A' }}</td>
                        <td>
                            <span class="badge badge-{{ $class['status'] ?? 'immediate' }}">
                                @if(($class['status'] ?? '') === 'immediate')
                                    Immediate
                                @elseif(($class['status'] ?? '') === 'late')
                                    Late
                                @else
                                    Very Late
                                @endif
                            </span>
                        </td>
                        <td>{{ ($class['minutes_after_end'] ?? 0) > 0 ? $class['minutes_after_end'] . ' min' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically by the Management System</p>
    </div>
</body>
</html>
