<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'PHPSESSID',
            'attendance_token',
            'remember_token',
        ]);

        // Compatibility route posts (login, dashboard forms, ajax *.php) do not send Laravel CSRF token.
        // Exempt compatibility routes so cutover remains behavior-compatible.
        $middleware->validateCsrfTokens(except: [
            '*.php',
            'presenova/*.php',
            'dashboard/*',
            'presenova/dashboard/*',
            'api/*',
            'presenova/api/*',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CanonicalPublicPathRedirect::class,
            \App\Http\Middleware\RememberTokenBridge::class,
            \App\Http\Middleware\NativePhpSessionBridge::class,
            \App\Http\Middleware\PresenovaStackHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $statusCode = 500;
            if ($e instanceof HttpExceptionInterface) {
                $statusCode = (int) $e->getStatusCode();
            } elseif (method_exists($e, 'getStatusCode')) {
                $candidate = (int) $e->getStatusCode();
                if ($candidate >= 400 && $candidate <= 599) {
                    $statusCode = $candidate;
                }
            }

            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }

            return app(\App\Http\Controllers\HomeController::class)->error($request, $statusCode);
        });
    })->create();
