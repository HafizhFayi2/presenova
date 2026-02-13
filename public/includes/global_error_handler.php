<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    return;
}

$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
if (preg_match('#/(404|laravel)\.php$#i', $scriptName)) {
    return;
}

if (defined('PRESENOVA_GLOBAL_ERROR_HANDLER_ACTIVE')) {
    return;
}
define('PRESENOVA_GLOBAL_ERROR_HANDLER_ACTIVE', true);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

/**
 * Prevent recursive redirects if another error occurs while rendering fallback.
 */
function presenova_redirect_to_404(): void
{
    static $isHandling = false;
    if ($isHandling) {
        return;
    }
    $isHandling = true;

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    if (!headers_sent()) {
        header('Location: /presenova/404.php', true, 302);
        exit;
    }

    http_response_code(404);
    $fallback404 = __DIR__ . '/../404.php';
    if (is_file($fallback404)) {
        require $fallback404;
    } else {
        echo '404 Not Found';
    }
    exit;
}

function presenova_log_php_error(string $type, string $message, string $file, int $line): void
{
    $logDir = __DIR__ . '/../uploads/temp';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $entry = sprintf(
        "[%s] %s: %s in %s:%d%s",
        date('c'),
        $type,
        $message,
        $file,
        $line,
        PHP_EOL
    );

    @file_put_contents($logDir . '/php_error_redirect.log', $entry, FILE_APPEND | LOCK_EX);
}

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    presenova_log_php_error('ERROR', $message, $file, $line);
    presenova_redirect_to_404();
    return true;
});

set_exception_handler(static function (Throwable $exception): void {
    presenova_log_php_error(
        'EXCEPTION',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );
    presenova_redirect_to_404();
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
        E_RECOVERABLE_ERROR
    ];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    presenova_log_php_error(
        'FATAL',
        (string)$error['message'],
        (string)$error['file'],
        (int)$error['line']
    );
    presenova_redirect_to_404();
});
