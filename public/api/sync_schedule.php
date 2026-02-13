<?php
// dashboard/api/sync_schedule.php
session_start();
require_once '../includes/database.php';
require_once '../helpers/jp_time_helper.php';
require_once '../includes/database_helper.php';
$db = new Database();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$student_id = $_POST['student_id'] ?? $_SESSION['student_id'];

// Validasi student_id
if (!$student_id) {
    echo json_encode(['success' => false, 'message' => 'Student ID tidak valid']);
    exit;
}

// Ambil data siswa untuk mendapatkan class_id
$sql_student = "SELECT class_id FROM student WHERE id = ?";
$stmt = $db->query($sql_student, [$student_id]);
$student = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$student || !$student['class_id']) {
    echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan atau belum memiliki kelas']);
    exit;
}

// Sinkronisasi jadwal siswa 6 bulan ke depan (rolling)
$dbHelper = new DatabaseHelper($db);
$added = $dbHelper->ensureStudentSchedulesForStudent($student_id, (int) $student['class_id'], 6);

echo json_encode([
    'success' => true,
    'message' => 'Sinkronisasi berhasil',
    'added' => $added,
    'total_schedules' => $added
]);
