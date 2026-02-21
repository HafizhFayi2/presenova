<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;

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
        if ($forceHttps && !$this->isLocalHttpHost(request())) {
            URL::forceScheme('https');
        }

        $runtimeHelpers = base_path('app/Support/runtime_helpers.php');
        if (is_file($runtimeHelpers)) {
            require_once $runtimeHelpers;
        }
    }

    private function isLocalHttpHost(Request $request): bool
    {
        $host = strtolower(trim((string) $request->getHost()));
        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return str_ends_with($host, '.localhost');
    }
}
