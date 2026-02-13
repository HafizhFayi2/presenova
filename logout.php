<?php
// logout.php - Versi diperbaiki
session_start();

// Log logout activity
error_log("User logout - User ID: " . ($_SESSION['user_id'] ?? 'not set') . 
          ", Role: " . ($_SESSION['role'] ?? 'not set'));

// Simpan data penting sebelum menghapus session (untuk audit trail)
$logout_data = [
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'logout_time' => date('Y-m-d H:i:s')
];

// Log ke file atau database (opsional)
error_log("Logout data: " . print_r($logout_data, true));

// Unset semua variabel session spesifik
$session_vars = [
    'user_id', 'student_id', 'teacher_id', 'role', 'level', 'username',
    'fullname', 'student_name', 'teacher_name', 'class', 'class_id',
    'has_face', 'logged_in', 'LAST_ACTIVITY', 'CREATED'
];

foreach ($session_vars as $var) {
    unset($_SESSION[$var]);
}

// Hapus semua data session
$_SESSION = array();

// Hancurkan cookie session
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hapus cookie lainnya
$cookie_names = [
    'attendance_token',
    'remember_me',
    'remember_token',
    'PHPSESSID',
    'session_token'
];

foreach ($cookie_names as $cookie_name) {
    if (isset($_COOKIE[$cookie_name])) {
        // Delete cookie untuk semua paths
        setcookie($cookie_name, '', time() - 3600, '/');
        setcookie($cookie_name, '', time() - 3600, '/', '', true, true);
        // Hapus dari array $_COOKIE juga
        unset($_COOKIE[$cookie_name]);
    }
}

// Hancurkan session
session_destroy();

// Clear session dari memory
session_unset();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect ke login dengan pesan sukses
header("Location: login.php?logout_success=1&t=" . time());
exit();
?>