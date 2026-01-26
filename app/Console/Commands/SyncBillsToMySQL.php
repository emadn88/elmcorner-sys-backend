<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bill;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SyncBillsToMySQL extends Command
{
    protected $signature = 'bills:sync-mysql';
    protected $description = 'Sync bills from SQLite to MySQL or create test bills in MySQL';

    public function handle()
    {
        $this->info('=== Syncing Bills to MySQL ===');
        
        // Check current connection
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $this->info("Current database driver: {$driver}");
        
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            $this->error("Not using MySQL/MariaDB! Current driver: {$driver}");
            $this->info("Please update your .env file to use MySQL:");
            $this->info("DB_CONNECTION=mysql");
            $this->info("DB_HOST=127.0.0.1");
            $this->info("DB_PORT=3306");
            $this->info("DB_DATABASE=elmcorner");
            $this->info("DB_USERNAME=your_username");
            $this->info("DB_PASSWORD=your_password");
            return 1;
        }
        
        // Check if bills table exists
        try {
            $tableExists = DB::select("SHOW TABLES LIKE 'bills'");
            if (empty($tableExists)) {
                $this->error("Bills table does not exist! Please run migrations first:");
                $this->info("php artisan migrate");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error checking table: " . $e->getMessage());
            return 1;
        }
        
        // Get current count
        $currentCount = Bill::count();
        $this->info("Current bills in MySQL: {$currentCount}");
        
        // Get students
        $students = Student::take(3)->get();
        if ($students->isEmpty()) {
            $this->error('No students found. Please create students first.');
            return 1;
        }
        
        $this->info("Found {$students->count()} students");
        
        // Create bills for current month
        $now = Carbon::now();
        $this->info("Creating bills for month: {$now->format('F Y')}");
        
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
                // Check if bill already exists for this student and date
                $exists = Bill::where('student_id', $billData['student_id'])
                    ->where('bill_date', $billData['bill_date'])
                    ->where('amount', $billData['amount'])
                    ->exists();
                
                if (!$exists) {
                    $bill = Bill::create($billData);
                    $this->info("Created bill ID #{$bill->id} for student #{$billData['student_id']} - Status: {$billData['status']} - Amount: {$billData['amount']} {$billData['currency']}");
                    $created++;
                } else {
                    $this->warn("Bill already exists for student #{$billData['student_id']} on {$billData['bill_date']}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to create bill: " . $e->getMessage());
                $this->error("Bill data: " . json_encode($billData, JSON_PRETTY_PRINT));
            }
        }
        
        $this->info("Successfully created {$created} bills out of " . count($bills));
        
        // Verify
        $totalCount = Bill::count();
        $this->info("Total bills in MySQL database: {$totalCount}");
        
        $monthCount = Bill::whereYear('bill_date', $now->year)
            ->whereMonth('bill_date', $now->month)
            ->count();
        $this->info("Bills in current month ({$now->format('F Y')}): {$monthCount}");
        
        if ($monthCount > 0) {
            $sampleBills = Bill::whereYear('bill_date', $now->year)
                ->whereMonth('bill_date', $now->month)
                ->with('student')
                ->take(5)
                ->get();
            
            $this->info("\nSample bills:");
            foreach ($sampleBills as $bill) {
                $studentName = $bill->student ? $bill->student->full_name : 'N/A';
                $this->line("  - Bill #{$bill->id}: Student: {$studentName}, Amount: {$bill->amount} {$bill->currency}, Status: {$bill->status}");
            }
        }
        
        return 0;
    }
}
