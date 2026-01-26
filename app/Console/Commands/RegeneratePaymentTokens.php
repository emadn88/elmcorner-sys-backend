<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bill;
use Illuminate\Support\Str;

class RegeneratePaymentTokens extends Command
{
    protected $signature = 'bills:regenerate-tokens';
    protected $description = 'Regenerate payment tokens to new format (elmcorner + 5 chars)';

    public function handle()
    {
        $this->info('Regenerating payment tokens to new format...');
        
        $bills = Bill::whereNotNull('payment_token')->get();
        $updated = 0;
        
        foreach ($bills as $bill) {
            // Skip if already in new format
            if (str_starts_with($bill->payment_token, 'elmcorner') && strlen($bill->payment_token) === 14) {
                continue;
            }
            
            // Generate new token
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $tokenSuffix = '';
            
            do {
                $tokenSuffix = '';
                for ($i = 0; $i < 5; $i++) {
                    $tokenSuffix .= $characters[random_int(0, strlen($characters) - 1)];
                }
                $token = 'elmcorner' . $tokenSuffix;
            } while (Bill::where('payment_token', $token)->where('id', '!=', $bill->id)->exists());
            
            $bill->update(['payment_token' => $token]);
            $this->info("Updated bill #{$bill->id}: {$token}");
            $updated++;
        }
        
        $this->info("Updated {$updated} payment tokens.");
        
        return 0;
    }
}
