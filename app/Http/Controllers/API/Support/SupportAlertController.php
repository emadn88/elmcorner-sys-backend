<?php

namespace App\Http\Controllers\API\Support;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ClassInstance;
use App\Models\Package;
use App\Services\PackageService;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SupportAlertController extends Controller
{
    protected $packageService;

    public function __construct(PackageService $packageService)
    {
        $this->packageService = $packageService;
    }

    /**
     * Get dashboard statistics for support app
     */
    public function dashboard(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $oneHourLater = $now->copy()->addHour();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        // Classes starting in 5 minutes to 1 hour
        $fiveMinutesLater = $now->copy()->addMinutes(5);
        $classesNextHour = ClassInstance::where('class_date', $now->format('Y-m-d'))
            ->whereBetween('start_time', [
                $fiveMinutesLater->format('H:i:s'),
                $oneHourLater->format('H:i:s')
            ])
            ->where('status', 'pending')
            ->count();

        // Classes that started today but teacher hasn't joined (within last 20 minutes only)
        $twentyMinutesAgo = $now->copy()->subMinutes(20);
        $classesNoTeacher = ClassInstance::where('class_date', $now->format('Y-m-d'))
            ->where(function ($query) use ($now, $twentyMinutesAgo) {
                $query->whereRaw("CONCAT(class_date, ' ', start_time) <= ?", [$now->format('Y-m-d H:i:s')])
                    ->whereRaw("CONCAT(class_date, ' ', start_time) >= ?", [$twentyMinutesAgo->format('Y-m-d H:i:s')])
                    ->where('status', 'pending')
                    ->where('meet_link_used', false);
            })
            ->count();

        // Teachers who joined today
        $teachersJoined = ClassInstance::where('class_date', $now->format('Y-m-d'))
            ->where('meet_link_used', true)
            ->distinct('teacher_id')
            ->count('teacher_id');

        // Pending bills (finished packages not notified)
        $pendingBills = Package::where('status', 'finished')
            ->where(function ($query) {
                $query->whereNull('last_notification_sent')
                    ->orWhere('last_notification_sent', '<', DB::raw('updated_at'));
            })
            ->count();

        // Total alerts today (can be enhanced with alerts table later)
        $totalAlertsToday = $classesNextHour + $classesNoTeacher;

        return response()->json([
            'status' => 'success',
            'data' => [
                'classes_next_hour' => $classesNextHour,
                'classes_no_teacher' => $classesNoTeacher,
                'teachers_joined_today' => $teachersJoined,
                'pending_bills' => $pendingBills,
                'total_alerts_today' => $totalAlertsToday,
                'handled_alerts_today' => 0, // Can be tracked with alerts table
            ],
        ]);
    }

    /**
     * Get class alerts that need attention
     */
    public function classAlerts(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $fiveMinutesLater = $now->copy()->addMinutes(5);
        $oneHourLater = $now->copy()->addHour();

        $query = ClassInstance::with([
            'student',
            'teacher.user',
            'course'
        ])->where('status', 'pending');

        // Default to today's classes only
        $today = $now->format('Y-m-d');
        if ($request->has('date_from')) {
            $query->where('class_date', '>=', $request->input('date_from'));
        } else {
            // Default to today if no date_from specified
            $query->where('class_date', '>=', $today);
        }
        if ($request->has('date_to')) {
            $query->where('class_date', '<=', $request->input('date_to'));
        } else {
            // Default to today if no date_to specified
            $query->where('class_date', '<=', $today);
        }

        // Filter by teacher
        if ($request->has('teacher_id')) {
            $query->where('teacher_id', $request->input('teacher_id'));
        }

        // Filter by status type
        $statusType = $request->input('status_type', 'all'); // all, starting_soon, no_teacher, upcoming

        if ($statusType === 'starting_soon') {
            // Classes starting in next 5 minutes to 1 hour
            $query->where('class_date', $now->format('Y-m-d'))
                ->whereBetween('start_time', [
                    $fiveMinutesLater->format('H:i:s'),
                    $oneHourLater->format('H:i:s')
                ]);
        } elseif ($statusType === 'no_teacher') {
            // Classes that started but teacher hasn't joined (within last 20 minutes only)
            $twentyMinutesAgo = $now->copy()->subMinutes(20);
            $query->where(function ($q) use ($now, $twentyMinutesAgo) {
                $q->whereRaw("CONCAT(class_date, ' ', start_time) <= ?", [$now->format('Y-m-d H:i:s')])
                    ->whereRaw("CONCAT(class_date, ' ', start_time) >= ?", [$twentyMinutesAgo->format('Y-m-d H:i:s')])
                    ->where('meet_link_used', false);
            });
        } elseif ($statusType === 'upcoming') {
            // All upcoming classes
            $query->where(function ($q) use ($now, $oneHourLater) {
                $q->where('class_date', '>', $now->format('Y-m-d'))
                    ->orWhere(function ($q2) use ($now, $oneHourLater) {
                        $q2->where('class_date', $now->format('Y-m-d'))
                            ->where('start_time', '>', $oneHourLater->format('H:i:s'));
                    });
            });
        }

        $classes = $query->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($request->input('per_page', 20));

        // Enhance with alert status
        $enhancedClasses = $classes->getCollection()->map(function ($class) use ($now) {
            $classDateTime = Carbon::parse($class->class_date->format('Y-m-d') . ' ' . $class->start_time->format('H:i:s'));
            $minutesUntilStart = $now->diffInMinutes($classDateTime, false);

            $alertStatus = 'upcoming';
            $elapsedMinutes = null;
            
            // Classes starting in 5 minutes to 1 hour
            if ($minutesUntilStart <= 60 && $minutesUntilStart >= 5) {
                $alertStatus = 'starting_soon';
            } elseif ($minutesUntilStart < 0 && !$class->meet_link_used) {
                $elapsedMinutes = abs($minutesUntilStart);
                // Only mark as "no_teacher" if elapsed time is within 20 minutes
                if ($elapsedMinutes <= 20) {
                    $alertStatus = 'no_teacher';
                }
            }

            return [
                ...$class->toArray(),
                'alert_status' => $alertStatus,
                'minutes_until_start' => $minutesUntilStart,
                'elapsed_time_minutes' => $elapsedMinutes,
                'teacher_whatsapp' => $class->teacher->user->whatsapp ?? null,
                'student_whatsapp' => $class->student->whatsapp ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $enhancedClasses,
            'meta' => [
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
            ],
        ]);
    }

    /**
     * Get pending bills (finished packages)
     */
    public function pendingBills(Request $request): JsonResponse
    {
        $filters = [
            'notification_status' => $request->input('notification_status', 'unnotified'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $perPage = $request->input('per_page', 20);

        $query = Package::with('student')
            ->where('status', 'finished');

        // Filter by notification status
        if ($filters['notification_status'] === 'unnotified') {
            $query->where(function ($q) {
                $q->whereNull('last_notification_sent')
                    ->orWhereColumn('last_notification_sent', '<', 'updated_at');
            });
        } elseif ($filters['notification_status'] === 'notified') {
            $query->whereNotNull('last_notification_sent')
                ->whereColumn('last_notification_sent', '>=', 'updated_at');
        }

        // Filter by date range
        if ($filters['date_from']) {
            $query->where('updated_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->where('updated_at', '<=', $filters['date_to']);
        }

        $packages = $query->orderBy('updated_at', 'desc')
            ->paginate($perPage);

        // Enhance with bills summary
        $enhancedPackages = $packages->getCollection()->map(function ($package) {
            $billsSummary = $this->packageService->getBillsSummary($package->id);

            return [
                ...$package->toArray(),
                'bills_summary' => $billsSummary,
                'completion_date' => $package->updated_at->format('Y-m-d H:i:s'),
                'student_whatsapp' => $package->student->whatsapp ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $enhancedPackages,
            'meta' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'per_page' => $packages->perPage(),
                'total' => $packages->total(),
            ],
        ]);
    }

    /**
     * Send a test notification (for development/testing)
     */
    public function testNotification(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'support') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$user->fcm_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'FCM token not registered. Please login again.',
            ], 400);
        }

        $firebaseService = app(FirebaseService::class);
        
        // Example notification with class details for TTS testing
        $now = Carbon::now();
        $formattedTime = $now->format('h:i A');
        
        $result = $firebaseService->sendNotification(
            $user->fcm_token,
            '⏰ Test Class Alert',
            'This is a test notification with spoken details',
            [
                'type' => 'class_starting',
                'student_name' => 'Ahmed Al-Saud',
                'teacher_name' => 'John Doe',
                'course_name' => 'English',
                'class_time' => $formattedTime,
                'timestamp' => (string)now()->timestamp,
            ],
            'high'
        );

        if ($result) {
            return response()->json([
                'status' => 'success',
                'message' => 'Test notification sent successfully',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to send notification. Check logs for details.',
        ], 500);
    }

    /**
     * Register FCM device token
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->input('fcm_token');
        $user->fcm_token_updated_at = now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Device token registered successfully',
        ]);
    }
}
