<?php
require_once '../includes/config.php';
require_once '../includes/push_service.php';

header('Content-Type: application/json');

if (!isPushEnabled()) {
    echo json_encode(['success' => false, 'message' => 'Push service not configured']);
    exit;
}

$config = getPushConfig();

echo json_encode([
    'success' => true,
    'publicKey' => $config['vapid_public_key']
]);
