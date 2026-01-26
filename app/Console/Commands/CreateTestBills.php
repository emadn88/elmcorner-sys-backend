<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bill;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CreateTestBills extends Command
{
    protected $signature = 'bills:create-test';
    protected $description = 'Create test bills for current month';

    public function handle()
    {
        $students = Student::take(3)->get();
        
        if ($students->isEmpty()) {
            $this->error('No students found. Please create students first.');
            return 1;
        }

        $now = Carbon::now();
        $this->info("Creating bills for month: {$now->format('F Y')}");

        // Don't delete - just create new ones
        // Bill::whereYear('bill_date', $now->year)
        //     ->whereMonth('bill_date', $now->month)
        //     ->delete();

        $bills = [];

        foreach ($students->take(3) as $index => $student) {
            $billDate = $now->copy()->startOfMonth()->addDays($index);
            
            $bills[] = [
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
                'payment_token' => in_array($index, [0, 1]) ? 'elmcorner' . Str::random(5) : null,
                'created_at' => $billDate,
                'updated_at' => $billDate,
            ];
        }

        $created = 0;
        foreach ($bills as $billData) {
            try {
                $bill = Bill::create($billData);
                $this->info("Created bill ID #{$bill->id} for student #{$billData['student_id']} - Status: {$billData['status']} - Amount: {$billData['amount']} {$billData['currency']}");
                $created++;
            } catch (\Exception $e) {
                $this->error("Failed to create bill: " . $e->getMessage());
                $this->error("Bill data: " . json_encode($billData, JSON_PRETTY_PRINT));
            }
        }

        $this->info("Successfully created {$created} bills out of " . count($bills));
        
        // Verify
        $totalCount = Bill::count();
        $this->info("Total bills in database: {$totalCount}");
        
        $monthCount = Bill::whereYear('bill_date', $now->year)
            ->whereMonth('bill_date', $now->month)
            ->count();
        $this->info("Bills in current month ({$now->format('F Y')}): {$monthCount}");
        
        if ($monthCount > 0) {
            $sampleBills = Bill::whereYear('bill_date', $now->year)
                ->whereMonth('bill_date', $now->month)
                ->with('student')
                ->take(3)
                ->get();
            
            foreach ($sampleBills as $bill) {
                $this->line("  - Bill #{$bill->id}: Student: " . ($bill->student->full_name ?? 'N/A') . ", Amount: {$bill->amount} {$bill->currency}, Status: {$bill->status}");
            }
        }

        return 0;
    }
}
