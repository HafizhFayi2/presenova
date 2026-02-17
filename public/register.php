<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'helpers/storage_path_helper.php';

$auth = new Auth();

// Handle logout request
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: login.php?logout_success=1");
    exit();
}

// Check if student is logged in
if (!$auth->isLoggedIn() || !isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/database.php';
$db = new Database();

if (!function_exists('register_has_pose_capture_dataset')) {
    function register_has_pose_capture_dataset($studentNisn, $className, $studentName)
    {
        $studentNisn = trim((string) $studentNisn);
        if ($studentNisn === '') {
            return false;
        }
        $classFolder = storage_class_folder($className ?: 'kelas');
        $studentFolder = storage_student_folder($studentName ?: ('siswa_' . $studentNisn));
        $poseDir = __DIR__ . '/uploads/faces/' . $classFolder . '/' . $studentFolder . '/pose';
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
    $photo_reference = $student_row['photo_reference'];
    $facePath = __DIR__ . '/uploads/faces/' . $photo_reference;
    $has_face = file_exists($facePath);

    if (!$has_face) {
        $db->query(
            "UPDATE student SET photo_reference = NULL, face_embedding = NULL WHERE id = ?",
            [$student_id]
        );
        $_SESSION['face_reference_notice'] = 'Sistem tidak menemukan photo referensi. Mohon photo ulang.';
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
    header("Location: dashboard/siswa.php");
    exit();
}
if ($pose_only && !$has_face) {
    header("Location: register.php?upload_face=1");
    exit();
}
if ($has_face && !$has_pose_capture && !$pose_only) {
    header("Location: register.php?upload_face=1&pose_only=1");
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
    require_once 'includes/functions.php'; // Untuk fungsi compressImage
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
    $db = new Database();
    $sql = "SELECT s.student_nisn, s.student_name, c.class_name
            FROM student s
            LEFT JOIN class c ON s.class_id = c.class_id
            WHERE s.id = ?";
    $stmt = $db->query($sql, [$student_id]);
    $student_data = $stmt->fetch();
    
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
        $filepath = 'uploads/faces/' . $relativePath;
        
        // Buat direktori jika belum ada
        $faceDir = 'uploads/faces/' . $classFolder . '/' . $studentFolder . '/';
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
                header("refresh:2;url=dashboard/siswa.php");
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
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon-white background.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-16x16-white background.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-16x16-black background.png" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app-dialog.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .register-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0f1e 0%, #1a2332 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 72px 14px 18px;
        }
        
        .logout-btn-container {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 100;
        }
        
        .logout-btn {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid rgba(255, 107, 107, 0.5);
            color: #ff6b6b;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: rgba(255, 107, 107, 0.3);
            border-color: #ff6b6b;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }
        
        .register-card {
            background: rgba(15, 22, 41, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-radius: 16px;
            padding: 22px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 2;
        }
        
        .register-title {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.7rem;
            background: linear-gradient(135deg, #00ff88 0%, #00d4ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            margin-bottom: 16px;
        }
        
        .instructions {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 14px;
        }
        
        .instructions h4 {
            color: #00ff88;
            margin-bottom: 10px;
            font-size: 1.05rem;
        }
        
        .instructions ol {
            color: #8b92a8;
            padding-left: 18px;
            margin: 0;
            font-size: 0.92rem;
        }
        
        .instructions li {
            margin-bottom: 6px;
        }
        
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 420px;
            margin: 0 auto 12px;
        }
        
        #video {
            width: 100%;
            border-radius: 10px;
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
            width: 170px;
            height: 220px;
            border: 2px solid #00ff88;
            border-radius: 10px;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.5);
        }
        
        .capture-btn {
            background: linear-gradient(135deg, #00ff88 0%, #00d4ff 100%);
            border: none;
            color: #0a0f1e;
            font-weight: 700;
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto;
        }
        
        .capture-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.5);
        }
        
        .capture-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .preview-container {
            display: none;
            text-align: center;
            margin-top: 12px;
        }
        
        #preview {
            max-width: 300px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-retake, .btn-submit {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-retake {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #00ff88 0%, #00d4ff 100%);
            color: #0a0f1e;
        }
        
        .btn-retake:hover, .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .success-message {
            text-align: center;
            padding: 40px;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #00ff88;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.3);
            color: #ff6b6b;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .notice-message {
            background: rgba(255, 193, 7, 0.12);
            border: 1px solid rgba(255, 193, 7, 0.35);
            color: #ffd166;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .student-info {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 100;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .student-info .name {
            color: #ffffff;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .student-info .nisn {
            color: #8b92a8;
            font-size: 0.8rem;
        }
        
        .filename-info {
            background: rgba(0, 123, 255, 0.1);
            border: 1px solid rgba(0, 123, 255, 0.3);
            color: #4da3ff;
            padding: 10px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            font-family: monospace;
            font-size: 0.9rem;
        }

        .register-status {
            margin: 10px 0 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(79, 70, 229, 0.14);
            border: 1px solid rgba(129, 140, 248, 0.35);
            color: #dbeafe;
            font-size: 0.88rem;
        }

        .pose-flow-card {
            margin: 12px 0;
            padding: 12px;
            border-radius: 12px;
            background: rgba(12, 18, 35, 0.78);
            border: 1px solid rgba(56, 189, 248, 0.25);
            text-align: center;
        }

        .pose-flow-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .pose-flow-header h5 {
            margin: 0;
            color: #dbeafe;
            font-size: 0.95rem;
        }

        .match-badge {
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            border: 1px solid transparent;
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
            color: #93c5fd;
            font-size: 0.82rem;
            margin-bottom: 8px;
            text-align: center;
        }

        .pose-progress-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 8px;
            justify-items: center;
        }

        .pose-progress-item {
            background: rgba(30, 41, 59, 0.62);
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 8px;
            padding: 8px 6px;
            text-align: center;
            width: 100%;
            max-width: 160px;
        }

        .pose-progress-item span {
            display: block;
            color: #94a3b8;
            font-size: 0.78rem;
        }

        .pose-progress-item strong {
            color: #e2e8f0;
            font-size: 0.95rem;
        }

        .pose-instruction {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 0.84rem;
            color: #cbd5e1;
            text-align: center;
        }

        .pose-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pose-only-complete {
            margin-top: 16px;
            border-radius: 12px;
            padding: 16px;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.32);
            text-align: center;
        }

        .pose-only-complete h5 {
            margin: 0 0 8px;
            color: #86efac;
        }

        .pose-only-complete p {
            color: #cbd5e1;
            margin: 0 0 12px;
        }

        @media (max-width: 576px) {
            .register-card {
                padding: 16px;
                border-radius: 12px;
            }

            .register-title {
                font-size: 1.4rem;
            }

            .camera-container {
                max-width: 100%;
            }

            .pose-progress-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-retake,
            .btn-submit,
            .pose-actions .btn,
            .capture-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Logout Button -->
    <div class="logout-btn-container">
        <a href="register.php?logout" class="logout-btn">
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
                    <h3 style="color: #00ff88;">Registrasi Berhasil!</h3>
                    <p style="color: #8b92a8;">Wajah Anda telah terdaftar. Mengarahkan ke dashboard...</p>
                    <div class="spinner-border text-success mt-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            <?php else: ?>
                <h1 class="register-title"><?php echo $pose_only_mode ? 'VALIDASI POSE KEPALA' : 'REGISTRASI WAJAH'; ?></h1>

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
                
                <!-- Info nama file -->
                <?php if (isset($student_data)): ?>
                <div class="filename-info">
                    <i class="fas fa-file-image"></i> File akan disimpan dengan nama:<br>
                    <strong><?php echo $student_data['student_nisn'] . '-' . strtoupper(preg_replace('/\s+/', '', $student_data['student_name'])) . '.jpg'; ?></strong>
                </div>
                <?php endif; ?>
                
                <div class="instructions">
                    <h4><i class="fas fa-info-circle"></i> Petunjuk:</h4>
                    <ol>
                        <li>Aktifkan kamera dan pastikan wajah terlihat jelas.</li>
                        <li>Klik konfirmasi pose sekali di awal, lalu menoleh ke kanan, kiri, dan kembali depan.</li>
                        <li>Sistem akan auto-capture 5 frame kanan, 5 frame kiri, dan 1 frame depan.</li>
                        <?php if (!$pose_only_mode): ?>
                            <li>Setelah pose selesai, ambil foto depan untuk referensi utama akun.</li>
                            <li>Foto depan ini yang tampil sebagai foto referensi di dashboard.</li>
                        <?php else: ?>
                            <li>Mode ini hanya melengkapi dataset pose. Foto referensi depan tidak diubah.</li>
                        <?php endif; ?>
                    </ol>
                </div>
                
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
                    <p class="pose-flow-desc">
                        Klik konfirmasi sekali di awal, lalu sistem auto-capture frame saat Anda menoleh kanan, kiri, dan kembali depan.
                    </p>
                    <div class="pose-progress-grid">
                        <div class="pose-progress-item">
                            <span>Kanan</span>
                            <strong id="poseRightProgress">0/5</strong>
                        </div>
                        <div class="pose-progress-item">
                            <span>Kiri</span>
                            <strong id="poseLeftProgress">0/5</strong>
                        </div>
                        <div class="pose-progress-item">
                            <span>Depan</span>
                            <strong id="poseFrontProgress">0/1</strong>
                        </div>
                    </div>
                    <div class="pose-instruction" id="poseInstructionText">
                        Aktifkan kamera, lalu klik <strong>Konfirmasi Siap & Mulai Otomatis</strong>.
                    </div>
                    <div class="pose-actions">
                        <button class="btn btn-outline-info btn-sm" id="poseStartBtn" type="button" disabled>
                            <i class="fas fa-check-circle"></i> Konfirmasi Siap & Mulai Otomatis
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="poseResetBtn" type="button" disabled>
                            <i class="fas fa-rotate-left"></i> Reset Pose
                        </button>
                    </div>
                </div>
                <div class="register-status" id="registerStatus">Kamera siap. Selesaikan pose capture terlebih dahulu.</div>

                <?php if (!$pose_only_mode): ?>
                    <form id="faceForm" method="POST">
                        <input type="hidden" name="face_data" id="faceData">
                        
                        <div class="text-center">
                            <button type="button" id="captureBtn" class="capture-btn" disabled>
                                <i class="fas fa-camera"></i> Ambil Foto Depan
                            </button>
                        </div>
                        
                        <div class="preview-container" id="previewContainer">
                            <h4 style="color: #8b92a8; margin-bottom: 15px;">Preview Foto Depan:</h4>
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
                        <a href="dashboard/siswa.php" class="btn btn-success btn-sm">
                            <i class="fas fa-arrow-right"></i> Lanjut ke Dashboard
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4" style="color: #8b92a8;">
                    <small>
                        <i class="fas fa-lock"></i> Foto wajah Anda hanya digunakan untuk sistem absensi dan dilindungi kerahasiaannya.
                    </small>
                </div>
                
                <!-- Additional Logout Button at Bottom -->
                <div class="text-center mt-4">
                    <a href="register.php?logout" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Keluar dan Kembali ke Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app-dialog.js"></script>
    <script src="face/faces_logics/face-api.min.js"></script>
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

            const poseFlowBadge = document.getElementById('poseFlowBadge');
            const poseInstructionText = document.getElementById('poseInstructionText');
            const poseStartBtn = document.getElementById('poseStartBtn');
            const poseResetBtn = document.getElementById('poseResetBtn');
            const poseRightProgress = document.getElementById('poseRightProgress');
            const poseLeftProgress = document.getElementById('poseLeftProgress');
            const poseFrontProgress = document.getElementById('poseFrontProgress');
            const poseOnlyComplete = document.getElementById('poseOnlyComplete');

            const poseOnlyMode = registerCard.dataset.poseOnly === '1';
            const hasPoseFromServer = registerCard.dataset.hasPose === '1';
            const modelBase = 'face/faces_logics/models';
            const poseRequiredPerSide = 5;
            const poseRequiredFront = 1;
            const poseYawSideThreshold = 0.12;
            const poseYawFrontThreshold = 0.08;
            const poseCaptureCooldownMs = 450;

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
                poseInstructionText.innerHTML = html;
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
            }

            function stopPoseMonitor() {
                if (poseMonitorId) {
                    clearInterval(poseMonitorId);
                    poseMonitorId = null;
                }
                poseMonitorBusy = false;
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
                setPoseInstruction('Aktifkan kamera, lalu klik <strong>Konfirmasi Siap & Mulai Otomatis</strong>.');
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
                setPoseInstruction('Dataset pose sudah lengkap dan tersimpan di server.');
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
                    setRegisterStatus('Model siap. Klik konfirmasi pose untuk memulai auto-capture.');
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
                        setRegisterStatus('Kamera aktif. Klik konfirmasi pose lalu menoleh kanan, kiri, dan depan.');
                    } else {
                        setRegisterStatus('Kamera aktif.');
                    }
                    updateActionButtons();
                } catch (err) {
                    console.error('Error accessing camera:', err);
                    cameraReady = false;
                    setRegisterStatus('Tidak dapat mengakses kamera. Pastikan izin kamera sudah diberikan.');
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
                    setPoseInstruction(`Target: <strong>${targetLabel}</strong>. Wajah belum terbaca, posisikan ke tengah.`);
                    return;
                }
                const directionText = sample.direction === 'front'
                    ? 'depan'
                    : (sample.direction === 'right' ? 'kanan' : 'kiri');
                setPoseInstruction(`Target: <strong>${targetLabel}</strong>. Terdeteksi: <strong>${directionText}</strong>.`);
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
                    const response = await fetch('api/save_pose_frames.php', {
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
                setPoseInstruction('Pose selesai. Lanjutkan ambil foto depan.');
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
                        setPoseInstruction('Step 2/3: Menoleh ke <strong>kiri</strong>.');
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
                        setPoseInstruction('Step 3/3: Hadapkan wajah ke <strong>depan</strong>.');
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
                setPoseInstruction('Step 1/3: Menoleh ke <strong>kanan</strong>. Sistem menangkap 5 frame otomatis.');
                setRegisterStatus('Capture pose berjalan otomatis.');
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
                    setRegisterStatus('Pose direset. Klik konfirmasi untuk memulai ulang.');
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
