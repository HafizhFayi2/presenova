<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $class_name = trim($_POST['class_name']);
        $jurusan_id = $_POST['jurusan_id'];
        
        // Validate input
        if(empty($class_name) || empty($jurusan_id)) {
            echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
            exit;
        }
        
        // Get jurusan info
        $jurusanStmt = $db->prepare("SELECT kode_jurusan FROM jurusan WHERE jurusan_id = ?");
        $jurusanStmt->execute([$jurusan_id]);
        $jurusan = $jurusanStmt->fetch();
        
        if(!$jurusan) {
            echo json_encode(['success' => false, 'message' => 'Jurusan tidak ditemukan']);
            exit;
        }
        
        // Generate kode_kelas from jurusan kode
        $kode_kelas = $jurusan['kode_jurusan'];
        
        // Check if class already exists
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM class WHERE class_name = ?");
        $checkStmt->execute([$class_name]);
        $result = $checkStmt->fetch();
        
        if($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Kelas dengan nama tersebut sudah ada']);
            exit;
        }
        
        // Insert new class
        $insertStmt = $db->prepare("INSERT INTO class (class_name, jurusan_id, kode_kelas) VALUES (?, ?, ?)");
        $insertStmt->execute([$class_name, $jurusan_id, $kode_kelas]);
        
        echo json_encode(['success' => true, 'message' => 'Kelas berhasil ditambahkan']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>