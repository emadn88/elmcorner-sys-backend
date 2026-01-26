<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Bill;

class CheckDatabaseConnection extends Command
{
    protected $signature = 'db:check-connection';
    protected $description = 'Check which database connection is being used';

    public function handle()
    {
        $this->info('=== Database Connection Information ===');
        
        $defaultConnection = config('database.default');
        $this->info("Default Connection: {$defaultConnection}");
        
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $this->info("Driver: {$driver}");
        
        if ($driver === 'sqlite') {
            $database = config("database.connections.{$defaultConnection}.database");
            $this->info("SQLite Database Path: {$database}");
            $this->info("File exists: " . (file_exists($database) ? 'Yes' : 'No'));
            if (file_exists($database)) {
                $this->info("File size: " . number_format(filesize($database) / 1024, 2) . " KB");
            }
        } else {
            $database = config("database.connections.{$defaultConnection}.database");
            $host = config("database.connections.{$defaultConnection}.host");
            $port = config("database.connections.{$defaultConnection}.port");
            $this->info("Database Name: {$database}");
            $this->info("Host: {$host}:{$port}");
        }
        
        $this->info("\n=== Bills Count ===");
        try {
            $count = Bill::count();
            $this->info("Total bills in current database: {$count}");
            
            if ($count > 0) {
                $sample = Bill::with('student')->take(3)->get();
                $this->info("\nSample bills:");
                foreach ($sample as $bill) {
                    $studentName = $bill->student ? $bill->student->full_name : 'No student';
                    $this->line("  - Bill #{$bill->id}: {$studentName} | {$bill->amount} {$bill->currency} | {$bill->status}");
                }
            }
        } catch (\Exception $e) {
            $this->error("Error querying bills: " . $e->getMessage());
        }
        
        $this->info("\n=== To Switch to MySQL/MariaDB ===");
        $this->info("1. Update your .env file:");
        $this->info("   DB_CONNECTION=mysql");
        $this->info("   DB_HOST=127.0.0.1");
        $this->info("   DB_PORT=3306");
        $this->info("   DB_DATABASE=elmcorner");
        $this->info("   DB_USERNAME=your_username");
        $this->info("   DB_PASSWORD=your_password");
        $this->info("\n2. Run migrations:");
        $this->info("   php artisan migrate");
        $this->info("\n3. Run seeders:");
        $this->info("   php artisan db:seed --class=BillSeeder");
        
        return 0;
    }
}
