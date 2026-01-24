<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\StorePackageRequest;
use App\Http\Requests\Package\UpdatePackageRequest;
use App\Models\Package;
use App\Services\PackageService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    protected $packageService;
    protected $whatsappService;

    public function __construct(PackageService $packageService, WhatsAppService $whatsappService)
    {
        $this->packageService = $packageService;
        $this->whatsappService = $whatsappService;
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
     * Send WhatsApp notification for finished package
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

        // Generate payment link (placeholder - will be implemented in Phase 8)
        $paymentLink = url("/external/payment/token-placeholder");

        // Send WhatsApp notification
        $variables = [
            'link' => $paymentLink,
        ];

        $success = $this->whatsappService->sendTemplateMessage(
            $package->student->whatsapp,
            'package_finished',
            $variables
        );

        if ($success) {
            // Update notification timestamp
            $this->packageService->updateNotificationSent($package->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification sent successfully',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to send notification',
        ], 500);
    }

    /**
     * Send bulk WhatsApp notifications
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

                // Generate payment link (placeholder)
                $paymentLink = url("/external/payment/token-placeholder");

                $success = $this->whatsappService->sendTemplateMessage(
                    $package->student->whatsapp,
                    'package_finished',
                    ['link' => $paymentLink]
                );

                if ($success) {
                    $this->packageService->updateNotificationSent($package->id);
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
     * Get package classes with cumulative hour counters
     */
    public function getPackageClasses(string $id): JsonResponse
    {
        $classes = $this->packageService->getPackageClassesWithCounters($id);

        return response()->json([
            'status' => 'success',
            'data' => $classes,
        ]);
    }

    /**
     * Get count of finished packages without notifications
     */
    public function getUnnotifiedCount(): JsonResponse
    {
        $count = Package::where(function ($q) {
                $q->where('status', 'finished')
                  ->orWhere(function ($q2) {
                      $q2->where('remaining_hours', '<=', 0)
                         ->orWhere(function ($q3) {
                             $q3->whereNull('remaining_hours')
                                ->where('remaining_classes', '<=', 0);
                         });
                  });
            })
            ->whereNull('last_notification_sent')
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'count' => $count,
            ],
        ]);
    }
}
