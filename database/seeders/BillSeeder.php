<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bill;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Package;
use App\Models\ClassInstance;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Delete existing bills to avoid duplicates
        Bill::truncate();
        
        // Get existing students, teachers, and packages
        $students = Student::where('status', 'active')->take(5)->get();
        $teachers = Teacher::with('user')->take(3)->get();
        $packages = Package::where('status', 'active')->take(5)->get();

        // If no students or teachers exist, we can't create bills
        if ($students->isEmpty() || $teachers->isEmpty()) {
            $this->command->warn('Not enough students or teachers found. Creating only custom bills with existing students...');
            
            // Try to get any students (even if not active)
            $students = Student::take(3)->get();
            if ($students->isEmpty()) {
                $this->command->error('No students found in database. Please create students first.');
                return;
            }
            
            // Get any teachers (optional for custom bills - teacher_id is now nullable)
            $teachers = Teacher::take(1)->get();
            $teacherId = $teachers->isNotEmpty() ? $teachers->first()->id : null;
            
            // Only create custom bills
            $now = Carbon::now();
            $bills = [];
            
            foreach ($students->take(3) as $index => $student) {
                // Use current month dates so bills show up in the filter
                $billDate = $now->copy()->startOfMonth()->addDays($index);
                $bills[] = [
                    'student_id' => $student->id,
                    'teacher_id' => $teacherId,
                    'amount' => [500, 750, 1000][$index],
                    'currency' => $student->currency ?? 'USD',
                    'status' => $index === 0 ? 'paid' : ($index === 1 ? 'sent' : 'pending'),
                    'bill_date' => $billDate->toDateString(),
                    'description' => [
                        'Advance payment for next package',
                        'Special discount package',
                        'Additional services payment',
                    ][$index],
                    'is_custom' => true,
                    'duration' => 0,
                    'total_hours' => 0,
                    'payment_date' => $index === 0 ? $billDate->copy()->addDays(1)->toDateString() : null,
                    'payment_method' => $index === 0 ? 'Bank Transfer' : null,
                    'sent_at' => $index === 1 ? $billDate->copy()->addDays(1) : null,
                    'created_at' => $billDate,
                    'updated_at' => $billDate,
                ];
            }
            
            // Insert custom bills
            foreach ($bills as $billData) {
                if (in_array($billData['status'], ['sent', 'paid'])) {
                    // Generate elmcorner + 5 character token
                    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $tokenSuffix = '';
                    for ($i = 0; $i < 5; $i++) {
                        $tokenSuffix .= $characters[random_int(0, strlen($characters) - 1)];
                    }
                    $billData['payment_token'] = 'elmcorner' . $tokenSuffix;
                }
                Bill::create($billData);
            }
            
            $this->command->info('Created ' . count($bills) . ' custom bills.');
            return;
        }

        $now = Carbon::now();
        $bills = [];

        // Create bills for packages with classes
        foreach ($packages as $index => $package) {
            $student = $package->student;
            $classes = ClassInstance::where('package_id', $package->id)
                ->whereIn('status', ['attended', 'absent_student'])
                ->orderBy('class_date', 'asc')
                ->take(3)
                ->get();

            if ($classes->isEmpty()) {
                continue;
            }

            $teacher = $classes->first()->teacher;
            $classIds = $classes->pluck('id')->toArray();
            $totalHours = $classes->sum('duration') / 60;
            $totalAmount = $classes->sum(function ($class) use ($teacher) {
                return ($class->duration / 60) * ($teacher->hourly_rate ?? 50);
            });

            // Create pending bill - use current month date
            $billDate = $now->copy()->startOfMonth()->addDays($index);
            $bills[] = [
                'package_id' => $package->id,
                'class_id' => $classes->first()->id,
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'class_ids' => $classIds,
                'duration' => $classes->sum('duration'),
                'total_hours' => round($totalHours, 2),
                'amount' => round($totalAmount, 2),
                'currency' => $package->currency ?? $student->currency ?? 'USD',
                'status' => 'pending',
                'bill_date' => $billDate->toDateString(),
                'is_custom' => false,
                'created_at' => $billDate,
                'updated_at' => $billDate,
            ];

            // Create sent bill
            if ($index < 3) {
                $sentClasses = ClassInstance::where('package_id', $package->id)
                    ->whereIn('status', ['attended', 'absent_student'])
                    ->orderBy('class_date', 'asc')
                    ->skip(3)
                    ->take(2)
                    ->get();

                if ($sentClasses->isNotEmpty()) {
                    $sentClassIds = $sentClasses->pluck('id')->toArray();
                    $sentTotalHours = $sentClasses->sum('duration') / 60;
                    $sentTotalAmount = $sentClasses->sum(function ($class) use ($teacher) {
                        return ($class->duration / 60) * ($teacher->hourly_rate ?? 50);
                    });

                    $sentBillDate = $now->copy()->startOfMonth()->addDays($index + 3);
                    $bills[] = [
                        'package_id' => $package->id,
                        'class_id' => $sentClasses->first()->id,
                        'student_id' => $student->id,
                        'teacher_id' => $teacher->id,
                        'class_ids' => $sentClassIds,
                        'duration' => $sentClasses->sum('duration'),
                        'total_hours' => round($sentTotalHours, 2),
                        'amount' => round($sentTotalAmount, 2),
                        'currency' => $package->currency ?? $student->currency ?? 'USD',
                        'status' => 'sent',
                        'bill_date' => $sentBillDate->toDateString(),
                        'sent_at' => $sentBillDate->copy()->addDays(1),
                        'is_custom' => false,
                        'created_at' => $sentBillDate,
                        'updated_at' => $sentBillDate->copy()->addDays(1),
                    ];
                }
            }

            // Create paid bill
            if ($index < 2) {
                $paidClasses = ClassInstance::where('package_id', $package->id)
                    ->whereIn('status', ['attended', 'absent_student'])
                    ->orderBy('class_date', 'asc')
                    ->skip(5)
                    ->take(4)
                    ->get();

                if ($paidClasses->isNotEmpty()) {
                    $paidClassIds = $paidClasses->pluck('id')->toArray();
                    $paidTotalHours = $paidClasses->sum('duration') / 60;
                    $paidTotalAmount = $paidClasses->sum(function ($class) use ($teacher) {
                        return ($class->duration / 60) * ($teacher->hourly_rate ?? 50);
                    });

                    $paidBillDate = $now->copy()->startOfMonth()->addDays($index + 6);
                    $paymentDate = $paidBillDate->copy()->addDays(2);
                    $bills[] = [
                        'package_id' => $package->id,
                        'class_id' => $paidClasses->first()->id,
                        'student_id' => $student->id,
                        'teacher_id' => $teacher->id,
                        'class_ids' => $paidClassIds,
                        'duration' => $paidClasses->sum('duration'),
                        'total_hours' => round($paidTotalHours, 2),
                        'amount' => round($paidTotalAmount, 2),
                        'currency' => $package->currency ?? $student->currency ?? 'USD',
                        'status' => 'paid',
                        'bill_date' => $paidBillDate->toDateString(),
                        'payment_date' => $paymentDate->toDateString(),
                        'payment_method' => ['Cash', 'Bank Transfer', 'Credit Card'][$index % 3],
                        'is_custom' => false,
                        'created_at' => $paidBillDate,
                        'updated_at' => $paymentDate,
                    ];
                }
            }
        }

        // Create custom bills
        foreach ($students->take(3) as $index => $student) {
            $bills[] = [
                'student_id' => $student->id,
                'teacher_id' => $teachers->random()->id,
                'amount' => [500, 750, 1000][$index],
                'currency' => $student->currency ?? 'USD',
                'status' => $index === 0 ? 'paid' : ($index === 1 ? 'sent' : 'pending'),
                'bill_date' => $now->copy()->subDays(2 - $index),
                'description' => [
                    'Advance payment for next package',
                    'Special discount package',
                    'Additional services payment',
                ][$index],
                'is_custom' => true,
                'duration' => 0,
                'total_hours' => 0,
                'payment_date' => $index === 0 ? $now->copy()->subDays(1) : null,
                'payment_method' => $index === 0 ? 'Bank Transfer' : null,
                'sent_at' => $index === 1 ? $now->copy()->subDays(1) : null,
                'created_at' => $now->copy()->subDays(2 - $index),
                'updated_at' => $now->copy()->subDays(2 - $index),
            ];
        }

        // Insert all bills
        foreach ($bills as $billData) {
            // Generate payment token for sent and paid bills
            if (in_array($billData['status'], ['sent', 'paid'])) {
                $billData['payment_token'] = Str::random(64);
            }

            Bill::create($billData);
        }

        $this->command->info('Created ' . count($bills) . ' bills (including custom bills)');
    }
}
