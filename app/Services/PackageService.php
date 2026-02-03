<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Student;
use App\Models\Bill;
use App\Models\ClassInstance;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

            // Mark any existing active packages as finished (pending payment)
            // Only update packages that are currently active (not 'finished' or 'paid')
            $activePackages = Package::where('student_id', $studentId)
                ->where('status', 'active')
                ->get();

            foreach ($activePackages as $oldPackage) {
                // Mark as finished (pending payment) - these are active packages being replaced
                $oldPackage->status = 'finished';
                $oldPackage->remaining_hours = 0;
                $oldPackage->remaining_classes = 0;
                // Update timestamp so it appears in notifications
                $oldPackage->updated_at = now();
                $oldPackage->save();
                
                // Send automatic bill notification
                $this->sendAutomaticBillNotification($oldPackage);
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

            // Process waiting list classes for this student
            $this->processWaitingListForStudent($studentId, $package);

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
    public function deductClass(int $packageId, float $durationHours = 1.0, bool $sendNotification = true): bool
    {
        $package = Package::findOrFail($packageId);
        $wasActive = $package->status === 'active';

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

        $saved = $package->save();
        
        // If package was just marked as finished, send automatic notification (unless disabled)
        if ($saved && $wasActive && $package->status === 'finished' && $sendNotification) {
            $this->sendAutomaticBillNotification($package);
        }

        return $saved;
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

            // Process waiting list classes for this student
            $this->processWaitingListForStudent($package->student_id, $package);

            DB::commit();
            return $package->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process waiting list classes for a student when a new package is activated
     * Assigns classes to the package and deducts hours until package limit is reached
     */
    public function processWaitingListForStudent(int $studentId, Package $package): int
    {
        $processed = 0;
        
        // Get waiting list classes for this student, ordered by date (oldest first)
        $waitingListClasses = ClassInstance::where('student_id', $studentId)
            ->where('status', 'waiting_list')
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        foreach ($waitingListClasses as $waitingClass) {
            $durationHours = $waitingClass->duration / 60.0;
            
            // Check if we can add this class to the package
            if ($this->canAddClassToPackage($package->id, $durationHours)) {
                // Assign class to package
                $waitingClass->package_id = $package->id;
                
                // Restore original status (attended, cancelled_by_student, or absent_student)
                // Since waiting_list is a temporary status, we need to determine what the original should be
                // For simplicity, we'll mark it as 'attended' since it was waiting to be counted
                $waitingClass->status = 'attended';
                $waitingClass->save();
                
                // Deduct from package
                $this->deductClass($package->id, $durationHours);
                
                // Refresh package to get updated remaining_hours
                $package->refresh();
                $processed++;
            } else {
                // Not enough hours, stop processing
                break;
            }
        }

        return $processed;
    }

    /**
     * Get all packages for a student with classes grouped by rounds
     * Classes are distributed to packages based on chronological order and package hour limits
     * First classes fill Round 1 until its hour limit, then Round 2, etc.
     * 
     * IMPORTANT: Paid packages have their class assignments FROZEN - they are not redistributed
     * 
     * Display order:
     * 1. Active packages (current, at the top)
     * 2. Finished packages (pending payment)
     * 3. Paid packages (collapsed at the bottom)
     */
    public function getStudentPackagesWithClassesByRounds(int $studentId): array
    {
        // Get all packages for the student, sorted by round_number ASC for class distribution
        // Classes should fill Round 1 first, then Round 2, etc.
        $allPackages = Package::where('student_id', $studentId)
            ->orderBy('round_number', 'asc')
            ->get();

        if ($allPackages->isEmpty()) {
            return [];
        }

        // Separate paid packages from active/finished packages
        // Paid packages have their class assignments frozen
        $paidPackages = $allPackages->where('status', 'paid');
        $activeAndFinishedPackages = $allPackages->whereIn('status', ['active', 'finished']);

        // Get all classes that are already assigned to PAID packages
        // These assignments are FROZEN and should not be redistributed
        $paidPackageClassIds = [];
        if ($paidPackages->isNotEmpty()) {
            $paidPackageClassIds = ClassInstance::where('student_id', $studentId)
                ->whereIn('package_id', $paidPackages->pluck('id'))
                ->whereIn('status', ['attended', 'cancelled_by_student', 'cancelled_by_teacher'])
                ->pluck('id')
                ->toArray();
        }

        // Get ALL completed classes for the student, ordered by date (oldest first)
        // Include cancelled_by_teacher but they won't count towards package limits
        $allClasses = ClassInstance::where('student_id', $studentId)
            ->whereIn('status', ['attended', 'cancelled_by_student', 'cancelled_by_teacher'])
            ->with(['course', 'teacher.user', 'student'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        
        // Get waiting list classes (classes waiting for new package/reactivation)
        $waitingListClasses = ClassInstance::where('student_id', $studentId)
            ->where('status', 'waiting_list')
            ->with(['course', 'teacher.user', 'student'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $distributedResults = [];
        $globalCumulativeHours = 0; // Track cumulative hours across all packages
        
        // Use transaction to ensure atomic updates of package_id
        DB::beginTransaction();
        try {
            // First, handle PAID packages with frozen assignments
            foreach ($paidPackages as $package) {
                $packageHourLimit = (float) $package->total_hours;
                $packageCumulativeHours = 0;
                $classesWithCounters = [];

                // Get classes that are frozen to this paid package
                $packageClasses = $allClasses->filter(function ($class) use ($package, $paidPackageClassIds) {
                    return in_array($class->id, $paidPackageClassIds) && $class->package_id == $package->id;
                });

                foreach ($packageClasses as $class) {
                    $durationHours = $class->duration / 60.0;
                    $countsTowardsLimit = $class->status !== 'cancelled_by_teacher';

                    if ($countsTowardsLimit) {
                        $packageCumulativeHours += $durationHours;
                        $globalCumulativeHours += $durationHours;
                    }

                    $classesWithCounters[] = [
                        'class' => $class,
                        'duration_hours' => round($durationHours, 2),
                        'cumulative_hours' => round($packageCumulativeHours, 2),
                        'counter' => $countsTowardsLimit ? round($globalCumulativeHours, 2) : 0,
                        'counts_towards_limit' => $countsTowardsLimit,
                    ];
                }

                $distributedResults[$package->id] = [
                    'package' => [
                        'id' => $package->id,
                        'round_number' => $package->round_number,
                        'total_hours' => $package->total_hours,
                        'remaining_hours' => $package->remaining_hours,
                        'status' => $package->status,
                        'start_date' => $package->start_date ? $package->start_date->format('Y-m-d') : null,
                    ],
                    'classes' => $classesWithCounters,
                    'total_classes' => count($classesWithCounters),
                    'total_hours_used' => round($packageCumulativeHours, 2),
                ];
            }

            // Now distribute remaining classes (not assigned to paid packages) to active/finished packages
            $remainingClasses = $allClasses->filter(function ($class) use ($paidPackageClassIds) {
                return !in_array($class->id, $paidPackageClassIds);
            })->values();

            $classIndex = 0;
            $totalRemainingClasses = $remainingClasses->count();

            foreach ($activeAndFinishedPackages as $package) {
                $packageHourLimit = (float) $package->total_hours;
                $packageCumulativeHours = 0;
                $classesWithCounters = [];

                // Add classes to this package until we reach its hour limit
                while ($classIndex < $totalRemainingClasses) {
                    $class = $remainingClasses[$classIndex];
                    $durationHours = $class->duration / 60.0;
                    
                    // Classes cancelled by teacher don't count towards package limit
                    $countsTowardsLimit = $class->status !== 'cancelled_by_teacher';

                    // Check if adding this class would exceed the package limit
                    // Only check if this class counts towards the limit AND we already have classes in this package
                    if ($countsTowardsLimit && $packageCumulativeHours + $durationHours > $packageHourLimit && $packageCumulativeHours > 0) {
                        // This class would exceed the limit, move to next package
                        break;
                    }

                    // Only add to cumulative hours if it counts towards limit
                    if ($countsTowardsLimit) {
                        $packageCumulativeHours += $durationHours;
                        $globalCumulativeHours += $durationHours;
                    }

                    // Update the package_id in the database to match chronological distribution
                    if ($class->package_id != $package->id) {
                        $class->package_id = $package->id;
                        $class->save();
                    }

                    $classesWithCounters[] = [
                        'class' => $class->fresh(),
                        'duration_hours' => round($durationHours, 2),
                        'cumulative_hours' => round($packageCumulativeHours, 2),
                        'counter' => $countsTowardsLimit ? round($globalCumulativeHours, 2) : 0,
                        'counts_towards_limit' => $countsTowardsLimit,
                    ];

                    $classIndex++;
                }

                $distributedResults[$package->id] = [
                    'package' => [
                        'id' => $package->id,
                        'round_number' => $package->round_number,
                        'total_hours' => $package->total_hours,
                        'remaining_hours' => $package->remaining_hours,
                        'status' => $package->status,
                        'start_date' => $package->start_date ? $package->start_date->format('Y-m-d') : null,
                    ],
                    'classes' => $classesWithCounters,
                    'total_classes' => count($classesWithCounters),
                    'total_hours_used' => round($packageCumulativeHours, 2),
                ];
            }

            // If there are remaining classes after all packages are filled,
            // add them to an "Unassigned" section with counter = 0
            if ($classIndex < $totalRemainingClasses) {
                $unassignedClasses = [];
                $packageCumulativeHours = 0;

                while ($classIndex < $totalRemainingClasses) {
                    $class = $remainingClasses[$classIndex];
                    $durationHours = $class->duration / 60.0;
                    $packageCumulativeHours += $durationHours;
                    // Don't add to global cumulative - these are waiting for new package
                    // Counter stays at 0 until package is reactivated/created

                    // Remove package_id if class is unassigned
                    if ($class->package_id !== null) {
                        $class->package_id = null;
                        $class->save();
                    }

                    $unassignedClasses[] = [
                        'class' => $class->fresh(),
                        'duration_hours' => round($durationHours, 2),
                        'cumulative_hours' => round($packageCumulativeHours, 2),
                        'counter' => 0, // Counter is 0 until new package is created
                    ];

                    $classIndex++;
                }

                if (!empty($unassignedClasses)) {
                    $distributedResults['pending_package'] = [
                        'package' => [
                            'id' => 0,
                            'round_number' => $allPackages->count() + 1,
                            'total_hours' => 0,
                            'remaining_hours' => 0,
                            'status' => 'pending_package',
                            'start_date' => null,
                        ],
                        'classes' => $unassignedClasses,
                        'total_classes' => count($unassignedClasses),
                        'total_hours_used' => round($packageCumulativeHours, 2),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Add waiting list classes as a separate section
        if ($waitingListClasses->count() > 0) {
            $waitingListWithCounters = [];
            $waitingCumulativeHours = 0;

            foreach ($waitingListClasses as $class) {
                $durationHours = $class->duration / 60.0;
                $waitingCumulativeHours += $durationHours;

                $waitingListWithCounters[] = [
                    'class' => $class,
                    'duration_hours' => round($durationHours, 2),
                    'cumulative_hours' => round($waitingCumulativeHours, 2),
                    'counter' => 0, // Counter is 0 until package is reactivated
                    'counts_towards_limit' => true,
                ];
            }

            $distributedResults['waiting_list'] = [
                'package' => [
                    'id' => 0,
                    'round_number' => $allPackages->count() + 1,
                    'total_hours' => 0,
                    'remaining_hours' => 0,
                    'status' => 'waiting_for_reactivation',
                    'start_date' => null,
                ],
                'classes' => $waitingListWithCounters,
                'total_classes' => count($waitingListWithCounters),
                'total_hours_used' => round($waitingCumulativeHours, 2),
            ];
        }

        // Now reorder for display: Active first, then Finished (pending payment), then Paid
        $result = [];
        
        // 1. Active packages first
        foreach ($allPackages->where('status', 'active') as $pkg) {
            if (isset($distributedResults[$pkg->id])) {
                $result[] = $distributedResults[$pkg->id];
            }
        }
        
        // 2. Finished packages (pending payment)
        foreach ($allPackages->where('status', 'finished') as $pkg) {
            if (isset($distributedResults[$pkg->id])) {
                $result[] = $distributedResults[$pkg->id];
            }
        }
        
        // 3. Pending package (overflow classes)
        if (isset($distributedResults['pending_package'])) {
            $result[] = $distributedResults['pending_package'];
        }
        
        // 4. Waiting list classes
        if (isset($distributedResults['waiting_list'])) {
            $result[] = $distributedResults['waiting_list'];
        }
        
        // 5. Paid packages (collapsed at bottom)
        foreach ($allPackages->where('status', 'paid') as $pkg) {
            if (isset($distributedResults[$pkg->id])) {
                $result[] = $distributedResults[$pkg->id];
            }
        }

        return $result;
    }

    /**
     * Get package classes with cumulative hour counters
     * Shows classes that have been assigned to this package (status: attended, cancelled_by_student, absent_student)
     * Also shows pending classes for the same student to give a complete picture
     */
    public function getPackageClassesWithCounters(int $packageId): array
    {
        $package = Package::findOrFail($packageId);
        
        // Get classes that are directly linked to this package (completed classes)
        $packageClasses = $package->classes()
            ->whereIn('status', ['attended', 'cancelled_by_student', 'absent_student'])
            ->with(['course', 'teacher.user', 'student'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        
        // Also get pending classes for the same student (not yet assigned to package)
        // These show upcoming classes that will be assigned when completed
        $pendingClasses = ClassInstance::where('student_id', $package->student_id)
            ->where('status', 'pending')
            ->whereNull('package_id')
            ->where('class_date', '>=', $package->start_date)
            ->with(['course', 'teacher.user', 'student'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        
        // Combine both sets - completed first, then pending
        $allClasses = $packageClasses->merge($pendingClasses);
        
        $cumulativeHours = 0;
        $result = [];

        foreach ($allClasses as $class) {
            $durationHours = $class->duration / 60.0;
            
            // Only count hours for completed classes (not pending)
            if (in_array($class->status, ['attended', 'cancelled_by_student', 'absent_student'])) {
                $cumulativeHours += $durationHours;
            }
            
            $result[] = [
                'class' => $class,
                'duration_hours' => $durationHours,
                'cumulative_hours' => round($cumulativeHours, 2),
                'counter' => $cumulativeHours,
            ];
        }

        return $result;
    }

    /**
     * Get finished packages with student and bills summary
     * Only returns 'finished' packages (pending payment), excludes 'paid' packages
     */
    public function getFinishedPackages(array $filters = []): Collection
    {
        $query = Package::with(['student', 'classes.bill'])
            ->where('status', 'finished'); // Only 'finished' (pending payment)

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
     * Only returns 'finished' packages (pending payment), excludes 'paid' packages
     */
    public function getFinishedPackagesWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Package::with(['student.family'])
            ->distinct()
            ->where('status', 'finished'); // Only 'finished' (pending payment)

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
     * Total is calculated as: package total_hours * package hour_price
     * Unpaid is calculated as: total_amount - paid_amount (from bills with status 'paid')
     */
    public function getBillsSummary(int $packageId): array
    {
        $package = Package::findOrFail($packageId);

        // Calculate total amount: package total_hours * hour_price
        // This is the full package value regardless of actual hours used
        $totalHours = (float) $package->total_hours;
        $hourPrice = (float) $package->hour_price;
        $totalAmount = $totalHours * $hourPrice;

        // Get bills for this package
        $bills = Bill::whereHas('class', function ($query) use ($packageId) {
            $query->where('package_id', $packageId);
        })->get();

        // Calculate paid amount from bills with status 'paid'
        $paidAmount = $bills->where('status', 'paid')->sum('amount');
        
        // Unpaid amount = total amount - paid amount
        $unpaidAmount = $totalAmount - $paidAmount;
        
        // Ensure unpaid amount is not negative
        if ($unpaidAmount < 0) {
            $unpaidAmount = 0;
        }

        $billCount = $bills->count();

        return [
            'total_amount' => round($totalAmount, 2),
            'unpaid_amount' => round($unpaidAmount, 2),
            'bill_count' => $billCount,
            'currency' => $package->currency,
            'total_hours' => round($totalHours, 2),
            'hour_price' => $hourPrice,
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
        // When status is 'all', show ALL packages (active, finished, and paid)

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
     * Automatically send bill notification when package is finished
     */
    public function sendAutomaticBillNotification(Package $package): void
    {
        try {
            // Load student relationship if not already loaded
            if (!$package->relationLoaded('student')) {
                $package->load('student');
            }

            // Check if student has WhatsApp number
            if (!$package->student || !$package->student->whatsapp) {
                return;
            }

            // Get services
            $billingService = app(BillingService::class);
            $whatsappService = app(WhatsAppService::class);

            // Get all unpaid bills for this package
            $bills = Bill::whereHas('class', function ($query) use ($package) {
                $query->where('package_id', $package->id);
            })->whereIn('status', ['pending', 'sent'])->get();

            // Skip if no bills to send
            if ($bills->isEmpty()) {
                return;
            }

            // IMPORTANT: Generate payment tokens for all bills BEFORE formatting message
            // This ensures payment links are included in the WhatsApp message
            $billIds = $bills->pluck('id')->toArray();
            Log::info('Generating payment tokens for bills', ['bill_ids' => $billIds]);
            
            foreach ($bills as $bill) {
                if (!$bill->payment_token) {
                    $billingService->generatePaymentToken($bill->id);
                    Log::info('Generated payment token', [
                        'bill_id' => $bill->id,
                    ]);
                }
            }
            
            // Reload bills to ensure all tokens are loaded
            $bills = Bill::whereIn('id', $billIds)->get();
            
            Log::info('Bills reloaded with tokens', [
                'bills' => $bills->map(function($b) {
                    return [
                        'id' => $b->id,
                        'has_token' => !empty($b->payment_token),
                        'token' => $b->payment_token
                    ];
                })->toArray()
            ]);

            // Get bills summary
            $billsSummary = $this->getBillsSummary($package->id);

            // Get student language (default to Arabic)
            $studentLanguage = strtolower(trim($package->student->language ?? 'ar'));
            if (!in_array($studentLanguage, ['ar', 'en', 'fr'])) {
                $studentLanguage = 'ar';
            }

            // Format message with bill details and payment links
            $message = $billingService->formatPackageBillWhatsAppMessage(
                $package,
                $bills,
                $billsSummary,
                $studentLanguage
            );

            // Send WhatsApp message
            $success = $whatsappService->sendMessage(
                $package->student->whatsapp,
                $message,
                null, // templateId is null for custom message
                [], // params are empty for custom message
                $package->id // Pass package_id for logging
            );

            if ($success) {
                // Update notification timestamp
                $this->updateNotificationSent($package->id);

                // Update bills status to 'sent' and set sent_at
                foreach ($bills as $bill) {
                    $bill->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't throw - we don't want to break the package update process
            Log::error('Failed to send automatic bill notification', [
                'package_id' => $package->id,
                'error' => $e->getMessage(),
            ]);
        }
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
