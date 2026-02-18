<?php

namespace App\Support\Core;

class Auth
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function logout(): void
    {
        try {
            session()->invalidate();
            session()->regenerateToken();
        } catch (\Throwable) {
            // Keep runtime flow resilient.
        }

        $_SESSION = [];
        setcookie('attendance_token', '', time() - 3600, '/');
        setcookie('remember_token', '', time() - 3600, '/');
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        unset($_COOKIE['attendance_token'], $_COOKIE['remember_token']);
    }

    public function isLoggedIn(): bool
    {
        if ((bool) ($_SESSION['logged_in'] ?? false) === true) {
            return true;
        }

        $token = trim((string) ($_COOKIE['attendance_token'] ?? ''));
        if ($token === '') {
            return false;
        }

        $payload = \validateJWT($token);
        if (!is_array($payload)) {
            return false;
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        $role = strtolower((string) ($payload['role'] ?? ''));
        if ($userId <= 0) {
            return false;
        }

        if ($role === 'student') {
            $stmt = $this->db->query("SELECT * FROM student WHERE id = ? LIMIT 1", [$userId]);
            $student = $stmt ? $stmt->fetch() : null;
            if (!$student) {
                return false;
            }
            $_SESSION['student_id'] = (int) $student['id'];
            $_SESSION['student_nisn'] = (string) ($student['student_nisn'] ?? '');
            $_SESSION['student_code'] = (string) ($student['student_code'] ?? '');
            $_SESSION['student_name'] = (string) ($student['student_name'] ?? '');
            $_SESSION['class_id'] = (int) ($student['class_id'] ?? 0);
            $_SESSION['role'] = 'siswa';
            $_SESSION['logged_in'] = true;

            return true;
        }

        if ($role === 'guru') {
            $stmt = $this->db->query("SELECT * FROM teacher WHERE id = ? LIMIT 1", [$userId]);
            $teacher = $stmt ? $stmt->fetch() : null;
            if (!$teacher) {
                return false;
            }
            $_SESSION['teacher_id'] = (int) $teacher['id'];
            $_SESSION['teacher_code'] = (string) ($teacher['teacher_code'] ?? '');
            $_SESSION['teacher_name'] = (string) ($teacher['teacher_name'] ?? '');
            $_SESSION['role'] = 'guru';
            $_SESSION['level'] = 2;
            $_SESSION['logged_in'] = true;

            return true;
        }

        $stmt = $this->db->query("SELECT * FROM user WHERE user_id = ? LIMIT 1", [$userId]);
        $admin = $stmt ? $stmt->fetch() : null;
        if (!$admin) {
            return false;
        }
        if (isset($admin['is_active']) && (string) $admin['is_active'] !== 'Y') {
            return false;
        }

        $_SESSION['user_id'] = (int) $admin['user_id'];
        $_SESSION['username'] = (string) ($admin['username'] ?? '');
        $_SESSION['fullname'] = (string) ($admin['fullname'] ?? '');
        $_SESSION['level'] = (int) ($admin['level'] ?? 0);
        $_SESSION['role'] = 'admin';
        $_SESSION['logged_in'] = true;

        return true;
    }
}

