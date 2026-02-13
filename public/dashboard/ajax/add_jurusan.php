<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $kode_jurusan = strtoupper(trim($_POST['kode_jurusan']));
        $name = trim($_POST['name']);
        
        // Validate input
        if(empty($kode_jurusan) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
            exit;
        }
        
        // Validate kode_jurusan length
        if(strlen($kode_jurusan) > 10) {
            echo json_encode(['success' => false, 'message' => 'Kode jurusan maksimal 10 karakter']);
            exit;
        }
        
        // Check if kode_jurusan already exists
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM jurusan WHERE kode_jurusan = ?");
        $checkStmt->execute([$kode_jurusan]);
        $result = $checkStmt->fetch();
        
        if($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Kode jurusan sudah digunakan']);
            exit;
        }
        
        // Check if name already exists
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM jurusan WHERE name = ?");
        $checkStmt->execute([$name]);
        $result = $checkStmt->fetch();
        
        if($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Nama jurusan sudah ada']);
            exit;
        }
        
        // Insert new jurusan
        $insertStmt = $db->prepare("INSERT INTO jurusan (kode_jurusan, name) VALUES (?, ?)");
        $insertStmt->execute([$kode_jurusan, $name]);
        
        echo json_encode(['success' => true, 'message' => 'Jurusan berhasil ditambahkan']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>