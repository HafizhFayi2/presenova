<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegacyPhpRenderer
{
    /**
     * Render a legacy PHP script from /public while keeping original output.
     */
    public function render(string $relativePublicPath): string
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePublicPath), '/');
        $fullPath = public_path($normalized);

        if (!is_file($fullPath)) {
            throw new NotFoundHttpException("Legacy script not found: {$normalized}");
        }

        $scriptDir = dirname($fullPath);
        $scriptName = '/' . trim($normalized, '/');
        $previousCwd = getcwd();
        $previousServer = $this->snapshotServer();
        $initialBufferLevel = ob_get_level();
        $html = '';

        // Mimic direct PHP execution context for relative include paths.
        $_SERVER['SCRIPT_FILENAME'] = $fullPath;
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['PHP_SELF'] = $scriptName;
        $_SERVER['DOCUMENT_ROOT'] = public_path();

        ob_start();

        try {
            if ($previousCwd !== false) {
                chdir($scriptDir);
            }

            include basename($fullPath);
            $html = (string) ob_get_contents();
        } finally {
            while (ob_get_level() > $initialBufferLevel) {
                ob_end_clean();
            }

            if ($previousCwd !== false) {
                chdir($previousCwd);
            }

            $this->restoreServer($previousServer);
        }

        return $html;
    }

    private function snapshotServer(): array
    {
        return [
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? null,
            'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
            'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? null,
        ];
    }

    private function restoreServer(array $snapshot): void
    {
        foreach ($snapshot as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
                continue;
            }

            $_SERVER[$key] = $value;
        }
    }
}

