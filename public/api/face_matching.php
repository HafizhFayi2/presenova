<?php
// api/face_matching.php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/face_matcher.php';

header('Content-Type: application/json');

if (function_exists('ignore_user_abort')) {
    @ignore_user_abort(true);
}

if (function_exists('set_time_limit')) {
    $pythonTimeout = defined('FACE_MATCH_TIMEOUT_SECONDS') ? max(30, (int) FACE_MATCH_TIMEOUT_SECONDS) : 60;
    $serverTimeout = defined('FACE_MATCH_SERVER_TIMEOUT_SECONDS')
        ? max($pythonTimeout + 45, (int) FACE_MATCH_SERVER_TIMEOUT_SECONDS)
        : max(180, $pythonTimeout + 90);
    @set_time_limit($serverTimeout);
    @ini_set('max_execution_time', (string) $serverTimeout);
    @ini_set('max_input_time', (string) $serverTimeout);
    @ini_set('default_socket_timeout', (string) max(120, $serverTimeout));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$captured_image = $_POST['captured_image'] ?? '';
if (empty($captured_image)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$student_id = $_SESSION['student_id'];
$student_nisn = $_SESSION['student_nisn'] ?? null;
$student_name = $_SESSION['student_name'] ?? null;

$db = new Database();
$photo_reference = $_SESSION['photo_reference'] ?? null;
if (!$student_nisn || !$student_name) {
    $stmt = $db->query('SELECT student_nisn, student_name, photo_reference FROM student WHERE id = ?', [$student_id]);
    $student = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($student) {
        $student_nisn = $student_nisn ?: $student['student_nisn'];
        $student_name = $student_name ?: $student['student_name'];
        $photo_reference = $photo_reference ?: ($student['photo_reference'] ?? null);
    }
}

if (!$student_nisn) {
    echo json_encode(['success' => false, 'message' => 'NISN tidak ditemukan']);
    exit;
}

$faceMatcher = new FaceMatcher();
$referencePath = $faceMatcher->getReferencePath($student_nisn, $photo_reference);
if (!$referencePath) {
    echo json_encode(['success' => false, 'message' => 'Foto referensi tidak ditemukan']);
    exit;
}

$selfieResult = $faceMatcher->saveSelfie($student_id, $captured_image);
if (empty($selfieResult['success'])) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan foto']);
    exit;
}

$label = $student_name ?: $student_nisn;
$matchResult = $faceMatcher->matchFaces($referencePath, $selfieResult['path'], [
    'label' => $label
]);

if (!empty($selfieResult['path']) && file_exists($selfieResult['path'])) {
    unlink($selfieResult['path']);
}

if (empty($matchResult['success'])) {
    echo json_encode([
        'success' => false,
        'message' => $matchResult['error'] ?? 'Gagal memproses wajah'
    ]);
    exit;
}

$similarity = $matchResult['similarity'] ?? 0;
$passed = !empty($matchResult['passed']);

$payload = [
    'success' => true,
    'passed' => $passed,
    'similarity' => $similarity,
    'threshold' => defined('FACE_MATCH_THRESHOLD') ? FACE_MATCH_THRESHOLD : 70,
    'label' => $label,
    'details' => $matchResult['details'] ?? []
];

if (!$passed) {
    unset($_SESSION['face_match_ticket']);
    $payload['message'] = 'Verifikasi wajah belum lolos';
} else {
    try {
        $matchToken = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $matchToken = sha1($student_id . '|' . microtime(true) . '|' . mt_rand());
    }
    $expiresAt = time() + 600;
    $_SESSION['face_match_ticket'] = [
        'token' => $matchToken,
        'student_id' => (int) $student_id,
        'passed' => true,
        'similarity' => (float) $similarity,
        'threshold' => (float) (defined('FACE_MATCH_THRESHOLD') ? FACE_MATCH_THRESHOLD : 70),
        'issued_at' => time(),
        'expires_at' => $expiresAt
    ];
    $payload['match_token'] = $matchToken;
    $payload['token_expires_at'] = $expiresAt;
    $payload['message'] = 'Verifikasi wajah berhasil';
}

echo json_encode($payload);
