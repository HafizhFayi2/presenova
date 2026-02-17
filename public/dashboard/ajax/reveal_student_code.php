<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['logged_in'], $_SESSION['role']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$password = trim((string) ($_POST['password'] ?? ''));
if ($password === '') {
    echo json_encode(['success' => false, 'message' => 'Password wajib diisi']);
    exit;
}

$db = new Database();

if ($_SESSION['role'] === 'siswa') {
    $studentId = isset($_SESSION['student_id']) ? (int) $_SESSION['student_id'] : 0;
    if ($studentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Session siswa tidak valid']);
        exit;
    }

    $studentStmt = $db->query(
        "SELECT student_code, student_password FROM student WHERE id = ? LIMIT 1",
        [$studentId]
    );
    $student = $studentStmt ? $studentStmt->fetch() : null;
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Data siswa tidak ditemukan']);
        exit;
    }

    $expectedHash = (string) ($student['student_password'] ?? '');
    $passwordHash = hash('sha256', $password . PASSWORD_SALT);
    if ($expectedHash === '' || !hash_equals($expectedHash, $passwordHash)) {
        echo json_encode(['success' => false, 'message' => 'Password siswa tidak sesuai']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'student_code' => strtoupper(trim((string) ($student['student_code'] ?? ''))),
    ]);
    exit;
}

if ($_SESSION['role'] === 'admin' && isset($_SESSION['level']) && (int) $_SESSION['level'] === 1) {
    $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;

    if ($adminId <= 0 || $studentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
        exit;
    }

    $adminStmt = $db->query(
        "SELECT password FROM user WHERE user_id = ? AND level = 1 LIMIT 1",
        [$adminId]
    );
    $admin = $adminStmt ? $adminStmt->fetch() : null;
    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Data admin tidak ditemukan']);
        exit;
    }

    $expectedHash = (string) ($admin['password'] ?? '');
    $passwordHash = hash('sha256', $password . PASSWORD_SALT);
    if ($expectedHash === '' || !hash_equals($expectedHash, $passwordHash)) {
        echo json_encode(['success' => false, 'message' => 'Password admin tidak sesuai']);
        exit;
    }

    $studentStmt = $db->query(
        "SELECT student_code FROM student WHERE id = ? LIMIT 1",
        [$studentId]
    );
    $student = $studentStmt ? $studentStmt->fetch() : null;
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'student_code' => strtoupper(trim((string) ($student['student_code'] ?? ''))),
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Role tidak memiliki izin']);
exit;

