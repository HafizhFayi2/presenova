<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$studentId = $_SESSION['student_id'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if ($data && !empty($data['endpoint'])) {
    $endpoint = $data['endpoint'];
    $stmt = $db->query(
        "UPDATE push_tokens SET is_active = 'N', updated_at = NOW() WHERE student_id = ? AND endpoint = ?",
        [$studentId, $endpoint]
    );
} else {
    $stmt = $db->query(
        "UPDATE push_tokens SET is_active = 'N', updated_at = NOW() WHERE student_id = ?",
        [$studentId]
    );
}

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus subscription']);
    exit;
}

echo json_encode(['success' => true]);
