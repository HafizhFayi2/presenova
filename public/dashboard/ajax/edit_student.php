<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id = $_POST['id'];
        $student_nisn = trim($_POST['student_nisn']);
        $student_code = strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['student_code'] ?? ''))));
        if ($student_code !== '' && strpos($student_code, 'SW') !== 0) {
            $student_code = 'SW' . $student_code;
        }
        $student_name = trim($_POST['student_name']);
        $class_id = $_POST['class_id'];
        $jurusan_id = $_POST['jurusan_id'];
        
        // Validate input
        if(empty($id) || empty($student_nisn) || empty($student_code) || empty($student_name) || empty($class_id) || empty($jurusan_id)) {
            echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
            exit;
        }
        
        // Check if NISN already exists for other students
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM student WHERE student_nisn = ? AND id != ?");
        $checkStmt->execute([$student_nisn, $id]);
        $result = $checkStmt->fetch();
        
        if($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'NISN sudah digunakan oleh siswa lain']);
            exit;
        }
        
        // Check if student_code already exists for other students
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM student WHERE student_code = ? AND id != ?");
        $checkStmt->execute([$student_code, $id]);
        $result = $checkStmt->fetch();
        
        if($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Kode siswa sudah digunakan oleh siswa lain']);
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
        
        // Update student
        $updateStmt = $db->prepare("UPDATE student SET student_nisn = ?, student_code = ?, student_name = ?, class_id = ?, jurusan_id = ? WHERE id = ?");
        $updateStmt->execute([$student_nisn, $student_code, $student_name, $class_id, $jurusan_id, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Data siswa berhasil diupdate']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
