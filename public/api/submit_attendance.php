<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/face_recognition.php';
require_once '../helpers/storage_path_helper.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn() || !isset($_SESSION['student_id'])) {
    jsonResponse(false, 'Unauthorized');
}

$db = new Database();
$student_id = $_SESSION['student_id'];

// Ambil data dari POST
$schedule_id = $_POST['schedule_id'] ?? 0;
$image_data = $_POST['image_data'] ?? '';
$latitude = $_POST['latitude'] ?? 0;
$longitude = $_POST['longitude'] ?? 0;
$timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');

if (!$schedule_id) {
    jsonResponse(false, 'Schedule ID tidak valid');
}

// Validasi lokasi
$distance = calculateDistance($latitude, $longitude, SCHOOL_LATITUDE, SCHOOL_LONGITUDE);
if ($distance > ATTENDANCE_RADIUS) {
    jsonResponse(false, "Anda berada di luar radius sekolah (".round($distance)."m)");
}

// Validasi waktu (cek apakah masih dalam rentang waktu absensi)
// Implementasi: cek jadwal dan waktu saat ini

// Validasi wajah
$stmt = $db->query("SELECT s.photo_reference, s.face_embedding, s.student_name, s.student_nisn, c.class_name
    FROM student s
    LEFT JOIN class c ON s.class_id = c.class_id
    WHERE s.id = ?", [$student_id]);
$student = $stmt->fetch();

if (!$student || empty($student['photo_reference'])) {
    jsonResponse(false, 'Wajah belum terdaftar');
}

// Simulasi face recognition
$reference_path = '../uploads/faces/' . $student['photo_reference'];
$face_result = FaceRecognition::matchFace($reference_path, $image_data);

if (!$face_result['match']) {
    jsonResponse(false, 'Verifikasi wajah gagal: ' . $face_result['message']);
}

// Simpan gambar absensi
$attendanceDate = date('Y-m-d', strtotime($timestamp));
$attendanceTime = date('H:i:s', strtotime($timestamp));
$dateTimeFolder = storage_attendance_datetime_folder($attendanceDate, $attendanceTime);
$classFolder = storage_class_folder($student['class_name'] ?? 'kelas');
$attendanceDir = '../uploads/attendance/' . $dateTimeFolder . '/' . $classFolder . '/';
if (!is_dir($attendanceDir)) {
    mkdir($attendanceDir, 0777, true);
}
$baseFilename = storage_attendance_basename($student['student_name'] ?? ('siswa_' . $student_id), $student['student_nisn'] ?? $student_id, $attendanceDate);
$attendance_filename = $baseFilename . '.jpg';
$attendance_path = $attendanceDir . $attendance_filename;

$image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
$image_data = str_replace('data:image/png;base64,', '', $image_data);
$image_data = str_replace(' ', '+', $image_data);
$image_binary = base64_decode($image_data);

if ($image_binary === false) {
    jsonResponse(false, 'Gambar tidak valid');
}

if (!file_put_contents($attendance_path, $image_binary)) {
    jsonResponse(false, 'Gagal menyimpan gambar absensi');
}

// Kompres gambar
compressImage($attendance_path, $attendance_path, 80);

// Tentukan apakah terlambat
$is_late = 'N';
$late_time = 0;

// Simpan ke database
$sql = "INSERT INTO presence (student_id, presence_date, time_in, picture_in, present_id, 
        latitude_in, longitude_in, distance_in, is_late, late_time, information) 
        VALUES (?, DATE(?), TIME(?), ?, 1, ?, ?, ?, ?, ?, ?)";
        
 $attendance_relative = $dateTimeFolder . '/' . $classFolder . '/' . $attendance_filename;
$params = [
    $student_id,
    $timestamp,
    $timestamp,
    $attendance_relative,
    $latitude,
    $longitude,
    round($distance),
    $is_late,
    $late_time,
    'Face score: ' . $face_result['score'] . '%'
];

$stmt = $db->query($sql, $params);

if ($stmt) {
    $attendance_id = $db->lastInsertId();
    
    // Log aktivitas
    logActivity($student_id, 'student', 'submit_attendance', 'Absensi berhasil dengan score wajah: ' . $face_result['score']);
    
    jsonResponse(true, 'Absensi berhasil', [
        'attendance_id' => $attendance_id,
        'face_score' => $face_result['score'],
        'distance' => round($distance, 2),
        'time' => $timestamp
    ]);
} else {
    // Hapus file yang sudah diupload
    unlink($attendance_path);
    jsonResponse(false, 'Gagal menyimpan data absensi');
}
?>
