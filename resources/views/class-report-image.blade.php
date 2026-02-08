<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير الحصة</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            width: 1080px;
            height: 1350px;
            font-family: 'Cairo', 'Tajawal', 'Segoe UI', Arial, sans-serif;
            overflow: hidden;
            position: relative;
            direction: rtl;
        }

        /* Background Image */
        .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            @if(isset($backgroundImage) && $backgroundImage)
            background-image: url('{{ $backgroundImage }}');
            @else
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #60a5fa 100%);
            @endif
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: 0;
        }

        /* Transparent Overlay Layer */
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.25);
            z-index: 1;
        }

        /* Blurred Colored Layer */
        .blur-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.4) 0%, rgba(59, 130, 246, 0.4) 50%, rgba(96, 165, 250, 0.4) 100%);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            z-index: 1.5;
        }

        /* Logo */
        .logo {
            position: absolute;
            top: 40px;
            right: 40px;
            width: 170px;
            height: auto;
            z-index: 3;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
        }

        /* Content Container */
        .container {
            position: relative;
            z-index: 2;
            padding: 60px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header-title {
            font-size: 64px;
            font-weight: 900;
            color: white;
            background: rgba(16, 185, 129, 0.95);
            padding: 30px 60px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            line-height: 1.3;
            letter-spacing: -1px;
        }

        .header-subtitle {
            font-size: 32px;
            color: white;
            background: rgba(99, 102, 241, 0.9);
            padding: 18px 45px;
            border-radius: 15px;
            display: inline-block;
            font-weight: 700;
            margin-top: 10px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .info-grid-4 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-card {
            background: rgba(15, 23, 42, 0.92);
            border-radius: 20px;
            padding: 30px;
            border: 2px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        .info-card-small {
            background: rgba(15, 23, 42, 0.92);
            border-radius: 15px;
            padding: 20px;
            border: 2px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        .card-label {
            font-size: 22px;
            font-weight: 900;
            color: white;
            background: rgba(99, 102, 241, 0.8);
            padding: 8px 16px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .card-value {
            font-size: 36px;
            font-weight: 900;
            color: white;
            line-height: 1.3;
        }

        .card-value-small {
            font-size: 28px;
            font-weight: 900;
            color: white;
            line-height: 1.3;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .status-attended {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .status-pending {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
        }

        .status-cancelled {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Report Section */
        .report-section {
            background: rgba(15, 23, 42, 0.92);
            border-radius: 20px;
            padding: 35px;
            border: 2px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 32px;
            font-weight: 900;
            color: white;
            background: rgba(99, 102, 241, 0.8);
            padding: 12px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .report-content {
            font-size: 28px;
            color: white;
            line-height: 1.8;
            font-weight: 600;
            text-align: justify;
        }


        /* Footer */
        .footer {
            margin-top: auto;
            text-align: center;
            padding-top: 25px;
        }

        .footer-text {
            font-size: 36px;
            color: white;
            font-weight: 900;
            background: rgba(99, 102, 241, 0.95);
            padding: 20px 60px;
            border-radius: 15px;
            display: inline-block;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            letter-spacing: 1px;
        }

        /* Date Time Section */
        .datetime-section {
            background: rgba(15, 23, 42, 0.92);
            border-radius: 20px;
            padding: 30px;
            border: 2px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            margin-bottom: 25px;
            text-align: center;
        }

        .datetime-label {
            font-size: 16px;
            font-weight: 700;
            color: rgba(147, 197, 253, 0.9);
            margin-bottom: 10px;
        }

        .datetime-value {
            font-size: 28px;
            font-weight: 800;
            color: white;
        }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="overlay"></div>
    <div class="blur-layer"></div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="header-title">
                تقرير الحصة الدراسية
                @php
                    $status = $class->status ?? 'pending';
                    $statusText = [
                        'attended' => 'حضر',
                        'pending' => 'قيد الانتظار',
                        'cancelled_by_student' => 'ملغي من الطالب',
                        'cancelled_by_teacher' => 'ملغي من المعلم',
                        'absent_student' => 'غائب',
                    ];
                    $statusDisplay = $statusText[$status] ?? $status;
                    $statusColor = $status === 'attended' ? '#ffffff' : ($status === 'pending' ? '#fff700' : '#ff6b6b');
                @endphp
                <span style="color: {{ $statusColor }}; font-size: 0.85em; font-weight: 800; margin-right: 15px; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">({{ $statusDisplay }})</span>
            </h1>
        </div>

        <!-- Student, Teacher, Date, and Time in one row -->
        <div class="info-grid-4">
            <div class="info-card-small">
                <div class="card-label">الطالب</div>
                <div class="card-value-small">{{ $class->student->full_name ?? 'N/A' }}</div>
            </div>

            <div class="info-card-small">
                <div class="card-label">المعلم</div>
                <div class="card-value-small">{{ $class->teacher->user->name ?? 'N/A' }}</div>
            </div>

            <div class="info-card-small">
                <div class="card-label">التاريخ</div>
                <div class="card-value-small">
                    @php
                        $date = $class->class_date ?? null;
                        if ($date) {
                            if (is_string($date)) {
                                $carbonDate = \Carbon\Carbon::parse($date);
                            } else {
                                $carbonDate = $date;
                            }
                            $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                            $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                            echo $carbonDate->format('d') . ' ' . $months[$carbonDate->month - 1] . ' ' . $carbonDate->format('Y');
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>

            <div class="info-card-small">
                <div class="card-label">الوقت</div>
                <div class="card-value-small">
                    @php
                        $startTime = $class->start_time ?? null;
                        $endTime = $class->end_time ?? null;
                        if ($startTime && $endTime) {
                            // Convert to 12-hour format with Arabic AM/PM
                            $startCarbon = is_string($startTime) ? \Carbon\Carbon::parse($startTime) : $startTime;
                            $endCarbon = is_string($endTime) ? \Carbon\Carbon::parse($endTime) : $endTime;
                            
                            $startHour = $startCarbon->format('g'); // 12-hour format without leading zero
                            $startMin = $startCarbon->format('i');
                            $startAmPm = $startCarbon->format('A') === 'AM' ? 'ص' : 'م';
                            
                            $endHour = $endCarbon->format('g');
                            $endMin = $endCarbon->format('i');
                            $endAmPm = $endCarbon->format('A') === 'AM' ? 'ص' : 'م';
                            
                            echo $startHour . ':' . $startMin . ' ' . $startAmPm . ' - ' . $endHour . ':' . $endMin . ' ' . $endAmPm;
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>
        </div>

        <!-- Class Report -->
        @if(isset($class->class_report) && !empty($class->class_report))
        <div class="report-section">
            <div class="section-title">
                تقرير الحصة
                @if(isset($class->student_evaluation) && !empty($class->student_evaluation))
                @php
                    $evaluation = $class->student_evaluation;
                    // Determine color based on evaluation text - brighter colors for visibility
                    $evalColor = '#a78bfa'; // Default purple - brighter
                    if (stripos($evaluation, 'excellent') !== false || stripos($evaluation, 'ممتاز') !== false) {
                        $evalColor = '#34d399'; // Bright green for excellent
                    } elseif (stripos($evaluation, 'very good') !== false || stripos($evaluation, 'جيد جداً') !== false) {
                        $evalColor = '#60a5fa'; // Bright blue for very good
                    } elseif (stripos($evaluation, 'good') !== false || stripos($evaluation, 'جيد') !== false) {
                        $evalColor = '#fbbf24'; // Bright yellow for good
                    } elseif (stripos($evaluation, 'fair') !== false || stripos($evaluation, 'مقبول') !== false) {
                        $evalColor = '#f59e0b'; // Bright orange for fair
                    } elseif (stripos($evaluation, 'poor') !== false || stripos($evaluation, 'ضعيف') !== false) {
                        $evalColor = '#f87171'; // Bright red for poor
                    }
                @endphp
                <span style="color: {{ $evalColor }}; font-size: 1em; font-weight: 900; margin-right: 15px; text-shadow: 0 2px 6px rgba(0,0,0,0.5); background: rgba(0,0,0,0.3); padding: 4px 12px; border-radius: 8px;">({{ $evaluation }})</span>
                @endif
            </div>
            <div class="report-content">{{ $class->class_report }}</div>
        </div>
        @endif

        <!-- Notes -->
        @if(isset($class->notes) && !empty($class->notes))
        <div class="report-section">
            <div class="section-title">ملاحظات</div>
            <div class="report-content">{{ $class->notes }}</div>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">ElmCorner Academy</p>
        </div>
    </div>
</body>
</html>
