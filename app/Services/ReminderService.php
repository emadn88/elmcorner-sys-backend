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
            $trial->load(['student', 'teacher.user', 'course']);
            
            $studentPhone = $trial->student->whatsapp;
            $teacherPhone = $trial->teacher->user->whatsapp ?? null;

            if (!$studentPhone && !$teacherPhone) {
                Log::warning('No WhatsApp numbers found for trial reminder', [
                    'trial_id' => $trial->id,
                    'reminder_type' => $reminderType,
                ]);
                return false;
            }

            $message = $this->getTrialReminderMessage($trial, $reminderType);
            $success = true;

            // Send to student
            if ($studentPhone) {
                $sent = $this->whatsappService->sendMessage($studentPhone, $message);
                if (!$sent) {
                    $success = false;
                }
            }

            // Send to teacher
            if ($teacherPhone) {
                $sent = $this->whatsappService->sendMessage($teacherPhone, $message);
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
     * Send reminder for a class
     */
    public function sendClassReminder(ClassInstance $class, string $reminderType): bool
    {
        try {
            $class->load(['student', 'teacher.user', 'course']);
            
            $studentPhone = $class->student->whatsapp;
            $teacherPhone = $class->teacher->user->whatsapp ?? null;

            if (!$studentPhone && !$teacherPhone) {
                Log::warning('No WhatsApp numbers found for class reminder', [
                    'class_id' => $class->id,
                    'reminder_type' => $reminderType,
                ]);
                return false;
            }

            $message = $this->getClassReminderMessage($class, $reminderType);
            $success = true;

            // Send to student
            if ($studentPhone) {
                $sent = $this->whatsappService->sendMessage($studentPhone, $message);
                if (!$sent) {
                    $success = false;
                }
            }

            // Send to teacher
            if ($teacherPhone) {
                $sent = $this->whatsappService->sendMessage($teacherPhone, $message);
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
     * Get reminder message for trial
     */
    protected function getTrialReminderMessage(TrialClass $trial, string $reminderType): string
    {
        $courseName = $trial->course->name ?? 'الدورة';
        $teacherName = $trial->teacher->user->name ?? 'المعلم';
        $date = $trial->trial_date->format('Y-m-d');
        $time = is_string($trial->start_time) ? $trial->start_time : Carbon::parse($trial->start_time)->format('H:i');

        switch ($reminderType) {
            case '5min_before':
                return "تذكير: لديك تجربة في {$courseName} بعد 5 دقائق مع {$teacherName} في {$date} الساعة {$time}";
            
            case 'start_time':
                return "تذكير: تجربتك في {$courseName} مع {$teacherName} تبدأ الآن";
            
            case '5min_after':
                return "تذكير: تجربتك في {$courseName} بدأت منذ 5 دقائق. يرجى التأكد من الدخول";
            
            default:
                return "تذكير: لديك تجربة في {$courseName} مع {$teacherName}";
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
        
        // Get student timezone (default to Egypt if not set)
        $studentTimezone = $trial->student->timezone ?? 'Africa/Cairo';
        
        // Convert times to student timezone
        $trialDate = $trial->trial_date->format('Y-m-d');
        
        // Parse start time - handle both H:i and H:i:s formats
        if (is_string($trial->start_time)) {
            $startTime = $trial->start_time;
        } else {
            $startTime = Carbon::parse($trial->start_time)->format('H:i');
        }
        
        // Parse end time - handle both H:i and H:i:s formats
        if (is_string($trial->end_time)) {
            $endTime = $trial->end_time;
        } else {
            $endTime = Carbon::parse($trial->end_time)->format('H:i');
        }
        
        // Normalize time format to H:i:s
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $startTime, $matches)) {
            $startTime = sprintf('%02d:%02d:00', $matches[1], $matches[2]);
        } elseif (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $startTime, $matches)) {
            $startTime = sprintf('%02d:%02d:%02d', $matches[1], $matches[2], $matches[3]);
        }
        
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $endTime, $matches)) {
            $endTime = sprintf('%02d:%02d:00', $matches[1], $matches[2]);
        } elseif (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $endTime, $matches)) {
            $endTime = sprintf('%02d:%02d:%02d', $matches[1], $matches[2], $matches[3]);
        }
        
        // Create datetime in Egypt timezone (default) and convert to student timezone
        try {
            $startDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$trialDate} {$startTime}", 'Africa/Cairo');
            $endDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$trialDate} {$endTime}", 'Africa/Cairo');
        } catch (\Exception $e) {
            Log::error('Failed to parse trial times', [
                'trial_id' => $trial->id,
                'start_time' => $trial->start_time,
                'end_time' => $trial->end_time,
                'parsed_start' => $startTime,
                'parsed_end' => $endTime,
                'error' => $e->getMessage(),
            ]);
            // Fallback: use current timezone
            $startDateTime = Carbon::parse("{$trialDate} {$startTime}", 'Africa/Cairo');
            $endDateTime = Carbon::parse("{$trialDate} {$endTime}", 'Africa/Cairo');
        }
        
        $startDateTime->setTimezone($studentTimezone);
        $endDateTime->setTimezone($studentTimezone);
        
        $date = $startDateTime->format('Y-m-d');
        // Convert to 12-hour format with AM/PM
        $time = $startDateTime->format('g:i A');
        $endTimeFormatted = $endDateTime->format('g:i A');
        
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
    protected function getTrialCreationMessageForTeacher(TrialClass $trial): string
    {
        $courseName = $trial->course->name ?? 'الدورة';
        $studentName = $trial->student->full_name ?? 'الطالب';
        $teacherName = $trial->teacher->user->name ?? 'المعلم';
        
        // Format date nicely
        $dateObj = Carbon::parse($trial->trial_date);
        $dayName = $this->getArabicDayName($dateObj->dayOfWeek);
        $monthName = $this->getArabicMonthName($dateObj->month);
        $date = "{$dayName}، {$dateObj->day} {$monthName} {$dateObj->year}";
        
        // Parse times and convert to 12-hour format
        $startTimeRaw = is_string($trial->start_time) ? $trial->start_time : Carbon::parse($trial->start_time)->format('H:i');
        $endTimeRaw = is_string($trial->end_time) ? $trial->end_time : Carbon::parse($trial->end_time)->format('H:i');
        
        // Normalize time format to H:i if needed
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $startTimeRaw, $matches)) {
            $startTimeRaw = sprintf('%02d:%02d', $matches[1], $matches[2]);
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $endTimeRaw, $matches)) {
            $endTimeRaw = sprintf('%02d:%02d', $matches[1], $matches[2]);
        }
        
        // Convert to 12-hour format with AM/PM
        try {
            $startDateTime = Carbon::createFromFormat('H:i', $startTimeRaw);
            $endDateTime = Carbon::createFromFormat('H:i', $endTimeRaw);
        } catch (\Exception $e) {
            // Fallback: try parsing directly
            $startDateTime = Carbon::parse($startTimeRaw);
            $endDateTime = Carbon::parse($endTimeRaw);
        }
        
        $time = $startDateTime->format('g:i A');
        $endTime = $endDateTime->format('g:i A');
        
        // Get trial notes/description
        $trialNotes = $trial->notes ?? '';

        $message = "🎉 *تم جدولة حصة تجريبية جديدة*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📚 *الدورة:* {$courseName}\n";
        $message .= "👤 *الطالب:* {$studentName}\n";
        $message .= "👨‍🏫 *المعلم:* {$teacherName}\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📅 *التاريخ:* {$date}\n";
        $message .= "⏰ *الوقت:* {$time} - {$endTime}\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "✅ تم تأكيد الجدولة بنجاح\n";
        $message .= "📱 سيتم إرسال تذكير في وقت الحصة\n\n";
        
        // Add trial notes/description if available
        if (!empty($trial->notes)) {
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📝 *التفاصيل:*\n";
            $message .= $trial->notes . "\n\n";
        }
        
        $message .= "وفقكم الله 🌟";

        return $message;
    }

    /**
     * Get Arabic student message
     */
    protected function getArabicStudentMessage(string $studentName, string $teacherName, string $country, string $date, string $time, string $endTime): string
    {
        $dateObj = Carbon::parse($date);
        $dayName = $this->getArabicDayName($dateObj->dayOfWeek);
        $monthName = $this->getArabicMonthName($dateObj->month);
        $formattedDate = "{$dayName}، {$dateObj->day} {$monthName} {$dateObj->year}";
        
        $message = "🎉 *تم حجز حصتك التجريبية بنجاح*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "👤 *الطالب:* {$studentName}\n";
        $message .= "👨‍🏫 *المعلم:* {$teacherName}\n";
        if ($country) {
            $message .= "🌍 *البلد:* {$country}\n";
        }
        $message .= "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📅 *التاريخ:* {$formattedDate}\n";
        $message .= "⏰ *الوقت:* {$time} - {$endTime}\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "✅ *بإذن الله*، سيتم إرسال رابط الزوم في وقت الحصة\n\n";
        $message .= "جزاك الله خيراً 🌟";

        return $message;
    }

    /**
     * Get English student message
     */
    protected function getEnglishStudentMessage(string $studentName, string $teacherName, string $country, string $date, string $time, string $endTime): string
    {
        $dateObj = Carbon::parse($date);
        $formattedDate = $dateObj->format('l, F j, Y');
        
        $message = "🎉 *Your free trial is Scheduled*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "👤 *Student:* {$studentName}\n";
        $message .= "👨‍🏫 *Teacher:* {$teacherName}\n";
        if ($country) {
            $message .= "🌍 *Country:* {$country}\n";
        }
        $message .= "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📅 *Date:* {$formattedDate}\n";
        $message .= "⏰ *Time:* {$time} - {$endTime}\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "✅ *Insha'Allah*, the Zoom link will be sent at the time of the class\n\n";
        $message .= "May Allah reward you 🌟";

        return $message;
    }

    /**
     * Get French student message
     */
    protected function getFrenchStudentMessage(string $studentName, string $teacherName, string $country, string $date, string $time, string $endTime): string
    {
        $dateObj = Carbon::parse($date);
        $formattedDate = $dateObj->locale('fr')->translatedFormat('l j F Y');
        
        $message = "🎉 *Votre essai gratuit est programmé*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "👤 *Étudiant:* {$studentName}\n";
        $message .= "👨‍🏫 *Professeur:* {$teacherName}\n";
        if ($country) {
            $message .= "🌍 *Pays:* {$country}\n";
        }
        $message .= "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📅 *Date:* {$formattedDate}\n";
        $message .= "⏰ *Heure:* {$time} - {$endTime}\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "✅ *Insha'Allah*, le lien Zoom sera envoyé à l'heure du cours\n\n";
        $message .= "Qu'Allah vous récompense 🌟";

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
    protected function getClassReminderMessage(ClassInstance $class, string $reminderType): string
    {
        $courseName = $class->course->name ?? 'الدورة';
        $teacherName = $class->teacher->user->name ?? 'المعلم';
        $date = $class->class_date->format('Y-m-d');
        $time = is_string($class->start_time) ? $class->start_time : Carbon::parse($class->start_time)->format('H:i');

        switch ($reminderType) {
            case '5min_before':
                return "تذكير: لديك حصة في {$courseName} بعد 5 دقائق مع {$teacherName} في {$date} الساعة {$time}";
            
            case 'start_time':
                return "تذكير: حصتك في {$courseName} مع {$teacherName} تبدأ الآن";
            
            case '5min_after':
                return "تذكير: حصتك في {$courseName} بدأت منذ 5 دقائق. يرجى التأكد من الدخول";
            
            default:
                return "تذكير: لديك حصة في {$courseName} مع {$teacherName}";
        }
    }
}
