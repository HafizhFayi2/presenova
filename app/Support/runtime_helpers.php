<?php

use App\Services\FaceMatcherService;
use App\Services\StudentPushNotificationService;
use App\Support\Core\Auth;
use App\Support\Core\Database;
use App\Support\Core\DatabaseHelper;
use Illuminate\Support\Facades\DB;

if (!function_exists('runtime_env')) {
    function runtime_env(string $key, mixed $default = null): mixed
    {
        $value = env($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!defined('DB_HOST')) {
    define('DB_HOST', (string) runtime_env('DB_HOST', '127.0.0.1'));
}
if (!defined('DB_USER')) {
    define('DB_USER', (string) runtime_env('DB_USERNAME', 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', (string) runtime_env('DB_PASSWORD', ''));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', (string) runtime_env('DB_DATABASE', 'presenova'));
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', public_path());
}
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', base_path());
}
if (!defined('SITE_NAME')) {
    define('SITE_NAME', (string) runtime_env('SITE_NAME', 'Absensi Online SMK'));
}
if (!defined('SITE_URL')) {
    $siteUrl = trim((string) runtime_env('SITE_URL', ''));
    $siteUrlHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
    $siteUrlIsLoopback = in_array($siteUrlHost, ['localhost', '127.0.0.1', '::1'], true);
    if ($siteUrl === '' || $siteUrlIsLoopback) {
        try {
            $siteUrl = (string) url('/');
        } catch (\Throwable) {
            $siteUrl = (string) config('app.url');
        }
    }
    define('SITE_URL', rtrim($siteUrl, '/') . '/');
}
if (!defined('ATTENDANCE_RADIUS')) {
    define('ATTENDANCE_RADIUS', (int) runtime_env('ATTENDANCE_RADIUS', 100));
}
if (!defined('FACE_MATCH_THRESHOLD')) {
    define('FACE_MATCH_THRESHOLD', (int) runtime_env('FACE_MATCH_THRESHOLD', 89));
}
if (!defined('FACE_DESCRIPTOR_DISTANCE_THRESHOLD')) {
    define('FACE_DESCRIPTOR_DISTANCE_THRESHOLD', (float) runtime_env('FACE_DESCRIPTOR_THRESHOLD', 0.55));
}
if (!defined('FACE_MATCH_MODEL')) {
    define('FACE_MATCH_MODEL', (string) runtime_env('FACE_MATCH_MODEL', 'SFace'));
}
if (!defined('FACE_MATCH_DETECTOR')) {
    define('FACE_MATCH_DETECTOR', (string) runtime_env('FACE_MATCH_DETECTOR', 'opencv'));
}
if (!defined('FACE_MATCH_DISTANCE_METRIC')) {
    define('FACE_MATCH_DISTANCE_METRIC', (string) runtime_env('FACE_MATCH_DISTANCE_METRIC', 'cosine'));
}
if (!defined('FACE_MATCH_ENFORCE_DETECTION')) {
    define(
        'FACE_MATCH_ENFORCE_DETECTION',
        filter_var((string) runtime_env('FACE_MATCH_ENFORCE_DETECTION', 'true'), FILTER_VALIDATE_BOOLEAN)
    );
}
if (!defined('FACE_MATCH_MAX_REFERENCES')) {
    define('FACE_MATCH_MAX_REFERENCES', (int) runtime_env('FACE_MATCH_MAX_REFERENCES', 1));
}
if (!defined('FACE_MATCH_ALLOW_FALLBACK')) {
    define(
        'FACE_MATCH_ALLOW_FALLBACK',
        filter_var((string) runtime_env('FACE_MATCH_ALLOW_FALLBACK', 'false'), FILTER_VALIDATE_BOOLEAN)
    );
}
if (!defined('FACE_MATCH_USE_BACKUP')) {
    define(
        'FACE_MATCH_USE_BACKUP',
        filter_var((string) runtime_env('FACE_MATCH_USE_BACKUP', 'true'), FILTER_VALIDATE_BOOLEAN)
    );
}
if (!defined('FACE_MATCH_BACKUP_MODEL')) {
    define('FACE_MATCH_BACKUP_MODEL', (string) runtime_env('FACE_MATCH_BACKUP_MODEL', 'SFace'));
}
if (!defined('FACE_MATCH_BACKUP_DETECTOR')) {
    define('FACE_MATCH_BACKUP_DETECTOR', (string) runtime_env('FACE_MATCH_BACKUP_DETECTOR', 'mtcnn'));
}
if (!defined('FACE_MATCH_BACKUP_MAX_REFERENCES')) {
    define('FACE_MATCH_BACKUP_MAX_REFERENCES', (int) runtime_env('FACE_MATCH_BACKUP_MAX_REFERENCES', 1));
}
if (!defined('FACE_MATCH_DETECTOR_FALLBACKS')) {
    define(
        'FACE_MATCH_DETECTOR_FALLBACKS',
        filter_var((string) runtime_env('FACE_MATCH_DETECTOR_FALLBACKS', 'false'), FILTER_VALIDATE_BOOLEAN)
    );
}
if (!defined('FACE_MATCH_TIMEOUT_SECONDS')) {
    define('FACE_MATCH_TIMEOUT_SECONDS', (int) runtime_env('FACE_MATCH_TIMEOUT_SECONDS', 60));
}

$deepfaceVenvPython = public_path('face/.venv/Scripts/python.exe');
$pythonBinDefault = is_file($deepfaceVenvPython) ? $deepfaceVenvPython : 'python';
if (!defined('PYTHON_BIN')) {
    define('PYTHON_BIN', (string) runtime_env('PYTHON_BIN', $pythonBinDefault));
}

$jwtRememberSecret = (string) runtime_env('JWT_REMEMBER_SECRET', '');
if ($jwtRememberSecret === '') {
    $jwtRememberSecret = (string) runtime_env('JWT_SECRET', '');
}
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', $jwtRememberSecret);
}
if (!defined('JWT_EXPIRE')) {
    define('JWT_EXPIRE', (int) runtime_env('JWT_EXPIRE_DAYS', 30));
}
if (!defined('PASSWORD_SALT')) {
    define('PASSWORD_SALT', (string) runtime_env('PASSWORD_SALT', '$%DSuTyr47542@#&*!=QxR094{a911}+'));
}
if (!defined('DEBUG')) {
    define('DEBUG', filter_var((string) runtime_env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN));
}
date_default_timezone_set((string) runtime_env('APP_TIMEZONE', 'Asia/Jakarta'));

if (!class_exists('Database')) {
    class_alias(Database::class, 'Database');
}
if (!class_exists('Auth')) {
    class_alias(Auth::class, 'Auth');
}
if (!class_exists('DatabaseHelper')) {
    class_alias(DatabaseHelper::class, 'DatabaseHelper');
}
if (!class_exists('FaceMatcher')) {
    class_alias(FaceMatcherService::class, 'FaceMatcher');
}

if (!function_exists('clear_output_buffers_for_binary_download')) {
    function clear_output_buffers_for_binary_download(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}

if (!function_exists('storage_slug')) {
    function storage_slug($text, $default = 'item')
    {
        $text = trim((string) $text);
        if ($text === '') {
            return $default;
        }
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', (string) $text);
        if ($converted !== false) {
            $text = $converted;
        }
        $text = preg_replace('~[^-\w]+~', '', (string) $text);
        $text = trim((string) $text, '-');
        $text = preg_replace('~-+~', '-', (string) $text);
        $text = strtolower((string) $text);

        return $text !== '' ? $text : $default;
    }
}

if (!function_exists('storage_class_folder')) {
    function storage_class_folder($className)
    {
        return storage_slug($className, 'kelas');
    }
}

if (!function_exists('storage_student_folder')) {
    function storage_student_folder($studentName)
    {
        return storage_slug($studentName, 'siswa');
    }
}

if (!function_exists('storage_indonesian_day_name')) {
    function storage_indonesian_day_name($date)
    {
        $date = trim((string) $date);
        if ($date === '') {
            return 'hari';
        }
        try {
            $dt = new DateTime($date);
        } catch (Exception) {
            return 'hari';
        }
        $names = ['', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
        $idx = (int) $dt->format('N');

        return $names[$idx] ?? 'hari';
    }
}

if (!function_exists('storage_attendance_datetime_folder')) {
    function storage_attendance_datetime_folder($date, $time)
    {
        $datePart = '';
        $timePart = '';
        if ($date) {
            $timestamp = strtotime((string) $date);
            if ($timestamp !== false) {
                $datePart = date('Y-m-d', $timestamp);
            }
        }
        if ($time) {
            $digits = preg_replace('/\D/', '', (string) $time);
            if (strlen((string) $digits) >= 6) {
                $timePart = substr((string) $digits, 0, 6);
            } elseif (strlen((string) $digits) > 0) {
                $timePart = str_pad((string) $digits, 6, '0', STR_PAD_RIGHT);
            }
        }
        if ($datePart === '') {
            $datePart = date('Y-m-d');
        }
        if ($timePart === '') {
            $timePart = date('His');
        }

        return $datePart . '_' . $timePart;
    }
}

if (!function_exists('storage_attendance_basename')) {
    function storage_attendance_basename($studentName, $nisn, $date)
    {
        $studentSlug = storage_slug($studentName, 'siswa');
        $nisn = trim((string) $nisn);
        $dayName = storage_indonesian_day_name($date);
        $parts = [$studentSlug];
        if ($nisn !== '') {
            $parts[] = $nisn;
        }
        $parts[] = $dayName;

        return implode('-', $parts);
    }
}

if (!function_exists('storage_face_reference_filename')) {
    function storage_face_reference_filename($nisn, $studentName, $extension = 'jpg')
    {
        $studentSlug = storage_slug($studentName, 'siswa');
        $nisn = trim((string) $nisn);
        $ext = strtolower(trim((string) $extension));
        if ($ext === '') {
            $ext = 'jpg';
        }
        $base = $nisn !== '' ? ($nisn . '-' . $studentSlug) : $studentSlug;

        return $base . '.' . $ext;
    }
}

if (!function_exists('normalize_public_relative_path')) {
    function normalize_public_relative_path($path)
    {
        $path = trim((string) $path);
        if ($path === '' || stripos($path, 'data:') === 0) {
            return '';
        }

        if (preg_match('~^https?://~i', $path) === 1) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsedPath) ? $parsedPath : '';
        }

        $path = str_replace('\\', '/', $path);
        $pathNoFragment = strtok($path, '?#');
        $path = $pathNoFragment === false ? '' : (string) $pathNoFragment;
        if ($path === '') {
            return '';
        }

        if (preg_match('~^[A-Za-z]:/~', $path) === 1 || str_starts_with($path, '/')) {
            $lowerPath = strtolower($path);
            $publicMarker = strpos($lowerPath, '/public/');
            if ($publicMarker !== false) {
                $path = substr($path, $publicMarker + strlen('/public/'));
            }
        }

        $path = ltrim($path, '/');
        if (str_starts_with(strtolower($path), 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        $segments = explode('/', $path);
        $cleanSegments = [];
        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($cleanSegments);
                continue;
            }
            $cleanSegments[] = $segment;
        }

        return implode('/', $cleanSegments);
    }
}

if (!function_exists('normalize_face_reference_path')) {
    function normalize_face_reference_path($photoReference)
    {
        $path = normalize_public_relative_path($photoReference);
        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);
        $lower = strtolower($path);
        $uploadsMarker = 'uploads/faces/';
        $uploadsPos = strpos($lower, $uploadsMarker);
        if ($uploadsPos !== false) {
            $path = substr($path, $uploadsPos + strlen($uploadsMarker));
        } elseif (str_starts_with($lower, 'faces/')) {
            $path = substr($path, strlen('faces/'));
        }

        return normalize_public_relative_path($path);
    }
}

if (!function_exists('face_reference_relative_from_file')) {
    function face_reference_relative_from_file($filePath)
    {
        $filePath = trim((string) $filePath);
        if ($filePath === '') {
            return '';
        }

        $realFile = realpath($filePath);
        $normalizedFile = str_replace('\\', '/', $realFile !== false ? $realFile : $filePath);
        if ($normalizedFile === '') {
            return '';
        }

        $facesRoot = realpath(public_path('uploads/faces'));
        if ($facesRoot !== false) {
            $normalizedFacesRoot = rtrim(str_replace('\\', '/', $facesRoot), '/');
            if (str_starts_with($normalizedFile, $normalizedFacesRoot . '/')) {
                $relative = substr($normalizedFile, strlen($normalizedFacesRoot) + 1);

                return normalize_face_reference_path($relative);
            }
        }

        $marker = '/uploads/faces/';
        $markerPos = stripos($normalizedFile, $marker);
        if ($markerPos !== false) {
            $relative = substr($normalizedFile, $markerPos + strlen($marker));

            return normalize_face_reference_path($relative);
        }

        return '';
    }
}

if (!function_exists('resolve_face_reference_file_path')) {
    function resolve_face_reference_file_path($photoReference)
    {
        $raw = trim((string) $photoReference);
        if ($raw === '') {
            return null;
        }

        $rawNormalized = str_replace('\\', '/', $raw);
        if ((preg_match('~^[A-Za-z]:/~', $rawNormalized) === 1 || str_starts_with($rawNormalized, '/')) && is_file($raw)) {
            $real = realpath($raw);
            return $real !== false ? $real : $raw;
        }

        $normalized = normalize_face_reference_path($raw);
        if ($normalized === '') {
            return null;
        }

        $candidates = [
            public_path('uploads/faces/' . $normalized),
            public_path($normalized),
            public_path('uploads/' . $normalized),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                $real = realpath($candidate);
                return $real !== false ? $real : $candidate;
            }
        }

        if (!str_contains($normalized, '/')) {
            $facesRoot = public_path('uploads/faces');
            if (is_dir($facesRoot)) {
                try {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($facesRoot, FilesystemIterator::SKIP_DOTS)
                    );
                    foreach ($iterator as $fileInfo) {
                        if (!$fileInfo->isFile()) {
                            continue;
                        }
                        if (strcasecmp($fileInfo->getFilename(), $normalized) !== 0) {
                            continue;
                        }
                        $real = realpath($fileInfo->getPathname());
                        return $real !== false ? $real : $fileInfo->getPathname();
                    }
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }
}

if (!function_exists('face_reference_public_url')) {
    function face_reference_public_url($photoReference, $appendVersion = true)
    {
        $absolutePath = resolve_face_reference_file_path($photoReference);
        if ($absolutePath === null) {
            return '';
        }

        $relativePath = face_reference_relative_from_file($absolutePath);
        if ($relativePath === '') {
            return '';
        }

        $url = (string) url('uploads/faces/' . ltrim($relativePath, '/'));
        if ($appendVersion) {
            $version = @filemtime($absolutePath);
            if ($version) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $version;
            }
        }

        return $url;
    }
}

if (!function_exists('calculateDistance')) {
    function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $latFrom = deg2rad((float) $lat1);
        $lonFrom = deg2rad((float) $lon1);
        $latTo = deg2rad((float) $lat2);
        $lonTo = deg2rad((float) $lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}

if (!function_exists('compressImage')) {
    function compressImage($source, $destination, $quality)
    {
        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            return false;
        }
        $info = @getimagesize((string) $source);
        if (!is_array($info) || !isset($info['mime'])) {
            return false;
        }

        if ($info['mime'] === 'image/jpeg') {
            $image = @imagecreatefromjpeg((string) $source);
            if (!$image) {
                return false;
            }
            imagejpeg($image, (string) $destination, (int) $quality);
            imagedestroy($image);

            return true;
        }
        if ($info['mime'] === 'image/png') {
            $image = @imagecreatefrompng((string) $source);
            if (!$image) {
                return false;
            }
            $qualityPng = 9 - round((int) $quality / 10);
            $qualityPng = max(0, min(9, (int) $qualityPng));
            imagepng($image, (string) $destination, $qualityPng);
            imagedestroy($image);

            return true;
        }
        if ($info['mime'] === 'image/gif') {
            $image = @imagecreatefromgif((string) $source);
            if (!$image) {
                return false;
            }
            imagegif($image, (string) $destination);
            imagedestroy($image);

            return true;
        }

        return false;
    }
}

if (!function_exists('getClientIP')) {
    function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return (string) $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return (string) $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? request()->ip() ?? '127.0.0.1');
    }
}

if (!function_exists('logActivity')) {
    function logActivity($user_id, $user_type, $action, $details = '')
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => $user_id ?: null,
                'user_type' => (string) $user_type,
                'action' => (string) $action,
                'details' => (string) $details,
                'ip_address' => getClientIP(),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? request()->userAgent() ?? ''),
                'created_at' => now(),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

if (!function_exists('pushNotifyStudent')) {
    function pushNotifyStudent(
        $studentId,
        $type,
        $title,
        $body,
        $url = '/dashboard/siswa.php?page=jadwal',
        $scheduleId = null,
        $scheduledAt = null
    ) {
        $studentId = (int) $studentId;
        $type = trim((string) $type);
        $title = trim((string) $title);
        $body = trim((string) $body);
        if ($studentId <= 0 || $type === '' || $title === '') {
            return false;
        }

        try {
            /** @var StudentPushNotificationService $service */
            $service = app(StudentPushNotificationService::class);
            $result = $service->notifyStudent(
                $studentId,
                $type,
                $title,
                $body,
                (string) $url,
                $scheduleId !== null ? (int) $scheduleId : null,
                $scheduledAt
            );

            return !empty($result['ok']) || !empty($result['duplicate']);
        } catch (\Throwable) {
            return false;
        }
    }
}

if (!function_exists('pushNotifyStudents')) {
    function pushNotifyStudents(
        $studentIds,
        $type,
        $title,
        $body,
        $url = '/dashboard/siswa.php?page=jadwal',
        $scheduleId = null,
        $scheduledAt = null
    ) {
        $ids = is_array($studentIds) ? $studentIds : [];
        $type = trim((string) $type);
        $title = trim((string) $title);
        if ($ids === [] || $type === '' || $title === '') {
            return 0;
        }

        try {
            /** @var StudentPushNotificationService $service */
            $service = app(StudentPushNotificationService::class);

            return $service->notifyStudents(
                array_map(static fn ($id): int => (int) $id, $ids),
                $type,
                $title,
                (string) $body,
                (string) $url,
                $scheduleId !== null ? (int) $scheduleId : null,
                $scheduledAt
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}

if (!function_exists('auditMasterData')) {
    function auditMasterData($actorId, $actorRole, $entityType, $entityId, $action, $before = null, $after = null, $meta = [])
    {
        try {
            DB::table('master_data_audit_logs')->insert([
                'actor_id' => $actorId !== null ? (string) $actorId : null,
                'actor_role' => (string) $actorRole,
                'entity_type' => (string) $entityType,
                'entity_id' => $entityId !== null ? (string) $entityId : null,
                'action' => (string) $action,
                'before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                'after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
                'meta_json' => json_encode(array_merge([
                    'ip_address' => getClientIP(),
                    'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? request()->userAgent() ?? ''),
                ], is_array($meta) ? $meta : []), JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

if (!function_exists('validateJWT')) {
    function validateJWT($token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $secret = (string) (JWT_SECRET ?: '');
        if ($secret === '') {
            return false;
        }

        $rawSignature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true);
        $expectedSignature = rtrim(strtr(base64_encode($rawSignature), '+/', '-_'), '=');
        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return false;
        }

        $base64 = strtr($encodedPayload, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }
        $payloadJson = base64_decode($base64, true);
        if ($payloadJson === false) {
            return false;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return false;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $exp < time()) {
            return false;
        }

        return $payload;
    }
}

if (!function_exists('getSetting')) {
    function getSetting($key)
    {
        static $settings = null;
        if ($settings === null) {
            try {
                $settings = DB::table('site')->first();
            } catch (\Throwable) {
                $settings = null;
            }
        }
        if ($settings === null) {
            return null;
        }

        $key = (string) $key;
        if ($key === '') {
            return null;
        }

        return $settings->{$key} ?? null;
    }
}

if (!function_exists('getJpDurationMinutes')) {
    function getJpDurationMinutes($jp)
    {
        return ((int) $jp === 5 || (int) $jp === 9) ? 15 : 45;
    }
}

if (!function_exists('getTimeToleranceMinutes')) {
    function getTimeToleranceMinutes($db)
    {
        try {
            $stmt = $db->query("SELECT time_tolerance FROM site LIMIT 1");
            $row = $stmt ? $stmt->fetch() : null;
            $minutes = isset($row['time_tolerance']) ? (int) $row['time_tolerance'] : 0;

            return max(0, $minutes);
        } catch (Exception) {
            return 0;
        }
    }
}

if (!function_exists('parseJpRangeFromShift')) {
    function parseJpRangeFromShift($shift_name)
    {
        if (preg_match('/JP(\d+)-JP(\d+)/i', (string) $shift_name, $m)) {
            return [intval($m[1]), intval($m[2])];
        }

        return null;
    }
}

if (!function_exists('calculateJpTimeRange')) {
    function calculateJpTimeRange($jp_start, $jp_end, $base_start = '07:00', $pre_minutes = 0, $tolerance_minutes = 0)
    {
        $jp_start = intval($jp_start);
        $jp_end = intval($jp_end);
        if ($jp_start < 1 || $jp_start > 12 || $jp_end < 1 || $jp_end > 12 || $jp_end < $jp_start) {
            return null;
        }

        $base = DateTime::createFromFormat('H:i', (string) $base_start);
        if (!$base) {
            $base = DateTime::createFromFormat('H:i:s', (string) $base_start);
        }
        if (!$base) {
            return null;
        }

        $pre_minutes = max(0, intval($pre_minutes));
        if ($pre_minutes > 0) {
            $base->modify('+' . $pre_minutes . ' minutes');
        }

        $time_in_obj = clone $base;
        $minutes_before = 0;
        for ($jp = 1; $jp < $jp_start; $jp++) {
            $minutes_before += getJpDurationMinutes($jp);
        }
        if ($minutes_before > 0) {
            $time_in_obj->modify('+' . $minutes_before . ' minutes');
        }

        $duration_minutes = 0;
        for ($jp = $jp_start; $jp <= $jp_end; $jp++) {
            $duration_minutes += getJpDurationMinutes($jp);
        }

        $time_out_obj = clone $time_in_obj;
        $time_out_obj->modify('+' . $duration_minutes . ' minutes');

        $tolerance_minutes = max(0, (int) $tolerance_minutes);
        if ($tolerance_minutes > 0) {
            $time_out_obj->modify('+' . $tolerance_minutes . ' minutes');
        }

        return [$time_in_obj->format('H:i:s'), $time_out_obj->format('H:i:s')];
    }
}

if (!function_exists('getDefaultDayId')) {
    function getDefaultDayId($db)
    {
        try {
            $row = $db->query(
                "SELECT d.day_id
                 FROM day d
                 LEFT JOIN day_schedule_config cfg ON cfg.day_id = d.day_id
                 WHERE d.is_active = 'Y'
                 ORDER BY COALESCE(cfg.activity1_minutes, 0) + COALESCE(cfg.activity2_minutes, 0) ASC,
                          d.day_order ASC
                 LIMIT 1"
            )?->fetch();
            if ($row && isset($row['day_id'])) {
                return (int) $row['day_id'];
            }
        } catch (Exception) {
            // Ignore query errors and use fallback.
        }

        return 1;
    }
}

if (!function_exists('getDayScheduleConfig')) {
    function getDayScheduleConfig($db, $day_id)
    {
        $day_id = intval($day_id);
        $default = [
            'school_start_time' => '06:30:00',
            'activity1_label' => '',
            'activity1_minutes' => 0,
            'activity2_label' => '',
            'activity2_minutes' => 0,
            'pre_minutes' => 0,
        ];

        if ($day_id <= 0) {
            return $default;
        }

        static $cache = [];
        if (isset($cache[$day_id])) {
            return $cache[$day_id];
        }

        try {
            $row = $db->query(
                "SELECT school_start_time, activity1_label, activity1_minutes, activity2_label, activity2_minutes
                 FROM day_schedule_config WHERE day_id = ?",
                [$day_id]
            )?->fetch();
        } catch (Exception) {
            $row = null;
        }

        if (!$row) {
            $cache[$day_id] = $default;

            return $default;
        }

        $activity1_minutes = max(0, (int) ($row['activity1_minutes'] ?? 0));
        $activity2_minutes = max(0, (int) ($row['activity2_minutes'] ?? 0));
        $config = [
            'school_start_time' => $row['school_start_time'] ?? $default['school_start_time'],
            'activity1_label' => trim((string) ($row['activity1_label'] ?? '')),
            'activity1_minutes' => $activity1_minutes,
            'activity2_label' => trim((string) ($row['activity2_label'] ?? '')),
            'activity2_minutes' => $activity2_minutes,
            'pre_minutes' => $activity1_minutes + $activity2_minutes,
        ];

        $cache[$day_id] = $config;

        return $config;
    }
}

if (!function_exists('calculateJpTimeRangeForDay')) {
    function calculateJpTimeRangeForDay($db, $jp_start, $jp_end, $day_id)
    {
        $config = getDayScheduleConfig($db, $day_id);
        $tolerance = getTimeToleranceMinutes($db);

        return calculateJpTimeRange($jp_start, $jp_end, $config['school_start_time'], $config['pre_minutes'], $tolerance);
    }
}

if (!function_exists('calculateJpTimeRangeFromShiftForDay')) {
    function calculateJpTimeRangeFromShiftForDay($db, $shift_name, $day_id)
    {
        $range = parseJpRangeFromShift($shift_name);
        if (!$range) {
            return null;
        }

        return calculateJpTimeRangeForDay($db, $range[0], $range[1], $day_id);
    }
}

if (!function_exists('buildScheduleWindow')) {
    function buildScheduleWindow($schedule_date, $time_in, $time_out, $tz = null, $tolerance_minutes = 0)
    {
        $tz = $tz ?: new DateTimeZone('Asia/Jakarta');
        $schedule_date = $schedule_date ?: date('Y-m-d');
        $time_in = $time_in ?: '00:00:00';
        $time_out = $time_out ?: '00:00:00';

        $start = new DateTime($schedule_date . ' ' . $time_in, $tz);
        $end = new DateTime($schedule_date . ' ' . $time_out, $tz);
        if ($end <= $start) {
            $end->modify('+1 day');
        }

        $tolerance_minutes = max(0, (int) $tolerance_minutes);
        $base_end = clone $end;
        if ($tolerance_minutes > 0) {
            $base_end->modify('-' . $tolerance_minutes . ' minutes');
        }
        if ($base_end < $start) {
            $base_end = clone $start;
        }

        return [$start, $end, $base_end];
    }
}


