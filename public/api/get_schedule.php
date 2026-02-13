<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    jsonResponse(false, 'Unauthorized');
}

$db = new Database();

// Tentukan user type
if (isset($_SESSION['student_id'])) {
    $user_type = 'student';
    $user_id = $_SESSION['student_id'];
} elseif (isset($_SESSION['teacher_id'])) {
    $user_type = 'teacher';
    $user_id = $_SESSION['teacher_id'];
} else {
    jsonResponse(false, 'User type tidak dikenali');
}

// Ambil parameter
$date = $_GET['date'] ?? date('Y-m-d');
$day_of_week = date('N', strtotime($date));

if ($user_type == 'student') {
    // Ambil jadwal untuk siswa berdasarkan kelas
    $stmt = $db->query("SELECT class_id FROM student WHERE id = ?", [$user_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        jsonResponse(false, 'Data siswa tidak ditemukan');
    }
    
    $class_id = $student['class_id'];
    
    $sql = "SELECT ts.*, t.teacher_name, c.class_name, d.day_name, sh.time_in, sh.time_out
            FROM teacher_schedule ts
            JOIN teacher t ON ts.teacher_id = t.id
            JOIN class c ON ts.class_id = c.class_id
            JOIN day d ON ts.day_id = d.day_id
            JOIN shift sh ON ts.shift_id = sh.shift_id
            WHERE ts.day_id = ? 
            AND ts.class_id = ?
            AND d.is_active = 'Y'
            ORDER BY sh.time_in";
    
    $stmt = $db->query($sql, [$day_of_week, $class_id]);
    $schedules = $stmt->fetchAll();
    
    // Untuk setiap jadwal, cek apakah sudah absen
    foreach ($schedules as &$schedule) {
        $attendance_sql = "SELECT * FROM presence 
                          WHERE student_id = ? 
                          AND DATE(presence_date) = ?
                          AND student_schedule_id IN (
                              SELECT student_schedule_id FROM student_schedule 
                              WHERE teacher_schedule_id = ?
                          )";
        $attendance_stmt = $db->query($attendance_sql, [$user_id, $date, $schedule['schedule_id']]);
        $attendance = $attendance_stmt->fetch();
        
        $schedule['has_attended'] = $attendance ? true : false;
        $schedule['attendance_status'] = $attendance ? $attendance['present_id'] : null;
        $schedule['attendance_time'] = $attendance ? $attendance['time_in'] : null;
    }
    
    jsonResponse(true, 'Success', [
        'date' => $date,
        'schedules' => $schedules
    ]);
    
} elseif ($user_type == 'teacher') {
    // Ambil jadwal untuk guru
    $sql = "SELECT ts.*, c.class_name, d.day_name, sh.time_in, sh.time_out
            FROM teacher_schedule ts
            JOIN class c ON ts.class_id = c.class_id
            JOIN day d ON ts.day_id = d.day_id
            JOIN shift sh ON ts.shift_id = sh.shift_id
            WHERE ts.teacher_id = ? 
            AND ts.day_id = ?
            AND d.is_active = 'Y'
            ORDER BY sh.time_in";
    
    $stmt = $db->query($sql, [$user_id, $day_of_week]);
    $schedules = $stmt->fetchAll();
    
    jsonResponse(true, 'Success', [
        'date' => $date,
        'schedules' => $schedules
    ]);
}
?>