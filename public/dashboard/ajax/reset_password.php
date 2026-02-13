<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id = $_POST['id'] ?? 0;
        $type = $_POST['type'] ?? '';
        
        if(empty($id) || empty($type)) {
            echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
            exit;
        }
        
        $db = new Database();
        
        if($type == 'student') {
            // Get student NISN
            $student = $db->query("SELECT student_nisn FROM student WHERE id = ?", [$id])->fetch();
            
            if(!$student) {
                echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
                exit;
            }
            
            // Reset password to NISN
            $password = hash('sha256', $student['student_nisn'] . PASSWORD_SALT);
            $db->query("UPDATE student SET student_password = ? WHERE id = ?", [$password, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Password berhasil direset ke NISN']);
            
        } elseif($type == 'teacher') {
            // Reset password to guru123
            $password = hash('sha256', 'guru123' . PASSWORD_SALT);
            $db->query("UPDATE teacher SET teacher_password = ? WHERE id = ?", [$password, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Password berhasil direset ke "guru123"']);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Tipe tidak valid']);
        }
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>