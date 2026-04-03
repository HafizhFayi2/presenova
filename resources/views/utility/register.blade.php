<?php
// Build URLs from request context to avoid redirects to XAMPP dashboard/root.
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = trim((string) dirname($scriptName), '/.');
$scriptSegments = array_values(array_filter(
    explode('/', $scriptDir),
    static fn (string $segment): bool => $segment !== ''
));
if ($scriptSegments !== [] && strcasecmp((string) end($scriptSegments), 'public') === 0) {
    array_pop($scriptSegments);
}
$basePrefix = $scriptSegments === [] ? '' : '/' . implode('/', $scriptSegments);

$appPath = static function (string $path = '') use ($basePrefix): string {
    $cleanPath = ltrim($path, '/');
    if ($cleanPath === '') {
        return $basePrefix === '' ? '/' : $basePrefix . '/';
    }

    return ($basePrefix === '' ? '/' : $basePrefix . '/') . $cleanPath;
};

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
$scheme = $isHttps ? 'https' : 'http';
$host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$siteUrl = $scheme . '://' . $host . ($basePrefix !== '' ? $basePrefix : '') . '/';

$appRootUrl = rtrim($siteUrl, '/');
$assetBaseUrl = $siteUrl;
$loginUrl = $appPath('login.php');
$registerUrl = $appPath('register.php');
$dashboardUrl = $appPath('dashboard/siswa.php');
$registerLogoutUrl = $registerUrl . '?logout=1';
$registerUploadFaceUrl = $registerUrl . '?upload_face=1';
$registerPoseOnlyUrl = $registerUrl . '?upload_face=1&pose_only=1';
$appDialogCssUrl = $appPath('assets/css/app-dialog.css');
$mainStyleCssUrl = $appPath('assets/css/style.css');
$appDialogScriptUrl = $appPath('assets/js/app-dialog.js');
$faceApiScriptUrl = $appPath('face/faces_logics/face-api.min.js');
$faceModelBaseUrl = $appPath('face/faces_logics/models');
$savePoseFramesUrl = $appPath('api/save_pose_frames.php');

try {
    $auth = new \App\Support\Core\Auth();
} catch (\Throwable) {
    header('Location: ' . $loginUrl . '?db_error=1');
    exit();
}

// Handle logout request
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: ' . $loginUrl . '?logout_success=1');
    exit();
}

// Check if student is logged in
if (!$auth->isLoggedIn() || !isset($_SESSION['student_id'])) {
    header('Location: ' . $loginUrl);
    exit();
}

try {
    $db = new \App\Support\Core\Database();
} catch (\Throwable) {
    header('Location: ' . $loginUrl . '?db_error=1');
    exit();
}

if (!function_exists('register_has_pose_capture_dataset')) {
    function register_has_pose_capture_dataset($studentNisn, $className, $studentName)
    {
        $studentNisn = trim((string) $studentNisn);
        if ($studentNisn === '') {
            return false;
        }
        $classFolder = storage_class_folder($className ?: 'kelas');
        $studentFolder = storage_student_folder($studentName ?: ('siswa_' . $studentNisn));
        $poseDir = public_path('uploads/faces/' . $classFolder . '/' . $studentFolder . '/pose');
        if (!is_dir($poseDir)) {
            return false;
        }
        $right = glob($poseDir . '/right_*.jpg') ?: [];
        $left = glob($poseDir . '/left_*.jpg') ?: [];
        $front = glob($poseDir . '/front_*.jpg') ?: [];
        return count($right) >= 5 && count($left) >= 5 && count($front) >= 1;
    }
}

$student_id = $_SESSION['student_id'];
$photo_reference = null;
$has_face = false;
$pose_only = isset($_GET['pose_only']) && $_GET['pose_only'] === '1';
$has_pose_capture = false;
$student_identity = null;

$stmt = $db->query(
    "SELECT s.student_nisn, s.student_name, s.photo_reference, c.class_name
     FROM student s
     LEFT JOIN class c ON s.class_id = c.class_id
     WHERE s.id = ?",
    [$student_id]
);
$student_row = $stmt ? $stmt->fetch() : null;
$student_identity = $student_row ?: null;

if ($student_row && !empty($student_row['photo_reference'])) {
    $photoReferenceRaw = (string) $student_row['photo_reference'];
    $resolvedFacePath = resolve_face_reference_file_path($photoReferenceRaw);
    $photo_reference = normalize_face_reference_path($photoReferenceRaw);
    if ($photo_reference === '' && $resolvedFacePath) {
        $photo_reference = face_reference_relative_from_file($resolvedFacePath);
    }
    $has_face = $resolvedFacePath !== null;

    if ($has_face && $photo_reference !== '' && strcasecmp($photoReferenceRaw, $photo_reference) !== 0) {
        $db->query(
            "UPDATE student SET photo_reference = ? WHERE id = ?",
            [$photo_reference, $student_id]
        );
    }

    if (!$has_face) {
        $db->query(
            "UPDATE student SET photo_reference = NULL, face_embedding = NULL WHERE id = ?",
            [$student_id]
        );
        $_SESSION['face_reference_notice'] = 'Sistem tidak menemukan photo referensi. Mohon photo ulang.';
    }
} elseif ($student_row && !empty($student_row['student_nisn'])) {
    try {
        $fallbackMatcher = app(\App\Services\FaceMatcherService::class);
        $fallbackPath = $fallbackMatcher->getReferencePath((string) $student_row['student_nisn'], null);
        if ($fallbackPath && is_file($fallbackPath)) {
            $photo_reference = face_reference_relative_from_file($fallbackPath);
            if ($photo_reference !== '') {
                $db->query(
                    "UPDATE student SET photo_reference = ? WHERE id = ?",
                    [$photo_reference, $student_id]
                );
            }
            $has_face = true;
        }
    } catch (\Throwable) {
        // Fallback matcher is optional; continue without blocking registration page.
    }
}

$has_pose_capture = register_has_pose_capture_dataset(
    $student_row['student_nisn'] ?? '',
    $student_row['class_name'] ?? '',
    $student_row['student_name'] ?? ''
);
$_SESSION['has_face'] = $has_face;
$_SESSION['has_pose_capture'] = $has_pose_capture;

// If student already has full data, redirect to dashboard.
if ($has_face && $has_pose_capture) {
    header('Location: ' . $dashboardUrl);
    exit();
}
if ($pose_only && !$has_face) {
    header('Location: ' . $registerUploadFaceUrl);
    exit();
}
if ($has_face && !$has_pose_capture && !$pose_only) {
    header('Location: ' . $registerPoseOnlyUrl);
    exit();
}
$pose_only_mode = $pose_only && $has_face && !$has_pose_capture;

$success = false;
$error = '';
$face_notice = '';
$pose_notice = '';
if (!empty($_SESSION['face_reference_notice'])) {
    $face_notice = $_SESSION['face_reference_notice'];
    unset($_SESSION['face_reference_notice']);
}
if (!empty($_SESSION['face_pose_notice'])) {
    $pose_notice = $_SESSION['face_pose_notice'];
    unset($_SESSION['face_pose_notice']);
}

// Handle face registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['face_data'])) {
    if ($pose_only_mode) {
        $error = "Mode pose-only tidak membutuhkan upload foto depan.";
    } elseif (empty($_SESSION['has_pose_capture'])) {
        $error = "Silakan selesaikan pose capture (5 kanan, 5 kiri, 1 depan) terlebih dahulu.";
    }
    
    $face_data = $_POST['face_data'] ?? '';
    $student_id = $_SESSION['student_id'];
    if (empty($error) && trim((string) $face_data) === '') {
        $error = "Data foto depan tidak valid.";
    }
    
    // Decode base64 image
    $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $face_data));
    if (empty($error) && ($image_data === false || strlen($image_data) < 400)) {
        $error = "Foto depan tidak dapat diproses. Silakan ambil ulang.";
    }
    
    // Get student data for filename
    $sql = "SELECT s.student_nisn, s.student_name, c.class_name
            FROM student s
            LEFT JOIN class c ON s.class_id = c.class_id
            WHERE s.id = ?";
    $stmt = $db->query($sql, [$student_id]);
    $student_data = $stmt ? $stmt->fetch() : null;
    
    if (!empty($error)) {
        // Do nothing, show error from pose validation.
    } elseif (!$student_data) {
        $error = "Data siswa tidak ditemukan!";
    } else {
        $nisn = $student_data['student_nisn'];
        $classFolder = storage_class_folder($student_data['class_name'] ?? 'kelas');
        $studentFolder = storage_student_folder($student_data['student_name'] ?? ('siswa_' . $nisn));
        $filename = storage_face_reference_filename($nisn, $student_data['student_name'] ?? 'siswa');
        $relativePath = $classFolder . '/' . $studentFolder . '/' . $filename;
        $filepath = public_path('uploads/faces/' . $relativePath);
        
        // Buat direktori jika belum ada
        $faceDir = dirname($filepath) . DIRECTORY_SEPARATOR;
        if (!file_exists($faceDir)) {
            mkdir($faceDir, 0777, true);
        }
        
        // Save image to server
        if (file_put_contents($filepath, $image_data)) {
            // Cek apakah GD library tersedia sebelum kompresi
            if (extension_loaded('gd') && function_exists('gd_info')) {
                // Compress image dengan GD library
                $compress_result = compressImage($filepath, $filepath, 80);
                if (!$compress_result) {
                    // Jika kompresi gagal, tetap lanjutkan
                    error_log("Gagal mengompresi gambar, menggunakan gambar asli");
                }
            } else {
                // Jika GD tidak tersedia, log warning dan lanjutkan tanpa kompresi
                error_log("GD library tidak tersedia, gambar disimpan tanpa kompresi");
            }
            
            // Upload to Google Drive (optional)
            // $google_drive_url = $this->uploadToGoogleDrive($filepath);
            
            // Save to database
            $sql = "UPDATE student SET photo_reference = ? WHERE id = ?";
            $stmt = $db->query($sql, [$relativePath, $student_id]);
            
            if ($stmt) {
                $_SESSION['has_face'] = true;
                $success = true;
                
                // Update session
                $_SESSION['has_face'] = true;
                
                // Redirect after 2 seconds
                header('Refresh:2;url=' . $dashboardUrl);
            } else {
                $error = "Gagal menyimpan data ke database";
                // Delete uploaded file
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
        } else {
            $error = "Gagal menyimpan gambar. Pastikan folder 'uploads/faces/' dapat ditulis.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>register - presenova</title>
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0a0f1e" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/apple-touch-icon-white background.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-16x16-white background.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-16x16-black background.png" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" type="image/png" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($appDialogCssUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($mainStyleCssUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        :root {
            --page-bg: #050b16;
            --card-bg: rgba(10, 20, 40, 0.9);
            --card-border: rgba(96, 165, 250, 0.25);
            --panel-bg: rgba(10, 18, 34, 0.92);
            --panel-border: rgba(56, 189, 248, 0.24);
            --surface-soft: rgba(15, 27, 49, 0.8);
            --stroke-soft: rgba(148, 163, 184, 0.28);
            --text-main: #eaf2ff;
            --text-muted: #9cb0cc;
            --accent: #38bdf8;
            --accent-soft: #22d3ee;
            --accent-strong: #60a5fa;
            --danger: #fb7185;
            --warning: #f59e0b;
            --success: #34d399;
        }

        body.guide-mobile-open {
            overflow: hidden;
        }

        .register-container {
            min-height: 100vh;
            background:
                radial-gradient(1200px 520px at 10% -20%, rgba(56, 189, 248, 0.14), transparent 60%),
                radial-gradient(900px 420px at 92% 115%, rgba(96, 165, 250, 0.1), transparent 65%),
                linear-gradient(140deg, var(--page-bg) 0%, #081327 52%, #0d1d3a 100%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 76px 16px 12px;
        }

        .register-layout {
            width: min(1320px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1fr) 270px;
            grid-auto-flow: dense;
            gap: 12px;
            align-items: start;
            position: relative;
            z-index: 5;
        }

        .register-brand-bg {
            position: fixed;
            inset: 0;
            z-index: 4;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            opacity: 1;
            user-select: none;
            transform: translateY(10px);
        }

        .brand-kicker {
            letter-spacing: 0.55em;
            text-transform: uppercase;
            font-size: clamp(0.46rem, 0.72vw, 0.72rem);
            color: rgba(248, 211, 120, 0.62);
            margin-bottom: 10px;
        }

        .brand-pre {
            font-family: "Times New Roman", Georgia, serif;
            font-size: clamp(3.8rem, 10vw, 11rem);
            line-height: 0.88;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: rgba(252, 246, 226, 0.54);
            text-shadow: 0 0 34px rgba(255, 220, 130, 0.2);
        }

        .brand-senova {
            font-family: "Times New Roman", Georgia, serif;
            font-size: clamp(4.1rem, 11.2vw, 12.5rem);
            line-height: 0.88;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin-top: -8px;
            background: linear-gradient(105deg, rgba(251, 191, 36, 0.64) 0%, rgba(196, 220, 110, 0.62) 45%, rgba(45, 212, 191, 0.68) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 54px rgba(250, 204, 21, 0.26);
        }

        .brand-tagline {
            font-family: "Times New Roman", Georgia, serif;
            margin-top: 8px;
            font-size: clamp(1rem, 2vw, 2.1rem);
            font-style: italic;
            color: rgba(255, 224, 174, 0.58);
        }

        .logout-btn-container {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 110;
        }

        .logout-btn {
            background: rgba(251, 113, 133, 0.15);
            border: 1px solid transparent;
            color: #fda4af;
            padding: 10px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .logout-btn:hover {
            background: rgba(251, 113, 133, 0.28);
            border-color: #fda4af;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(251, 113, 133, 0.24);
        }

        .student-info {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 110;
            background: rgba(15, 23, 42, 0.56);
            padding: 10px 18px;
            border-radius: 10px;
            border: 1px solid transparent;
            box-shadow: 0 8px 20px rgba(4, 8, 20, 0.35);
        }

        .student-info .name {
            color: #edf4ff;
            font-weight: 700;
            font-size: 1.06rem;
        }

        .student-info .nisn {
            color: #9fb1c9;
            font-size: 0.84rem;
            margin-top: 2px;
        }

        .register-card {
            grid-column: 1;
            grid-row: 1;
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid transparent;
            border-radius: 18px;
            padding: 14px 16px;
            box-shadow: 0 22px 60px rgba(2, 7, 19, 0.5);
            width: 100%;
        }

        .register-title {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: clamp(1.35rem, 1.55vw, 1.88rem);
            background: linear-gradient(120deg, var(--accent-soft) 0%, var(--accent-strong) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: 0.02em;
        }

        .register-subtitle {
            color: var(--text-muted);
            text-align: center;
            margin: 0 0 8px;
            font-size: 0.84rem;
        }

        .register-guide {
            grid-column: 2;
            grid-row: 1;
            background: var(--panel-bg);
            border: 1px solid transparent;
            border-radius: 14px;
            padding: 10px 11px;
            color: var(--text-main);
            box-shadow: 0 18px 40px rgba(2, 7, 19, 0.45);
            align-self: start;
        }

        .guide-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 7px;
        }

        .guide-header h4 {
            font-size: 0.9rem;
            margin: 0;
            color: var(--accent-soft);
        }

        .guide-header p {
            margin: 2px 0 0;
            color: var(--text-muted);
            font-size: 0.76rem;
            line-height: 1.35;
        }

        .guide-close {
            display: none;
            width: 30px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: rgba(30, 41, 59, 0.75);
            color: #d8e2f2;
            line-height: 1;
            font-size: 1.05rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .guide-list {
            margin: 0;
            padding-left: 14px;
            color: #b8cce4;
            font-size: 0.74rem;
            line-height: 1.35;
        }

        .guide-list li {
            margin-bottom: 3px;
        }

        .guide-list li:last-child {
            margin-bottom: 0;
        }

        .guide-progress-card {
            margin-top: 8px;
            background: rgba(8, 15, 30, 0.74);
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 7px 8px;
        }

        .guide-progress-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .guide-progress-top span {
            color: #98b1cf;
            font-size: 0.67rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .guide-progress-top strong {
            color: #d6ebfd;
            font-size: 0.93rem;
        }

        .guide-progress-mini {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 5px;
            font-size: 0.72rem;
        }

        .guide-progress-mini span {
            display: block;
            text-align: center;
            padding: 6px 4px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: rgba(20, 33, 56, 0.46);
            color: #9db4d1;
        }

        .guide-progress-mini strong {
            color: #deebf8;
            display: block;
            font-size: 0.82rem;
            margin-top: 1px;
        }

        .pose-instruction {
            margin-top: 8px;
            border-radius: 9px;
            padding: 7px 8px;
            background: rgba(14, 116, 144, 0.16);
            border: 1px solid transparent;
            color: #d9eeff;
            font-size: 0.73rem;
            line-height: 1.35;
            min-height: 40px;
        }

        .guide-fab {
            display: none;
        }

        .guide-backdrop {
            display: none;
        }

        .camera-container {
            position: relative;
            width: min(100%, 68vh);
            max-width: 620px;
            margin: 0 auto 8px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid transparent;
            box-shadow: 0 12px 26px rgba(2, 7, 19, 0.45);
            background: #020912;
        }

        #video {
            width: 100%;
            border-radius: 14px;
            transform: scaleX(-1);
            display: block;
        }

        #canvas {
            display: none;
        }

        .face-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .face-guide {
            width: clamp(148px, 43%, 186px);
            height: clamp(188px, 52%, 236px);
            border: 2px solid rgba(34, 211, 238, 0.92);
            border-radius: 14px;
            box-shadow: 0 0 18px rgba(34, 211, 238, 0.35);
        }

        .pose-flow-card {
            margin: 0;
            padding: 10px;
            border-radius: 12px;
            background: rgba(10, 20, 40, 0.8);
            border: 1px solid transparent;
            text-align: center;
        }

        .pose-flow-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 7px;
        }

        .pose-flow-header h5 {
            margin: 0;
            color: #dce8f8;
            font-size: 0.94rem;
        }

        .match-badge {
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 0.73rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .match-badge.waiting {
            color: #cbd5e1;
            background: rgba(148, 163, 184, 0.22);
            border-color: rgba(148, 163, 184, 0.35);
        }

        .match-badge.loading {
            color: #67e8f9;
            background: rgba(6, 182, 212, 0.2);
            border-color: rgba(6, 182, 212, 0.35);
        }

        .match-badge.success {
            color: #86efac;
            background: rgba(34, 197, 94, 0.2);
            border-color: rgba(34, 197, 94, 0.35);
        }

        .match-badge.warning {
            color: #fcd34d;
            background: rgba(245, 158, 11, 0.2);
            border-color: rgba(245, 158, 11, 0.35);
        }

        .pose-flow-desc {
            color: #9ac5e9;
            font-size: 0.79rem;
            margin-bottom: 8px;
            text-align: center;
        }

        .pose-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pose-actions .btn {
            min-width: 170px;
        }

        .register-status {
            margin: 8px 0 9px;
            padding: 8px 10px;
            border-radius: 10px;
            background: rgba(37, 99, 235, 0.15);
            border: 1px solid transparent;
            color: #cadcf2;
            font-size: 0.83rem;
        }

        .capture-btn {
            background: linear-gradient(120deg, var(--accent) 0%, var(--accent-soft) 100%);
            border: none;
            color: #031525;
            font-weight: 800;
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 230px;
        }

        .capture-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(56, 189, 248, 0.34);
        }

        .capture-btn:disabled {
            opacity: 0.52;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        .preview-container {
            display: none;
            text-align: center;
            margin-top: 12px;
            padding: 12px;
            border: 1px solid transparent;
            border-radius: 12px;
            background: rgba(8, 14, 28, 0.58);
        }

        .preview-title {
            color: #c8dbef;
            margin-bottom: 12px;
            font-size: 0.92rem;
        }

        #preview {
            width: min(100%, 260px);
            border-radius: 10px;
            margin-bottom: 12px;
            border: 1px solid transparent;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-retake,
        .btn-submit {
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-retake {
            background: rgba(30, 41, 59, 0.55);
            color: #dfeafa;
            border: 1px solid transparent;
        }

        .btn-submit {
            background: linear-gradient(120deg, var(--accent) 0%, var(--accent-soft) 100%);
            color: #031525;
        }

        .btn-retake:hover,
        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .filename-info {
            background: rgba(2, 132, 199, 0.16);
            border: 1px solid transparent;
            color: #9ddcff;
            padding: 9px 10px;
            border-radius: 10px;
            margin: 10px 0 12px;
            text-align: center;
            font-family: monospace;
            font-size: 0.84rem;
        }

        .error-message {
            background: rgba(225, 29, 72, 0.14);
            border: 1px solid transparent;
            color: #ffd1d8;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 12px;
            text-align: center;
            font-size: 0.88rem;
        }

        .notice-message {
            background: rgba(245, 158, 11, 0.14);
            border: 1px solid transparent;
            color: #ffe0aa;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 12px;
            text-align: center;
            font-size: 0.86rem;
        }

        .success-message {
            text-align: center;
            padding: 30px 20px;
        }

        .success-icon {
            font-size: 3.4rem;
            color: var(--accent-soft);
            margin-bottom: 14px;
        }

        .pose-only-complete {
            margin-top: 12px;
            border-radius: 12px;
            padding: 14px;
            background: rgba(16, 185, 129, 0.14);
            border: 1px solid transparent;
            text-align: center;
        }

        .pose-only-complete h5 {
            margin: 0 0 7px;
            color: #a7f3d0;
        }

        .pose-only-complete p {
            color: #d8fbe8;
            margin: 0 0 12px;
            font-size: 0.9rem;
        }

        .privacy-note {
            color: #91a6c0;
            margin-top: 8px;
            font-size: 0.78rem;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .register-layout {
                grid-template-columns: 1fr;
                max-width: 760px;
            }

            .register-brand-bg {
                opacity: 0.5;
            }

            .register-card,
            .register-guide {
                grid-column: auto;
                grid-row: auto;
            }

            .register-guide {
                order: 2;
            }
        }

        @media (max-width: 900px) {
            .register-container {
                padding-top: 90px;
            }

            .logout-btn-container {
                top: 14px;
                right: 14px;
            }

            .student-info {
                top: 14px;
                left: 14px;
                padding: 8px 12px;
            }
        }

        @media (max-width: 768px) {
            .register-layout {
                max-width: 620px;
            }

            .register-brand-bg {
                display: none;
            }

            .register-guide {
                position: fixed;
                left: 12px;
                right: 12px;
                bottom: 86px;
                max-height: min(72vh, 540px);
                overflow-y: auto;
                z-index: 230;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translateY(16px) scale(0.985);
                transition: opacity 0.22s ease, transform 0.22s ease;
            }

            .register-guide.is-open {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: translateY(0) scale(1);
            }

            .guide-close {
                display: inline-flex;
            }

            .guide-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(4, 9, 20, 0.6);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                z-index: 220;
                transition: opacity 0.22s ease;
            }

            .guide-backdrop.is-open {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }

            .guide-fab {
                display: inline-flex;
                position: fixed;
                right: 14px;
                bottom: 20px;
                z-index: 240;
                width: 48px;
                height: 48px;
                border-radius: 999px;
                border: 1px solid rgba(56, 189, 248, 0.52);
                background: linear-gradient(140deg, rgba(11, 26, 50, 0.96), rgba(10, 20, 40, 0.96));
                color: #bfe9ff;
                font-weight: 800;
                font-size: 1.5rem;
                align-items: center;
                justify-content: center;
                line-height: 1;
                box-shadow: 0 10px 22px rgba(2, 7, 19, 0.58);
                cursor: pointer;
            }

            .guide-fab.is-pulsing {
                animation: guidePulse 1.55s ease-in-out infinite;
            }

            .guide-fab:focus-visible {
                outline: 2px solid #7dd3fc;
                outline-offset: 3px;
            }

            .guide-progress-mini {
                gap: 6px;
            }
        }

        @keyframes guidePulse {
            0% {
                box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.52), 0 8px 18px rgba(2, 7, 19, 0.58);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(56, 189, 248, 0), 0 10px 22px rgba(2, 7, 19, 0.65);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(56, 189, 248, 0), 0 8px 18px rgba(2, 7, 19, 0.58);
            }
        }

        @media (max-width: 576px) {
            .register-container {
                padding: 84px 10px 16px;
            }

            .register-card {
                padding: 15px;
                border-radius: 14px;
            }

            .register-title {
                font-size: 1.26rem;
            }

            .camera-container {
                max-width: 100%;
            }

            .pose-flow-header {
                flex-wrap: wrap;
            }

            .pose-actions .btn,
            .capture-btn {
                width: 100%;
                min-width: 0;
            }

            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Logout Button -->
    <div class="logout-btn-container">
        <a href="<?php echo htmlspecialchars($registerLogoutUrl, ENT_QUOTES, 'UTF-8'); ?>" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
    
    <!-- Student Info -->
    <?php if (isset($_SESSION['student_name'])): ?>
    <div class="student-info">
        <div class="name"><?php echo $_SESSION['student_name']; ?></div>
        <div class="nisn">NISN: <?php echo $_SESSION['student_nisn'] ?? ' - '; ?></div>
    </div>
    <?php endif; ?>
    
    <div class="register-container">
        <div class="grid-background"></div>
        <div class="glow-orb orb-1" style="top: 10%; left: 10%;"></div>
        <div class="glow-orb orb-2" style="top: 70%; right: 10%;"></div>
        <div class="register-brand-bg" aria-hidden="true">
            <div class="brand-kicker">DIGITAL EBOOK - SISTEM ABSENSI MODERN</div>
            <div class="brand-pre">PRE</div>
            <div class="brand-senova">SENOVA</div>
            <div class="brand-tagline">Bringing Back Learning Time</div>
        </div>

        <div class="register-layout">
            <?php if (!$success): ?>
                <aside class="register-guide" id="registerGuidePanel" aria-label="Petunjuk registrasi wajah">
                    <div class="guide-header">
                        <div>
                            <h4><i class="fas fa-circle-info"></i> Petunjuk Ringkas</h4>
                            <p>Satu kali konfirmasi, lalu sistem auto-capture pose hingga selesai.</p>
                        </div>
                        <button type="button" class="guide-close" id="guideCloseBtn" aria-label="Tutup petunjuk">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <ol class="guide-list">
                        <li>Pastikan wajah berada di area kotak hijau dan cahaya cukup.</li>
                        <li>Klik tombol <strong>Mulai Otomatis</strong>, lalu menoleh ke kanan, kiri, lalu depan.</li>
                        <li>Sistem menyimpan pose otomatis tanpa perlu klik ulang.</li>
                        <?php if (!$pose_only_mode): ?>
                            <li>Setelah pose lengkap, lanjut ambil satu foto depan.</li>
                        <?php else: ?>
                            <li>Mode ini hanya menyimpan dataset pose, foto referensi depan tidak diubah.</li>
                        <?php endif; ?>
                    </ol>
                    <div class="guide-progress-card">
                        <div class="guide-progress-top">
                            <span>Total progress pose</span>
                            <strong id="poseTotalProgress">0/11</strong>
                        </div>
                        <div class="guide-progress-mini">
                            <span>Kanan <strong id="poseRightProgress">0/5</strong></span>
                            <span>Kiri <strong id="poseLeftProgress">0/5</strong></span>
                            <span>Depan <strong id="poseFrontProgress">0/1</strong></span>
                        </div>
                    </div>
                    <div class="pose-instruction" id="poseInstructionText">
                        Aktifkan kamera lalu klik <strong>Mulai Otomatis</strong>.
                    </div>
                </aside>
                <button type="button" class="guide-fab" id="guideToggleBtn" aria-label="Buka petunjuk registrasi">!</button>
                <div class="guide-backdrop" id="guideBackdrop"></div>
            <?php endif; ?>

            <div class="register-card"
                 data-pose-only="<?php echo $pose_only_mode ? '1' : '0'; ?>"
                 data-has-face="<?php echo $has_face ? '1' : '0'; ?>"
                 data-has-pose="<?php echo $has_pose_capture ? '1' : '0'; ?>"
                 data-student-nisn="<?php echo htmlspecialchars((string) ($student_row['student_nisn'] ?? ''), ENT_QUOTES); ?>">
                <?php if ($success): ?>
                    <div class="success-message">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 style="color: #22d3ee;">Registrasi Berhasil!</h3>
                        <p style="color: #9cb0cc;">Wajah Anda telah terdaftar. Mengarahkan ke dashboard...</p>
                        <div class="spinner-border text-success mt-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                <?php else: ?>
                    <h1 class="register-title"><?php echo $pose_only_mode ? 'VALIDASI POSE KEPALA' : 'REGISTRASI WAJAH'; ?></h1>
                    <p class="register-subtitle">
                        <?php echo $pose_only_mode
                            ? 'Lengkapi data pose kepala untuk menyelesaikan aktivasi akun.'
                            : 'Selesaikan pose otomatis lalu ambil foto depan sebagai referensi utama.'; ?>
                    </p>

                    <?php if ($face_notice): ?>
                        <div class="notice-message">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($face_notice); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($pose_notice): ?>
                        <div class="notice-message">
                            <i class="fas fa-arrows-left-right"></i> <?php echo htmlspecialchars($pose_notice); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($student_data)): ?>
                        <div class="filename-info">
                            <i class="fas fa-file-image"></i> File akan disimpan dengan nama:<br>
                            <strong><?php echo $student_data['student_nisn'] . '-' . strtoupper(preg_replace('/\s+/', '', $student_data['student_name'])) . '.jpg'; ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="camera-container">
                        <video id="video" autoplay playsinline></video>
                        <canvas id="canvas"></canvas>
                        <div class="face-overlay">
                            <div class="face-guide"></div>
                        </div>
                    </div>

                    <div class="pose-flow-card" id="poseFlowCard">
                        <div class="pose-flow-header">
                            <h5><i class="fas fa-arrows-left-right"></i> Capture Pose Kepala</h5>
                            <span id="poseFlowBadge" class="match-badge waiting">Belum Mulai</span>
                        </div>
                        <p class="pose-flow-desc">Klik tombol mulai sekali, lalu sistem capture otomatis kanan, kiri, dan depan.</p>
                        <div class="pose-actions">
                            <button class="btn btn-outline-info btn-sm" id="poseStartBtn" type="button" disabled>
                                <i class="fas fa-check-circle"></i> Mulai Otomatis
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="poseResetBtn" type="button" disabled>
                                <i class="fas fa-rotate-left"></i> Reset Pose
                            </button>
                        </div>
                    </div>
                    <div class="register-status" id="registerStatus">Kamera siap. Klik Mulai Otomatis untuk memulai validasi pose.</div>

                    <?php if (!$pose_only_mode): ?>
                        <form id="faceForm" method="POST">
                            <input type="hidden" name="face_data" id="faceData">

                            <div class="text-center">
                                <button type="button" id="captureBtn" class="capture-btn" disabled>
                                    <i class="fas fa-camera"></i> Ambil Foto Depan
                                </button>
                            </div>

                            <div class="preview-container" id="previewContainer">
                                <h4 class="preview-title">Preview Foto Depan</h4>
                                <img id="preview" src="" alt="Preview">
                                <div class="btn-group">
                                    <button type="button" id="retakeBtn" class="btn-retake">
                                        <i class="fas fa-redo"></i> Ambil Ulang
                                    </button>
                                    <button type="submit" id="submitBtn" class="btn-submit">
                                        <i class="fas fa-check"></i> Simpan Wajah
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="pose-only-complete" id="poseOnlyComplete" style="display: none;">
                            <h5><i class="fas fa-circle-check"></i> Pose Berhasil Disimpan</h5>
                            <p>Dataset pose sudah lengkap. Klik lanjut untuk masuk ke dashboard.</p>
                            <a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-arrow-right"></i> Lanjut ke Dashboard
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="privacy-note">
                        <small>
                            <i class="fas fa-lock"></i> Foto wajah digunakan hanya untuk absensi dan tetap terlindungi.
                        </small>
                    </div>

                    <div class="text-center mt-3">
                        <a href="<?php echo htmlspecialchars($registerLogoutUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-sign-out-alt"></i> Keluar dan Kembali ke Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo htmlspecialchars($appDialogScriptUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars($faceApiScriptUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registerCard = document.querySelector('.register-card');
            if (!registerCard) return;

            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const captureBtn = document.getElementById('captureBtn');
            const retakeBtn = document.getElementById('retakeBtn');
            const submitBtn = document.getElementById('submitBtn');
            const preview = document.getElementById('preview');
            const previewContainer = document.getElementById('previewContainer');
            const faceDataInput = document.getElementById('faceData');
            const faceForm = document.getElementById('faceForm');
            const registerStatus = document.getElementById('registerStatus');

            if (!video || !canvas || !registerStatus) {
                return;
            }

            const poseFlowBadge = document.getElementById('poseFlowBadge');
            const poseInstructionText = document.getElementById('poseInstructionText');
            const poseStartBtn = document.getElementById('poseStartBtn');
            const poseResetBtn = document.getElementById('poseResetBtn');
            const poseTotalProgress = document.getElementById('poseTotalProgress');
            const poseRightProgress = document.getElementById('poseRightProgress');
            const poseLeftProgress = document.getElementById('poseLeftProgress');
            const poseFrontProgress = document.getElementById('poseFrontProgress');
            const poseOnlyComplete = document.getElementById('poseOnlyComplete');
            const registerGuidePanel = document.getElementById('registerGuidePanel');
            const guideToggleBtn = document.getElementById('guideToggleBtn');
            const guideCloseBtn = document.getElementById('guideCloseBtn');
            const guideBackdrop = document.getElementById('guideBackdrop');

            const poseOnlyMode = registerCard.dataset.poseOnly === '1';
            const hasPoseFromServer = registerCard.dataset.hasPose === '1';
            const modelBase = '<?php echo htmlspecialchars($faceModelBaseUrl, ENT_QUOTES, 'UTF-8'); ?>';
            const poseRequiredPerSide = 5;
            const poseRequiredFront = 1;
            const poseTotalRequired = (poseRequiredPerSide * 2) + poseRequiredFront;
            const poseYawSideThreshold = 0.12;
            const poseYawFrontThreshold = 0.08;
            const poseCaptureCooldownMs = 450;
            const guideSeenStorageKey = 'presenova_register_guide_seen';

            let stream = null;
            let capturedImage = null;
            let modelsReady = false;
            let poseStarted = false;
            let poseCompleted = false;
            let poseStep = 'right';
            let poseRightSign = null;
            let poseRightFrames = [];
            let poseLeftFrames = [];
            let poseFrontFrames = [];
            let poseMonitorId = null;
            let poseMonitorBusy = false;
            let poseLastCaptureAt = 0;
            let poseSaving = false;
            let cameraReady = false;
            let poseInstructionCache = '';

            function getSecureContextMessage(featureName) {
                const feature = featureName || 'fitur ini';
                return `Akses ${feature} bisa dibatasi browser pada HTTP non-localhost. Jika gagal, gunakan https://.`;
            }

            function buildCameraErrorMessage(error) {
                const name = (error && error.name) ? String(error.name) : '';
                if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
                    return 'Izin kamera ditolak. Aktifkan izin kamera di browser lalu coba lagi.';
                }
                if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                    return 'Perangkat kamera tidak ditemukan.';
                }
                if (name === 'NotReadableError' || name === 'TrackStartError') {
                    return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi lain lalu coba lagi.';
                }
                if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
                    return 'Konfigurasi kamera tidak didukung perangkat ini.';
                }

                const rawMessage = error && error.message ? String(error.message) : '';
                if (/secure context|only secure origins|insecure/i.test(rawMessage)) {
                    return getSecureContextMessage('kamera');
                }
                if (rawMessage !== '') {
                    return `Tidak dapat mengakses kamera: ${rawMessage}`;
                }

                return 'Tidak dapat mengakses kamera. Pastikan izin kamera sudah diberikan.';
            }

            function setRegisterStatus(message) {
                if (!registerStatus) return;
                registerStatus.textContent = message;
            }

            function setPoseBadge(state, text) {
                if (!poseFlowBadge) return;
                poseFlowBadge.className = 'match-badge ' + state;
                poseFlowBadge.textContent = text;
            }

            function setPoseInstruction(html) {
                if (!poseInstructionText) return;
                const nextValue = String(html || '');
                if (poseInstructionCache === nextValue) {
                    return;
                }
                poseInstructionCache = nextValue;
                poseInstructionText.innerHTML = nextValue;
            }

            function updatePoseProgress() {
                if (poseRightProgress) {
                    poseRightProgress.textContent = `${poseRightFrames.length}/${poseRequiredPerSide}`;
                }
                if (poseLeftProgress) {
                    poseLeftProgress.textContent = `${poseLeftFrames.length}/${poseRequiredPerSide}`;
                }
                if (poseFrontProgress) {
                    poseFrontProgress.textContent = `${poseFrontFrames.length}/${poseRequiredFront}`;
                }
                if (poseTotalProgress) {
                    const currentTotal = poseRightFrames.length + poseLeftFrames.length + poseFrontFrames.length;
                    poseTotalProgress.textContent = `${currentTotal}/${poseTotalRequired}`;
                }
            }

            function stopPoseMonitor() {
                if (poseMonitorId) {
                    clearInterval(poseMonitorId);
                    poseMonitorId = null;
                }
                poseMonitorBusy = false;
            }

            function isSmallScreen() {
                return window.matchMedia('(max-width: 768px)').matches;
            }

            function closeGuidePanel() {
                if (!registerGuidePanel) return;
                registerGuidePanel.classList.remove('is-open');
                if (guideBackdrop) {
                    guideBackdrop.classList.remove('is-open');
                }
                document.body.classList.remove('guide-mobile-open');
            }

            function openGuidePanel() {
                if (!registerGuidePanel || !isSmallScreen()) return;
                registerGuidePanel.classList.add('is-open');
                if (guideBackdrop) {
                    guideBackdrop.classList.add('is-open');
                }
                document.body.classList.add('guide-mobile-open');
            }

            function markGuideAsSeen() {
                if (guideToggleBtn) {
                    guideToggleBtn.classList.remove('is-pulsing');
                }
                try {
                    window.localStorage.setItem(guideSeenStorageKey, '1');
                } catch (error) {
                    // Ignore storage errors.
                }
            }

            function initGuidePanel() {
                if (!registerGuidePanel || !guideToggleBtn) return;

                let guideSeen = false;
                try {
                    guideSeen = window.localStorage.getItem(guideSeenStorageKey) === '1';
                } catch (error) {
                    guideSeen = false;
                }

                if (!guideSeen) {
                    guideToggleBtn.classList.add('is-pulsing');
                }

                guideToggleBtn.addEventListener('click', function() {
                    openGuidePanel();
                    markGuideAsSeen();
                });

                if (guideCloseBtn) {
                    guideCloseBtn.addEventListener('click', function() {
                        closeGuidePanel();
                    });
                }

                if (guideBackdrop) {
                    guideBackdrop.addEventListener('click', function() {
                        closeGuidePanel();
                    });
                }

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        closeGuidePanel();
                    }
                });

                window.addEventListener('resize', function() {
                    if (!isSmallScreen()) {
                        closeGuidePanel();
                    }
                });
            }

            function updateActionButtons() {
                const hasStream = !!stream && !!video.srcObject && cameraReady;
                if (poseStartBtn) {
                    poseStartBtn.disabled = !hasStream || !modelsReady || poseStarted || poseCompleted || poseSaving;
                }
                if (poseResetBtn) {
                    poseResetBtn.disabled = !hasStream || poseSaving;
                }
                if (captureBtn) {
                    captureBtn.disabled = !hasStream || !poseCompleted;
                }
            }

            function resetPoseState() {
                stopPoseMonitor();
                poseStarted = false;
                poseCompleted = false;
                poseStep = 'right';
                poseRightSign = null;
                poseRightFrames = [];
                poseLeftFrames = [];
                poseFrontFrames = [];
                poseLastCaptureAt = 0;
                poseSaving = false;
                setPoseBadge('waiting', 'Belum Mulai');
                setPoseInstruction('Aktifkan kamera lalu klik <strong>Mulai Otomatis</strong>.');
                updatePoseProgress();
                updateActionButtons();
            }

            function setPoseAlreadyCompleted() {
                stopPoseMonitor();
                poseStarted = false;
                poseCompleted = true;
                poseRightFrames = new Array(poseRequiredPerSide).fill({});
                poseLeftFrames = new Array(poseRequiredPerSide).fill({});
                poseFrontFrames = new Array(poseRequiredFront).fill({});
                setPoseBadge('success', 'Selesai');
                setPoseInstruction('Pose sudah lengkap dan tersimpan di server.');
                setRegisterStatus('Dataset pose sudah lengkap.');
                updatePoseProgress();
                if (poseOnlyMode && poseOnlyComplete) {
                    poseOnlyComplete.style.display = 'block';
                }
                updateActionButtons();
            }

            function averagePoint(points) {
                if (!points || !points.length) return null;
                let x = 0;
                let y = 0;
                for (const point of points) {
                    x += point.x;
                    y += point.y;
                }
                return {
                    x: x / points.length,
                    y: y / points.length
                };
            }

            function estimateHeadYaw(landmarks) {
                if (!landmarks) return null;
                const nose = landmarks.getNose();
                const leftEye = landmarks.getLeftEye();
                const rightEye = landmarks.getRightEye();
                if (!nose || !nose.length || !leftEye || !leftEye.length || !rightEye || !rightEye.length) {
                    return null;
                }
                const noseTip = nose[3] || nose[Math.floor(nose.length / 2)];
                const leftEyeCenter = averagePoint(leftEye);
                const rightEyeCenter = averagePoint(rightEye);
                if (!noseTip || !leftEyeCenter || !rightEyeCenter) {
                    return null;
                }
                const eyeSpan = Math.abs(rightEyeCenter.x - leftEyeCenter.x);
                if (eyeSpan < 1) {
                    return null;
                }
                const eyeCenterX = (leftEyeCenter.x + rightEyeCenter.x) / 2;
                const normalized = (noseTip.x - eyeCenterX) / (eyeSpan / 2);
                return Math.max(-1.2, Math.min(1.2, normalized));
            }

            function classifyYawDirection(yaw) {
                if (!Number.isFinite(yaw)) return 'unknown';
                if (Math.abs(yaw) < poseYawFrontThreshold) return 'front';
                return yaw >= 0 ? 'right' : 'left';
            }

            function buildPoseFrameCanvas(maxSide = 640) {
                if (!video.videoWidth || !video.videoHeight) return null;
                const sourceWidth = video.videoWidth;
                const sourceHeight = video.videoHeight;
                const longest = Math.max(sourceWidth, sourceHeight);
                const scale = longest > maxSide ? (maxSide / longest) : 1;
                const width = Math.max(1, Math.round(sourceWidth * scale));
                const height = Math.max(1, Math.round(sourceHeight * scale));
                const poseCanvas = document.createElement('canvas');
                poseCanvas.width = width;
                poseCanvas.height = height;
                const poseCtx = poseCanvas.getContext('2d');
                poseCtx.save();
                poseCtx.scale(-1, 1);
                poseCtx.drawImage(video, -width, 0, width, height);
                poseCtx.restore();
                return poseCanvas;
            }

            async function loadFaceModels() {
                if (modelsReady) {
                    return true;
                }
                if (typeof faceapi === 'undefined') {
                    setRegisterStatus('Library face-api tidak termuat. Refresh halaman.');
                    return false;
                }
                setRegisterStatus('Memuat model deteksi wajah...');
                try {
                    await faceapi.nets.tinyFaceDetector.loadFromUri(modelBase);
                    await faceapi.nets.faceLandmark68Net.loadFromUri(modelBase);
                    modelsReady = true;
                    setRegisterStatus('Model siap. Klik Mulai Otomatis untuk memulai capture pose.');
                    return true;
                } catch (error) {
                    console.error('Load model error:', error);
                    setRegisterStatus('Gagal memuat model wajah. Cek folder model dan refresh halaman.');
                    return false;
                } finally {
                    updateActionButtons();
                }
            }
            
            // Initialize camera
            async function initCamera() {
                if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                    cameraReady = false;
                    setRegisterStatus('Browser ini tidak mendukung akses kamera (getUserMedia).');
                    updateActionButtons();
                    return;
                }

                try {
                    stream = await navigator.mediaDevices.getUserMedia({ 
                        video: { 
                            facingMode: 'user',
                            width: { ideal: 640 },
                            height: { ideal: 480 }
                        },
                        audio: false 
                    });
                    video.srcObject = stream;
                    await new Promise((resolve) => {
                        if (video.readyState >= 1) {
                            resolve();
                            return;
                        }
                        video.onloadedmetadata = () => resolve();
                    });
                    cameraReady = true;
                    if (!modelsReady) {
                        setRegisterStatus('Kamera aktif, tetapi model wajah belum siap. Refresh halaman lalu coba lagi.');
                    } else if (!hasPoseFromServer) {
                        setRegisterStatus('Kamera aktif. Klik Mulai Otomatis lalu menoleh kanan, kiri, dan depan.');
                    } else {
                        setRegisterStatus('Kamera aktif.');
                    }
                    updateActionButtons();
                } catch (err) {
                    console.error('Error accessing camera:', err);
                    cameraReady = false;
                    setRegisterStatus(buildCameraErrorMessage(err));
                    updateActionButtons();
                }
            }

            function stopCamera() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
                cameraReady = false;
                updateActionButtons();
            }

            async function detectPoseSample(includeImage = false) {
                const poseCanvas = buildPoseFrameCanvas(640);
                if (!poseCanvas) return null;
                const detection = await faceapi
                    .detectSingleFace(
                        poseCanvas,
                        new faceapi.TinyFaceDetectorOptions({
                            inputSize: 320,
                            scoreThreshold: 0.5
                        })
                    )
                    .withFaceLandmarks();
                if (!detection) {
                    return null;
                }

                const yaw = estimateHeadYaw(detection.landmarks);
                if (!Number.isFinite(yaw)) {
                    return null;
                }

                const sample = {
                    yaw,
                    direction: classifyYawDirection(yaw),
                    timestamp: Date.now()
                };

                if (includeImage) {
                    sample.imageData = poseCanvas.toDataURL('image/jpeg', 0.84);
                }

                return sample;
            }

            function updatePoseLiveInstruction(sample) {
                if (!poseStarted || poseCompleted) return;
                let targetLabel = 'kanan';
                if (poseStep === 'left') targetLabel = 'kiri';
                if (poseStep === 'front') targetLabel = 'depan';
                if (!sample) {
                    setPoseInstruction(`Target <strong>${targetLabel}</strong> • wajah belum terbaca, posisikan ke tengah.`);
                    return;
                }
                const directionText = sample.direction === 'front'
                    ? 'depan'
                    : (sample.direction === 'right' ? 'kanan' : 'kiri');
                setPoseInstruction(`Target <strong>${targetLabel}</strong> • terbaca <strong>${directionText}</strong>.`);
            }

            async function savePoseFramesToServer() {
                if (poseSaving) {
                    return false;
                }
                poseSaving = true;
                updateActionButtons();
                setRegisterStatus('Menyimpan frame pose ke server...');
                try {
                    const payload = {
                        right: poseRightFrames.map(item => item.imageData).filter(Boolean),
                        left: poseLeftFrames.map(item => item.imageData).filter(Boolean),
                        front: poseFrontFrames.map(item => item.imageData).filter(Boolean)
                    };
                    const response = await fetch('<?php echo htmlspecialchars($savePoseFramesUrl, ENT_QUOTES, 'UTF-8'); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || !data || !data.success) {
                        throw new Error(data?.message || 'Gagal menyimpan frame pose.');
                    }
                    return true;
                } catch (error) {
                    console.error('Save pose error:', error);
                    setRegisterStatus(error?.message || 'Gagal menyimpan frame pose di server.');
                    return false;
                } finally {
                    poseSaving = false;
                    updateActionButtons();
                }
            }

            async function finalizePoseValidation() {
                const saved = await savePoseFramesToServer();
                poseStarted = false;
                stopPoseMonitor();
                if (!saved) {
                    setPoseBadge('warning', 'Gagal Simpan');
                    setPoseInstruction('Penyimpanan pose gagal. Klik <strong>Reset Pose</strong> lalu ulangi.');
                    return;
                }
                poseCompleted = true;
                setPoseBadge('success', 'Selesai');
                setPoseInstruction('Pose selesai. Lanjut ambil foto depan.');
                setRegisterStatus('Pose berhasil disimpan.');
                if (poseOnlyMode) {
                    if (poseOnlyComplete) {
                        poseOnlyComplete.style.display = 'block';
                    }
                    setRegisterStatus('Pose berhasil disimpan. Anda bisa lanjut ke dashboard.');
                }
                updateActionButtons();
            }

            async function processPoseSample(sample) {
                if (!poseStarted || poseCompleted || !sample) return;
                const now = Date.now();
                if ((now - poseLastCaptureAt) < poseCaptureCooldownMs) {
                    return;
                }
                const absYaw = Math.abs(sample.yaw);
                const sign = sample.yaw >= 0 ? 1 : -1;

                if (poseStep === 'right') {
                    if (absYaw < poseYawSideThreshold) {
                        return;
                    }
                    if (poseRightSign === null) {
                        poseRightSign = sign;
                    }
                    if (sign !== poseRightSign) {
                        return;
                    }
                    poseRightFrames.push(sample);
                    poseLastCaptureAt = now;
                    updatePoseProgress();
                    setRegisterStatus(`Frame kanan tersimpan (${poseRightFrames.length}/${poseRequiredPerSide}).`);
                    if (poseRightFrames.length >= poseRequiredPerSide) {
                        poseStep = 'left';
                        setPoseInstruction('Lanjut step 2/3: menoleh ke <strong>kiri</strong>.');
                    }
                    return;
                }

                if (poseStep === 'left') {
                    if (absYaw < poseYawSideThreshold) {
                        return;
                    }
                    if (poseRightSign === null || sign !== (poseRightSign * -1)) {
                        return;
                    }
                    poseLeftFrames.push(sample);
                    poseLastCaptureAt = now;
                    updatePoseProgress();
                    setRegisterStatus(`Frame kiri tersimpan (${poseLeftFrames.length}/${poseRequiredPerSide}).`);
                    if (poseLeftFrames.length >= poseRequiredPerSide) {
                        poseStep = 'front';
                        setPoseInstruction('Lanjut step 3/3: hadapkan wajah ke <strong>depan</strong>.');
                    }
                    return;
                }

                if (poseStep === 'front') {
                    if (absYaw > poseYawFrontThreshold) {
                        return;
                    }
                    poseFrontFrames.push(sample);
                    poseLastCaptureAt = now;
                    updatePoseProgress();
                    await finalizePoseValidation();
                }
            }

            function startPoseMonitor() {
                stopPoseMonitor();
                poseMonitorId = setInterval(async () => {
                    if (!poseStarted || poseCompleted || poseMonitorBusy || !stream || !video.videoWidth) {
                        return;
                    }
                    poseMonitorBusy = true;
                    try {
                        const sample = await detectPoseSample(true);
                        updatePoseLiveInstruction(sample);
                        await processPoseSample(sample);
                    } catch (error) {
                        console.warn('Pose monitor frame error:', error);
                    } finally {
                        poseMonitorBusy = false;
                    }
                }, 550);
            }

            function startPoseValidationFlow() {
                if (!stream || !video.srcObject) {
                    setRegisterStatus('Aktifkan kamera terlebih dahulu.');
                    return;
                }
                if (!modelsReady) {
                    setRegisterStatus('Model belum siap. Tunggu proses loading selesai.');
                    return;
                }
                if (poseCompleted) {
                    setRegisterStatus('Pose sudah lengkap.');
                    return;
                }

                if (poseRightFrames.length || poseLeftFrames.length || poseFrontFrames.length) {
                    resetPoseState();
                }

                poseStarted = true;
                poseStep = 'right';
                setPoseBadge('loading', 'Berjalan');
                setPoseInstruction('Step 1/3: menoleh ke <strong>kanan</strong>.');
                setRegisterStatus('Capture pose otomatis sedang berjalan.');
                updateActionButtons();
                startPoseMonitor();
            }
            
            // Capture photo
            if (captureBtn) {
                captureBtn.addEventListener('click', function() {
                    if (!poseCompleted) {
                        alert('Selesaikan pose capture terlebih dahulu.');
                        return;
                    }
                    if (!stream || !cameraReady) return;
                    
                    // Set canvas dimensions to match video
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    
                    // Draw current video frame to canvas
                    const context = canvas.getContext('2d');
                    context.save();
                    context.scale(-1, 1);
                    context.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
                    context.restore();
                    
                    // Get image data
                    capturedImage = canvas.toDataURL('image/jpeg', 0.84);
                    
                    // Show preview
                    preview.src = capturedImage;
                    previewContainer.style.display = 'block';
                    captureBtn.style.display = 'none';
                    setRegisterStatus('Preview foto depan siap. Simpan untuk menyelesaikan registrasi.');
                    
                    // Stop camera
                    stopCamera();
                });
            }
            
            // Retake photo
            if (retakeBtn) {
                retakeBtn.addEventListener('click', function() {
                    previewContainer.style.display = 'none';
                    if (captureBtn) {
                        captureBtn.style.display = 'flex';
                    }
                    capturedImage = null;
                    initCamera();
                });
            }
            
            // Submit form
            if (faceForm) {
                faceForm.addEventListener('submit', function(e) {
                    if (!poseCompleted) {
                        e.preventDefault();
                        alert('Pose capture belum selesai.');
                        return;
                    }
                    if (!capturedImage) {
                        e.preventDefault();
                        alert('Silakan ambil foto depan terlebih dahulu.');
                        return;
                    }
                    faceDataInput.value = capturedImage;
                    
                    // Show loading
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
                        submitBtn.disabled = true;
                    }
                });
            }

            if (poseStartBtn) {
                poseStartBtn.addEventListener('click', async function() {
                    const ready = await AppDialog.confirm('Pastikan Anda siap. Setelah ini sistem auto-capture pose kanan, kiri, lalu depan.', {
                        title: 'Konfirmasi Mulai Pose'
                    });
                    if (!ready) return;
                    startPoseValidationFlow();
                });
            }

            if (poseResetBtn) {
                poseResetBtn.addEventListener('click', function() {
                    resetPoseState();
                    setRegisterStatus('Pose direset. Klik Mulai Otomatis untuk mengulang.');
                });
            }
            
            // Confirm before logout
            document.querySelectorAll('a[href*="logout"]').forEach(link => {
                link.addEventListener('click', async function(e) {
                    const confirmed = await AppDialog.confirm('Anda yakin ingin logout? Proses registrasi wajah akan dibatalkan.', {
                        title: 'Konfirmasi Logout'
                    });
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Stop camera if active
                    stopCamera();
                    stopPoseMonitor();
                    return true;
                });
            });
            
            // Initialize state and camera
            initGuidePanel();
            resetPoseState();
            if (hasPoseFromServer) {
                setPoseAlreadyCompleted();
            }
            loadFaceModels().finally(() => {
                initCamera();
            });
            
            // Handle page unload to stop camera
            window.addEventListener('beforeunload', function() {
                stopCamera();
                stopPoseMonitor();
            });
        });
    </script>
</body>
</html>
