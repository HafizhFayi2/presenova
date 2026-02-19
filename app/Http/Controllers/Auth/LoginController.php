<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->query->has('logout')) {
            return $this->logout($request, false);
        }

        if ($this->hasSessionTimeout($request)) {
            $this->destroyLoginState($request);

            return redirect($this->appPath('login.php?timeout=1'));
        }

        $this->touchActivityTimestamp($request);

        if (!$request->query->has('timeout') && $this->isLoggedIn()) {
            return redirect($this->roleDashboardPath((string) session('role', '')));
        }

        return $this->renderLoginPage($request);
    }

    public function authenticate(Request $request): Response|RedirectResponse
    {
        if (strtoupper((string) $request->method()) !== 'POST') {
            return redirect($this->appPath('login.php'));
        }

        $role = (string) $request->input('role', 'siswa');
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $remember = $request->has('remember');

        if ($username === '' || $password === '') {
            return $this->renderLoginPage($request, 'Username dan password harus diisi!');
        }

        if ($role === 'admin') {
            $admin = DB::table('user')
                ->where('username', $username)
                ->whereIn('level', [1, 2])
                ->first();

            if (!$admin || !$this->verifyPassword($password, (string) ($admin->password ?? ''))) {
                return $this->renderLoginPage($request, 'Username atau password admin salah!');
            }

            if (property_exists($admin, 'is_active') && (string) ($admin->is_active ?? 'Y') !== 'Y') {
                return $this->renderLoginPage($request, 'Akun admin tidak aktif.');
            }

            DB::table('user')
                ->where('user_id', (int) $admin->user_id)
                ->update(['last_login' => now()]);

            $this->syncSession([
                'user_id' => (int) $admin->user_id,
                'username' => (string) ($admin->username ?? ''),
                'fullname' => (string) ($admin->fullname ?? ''),
                'level' => (int) ($admin->level ?? 0),
                'role' => 'admin',
                'logged_in' => true,
            ]);

            $this->applyRememberCookie($remember, 'admin', (int) $admin->user_id);
            $this->touchActivityTimestamp($request, true);

            return redirect($this->appPath('dashboard/admin.php'));
        }

        if ($role === 'guru') {
            $teacher = DB::table('teacher')
                ->where('teacher_code', $username)
                ->orWhere('teacher_username', $username)
                ->first();

            if (!$teacher || !$this->verifyPassword($password, (string) ($teacher->teacher_password ?? ''))) {
                return $this->renderLoginPage($request, 'Kode guru atau password salah!');
            }

            DB::table('teacher')
                ->where('id', (int) $teacher->id)
                ->update(['created_login' => now()]);

            $this->syncSession([
                'teacher_id' => (int) $teacher->id,
                'teacher_code' => (string) ($teacher->teacher_code ?? ''),
                'teacher_name' => (string) ($teacher->teacher_name ?? ''),
                'role' => 'guru',
                'level' => 2,
                'logged_in' => true,
            ]);

            $this->applyRememberCookie($remember, 'guru', (int) $teacher->id);
            $this->touchActivityTimestamp($request, true);

            return redirect($this->appPath('dashboard/guru.php'));
        }

        $studentNisn = preg_replace('/\s+/', '', $username);
        $student = DB::table('student')
            ->where('student_nisn', $studentNisn)
            ->first();

        if (!$student || !$this->verifyPassword($password, (string) ($student->student_password ?? ''))) {
            return $this->renderLoginPage($request, 'NISN atau password salah');
        }

        $hasFace = $this->ensureStudentFaceReference((int) $student->id, (string) ($student->photo_reference ?? ''));

        DB::table('student')
            ->where('id', (int) $student->id)
            ->update(['created_login' => now()]);

        $this->syncSession([
            'student_id' => (int) $student->id,
            'student_nisn' => (string) ($student->student_nisn ?? ''),
            'student_code' => (string) ($student->student_code ?? ''),
            'student_name' => (string) ($student->student_name ?? ''),
            'class_id' => (int) ($student->class_id ?? 0),
            'role' => 'siswa',
            'has_face' => $hasFace,
            'has_pose_capture' => $this->hasPoseCaptureDataset((string) ($student->student_nisn ?? '')),
            'logged_in' => true,
        ]);

        $this->applyRememberCookie($remember, 'student', (int) $student->id);
        $this->touchActivityTimestamp($request, true);

        if ($hasFace) {
            return redirect($this->appPath('dashboard/siswa.php'));
        }

        if (session('face_reference_missing') === true) {
            $this->syncSession([
                'face_reference_notice' => 'Sistem tidak menemukan photo referensi. Mohon photo ulang.',
                'face_reference_missing' => null,
            ]);
        }

        return redirect($this->appPath('register.php?upload_face=1&first_login=1'));
    }

    public function logout(Request $request, bool $withMessage = true): RedirectResponse
    {
        $this->destroyLoginState($request);

        if ($withMessage) {
            return redirect($this->appPath('login.php?logout_success=1&t=' . time()));
        }

        return redirect($this->appPath('login.php'));
    }

    private function renderLoginPage(Request $request, string $error = '', string $success = ''): Response
    {
        if ($error === '' && $success === '') {
            if ($request->query->has('timeout')) {
                $error = 'Session Anda telah berakhir. Silakan login kembali.';
            } elseif ($request->query->has('registered')) {
                $success = 'Registrasi berhasil! Silakan login dengan akun Anda.';
            } elseif ($request->query->has('logout_success')) {
                $success = 'Anda telah berhasil logout.';
            }
        }

        return response()
            ->view('pages.login', [
                'error' => $error,
                'success' => $success,
                'assetBaseUrl' => rtrim($this->resolveAppRootUrl(), '/') . '/',
                'getStartedUrl' => $this->appPath('public/getstarted/index.php'),
                'forgotPasswordUrl' => $this->appPath('forgot-password.php'),
                'registerCallUrl' => $this->appPath('call.php'),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    private function hasSessionTimeout(Request $request): bool
    {
        $lastActivity = (int) $request->session()->get('LAST_ACTIVITY', 0);
        if ($lastActivity <= 0) {
            return false;
        }

        return (time() - $lastActivity) > 3600;
    }

    private function touchActivityTimestamp(Request $request, bool $regenerate = false): void
    {
        $now = time();
        $this->syncSession([
            'LAST_ACTIVITY' => $now,
        ]);

        if (!$request->session()->has('CREATED')) {
            $this->syncSession([
                'CREATED' => $now,
            ]);
        }

        if ($regenerate) {
            $request->session()->regenerate();
        }
    }

    private function isLoggedIn(): bool
    {
        return (bool) session('logged_in', false) === true
            && (int) (session('user_id', 0) ?: session('teacher_id', 0) ?: session('student_id', 0)) > 0;
    }

    private function roleDashboardPath(string $role): string
    {
        return match ($role) {
            'siswa' => $this->appPath('dashboard/siswa.php'),
            'guru' => $this->appPath('dashboard/guru.php'),
            default => $this->appPath('dashboard/admin.php'),
        };
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

    private function resolveAppPrefix(): string
    {
        $configPrefix = $this->normalizePathPrefix((string) parse_url((string) config('app.url'), PHP_URL_PATH));
        if ($configPrefix !== '') {
            return $configPrefix;
        }

        return $this->normalizePathPrefix((string) request()->getBasePath());
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

    private function resolveAppRootUrl(): string
    {
        $configuredUrl = trim((string) config('app.url'));
        if ($configuredUrl !== '') {
            $parsed = parse_url($configuredUrl);
            if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
                $port = isset($parsed['port']) ? ':' . (string) $parsed['port'] : '';
                $prefix = $this->normalizePathPrefix((string) ($parsed['path'] ?? ''));

                return $parsed['scheme'] . '://' . $parsed['host'] . $port . ($prefix !== '' ? '/' . $prefix : '');
            }
        }

        $hostUrl = rtrim((string) request()->getSchemeAndHttpHost(), '/');
        $prefix = $this->resolveAppPrefix();

        return $hostUrl . ($prefix !== '' ? '/' . $prefix : '');
    }

    private function verifyPassword(string $plainPassword, string $storedHash): bool
    {
        if ($storedHash === '') {
            return false;
        }

        return hash_equals($storedHash, hash('sha256', $plainPassword . $this->passwordSalt()));
    }

    private function passwordSalt(): string
    {
        return (string) (env('PASSWORD_SALT') ?: '$%DSuTyr47542@#&*!=QxR094{a911}+');
    }

    private function issueRememberToken(int $userId, string $role): ?string
    {
        $secret = (string) (env('JWT_REMEMBER_SECRET')
            ?: env('JWT_SECRET')
            ?: '');
        if ($secret === '') {
            return null;
        }

        $expireDays = max(1, (int) (env('JWT_EXPIRE_DAYS') ?: 30));
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_UNESCAPED_SLASHES));
        $payload = $this->base64UrlEncode(json_encode([
            'user_id' => $userId,
            'role' => $role,
            'exp' => time() + (86400 * $expireDays),
        ], JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        return $header . '.' . $payload . '.' . $encodedSignature;
    }

    private function applyRememberCookie(bool $remember, string $role, int $userId): void
    {
        if ($remember && $userId > 0) {
            $token = $this->issueRememberToken($userId, $role);
            if ($token !== null) {
                setcookie('attendance_token', $token, time() + (30 * 24 * 3600), '/');
            }
            setcookie('remember_token', bin2hex(random_bytes(32)), time() + (30 * 24 * 3600), '/', '', true, true);
            return;
        }

        setcookie('attendance_token', '', time() - 3600, '/');
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        unset($_COOKIE['attendance_token'], $_COOKIE['remember_token']);
    }

    private function destroyLoginState(Request $request): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $_SESSION = [];

        $cookieNames = [
            'attendance_token',
            'remember_token',
            'PHPSESSID',
            'session_token',
            'remember_me',
        ];

        foreach ($cookieNames as $cookieName) {
            setcookie($cookieName, '', time() - 3600, '/');
            setcookie($cookieName, '', time() - 3600, '/', '', true, true);
            unset($_COOKIE[$cookieName]);
        }
    }

    private function syncSession(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                session()->forget($key);
                unset($_SESSION[$key]);
                continue;
            }

            session([$key => $value]);
            $_SESSION[$key] = $value;
        }
    }

    private function ensureStudentFaceReference(int $studentId, string $photoReference): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        $photoReference = trim($photoReference);
        if ($photoReference === '') {
            return false;
        }

        $normalized = str_replace('\\', '/', $photoReference);
        if (str_starts_with($normalized, 'uploads/faces/')) {
            $normalized = substr($normalized, strlen('uploads/faces/'));
        }
        $normalized = ltrim($normalized, '/');
        $path = public_path('uploads/faces/' . $normalized);

        if (is_file($path)) {
            return true;
        }

        DB::table('student')
            ->where('id', $studentId)
            ->update([
                'photo_reference' => null,
                'face_embedding' => null,
            ]);

        $this->syncSession([
            'face_reference_missing' => true,
        ]);

        return false;
    }

    private function hasPoseCaptureDataset(string $studentNisn): bool
    {
        $studentNisn = trim($studentNisn);
        if ($studentNisn === '') {
            return false;
        }

        $student = DB::table('student as s')
            ->leftJoin('class as c', 's.class_id', '=', 'c.class_id')
            ->where('s.student_nisn', $studentNisn)
            ->select('s.student_name', 'c.class_name')
            ->first();

        if (!$student) {
            return false;
        }

        $classFolder = $this->storageSlug((string) ($student->class_name ?? ''), 'kelas');
        $studentFolder = $this->storageSlug((string) ($student->student_name ?? ''), 'siswa');
        $poseDir = public_path('uploads/faces/' . $classFolder . '/' . $studentFolder . '/pose');

        if (!is_dir($poseDir)) {
            return false;
        }

        $right = glob($poseDir . '/right_*.jpg') ?: [];
        $left = glob($poseDir . '/left_*.jpg') ?: [];
        $front = glob($poseDir . '/front_*.jpg') ?: [];

        return count($right) >= 5 && count($left) >= 5 && count($front) >= 1;
    }

    private function storageSlug(string $text, string $default): string
    {
        $text = trim($text);
        if ($text === '') {
            return $default;
        }

        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
        $text = preg_replace('~[^-\w]+~', '', $text) ?? '';
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text) ?? '';
        $text = strtolower($text);

        return $text !== '' ? $text : $default;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
