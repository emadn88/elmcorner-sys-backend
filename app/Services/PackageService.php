<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Student;
use App\Models\Bill;
use App\Models\ClassInstance;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PackageService
{
    /**
     * Activate a new round for a student
     * Increments round_number automatically
     * Marks any existing active packages as finished
     */
    public function activateNewRound(int $studentId, array $packageData): Package
    {
        DB::beginTransaction();
        try {
            // Get the last round number for this student
            $lastRound = Package::where('student_id', $studentId)
                ->max('round_number') ?? 0;

            // Package is calculated by total_hours, not total_classes
            // If total_hours is not provided, throw error
            if (empty($packageData['total_hours'])) {
                throw new \Exception('total_hours is required for package creation');
            }

            $totalHours = $packageData['total_hours'];

            // Mark any existing active packages as finished
            // Only update packages that are currently active (not already finished)
            $activePackages = Package::where('student_id', $studentId)
                ->where('status', 'active')
                ->get();

            foreach ($activePackages as $oldPackage) {
                // Mark as finished - these are active packages being replaced
                $oldPackage->status = 'finished';
                $oldPackage->remaining_hours = 0;
                $oldPackage->remaining_classes = 0;
                // Update timestamp so it appears in notifications
                $oldPackage->updated_at = now();
                $oldPackage->save();
            }

            // Create new package with incremented round number
            $package = Package::create([
                'student_id' => $studentId,
                'start_date' => $packageData['start_date'],
                'total_classes' => 0, // Not used, kept for backward compatibility
                'remaining_classes' => 0, // Not used, kept for backward compatibility
                'total_hours' => $totalHours,
                'remaining_hours' => $totalHours,
                'hour_price' => $packageData['hour_price'],
                'currency' => $packageData['currency'] ?? 'USD',
                'round_number' => $lastRound + 1,
                'status' => 'active',
            ]);

            DB::commit();
            return $package;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Deduct a class from package based on duration (hours)
     * Updates status to 'finished' if remaining_hours <= 0
     */
    public function deductClass(int $packageId, float $durationHours = 1.0): bool
    {
        $package = Package::findOrFail($packageId);

        // Check if package is finished
        if ($package->status === 'finished' || 
            ($package->remaining_hours !== null && $package->remaining_hours <= 0) ||
            ($package->remaining_hours === null && $package->remaining_classes <= 0)) {
            return false;
        }

        // Deduct hours if package uses hours-based tracking
        if ($package->remaining_hours !== null) {
            $package->remaining_hours = max(0, $package->remaining_hours - $durationHours);
            
            // If no hours remaining, mark as finished
            if ($package->remaining_hours <= 0) {
                $package->status = 'finished';
            }
        }

        // Also deduct classes for backward compatibility
        if ($package->remaining_classes > 0) {
            $package->remaining_classes -= 1;
            
            // If no classes remaining and no hours tracking, mark as finished
            if ($package->remaining_classes <= 0 && $package->remaining_hours === null) {
                $package->status = 'finished';
            }
        }

        return $package->save();
    }

    /**
     * Check if a class can be added to package or should go to waiting list
     * Returns true if can be added, false if should go to waiting list
     */
    public function canAddClassToPackage(int $packageId, float $durationHours): bool
    {
        $package = Package::findOrFail($packageId);

        // If package is finished, class should go to waiting list
        if ($package->status === 'finished') {
            return false;
        }

        // Check hours-based limit
        if ($package->remaining_hours !== null) {
            return $package->remaining_hours >= $durationHours;
        }

        // Check classes-based limit
        return $package->remaining_classes > 0;
    }

    /**
     * Add class to package or waiting list
     * Returns the class instance with updated status
     */
    public function addClassToPackage(ClassInstance $class): ClassInstance
    {
        if (!$class->package_id) {
            return $class;
        }

        $package = Package::findOrFail($class->package_id);
        $durationHours = $class->duration / 60.0; // Convert minutes to hours

        // Check if class can be added to package
        if ($this->canAddClassToPackage($package->id, $durationHours)) {
            // Add to package normally
            $class->status = 'pending';
        } else {
            // Add to waiting list
            $class->status = 'waiting_list';
        }

        $class->save();
        return $class;
    }

    /**
     * Reactivate a finished package and process waiting list
     */
    public function reactivatePackage(int $packageId, array $packageData = []): Package
    {
        $package = Package::findOrFail($packageId);

        if ($package->status !== 'finished') {
            throw new \Exception('Package is not finished and cannot be reactivated');
        }

        DB::beginTransaction();
        try {
            // Update package with new data if provided
            if (!empty($packageData)) {
                // Package is calculated by total_hours
                $totalHours = $packageData['total_hours'] ?? $package->total_hours;
                
                if (empty($totalHours)) {
                    throw new \Exception('total_hours is required for package reactivation');
                }
                
                $package->update([
                    'start_date' => $packageData['start_date'] ?? $package->start_date,
                    'total_hours' => $totalHours,
                    'remaining_hours' => $totalHours,
                    'hour_price' => $packageData['hour_price'] ?? $package->hour_price,
                    'currency' => $packageData['currency'] ?? $package->currency,
                    'status' => 'active',
                ]);
            } else {
                // Just reactivate with existing limits (must have total_hours)
                if (empty($package->total_hours)) {
                    throw new \Exception('Package must have total_hours to reactivate');
                }
                
                $package->status = 'active';
                $package->remaining_hours = $package->total_hours;
                if ($package->remaining_classes !== null) {
                    $package->remaining_classes = $package->total_classes;
                }
                $package->save();
            }

            // Process waiting list classes
            $waitingListClasses = ClassInstance::where('package_id', $packageId)
                ->where('status', 'waiting_list')
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($waitingListClasses as $waitingClass) {
                $durationHours = $waitingClass->duration / 60.0;
                
                // Check if we can add this class to the reactivated package
                if ($this->canAddClassToPackage($package->id, $durationHours)) {
                    // Add class to package and deduct hours
                    $waitingClass->status = 'pending';
                    $waitingClass->save();
                    
                    // Deduct from package
                    $this->deductClass($package->id, $durationHours);
                    
                    // Refresh package to get updated remaining_hours
                    $package->refresh();
                } else {
                    // Not enough hours, keep in waiting list
                    break;
                }
            }

            DB::commit();
            return $package->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get package classes with cumulative hour counters
     * Only returns finished classes (attended, cancelled_by_student, absent_student)
     */
    public function getPackageClassesWithCounters(int $packageId): array
    {
        $package = Package::with(['classes' => function ($query) {
            $query->whereIn('status', ['attended', 'cancelled_by_student', 'absent_student'])
                  ->orderBy('class_date', 'asc')
                  ->orderBy('start_time', 'asc');
        }])->findOrFail($packageId);

        $classes = $package->classes;
        $cumulativeHours = 0;
        $result = [];

        foreach ($classes as $class) {
            $durationHours = $class->duration / 60.0;
            $cumulativeHours += $durationHours;
            
            $result[] = [
                'class' => $class,
                'duration_hours' => $durationHours,
                'cumulative_hours' => round($cumulativeHours, 2),
                'counter' => $cumulativeHours, // This is the counter (e.g., 1, 2.5, etc.)
            ];
        }

        return $result;
    }

    /**
     * Get finished packages with student and bills summary
     */
    public function getFinishedPackages(array $filters = []): Collection
    {
        $query = Package::with(['student', 'classes.bill'])
            ->where(function ($q) {
                $q->where('status', 'finished')
                  ->orWhere(function ($q2) {
                      $q2->where('remaining_hours', '<=', 0)
                         ->orWhere(function ($q3) {
                             $q3->whereNull('remaining_hours')
                                ->where('remaining_classes', '<=', 0);
                         });
                  });
            });

        // Apply filters
        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('updated_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('updated_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['days_since_finished'])) {
            $query->where('updated_at', '>=', now()->subDays($filters['days_since_finished']));
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get finished packages with filters and pagination
     */
    public function getFinishedPackagesWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Package::with(['student.family'])
            ->distinct()
            ->where(function ($q) {
                $q->where('status', 'finished')
                  ->orWhere(function ($q2) {
                      $q2->where('remaining_hours', '<=', 0)
                         ->orWhere(function ($q3) {
                             $q3->whereNull('remaining_hours')
                                ->where('remaining_classes', '<=', 0);
                         });
                  });
            });

        // Filter by student status
        if (!empty($filters['student_status']) && $filters['student_status'] !== 'all') {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('status', $filters['student_status']);
            });
        }

        // Filter by notification status
        if (!empty($filters['notification_status']) && $filters['notification_status'] !== 'all') {
            if ($filters['notification_status'] === 'sent') {
                $query->whereNotNull('last_notification_sent');
            } elseif ($filters['notification_status'] === 'not_sent') {
                $query->whereNull('last_notification_sent');
            }
        }

        // Filter by date range
        if (!empty($filters['date_from'])) {
            $query->where('updated_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('updated_at', '<=', $filters['date_to']);
        }

        // Filter by days since finished
        if (!empty($filters['days_since_finished'])) {
            $query->where('updated_at', '>=', now()->subDays($filters['days_since_finished']));
        }

        // Search by student name
        if (!empty($filters['search'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('full_name', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('updated_at', 'desc')->paginate($perPage);
    }

    /**
     * Get package with bills summary
     */
    public function getPackageWithBills(int $packageId): Package
    {
        $package = Package::with(['student', 'classes.bill'])->findOrFail($packageId);

        return $package;
    }

    /**
     * Get bills summary for a package
     */
    public function getBillsSummary(int $packageId): array
    {
        $package = Package::findOrFail($packageId);

        // Get all bills for classes in this package
        $bills = Bill::whereHas('class', function ($query) use ($packageId) {
            $query->where('package_id', $packageId);
        })->get();

        $totalAmount = $bills->sum('amount');
        $unpaidAmount = $bills->where('status', '!=', 'paid')->sum('amount');
        $billCount = $bills->count();

        return [
            'total_amount' => (float) $totalAmount,
            'unpaid_amount' => (float) $unpaidAmount,
            'bill_count' => $billCount,
            'currency' => $package->currency,
        ];
    }

    /**
     * Search and filter packages
     */
    public function searchPackages(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Package::with(['student']);

        // Filter by status
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Filter by student
        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        // Search by student name
        if (!empty($filters['search'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('full_name', 'like', "%{$filters['search']}%");
            });
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $query->where('start_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('start_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Update notification timestamp
     */
    public function updateNotificationSent(int $packageId): bool
    {
        $package = Package::findOrFail($packageId);
        
        $package->last_notification_sent = now();
        $package->notification_count = ($package->notification_count ?? 0) + 1;

        return $package->save();
    }
}
