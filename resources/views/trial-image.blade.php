<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الحصة التجريبية</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
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
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.4) 0%, rgba(118, 75, 162, 0.4) 50%, rgba(240, 147, 251, 0.4) 100%);
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

        /* Decorative Shapes */
        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            z-index: 1.8;
        }

        .shape-1 {
            width: 400px;
            height: 400px;
            top: -150px;
            right: -100px;
        }

        .shape-2 {
            width: 300px;
            height: 300px;
            bottom: -100px;
            left: -50px;
        }

        .shape-3 {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .shape-4 {
            width: 150px;
            height: 150px;
            top: 200px;
            left: 100px;
        }

        .shape-5 {
            width: 120px;
            height: 120px;
            bottom: 200px;
            right: 150px;
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
            font-size: 42px;
            font-weight: 900;
            color: white;
            background: rgba(16, 185, 129, 0.9);
            padding: 20px 40px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            line-height: 1.4;
        }

        .celebrate-icon {
            font-size: 48px;
            display: inline-block;
            margin-left: 15px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .header-subtitle {
            font-size: 22px;
            color: white;
            background: rgba(99, 102, 241, 0.85);
            padding: 12px 30px;
            border-radius: 15px;
            display: inline-block;
            font-weight: 600;
            margin-top: 10px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        /* Time Sections */
        .time-sections {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .time-section {
            background: rgba(30, 41, 59, 0.85);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .time-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .time-label {
            font-size: 16px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
        }

        .time-value {
            font-size: 24px;
            font-weight: 800;
            color: white;
        }

        /* Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .info-card {
            background: rgba(30, 41, 59, 0.85);
            border-radius: 24px;
            padding: 35px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .info-card.full-width {
            grid-column: 1 / -1;
        }

        .card-label {
            font-size: 18px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 12px;
        }

        .card-value {
            font-size: 28px;
            font-weight: 700;
            color: white;
            line-height: 1.3;
        }

        .card-value-small {
            font-size: 20px;
            font-weight: 600;
            color: white;
        }


        /* Notes Section */
        .notes-card {
            background: rgba(30, 41, 59, 0.85);
            border-radius: 24px;
            padding: 35px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .notes-label {
            font-size: 18px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 15px;
        }

        .notes-text {
            font-size: 20px;
            color: white;
            line-height: 1.8;
            font-weight: 500;
            text-align: center;
        }

        .notes-motivational {
            font-size: 22px;
            color: white;
            line-height: 1.8;
            font-weight: 700;
            text-align: center;
            margin-bottom: 15px;
        }

        .notes-dua {
            font-size: 19px;
            color: rgba(255, 255, 255, 0.95);
            line-height: 1.8;
            font-weight: 600;
            text-align: center;
            font-style: italic;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Footer */
        .footer {
            margin-top: auto;
            text-align: center;
            padding-top: 30px;
        }

        .footer-text {
            font-size: 24px;
            color: white;
            font-weight: 800;
            background: rgba(99, 102, 241, 0.9);
            padding: 15px 40px;
            border-radius: 15px;
            display: inline-block;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="overlay"></div>
    <div class="blur-layer"></div>
    
    <!-- Logo -->
    @if(isset($logoImage) && $logoImage)
    <img src="{{ $logoImage }}" alt="Logo" class="logo" />
    @endif
    
    <!-- Decorative Shapes -->
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
    <div class="shape shape-4"></div>
    <div class="shape shape-5"></div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="header-title">
                تم حجز حصتك التجريبية بنجاح
                <span class="celebrate-icon">🎉</span>
            </h1>
            <p class="header-subtitle">رحلتك التعليمية تبدأ من هنا</p>
        </div>

        <!-- Student and Teacher -->
        <div class="info-grid" style="margin-bottom: 20px;">
            <div class="info-card">
                <div class="card-label">الطالب</div>
                <div class="card-value">{{ $trial->student->full_name ?? 'N/A' }}</div>
            </div>

            <div class="info-card">
                <div class="card-label">المعلم</div>
                <div class="card-value">{{ $trial->teacher->user->name ?? 'N/A' }}</div>
            </div>
        </div>

        <!-- Time Sections -->
        <div class="time-sections">
            <div class="time-section">
                <div class="time-icon">📅</div>
                <div class="time-label">اليوم والتاريخ</div>
                <div class="time-value">
                    @php
                        $date = $trial->trial_date ?? null;
                        if ($date) {
                            if (is_string($date)) {
                                $carbonDate = \Carbon\Carbon::parse($date);
                                $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                                $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                                echo $days[$carbonDate->dayOfWeek] . '<br>' . $carbonDate->format('d') . ' ' . $months[$carbonDate->month - 1] . ' ' . $carbonDate->format('Y');
                            } else {
                                $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                                $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                                echo $days[$date->dayOfWeek] . '<br>' . $date->format('d') . ' ' . $months[$date->month - 1] . ' ' . $date->format('Y');
                            }
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>

            <div class="time-section">
                <div class="time-icon">⏰</div>
                <div class="time-label">وقت البداية</div>
                <div class="time-value">
                    @php
                        if (isset($trial->start_time)) {
                            $time = substr($trial->start_time, 0, 5);
                            $parts = explode(':', $time);
                            $hour = (int)$parts[0];
                            $minute = $parts[1];
                            $ampm = $hour >= 12 ? 'م' : 'ص';
                            $hour12 = $hour > 12 ? $hour - 12 : ($hour == 0 ? 12 : $hour);
                            echo $hour12 . ':' . $minute . ' ' . $ampm;
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>

            <div class="time-section">
                <div class="time-icon">⏱️</div>
                <div class="time-label">وقت النهاية</div>
                <div class="time-value">
                    @php
                        if (isset($trial->end_time)) {
                            $time = substr($trial->end_time, 0, 5);
                            $parts = explode(':', $time);
                            $hour = (int)$parts[0];
                            $minute = $parts[1];
                            $ampm = $hour >= 12 ? 'م' : 'ص';
                            $hour12 = $hour > 12 ? $hour - 12 : ($hour == 0 ? 12 : $hour);
                            echo $hour12 . ':' . $minute . ' ' . $ampm;
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="notes-card" style="margin-bottom: 20px;">
            <div class="notes-label">ملاحظات مهمة</div>
            <div class="notes-motivational">
                كونوا على الموعد، سنكون في انتظاركم! 🎯
            </div>
            @if(isset($trial->notes) && !empty($trial->notes))
            <div class="notes-text">{{ $trial->notes }}</div>
            @endif
            <div class="notes-dua">
                🌟 نسأل الله أن يوفقكم ويفتح عليكم أبواب العلم والمعرفة<br>
                💫 بالتوفيق والنجاح في رحلتكم التعليمية
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">ElmCorner Academy</p>
        </div>
    </div>
</body>
</html>
