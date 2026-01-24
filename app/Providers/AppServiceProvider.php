<?php

namespace App\Providers;

use App\Models\ClassInstance;
use App\Observers\ClassObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        ClassInstance::observe(ClassObserver::class);
    }
}
