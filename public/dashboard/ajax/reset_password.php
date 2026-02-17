<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (
            !isset($_SESSION['logged_in'], $_SESSION['role'], $_SESSION['level']) ||
            $_SESSION['role'] !== 'admin' ||
            !in_array((int) $_SESSION['level'], [1, 2], true)
        ) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $type = trim((string) ($_POST['type'] ?? ''));

        if ($id <= 0 || $type === '') {
            echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
            exit;
        }

        $db = new Database();

        if ($type === 'student') {
            // Reset password siswa ke kode siswa.
            $studentStmt = $db->query("SELECT student_code FROM student WHERE id = ? LIMIT 1", [$id]);
            $student = $studentStmt ? $studentStmt->fetch() : null;

            if (!$student) {
                echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
                exit;
            }

            $studentCode = strtoupper(trim((string) ($student['student_code'] ?? '')));
            if ($studentCode === '') {
                echo json_encode(['success' => false, 'message' => 'Kode siswa tidak valid']);
                exit;
            }

            $password = hash('sha256', $studentCode . PASSWORD_SALT);
            $updated = $db->query("UPDATE student SET student_password = ? WHERE id = ?", [$password, $id]);
            if (!$updated) {
                echo json_encode(['success' => false, 'message' => 'Gagal mereset password siswa']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Password siswa berhasil direset ke kode siswa']);
        } elseif ($type === 'teacher') {
            // Reset password to guru123
            $password = hash('sha256', 'guru123' . PASSWORD_SALT);
            $updated = $db->query("UPDATE teacher SET teacher_password = ? WHERE id = ?", [$password, $id]);
            if (!$updated) {
                echo json_encode(['success' => false, 'message' => 'Gagal mereset password guru']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Password berhasil direset ke "guru123"']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tipe tidak valid']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
