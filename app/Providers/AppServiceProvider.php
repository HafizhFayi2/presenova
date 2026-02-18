<?php

namespace App\Providers;

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
        $runtimeHelpers = base_path('app/Support/runtime_helpers.php');
        if (is_file($runtimeHelpers)) {
            require_once $runtimeHelpers;
        }
    }
}
