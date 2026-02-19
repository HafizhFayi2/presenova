<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalPublicPathRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $rawUri = (string) $request->server('REQUEST_URI', '');
        $path = (string) parse_url($rawUri, PHP_URL_PATH);
        if ($path === '' || $path === '/') {
            return $next($request);
        }

        $configuredPrefix = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        $normalizedPath = $this->normalizePath($path, $configuredPrefix);
        if ($normalizedPath === $path) {
            return $next($request);
        }

        $target = rtrim((string) $request->getSchemeAndHttpHost(), '/') . $normalizedPath;
        $query = (string) $request->server('QUERY_STRING', '');
        if ($query !== '') {
            $target .= '?' . $query;
        }

        return redirect()->to($target, 302);
    }

    private function normalizePath(string $path, string $configuredPrefix): string
    {
        $hasTrailingSlash = $path !== '/' && str_ends_with($path, '/');
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn (string $segment): bool => $segment !== ''
        ));

        if ($segments === []) {
            return '/';
        }

        $originalSegments = $segments;
        $segments = array_values(array_filter(
            $segments,
            static fn (string $segment): bool => strcasecmp($segment, 'public') !== 0
        ));

        if ($segments === []) {
            return '/';
        }

        $prefixSegments = $configuredPrefix === ''
            ? []
            : array_values(array_filter(explode('/', $configuredPrefix), static fn (string $segment): bool => $segment !== ''));

        if ($prefixSegments !== []) {
            while ($this->startsWith($segments, array_merge($prefixSegments, $prefixSegments))) {
                $segments = array_merge(
                    $prefixSegments,
                    array_slice($segments, count($prefixSegments) * 2)
                );
            }
        }

        $normalized = '/' . implode('/', $segments);
        if ($hasTrailingSlash) {
            $normalized .= '/';
        }

        if ($segments === $originalSegments) {
            return $normalized;
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $haystack
     * @param array<int, string> $needle
     */
    private function startsWith(array $haystack, array $needle): bool
    {
        if ($needle === []) {
            return true;
        }

        if (count($haystack) < count($needle)) {
            return false;
        }

        return array_slice($haystack, 0, count($needle)) === $needle;
    }
}
