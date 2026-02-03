<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\StorePackageRequest;
use App\Http\Requests\Package\UpdatePackageRequest;
use App\Models\Package;
use App\Models\Bill;
use App\Models\ClassInstance;
use App\Services\PackageService;
use App\Services\WhatsAppService;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    protected $packageService;
    protected $whatsappService;
    protected $billingService;

    public function __construct(
        PackageService $packageService,
        WhatsAppService $whatsappService, 
        BillingService $billingService
    ) {
        $this->packageService = $packageService;
        $this->whatsappService = $whatsappService;
        $this->billingService = $billingService;
    }

    /**
     * Display a listing of packages
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status', 'all'),
            'student_id' => $request->input('student_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $perPage = $request->input('per_page', 15);
        $packages = $this->packageService->searchPackages($filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $packages->items(),
            'meta' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'per_page' => $packages->perPage(),
                'total' => $packages->total(),
            ],
        ]);
    }

    /**
     * Store a newly created package
     */
    public function store(StorePackageRequest $request): JsonResponse
    {
        $package = $this->packageService->activateNewRound(
            $request->student_id,
            $request->validated()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Package created successfully',
            'data' => $package->load('student'),
        ], 201);
    }

    /**
     * Display the specified package
     */
    public function show(string $id): JsonResponse
    {
        $package = $this->packageService->getPackageWithBills($id);
        $billsSummary = $this->packageService->getBillsSummary($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'package' => $package,
                'bills_summary' => $billsSummary,
            ],
        ]);
    }

    /**
     * Update the specified package
     */
    public function update(UpdatePackageRequest $request, string $id): JsonResponse
    {
        $package = Package::findOrFail($id);
        $package->update($request->validated());

        // If remaining_classes is set and <= 0, mark as finished
        if (isset($request->remaining_classes) && $request->remaining_classes <= 0) {
            $package->status = 'finished';
            $package->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Package updated successfully',
            'data' => $package->fresh()->load('student'),
        ]);
    }

    /**
     * Remove the specified package
     */
    public function destroy(string $id): JsonResponse
    {
        $package = Package::findOrFail($id);
        $package->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Package deleted successfully',
        ]);
    }

    /**
     * Get finished packages (for notifications center)
     */
    public function finished(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'student_status' => $request->input('student_status', 'all'),
            'notification_status' => $request->input('notification_status', 'all'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'days_since_finished' => $request->input('days_since_finished'),
        ];

        $perPage = $request->input('per_page', 15);
        $packages = $this->packageService->getFinishedPackagesWithFilters($filters, $perPage);

        // Enhance with bills summary and completion date
        $enhancedPackages = $packages->getCollection()->map(function ($package) {
            $billsSummary = $this->packageService->getBillsSummary($package->id);
            
            return [
                ...$package->toArray(),
                'bills_summary' => $billsSummary,
                'completion_date' => $package->updated_at->format('Y-m-d'),
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
     * Send WhatsApp notification for finished package with bill and payment links
     */
    public function notify(Request $request, string $id): JsonResponse
    {
        $package = Package::with('student')->findOrFail($id);

        if (!$package->student->whatsapp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student does not have a WhatsApp number',
            ], 400);
        }

        try {
            // Get all unpaid bills for this package
            $bills = Bill::whereHas('class', function ($query) use ($id) {
                $query->where('package_id', $id);
            })->whereIn('status', ['pending', 'sent'])->get();

            // IMPORTANT: Generate payment tokens for all bills BEFORE formatting message
            // This ensures payment links are included in the WhatsApp message
            $billIds = $bills->pluck('id')->toArray();
            \Log::info('PackageController::notify - Generating payment tokens', ['bill_ids' => $billIds]);
            
            foreach ($bills as $bill) {
                if (!$bill->payment_token) {
                    $this->billingService->generatePaymentToken($bill->id);
                    \Log::info('Generated payment token', ['bill_id' => $bill->id]);
                }
            }
            
            // Reload bills to ensure all tokens are loaded
            $bills = Bill::whereIn('id', $billIds)->get();
            
            \Log::info('Bills reloaded', [
                'bills' => $bills->map(function($b) {
                    return ['id' => $b->id, 'has_token' => !empty($b->payment_token), 'token' => $b->payment_token];
                })->toArray()
            ]);

            // Get bills summary
            $billsSummary = $this->packageService->getBillsSummary($id);

            // Get student language (default to Arabic)
            $studentLanguage = strtolower(trim($package->student->language ?? 'ar'));
            if (!in_array($studentLanguage, ['ar', 'en', 'fr'])) {
                $studentLanguage = 'ar';
            }

            // Format message with bill details and payment links
            $message = $this->billingService->formatPackageBillWhatsAppMessage(
                $package,
                $bills,
                $billsSummary,
                $studentLanguage
            );

            // Send WhatsApp message
            $success = $this->whatsappService->sendMessage(
                $package->student->whatsapp,
                $message,
                null,
                [],
                $package->id
            );

            if ($success) {
                // Update notification timestamp
                $this->packageService->updateNotificationSent($package->id);

                // Update bills status to 'sent' and set sent_at
                foreach ($bills as $bill) {
                    $bill->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Notification sent successfully',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send notification',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send bulk WhatsApp notifications with bills and payment links
     */
    public function bulkNotify(Request $request): JsonResponse
    {
        $request->validate([
            'package_ids' => 'required|array',
            'package_ids.*' => 'exists:packages,id',
        ]);

        $packageIds = $request->input('package_ids');
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($packageIds as $packageId) {
            try {
                $package = Package::with('student')->findOrFail($packageId);

                if (!$package->student->whatsapp) {
                    $failedCount++;
                    $errors[] = "Package #{$packageId}: Student does not have WhatsApp number";
                    continue;
                }

                // Get all unpaid bills for this package
                $bills = Bill::whereHas('class', function ($query) use ($packageId) {
                    $query->where('package_id', $packageId);
                })->whereIn('status', ['pending', 'sent'])->get();

                // IMPORTANT: Generate payment tokens for all bills BEFORE formatting message
                // This ensures payment links are included in the WhatsApp message
                $billIds = $bills->pluck('id')->toArray();
                foreach ($bills as $bill) {
                    if (!$bill->payment_token) {
                        $this->billingService->generatePaymentToken($bill->id);
                    }
                }
                
                // Reload bills to ensure all tokens are loaded
                $bills = Bill::whereIn('id', $billIds)->get();

                // Get bills summary
                $billsSummary = $this->packageService->getBillsSummary($packageId);

                // Get student language (default to Arabic)
                $studentLanguage = strtolower(trim($package->student->language ?? 'ar'));
                if (!in_array($studentLanguage, ['ar', 'en', 'fr'])) {
                    $studentLanguage = 'ar';
                }

                // Format message with bill details and payment links
                $message = $this->billingService->formatPackageBillWhatsAppMessage(
                    $package,
                    $bills,
                    $billsSummary,
                    $studentLanguage
                );

                // Send WhatsApp message
                $success = $this->whatsappService->sendMessage(
                    $package->student->whatsapp,
                    $message,
                    null,
                    [],
                    $package->id
                );

                if ($success) {
                    $this->packageService->updateNotificationSent($package->id);
                    
                    // Update bills status to 'sent' and set sent_at
                    foreach ($bills as $bill) {
                        $bill->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]);
                    }
                    
                    $successCount++;
                } else {
                    $failedCount++;
                    $errors[] = "Package #{$packageId}: Failed to send notification";
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "Package #{$packageId}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Sent {$successCount} notifications, {$failedCount} failed",
            'data' => [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Get bills summary for a package
     */
    public function bills(string $id): JsonResponse
    {
        $billsSummary = $this->packageService->getBillsSummary($id);

        return response()->json([
            'status' => 'success',
            'data' => $billsSummary,
        ]);
    }

    /**
     * Get notification history for a package
     */
    public function notificationHistory(string $id): JsonResponse
    {
        $package = Package::findOrFail($id);

        $notifications = DB::table('whatsapp_logs')
            ->where('package_id', $id)
            ->where('status', 'sent')
            ->orderBy('sent_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'sent_at' => $log->sent_at,
                    'status' => $log->status,
                    'message_type' => $log->message_type,
                    'recipient' => $log->recipient,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $notifications,
        ]);
    }

    /**
     * Mark a package as paid.
     * 
     * SIMPLE Flow:
     * 1. Mark the package as 'paid' (freezes its classes)
     * 2. Mark all bills for classes in this package as paid
     * 
     * NOTE: New packages are created automatically when needed (when a class is attended
     * and no active package exists). No need to create one here.
     */
    public function markAsPaid(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $package = Package::findOrFail($id);
            
            // Get all bills for classes in this package
            $bills = Bill::whereHas('class', function ($query) use ($id) {
                $query->where('package_id', $id);
            })->get();

            // Mark all bills as paid
            foreach ($bills as $bill) {
                $bill->status = 'paid';
                $bill->payment_date = now();
                $bill->save();
            }

            // Mark the package as 'paid' (classes assignments frozen)
            $package->status = 'paid';
            $package->last_notification_sent = now();
            $package->notification_count = ($package->notification_count ?? 0) + 1;
            $package->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Package marked as paid successfully',
                'data' => [
                    'package' => $package->fresh()->load('student'),
                    'bills_updated' => $bills->count(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download package PDF (placeholder - PDF generation will be implemented later)
     */
    public function downloadPdf(string $id): JsonResponse
    {
        $package = Package::with(['student', 'classes'])->findOrFail($id);
        $billsSummary = $this->packageService->getBillsSummary($id);

        // TODO: Implement PDF generation using a library like DomPDF or Snappy
        // For now, return JSON data that can be used to generate PDF on frontend

        return response()->json([
            'status' => 'success',
            'message' => 'PDF generation not yet implemented',
            'data' => [
                'package' => $package,
                'bills_summary' => $billsSummary,
            ],
        ]);
    }

    /**
     * Reactivate a finished package
     */
    public function reactivate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'total_hours' => 'nullable|numeric|min:0',
            'total_classes' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'hour_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string',
        ]);

        try {
            $package = $this->packageService->reactivatePackage($id, $request->only([
                'total_hours',
                'total_classes',
                'start_date',
                'hour_price',
                'currency',
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Package reactivated successfully',
                'data' => $package->load('student'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get classes for a specific package
     * Returns ONLY classes assigned to THIS package (no redistribution)
     */
    public function getPackageClasses(string $id): JsonResponse
    {
        $package = Package::with('student')->findOrFail($id);
        
        // Get ONLY classes for THIS package
        // All classes (attended, cancelled_by_student, cancelled_by_teacher, absent_student) 
        // should be assigned to a package and will show here
        $classes = ClassInstance::where('package_id', $id)
            ->with(['teacher.user', 'course', 'bill'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        
        // Calculate totals
        $totalHours = 0;
        $cumulativeHours = 0;
        
        $classesWithCounters = $classes->map(function ($class) use (&$cumulativeHours) {
            $durationHours = $class->duration / 60.0;
            $countsTowardsLimit = $class->status !== 'cancelled_by_teacher';
            
            if ($countsTowardsLimit) {
                $cumulativeHours += $durationHours;
            }
            
            return [
                'class' => $class,
                'duration_hours' => round($durationHours, 2),
                'cumulative_hours' => round($cumulativeHours, 2),
                'counter' => $countsTowardsLimit ? round($cumulativeHours, 2) : 0,
                'counts_towards_limit' => $countsTowardsLimit,
            ];
        });
        
        $totalHours = $cumulativeHours;

        // Return as array to match frontend expectations (rounds array)
        // Each package is one "round" - just return this single package as an array
        $roundData = [
            'package' => [
                'id' => $package->id,
                'round_number' => $package->round_number,
                'total_hours' => $package->total_hours,
                'remaining_hours' => $package->remaining_hours,
                'status' => $package->status,
                'start_date' => $package->start_date ? $package->start_date->format('Y-m-d') : null,
            ],
            'classes' => $classesWithCounters->values()->all(),
            'total_classes' => $classes->count(),
            'total_hours_used' => round($totalHours, 2),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [$roundData], // Return as array to match frontend expectations
        ]);
    }

    /**
     * Get count of finished packages without notifications
     * Only counts 'finished' status (pending payment), excludes 'paid' packages
     */
    public function getUnnotifiedCount(): JsonResponse
    {
        // Count all finished packages (pending payment) regardless of notification status
        // Badge should persist until package is marked as paid
        $count = Package::where('status', 'finished') // Only 'finished' (pending payment, not yet paid)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'count' => $count,
            ],
        ]);
    }
}
