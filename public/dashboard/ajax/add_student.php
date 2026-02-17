<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $student_nisn = trim($_POST['student_nisn']);
        $student_code = strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['student_code'] ?? ''))));
        if ($student_code !== '' && strpos($student_code, 'SW') !== 0) {
            $student_code = 'SW' . $student_code;
        }
        $student_name = trim($_POST['student_name']);
        $class_id = $_POST['class_id'];
        $jurusan_id = $_POST['jurusan_id'];
        
        // Validate input
        if(empty($student_nisn) || empty($student_code) || empty($student_name) || empty($class_id) || empty($jurusan_id)) {
            echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
            exit;
        }
        
        // Check if NISN already exists
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM student WHERE student_nisn = ?");
        $checkStmt->execute([$student_nisn]);
        $result = $checkStmt->fetch();
        
        if($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'NISN sudah terdaftar']);
            exit;
        }
        
        // Check if student_code already exists
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM student WHERE student_code = ?");
        $checkStmt->execute([$student_code]);
        $result = $checkStmt->fetch();
        
        if($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Kode siswa sudah digunakan']);
            exit;
        }
        
        // Verify class and jurusan match
        $verifyStmt = $db->prepare("SELECT jurusan_id FROM class WHERE class_id = ?");
        $verifyStmt->execute([$class_id]);
        $classData = $verifyStmt->fetch();
        
        if(!$classData) {
            echo json_encode(['success' => false, 'message' => 'Kelas tidak ditemukan']);
            exit;
        }
        
        if($classData['jurusan_id'] != $jurusan_id) {
            echo json_encode(['success' => false, 'message' => 'Jurusan tidak sesuai dengan kelas']);
            exit;
        }
        
        // Hash password (default is NISN)
        $password = password_hash($student_nisn, PASSWORD_DEFAULT);
        
        // Insert new student
        $insertStmt = $db->prepare("INSERT INTO student (student_nisn, student_code, student_name, class_id, jurusan_id, password) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$student_nisn, $student_code, $student_name, $class_id, $jurusan_id, $password]);
        
        echo json_encode(['success' => true, 'message' => 'Siswa berhasil ditambahkan']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
