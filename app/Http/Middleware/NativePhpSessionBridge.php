<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NativePhpSessionBridge
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->startNativeSession();
        $this->syncNativeFromLaravel();

        /** @var Response $response */
        $response = $next($request);

        $this->syncLaravelFromNative();

        return $response;
    }

    private function startNativeSession(): void
    {
        try {
            if (session_status() !== PHP_SESSION_NONE) {
                return;
            }

            $sessionName = session_name();
            $sessionId = isset($_COOKIE[$sessionName]) ? (string) $_COOKIE[$sessionName] : '';
            if ($sessionId !== '') {
                session_id($sessionId);
            }

            @session_start();
        } catch (\Throwable) {
            // Keep request resilient even when native session bridge fails.
        }
    }

    private function syncNativeFromLaravel(): void
    {
        try {
            $laravelSession = session();
            foreach ($this->sharedSessionKeys() as $key) {
                if ($laravelSession->has($key)) {
                    $_SESSION[$key] = $laravelSession->get($key);
                }
            }
        } catch (\Throwable) {
            // Ignore bridge sync failures.
        }
    }

    private function syncLaravelFromNative(): void
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return;
            }

            foreach ($this->sharedSessionKeys() as $key) {
                if (array_key_exists($key, $_SESSION)) {
                    session([$key => $_SESSION[$key]]);
                } else {
                    session()->forget($key);
                }
            }
        } catch (\Throwable) {
            // Ignore bridge sync failures.
        }
    }

    /**
     * @return array<int, string>
     */
    private function sharedSessionKeys(): array
    {
        return [
            'logged_in',
            'role',
            'level',
            'user_id',
            'username',
            'fullname',
            'teacher_id',
            'teacher_code',
            'teacher_name',
            'student_id',
            'student_nisn',
            'student_code',
            'student_name',
            'class_id',
            'has_face',
            'has_pose_capture',
            'face_reference_missing',
            'teacher_password_rotation_notice',
            'require_password_change',
            'password_change_reason',
            'LAST_ACTIVITY',
            'CREATED',
            'forgot_password_csrf',
            'forgot_password_last_attempt',
            'face_reference_notice',
            'face_pose_notice',
            'face_match_ticket',
        ];
    }
}

