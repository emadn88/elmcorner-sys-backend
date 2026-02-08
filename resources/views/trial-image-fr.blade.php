<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Cours d'Essai</title>
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
            left: 40px;
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
            left: -100px;
        }

        .shape-2 {
            width: 300px;
            height: 300px;
            bottom: -100px;
            right: -50px;
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
            right: 100px;
        }

        .shape-5 {
            width: 120px;
            height: 120px;
            bottom: 200px;
            left: 150px;
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
            font-size: 56px;
            font-weight: 900;
            color: white;
            background: rgba(16, 185, 129, 0.9);
            padding: 25px 50px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            line-height: 1.4;
        }

        .celebrate-icon {
            font-size: 64px;
            display: inline-block;
            margin-left: 15px;
        }

        .header-subtitle {
            font-size: 30px;
            color: white;
            background: rgba(99, 102, 241, 0.85);
            padding: 15px 40px;
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
        
        /* Reduce height for date section */
        .time-section:first-child {
            padding: 20px 25px;
        }

        .time-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 12px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Calendar icon - pure CSS */
        .icon-calendar {
            width: 50px;
            height: 50px;
            border: 3px solid white;
            border-radius: 8px;
            position: relative;
            background: rgba(255, 255, 255, 0.1);
        }
        .icon-calendar::before {
            content: "";
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 8px;
            background: white;
            border-radius: 4px 4px 0 0;
        }
        .icon-calendar::after {
            content: "";
            position: absolute;
            top: 8px;
            left: 6px;
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 2px;
            box-shadow: 12px 0 0 white, 24px 0 0 white, 0 12px 0 white, 12px 12px 0 white, 24px 12px 0 white;
        }
        
        /* Clock icon - pure CSS */
        .icon-clock {
            width: 50px;
            height: 50px;
            border: 3px solid white;
            border-radius: 50%;
            position: relative;
            background: rgba(255, 255, 255, 0.1);
        }
        .icon-clock::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 3px;
            height: 15px;
            background: white;
            transform-origin: bottom center;
            transform: translate(-50%, -100%) rotate(45deg);
        }
        .icon-clock::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 12px;
            height: 3px;
            background: white;
            transform-origin: center;
            transform: translate(-50%, -50%) rotate(45deg);
        }
        
        /* Stopwatch icon - pure CSS */
        .icon-stopwatch {
            width: 50px;
            height: 50px;
            border: 3px solid white;
            border-radius: 50%;
            position: relative;
            background: rgba(255, 255, 255, 0.1);
        }
        .icon-stopwatch::before {
            content: "";
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 12px;
            background: white;
            border-radius: 2px;
        }
        .icon-stopwatch::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 2px;
            height: 12px;
            background: white;
            transform-origin: bottom center;
            transform: translate(-50%, -50%) rotate(30deg);
        }

        .time-label {
            font-size: 26px;
            font-weight: 900;
            color: white;
            background: rgba(99, 102, 241, 0.8);
            padding: 8px 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .time-value {
            font-size: 29px;
            font-weight: 900;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            line-height: 1.2;
        }
        
        /* Date-specific styling - smaller font and reduced height */
        .time-section:first-child .time-value {
            font-size: 22px;
            line-height: 1.1;
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
            font-size: 28px;
            font-weight: 900;
            color: white;
            background: rgba(16, 185, 129, 0.8);
            padding: 10px 20px;
            border-radius: 12px;
            margin-bottom: 18px;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .card-value {
            font-size: 38px;
            font-weight: 700;
            color: white;
            line-height: 1.3;
        }

        .card-value-small {
            font-size: 28px;
            font-weight: 600;
            color: white;
        }


        /* Notes Section */
        .notes-card {
            background: rgba(30, 41, 59, 0.85);
            border-radius: 24px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin-bottom: 12px;
        }

        .notes-label {
            font-size: 28px;
            font-weight: 900;
            color: white;
            background: rgba(99, 102, 241, 0.8);
            padding: 8px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .notes-motivational {
            font-size: 30px;
            color: white;
            line-height: 1.4;
            font-weight: 700;
            text-align: center;
            margin-bottom: 14px;
        }

        .notes-dua {
            font-size: 26px;
            color: rgba(255, 255, 255, 0.95);
            line-height: 1.4;
            font-weight: 600;
            text-align: center;
            font-style: italic;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Footer */
        .footer {
            margin-top: auto;
            text-align: center;
            padding-top: 20px;
            padding-bottom: 20px;
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
    
    <!-- Logo - Removed -->
    
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
                Votre Cours d'Essai a été Réservé avec Succès
            </h1>
            <p class="header-subtitle">Votre Parcours d'Apprentissage Commence Ici</p>
        </div>

        <!-- Student and Teacher -->
        <div class="info-grid" style="margin-bottom: 20px;">
            <div class="info-card">
                <div class="card-label">Étudiant</div>
                <div class="card-value">{{ $trial->student->full_name ?? 'N/A' }}</div>
            </div>

            <div class="info-card">
                <div class="card-label">Enseignant</div>
                <div class="card-value">{{ $trial->teacher->user->name ?? 'N/A' }}</div>
            </div>
        </div>

        <!-- Time Sections -->
        <div class="time-sections">
            <div class="time-section">
                <div class="time-icon icon-calendar"></div>
                <div class="time-label">Jour & Date</div>
                <div class="time-value">
                    @php
                        // Use student date if available, fallback to trial_date (teacher time)
                        $date = $trial->student_date ?? $trial->trial_date ?? null;
                        if ($date) {
                            if (is_string($date)) {
                                $carbonDate = \Carbon\Carbon::parse($date);
                                echo $carbonDate->format('d/n/Y');
                            } else {
                                echo $date->format('d/n/Y');
                            }
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>

            <div class="time-section">
                <div class="time-icon icon-clock"></div>
                <div class="time-label">Heure de Début</div>
                <div class="time-value">
                    @php
                        // Use student start time if available, fallback to start_time (teacher time)
                        $startTime = $trial->student_start_time ?? $trial->start_time ?? null;
                        if ($startTime) {
                            $time = substr($startTime, 0, 5);
                            $parts = explode(':', $time);
                            $hour = (int)$parts[0];
                            $minute = $parts[1];
                            echo $hour . 'h' . $minute;
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>

            <div class="time-section">
                <div class="time-icon icon-stopwatch"></div>
                <div class="time-label">Heure de Fin</div>
                <div class="time-value">
                    @php
                        // Use student end time if available, fallback to end_time (teacher time)
                        $endTime = $trial->student_end_time ?? $trial->end_time ?? null;
                        if ($endTime) {
                            $time = substr($endTime, 0, 5);
                            $parts = explode(':', $time);
                            $hour = (int)$parts[0];
                            $minute = $parts[1];
                            echo $hour . 'h' . $minute;
                        } else {
                            echo 'N/A';
                        }
                    @endphp
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="notes-card">
            <div class="notes-label">Notes Importantes</div>
            <div class="notes-motivational">
                Soyez à l'heure, nous vous attendons !
            </div>
            <div class="notes-dua">
                Nous vous souhaitons succès et ouverture des portes de la connaissance<br>
                Bonne chance et succès dans votre parcours éducatif
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">ElmCorner Academy</p>
        </div>
    </div>
</body>
</html>
