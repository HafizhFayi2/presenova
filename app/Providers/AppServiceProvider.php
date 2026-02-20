<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        $forceHttps = filter_var((string) env('FORCE_HTTPS', 'false'), FILTER_VALIDATE_BOOLEAN);
        if ($forceHttps) {
            URL::forceScheme('https');
        }

        $runtimeHelpers = base_path('app/Support/runtime_helpers.php');
        if (is_file($runtimeHelpers)) {
            require_once $runtimeHelpers;
        }
    }
}
