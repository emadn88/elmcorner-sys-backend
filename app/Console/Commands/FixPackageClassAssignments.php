<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Models\ClassInstance;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPackageClassAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:fix-assignments {--student_id= : Fix only for specific student ID} {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix package class assignments by redistributing classes chronologically (respects frozen paid packages)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $studentId = $this->option('student_id');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get students to fix
        $studentsQuery = Student::query();
        if ($studentId) {
            $studentsQuery->where('id', $studentId);
        }
        
        // Only get students who have packages
        $students = $studentsQuery->whereHas('packages')->get();

        if ($students->isEmpty()) {
            $this->error('No students found with packages.');
            return 1;
        }

        $this->info("Found {$students->count()} student(s) to process");
        $this->newLine();

        $totalFixed = 0;
        $totalErrors = 0;

        foreach ($students as $student) {
            try {
                $fixed = $this->fixStudentPackageAssignments($student, $dryRun);
                if ($fixed > 0) {
                    $totalFixed += $fixed;
                    $this->info("✓ Fixed {$fixed} class assignment(s) for {$student->full_name} (ID: {$student->id})");
                }
            } catch (\Exception $e) {
                $totalErrors++;
                $this->error("✗ Error fixing {$student->full_name} (ID: {$student->id}): " . $e->getMessage());
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("📊 Dry Run Summary: Would fix {$totalFixed} class assignment(s) across {$students->count()} student(s)");
            if ($totalErrors > 0) {
                $this->warn("⚠️  {$totalErrors} error(s) encountered");
            }
            $this->newLine();
            $this->warn('Run without --dry-run to apply changes');
        } else {
            $this->info("✅ Fixed {$totalFixed} class assignment(s) across {$students->count()} student(s)");
            if ($totalErrors > 0) {
                $this->warn("⚠️  {$totalErrors} error(s) encountered");
            }
        }

        return 0;
    }

    /**
     * Fix package assignments for a single student
     * Returns number of classes that were reassigned
     */
    protected function fixStudentPackageAssignments(Student $student, bool $dryRun = false): int
    {
        // Get all packages for this student, sorted by round number
        $allPackages = Package::where('student_id', $student->id)
            ->orderBy('round_number', 'asc')
            ->get();

        if ($allPackages->isEmpty()) {
            return 0;
        }

        // Separate paid packages (frozen) from others
        $paidPackages = $allPackages->where('status', 'paid');
        $activeAndFinishedPackages = $allPackages->whereIn('status', ['active', 'finished']);

        // Get all completed classes, chronologically
        $allClasses = ClassInstance::where('student_id', $student->id)
            ->whereIn('status', ['attended', 'cancelled_by_student', 'cancelled_by_teacher'])
            ->orderBy('class_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        if ($allClasses->isEmpty()) {
            return 0;
        }

        $changesCount = 0;
        $classIndex = 0;
        $globalCumulativeHours = 0;

        DB::beginTransaction();
        try {
            // First, process PAID packages (verify/fix their assignments)
            foreach ($paidPackages as $package) {
                $packageHourLimit = (float) $package->total_hours;
                $packageCumulativeHours = 0;

                $this->line("  Processing PAID Package Round {$package->round_number} (Limit: {$packageHourLimit}h)");

                // Assign classes to this paid package chronologically until limit reached
                while ($classIndex < $allClasses->count()) {
                    $class = $allClasses[$classIndex];
                    $durationHours = $class->duration / 60.0;
                    
                    // Classes cancelled by teacher don't count towards limit
                    $countsTowardsLimit = $class->status !== 'cancelled_by_teacher';

                    // Check if this class would exceed the package limit
                    if ($countsTowardsLimit && 
                        $packageCumulativeHours + $durationHours > $packageHourLimit && 
                        $packageCumulativeHours > 0) {
                        // Move to next package
                        break;
                    }

                    // Update cumulative hours
                    if ($countsTowardsLimit) {
                        $packageCumulativeHours += $durationHours;
                        $globalCumulativeHours += $durationHours;
                    }

                    // Fix assignment if needed
                    if ($class->package_id != $package->id) {
                        $oldPackageId = $class->package_id ?? 'NULL';
                        $this->line("    - Class #{$class->id} ({$class->class_date}): {$oldPackageId} → {$package->id}");
                        
                        if (!$dryRun) {
                            $class->package_id = $package->id;
                            $class->save();
                        }
                        $changesCount++;
                    }

                    $classIndex++;
                }

                $this->line("    Used {$packageCumulativeHours}h of {$packageHourLimit}h");
            }

            // Now process ACTIVE/FINISHED packages (redistribute remaining classes)
            foreach ($activeAndFinishedPackages as $package) {
                $packageHourLimit = (float) $package->total_hours;
                $packageCumulativeHours = 0;

                $statusLabel = strtoupper($package->status);
                $this->line("  Processing {$statusLabel} Package Round {$package->round_number} (Limit: {$packageHourLimit}h)");

                // Assign classes to this package chronologically until limit reached
                while ($classIndex < $allClasses->count()) {
                    $class = $allClasses[$classIndex];
                    $durationHours = $class->duration / 60.0;
                    
                    // Classes cancelled by teacher don't count towards limit
                    $countsTowardsLimit = $class->status !== 'cancelled_by_teacher';

                    // Check if this class would exceed the package limit
                    if ($countsTowardsLimit && 
                        $packageCumulativeHours + $durationHours > $packageHourLimit && 
                        $packageCumulativeHours > 0) {
                        // Move to next package
                        break;
                    }

                    // Update cumulative hours
                    if ($countsTowardsLimit) {
                        $packageCumulativeHours += $durationHours;
                        $globalCumulativeHours += $durationHours;
                    }

                    // Fix assignment if needed
                    if ($class->package_id != $package->id) {
                        $oldPackageId = $class->package_id ?? 'NULL';
                        $this->line("    - Class #{$class->id} ({$class->class_date}): {$oldPackageId} → {$package->id}");
                        
                        if (!$dryRun) {
                            $class->package_id = $package->id;
                            $class->save();
                        }
                        $changesCount++;
                    }

                    $classIndex++;
                }

                $this->line("    Used {$packageCumulativeHours}h of {$packageHourLimit}h");
            }

            // Handle remaining classes (unassigned - clear their package_id)
            if ($classIndex < $allClasses->count()) {
                $remainingCount = $allClasses->count() - $classIndex;
                $this->line("  Clearing package_id for {$remainingCount} unassigned class(es)");

                while ($classIndex < $allClasses->count()) {
                    $class = $allClasses[$classIndex];
                    
                    if ($class->package_id !== null) {
                        $this->line("    - Class #{$class->id} ({$class->class_date}): {$class->package_id} → NULL");
                        
                        if (!$dryRun) {
                            $class->package_id = null;
                            $class->save();
                        }
                        $changesCount++;
                    }

                    $classIndex++;
                }
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return $changesCount;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
