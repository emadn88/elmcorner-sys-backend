<?php

namespace App\Services;

use App\Models\TrialClass;
use App\Models\ClassInstance;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Send reminder for a trial
     */
    public function sendTrialReminder(TrialClass $trial, string $reminderType): bool
    {
        try {
            $trial->load(['student', 'teacher.user', 'course', 'teacher']);
            
            $studentPhone = $trial->student->whatsapp;
            $teacherPhone = $trial->teacher->user->whatsapp ?? null;

            if (!$studentPhone && !$teacherPhone) {
                Log::warning('No WhatsApp numbers found for trial reminder', [
                    'trial_id' => $trial->id,
                    'reminder_type' => $reminderType,
                ]);
                return false;
            }

            $success = true;

            // Send to student with student time
            if ($studentPhone) {
                $studentMessage = $this->getTrialReminderMessage($trial, $reminderType, 'student');
                $sent = $this->whatsappService->sendMessage($studentPhone, $studentMessage);
                if (!$sent) {
                    $success = false;
                }
            }

            // Send to teacher with teacher time and zoom link
            if ($teacherPhone) {
                $teacherMessage = $this->getTrialReminderMessage($trial, $reminderType, 'teacher');
                $sent = $this->whatsappService->sendMessage($teacherPhone, $teacherMessage);
                if (!$sent) {
                    $success = false;
                }
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to send trial reminder', [
                'trial_id' => $trial->id,
                'reminder_type' => $reminderType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send reminder for a trial to student only
     */
    public function sendTrialReminderToStudentOnly(TrialClass $trial, string $reminderType): bool
    {
        try {
            // Ensure all relationships are loaded including teacher with meet_link
            $trial->load(['student', 'teacher.user', 'course', 'teacher']);
            
            $studentPhone = $trial->student->whatsapp;

            if (!$studentPhone) {
                Log::warning('No WhatsApp number found for student trial reminder', [
                    'trial_id' => $trial->id,
                    'reminder_type' => $reminderType,
                ]);
                return false;
            }

            // Send to student only
            $studentMessage = $this->getTrialReminderMessage($trial, $reminderType, 'student');
            $sent = $this->whatsappService->sendMessage($studentPhone, $studentMessage);

            return $sent;
        } catch (\Exception $e) {
            Log::error('Failed to send student-only trial reminder', [
                'trial_id' => $trial->id,
                'reminder_type' => $reminderType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send reminder for a class
     */
    public function sendClassReminder(ClassInstance $class, string $reminderType): bool
    {
        try {
            $class->load(['student', 'teacher.user', 'course', 'teacher']);
            
            $studentPhone = $class->student->whatsapp;
            $teacherPhone = $class->teacher->user->whatsapp ?? null;

            if (!$studentPhone && !$teacherPhone) {
                Log::warning('No WhatsApp numbers found for class reminder', [
                    'class_id' => $class->id,
                    'reminder_type' => $reminderType,
                ]);
                return false;
            }

            $success = true;

            // Send to student with student time
            if ($studentPhone) {
                $studentMessage = $this->getClassReminderMessage($class, $reminderType, 'student');
                $sent = $this->whatsappService->sendMessage($studentPhone, $studentMessage);
                if (!$sent) {
                    $success = false;
                }
            }

            // Send to teacher with teacher time and system link
            if ($teacherPhone) {
                $teacherMessage = $this->getClassReminderMessage($class, $reminderType, 'teacher');
                $sent = $this->whatsappService->sendMessage($teacherPhone, $teacherMessage);
                if (!$sent) {
                    $success = false;
                }
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to send class reminder', [
                'class_id' => $class->id,
                'reminder_type' => $reminderType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send reminder for a class to student only
     */
    public function sendClassReminderToStudentOnly(ClassInstance $class, string $reminderType): bool
    {
        try {
            $class->load(['student', 'teacher.user', 'course', 'teacher']);
            
            $studentPhone = $class->student->whatsapp;

            if (!$studentPhone) {
                Log::warning('No WhatsApp number found for student class reminder', [
                    'class_id' => $class->id,
                    'reminder_type' => $reminderType,
                ]);
                return false;
            }

            // Send to student only
            $studentMessage = $this->getClassReminderMessage($class, $reminderType, 'student');
            $sent = $this->whatsappService->sendMessage($studentPhone, $studentMessage);

            return $sent;
        } catch (\Exception $e) {
            Log::error('Failed to send student-only class reminder', [
                'class_id' => $class->id,
                'reminder_type' => $reminderType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get reminder message for trial
     */
    protected function getTrialReminderMessage(TrialClass $trial, string $reminderType, string $recipient = 'student'): string
    {
        // Ensure teacher relationship is loaded with meet_link
        if (!$trial->relationLoaded('teacher')) {
            $trial->load('teacher');
        }
        
        $courseName = $trial->course->name ?? 'الدورة';
        $teacherName = $trial->teacher->user->name ?? 'المعلم';
        $studentName = $trial->student->full_name ?? 'الطالب';
        $supportPhone = config('whatsapp.support_phone', '+201099471391');
        
        // Get meet_link from teacher, ensure it's not null/empty
        $meetLink = '';
        if ($trial->teacher) {
            // Get meet_link directly from teacher model
            $meetLink = trim($trial->teacher->meet_link ?? '');
            
            // Log for debugging if meet_link is missing
            if (empty($meetLink)) {
                Log::warning('Trial reminder: Teacher meet_link is empty', [
                    'trial_id' => $trial->id,
                    'teacher_id' => $trial->teacher->id,
                    'reminder_type' => $reminderType,
                    'recipient' => $recipient,
                ]);
            }
        }
        
        // Get student language for translation
        $studentLanguage = 'ar'; // Default to Arabic
        if ($recipient === 'student') {
            try {
                $student = \App\Models\Student::find($trial->student_id);
                if ($student && isset($student->language)) {
                    $studentLanguage = strtolower(trim((string)$student->language));
                    if (empty($studentLanguage) || !in_array($studentLanguage, ['ar', 'en', 'fr'])) {
                        $studentLanguage = 'ar';
                    }
                }
            } catch (\Exception $e) {
                // Default to Arabic on error
                $studentLanguage = 'ar';
            }
        }
        
        // Always use teacher time as primary time (for both student and teacher)
        if ($trial->teacher_date && $trial->teacher_start_time) {
            $date = $trial->teacher_date instanceof \Carbon\Carbon 
                ? $trial->teacher_date->format('Y-m-d')
                : $trial->teacher_date;
            $startTime = is_string($trial->teacher_start_time) 
                ? $trial->teacher_start_time 
                : Carbon::parse($trial->teacher_start_time)->format('H:i');
        } else {
            $date = $trial->trial_date->format('Y-m-d');
            $startTime = is_string($trial->start_time) ? $trial->start_time : Carbon::parse($trial->start_time)->format('H:i');
        }
        // Format time to 12-hour format
        $timeParts = explode(':', $startTime);
        $hour = (int)$timeParts[0];
        $minute = $timeParts[1] ?? '00';
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12 ?: 12;
        $time = sprintf('%d:%s %s', $hour12, $minute, $ampm);
        
        // Get student time as additional info (if available and sending to student)
        $studentTime = '';
        if ($recipient === 'student' && $trial->student_date && $trial->student_start_time) {
            $studentStartTime = is_string($trial->student_start_time) 
                ? $trial->student_start_time 
                : Carbon::parse($trial->student_start_time)->format('H:i');
            $studentTimeParts = explode(':', $studentStartTime);
            $studentHour = (int)$studentTimeParts[0];
            $studentMinute = $studentTimeParts[1] ?? '00';
            $studentAmpm = $studentHour >= 12 ? 'PM' : 'AM';
            $studentHour12 = $studentHour % 12 ?: 12;
            $studentTime = sprintf('%d:%s %s', $studentHour12, $studentMinute, $studentAmpm);
        }

        // Get status message based on recipient and reminder type
        $statusMessage = $this->getReminderStatusMessage($reminderType, $recipient, $studentName, $studentLanguage);
        
        $academyName = config('app.name', 'Elm Corner Academy');

        // Generate message based on language
        if ($studentLanguage === 'en' && $recipient === 'student') {
            $message = "🎓 *ELM CORNER ACADEMY*\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "👨‍🏫 *Teacher:* {$teacherName}\n";
            $message .= "📚 *Course:* {$courseName}\n";
            $message .= "🕐 *Time:* {$time}";
            if (!empty($studentTime)) {
                $message .= " (Your time: {$studentTime})";
            }
            $message .= "\n";
            
            // Always include Zoom link for both student and teacher if available
            if (!empty($meetLink)) {
                $message .= "\n🔗 *Zoom Link:*\n{$meetLink}";
            }
            
            $message .= "\n\n💬 *WhatsApp Support:* {$supportPhone}";
            
            return $message;
        } elseif ($studentLanguage === 'fr' && $recipient === 'student') {
            $message = "🎓 *ELM CORNER ACADEMY*\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "👨‍🏫 *Professeur:* {$teacherName}\n";
            $message .= "📚 *Cours:* {$courseName}\n";
            $message .= "🕐 *Heure:* {$time}";
            if (!empty($studentTime)) {
                $message .= " (Votre heure: {$studentTime})";
            }
            $message .= "\n";
            
            // Always include Zoom link for both student and teacher if available
            if (!empty($meetLink)) {
                $message .= "\n🔗 *Lien Zoom:*\n{$meetLink}";
            }
            
            $message .= "\n\n💬 *Support WhatsApp:* {$supportPhone}";
            
            return $message;
        } else {
            // Arabic (default) or teacher messages
            $message = "🎓 *أكاديمية إلم كورنر*\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "👨‍🏫 *المعلم:* {$teacherName}\n";
            $message .= "📚 *الدورة:* {$courseName}\n";
            $message .= "🕐 *الوقت:* {$time}";
            if (!empty($studentTime) && $recipient === 'student') {
                $message .= " (وقتك: {$studentTime})";
            }
            $message .= "\n";
            
            // Always include Zoom link for both student and teacher if available
            if (!empty($meetLink)) {
                $message .= "\n🔗 *رابط الزوم:*\n{$meetLink}";
            }
            
            $message .= "\n\n💬 *واتساب الدعم:* {$supportPhone}";
            
            return $message;
        }
    }

    /**
     * Get reminder status message based on type and recipient
     */
    protected function getReminderStatusMessage(string $reminderType, string $recipient, string $studentName, string $language = 'ar'): string
    {
        if ($recipient === 'student') {
            // Student messages - translate based on language
            switch ($reminderType) {
                case '2hours_before':
                    if ($language === 'en') {
                        return "Your trial class will start in 2 hours";
                    } elseif ($language === 'fr') {
                        return "Votre cours d'essai commencera dans 2 heures";
                    } else {
                        return "الحصة التجريبية الخاصة بك ستبدأ بعد ساعتين";
                    }
                
                case '5min_before':
                    if ($language === 'en') {
                        return "Your trial class will start soon";
                    } elseif ($language === 'fr') {
                        return "Votre cours d'essai va commencer bientôt";
                    } else {
                        return "الحصة التجريبية الخاصة بك ستبدأ قريبا";
                    }
                
                case 'start_time':
                    if ($language === 'en') {
                        return "Your trial class has started. The teacher is waiting";
                    } elseif ($language === 'fr') {
                        return "Votre cours d'essai a commencé. Le professeur attend";
                    } else {
                        return "الحصة التجريبية الخاصه بك بدأت المعلم في الانتظار";
                    }
                
                case '5min_after':
                    if ($language === 'en') {
                        return "Your trial class started minutes ago. The teacher is waiting";
                    } elseif ($language === 'fr') {
                        return "Votre cours d'essai a commencé il y a quelques minutes. Le professeur attend";
                    } else {
                        return "الحصة التجريبيه بدأت منذ دقائق المعلم في الانتظار";
                    }
                
                default:
                    return "الحصة التجريبية الخاصة بك ستبدأ قريبا";
            }
        } else {
            // Teacher messages - always in Arabic
            switch ($reminderType) {
                case '5min_before':
                    return "الحصة التجريبية للطالب {$studentName} ستبدأ قريبا";
                
                case 'start_time':
                    return "الحصة التجريبية الخاصه بالطالب {$studentName} بدأت";
                
                case '5min_after':
                    return "الحصة التجريبيه الخاصه بالطالب {$studentName} بدأت منذ دقايق";
                
                default:
                    return "الحصة التجريبية للطالب {$studentName} ستبدأ قريبا";
            }
        }
    }

    /**
     * Send trial creation notification
     */
    public function sendTrialCreationNotification(TrialClass $trial): bool
    {
        try {
            $trial->load(['student', 'teacher.user', 'course']);
            
            // Check if relationships are loaded
            if (!$trial->student) {
                Log::error('Trial student not found', ['trial_id' => $trial->id]);
                return false;
            }
            
            if (!$trial->teacher || !$trial->teacher->user) {
                Log::error('Trial teacher or teacher user not found', ['trial_id' => $trial->id]);
                return false;
            }
            
            $studentPhone = $trial->student->whatsapp;
            $teacherPhone = $trial->teacher->user->whatsapp ?? null;

            Log::info('Sending trial creation notification', [
                'trial_id' => $trial->id,
                'student_phone' => $studentPhone ? 'present' : 'missing',
                'teacher_phone' => $teacherPhone ? 'present' : 'missing',
            ]);

            if (!$studentPhone && !$teacherPhone) {
                Log::warning('No WhatsApp numbers found for trial creation notification', [
                    'trial_id' => $trial->id,
                    'student_id' => $trial->student_id,
                    'teacher_id' => $trial->teacher_id,
                ]);
                return false;
            }

            $success = true;

            // Send to student (in their language)
            if ($studentPhone) {
                try {
            // Reload student to ensure we have the latest data including language
            $student = \App\Models\Student::find($trial->student_id);
            
            // Get language from database directly (check if column exists first)
            $studentLanguageRaw = null;
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('students', 'language')) {
                    $studentLanguageRaw = \Illuminate\Support\Facades\DB::table('students')
                        ->where('id', $trial->student_id)
                        ->value('language');
                }
            } catch (\Exception $e) {
                Log::warning('Could not check language column', ['error' => $e->getMessage()]);
            }
            
            $studentLanguage = $studentLanguageRaw ?? $student->language ?? 'ar';
            
            // Normalize language value
            $studentLanguage = strtolower(trim((string)$studentLanguage));
            
            // If language is null or empty, default to Arabic
            if (empty($studentLanguage) || !in_array($studentLanguage, ['ar', 'en', 'fr'])) {
                Log::warning('Invalid language detected, defaulting to Arabic', [
                    'student_id' => $trial->student_id,
                    'language_received' => $studentLanguage,
                ]);
                $studentLanguage = 'ar';
            }
            
            Log::info('Sending student notification', [
                'trial_id' => $trial->id,
                'student_id' => $trial->student_id,
                'phone' => $studentPhone,
                'language' => $studentLanguage,
                'language_raw' => $studentLanguageRaw,
                'student_language_from_model' => $student->language,
                'student_language_from_db' => $studentLanguageRaw,
            ]);
                    
                    $studentMessage = $this->getTrialCreationMessageForStudent($trial, $studentLanguage);
                    $sent = $this->whatsappService->sendMessage($studentPhone, $studentMessage);
                    if (!$sent) {
                        Log::error('Failed to send student notification', [
                            'trial_id' => $trial->id,
                            'phone' => $studentPhone,
                        ]);
                        $success = false;
                    } else {
                        Log::info('Student notification sent successfully', [
                            'trial_id' => $trial->id,
                            'phone' => $studentPhone,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Exception sending student notification', [
                        'trial_id' => $trial->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $success = false;
                }
            }

            // Send to teacher (always in Arabic)
            if ($teacherPhone) {
                try {
                    $teacherMessage = $this->getTrialCreationMessageForTeacher($trial);
                    Log::info('Sending teacher notification', [
                        'trial_id' => $trial->id,
                        'phone' => $teacherPhone,
                    ]);
                    $sent = $this->whatsappService->sendMessage($teacherPhone, $teacherMessage);
                    if (!$sent) {
                        Log::error('Failed to send teacher notification', [
                            'trial_id' => $trial->id,
                            'phone' => $teacherPhone,
                        ]);
                        $success = false;
                    } else {
                        Log::info('Teacher notification sent successfully', [
                            'trial_id' => $trial->id,
                            'phone' => $teacherPhone,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Exception sending teacher notification', [
                        'trial_id' => $trial->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $success = false;
                }
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to send trial creation notification', [
                'trial_id' => $trial->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get trial creation message for student (in their language)
     */
    protected function getTrialCreationMessageForStudent(TrialClass $trial, string $language): string
    {
        $studentName = $trial->student->full_name ?? '';
        $teacherName = $trial->teacher->user->name ?? '';
        $country = $trial->student->country ?? '';
        
        // Use stored student times directly (already in student timezone)
        if ($trial->student_date && $trial->student_start_time && $trial->student_end_time) {
            $date = $trial->student_date instanceof \Carbon\Carbon 
                ? $trial->student_date->format('Y-m-d')
                : $trial->student_date;
            
            // Parse and format times
            $startTime = is_string($trial->student_start_time) 
                ? $trial->student_start_time 
                : Carbon::parse($trial->student_start_time)->format('H:i');
            $endTime = is_string($trial->student_end_time) 
                ? $trial->student_end_time 
                : Carbon::parse($trial->student_end_time)->format('H:i');
            
            // Normalize to H:i format
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $startTime, $matches)) {
                $startTime = sprintf('%02d:%02d:%02d', $matches[1], $matches[2], 0);
            }
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $endTime, $matches)) {
                $endTime = sprintf('%02d:%02d:%02d', $matches[1], $matches[2], 0);
            }
            
            try {
                $startDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$startTime}");
                $endDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$endTime}");
            } catch (\Exception $e) {
                $startDateTime = Carbon::parse("{$date} {$startTime}");
                $endDateTime = Carbon::parse("{$date} {$endTime}");
            }
            
            $time = $startDateTime->format('g:i A');
            $endTimeFormatted = $endDateTime->format('g:i A');
        } else {
            // Fallback: convert from stored Egypt time (backward compatibility)
            $studentTimezone = $trial->student->timezone ?? 'Africa/Cairo';
            $trialDate = $trial->trial_date->format('Y-m-d');
            
            $startTime = is_string($trial->start_time) ? $trial->start_time : Carbon::parse($trial->start_time)->format('H:i');
            $endTime = is_string($trial->end_time) ? $trial->end_time : Carbon::parse($trial->end_time)->format('H:i');
            
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $startTime, $matches)) {
                $startTime = sprintf('%02d:%02d:00', $matches[1], $matches[2]);
            }
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $endTime, $matches)) {
                $endTime = sprintf('%02d:%02d:00', $matches[1], $matches[2]);
            }
            
            try {
                $startDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$trialDate} {$startTime}", 'Africa/Cairo');
                $endDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$trialDate} {$endTime}", 'Africa/Cairo');
            } catch (\Exception $e) {
                $startDateTime = Carbon::parse("{$trialDate} {$startTime}", 'Africa/Cairo');
                $endDateTime = Carbon::parse("{$trialDate} {$endTime}", 'Africa/Cairo');
            }
            
            $startDateTime->setTimezone($studentTimezone);
            $endDateTime->setTimezone($studentTimezone);
            
            $date = $startDateTime->format('Y-m-d');
            $time = $startDateTime->format('g:i A');
            $endTimeFormatted = $endDateTime->format('g:i A');
        }
        
        // Normalize language value (trim and lowercase)
        $language = strtolower(trim($language));
        
        // Log the language being used for debugging
        Log::info('Generating student message', [
            'trial_id' => $trial->id,
            'student_id' => $trial->student_id,
            'language' => $language,
            'language_type' => gettype($language),
            'student_language_field' => $trial->student->language ?? 'not set',
        ]);
        
        // Generate message based on language
        if ($language === 'en') {
            Log::info('Using English message', ['trial_id' => $trial->id]);
            return $this->getEnglishStudentMessage($studentName, $teacherName, $country, $date, $time, $endTimeFormatted);
        } elseif ($language === 'fr') {
            Log::info('Using French message', ['trial_id' => $trial->id]);
            return $this->getFrenchStudentMessage($studentName, $teacherName, $country, $date, $time, $endTimeFormatted);
        } else {
            // Default to Arabic
            Log::info('Using Arabic message (default)', [
                'trial_id' => $trial->id,
                'language_received' => $language,
            ]);
            return $this->getArabicStudentMessage($studentName, $teacherName, $country, $date, $time, $endTimeFormatted);
        }
    }

    /**
     * Get trial creation message for teacher (always Arabic)
     */
    public function getTrialCreationMessageForTeacher(TrialClass $trial): string
    {
        $courseName = $trial->course->name ?? 'الدورة';
        $studentName = $trial->student->full_name ?? 'الطالب';
        $teacherName = $trial->teacher->user->name ?? 'المعلم';
        
        // Use stored teacher times directly (already in teacher timezone)
        if ($trial->teacher_date && $trial->teacher_start_time && $trial->teacher_end_time) {
            $dateObj = $trial->teacher_date instanceof \Carbon\Carbon 
                ? $trial->teacher_date 
                : Carbon::parse($trial->teacher_date);
            
            $dayName = $this->getArabicDayName($dateObj->dayOfWeek);
            $monthName = $this->getArabicMonthName($dateObj->month);
            $date = "{$dayName}، {$dateObj->day} {$monthName} {$dateObj->year}";
            
            // Parse and format times
            $startTimeRaw = is_string($trial->teacher_start_time) 
                ? $trial->teacher_start_time 
                : Carbon::parse($trial->teacher_start_time)->format('H:i');
            $endTimeRaw = is_string($trial->teacher_end_time) 
                ? $trial->teacher_end_time 
                : Carbon::parse($trial->teacher_end_time)->format('H:i');
            
            // Normalize to H:i format
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $startTimeRaw, $matches)) {
                $startTimeRaw = sprintf('%02d:%02d', $matches[1], $matches[2]);
            }
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $endTimeRaw, $matches)) {
                $endTimeRaw = sprintf('%02d:%02d', $matches[1], $matches[2]);
            }
            
            try {
                $startDateTime = Carbon::createFromFormat('H:i', $startTimeRaw);
                $endDateTime = Carbon::createFromFormat('H:i', $endTimeRaw);
            } catch (\Exception $e) {
                $startDateTime = Carbon::parse($startTimeRaw);
                $endDateTime = Carbon::parse($endTimeRaw);
            }
            
            $time = $startDateTime->format('g:i A');
            $endTime = $endDateTime->format('g:i A');
        } else {
            // Fallback: use stored Egypt time (backward compatibility)
            $dateObj = Carbon::parse($trial->trial_date);
            $dayName = $this->getArabicDayName($dateObj->dayOfWeek);
            $monthName = $this->getArabicMonthName($dateObj->month);
            $date = "{$dayName}، {$dateObj->day} {$monthName} {$dateObj->year}";
            
            $startTimeRaw = is_string($trial->start_time) ? $trial->start_time : Carbon::parse($trial->start_time)->format('H:i');
            $endTimeRaw = is_string($trial->end_time) ? $trial->end_time : Carbon::parse($trial->end_time)->format('H:i');
            
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $startTimeRaw, $matches)) {
                $startTimeRaw = sprintf('%02d:%02d', $matches[1], $matches[2]);
            }
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $endTimeRaw, $matches)) {
                $endTimeRaw = sprintf('%02d:%02d', $matches[1], $matches[2]);
            }
            
            try {
                $startDateTime = Carbon::createFromFormat('H:i', $startTimeRaw);
                $endDateTime = Carbon::createFromFormat('H:i', $endTimeRaw);
            } catch (\Exception $e) {
                $startDateTime = Carbon::parse($startTimeRaw);
                $endDateTime = Carbon::parse($endTimeRaw);
            }
            
            $time = $startDateTime->format('g:i A');
            $endTime = $endDateTime->format('g:i A');
        }
        
        // Get trial notes/description
        $trialNotes = $trial->notes ?? '';
        $academyName = config('app.name', 'Elm Corner Academy');
        $supportPhone = config('whatsapp.support_phone', '+201099471391');

        $message = "🎓 *أكاديمية إلم كورنر*\n\n";
        $message .= "🎉 *تم جدولة حصة تجريبية جديدة*\n\n";
        $message .= "📚 *الدورة:* {$courseName}\n";
        $message .= "👤 *الطالب:* {$studentName}\n";
        $message .= "📅 *التاريخ:* {$date}\n";
        $message .= "⏰ *الوقت:* {$time} - {$endTime}\n\n";
        $message .= "✅ تم تأكيد الجدولة بنجاح\n";
        $message .= "📱 سيتم إرسال تذكير في وقت الحصة";
        
        // Add trial notes/description if available
        if (!empty($trial->notes)) {
            $message .= "\n\n📝 *التفاصيل:*\n{$trial->notes}";
        }
        
        $message .= "\n\n💬 *واتساب الدعم:* {$supportPhone}";

        return $message;
    }

    /**
     * Get Arabic student message
     */
    protected function getArabicStudentMessage(string $studentName, string $teacherName, string $country, string $date, string $time, string $endTime): string
    {
        $academyName = config('app.name', 'Elm Corner Academy');
        $supportPhone = config('whatsapp.support_phone', '+201099471391');
        
        $dateObj = Carbon::parse($date);
        $dayName = $this->getArabicDayName($dateObj->dayOfWeek);
        $monthName = $this->getArabicMonthName($dateObj->month);
        $formattedDate = "{$dayName}، {$dateObj->day} {$monthName} {$dateObj->year}";
        
        $message = "🎓 *أكاديمية إلم كورنر*\n\n";
        $message .= "🎉 *تم حجز حصتك التجريبية بنجاح*\n\n";
        $message .= "👨‍🏫 *المعلم:* {$teacherName}\n";
        $message .= "📅 *التاريخ:* {$formattedDate}\n";
        $message .= "⏰ *الوقت:* {$time} - {$endTime}\n\n";
        $message .= "✅ *بإذن الله*، سيتم إرسال رابط الزوم في وقت الحصة\n\n";
        $message .= "💬 *واتساب الدعم:* {$supportPhone}";

        return $message;
    }

    /**
     * Get English student message
     */
    protected function getEnglishStudentMessage(string $studentName, string $teacherName, string $country, string $date, string $time, string $endTime): string
    {
        $academyName = config('app.name', 'Elm Corner Academy');
        $supportPhone = config('whatsapp.support_phone', '+201099471391');
        
        $dateObj = Carbon::parse($date);
        $formattedDate = $dateObj->format('l, F j, Y');
        
        $message = "🎓 *ELM CORNER ACADEMY*\n\n";
        $message .= "🎉 *Your Free Trial is Scheduled*\n\n";
        $message .= "👨‍🏫 *Teacher:* {$teacherName}\n";
        $message .= "📅 *Date:* {$formattedDate}\n";
        $message .= "⏰ *Time:* {$time} - {$endTime}\n\n";
        $message .= "✅ *Insha'Allah*, the Zoom link will be sent at the time of the class\n\n";
        $message .= "💬 *WhatsApp Support:* {$supportPhone}";

        return $message;
    }

    /**
     * Get French student message
     */
    protected function getFrenchStudentMessage(string $studentName, string $teacherName, string $country, string $date, string $time, string $endTime): string
    {
        $academyName = config('app.name', 'Elm Corner Academy');
        $supportPhone = config('whatsapp.support_phone', '+201099471391');
        
        $dateObj = Carbon::parse($date);
        $formattedDate = $dateObj->locale('fr')->translatedFormat('l j F Y');
        
        $message = "🎓 *ELM CORNER ACADEMY*\n\n";
        $message .= "🎉 *Votre Essai Gratuit est Programmé*\n\n";
        $message .= "👨‍🏫 *Professeur:* {$teacherName}\n";
        $message .= "📅 *Date:* {$formattedDate}\n";
        $message .= "⏰ *Heure:* {$time} - {$endTime}\n\n";
        $message .= "✅ *Insha'Allah*, le lien Zoom sera envoyé à l'heure du cours\n\n";
        $message .= "💬 *Support WhatsApp:* {$supportPhone}";

        return $message;
    }

    /**
     * Get Arabic day name
     */
    protected function getArabicDayName(int $dayOfWeek): string
    {
        $days = [
            0 => 'الأحد',
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];
        return $days[$dayOfWeek] ?? '';
    }

    /**
     * Get Arabic month name
     */
    protected function getArabicMonthName(int $month): string
    {
        $months = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ];
        return $months[$month] ?? '';
    }

    /**
     * Get reminder message for class
     */
    protected function getClassReminderMessage(ClassInstance $class, string $reminderType, string $recipient = 'student'): string
    {
        $courseName = $class->course->name ?? 'الدورة';
        $teacherName = $class->teacher->user->name ?? 'المعلم';
        $studentName = $class->student->full_name ?? 'الطالب';
        $supportPhone = config('whatsapp.support_phone', '+201099471391');
        $meetLink = $class->teacher->meet_link ?? '';
        
        // Get student language for translation
        $studentLanguage = 'ar'; // Default to Arabic
        if ($recipient === 'student') {
            try {
                $student = \App\Models\Student::find($class->student_id);
                if ($student && isset($student->language)) {
                    $studentLanguage = strtolower(trim((string)$student->language));
                    if (empty($studentLanguage) || !in_array($studentLanguage, ['ar', 'en', 'fr'])) {
                        $studentLanguage = 'ar';
                    }
                }
            } catch (\Exception $e) {
                // Default to Arabic on error
                $studentLanguage = 'ar';
            }
        }
        
        // Always use teacher time as primary time (for both student and teacher)
        $classDate = $class->class_date instanceof \Carbon\Carbon 
            ? $class->class_date 
            : Carbon::parse($class->class_date);
        $date = $classDate->format('Y-m-d');
        $startTime = is_string($class->start_time) 
            ? Carbon::parse($class->start_time)->format('H:i')
            : Carbon::parse($class->start_time)->format('H:i');
        // Format time to 12-hour format
        $timeParts = explode(':', $startTime);
        $hour = (int)$timeParts[0];
        $minute = $timeParts[1] ?? '00';
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12 ?: 12;
        $time = sprintf('%d:%s %s', $hour12, $minute, $ampm);
        
        // Get student time as additional info (if available and sending to student)
        $studentTime = '';
        if ($recipient === 'student' && $class->student_date && $class->student_start_time) {
            $studentStartTime = is_string($class->student_start_time) 
                ? $class->student_start_time 
                : Carbon::parse($class->student_start_time)->format('H:i');
            $studentTimeParts = explode(':', $studentStartTime);
            $studentHour = (int)$studentTimeParts[0];
            $studentMinute = $studentTimeParts[1] ?? '00';
            $studentAmpm = $studentHour >= 12 ? 'PM' : 'AM';
            $studentHour12 = $studentHour % 12 ?: 12;
            $studentTime = sprintf('%d:%s %s', $studentHour12, $studentMinute, $studentAmpm);
        }

        // Get status message based on recipient and reminder type
        $statusMessage = $this->getClassReminderStatusMessage($reminderType, $recipient, $studentName, $studentLanguage);
        
        $academyName = config('app.name', 'Elm Corner Academy');

        // Generate message based on language
        if ($studentLanguage === 'en' && $recipient === 'student') {
            $message = "🎓 *ELM CORNER ACADEMY*\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "👨‍🏫 *Teacher:* {$teacherName}\n";
            $message .= "📚 *Course:* {$courseName}\n";
            $message .= "🕐 *Time:* {$time}";
            if (!empty($studentTime)) {
                $message .= " (Your time: {$studentTime})";
            }
            $message .= "\n";
            
            if ($meetLink) {
                $message .= "\n🔗 *Zoom Link:*\n{$meetLink}";
            }
            
            $message .= "\n\n💬 *WhatsApp Support:* {$supportPhone}";
            
            return $message;
        } elseif ($studentLanguage === 'fr' && $recipient === 'student') {
            $message = "🎓 *ELM CORNER ACADEMY*\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "👨‍🏫 *Professeur:* {$teacherName}\n";
            $message .= "📚 *Cours:* {$courseName}\n";
            $message .= "🕐 *Heure:* {$time}";
            if (!empty($studentTime)) {
                $message .= " (Votre heure: {$studentTime})";
            }
            $message .= "\n";
            
            if ($meetLink) {
                $message .= "\n🔗 *Lien Zoom:*\n{$meetLink}";
            }
            
            $message .= "\n\n💬 *Support WhatsApp:* {$supportPhone}";
            
            return $message;
        } elseif ($recipient === 'teacher') {
            // Teacher messages - always in Arabic with system link and credentials
            $frontendUrl = env('FRONTEND_URL', config('app.url', 'https://admin.elmcorner.com'));
            $systemLink = rtrim($frontendUrl, '/') . '/login';
            $user = $class->teacher->user;
            $email = $user->email ?? '';
            $password = $user->plain_password ?? 'Not available';
            
            $message = "🎓 *أكاديمية إلم كورنر*\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "📚 *الدورة:* {$courseName}\n";
            $message .= "👤 *الطالب:* {$studentName}\n";
            $message .= "🕐 *الوقت:* {$time}\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "🔗 *رابط النظام:*\n";
            $message .= "{$systemLink}\n\n";
            $message .= "📋 *بيانات الدخول:*\n";
            $message .= "📧 *البريد:* {$email}\n";
            $message .= "🔐 *كلمة المرور:* {$password}\n\n";
            $message .= "⚠️ *يرجى تسجيل الدخول إلى حسابك وبدء الحصة من هناك*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💬 *واتساب الدعم:* {$supportPhone}";
            
            return $message;
        } else {
            // Arabic (default) for students
            $message = "🎓 *أكاديمية إلم كورنر*\n\n";
            $message .= "{$statusMessage}\n\n";
            $message .= "👨‍🏫 *المعلم:* {$teacherName}\n";
            $message .= "📚 *الدورة:* {$courseName}\n";
            $message .= "🕐 *الوقت:* {$time}";
            if (!empty($studentTime) && $recipient === 'student') {
                $message .= " (وقتك: {$studentTime})";
            }
            $message .= "\n";
            
            if ($meetLink) {
                $message .= "\n🔗 *رابط الزوم:*\n{$meetLink}";
            }
            
            $message .= "\n\n💬 *واتساب الدعم:* {$supportPhone}";
            
            return $message;
        }
    }

    /**
     * Get class reminder status message based on type and recipient
     */
    protected function getClassReminderStatusMessage(string $reminderType, string $recipient, string $studentName, string $language = 'ar'): string
    {
        if ($recipient === 'student') {
            // Student messages - translate based on language
            switch ($reminderType) {
                case '2hours_before':
                    if ($language === 'en') {
                        return "Your class will start in 2 hours";
                    } elseif ($language === 'fr') {
                        return "Votre cours commencera dans 2 heures";
                    } else {
                        return "حصتك ستبدأ بعد ساعتين";
                    }
                
                case '5min_before':
                    if ($language === 'en') {
                        return "Your class will start soon";
                    } elseif ($language === 'fr') {
                        return "Votre cours va commencer bientôt";
                    } else {
                        return "حصتك ستبدأ قريبا";
                    }
                
                case 'start_time':
                    if ($language === 'en') {
                        return "Your class has started. The teacher is waiting";
                    } elseif ($language === 'fr') {
                        return "Votre cours a commencé. Le professeur attend";
                    } else {
                        return "حصتك بدأت المعلم في الانتظار";
                    }
                
                case '5min_after':
                    if ($language === 'en') {
                        return "Your class started minutes ago. The teacher is waiting";
                    } elseif ($language === 'fr') {
                        return "Votre cours a commencé il y a quelques minutes. Le professeur attend";
                    } else {
                        return "حصتك بدأت منذ دقائق المعلم في الانتظار";
                    }
                
                default:
                    return "حصتك ستبدأ قريبا";
            }
        } else {
            // Teacher messages - always in Arabic
            switch ($reminderType) {
                case '2hours_before':
                    return "الحصة للطالب {$studentName} ستبدأ بعد ساعتين";
                
                case '5min_before':
                    return "الحصة للطالب {$studentName} ستبدأ قريبا";
                
                case 'start_time':
                    return "الحصة الخاصه بالطالب {$studentName} بدأت";
                
                case '5min_after':
                    return "الحصة الخاصه بالطالب {$studentName} بدأت منذ دقايق";
                
                default:
                    return "الحصة للطالب {$studentName} ستبدأ قريبا";
            }
        }
    }
}
