<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="log_system_' . date('Ymd_His') . '.xls"');

$db = new Database();

$sql = "SELECT user_type, action, created_at 
        FROM activity_logs 
        ORDER BY created_at DESC";
$stmt = $db->query($sql);
$logs = $stmt ? $stmt->fetchAll() : [];

$output = fopen('php://output', 'w');
fputcsv($output, ['Tipe User', 'Aktivitas', 'Waktu']);

foreach ($logs as $log) {
    fputcsv($output, [
        $log['user_type'] ?? '',
        $log['action'] ?? '',
        $log['created_at'] ?? ''
    ]);
}

fclose($output);
exit;
