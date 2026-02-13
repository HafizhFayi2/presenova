<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/push_service.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isPushEnabled()) {
    echo json_encode(['success' => false, 'message' => 'Push service not configured']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
    echo json_encode(['success' => false, 'message' => 'Data subscription tidak lengkap']);
    exit;
}

$studentId = $_SESSION['student_id'];
$endpoint = $data['endpoint'];
$p256dh = $data['keys']['p256dh'];
$auth = $data['keys']['auth'];
$contentEncoding = $data['contentEncoding'] ?? $data['content_encoding'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$platform = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? null;
$browser = null;

if ($userAgent) {
    if (stripos($userAgent, 'Edg') !== false) {
        $browser = 'Edge';
    } elseif (stripos($userAgent, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($userAgent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($userAgent, 'Safari') !== false) {
        $browser = 'Safari';
    }
}

$sql = "
    INSERT INTO push_tokens (
        student_id, endpoint, p256dh, auth, content_encoding,
        browser, platform, user_agent, is_active, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Y', NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        student_id = VALUES(student_id),
        p256dh = VALUES(p256dh),
        auth = VALUES(auth),
        content_encoding = VALUES(content_encoding),
        browser = VALUES(browser),
        platform = VALUES(platform),
        user_agent = VALUES(user_agent),
        is_active = 'Y',
        updated_at = NOW()
";

$stmt = $db->query($sql, [
    $studentId,
    $endpoint,
    $p256dh,
    $auth,
    $contentEncoding,
    $browser,
    $platform,
    $userAgent
]);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan subscription']);
    exit;
}

echo json_encode(['success' => true]);
