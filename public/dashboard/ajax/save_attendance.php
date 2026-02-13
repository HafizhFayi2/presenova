<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$db = new Database();

// Get data
$student_schedule_id = $_POST['student_schedule_id'] ?? 0;
$student_id = $_POST['student_id'] ?? 0;
$present_id = $_POST['present_id'] ?? 1;
$information = $_POST['information'] ?? '';
$photo_data = $_POST['photo_data'] ?? '';

// Validate
if (!$student_schedule_id || !$student_id) {
    die(json_encode(['success' => false, 'message' => 'Data tidak valid']));
}

// Get schedule info
$schedule_sql = "SELECT ss.*, ss.time_in as schedule_time_in 
                 FROM student_schedule ss
                 WHERE ss.student_schedule_id = ? AND ss.student_id = ?";
$schedule_stmt = $db->query($schedule_sql, [$student_schedule_id, $student_id]);
$schedule = $schedule_stmt ? $schedule_stmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$schedule) {
    die(json_encode(['success' => false, 'message' => 'Jadwal tidak ditemukan']));
}

// Check if already attended
$check_sql = "SELECT COUNT(*) as count FROM presence 
              WHERE student_schedule_id = ? AND student_id = ?";
$check_stmt = $db->query($check_sql, [$student_schedule_id, $student_id]);
$check_result = $check_stmt ? $check_stmt->fetch(PDO::FETCH_ASSOC) : null;

if ($check_result && $check_result['count'] > 0) {
    die(json_encode(['success' => false, 'message' => 'Anda sudah melakukan absensi']));
}

// Calculate if late
$current_time = date('H:i:s');
$schedule_time = $schedule['schedule_time_in'] ?? $schedule['time_in'] ?? null;
$is_late = 'N';
$late_time = 0;

if ($current_time > $schedule_time) {
    $time_diff = strtotime($current_time) - strtotime($schedule_time);
    $late_minutes = floor($time_diff / 60);
    
    if ($late_minutes > 30) { // 30 minutes tolerance
        $is_late = 'Y';
        $late_time = $late_minutes - 30; // Only count beyond tolerance
    }
}

// Save photo if exists
$photo_filename = '';
if ($photo_data && strpos($photo_data, 'data:image') === 0) {
    $upload_dir = '../../uploads/attendance/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $photo_filename = 'attendance_' . $student_id . '_' . time() . '.jpg';
    $photo_path = $upload_dir . $photo_filename;
    
    // Convert base64 to image
    $photo_data = str_replace('data:image/jpeg;base64,', '', $photo_data);
    $photo_data = str_replace(' ', '+', $photo_data);
    file_put_contents($photo_path, base64_decode($photo_data));
}

// Insert attendance
$insert_sql = "INSERT INTO presence 
               (student_id, student_schedule_id, present_id, 
                presence_date, time_in, information, 
                is_late, late_time, picture_in, created_at) 
               VALUES (?, ?, ?, CURDATE(), NOW(), ?, ?, ?, ?, NOW())";
               
$params = [
    $student_id,
    $student_schedule_id,
    $present_id,
    $information,
    $is_late,
    $late_time,
    $photo_filename
];

$insert_stmt = $db->query($insert_sql, $params);

if ($insert_stmt) {
    echo json_encode([
        'success' => true,
        'message' => 'Absensi berhasil disimpan!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan absensi'
    ]);
}
?>
