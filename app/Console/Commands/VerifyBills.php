<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bill;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class VerifyBills extends Command
{
    protected $signature = 'bills:verify';
    protected $description = 'Verify bills in database';

    public function handle()
    {
        $this->info('=== Bills Database Verification ===');
        
        // Check total count
        $total = Bill::count();
        $this->info("Total bills in database: {$total}");
        
        if ($total === 0) {
            $this->warn('No bills found! Creating sample bills...');
            $this->createSampleBills();
            $total = Bill::count();
            $this->info("Now total bills: {$total}");
        }
        
        // Show current month bills
        $now = \Carbon\Carbon::now();
        $monthBills = Bill::whereYear('bill_date', $now->year)
            ->whereMonth('bill_date', $now->month)
            ->with('student')
            ->get();
            
        $this->info("\nBills for {$now->format('F Y')}: {$monthBills->count()}");
        
        foreach ($monthBills as $bill) {
            $studentName = $bill->student ? $bill->student->full_name : 'Unknown';
            $this->line("  - Bill #{$bill->id}: {$studentName} | {$bill->amount} {$bill->currency} | {$bill->status} | Custom: " . ($bill->is_custom ? 'Yes' : 'No'));
        }
        
        // Show all bills grouped by month
        $allBills = Bill::orderBy('bill_date', 'desc')->get();
        $grouped = $allBills->groupBy(function($bill) {
            return \Carbon\Carbon::parse($bill->bill_date)->format('Y-m');
        });
        
        $this->info("\nAll bills by month:");
        foreach ($grouped as $month => $bills) {
            $this->line("  {$month}: {$bills->count()} bills");
        }
        
        return 0;
    }
    
    private function createSampleBills()
    {
        $students = Student::take(3)->get();
        if ($students->isEmpty()) {
            $this->error('No students found. Cannot create bills.');
            return;
        }
        
        $now = \Carbon\Carbon::now();
        $bills = [];
        
        foreach ($students->take(3) as $index => $student) {
            $billDate = $now->copy()->startOfMonth()->addDays($index);
            
            try {
                $bill = Bill::create([
                    'student_id' => $student->id,
                    'teacher_id' => null,
                    'amount' => [500, 750, 1000][$index],
                    'currency' => $student->currency ?? 'SAR',
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
                    'payment_token' => in_array($index, [0, 1]) ? 'elmcorner' . \Illuminate\Support\Str::random(5) : null,
                ]);
                $this->info("Created bill #{$bill->id}");
            } catch (\Exception $e) {
                $this->error("Failed: " . $e->getMessage());
            }
        }
    }
}
