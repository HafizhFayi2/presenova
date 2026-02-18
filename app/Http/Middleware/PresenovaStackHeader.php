<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PresenovaStackHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!headers_sent()) {
            header('X-Presenova-Stack: laravel-only', true);
        }

        /** @var Response $response */
        return $next($request);
    }
}
