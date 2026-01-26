<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SwitchToMySQL extends Command
{
    protected $signature = 'db:switch-mysql {--force : Force switch without confirmation}';
    protected $description = 'Switch database connection to MySQL and run migrations';

    public function handle()
    {
        $this->info('=== Switching to MySQL Database ===');
        
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->error('.env file not found!');
            return 1;
        }
        
        // Read current .env
        $envContent = File::get($envPath);
        
        // Check current connection
        $currentConnection = env('DB_CONNECTION', 'sqlite');
        $this->info("Current database connection: {$currentConnection}");
        
        if ($currentConnection === 'mysql' || $currentConnection === 'mariadb') {
            $this->info('Already using MySQL/MariaDB!');
            
            // Test connection
            try {
                DB::connection()->getPdo();
                $this->info('✓ MySQL connection successful!');
                
                // Check if bills table exists
                $tables = DB::select("SHOW TABLES LIKE 'bills'");
                if (empty($tables)) {
                    $this->warn('Bills table does not exist. Running migrations...');
                    $this->call('migrate', ['--force' => true]);
                } else {
                    $this->info('✓ Bills table exists');
                }
                
                $this->info("\nNext steps:");
                $this->info("1. Run: php artisan bills:sync-mysql");
                $this->info("   OR");
                $this->info("2. Run: php artisan db:seed --class=BillSeeder");
                
                return 0;
            } catch (\Exception $e) {
                $this->error('MySQL connection failed: ' . $e->getMessage());
                $this->info("\nPlease update your .env file with correct MySQL credentials:");
                $this->info("DB_CONNECTION=mysql");
                $this->info("DB_HOST=127.0.0.1");
                $this->info("DB_PORT=3306");
                $this->info("DB_DATABASE=elmcorner");
                $this->info("DB_USERNAME=your_username");
                $this->info("DB_PASSWORD=your_password");
                return 1;
            }
        }
        
        // Ask for MySQL credentials
        if (!$this->option('force')) {
            $this->info("\nPlease provide MySQL database credentials:");
            $host = $this->ask('MySQL Host', '127.0.0.1');
            $port = $this->ask('MySQL Port', '3306');
            $database = $this->ask('Database Name', 'elmcorner');
            $username = $this->ask('Username', 'root');
            $password = $this->secret('Password');
        } else {
            // Use defaults or env values
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $database = env('DB_DATABASE', 'elmcorner');
            $username = env('DB_USERNAME', 'root');
            $password = env('DB_PASSWORD', '');
        }
        
        // Update .env file
        $this->info("\nUpdating .env file...");
        
        // Replace DB_CONNECTION
        $envContent = preg_replace(
            '/^DB_CONNECTION=.*/m',
            'DB_CONNECTION=mysql',
            $envContent
        );
        
        // Replace or add MySQL settings
        $replacements = [
            '/^DB_HOST=.*/m' => "DB_HOST={$host}",
            '/^DB_PORT=.*/m' => "DB_PORT={$port}",
            '/^DB_DATABASE=.*/m' => "DB_DATABASE={$database}",
            '/^DB_USERNAME=.*/m' => "DB_USERNAME={$username}",
            '/^DB_PASSWORD=.*/m' => "DB_PASSWORD={$password}",
        ];
        
        foreach ($replacements as $pattern => $replacement) {
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                // Add if doesn't exist
                $envContent .= "\n{$replacement}";
            }
        }
        
        File::put($envPath, $envContent);
        $this->info('✓ .env file updated');
        
        // Clear config cache
        $this->call('config:clear');
        $this->call('cache:clear');
        
        // Test connection
        $this->info("\nTesting MySQL connection...");
        try {
            // Temporarily set config
            config(['database.connections.mysql.host' => $host]);
            config(['database.connections.mysql.port' => $port]);
            config(['database.connections.mysql.database' => $database]);
            config(['database.connections.mysql.username' => $username]);
            config(['database.connections.mysql.password' => $password]);
            
            DB::purge('mysql');
            DB::connection('mysql')->getPdo();
            $this->info('✓ MySQL connection successful!');
        } catch (\Exception $e) {
            $this->error('✗ MySQL connection failed: ' . $e->getMessage());
            $this->error("\nPlease check your MySQL credentials and try again.");
            return 1;
        }
        
        // Run migrations
        $this->info("\nRunning migrations...");
        try {
            $this->call('migrate', ['--force' => true]);
            $this->info('✓ Migrations completed');
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
        
        // Seed bills
        $this->info("\nWould you like to seed bills data?");
        if ($this->confirm('Seed bills data?', true)) {
            $this->call('db:seed', ['--class' => 'BillSeeder', '--force' => true]);
            $this->info('✓ Bills seeded successfully');
        }
        
        $this->info("\n=== Switch to MySQL Complete ===");
        $this->info("Your application is now using MySQL database: {$database}");
        
        return 0;
    }
}
