<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HomeController extends Controller
{
    public function getStarted(Request $request): Response
    {
        $requestUriPath = (string) parse_url((string) $request->server('REQUEST_URI', ''), PHP_URL_PATH);
        if (preg_match('~(?:^|/)index\.php$~i', $requestUriPath) === 1
            && preg_match('~(?:^|/)getstarted/index\.php$~i', $requestUriPath) !== 1) {
            return $this->index($request);
        }

        $urlContext = $this->resolveUrlContext($request);

        return response()->view('pages.get-started', [
            'siteUrl' => $urlContext['siteUrl'],
            'fullurl' => $urlContext['rootUrl'],
            'indexUrl' => $urlContext['indexUrl'],
            'loginUrl' => $urlContext['loginUrl'],
            'loginAdminUrl' => $urlContext['loginAdminUrl'],
            'loginGuruUrl' => $urlContext['loginGuruUrl'],
            'registerUrl' => $urlContext['registerUrl'],
            'getStartedUrl' => $urlContext['getStartedUrl'],
            'assetBaseUrl' => $urlContext['assetBaseUrl'],
        ]);
    }

    public function index(Request $request): Response
    {
        $urlContext = $this->resolveUrlContext($request);

        return response()->view('pages.home', [
            'siteUrl' => $urlContext['siteUrl'],
            'fullurl' => 'index.php',
            'loginUrl' => $urlContext['loginUrl'],
            'loginAdminUrl' => $urlContext['loginAdminUrl'],
            'loginGuruUrl' => $urlContext['loginGuruUrl'],
            'registerUrl' => $urlContext['registerUrl'],
            'getStartedUrl' => $urlContext['getStartedUrl'],
            'assetBaseUrl' => $urlContext['assetBaseUrl'],
        ]);
    }

    public function notFound(Request $request): Response
    {
        return $this->error($request, 404);
    }

    public function error(Request $request, int|string $code = 404): Response
    {
        $statusCode = (int) $code;
        if ($statusCode < 400 || $statusCode > 599) {
            $statusCode = 404;
        }

        $statusText = (string) (SymfonyResponse::$statusTexts[$statusCode] ?? 'Error');
        $message = $this->resolveErrorMessage($statusCode);

        $studentId = (int) session('student_id', 0);
        if ($studentId > 0 && function_exists('pushNotifyStudent')) {
            pushNotifyStudent(
                $studentId,
                'system_error',
                'Error Sistem ' . $statusCode,
                "Sistem menampilkan {$statusCode} {$statusText}. {$message}",
                '/dashboard/siswa.php?page=dashboard'
            );
        }

        return response()->view('pages.not-found', [
            'errorCode' => $statusCode,
            'errorTitle' => $statusText,
            'errorMessage' => $message,
        ], $statusCode)
            ->header('X-Presenova-Error-Page', '1')
            ->header('X-Presenova-Error-Code', (string) $statusCode);
    }

    private function resolveErrorMessage(int $statusCode): string
    {
        if ($statusCode === 404) {
            return 'The requested URL was not found on this server.';
        }

        return 'The server returned HTTP ' . $statusCode . ' while processing your request.';
    }

    private function resolveUrlContext(Request $request): array
    {
        $appPath = $this->resolveAppPrefix($request);
        $basePath = $appPath === '' ? '' : '/' . $appPath;

        $hostUrl = rtrim((string) $request->getSchemeAndHttpHost(), '/');
        $siteUrl = $hostUrl . ($basePath !== '' ? $basePath : '') . '/';
        $assetBaseUrl = $siteUrl;

        $indexUrl = ($basePath === '' ? '/index.php' : $basePath . '/index.php');
        $loginUrl = ($basePath === '' ? '/login.php' : $basePath . '/login.php');
        $getStartedUrl = ($basePath === '' ? '/getstarted/index.php' : $basePath . '/getstarted/index.php');
        $rootUrl = ($basePath === '' ? '/' : $basePath . '/');

        return [
            'siteUrl' => $siteUrl,
            'rootUrl' => $rootUrl,
            'indexUrl' => $indexUrl,
            'loginUrl' => $loginUrl,
            'loginAdminUrl' => $loginUrl . '?role=admin',
            'loginGuruUrl' => $loginUrl . '?role=guru',
            'registerUrl' => ($basePath === '' ? '/register.php' : $basePath . '/register.php'),
            'getStartedUrl' => $getStartedUrl,
            'assetBaseUrl' => $assetBaseUrl,
        ];
    }

    private function resolveAppPrefix(Request $request): string
    {
        $basePath = $this->normalizePathPrefix((string) $request->getBasePath());
        if ($basePath !== '') {
            return $basePath;
        }

        $scriptPrefix = $this->prefixFromScriptName((string) $request->server('SCRIPT_NAME', ''));
        if ($scriptPrefix !== '') {
            return $scriptPrefix;
        }

        $configPath = $this->normalizePathPrefix((string) parse_url((string) config('app.url'), PHP_URL_PATH));
        if ($configPath === '') {
            return '';
        }

        $requestPath = '/' . trim((string) parse_url((string) $request->server('REQUEST_URI', ''), PHP_URL_PATH), '/');
        if ($requestPath === '/') {
            return '';
        }

        if (preg_match('~^/' . preg_quote($configPath, '~') . '(?:/|$)~i', $requestPath) === 1) {
            return $configPath;
        }

        return '';
    }

    private function normalizePathPrefix(string $prefix): string
    {
        $prefix = trim($prefix, '/');
        if ($prefix === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $prefix), static fn (string $segment): bool => $segment !== ''));
        $segmentCount = count($segments);
        if ($segmentCount < 2) {
            return implode('/', $segments);
        }

        for ($size = intdiv($segmentCount, 2); $size >= 1; $size--) {
            if (($segmentCount % $size) !== 0 || $segmentCount < ($size * 2)) {
                continue;
            }

            $pattern = array_slice($segments, 0, $size);
            $allSame = true;
            for ($index = $size; $index < $segmentCount; $index += $size) {
                if (array_slice($segments, $index, $size) !== $pattern) {
                    $allSame = false;
                    break;
                }
            }

            if ($allSame) {
                return implode('/', $pattern);
            }
        }

        return implode('/', $segments);
    }

    private function prefixFromScriptName(string $scriptName): string
    {
        $scriptName = str_replace('\\', '/', $scriptName);
        if ($scriptName === '') {
            return '';
        }

        $scriptDir = trim(dirname($scriptName), '/.');
        if ($scriptDir === '') {
            return '';
        }

        $segments = array_values(array_filter(
            explode('/', $scriptDir),
            static fn (string $segment): bool => $segment !== ''
        ));

        if ($segments !== [] && strcasecmp((string) end($segments), 'public') === 0) {
            array_pop($segments);
        }

        return $this->normalizePathPrefix(implode('/', $segments));
    }
}
