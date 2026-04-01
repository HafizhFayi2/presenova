<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class DashboardPageController extends Controller
{
    public function admin(Request $request): Response|RedirectResponse
    {
        return $this->renderDashboard('dashboard.admin');
    }

    public function guru(Request $request): Response|RedirectResponse
    {
        return $this->renderDashboard('dashboard.guru');
    }

    public function siswa(Request $request): Response|RedirectResponse
    {
        return $this->renderDashboard('dashboard.siswa');
    }

    private function renderDashboard(string $view): Response|RedirectResponse
    {
        try {
            return response()
                ->view($view)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (Throwable $e) {
            if ($this->isDatabaseConnectionFailure($e)) {
                report($e);

                return redirect($this->appPath('login.php?db_error=1'));
            }

            throw $e;
        }
    }

    private function isDatabaseConnectionFailure(Throwable $e): bool
    {
        $keywords = [
            'sqlstate[hy000] [2002]',
            'connection refused',
            'target machine actively refused it',
            'server has gone away',
            'connection timed out',
            'could not find driver',
        ];

        $current = $e;
        while ($current instanceof Throwable) {
            $message = strtolower((string) $current->getMessage());
            foreach ($keywords as $keyword) {
                if (str_contains($message, $keyword)) {
                    return true;
                }
            }
            $current = $current->getPrevious();
        }

        return false;
    }

    private function appPath(string $path): string
    {
        if (preg_match('~^https?://~i', $path) === 1) {
            return $path;
        }

        $path = ltrim($path, '/');
        $root = $this->resolveAppRootUrl();
        if ($path === '') {
            return $root . '/';
        }

        return $root . '/' . $path;
    }

    private function resolveAppRootUrl(): string
    {
        $request = request();
        $hostUrl = rtrim((string) $request->getSchemeAndHttpHost(), '/');
        if ($hostUrl !== '') {
            $prefix = $this->resolveAppPrefix();

            return $hostUrl . ($prefix !== '' ? '/' . $prefix : '');
        }

        $prefix = $this->resolveAppPrefix();
        if ($prefix !== '') {
            return '/' . $prefix;
        }

        return '';
    }

    private function resolveAppPrefix(): string
    {
        $request = request();

        $scriptPrefix = $this->prefixFromScriptName((string) $request->server('SCRIPT_NAME', ''));
        if ($scriptPrefix !== '') {
            return $scriptPrefix;
        }

        $basePath = $this->normalizePathPrefix((string) $request->getBasePath());
        if ($basePath !== '') {
            return $basePath;
        }

        return '';
    }

    private function normalizePathPrefix(string $prefix): string
    {
        $prefix = str_replace('\\', '/', $prefix);
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
