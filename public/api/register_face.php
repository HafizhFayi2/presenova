<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/face_recognition.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn() || !isset($_SESSION['student_id'])) {
    jsonResponse(false, 'Unauthorized');
}

$db = new Database();
$student_id = $_SESSION['student_id'];

// Cek apakah sudah memiliki wajah terdaftar
$stmt = $db->query("SELECT photo_reference FROM student WHERE id = ?", [$student_id]);
$student = $stmt->fetch();

if ($student && !empty($student['photo_reference'])) {
    jsonResponse(false, 'Wajah sudah terdaftar sebelumnya');
}

// Ambil data dari POST
$image_data = $_POST['image_data'] ?? '';
if (empty($image_data)) {
    jsonResponse(false, 'Tidak ada data gambar');
}

// Simpan gambar ke server
$filename = 'face_' . $student_id . '_' . time() . '.jpg';
$filepath = '../uploads/faces/' . $filename;

// Decode base64 image
$image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
$image_data = str_replace('data:image/png;base64,', '', $image_data);
$image_data = str_replace(' ', '+', $image_data);
$image_binary = base64_decode($image_data);

if ($image_binary === false) {
    jsonResponse(false, 'Gambar tidak valid');
}

// Simpan file
if (!file_put_contents($filepath, $image_binary)) {
    jsonResponse(false, 'Gagal menyimpan gambar');
}

// Kompres gambar
compressImage($filepath, $filepath, 80);

// Simpan face embedding (simulasi)
$face_embedding = FaceRecognition::registerFace($image_data);

// Update database
$sql = "UPDATE student SET photo_reference = ?, face_embedding = ?, last_face_update = NOW() WHERE id = ?";
$stmt = $db->query($sql, [$filename, $face_embedding, $student_id]);

if ($stmt) {
    // Update session
    $_SESSION['has_face'] = true;
    
    // Log aktivitas
    logActivity($student_id, 'student', 'register_face', 'Registrasi wajah berhasil');
    
    jsonResponse(true, 'Registrasi wajah berhasil', ['filename' => $filename]);
} else {
    // Hapus file yang sudah diupload
    unlink($filepath);
    jsonResponse(false, 'Gagal menyimpan data ke database');
}
?>