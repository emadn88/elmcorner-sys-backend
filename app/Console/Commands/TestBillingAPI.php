<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BillingService;
use Carbon\Carbon;

class TestBillingAPI extends Command
{
    protected $signature = 'bills:test-api';
    protected $description = 'Test billing API response';

    public function handle()
    {
        $billingService = app(BillingService::class);
        
        $year = Carbon::now()->year;
        $month = Carbon::now()->month;
        
        $this->info("Testing API for {$year}-{$month}");
        
        $bills = $billingService->getBills([
            'year' => $year,
            'month' => $month,
        ]);
        
        $this->info("Bills returned: " . count($bills));
        $this->info("Bills keys: " . implode(', ', array_keys($bills)));
        
        foreach ($bills as $key => $monthData) {
            $this->info("\nMonth: {$key}");
            $this->info("  Total bills: " . count($monthData['bills']));
            $this->info("  Paid: " . count($monthData['paid']));
            $this->info("  Unpaid: " . count($monthData['unpaid']));
            
            foreach ($monthData['bills'] as $bill) {
                $studentName = $bill->student ? $bill->student->full_name : 'No student';
                $this->line("    - Bill #{$bill->id}: {$studentName} | {$bill->amount} {$bill->currency} | {$bill->status}");
            }
        }
        
        $stats = $billingService->getBillingStatistics($year, $month);
        $this->info("\nStatistics:");
        $this->info("  Due: " . $stats['due']['count'] . " bills");
        $this->info("  Paid: " . $stats['paid']['count'] . " bills");
        $this->info("  Unpaid: " . $stats['unpaid']['count'] . " bills");
        
        return 0;
    }
}
