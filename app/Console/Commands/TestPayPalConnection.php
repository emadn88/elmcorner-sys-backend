<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestPayPalConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paypal:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test PayPal API connection and credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing PayPal API Connection...');
        $this->newLine();

        // Get credentials - try multiple methods
        $clientId = config('paypal.client_id') ?: getenv('PAYPAL_CLIENT_ID');
        $clientSecret = config('paypal.client_secret') ?: getenv('PAYPAL_CLIENT_SECRET');
        $mode = config('paypal.mode', 'sandbox') ?: getenv('PAYPAL_MODE') ?: 'sandbox';
        
        // If still empty, use direct values (for testing)
        if (empty($clientId) || empty($clientSecret)) {
            $this->warn('Config values not found, using direct credentials for testing...');
            $clientId = 'AeHgsV16i6_h7mR3IZz0l0mavTwbOdJilngxZ_q1KsGlUjHS-v4YZCfnk2_xgAsSjn9bSvWu_O-Y3r2d';
            $clientSecret = 'ENQCjIHjuAfKqfy6skx55toR47hNxID_XaPe8r6xjC_g1hsY-8MO_Zq0RA0rFewIK3fL5bIOQVj0UAJA';
            $mode = 'sandbox';
        }
        
        $baseUrl = $mode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';

        // Display configuration
        $this->info('Configuration:');
        $this->line('  Mode: ' . $mode);
        $this->line('  Base URL: ' . $baseUrl);
        $this->line('  Client ID: ' . ($clientId ? substr($clientId, 0, 20) . '...' : 'NOT SET'));
        $this->line('  Client Secret: ' . ($clientSecret ? substr($clientSecret, 0, 20) . '...' : 'NOT SET'));
        $this->newLine();

        // Validate credentials
        if (empty($clientId) || empty($clientSecret)) {
            $this->error('❌ PayPal credentials are missing!');
            $this->line('Please check your .env file for:');
            $this->line('  - PAYPAL_CLIENT_ID');
            $this->line('  - PAYPAL_CLIENT_SECRET');
            return 1;
        }

        // Test OAuth token request
        $this->info('Testing OAuth Token Request...');
        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                ])
                ->withoutVerifying() // Disable SSL verification for development
                ->post($baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    $this->info('✅ Successfully obtained access token!');
                    $this->line('  Token Type: ' . ($data['token_type'] ?? 'N/A'));
                    $this->line('  Expires In: ' . ($data['expires_in'] ?? 'N/A') . ' seconds');
                    $this->line('  Access Token: ' . substr($data['access_token'], 0, 20) . '...');
                    $this->newLine();
                    $this->info('✅ PayPal API connection is working correctly!');
                    return 0;
                } else {
                    $this->error('❌ Response missing access_token');
                    $this->line('Response: ' . json_encode($data, JSON_PRETTY_PRINT));
                    return 1;
                }
            } else {
                $statusCode = $response->status();
                $errorBody = $response->body();
                $errorJson = $response->json();
                
                $this->error('❌ Failed to get access token');
                $this->line('  Status Code: ' . $statusCode);
                $this->line('  Response: ' . $errorBody);
                
                if (isset($errorJson['error'])) {
                    $this->line('  Error: ' . $errorJson['error']);
                }
                if (isset($errorJson['error_description'])) {
                    $this->line('  Description: ' . $errorJson['error_description']);
                }
                
                $this->newLine();
                $this->warn('Common issues:');
                $this->line('  - Invalid Client ID or Secret');
                $this->line('  - Credentials not activated in PayPal Developer Dashboard');
                $this->line('  - Wrong mode (sandbox vs live)');
                
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Exception occurred:');
            $this->line('  ' . $e->getMessage());
            $this->line('  ' . $e->getTraceAsString());
            return 1;
        }
    }
}
