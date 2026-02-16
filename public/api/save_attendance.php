<?php
// api/save_attendance.php
ob_start();
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/database_helper.php';
require_once '../includes/face_matcher.php';
require_once '../includes/push_service.php';
require_once '../helpers/storage_path_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$logFile = __DIR__ . '/../uploads/temp/attendance_error.log';

function logAttendanceError($message, array $context = []) {
    global $logFile;
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $entry = [
        'time' => date('c'),
        'message' => $message,
        'context' => $context
    ];
    @file_put_contents($logFile, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

register_shutdown_function(function() {
    $error = error_get_last();
    if (!$error) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }
    logAttendanceError('Fatal error', $error);
    if (ob_get_length()) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server. Silakan coba lagi.'
    ]);
    exit;
});

function respondJson($payload, int $statusCode = 200) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    respondJson(['success' => false, 'message' => 'Anda harus login terlebih dahulu'], 401);
}

$student_id = $_SESSION['student_id'];
$schedule_id = $_POST['student_schedule_id'] ?? null;
$base64Image = $_POST['captured_image'] ?? null;
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$accuracy = $_POST['accuracy'] ?? null;
$present_id = $_POST['present_id'] ?? 1; // Default Hadir
$information = $_POST['information'] ?? '';
$face_similarity = isset($_POST['face_similarity']) ? floatval($_POST['face_similarity']) : null;
$face_distance = isset($_POST['face_distance']) ? floatval($_POST['face_distance']) : null;
$face_verified = isset($_POST['face_verified']) ? ($_POST['face_verified'] === '1') : false;
$face_match_token = trim((string) ($_POST['face_match_token'] ?? ''));
$validationSnapshot = trim((string) ($_POST['validation_snapshot'] ?? ''));
$descriptorThreshold = defined('FACE_DESCRIPTOR_DISTANCE_THRESHOLD')
    ? (float) FACE_DESCRIPTOR_DISTANCE_THRESHOLD
    : 0.55;

// Validasi input
if (!$schedule_id || !$base64Image || !$latitude || !$longitude) {
    respondJson(['success' => false, 'message' => 'Data tidak lengkap'], 422);
}

try {
    // 1. Validasi jadwal dan waktu
    $sql_schedule = "
        SELECT ss.*, ss.time_in, ss.time_out, sh.shift_name, ts.subject,
               s.student_nisn, s.student_name, s.class_id, s.photo_reference,
               c.class_name,
               t.teacher_name, t.teacher_code, d.day_name
        FROM student_schedule ss
        JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
        JOIN shift sh ON ts.shift_id = sh.shift_id
        JOIN student s ON ss.student_id = s.id
        LEFT JOIN class c ON s.class_id = c.class_id
        LEFT JOIN teacher t ON ts.teacher_id = t.id
        LEFT JOIN day d ON ts.day_id = d.day_id
        WHERE ss.student_schedule_id = ?
        AND ss.student_id = ?
        AND ss.status = 'ACTIVE'
    ";
    
    $stmt = $db->query($sql_schedule, [$schedule_id, $student_id]);
    $schedule = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    
    if (!$schedule) {
        respondJson(['success' => false, 'message' => 'Jadwal tidak ditemukan'], 404);
    }
    
    // 2. Validasi waktu absensi
    $time_in = $schedule['time_in'];
    $time_out = $schedule['time_out'];
    $shift_name = $schedule['shift_name'];
    $schedule_date = $schedule['schedule_date'] ?? date('Y-m-d');

    $siteStmt = $db->query("SELECT time_tolerance FROM site LIMIT 1");
    $siteSetting = $siteStmt ? $siteStmt->fetch(PDO::FETCH_ASSOC) : null;
    $toleransi_menit = isset($siteSetting['time_tolerance']) ? (int) $siteSetting['time_tolerance'] : 15;
    if ($toleransi_menit < 0) {
        $toleransi_menit = 0;
    }
    
    $can_attend = false;
    $is_late = false;
    $late_time = 0;

    $tz = new DateTimeZone('Asia/Jakarta');
    $now = new DateTime('now', $tz);
    $current_time = $now->format('H:i:s');
    if ($shift_name == 'Full Day') {
        $time_in = '06:00:00';
        $time_out = '23:00:00';
    }
    [$start_dt, $end_dt, $base_end_dt] = buildScheduleWindow(
        $schedule_date,
        $time_in,
        $time_out,
        $tz,
        $toleransi_menit
    );

    $can_attend = ($now >= $start_dt && $now <= $end_dt);
    if ($toleransi_menit > 0 && $now > $base_end_dt) {
        $is_late = true;
        $late_time = max(0, round(($now->getTimestamp() - $base_end_dt->getTimestamp()) / 60));
    }
    
    if (!$can_attend) {
        if ($now < $start_dt) {
            respondJson(['success' => false, 'message' => 'Belum masuk waktu absensi'], 403);
        } else {
            respondJson(['success' => false, 'message' => 'Waktu absensi sudah ditutup'], 403);
        }
    }
    
    // 3. Validasi GPS
    require_once 'check_location.php';
    $gpsCheck = checkLocation($latitude, $longitude, $accuracy);
    
    if (!$gpsCheck['success']) {
        respondJson([
            'success' => false,
            'message' => 'Gagal memvalidasi lokasi'
        ], 422);
    }
    $withinRadius = !empty($gpsCheck['data']['within_radius']);
    if ((int)$present_id === 1 && !$withinRadius) {
        respondJson([
            'success' => false, 
            'message' => 'Anda berada di luar radius sekolah'
        ], 403);
    }
    
    // 4. Validasi Face Matching
    $faceMatcher = new FaceMatcher();
    
    // Simpan selfie temporary
    $selfieResult = $faceMatcher->saveSelfie($student_id, $base64Image);
    if (!$selfieResult['success']) {
        respondJson(['success' => false, 'message' => 'Gagal menyimpan foto'], 500);
    }
    if (empty($selfieResult['path']) || !file_exists($selfieResult['path'])) {
        respondJson(['success' => false, 'message' => 'File foto tidak ditemukan'], 500);
    }
    
    $trustedMatch = null;
    if ($face_match_token !== '' && !empty($_SESSION['face_match_ticket']) && is_array($_SESSION['face_match_ticket'])) {
        $ticket = $_SESSION['face_match_ticket'];
        $ticketToken = (string) ($ticket['token'] ?? '');
        $ticketStudentId = (int) ($ticket['student_id'] ?? 0);
        $ticketPassed = !empty($ticket['passed']);
        $ticketExpires = (int) ($ticket['expires_at'] ?? 0);
        if (
            hash_equals($ticketToken, $face_match_token) &&
            $ticketStudentId === (int) $student_id &&
            $ticketPassed &&
            $ticketExpires >= time()
        ) {
            $trustedMatch = $ticket;
        }
    }

    if ($trustedMatch) {
        $matchResult = [
            'success' => true,
            'passed' => true,
            'similarity' => max(
                (float) ($trustedMatch['similarity'] ?? 0),
                (float) ($face_similarity ?? 0),
                (float) FACE_MATCH_THRESHOLD
            ),
            'details' => [
                'match_source' => 'face_matching_ticket',
                'ticket_issued_at' => (int) ($trustedMatch['issued_at'] ?? time()),
                'ticket_expires_at' => (int) ($trustedMatch['expires_at'] ?? time()),
                'client_distance' => $face_distance
            ]
        ];
        unset($_SESSION['face_match_ticket']);
    } else {
        // Fallback: verifikasi ulang di server jika tiket tidak tersedia / kadaluarsa.
        $referencePath = $faceMatcher->getReferencePath(
            $schedule['student_nisn'] ?? '',
            $schedule['photo_reference'] ?? ''
        );
        if (!$referencePath) {
            if (file_exists($selfieResult['path'])) {
                @unlink($selfieResult['path']);
            }
            respondJson(['success' => false, 'message' => 'Foto referensi tidak ditemukan'], 404);
        }

        $matchResult = $faceMatcher->matchFaces($referencePath, $selfieResult['path'], [
            'label' => $schedule['student_name'] ?? $schedule['student_nisn'] ?? ''
        ]);

        if (!$matchResult['success']) {
            if (file_exists($selfieResult['path'])) {
                @unlink($selfieResult['path']);
            }
            respondJson([
                'success' => false,
                'message' => $matchResult['error'] ?? 'Gagal proses face matching'
            ], 500);
        }
    }

    $serverPassed = !empty($matchResult['passed']);
    // Descriptor distance valid range ~0.0 - 1.5. Nilai besar (mis. lbph confidence)
    // dianggap legacy dan diabaikan agar tetap kompatibel dengan form lama.
    $clientDistanceProvided = is_numeric($face_distance) && $face_distance > 0 && $face_distance <= 2.0;
    $clientDistancePassed = $clientDistanceProvided ? ((float)$face_distance <= $descriptorThreshold) : null;
    $clientStrongVerified = $face_verified && $clientDistancePassed === true;

    if ($serverPassed && $clientDistancePassed === false) {
        // Prioritaskan verifikasi server DeepFace agar hasil modal & submit konsisten.
        $matchResult['details'] = array_merge($matchResult['details'] ?? [], [
            'client_distance' => $face_distance,
            'client_distance_threshold' => $descriptorThreshold,
            'client_descriptor_mismatch' => true
        ]);
    }

    if (!$serverPassed && !$clientStrongVerified) {
        $faceMatcher->saveMatchResult(
            $student_id,
            $matchResult['similarity'],
            false,
            array_merge($matchResult['details'] ?? [], [
                'client_distance' => $clientDistanceProvided ? $face_distance : null,
                'client_distance_threshold' => $descriptorThreshold,
                'client_verified' => $face_verified ? 1 : 0
            ])
        );

        if (file_exists($selfieResult['path'])) {
            @unlink($selfieResult['path']);
        }

        respondJson([
            'success' => false,
            'message' => 'Verifikasi wajah gagal',
            'similarity' => $matchResult['similarity']
        ], 403);
    }

    if (!$serverPassed && $clientStrongVerified) {
        $matchResult['passed'] = true;
        $matchResult['similarity'] = max((float)($matchResult['similarity'] ?? 0), (float)($face_similarity ?? 0), (float)FACE_MATCH_THRESHOLD);
        $matchResult['details'] = array_merge($matchResult['details'] ?? [], [
            'client_distance' => $face_distance,
            'client_distance_threshold' => $descriptorThreshold,
            'client_descriptor_override' => true
        ]);
    }
    
    // 6. Simpan log matching sukses
    $matchLog = $faceMatcher->saveMatchResult(
        $student_id,
        $matchResult['similarity'],
        true,
        $matchResult['details']
    );
    
    // 7. Pindahkan foto ke folder attendance
    $attendanceDate = $schedule_date ?: date('Y-m-d');
    $dateTimeFolder = storage_attendance_datetime_folder($attendanceDate, $current_time);
    $classFolder = storage_class_folder($schedule['class_name'] ?? ($schedule['class_id'] ?? 'kelas'));
    $attendanceDir = "../uploads/attendance/{$dateTimeFolder}/{$classFolder}/";
    if (!is_dir($attendanceDir)) {
        if (!mkdir($attendanceDir, 0777, true)) {
            respondJson(['success' => false, 'message' => 'Gagal membuat folder absensi'], 500);
        }
    }

    $studentNameRaw = $schedule['student_name'] ?? ('siswa_' . $student_id);
    $studentNisn = $schedule['student_nisn'] ?? '';
    $baseFilename = storage_attendance_basename($studentNameRaw, $studentNisn ?: $student_id, $attendanceDate);

    $rawFilename = "{$baseFilename}_raw.jpg";
    $rawPath = $attendanceDir . $rawFilename;

    if (!@rename($selfieResult['path'], $rawPath)) {
        respondJson(['success' => false, 'message' => 'Gagal memindahkan foto absensi'], 500);
    }

    // 7b. Generate validation card
    $statusLabel = $is_late ? 'OVERDUE' : 'SUCCESS';
    $validationFilename = "{$baseFilename}.jpg";
    $validationPath = $attendanceDir . $validationFilename;
    $validationGenerated = false;

    $schoolLocation = null;
    if (function_exists('getDefaultSchoolLocation')) {
        $schoolLocation = getDefaultSchoolLocation($db);
    }

    $jpRange = $schedule['shift_name'] ?? '';
    if (!$jpRange && !empty($time_in) && !empty($time_out)) {
        $jpRange = $time_in . ' - ' . $time_out;
    }

    $validationMeta = [
        'student_name' => $schedule['student_name'] ?? 'Siswa',
        'date' => $attendanceDate ? date('d/m/Y', strtotime($attendanceDate)) : date('d/m/Y'),
        'time' => $current_time,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'distance' => $gpsCheck['data']['distance'] ?? 0,
        'accuracy' => $gpsCheck['data']['accuracy'] ?? null,
        'status' => $statusLabel,
        'subject' => $schedule['subject'] ?? '-',
        'teacher_name' => $schedule['teacher_name'] ?? '-',
        'teacher_code' => $schedule['teacher_code'] ?? '-',
        'jp_range' => $jpRange ?: '-',
        'day_name' => $schedule['day_name'] ?? null,
        'present_label' => $present_id === 1 ? 'Hadir' : ($present_id === 2 ? 'Sakit' : ($present_id === 3 ? 'Izin' : 'Alpa')),
        'location_name' => 'Lokasi Siswa',
        'school_name' => $schoolLocation['location_name'] ?? 'Lokasi Sekolah',
        'school_address' => $schoolLocation['address'] ?? ''
    ];

    if ($validationSnapshot !== '') {
        if (saveBase64Image($validationSnapshot, $validationPath)) {
            $validationGenerated = true;
        }
    }

    if (!$validationGenerated) {
        if (createValidationCard($rawPath, $validationPath, $validationMeta)) {
            $validationGenerated = true;
        } else {
            $validationPath = null;
        }
    }

    $storedFilename = $validationGenerated ? $validationFilename : $rawFilename;
    $attendancePath = $validationGenerated ? $validationPath : $rawPath;
    $attendanceFilename = $dateTimeFolder . '/' . $classFolder . '/' . $storedFilename;

    if ($validationGenerated && file_exists($rawPath)) {
        @unlink($rawPath);
    }

    // 7c. Write attendance log
    $logDir = "../uploads/temp/capture/";
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . "attendance_log.txt";
    $logEntry = [
        'timestamp' => date('c'),
        'student_id' => $student_id,
        'schedule_id' => $schedule_id,
        'status' => $statusLabel,
        'distance' => $gpsCheck['data']['distance'] ?? 0,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'attendance_path' => str_replace('../', '', $attendancePath),
        'validation_path' => $validationGenerated ? str_replace('../', '', $validationPath) : null
    ];
    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // 8. Simpan data absensi ke database
    $sql_insert = "
        INSERT INTO presence (
            student_id, student_schedule_id, presence_date, time_in,
            picture_in, present_id, latitude_in, longitude_in,
            distance_in, is_late, late_time, information
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $params = [
        $student_id,
        $schedule_id,
        $schedule_date,
        $current_time,
        $attendanceFilename,
        $present_id,
        $latitude,
        $longitude,
        $gpsCheck['data']['distance'],
        $is_late ? 'Y' : 'N',
        $late_time,
        $information
    ];
    
    $pdo = $db->getConnection();
    try {
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute($params);
    } catch (PDOException $e) {
        logAttendanceError('Insert presence failed', [
            'error' => $e->getMessage(),
            'schedule_id' => $schedule_id,
            'student_id' => $student_id
        ]);
        respondJson(['success' => false, 'message' => 'Gagal menyimpan data absensi'], 500);
    }

    if ($stmt) {
        // Update status student_schedule
        $sql_update = "
            UPDATE student_schedule 
            SET status = 'COMPLETED', updated_at = NOW()
            WHERE student_schedule_id = ?
        ";
        try {
            $upd = $pdo->prepare($sql_update);
            $upd->execute([$schedule_id]);
        } catch (PDOException $e) {
            logAttendanceError('Update student_schedule failed', [
                'error' => $e->getMessage(),
                'schedule_id' => $schedule_id,
                'student_id' => $student_id
            ]);
            respondJson(['success' => false, 'message' => 'Gagal memperbarui status jadwal'], 500);
        }
        
        // Insert activity log
        $log_sql = "
            INSERT INTO activity_logs 
            (user_id, user_type, action, details, ip_address, user_agent)
            VALUES (?, 'student', 'attendance', ?, ?, ?)
        ";
        $log_details = json_encode([
            'schedule_id' => $schedule_id,
            'similarity' => $matchResult['similarity'],
            'match_details' => $matchResult['details'],
            'status' => $statusLabel,
            'attendance_path' => str_replace('../', '', $attendancePath),
            'validation_path' => $validationGenerated ? str_replace('../', '', $validationPath) : null
        ]);
        
        try {
            $logStmt = $pdo->prepare($log_sql);
            $logStmt->execute([
                $student_id,
                $log_details,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (PDOException $e) {
            logAttendanceError('Insert activity log failed', [
                'error' => $e->getMessage(),
                'schedule_id' => $schedule_id,
                'student_id' => $student_id
            ]);
        }

        // Push notification for attendance result
        $subjectName = $schedule['subject'] ?? 'Pelajaran';
        $attendanceTitle = $is_late ? 'Absensi Overdue Berhasil' : 'Absensi Berhasil';
        $attendanceBody = $is_late
            ? "Absensi $subjectName berhasil dalam kondisi overdue."
            : "Absensi $subjectName berhasil. Terima kasih.";
        $attendanceUrl = '/dashboard/siswa.php?page=riwayat';
        sendPushToStudent($db, $student_id, [
            'title' => $attendanceTitle,
            'body' => $attendanceBody,
            'url' => $attendanceUrl
        ]);
        
        $presentLabel = ((int) $present_id === 2) ? 'Sakit' : (((int) $present_id === 3) ? 'Izin' : 'Hadir');
        respondJson([
            'success' => true,
            'message' => 'Absensi berhasil',
            'data' => [
                'similarity' => $matchResult['similarity'],
                'match_log' => $matchLog,
                'attendance_path' => str_replace('../', '', $attendancePath),
                'validation_path' => $validationGenerated ? str_replace('../', '', $validationPath) : null,
                'status' => $statusLabel,
                'attendance_time' => $current_time,
                'attendance_date' => $schedule_date,
                'day_name' => $schedule['day_name'] ?? null,
                'subject' => $schedule['subject'] ?? null,
                'jp_range' => $jpRange ?: null,
                'teacher_name' => $schedule['teacher_name'] ?? null,
                'student_name' => $schedule['student_name'] ?? null,
                'present_label' => $presentLabel
            ]
        ]);
    } else {
        respondJson(['success' => false, 'message' => 'Gagal menyimpan data absensi'], 500);
    }
    
} catch (Exception $e) {
    respondJson(['success' => false, 'message' => $e->getMessage()], 500);
}

/**
 * Create validation card image (simple GD layout)
 */
function slugifyFilename($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return 'siswa';
    }
    $text = preg_replace('~[^\pL\d]+~u', '_', $text);
    $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
    if ($converted !== false) {
        $text = $converted;
    }
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '_');
    $text = preg_replace('~_+~', '_', $text);
    $text = strtolower($text);
    return $text !== '' ? $text : 'siswa';
}

function saveBase64Image($base64Image, $outputPath) {
    $base64Image = trim((string) $base64Image);
    if ($base64Image === '') {
        return false;
    }
    $data = preg_replace('#^data:image/\\w+;base64,#i', '', $base64Image);
    $imageData = base64_decode($data);
    if ($imageData === false) {
        return false;
    }
    return file_put_contents($outputPath, $imageData) !== false;
}

function createValidationCard($sourcePath, $outputPath, $meta) {
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return false;
    }

    $srcImage = loadImageResource($sourcePath);
    if (!$srcImage) {
        return false;
    }

    $width = 1280;
    $height = 760;
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);
    $bg = imagecolorallocate($canvas, 8, 15, 30);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $bg);

    $white = imagecolorallocate($canvas, 245, 248, 255);
    $muted = imagecolorallocate($canvas, 165, 178, 198);
    $soft = imagecolorallocate($canvas, 203, 213, 225);
    $accent = imagecolorallocate($canvas, 56, 189, 248);
    $success = imagecolorallocate($canvas, 16, 185, 129);
    $warning = imagecolorallocate($canvas, 245, 158, 11);
    $danger = imagecolorallocate($canvas, 239, 68, 68);
    $panelBg = imagecolorallocatealpha($canvas, 10, 20, 38, 24);
    $panelBorder = imagecolorallocatealpha($canvas, 148, 163, 184, 66);
    $detailBg = imagecolorallocatealpha($canvas, 13, 24, 45, 18);

    $padding = 26;
    $photoW = $width - ($padding * 2);
    $photoH = 500;
    $photoX = $padding;
    $photoY = 24;

    // Selfie full-width area with crop cover.
    $srcW = imagesx($srcImage);
    $srcH = imagesy($srcImage);
    $targetRatio = $photoW / $photoH;
    $srcRatio = $srcH > 0 ? ($srcW / $srcH) : 1;
    if ($srcRatio > $targetRatio) {
        $newW = (int) round($srcH * $targetRatio);
        $cropX = (int) (($srcW - $newW) / 2);
        $cropY = 0;
        $cropW = $newW;
        $cropH = $srcH;
    } else {
        $newH = (int) round($srcW / $targetRatio);
        $cropX = 0;
        $cropY = (int) (($srcH - $newH) / 2);
        $cropW = $srcW;
        $cropH = $newH;
    }
    imagecopyresampled($canvas, $srcImage, $photoX, $photoY, $cropX, $cropY, $photoW, $photoH, $cropW, $cropH);

    // Subtle gradient for readability on bottom of photo.
    for ($i = 0; $i < 120; $i++) {
        $alpha = min(95, 82 + (int) ($i * 0.1));
        $shade = imagecolorallocatealpha($canvas, 5, 10, 18, $alpha);
        imageline($canvas, $photoX, $photoY + $photoH - $i, $photoX + $photoW, $photoY + $photoH - $i, $shade);
    }

    // Compact metadata panel overlay (right-bottom).
    $overlayW = 500;
    $overlayH = 220;
    $overlayX = $photoX + $photoW - $overlayW - 18;
    $overlayY = $photoY + $photoH - $overlayH - 16;
    imagefilledrectangle($canvas, $overlayX, $overlayY, $overlayX + $overlayW, $overlayY + $overlayH, $panelBg);
    imagerectangle($canvas, $overlayX, $overlayY, $overlayX + $overlayW, $overlayY + $overlayH, $panelBorder);

    $statusText = strtoupper($meta['status'] ?? 'SUCCESS');
    $presentLabel = strtoupper((string) ($meta['present_label'] ?? 'HADIR'));
    $statusColor = ($statusText === 'OVERDUE') ? $warning : ($statusText === 'FAILED' ? $danger : $success);
    imagefilledrectangle($canvas, $overlayX + 14, $overlayY + 12, $overlayX + 168, $overlayY + 36, $statusColor);
    imagestring($canvas, 3, $overlayX + 24, $overlayY + 18, $statusText, $white);
    imagefilledrectangle($canvas, $overlayX + 176, $overlayY + 12, $overlayX + 334, $overlayY + 36, $accent);
    imagestring($canvas, 3, $overlayX + 186, $overlayY + 18, $presentLabel, $white);

    imagestring($canvas, 4, $overlayX + 14, $overlayY + 46, 'Geolocation', $white);
    imagestring($canvas, 2, $overlayX + 14, $overlayY + 66, $meta['location_name'] ?? 'Lokasi Siswa', $soft);

    $latText = isset($meta['latitude']) ? number_format((float) $meta['latitude'], 6) : '-';
    $lngText = isset($meta['longitude']) ? number_format((float) $meta['longitude'], 6) : '-';
    $distText = isset($meta['distance']) ? number_format((float) $meta['distance'], 1) . ' m' : '-';
    $accText = isset($meta['accuracy']) ? '+/-' . number_format((float) $meta['accuracy'], 1) . ' m' : '-';

    imagestring($canvas, 2, $overlayX + 14, $overlayY + 86, "Lat: {$latText}", $muted);
    imagestring($canvas, 2, $overlayX + 14, $overlayY + 104, "Lng: {$lngText}", $muted);
    imagestring($canvas, 2, $overlayX + 14, $overlayY + 122, "Jarak: {$distText}", $muted);
    imagestring($canvas, 2, $overlayX + 14, $overlayY + 140, "Akurasi: {$accText}", $muted);
    imagestring($canvas, 2, $overlayX + 14, $overlayY + 160, "Update: " . ($meta['time'] ?? '-'), $muted);

    // HD map preview with marker.
    $mapW = 220;
    $mapH = 132;
    $mapX = $overlayX + $overlayW - $mapW - 14;
    $mapY = $overlayY + 72;
    $mapBg = imagecolorallocate($canvas, 30, 41, 59);
    imagefilledrectangle($canvas, $mapX, $mapY, $mapX + $mapW, $mapY + $mapH, $mapBg);
    imagerectangle($canvas, $mapX, $mapY, $mapX + $mapW, $mapY + $mapH, $panelBorder);

    $mapImage = fetchMapCompositeImage($meta['latitude'] ?? null, $meta['longitude'] ?? null, 19, 3);
    if ($mapImage) {
        imagecopyresampled($canvas, $mapImage, $mapX, $mapY, 0, 0, $mapW, $mapH, imagesx($mapImage), imagesy($mapImage));
        imagedestroy($mapImage);
        $markerX = (int) ($mapX + $mapW / 2);
        $markerY = (int) ($mapY + $mapH / 2);
        imagefilledellipse($canvas, $markerX, $markerY, 14, 14, $danger);
        imageellipse($canvas, $markerX, $markerY, 26, 26, $white);
    }

    // Attendance details section (compact under photo).
    $sectionY = $photoY + $photoH + 20;
    $detailH = $height - $sectionY - 20;
    imagefilledrectangle($canvas, $photoX, $sectionY, $photoX + $photoW, $sectionY + $detailH, $detailBg);
    imagerectangle($canvas, $photoX, $sectionY, $photoX + $photoW, $sectionY + $detailH, $panelBorder);
    imagestring($canvas, 5, $photoX + 14, $sectionY + 10, 'Keterangan Absensi', $white);

    $details = [
        ['Waktu absen', $meta['time'] ?? '-'],
        ['Nama mapel', $meta['subject'] ?? '-'],
        ['Hari', $meta['day_name'] ?? '-'],
        ['Tanggal', $meta['date'] ?? '-'],
        ['Jam pelajaran', $meta['jp_range'] ?? '-'],
        ['Nama guru', $meta['teacher_name'] ?? '-'],
        ['Nama siswa', $meta['student_name'] ?? '-'],
        ['Absen', $meta['present_label'] ?? ($meta['status'] ?? '-')]
    ];

    $rowY = $sectionY + 42;
    $colX1 = $photoX + 14;
    $colX2 = $photoX + (int)($photoW / 2) + 8;
    $rowGap = 30;
    for ($i = 0; $i < count($details); $i++) {
        $colX = $i % 2 === 0 ? $colX1 : $colX2;
        if ($i % 2 === 0 && $i > 0) {
            $rowY += $rowGap;
        }
        $value = (string) $details[$i][1];
        if (strlen($value) > 42) {
            $value = substr($value, 0, 39) . '...';
        }
        imagestring($canvas, 2, $colX, $rowY, $details[$i][0], $muted);
        imagestring($canvas, 4, $colX, $rowY + 12, $value, $white);
    }

    $saved = imagejpeg($canvas, $outputPath, 92);
    imagedestroy($srcImage);
    imagedestroy($canvas);

    return $saved;
}

function fetchMapCompositeImage($lat, $lng, $zoom = 18, $gridSize = 3) {
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }
    $gridSize = max(1, min(5, (int) $gridSize));
    $tileSize = 256;
    $canvasSize = $gridSize * $tileSize;
    $mapCanvas = imagecreatetruecolor($canvasSize, $canvasSize);
    if (!$mapCanvas) {
        return null;
    }
    imagealphablending($mapCanvas, true);
    imagesavealpha($mapCanvas, true);
    $bg = imagecolorallocate($mapCanvas, 28, 39, 58);
    imagefilledrectangle($mapCanvas, 0, 0, $canvasSize, $canvasSize, $bg);

    $tilePos = latLngToTileFraction($lat, $lng, $zoom);
    if (!$tilePos) {
        imagedestroy($mapCanvas);
        return null;
    }

    $baseX = (int) floor($tilePos['x']) - (int) floor($gridSize / 2);
    $baseY = (int) floor($tilePos['y']) - (int) floor($gridSize / 2);
    $maxTile = (int) pow(2, $zoom) - 1;
    $drawn = 0;

    for ($dy = 0; $dy < $gridSize; $dy++) {
        for ($dx = 0; $dx < $gridSize; $dx++) {
            $tileX = $baseX + $dx;
            $tileY = $baseY + $dy;
            while ($tileX < 0) {
                $tileX += ($maxTile + 1);
            }
            while ($tileX > $maxTile) {
                $tileX -= ($maxTile + 1);
            }
            if ($tileY < 0 || $tileY > $maxTile) {
                continue;
            }
            $tileImage = fetchMapTileImageByXYZ($tileX, $tileY, $zoom);
            if (!$tileImage) {
                continue;
            }
            imagecopy($mapCanvas, $tileImage, $dx * $tileSize, $dy * $tileSize, 0, 0, imagesx($tileImage), imagesy($tileImage));
            imagedestroy($tileImage);
            $drawn++;
        }
    }

    if ($drawn === 0) {
        imagedestroy($mapCanvas);
        return null;
    }

    // Re-center around exact fractional tile coordinate.
    $offsetX = (int) round(($tilePos['x'] - floor($tilePos['x'])) * $tileSize);
    $offsetY = (int) round(($tilePos['y'] - floor($tilePos['y'])) * $tileSize);
    $centerCanvas = imagecreatetruecolor($canvasSize, $canvasSize);
    if (!$centerCanvas) {
        imagedestroy($mapCanvas);
        return null;
    }
    imagealphablending($centerCanvas, true);
    imagesavealpha($centerCanvas, true);
    imagefilledrectangle($centerCanvas, 0, 0, $canvasSize, $canvasSize, $bg);

    $sourceX = max(0, $tileSize * (int) floor($gridSize / 2) + $offsetX - (int) floor($canvasSize / 2));
    $sourceY = max(0, $tileSize * (int) floor($gridSize / 2) + $offsetY - (int) floor($canvasSize / 2));
    if ($sourceX + $canvasSize > imagesx($mapCanvas)) {
        $sourceX = max(0, imagesx($mapCanvas) - $canvasSize);
    }
    if ($sourceY + $canvasSize > imagesy($mapCanvas)) {
        $sourceY = max(0, imagesy($mapCanvas) - $canvasSize);
    }
    imagecopy($centerCanvas, $mapCanvas, 0, 0, $sourceX, $sourceY, $canvasSize, $canvasSize);
    imagedestroy($mapCanvas);

    return $centerCanvas;
}

function fetchMapTileImageByXYZ($x, $y, $zoom) {
    $x = (int) $x;
    $y = (int) $y;
    $zoom = (int) $zoom;
    $url = "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{$zoom}/{$y}/{$x}";
    $context = stream_context_create([
        'http' => [
            'timeout' => 3,
            'header' => "User-Agent: Presenova\r\n"
        ]
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        return null;
    }
    $image = @imagecreatefromstring($data);
    if (!$image) {
        return null;
    }
    return $image;
}

function latLngToTile($lat, $lng, $zoom) {
    $latRad = deg2rad($lat);
    $n = pow(2, $zoom);
    $x = (int) floor(($lng + 180.0) / 360.0 * $n);
    $y = (int) floor((1.0 - log(tan($latRad) + (1 / cos($latRad))) / pi()) / 2.0 * $n);
    return ['x' => $x, 'y' => $y];
}

function latLngToTileFraction($lat, $lng, $zoom) {
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }
    $lat = max(-85.05112878, min(85.05112878, (float) $lat));
    $lng = (float) $lng;
    $n = pow(2, (int) $zoom);
    $x = (($lng + 180.0) / 360.0) * $n;
    $latRad = deg2rad($lat);
    $y = (1.0 - log(tan($latRad) + (1 / cos($latRad))) / pi()) / 2.0 * $n;
    return ['x' => $x, 'y' => $y];
}

function loadImageResource($path) {

    if (!file_exists($path)) {
        return null;
    }
    $info = getimagesize($path);
    if (!$info || empty($info['mime'])) {
        return null;
    }
    if ($info['mime'] === 'image/jpeg') {
        return imagecreatefromjpeg($path);
    }
    if ($info['mime'] === 'image/png') {
        return imagecreatefrompng($path);
    }
    if ($info['mime'] === 'image/gif') {
        return imagecreatefromgif($path);
    }
    return null;
}

function wrapText($text, $maxChars) {
    $words = preg_split('/\s+/', trim($text));
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $test = $current === '' ? $word : $current . ' ' . $word;
        if (strlen($test) > $maxChars) {
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    return $lines;
}

/**
 * Helper function untuk check GPS
 */
function checkLocation($latitude, $longitude, $accuracy = null) {
    global $db;

    $school = null;
    if (function_exists('getDefaultSchoolLocation')) {
        $school = getDefaultSchoolLocation($db);
    } else {
        $siteStmt = $db->query("SELECT default_location_id FROM site LIMIT 1");
        $site = $siteStmt ? $siteStmt->fetch(PDO::FETCH_ASSOC) : null;
        $locationId = !empty($site['default_location_id']) ? (int) $site['default_location_id'] : 0;

        if ($locationId > 0) {
            $locStmt = $db->query("SELECT * FROM school_location WHERE location_id = ? LIMIT 1", [$locationId]);
            $school = $locStmt ? $locStmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($school && $school['is_active'] !== 'Y') {
                $school = null;
            }
        }

        if (!$school) {
            $stmt = $db->query("SELECT * FROM school_location WHERE is_active = 'Y' ORDER BY location_id DESC LIMIT 1");
            $school = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        }
    }
    
    if (!$school) {
        return ['success' => false, 'message' => 'Lokasi sekolah belum dikonfigurasi'];
    }
    
    // Calculate distance
    $distance = calculateDistance(
        $latitude,
        $longitude,
        $school['latitude'],
        $school['longitude']
    );

    $accuracyVal = is_numeric($accuracy) ? (float) $accuracy : null;
    $radius = (float) $school['radius'];
    $accuracyBuffer = 0;
    if ($accuracyVal !== null && $accuracyVal > 0) {
        $accuracyBuffer = min($accuracyVal, max(50, $radius * 1.5));
    }
    
    return [
        'success' => true,
        'data' => [
            'distance' => round($distance, 2),
            'within_radius' => $distance <= ($radius + $accuracyBuffer),
            'radius_limit' => $school['radius'],
            'accuracy' => $accuracyVal !== null ? round($accuracyVal, 2) : null,
            'accuracy_buffer' => round($accuracyBuffer, 2)
        ]
    ];
}
