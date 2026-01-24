<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassInstance;
use App\Services\TeacherClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $classService;

    public function __construct(TeacherClassService $classService)
    {
        $this->classService = $classService;
    }

    /**
     * Get all notifications (packages and class cancellations)
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all'); // 'all', 'packages', 'class_cancellations'

        $notifications = [];

        // Get package notifications (finished packages without notification sent)
        if ($type === 'all' || $type === 'packages') {
            $packages = \App\Models\Package::with(['student.family'])
                ->where(function ($q) {
                    $q->where('status', 'finished')
                      ->orWhere(function ($q2) {
                          $q2->where('remaining_hours', '<=', 0)
                             ->orWhere(function ($q3) {
                                 $q3->whereNull('remaining_hours')
                                    ->where('remaining_classes', '<=', 0);
                             });
                      });
                })
                ->get()
                ->map(function ($package) {
                    return [
                        'id' => $package->id,
                        'type' => 'package',
                        'student_name' => $package->student->full_name ?? 'N/A',
                        'student_id' => $package->student_id,
                        'package_id' => $package->id,
                        'completion_date' => $package->updated_at->format('Y-m-d'),
                        'notification_sent' => !is_null($package->last_notification_sent),
                        'notification_count' => $package->notification_count ?? 0,
                        'created_at' => $package->created_at,
                        'updated_at' => $package->updated_at,
                    ];
                });

            $notifications = array_merge($notifications, $packages->toArray());
        }

        // Get class cancellation requests
        if ($type === 'all' || $type === 'class_cancellations') {
            $cancellations = $this->classService->getCancellationRequests()
                ->map(function ($class) {
                    return [
                        'id' => $class->id,
                        'type' => 'class_cancellation',
                        'class_id' => $class->id,
                        'student_name' => $class->student->full_name ?? 'N/A',
                        'student_id' => $class->student_id,
                        'teacher_name' => $class->teacher->user->name ?? 'N/A',
                        'teacher_id' => $class->teacher_id,
                        'course_name' => $class->course->name ?? 'N/A',
                        'class_date' => $class->class_date->format('Y-m-d'),
                        'start_time' => $class->start_time,
                        'cancellation_reason' => $class->cancellation_reason,
                        'status' => $class->cancellation_request_status,
                        'created_at' => $class->created_at,
                        'updated_at' => $class->updated_at,
                    ];
                });

            $notifications = array_merge($notifications, $cancellations->toArray());
        }

        // Sort by created_at descending
        usort($notifications, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $notifications,
        ]);
    }

    /**
     * Approve class cancellation request
     */
    public function approveCancellation(int $id): JsonResponse
    {
        $class = ClassInstance::findOrFail($id);

        if ($class->cancellation_request_status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'This cancellation request is not pending',
            ], 400);
        }

        $this->classService->approveCancellation($class);

        return response()->json([
            'status' => 'success',
            'message' => 'Cancellation request approved',
            'data' => $class->fresh()->load(['student', 'teacher.user', 'course']),
        ]);
    }

    /**
     * Reject class cancellation request
     */
    public function rejectCancellation(int $id): JsonResponse
    {
        $class = ClassInstance::findOrFail($id);

        if ($class->cancellation_request_status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'This cancellation request is not pending',
            ], 400);
        }

        $this->classService->rejectCancellation($class);

        return response()->json([
            'status' => 'success',
            'message' => 'Cancellation request rejected',
            'data' => $class->fresh()->load(['student', 'teacher.user', 'course']),
        ]);
    }
}
