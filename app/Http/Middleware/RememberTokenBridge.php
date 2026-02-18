<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RememberTokenBridge
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasAuthenticatedSession()) {
            return $next($request);
        }

        $token = (string) $request->cookie('attendance_token', '');
        if ($token === '') {
            return $next($request);
        }

        $payload = $this->decodeRememberJwt($token);
        if ($payload === null) {
            return $next($request);
        }

        $role = strtolower((string) ($payload['role'] ?? ''));
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return $next($request);
        }

        switch ($role) {
            case 'student':
                $student = DB::table('student')
                    ->select('id', 'student_nisn', 'student_code', 'student_name', 'class_id')
                    ->where('id', $userId)
                    ->first();
                if ($student) {
                    $this->syncSession([
                        'student_id' => (int) $student->id,
                        'student_nisn' => (string) ($student->student_nisn ?? ''),
                        'student_code' => (string) ($student->student_code ?? ''),
                        'student_name' => (string) ($student->student_name ?? ''),
                        'class_id' => (int) ($student->class_id ?? 0),
                        'role' => 'siswa',
                        'logged_in' => true,
                    ]);
                }
                break;

            case 'guru':
                $teacher = DB::table('teacher')
                    ->select('id', 'teacher_code', 'teacher_name')
                    ->where('id', $userId)
                    ->first();
                if ($teacher) {
                    $this->syncSession([
                        'teacher_id' => (int) $teacher->id,
                        'teacher_code' => (string) ($teacher->teacher_code ?? ''),
                        'teacher_name' => (string) ($teacher->teacher_name ?? ''),
                        'role' => 'guru',
                        'level' => 2,
                        'logged_in' => true,
                    ]);
                }
                break;

            default:
                $admin = DB::table('user')
                    ->select('user_id', 'username', 'fullname', 'level', 'is_active')
                    ->where('user_id', $userId)
                    ->first();
                if ($admin && ((string) ($admin->is_active ?? 'Y')) === 'Y') {
                    $this->syncSession([
                        'user_id' => (int) $admin->user_id,
                        'username' => (string) ($admin->username ?? ''),
                        'fullname' => (string) ($admin->fullname ?? ''),
                        'level' => (int) ($admin->level ?? 0),
                        'role' => 'admin',
                        'logged_in' => true,
                    ]);
                }
                break;
        }

        return $next($request);
    }

    private function hasAuthenticatedSession(): bool
    {
        $role = (string) session('role', '');
        if ((bool) session('logged_in', false) !== true) {
            return false;
        }

        if ($role === 'siswa' && (int) session('student_id', 0) > 0) {
            return true;
        }
        if ($role === 'guru' && (int) session('teacher_id', 0) > 0) {
            return true;
        }
        if ($role === 'admin' && (int) session('user_id', 0) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Decode remember-token JWT payload.
     */
    private function decodeRememberJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $secret = (string) (env('JWT_REMEMBER_SECRET')
            ?: env('JWT_SECRET')
            ?: '');
        if ($secret === '') {
            return null;
        }

        $rawSignature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true);
        $validSignature = rtrim(strtr(base64_encode($rawSignature), '+/', '-_'), '=');
        if (!hash_equals($validSignature, $encodedSignature)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($encodedPayload);
        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $exp < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Decode URL-safe base64 with optional missing padding.
     *
     * @return string|false
     */
    private function base64UrlDecode(string $value)
    {
        $base64 = strtr($value, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($base64, true);
    }

    private function syncSession(array $values): void
    {
        foreach ($values as $key => $value) {
            session([$key => $value]);
            $_SESSION[$key] = $value;
        }
    }
}
