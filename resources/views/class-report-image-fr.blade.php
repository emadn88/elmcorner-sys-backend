<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport de Classe</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Poppins:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            width: 1080px;
            height: 1350px;
            font-family: 'Inter', 'Poppins', 'Segoe UI', Arial, sans-serif;
            overflow: hidden;
            position: relative;
            direction: ltr;
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

        /* Info Grid */
        .info-grid-4 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
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

        .card-value-small {
            font-size: 28px;
            font-weight: 900;
            color: white;
            line-height: 1.3;
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
                Rapport de Classe
                @php
                    $status = $class->status ?? 'pending';
                    $statusText = [
                        'attended' => 'Présent',
                        'pending' => 'En attente',
                        'cancelled_by_student' => 'Annulé par l\'étudiant',
                        'cancelled_by_teacher' => 'Annulé par le professeur',
                        'absent_student' => 'Absent',
                    ];
                    $statusDisplay = $statusText[$status] ?? $status;
                    $statusColor = $status === 'attended' ? '#ffffff' : ($status === 'pending' ? '#fff700' : '#ff6b6b');
                @endphp
                <span style="color: {{ $statusColor }}; font-size: 0.85em; font-weight: 800; margin-left: 15px; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">({{ $statusDisplay }})</span>
            </h1>
        </div>

        <!-- Student, Teacher, Date, and Time in one row -->
        <div class="info-grid-4">
            <div class="info-card-small">
                <div class="card-label">Étudiant</div>
                <div class="card-value-small">{{ $class->student->full_name ?? 'N/A' }}</div>
            </div>

            <div class="info-card-small">
                <div class="card-label">Professeur</div>
                <div class="card-value-small">{{ $class->teacher->user->name ?? 'N/A' }}</div>
            </div>

            <div class="info-card-small">
                <div class="card-label">Date</div>
                <div class="card-value-small">
                    @php
                        $date = $class->class_date ?? null;
                        if ($date) {
                            if (is_string($date)) {
                                $carbonDate = \Carbon\Carbon::parse($date);
                            } else {
                                $carbonDate = $date;
                            }
                            $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                            $months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                            echo $days[$carbonDate->dayOfWeek] . ', ' . $carbonDate->format('d') . ' ' . $months[$carbonDate->month - 1] . ' ' . $carbonDate->format('Y');
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>

            <div class="info-card-small">
                <div class="card-label">Heure</div>
                <div class="card-value-small">
                    @php
                        $startTime = $class->start_time ?? null;
                        $endTime = $class->end_time ?? null;
                        if ($startTime && $endTime) {
                            // Convert to 12-hour format with AM/PM
                            $startCarbon = is_string($startTime) ? \Carbon\Carbon::parse($startTime) : $startTime;
                            $endCarbon = is_string($endTime) ? \Carbon\Carbon::parse($endTime) : $endTime;
                            
                            $startHour = $startCarbon->format('g'); // 12-hour format without leading zero
                            $startMin = $startCarbon->format('i');
                            $startAmPm = $startCarbon->format('A');
                            
                            $endHour = $endCarbon->format('g');
                            $endMin = $endCarbon->format('i');
                            $endAmPm = $endCarbon->format('A');
                            
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
                Rapport de Classe
                @if(isset($class->student_evaluation) && !empty($class->student_evaluation))
                @php
                    $evaluation = $class->student_evaluation;
                    // Determine color based on evaluation text - brighter colors for visibility
                    $evalColor = '#a78bfa'; // Default purple - brighter
                    if (stripos($evaluation, 'excellent') !== false || stripos($evaluation, 'ممتاز') !== false) {
                        $evalColor = '#34d399'; // Bright green for excellent
                    } elseif (stripos($evaluation, 'very good') !== false || stripos($evaluation, 'très bien') !== false || stripos($evaluation, 'جيد جداً') !== false) {
                        $evalColor = '#60a5fa'; // Bright blue for very good
                    } elseif (stripos($evaluation, 'good') !== false || stripos($evaluation, 'bien') !== false || stripos($evaluation, 'جيد') !== false) {
                        $evalColor = '#fbbf24'; // Bright yellow for good
                    } elseif (stripos($evaluation, 'fair') !== false || stripos($evaluation, 'moyen') !== false || stripos($evaluation, 'مقبول') !== false) {
                        $evalColor = '#f59e0b'; // Bright orange for fair
                    } elseif (stripos($evaluation, 'poor') !== false || stripos($evaluation, 'faible') !== false || stripos($evaluation, 'ضعيف') !== false) {
                        $evalColor = '#f87171'; // Bright red for poor
                    }
                @endphp
                <span style="color: {{ $evalColor }}; font-size: 1em; font-weight: 900; margin-left: 15px; text-shadow: 0 2px 6px rgba(0,0,0,0.5); background: rgba(0,0,0,0.3); padding: 4px 12px; border-radius: 8px;">({{ $evaluation }})</span>
                @endif
            </div>
            <div class="report-content">{{ $class->class_report }}</div>
        </div>
        @endif

        <!-- Notes -->
        @if(isset($class->notes) && !empty($class->notes))
        <div class="report-section">
            <div class="section-title">Notes</div>
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
