<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../helpers/storage_path_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string) $rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload tidak valid']);
    exit;
}

$rightFrames = isset($payload['right']) && is_array($payload['right']) ? $payload['right'] : [];
$leftFrames = isset($payload['left']) && is_array($payload['left']) ? $payload['left'] : [];
$frontFrames = isset($payload['front']) && is_array($payload['front']) ? $payload['front'] : [];

if (count($rightFrames) < 5 || count($leftFrames) < 5 || count($frontFrames) < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Jumlah frame pose belum lengkap']);
    exit;
}

function decode_pose_image($dataUrl)
{
    if (!is_string($dataUrl) || trim($dataUrl) === '') {
        return null;
    }
    if (!preg_match('#^data:image/(jpeg|jpg|png);base64,#i', $dataUrl)) {
        return null;
    }
    $raw = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
    $raw = str_replace(' ', '+', (string) $raw);
    $binary = base64_decode($raw, true);
    if ($binary === false || strlen($binary) < 400) {
        return null;
    }
    return $binary;
}

$db = new Database();
$studentId = (int) $_SESSION['student_id'];
$studentStmt = $db->query(
    'SELECT s.student_nisn, s.student_name, c.class_name
     FROM student s
     LEFT JOIN class c ON s.class_id = c.class_id
     WHERE s.id = ? LIMIT 1',
    [$studentId]
);
$student = $studentStmt ? $studentStmt->fetch(PDO::FETCH_ASSOC) : null;
$nisn = trim((string) ($student['student_nisn'] ?? ''));
if ($nisn === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'NISN siswa tidak ditemukan']);
    exit;
}

$facesBase = realpath(__DIR__ . '/../uploads/faces');
if ($facesBase === false) {
    $facesBase = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'faces';
}
$classFolder = storage_class_folder($student['class_name'] ?? 'kelas');
$studentFolder = storage_student_folder($student['student_name'] ?? ('siswa_' . $nisn));
$poseDir = rtrim($facesBase, '/\\') . DIRECTORY_SEPARATOR . $classFolder . DIRECTORY_SEPARATOR . $studentFolder . DIRECTORY_SEPARATOR . 'pose';
if (!is_dir($poseDir) && !mkdir($poseDir, 0777, true) && !is_dir($poseDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal membuat folder pose']);
    exit;
}

$oldFiles = glob($poseDir . DIRECTORY_SEPARATOR . '*') ?: [];
foreach ($oldFiles as $oldFile) {
    if (is_file($oldFile)) {
        @unlink($oldFile);
    }
}

$writeFrames = function (array $frames, string $prefix, int $maxCount) use ($poseDir) {
    $saved = 0;
    $limited = array_slice($frames, 0, $maxCount);
    foreach ($limited as $index => $frame) {
        $binary = decode_pose_image($frame);
        if ($binary === null) {
            continue;
        }
        $filename = sprintf('%s_%02d.jpg', $prefix, $index + 1);
        $path = $poseDir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $binary) !== false) {
            $saved++;
        }
    }
    return $saved;
};

$savedRight = $writeFrames($rightFrames, 'right', 5);
$savedLeft = $writeFrames($leftFrames, 'left', 5);
$savedFront = $writeFrames($frontFrames, 'front', 1);

if ($savedRight < 5 || $savedLeft < 5 || $savedFront < 1) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sebagian frame pose gagal disimpan']);
    exit;
}

$manifest = [
    'student_id' => $studentId,
    'student_nisn' => $nisn,
    'saved_at' => date('c'),
    'counts' => [
        'right' => $savedRight,
        'left' => $savedLeft,
        'front' => $savedFront
    ]
];
file_put_contents($poseDir . DIRECTORY_SEPARATOR . 'pose_manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

$_SESSION['has_pose_capture'] = true;

echo json_encode([
    'success' => true,
    'message' => 'Frame pose berhasil disimpan',
    'counts' => $manifest['counts']
]);
