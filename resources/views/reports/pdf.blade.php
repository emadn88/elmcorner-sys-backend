<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report #{{ $report->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Cairo', 'Arial', sans-serif;
            direction: rtl;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3b82f6;
        }
        .header h1 {
            color: #3b82f6;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
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
            border-right: 4px solid #3b82f6;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        .info-item {
            padding: 10px;
            background-color: #f9fafb;
            border-radius: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #6b7280;
            margin-bottom: 5px;
            font-size: 11px;
        }
        .info-value {
            color: #1f2937;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th, table td {
            padding: 10px;
            text-align: right;
            border: 1px solid #e5e7eb;
        }
        table th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #1f2937;
        }
        table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 10px;
        }
        .stat-box {
            display: inline-block;
            padding: 15px 20px;
            margin: 5px;
            background-color: #eff6ff;
            border-radius: 8px;
            text-align: center;
            min-width: 120px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير #{{ $report->id }}</h1>
        <p>نوع التقرير: {{ $report->report_type }}</p>
        <p>تاريخ الإنشاء: {{ $report->created_at->format('Y-m-d H:i') }}</p>
    </div>

    @if($report->student)
    <div class="section">
        <div class="section-title">معلومات الطالب</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">الاسم</div>
                <div class="info-value">{{ $report->student->full_name }}</div>
            </div>
            @if($report->student->email)
            <div class="info-item">
                <div class="info-label">البريد الإلكتروني</div>
                <div class="info-value">{{ $report->student->email }}</div>
            </div>
            @endif
            @if($report->student->whatsapp)
            <div class="info-item">
                <div class="info-label">واتساب</div>
                <div class="info-value">{{ $report->student->whatsapp }}</div>
            </div>
            @endif
            <div class="info-item">
                <div class="info-label">الحالة</div>
                <div class="info-value">{{ $report->student->status }}</div>
            </div>
        </div>
    </div>
    @endif

    @if($report->teacher)
    <div class="section">
        <div class="section-title">معلومات المعلم</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">الاسم</div>
                <div class="info-value">{{ $report->teacher->user->name ?? 'N/A' }}</div>
            </div>
        </div>
    </div>
    @endif

    @if(isset($content['statistics']))
    <div class="section">
        <div class="section-title">الإحصائيات</div>
        <div style="text-align: center; margin-bottom: 20px;">
            @foreach($content['statistics'] as $key => $value)
            <div class="stat-box">
                <div class="stat-value">{{ $value }}</div>
                <div class="stat-label">{{ $key }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if(isset($content['date_range']))
    <div class="section">
        <div class="section-title">الفترة الزمنية</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">من</div>
                <div class="info-value">{{ $content['date_range']['from'] }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">إلى</div>
                <div class="info-value">{{ $content['date_range']['to'] }}</div>
            </div>
        </div>
    </div>
    @endif

    <div class="footer">
        <p>تم إنشاء هذا التقرير تلقائياً من نظام إدارة الأكاديمية</p>
        <p>تاريخ الإنشاء: {{ $report->created_at->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
