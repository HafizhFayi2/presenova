<?php

if (!function_exists('legacy_load_env_file')) {
    /**
     * Minimal .env loader fallback when vlucas/phpdotenv is unavailable.
     */
    function legacy_load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (stripos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '') {
                continue;
            }

            // Remove UTF-8 BOM on first key if present.
            if (strncmp($key, "\xEF\xBB\xBF", 3) === 0) {
                $key = substr($key, 3);
            }

            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

// Load Laravel .env so legacy scripts can share one configuration source.
$projectRoot = dirname(__DIR__, 2);
$autoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
static $legacyEnvLoaded = false;
if (!$legacyEnvLoaded && is_file($envPath)) {
    $loadedByDotenv = false;

    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
            $loadedByDotenv = true;
        }
    }

    if (!$loadedByDotenv) {
        legacy_load_env_file($envPath);
    }

    $legacyEnvLoaded = true;
}

if (!function_exists('legacy_env')) {
    function legacy_env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

$siteUrlDefault = rtrim((string) legacy_env('APP_URL', 'http://localhost/presenova'), '/') . '/';
$deepfaceVenvPython = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'face' . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
$pythonBinDefault = is_file($deepfaceVenvPython) ? $deepfaceVenvPython : 'python';
$pythonBinEnv = (string) legacy_env('LEGACY_PYTHON_BIN', $pythonBinDefault);

// Normalize python path from .env (relative path should resolve from project root).
$pythonBinNormalized = $pythonBinEnv;
$pythonEnvLooksAbsolute = (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $pythonBinEnv) || strpos($pythonBinEnv, '/') === 0;
if (!$pythonEnvLooksAbsolute && $pythonBinEnv !== '' && strpos($pythonBinEnv, 'python') !== 0) {
    $candidatePython = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pythonBinEnv);
    if (is_file($candidatePython)) {
        $pythonBinNormalized = $candidatePython;
    }
}

// Konfigurasi Database
if (!defined('DB_HOST')) define('DB_HOST', (string) legacy_env('DB_HOST', '127.0.0.1'));
if (!defined('DB_USER')) define('DB_USER', (string) legacy_env('DB_USERNAME', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', (string) legacy_env('DB_PASSWORD', ''));
if (!defined('DB_NAME')) define('DB_NAME', (string) legacy_env('DB_DATABASE', 'presenova'));

// Konfigurasi Sistem
if (!defined('SITE_NAME')) define('SITE_NAME', (string) legacy_env('LEGACY_SITE_NAME', 'Absensi Online SMK'));
if (!defined('SITE_URL')) define('SITE_URL', (string) legacy_env('LEGACY_SITE_URL', $siteUrlDefault));

// Konfigurasi Absensi
if (!defined('ATTENDANCE_RADIUS')) define('ATTENDANCE_RADIUS', (int) legacy_env('LEGACY_ATTENDANCE_RADIUS', 100));
if (!defined('FACE_MATCH_THRESHOLD')) define('FACE_MATCH_THRESHOLD', (int) legacy_env('LEGACY_FACE_MATCH_THRESHOLD', 89));
if (!defined('FACE_DESCRIPTOR_DISTANCE_THRESHOLD')) define('FACE_DESCRIPTOR_DISTANCE_THRESHOLD', (float) legacy_env('LEGACY_FACE_DESCRIPTOR_THRESHOLD', 0.55));
if (!defined('FACE_MATCH_MODEL')) define('FACE_MATCH_MODEL', (string) legacy_env('LEGACY_FACE_MATCH_MODEL', 'SFace'));
if (!defined('FACE_MATCH_DETECTOR')) define('FACE_MATCH_DETECTOR', (string) legacy_env('LEGACY_FACE_MATCH_DETECTOR', 'opencv'));
if (!defined('FACE_MATCH_DISTANCE_METRIC')) define('FACE_MATCH_DISTANCE_METRIC', (string) legacy_env('LEGACY_FACE_MATCH_DISTANCE_METRIC', 'cosine'));
if (!defined('FACE_MATCH_ENFORCE_DETECTION')) define(
    'FACE_MATCH_ENFORCE_DETECTION',
    filter_var((string) legacy_env('LEGACY_FACE_MATCH_ENFORCE_DETECTION', 'true'), FILTER_VALIDATE_BOOLEAN)
);
if (!defined('FACE_MATCH_MAX_REFERENCES')) define('FACE_MATCH_MAX_REFERENCES', (int) legacy_env('LEGACY_FACE_MATCH_MAX_REFERENCES', 1));
if (!defined('FACE_MATCH_ALLOW_LEGACY')) define(
    'FACE_MATCH_ALLOW_LEGACY',
    filter_var((string) legacy_env('LEGACY_FACE_MATCH_ALLOW_LEGACY', 'false'), FILTER_VALIDATE_BOOLEAN)
);
if (!defined('FACE_MATCH_USE_BACKUP')) define(
    'FACE_MATCH_USE_BACKUP',
    filter_var((string) legacy_env('LEGACY_FACE_MATCH_USE_BACKUP', 'true'), FILTER_VALIDATE_BOOLEAN)
);
if (!defined('FACE_MATCH_BACKUP_MODEL')) define('FACE_MATCH_BACKUP_MODEL', (string) legacy_env('LEGACY_FACE_MATCH_BACKUP_MODEL', 'SFace'));
if (!defined('FACE_MATCH_BACKUP_DETECTOR')) define('FACE_MATCH_BACKUP_DETECTOR', (string) legacy_env('LEGACY_FACE_MATCH_BACKUP_DETECTOR', 'mtcnn'));
if (!defined('FACE_MATCH_BACKUP_MAX_REFERENCES')) define('FACE_MATCH_BACKUP_MAX_REFERENCES', (int) legacy_env('LEGACY_FACE_MATCH_BACKUP_MAX_REFERENCES', 1));
if (!defined('FACE_MATCH_DETECTOR_FALLBACKS')) define(
    'FACE_MATCH_DETECTOR_FALLBACKS',
    filter_var((string) legacy_env('LEGACY_FACE_MATCH_DETECTOR_FALLBACKS', 'false'), FILTER_VALIDATE_BOOLEAN)
);
if (!defined('FACE_MATCH_TIMEOUT_SECONDS')) define('FACE_MATCH_TIMEOUT_SECONDS', (int) legacy_env('LEGACY_FACE_MATCH_TIMEOUT_SECONDS', 60));
if (!defined('TIME_TOLERANCE_MINUTES')) define('TIME_TOLERANCE_MINUTES', (int) legacy_env('LEGACY_TIME_TOLERANCE_MINUTES', 15));
if (!defined('SCHOOL_LATITUDE')) define('SCHOOL_LATITUDE', (float) legacy_env('LEGACY_SCHOOL_LATITUDE', -6.3519595510017455));
if (!defined('SCHOOL_LONGITUDE')) define('SCHOOL_LONGITUDE', (float) legacy_env('LEGACY_SCHOOL_LONGITUDE', 107.10615744323621));

// Python runtime (untuk face matching server-side)
if (!defined('PYTHON_BIN')) define('PYTHON_BIN', $pythonBinNormalized);

// Google Drive API (untuk upload foto ke cloud)
if (!defined('GOOGLE_DRIVE_FOLDER_ID')) define('GOOGLE_DRIVE_FOLDER_ID', (string) legacy_env('LEGACY_GOOGLE_DRIVE_FOLDER_ID', 'YOUR_GOOGLE_DRIVE_FOLDER_ID'));

// Google Maps API (untuk peta di Admin)
if (!defined('GOOGLE_MAPS_API_KEY')) define('GOOGLE_MAPS_API_KEY', (string) legacy_env('LEGACY_GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY'));

// JWT Secret
if (!defined('JWT_SECRET')) define('JWT_SECRET', (string) legacy_env('LEGACY_JWT_SECRET', 'absensi_smk_secret_key_2024'));
if (!defined('JWT_EXPIRE')) define('JWT_EXPIRE', (int) legacy_env('LEGACY_JWT_EXPIRE', 30));

// Password Hash Salt (HARUS SAMA dengan yang ada di database!)
if (!defined('PASSWORD_SALT')) define('PASSWORD_SALT', (string) legacy_env('LEGACY_PASSWORD_SALT', '$%DSuTyr47542@#&*!=QxR094{a911}+'));

// Debug mode
$debugMode = filter_var((string) legacy_env('LEGACY_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN);
if (!defined('DEBUG')) define('DEBUG', $debugMode);

// Timezone
date_default_timezone_set((string) legacy_env('APP_TIMEZONE', 'Asia/Jakarta'));

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
}
