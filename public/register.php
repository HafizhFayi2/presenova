<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

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

$student_id = $_SESSION['student_id'];
$photo_reference = null;
$has_face = false;

$stmt = $db->query("SELECT photo_reference FROM student WHERE id = ?", [$student_id]);
$student_row = $stmt ? $stmt->fetch() : null;

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

$_SESSION['has_face'] = $has_face;

// If student already has face photo, redirect to dashboard
if ($has_face) {
    header("Location: dashboard/siswa.php");
    exit();
}

$success = false;
$error = '';
$face_notice = '';
if (!empty($_SESSION['face_reference_notice'])) {
    $face_notice = $_SESSION['face_reference_notice'];
    unset($_SESSION['face_reference_notice']);
}

// Handle face registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['face_data'])) {
    require_once 'includes/functions.php'; // Untuk fungsi compressImage
    
    $face_data = $_POST['face_data'];
    $student_id = $_SESSION['student_id'];
    
    // Decode base64 image
    $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $face_data));
    
    // Get student data for filename
    $db = new Database();
    $sql = "SELECT student_nisn, student_name FROM student WHERE id = ?";
    $stmt = $db->query($sql, [$student_id]);
    $student_data = $stmt->fetch();
    
    if (!$student_data) {
        $error = "Data siswa tidak ditemukan!";
    } else {
        // Format nama file: NISN-USERNAME.jpg
        // Username diambil dari nama tanpa spasi, huruf besar
        $nisn = $student_data['student_nisn'];
        $username = strtoupper(preg_replace('/\s+/', '', $student_data['student_name']));
        
        // Generate unique filename dengan timestamp untuk menghindari duplikat
        $filename = $nisn . '-' . $username . '_' . time() . '.jpg';
        $filepath = 'uploads/faces/' . $filename;
        
        // Buat direktori jika belum ada
        if (!file_exists('uploads/faces/')) {
            mkdir('uploads/faces/', 0777, true);
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
            $stmt = $db->query($sql, [$filename, $student_id]);
            
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
            padding-top: 80px;
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
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 2;
        }
        
        .register-title {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 2rem;
            background: linear-gradient(135deg, #00ff88 0%, #00d4ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .instructions {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .instructions h4 {
            color: #00ff88;
            margin-bottom: 15px;
        }
        
        .instructions ol {
            color: #8b92a8;
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 10px;
        }
        
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto 30px;
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
            width: 200px;
            height: 250px;
            border: 3px solid #00ff88;
            border-radius: 10px;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.5);
        }
        
        .capture-btn {
            background: linear-gradient(135deg, #00ff88 0%, #00d4ff 100%);
            border: none;
            color: #0a0f1e;
            font-weight: 700;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            margin-top: 20px;
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
    </style>
</head>
<body>
    <!-- Logout Button -->
    <div class="logout-btn-container">
        <a href="register.php?logout" class="logout-btn" onclick="return confirm('Anda yakin ingin logout? Proses registrasi wajah akan dibatalkan.')">
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
        
        <div class="register-card">
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
                <h1 class="register-title">REGISTRASI WAJAH</h1>

                <?php if ($face_notice): ?>
                    <div class="notice-message">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($face_notice); ?>
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
                    <h4><i class="fas fa-info-circle"></i> Petunjuk Registrasi Wajah:</h4>
                    <ol>
                        <li>Pastikan pencahayaan cukup dan wajah terlihat jelas</li>
                        <li>Posisikan wajah di dalam area pandu (kotak hijau)</li>
                        <li>Hapus kacamata dan topi agar wajah terlihat jelas</li>
                        <li>Ekspresi wajah netral (tidak tersenyum atau cemberut)</li>
                        <li>Klik "Ambil Foto" saat sudah siap</li>
                        <li>Foto ini akan digunakan untuk verifikasi absensi harian</li>
                    </ol>
                </div>
                
                <div class="camera-container">
                    <video id="video" autoplay playsinline></video>
                    <canvas id="canvas"></canvas>
                    <div class="face-overlay">
                        <div class="face-guide"></div>
                    </div>
                </div>
                
                <form id="faceForm" method="POST">
                    <input type="hidden" name="face_data" id="faceData">
                    
                    <div class="text-center">
                        <button type="button" id="captureBtn" class="capture-btn">
                            <i class="fas fa-camera"></i> Ambil Foto
                        </button>
                    </div>
                    
                    <div class="preview-container" id="previewContainer">
                        <h4 style="color: #8b92a8; margin-bottom: 15px;">Preview Foto:</h4>
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
                
                <div class="text-center mt-4" style="color: #8b92a8;">
                    <small>
                        <i class="fas fa-lock"></i> Foto wajah Anda hanya digunakan untuk sistem absensi dan dilindungi kerahasiaannya.
                    </small>
                </div>
                
                <!-- Additional Logout Button at Bottom -->
                <div class="text-center mt-4">
                    <a href="register.php?logout" class="btn btn-outline-danger btn-sm" onclick="return confirm('Anda yakin ingin logout?')">
                        <i class="fas fa-sign-out-alt"></i> Keluar dan Kembali ke Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const captureBtn = document.getElementById('captureBtn');
            const retakeBtn = document.getElementById('retakeBtn');
            const submitBtn = document.getElementById('submitBtn');
            const preview = document.getElementById('preview');
            const previewContainer = document.getElementById('previewContainer');
            const faceDataInput = document.getElementById('faceData');
            
            let stream = null;
            let capturedImage = null;
            
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
                } catch (err) {
                    console.error('Error accessing camera:', err);
                    alert('Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan.');
                }
            }
            
            // Capture photo
            captureBtn.addEventListener('click', function() {
                if (!stream) return;
                
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
                capturedImage = canvas.toDataURL('image/jpeg', 0.8);
                
                // Show preview
                preview.src = capturedImage;
                previewContainer.style.display = 'block';
                captureBtn.style.display = 'none';
                
                // Stop camera
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            });
            
            // Retake photo
            retakeBtn.addEventListener('click', function() {
                previewContainer.style.display = 'none';
                captureBtn.style.display = 'flex';
                capturedImage = null;
                initCamera();
            });
            
            // Submit form
            document.getElementById('faceForm').addEventListener('submit', function(e) {
                if (!capturedImage) {
                    e.preventDefault();
                    alert('Silakan ambil foto terlebih dahulu');
                    return;
                }
                faceDataInput.value = capturedImage;
                
                // Show loading
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
                submitBtn.disabled = true;
            });
            
            // Confirm before logout
            document.querySelectorAll('a[href*="logout"]').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Anda yakin ingin logout? Proses registrasi wajah akan dibatalkan.')) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Stop camera if active
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                    return true;
                });
            });
            
            // Initialize camera on load
            initCamera();
            
            // Handle page unload to stop camera
            window.addEventListener('beforeunload', function() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
            });
        });
    </script>
</body>
</html>
