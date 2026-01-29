<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\TeacherAvailability;
use App\Services\TeacherService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    protected $teacherService;
    protected $whatsappService;

    public function __construct(TeacherService $teacherService, WhatsAppService $whatsappService)
    {
        $this->teacherService = $teacherService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status', 'all'),
            'course_id' => $request->input('course_id'),
        ];

        $perPage = $request->input('per_page', 15);
        $teachers = $this->teacherService->getTeachers($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $teachers->items(),
            'meta' => [
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
            ],
        ]);
    }

    /**
     * Get teacher statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->teacherService->getTeacherStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Get password or generate default
        $password = $validated['password'] ?? 'password';
        
        // Create user first
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($password),
            'plain_password' => $password, // Store plain password (encrypted)
            'role' => 'teacher',
            'whatsapp' => $validated['whatsapp'] ?? null,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'status' => 'active',
        ]);

        // Extract course_ids if present
        $courseIds = $validated['course_ids'] ?? null;
        unset($validated['course_ids']);
        
        // Create teacher profile
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'hourly_rate' => $validated['hourly_rate'],
            'currency' => $validated['currency'] ?? 'USD',
            'timezone' => $validated['timezone'] ?? 'UTC',
            'status' => $validated['status'],
            'bio' => $validated['bio'] ?? null,
            'meet_link' => $validated['meet_link'] ?? null,
        ]);
        
        // Sync courses if provided
        if ($courseIds !== null) {
            $teacher->courses()->sync($courseIds);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create',
            'description' => "Teacher {$user->name} was created",
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher created successfully',
            'data' => $teacher->load(['user', 'courses']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $profile = $this->teacherService->getTeacherProfile($id);

        return response()->json([
            'status' => 'success',
            'data' => $profile,
        ]);
    }

    /**
     * Get teacher performance metrics
     */
    public function performance(Request $request, string $id): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $performance = $this->teacherService->getTeacherPerformance($id, $dateFrom, $dateTo);

        return response()->json([
            'status' => 'success',
            'data' => $performance,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeacherRequest $request, string $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        $validated = $request->validated();
        
        // Update user if user fields are provided
        if ($teacher->user) {
            $userData = [];
            if (isset($validated['name'])) {
                $userData['name'] = $validated['name'];
            }
            if (isset($validated['email'])) {
                $userData['email'] = $validated['email'];
            }
            if (isset($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
                $userData['plain_password'] = $validated['password']; // Store plain password (encrypted)
            }
            if (isset($validated['whatsapp'])) {
                $userData['whatsapp'] = $validated['whatsapp'];
            }
            
            if (!empty($userData)) {
                $teacher->user->update($userData);
            }
        }
        
        // Update teacher fields
        $teacherData = [];
        if (isset($validated['hourly_rate'])) {
            $teacherData['hourly_rate'] = $validated['hourly_rate'];
        }
        if (isset($validated['currency'])) {
            $teacherData['currency'] = $validated['currency'];
        }
        if (isset($validated['timezone'])) {
            $teacherData['timezone'] = $validated['timezone'];
        }
        if (isset($validated['status'])) {
            $teacherData['status'] = $validated['status'];
        }
        if (isset($validated['bio'])) {
            $teacherData['bio'] = $validated['bio'];
        }
        if (isset($validated['meet_link'])) {
            $teacherData['meet_link'] = $validated['meet_link'];
        }
        
        // Extract course_ids if present
        $courseIds = $validated['course_ids'] ?? null;
        unset($validated['course_ids']);
        
        if (!empty($teacherData)) {
            $teacher->update($teacherData);
        }
        
        // Sync courses if provided
        if ($courseIds !== null) {
            $teacher->courses()->sync($courseIds);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'description' => "Teacher {$teacher->full_name} was updated",
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher updated successfully',
            'data' => $teacher->fresh()->load(['user', 'courses']),
        ]);
    }

    /**
     * Get teacher's availability
     */
    public function getAvailability(string $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        
        $availability = $teacher->availability()
            ->where('is_available', true)
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $availability,
        ]);
    }

    /**
     * Get available time slots for a teacher on a specific date
     * Excludes booked trials and classes
     */
    public function getAvailableTimeSlots(string $id, Request $request): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        
        $request->validate([
            'date' => 'required|date',
        ]);
        
        $date = \Carbon\Carbon::parse($request->input('date'));
        $dayOfWeek = $date->dayOfWeek == 0 ? 7 : $date->dayOfWeek; // Convert Sunday (0) to 7
        
        // Get teacher's availability for this day of week
        $availabilitySlots = $teacher->availability()
            ->where('is_available', true)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time', 'asc')
            ->get();
        
        // Get booked trials for this date
        $bookedTrials = \App\Models\TrialClass::where('teacher_id', $teacher->id)
            ->where('trial_date', $date->format('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->get();
        
        // Get booked classes for this date
        $bookedClasses = \App\Models\ClassInstance::where('teacher_id', $teacher->id)
            ->where('class_date', $date->format('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->get();
        
        // Filter out booked time slots
        $dateStr = $date->format('Y-m-d');
        $availableSlots = $availabilitySlots->filter(function ($slot) use ($bookedTrials, $bookedClasses, $dateStr) {
            $slotStartTime = strlen($slot->start_time) === 5 ? $slot->start_time : substr($slot->start_time, 0, 5);
            $slotEndTime = strlen($slot->end_time) === 5 ? $slot->end_time : substr($slot->end_time, 0, 5);
            
            $slotStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$slotStartTime}");
            $slotEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$slotEndTime}");
            
            // Check if slot conflicts with any booked trial
            foreach ($bookedTrials as $trial) {
                $trialStartTime = strlen($trial->start_time) === 5 ? $trial->start_time : substr($trial->start_time, 0, 5);
                $trialEndTime = strlen($trial->end_time) === 5 ? $trial->end_time : substr($trial->end_time, 0, 5);
                
                $trialStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$trialStartTime}");
                $trialEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$trialEndTime}");
                
                // Check for overlap: slot start < trial end AND slot end > trial start
                if ($slotStart->lt($trialEnd) && $slotEnd->gt($trialStart)) {
                    return false; // Slot is booked
                }
            }
            
            // Check if slot conflicts with any booked class
            foreach ($bookedClasses as $class) {
                // ClassInstance has datetime fields, extract time portion
                $classStartTime = $class->start_time instanceof \Carbon\Carbon 
                    ? $class->start_time->format('H:i')
                    : (is_string($class->start_time) ? substr($class->start_time, 11, 5) : '00:00');
                $classEndTime = $class->end_time instanceof \Carbon\Carbon 
                    ? $class->end_time->format('H:i')
                    : (is_string($class->end_time) ? substr($class->end_time, 11, 5) : '00:00');
                
                $classStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$classStartTime}");
                $classEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$classEndTime}");
                
                // Check for overlap
                if ($slotStart->lt($classEnd) && $slotEnd->gt($classStart)) {
                    return false; // Slot is booked
                }
            }
            
            return true; // Slot is available
        })->values();
        
        return response()->json([
            'status' => 'success',
            'data' => $availableSlots,
        ]);
    }

    /**
     * Get teacher's weekly schedule (availability + classes + trials + timetables)
     */
    public function getWeeklySchedule(string $id, Request $request): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        
        // Get week start date (default to current week start - Sunday)
        $weekStartInput = $request->input('week_start');
        if ($weekStartInput) {
            $weekStart = \Carbon\Carbon::parse($weekStartInput)->startOfWeek(\Carbon\Carbon::SUNDAY);
        } else {
            $weekStart = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY);
        }
        $weekEnd = $weekStart->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);

        // Get availability slots
        $availability = $teacher->availability()
            ->where('is_available', true)
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        // Get classes for the week
        $classes = \App\Models\ClassInstance::where('teacher_id', $teacher->id)
            ->whereBetween('class_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->with(['student', 'course', 'timetable', 'package'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        // Get trials for the week
        $trials = \App\Models\TrialClass::where('teacher_id', $teacher->id)
            ->whereBetween('trial_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
            ->with(['student', 'course'])
            ->orderBy('trial_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        // Get active timetables that might generate classes for this week
        $timetables = \App\Models\Timetable::where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->with(['student', 'course'])
            ->get();

        // Format response grouped by day of week
        $schedule = [];
        for ($day = 1; $day <= 7; $day++) {
            $date = $weekStart->copy()->addDays($day - 1);
            
            // Get availability for this day
            $dayAvailability = $availability->where('day_of_week', $day)->values();
            
            // Get classes for this date
            $dayClasses = $classes->filter(function ($class) use ($date) {
                return $class->class_date->format('Y-m-d') === $date->format('Y-m-d');
            })->values();
            
            // Get trials for this date
            $dayTrials = $trials->filter(function ($trial) use ($date) {
                return $trial->trial_date->format('Y-m-d') === $date->format('Y-m-d');
            })->values();
            
            $schedule[] = [
                'day_of_week' => $day,
                'date' => $date->format('Y-m-d'),
                'date_formatted' => $date->format('Y-m-d'),
                'availability' => $dayAvailability,
                'classes' => $dayClasses,
                'trials' => $dayTrials,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->full_name,
                    'timezone' => $teacher->timezone ?? 'UTC',
                ],
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'schedule' => $schedule,
                'timetables' => $timetables, // Active timetables for reference
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        $teacherName = $teacher->full_name;

        // Check for active classes
        $hasActiveClasses = $teacher->classes()
            ->where('class_date', '>=', now())
            ->exists();

        if ($hasActiveClasses) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete teacher with active classes',
            ], 422);
        }

        // Log activity before deletion
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'description' => "Teacher {$teacherName} was deleted",
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $teacher->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher deleted successfully',
        ]);
    }

    /**
     * Assign courses to teacher
     */
    public function assignCourses(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'course_ids' => ['required', 'array'],
            'course_ids.*' => ['exists:courses,id'],
        ]);

        $teacher = $this->teacherService->assignCourses($id, $request->course_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Courses assigned successfully',
            'data' => $teacher,
        ]);
    }

    /**
     * Get teacher monthly statistics and students
     */
    public function monthlyStats(Request $request, string $id): JsonResponse
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $stats = $this->teacherService->getTeacherMonthlyStats((int) $id, (int) $month, (int) $year);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Find all available teachers for a specific date and time range
     */
    public function findAvailableTeachers(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $date = \Carbon\Carbon::parse($request->input('date'));
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        $dayOfWeek = $date->dayOfWeek == 0 ? 7 : $date->dayOfWeek; // Convert Sunday (0) to 7
        $dateStr = $date->format('Y-m-d');

        // Get all active teachers
        $teachers = Teacher::where('status', 'active')
            ->with(['user', 'availability'])
            ->get();

        $availableTeachers = [];

        foreach ($teachers as $teacher) {
            // Check if teacher has availability for this day of week
            $availabilitySlots = $teacher->availability()
                ->where('is_available', true)
                ->where('day_of_week', $dayOfWeek)
                ->get();

            if ($availabilitySlots->isEmpty()) {
                continue; // Teacher has no availability for this day
            }

            // Check if the requested time overlaps with any availability slot
            $hasMatchingAvailability = false;
            foreach ($availabilitySlots as $slot) {
                $slotStartTime = strlen($slot->start_time) === 5 ? $slot->start_time : substr($slot->start_time, 0, 5);
                $slotEndTime = strlen($slot->end_time) === 5 ? $slot->end_time : substr($slot->end_time, 0, 5);

                $slotStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$slotStartTime}");
                $slotEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$slotEndTime}");
                $requestStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$startTime}");
                $requestEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$endTime}");

                // Check if requested time is within availability slot
                if ($requestStart->gte($slotStart) && $requestEnd->lte($slotEnd)) {
                    $hasMatchingAvailability = true;
                    break;
                }
            }

            if (!$hasMatchingAvailability) {
                continue; // Teacher doesn't have availability for this time
            }

            // Check for conflicts with booked classes
            $bookedClasses = \App\Models\ClassInstance::where('teacher_id', $teacher->id)
                ->where('class_date', $dateStr)
                ->where('status', '!=', 'cancelled')
                ->get();

            $hasConflict = false;
            foreach ($bookedClasses as $class) {
                $classStartTime = $class->start_time instanceof \Carbon\Carbon 
                    ? $class->start_time->format('H:i')
                    : (is_string($class->start_time) ? substr($class->start_time, 11, 5) : '00:00');
                $classEndTime = $class->end_time instanceof \Carbon\Carbon 
                    ? $class->end_time->format('H:i')
                    : (is_string($class->end_time) ? substr($class->end_time, 11, 5) : '00:00');

                $classStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$classStartTime}");
                $classEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$classEndTime}");
                $requestStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$startTime}");
                $requestEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$endTime}");

                // Check for overlap
                if ($requestStart->lt($classEnd) && $requestEnd->gt($classStart)) {
                    $hasConflict = true;
                    break;
                }
            }

            if ($hasConflict) {
                continue; // Teacher has a conflict
            }

            // Check for conflicts with booked trials
            $bookedTrials = \App\Models\TrialClass::where('teacher_id', $teacher->id)
                ->where('trial_date', $dateStr)
                ->where('status', '!=', 'cancelled')
                ->get();

            foreach ($bookedTrials as $trial) {
                $trialStartTime = strlen($trial->start_time) === 5 ? $trial->start_time : substr($trial->start_time, 0, 5);
                $trialEndTime = strlen($trial->end_time) === 5 ? $trial->end_time : substr($trial->end_time, 0, 5);

                $trialStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$trialStartTime}");
                $trialEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$trialEndTime}");
                $requestStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$startTime}");
                $requestEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$dateStr} {$endTime}");

                // Check for overlap
                if ($requestStart->lt($trialEnd) && $requestEnd->gt($trialStart)) {
                    $hasConflict = true;
                    break;
                }
            }

            if (!$hasConflict) {
                $availableTeachers[] = [
                    'id' => $teacher->id,
                    'name' => $teacher->user->name ?? 'N/A',
                    'email' => $teacher->user->email ?? 'N/A',
                    'hourly_rate' => $teacher->hourly_rate,
                    'currency' => $teacher->currency,
                    'status' => $teacher->status,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $availableTeachers,
        ]);
    }

    /**
     * Send teacher credentials via WhatsApp
     */
    public function sendCredentialsWhatsApp(string $id): JsonResponse
    {
        try {
            $teacher = Teacher::with('user')->findOrFail($id);
            
            if (!$teacher->user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher user not found',
                ], 404);
            }

            $user = $teacher->user;
            
            if (!$user->whatsapp) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher WhatsApp number not found',
                ], 400);
            }

            // Get system link from config
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            $systemLink = rtrim($frontendUrl, '/') . '/login';

            // Get credentials
            $email = $user->email;
            $password = $user->plain_password ?? 'Not available';

            // Build modern Islamic-style message
            $message = "السلام عليكم ورحمة الله وبركاته\n\n";
            $message .= "أهلاً وسهلاً {$user->name} 👋\n\n";
            $message .= "نرحب بك في منصة *Elm Corner* 🎓\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📋 *بيانات الدخول لحسابك:*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📧 *البريد الإلكتروني:*\n";
            $message .= "{$email}\n\n";
            $message .= "🔐 *كلمة المرور:*\n";
            $message .= "{$password}\n\n";
            $message .= "🔗 *رابط النظام:*\n";
            $message .= "{$systemLink}\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📞 *لديك استفسار أو تحتاج مساعدة؟*\n\n";
            $message .= "تواصل مع فريق الدعم عبر واتساب:\n";
            $message .= "➕ *+1 (940) 618-2531*\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "نتمنى لك تجربة مميزة معنا 🌟\n\n";
            $message .= "بالتوفيق والنجاح ✨";

            // Send WhatsApp message
            $success = $this->whatsappService->sendMessage($user->whatsapp, $message);

            if (!$success) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send WhatsApp message',
                ], 500);
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'send_credentials',
                'description' => "Sent credentials via WhatsApp to teacher {$user->name}",
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Credentials sent via WhatsApp successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get teacher credentials (email and password)
     */
    public function getCredentials(string $id): JsonResponse
    {
        try {
            $teacher = Teacher::with('user')->findOrFail($id);
            
            if (!$teacher->user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher user not found',
                ], 404);
            }

            $user = $teacher->user;
            
            // Get system link from config
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            $systemLink = rtrim($frontendUrl, '/') . '/login';

            return response()->json([
                'status' => 'success',
                'data' => [
                    'email' => $user->email,
                    'password' => $user->plain_password ?? 'Not available',
                    'system_link' => $systemLink,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
