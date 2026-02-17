<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/face_matcher.php';
require_once '../includes/functions.php';
require_once '../helpers/storage_path_helper.php';

$auth = new Auth();

// Check if student is logged in menggunakan method isLoggedIn
if (!$auth->isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] != 'siswa') {
    header("Location: ../login.php");
    exit();
}

// If student hasn't uploaded face photo, redirect to registration
if (!isset($_SESSION['has_face']) || !$_SESSION['has_face']) {
    if (!empty($_SESSION['face_reference_missing'])) {
        $_SESSION['face_reference_notice'] = 'Sistem tidak menemukan photo referensi. Mohon photo ulang.';
        unset($_SESSION['face_reference_missing']);
    }
    header("Location: ../register.php?upload_face=1");
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

require_once '../includes/database.php';
$db = new Database();

if (!isset($_SESSION['last_siswa_dashboard_log']) || (time() - $_SESSION['last_siswa_dashboard_log']) > 300) {
    if (!empty($_SESSION['student_id'])) {
        logActivity((int) $_SESSION['student_id'], 'student', 'dashboard_access', 'Student dashboard accessed');
        $_SESSION['last_siswa_dashboard_log'] = time();
    }
}

if (!function_exists('hasPoseCaptureDataset')) {
    function hasPoseCaptureDataset($studentNisn, $className, $studentName)
    {
        $studentNisn = trim((string) $studentNisn);
        if ($studentNisn === '') {
            return false;
        }
        $baseDir = realpath(__DIR__ . '/../uploads/faces');
        if ($baseDir === false) {
            $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'faces';
        }
        $classFolder = storage_class_folder($className ?: 'kelas');
        $studentFolder = storage_student_folder($studentName ?: ('siswa_' . $studentNisn));
        $poseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $classFolder . DIRECTORY_SEPARATOR . $studentFolder . DIRECTORY_SEPARATOR . 'pose';
        if (!is_dir($poseDir)) {
            return false;
        }
        $right = glob($poseDir . DIRECTORY_SEPARATOR . 'right_*.jpg') ?: [];
        $left = glob($poseDir . DIRECTORY_SEPARATOR . 'left_*.jpg') ?: [];
        $front = glob($poseDir . DIRECTORY_SEPARATOR . 'front_*.jpg') ?: [];
        return count($right) >= 5 && count($left) >= 5 && count($front) >= 1;
    }
}

// Get student info
$student_id = $_SESSION['student_id'];
$sql = "SELECT s.*, c.class_name, j.name as jurusan_name 
        FROM student s 
        LEFT JOIN class c ON s.class_id = c.class_id 
        LEFT JOIN jurusan j ON s.jurusan_id = j.jurusan_id 
        WHERE s.id = ?";
$stmt = $db->query($sql, [$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: ../login.php");
    exit();
}

$faceMatcherGate = new FaceMatcher();
$resolvedReferencePath = $faceMatcherGate->getReferencePath(
    $student['student_nisn'] ?? '',
    $student['photo_reference'] ?? ''
);
if (!$resolvedReferencePath) {
    $_SESSION['has_face'] = false;
    $_SESSION['face_reference_notice'] = 'Foto referensi wajah belum lengkap. Silakan lakukan register ulang.';
    header("Location: ../register.php?upload_face=1");
    exit();
}
$_SESSION['has_face'] = true;
$hasPoseCapture = hasPoseCaptureDataset(
    $student['student_nisn'] ?? '',
    $student['class_name'] ?? '',
    $student['student_name'] ?? ''
);
$_SESSION['has_pose_capture'] = $hasPoseCapture;
if (!$hasPoseCapture) {
    $_SESSION['face_pose_notice'] = 'Lengkapi capture pose kepala (kanan, kiri, depan) sebelum menggunakan dashboard.';
    header("Location: ../register.php?upload_face=1&pose_only=1");
    exit();
}

// Resolve student profile photo for sidebar logo
$profileImageUrl = null;
if (!empty($student['photo'])) {
    $photoPath = __DIR__ . '/../uploads/faces/' . $student['photo'];
    if (file_exists($photoPath)) {
        $profileImageUrl = '../uploads/faces/' . $student['photo'];
    }
}
if (!$profileImageUrl && !empty($student['photo_reference'])) {
    $photoPath = __DIR__ . '/../uploads/faces/' . $student['photo_reference'];
    if (file_exists($photoPath)) {
        $profileImageUrl = '../uploads/faces/' . $student['photo_reference'];
    }
}
if (!$profileImageUrl && !empty($student['student_nisn'])) {
    $faceMatcher = new FaceMatcher();
    $referencePath = $faceMatcher->getReferencePath(
        $student['student_nisn'],
        $student['photo_reference'] ?? ''
    );
    if ($referencePath) {
        $profileImageUrl = $faceMatcher->toPublicUrl($referencePath, '..');
    }
}
if (!$profileImageUrl) {
    $profileImageUrl = '../assets/images/presenova.png';
}

// Get today's schedule for dashboard
$today = date('Y-m-d');
$day_of_week = date('N'); // 1=Monday, 7=Sunday

// Check if theme is set in cookie, otherwise default to light
$theme = isset($_COOKIE['siswa_theme']) ? strtolower(trim((string) $_COOKIE['siswa_theme'])) : 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
$siswa_touch_icon = '../assets/images/apple-touch-icon_student.png?v=20260212c';
$siswa_icon_light = '../assets/images/favicon-32x32_student.png?v=20260212c';
$siswa_icon_dark = '../assets/images/favicon-32x32_student.png?v=20260212c';
$siswa_section_css = [
    'dashboard' => 'dashboard_siswa.css',
    'face_recognition' => 'face_recognition.css',
    'jadwal' => 'jadwal.css',
    'profil' => 'profil.css',
    'riwayat' => 'riwayat.css',
];
$active_siswa_section_css = $siswa_section_css[$page] ?? null;
$siswa_core_css_version = @filemtime(__DIR__ . '/../assets/css/siswa.css') ?: time();
$siswa_dialog_css_version = @filemtime(__DIR__ . '/../assets/css/app-dialog.css') ?: time();
$siswa_section_css_version = null;
if ($active_siswa_section_css !== null) {
    $sectionCssPath = __DIR__ . '/../assets/css/sections/' . $active_siswa_section_css;
    $siswa_section_css_version = @filemtime($sectionCssPath) ?: time();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>dashboard siswa - presenova</title>
    <meta name="color-scheme" content="light dark">
    <meta id="siswaThemeColor" name="theme-color" content="<?php echo $theme === 'dark' ? '#0f141c' : '#f7f8fc'; ?>">
    <link rel="apple-touch-icon" href="<?php echo $siswa_touch_icon; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon-16x16_student.png?v=20260212c">
    <link id="siswaFavicon" rel="icon" type="image/png" href="<?php echo $theme === 'dark' ? $siswa_icon_dark : $siswa_icon_light; ?>" data-light="<?php echo $siswa_icon_light; ?>" data-dark="<?php echo $siswa_icon_dark; ?>">
    <link id="siswaShortcutIcon" rel="shortcut icon" type="image/png" href="<?php echo $theme === 'dark' ? $siswa_icon_dark : $siswa_icon_light; ?>">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon_student.ico?v=20260212c">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Prevent theme flash -->
    <script>
        (function() {
            const savedTheme = document.cookie.match(/(?:^|;\s*)siswa_theme=([^;]+)/);
            let rawTheme = '';
            if (savedTheme) {
                try {
                    rawTheme = decodeURIComponent(savedTheme[1]).toLowerCase().trim();
                } catch (error) {
                    rawTheme = String(savedTheme[1] || '').toLowerCase().trim();
                }
            }
            const theme = rawTheme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);

            const favicon = document.getElementById('siswaFavicon');
            if (favicon) {
                const light = favicon.getAttribute('data-light');
                const dark = favicon.getAttribute('data-dark');
                favicon.setAttribute('href', theme === 'dark' ? dark : light);
            }
            const shortcutIcon = document.getElementById('siswaShortcutIcon');
            if (shortcutIcon) {
                const light = favicon ? favicon.getAttribute('data-light') : '<?php echo $siswa_icon_light; ?>';
                const dark = favicon ? favicon.getAttribute('data-dark') : '<?php echo $siswa_icon_dark; ?>';
                shortcutIcon.setAttribute('href', theme === 'dark' ? dark : light);
            }
            const themeColorMeta = document.getElementById('siswaThemeColor');
            if (themeColorMeta) {
                themeColorMeta.setAttribute('content', theme === 'dark' ? '#0f141c' : '#f7f8fc');
            }
        })();
    </script>
    
    
<link rel="stylesheet" href="../assets/css/siswa.css?v=<?php echo $siswa_core_css_version; ?>" data-inline-style="extracted">
    <link rel="stylesheet" href="../assets/css/app-dialog.css?v=<?php echo $siswa_dialog_css_version; ?>">
    <?php if ($active_siswa_section_css !== null): ?>
    <link rel="stylesheet" href="../assets/css/sections/<?php echo $active_siswa_section_css; ?>?v=<?php echo $siswa_section_css_version; ?>">
    <?php endif; ?>

</head>
<body data-enable-push="1">
    <div class="bg-orbs" aria-hidden="true">
        <span class="orb orb-1"></span>
        <span class="orb orb-2"></span>
        <span class="orb orb-3"></span>
    </div>
    <svg class="glass-filter" aria-hidden="true" width="0" height="0" style="position:absolute">
        <filter id="glass-distortion">
            <feTurbulence type="fractalNoise" baseFrequency="0.008 0.02" numOctaves="2" seed="2" result="noise" />
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="12" xChannelSelector="R" yChannelSelector="G" />
        </filter>
    </svg>
    <div class="student-shell container-fluid">
        <!-- App Container -->
        <div class="app-container">
            <!-- Sidebar Shell -->
            <div class="nav-shell" id="navShell">
                <!-- Sidebar -->
                <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="?page=dashboard" class="logo">
                    <div class="logo-icon profile-photo">
                        <img src="<?php echo $profileImageUrl; ?>" alt="Foto Profil Siswa">
                    </div>
                    <div class="logo-text">
                        <h4>Student Portal</h4>
                        <small>SMKN 1 Cikarang</small>
                    </div>
                </a>
            </div>
            
            <ul class="sidebar-menu nav flex-column">
                <li class="nav-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                    <a href="?page=dashboard" class="nav-link <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item <?php echo $page == 'jadwal' ? 'active' : ''; ?>">
                    <a href="?page=jadwal" class="nav-link <?php echo $page == 'jadwal' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Jadwal</span>
                    </a>
                </li>
                <li class="nav-item <?php echo $page == 'face_recognition' ? 'active' : ''; ?>">
                    <a href="?page=face_recognition" class="nav-link <?php echo $page == 'face_recognition' ? 'active' : ''; ?>">
                        <i class="fas fa-user-check"></i>
                        <span>Verifikasi Wajah</span>
                    </a>
                </li>
                <li class="nav-item <?php echo $page == 'riwayat' ? 'active' : ''; ?>">
                    <a href="?page=riwayat" class="nav-link <?php echo $page == 'riwayat' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Riwayat Absensi</span>
                    </a>
                </li>
                <li class="nav-item <?php echo $page == 'profil' ? 'active' : ''; ?>">
                    <a href="?page=profil" class="nav-link <?php echo $page == 'profil' ? 'active' : ''; ?>">
                        <i class="fas fa-user-circle"></i>
                        <span>Profil</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">
                        <img src="<?php echo $profileImageUrl; ?>" alt="Foto Profil Siswa">
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo $student['student_name']; ?></span>
                        <span class="user-role"><?php echo $student['class_name']; ?></span>
                    </div>
                </div>
                <div class="sidebar-logout">
                    <a href="../logout.php" class="logout-link">
                        <span class="logout-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </span>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
                </div>
            </div>
        
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h3 id="pageTitle">
                        <?php
                        $pageTitles = [
                            'dashboard' => 'Dashboard Siswa',
                            'jadwal' => 'Jadwal Pelajaran',
                            'face_recognition' => 'Verifikasi Wajah',
                            'riwayat' => 'Riwayat Absensi',
                            'profil' => 'Profil Siswa'
                        ];
                        echo $pageTitles[$page] ?? 'Dashboard Siswa';
                        ?>
                    </h3>
                    <p id="dateDisplay"><?php echo date('l, d F Y'); ?></p>
                </div>
                
                <div class="top-bar-actions">
                    <button class="btn" id="enablePushBtn" type="button">
                        <i class="fas fa-bell"></i>
                        <span>Aktifkan Notifikasi</span>
                    </button>
                    <div class="time-orbit-wrap">
                        <div class="date-time-display">
                            <i class="fas fa-clock"></i>
                            <span id="clock"><?php echo date('H:i:s'); ?></span>
                        </div>

                        <div class="theme-orbit">
                            <button class="theme-toggle" id="themeToggle" type="button" title="<?php echo $theme == 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'; ?>" aria-label="<?php echo $theme == 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'; ?>" aria-pressed="<?php echo $theme == 'dark' ? 'true' : 'false'; ?>">
                                <svg class="theme-orbit-curve" viewBox="0 0 104 64" aria-hidden="true" focusable="false">
                                    <defs>
                                        <linearGradient id="siswaOrbitGradientLight" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.9"></stop>
                                            <stop offset="55%" stop-color="#ffd1b0" stop-opacity="0.85"></stop>
                                            <stop offset="100%" stop-color="#7dc7ff" stop-opacity="0.9"></stop>
                                        </linearGradient>
                                        <linearGradient id="siswaOrbitGradientDark" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#cbd5f5" stop-opacity="0.85"></stop>
                                            <stop offset="60%" stop-color="#8b5cf6" stop-opacity="0.85"></stop>
                                            <stop offset="100%" stop-color="#f97316" stop-opacity="0.8"></stop>
                                        </linearGradient>
                                    </defs>
                                    <path class="orbit-glow orbit-glow-light" d="M 34 14 Q 76 6 92 40" stroke="rgba(255, 176, 130, 0.7)"></path>
                                    <path class="orbit-glow orbit-glow-dark" d="M 34 14 Q 76 6 92 40" stroke="rgba(147, 51, 234, 0.65)"></path>
                                    <path class="orbit-path orbit-path-light" d="M 34 14 Q 76 6 92 40" stroke="url(#siswaOrbitGradientLight)"></path>
                                    <path class="orbit-path orbit-path-dark" d="M 34 14 Q 76 6 92 40" stroke="url(#siswaOrbitGradientDark)"></path>
                                </svg>
                                <span class="theme-orbit-thumb">
                                    <span class="theme-orbit-icon">
                                        <i class="fas fa-<?php echo $theme == 'dark' ? 'sun' : 'moon'; ?>"></i>
                                    </span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <button class="mobile-menu-toggle d-lg-none" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
            
            <!-- Content based on page -->
            <div id="content">
                <?php
                // Include content based on page
                switch($page) {
                    case 'jadwal':
                        include 'roles/siswa/sections/jadwal.php';
                        break;
                    case 'face_recognition':
                        include 'roles/siswa/sections/face_recognition.php';
                        break;
                    case 'riwayat':
                        include 'roles/siswa/sections/riwayat.php';
                        break;
                    case 'profil':
                        include 'roles/siswa/sections/profil.php';
                        break;
                    default: // dashboard
                        include 'roles/siswa/sections/dashboard.php';
                }
                ?>
            </div>
        </div>
        </div>
    </div>
    
    <!-- Attendance Modal (for dashboard) -->
    <div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-camera"></i> Absensi Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="attendanceSteps">
                        <!-- Steps content from original file -->
                        <!-- Keep the original attendance modal steps -->
                        <div class="attendance-step" id="step1">
                            <div class="step-number">1</div>
                            <h6 class="step-title">Validasi Lokasi</h6>
                            <p class="text-muted">Mengecek lokasi Anda saat ini...</p>
                            <div>
                                <span class="location-status status-checking">
                                    <i class="fas fa-spinner fa-spin"></i> Memeriksa...
                                </span>
                            </div>
                        </div>
                        
                        <!-- Add other steps here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app-dialog.js"></script>
    <script src="../assets/js/schedule-print-dialog.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="../assets/js/pwa.js"></script>
    
    <script>
    const siswaPage = <?php echo json_encode((string) $page); ?>;

    // Set cookie function
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + encodeURIComponent(value || "") + expires + "; path=/";
    }

    function normalizeTheme(theme) {
        return theme === 'dark' ? 'dark' : 'light';
    }

    function ensureStudentPageUnlocked() {
        if (siswaPage === 'face_recognition') {
            return;
        }

        const html = document.documentElement;
        const body = document.body;
        html.classList.remove('scroll-locked');
        html.style.overflow = '';
        html.style.paddingRight = '';
        body.classList.remove('scroll-locked', 'modal-open', 'attendance-modal-open');
        body.style.position = '';
        body.style.top = '';
        body.style.width = '';
        body.style.overflow = '';
        body.style.paddingRight = '';
        body.style.touchAction = '';

        document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach((backdrop) => {
            backdrop.remove();
        });

        document.querySelectorAll('.modal.show').forEach((modal) => {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('aria-modal');
            modal.removeAttribute('role');
        });
    }

    function updateSiswaBranding(theme) {
        const favicon = document.getElementById('siswaFavicon');
        const shortcutIcon = document.getElementById('siswaShortcutIcon');
        if (favicon) {
            const light = favicon.getAttribute('data-light');
            const dark = favicon.getAttribute('data-dark');
            favicon.setAttribute('href', theme === 'dark' ? dark : light);
            if (shortcutIcon) {
                shortcutIcon.setAttribute('href', theme === 'dark' ? dark : light);
            }
        }
        const themeColorMeta = document.getElementById('siswaThemeColor');
        if (themeColorMeta) {
            themeColorMeta.setAttribute('content', theme === 'dark' ? '#0f141c' : '#f7f8fc');
        }
    }

    function readCssNumber(varName, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
        const num = parseFloat(value);
        return Number.isFinite(num) ? num : fallback;
    }

    function updateThemeOrbitPath() {
        const startX = readCssNumber('--orbit-start-x', 30);
        const startY = readCssNumber('--orbit-start-y', 20);
        const controlX = readCssNumber('--orbit-control-x', 62);
        const controlY = readCssNumber('--orbit-control-y', 4);
        const endX = readCssNumber('--orbit-end-x', 84);
        const endY = readCssNumber('--orbit-end-y', 44);
        const path = `M ${startX} ${startY} Q ${controlX} ${controlY} ${endX} ${endY}`;

        document.querySelectorAll('.theme-orbit-curve path').forEach((pathEl) => {
            pathEl.setAttribute('d', path);
        });

        const thumb = document.querySelector('.theme-orbit-thumb');
        if (thumb && window.CSS && CSS.supports && CSS.supports('offset-path', `path("${path}")`)) {
            thumb.style.offsetPath = `path("${path}")`;
        }
    }

    window.addEventListener('resize', updateThemeOrbitPath);
    
    // Real-time clock
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');

        const clockEl = document.getElementById('clock');
        const dateDisplayEl = document.getElementById('dateDisplay');
        if (clockEl) {
            clockEl.textContent = `${hours}:${minutes}:${seconds}`;
        }

        // Update date if needed
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const dateStr = now.toLocaleDateString('id-ID', options);
        if (dateDisplayEl) {
            dateDisplayEl.textContent = dateStr;
        }
    }
    
    // Update every second
    setInterval(updateClock, 1000);
    updateClock();
    
    let siswaPageUiInitialized = false;

    function initializeSiswaPageUi() {
        if (siswaPageUiInitialized) {
            return;
        }
        siswaPageUiInitialized = true;

        updateThemeOrbitPath();

        const currentTheme = normalizeTheme(document.documentElement.getAttribute('data-theme') || '<?php echo $theme; ?>');
        if (document.documentElement.getAttribute('data-theme') !== currentTheme) {
            document.documentElement.setAttribute('data-theme', currentTheme);
        }
        updateSiswaBranding(currentTheme);
        ensureStudentPageUnlocked();

        if (typeof window.jQuery === 'undefined') {
            return;
        }

        $(document).on('init.dt', function(event, settings) {
            const $table = $(settings.nTable);
            const $wrapper = $table.closest('.dataTables_wrapper');
            const $scrollBody = $wrapper.find('.dataTables_scrollBody');
            const $scrollHead = $wrapper.find('.dataTables_scrollHead');
            if ($scrollBody.length && $scrollHead.length) {
                $wrapper.closest('.table-responsive').addClass('dt-scroll');
                $scrollBody.off('scroll.dt-sync').on('scroll.dt-sync', function() {
                    $scrollHead.scrollLeft(this.scrollLeft);
                });
            } else {
                $wrapper.closest('.table-responsive').removeClass('dt-scroll');
            }
        });

        // Initialize DataTables for history page
        if ($.fn.DataTable && $('.data-table').length) {
            $('.data-table').DataTable({
                "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                "pageLength": 10,
                "responsive": false,
                "scrollX": false,
                "scrollCollapse": false
            });
        }

        $(window).off('resize.siswaDtAdjust').on('resize.siswaDtAdjust', function() {
            if ($.fn.dataTable && $.fn.dataTable.tables) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            }
        });
        
        // Mobile menu toggle
        $('#mobileMenuToggle').click(function() {
            $('#navShell').toggleClass('show');
        });
        
        // Theme toggle
        $('#themeToggle').click(function() {
            const html = document.documentElement;
            const currentTheme = normalizeTheme(html.getAttribute('data-theme'));
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            const icon = $(this).find('i');
            
            // Set theme attribute
            html.setAttribute('data-theme', newTheme);
            updateSiswaBranding(newTheme);
            
            // Update cookie (expires in 365 days)
            setCookie('siswa_theme', newTheme, 365);
            
            // Update icon
            if (newTheme === 'dark') {
                icon.removeClass('fa-moon').addClass('fa-sun');
                $(this).attr('title', 'Switch to Light Mode');
                $(this).attr('aria-label', 'Switch to Light Mode');
                $(this).attr('aria-pressed', 'true');
            } else {
                icon.removeClass('fa-sun').addClass('fa-moon');
                $(this).attr('title', 'Switch to Dark Mode');
                $(this).attr('aria-label', 'Switch to Dark Mode');
                $(this).attr('aria-pressed', 'false');
            }
        });
        
        // Close mobile sidebar when clicking outside
        $(document).click(function(event) {
            if ($(window).width() <= 992) {
                const navShell = $('#navShell');
                const toggle = $('#mobileMenuToggle');
                if (!navShell.is(event.target) && navShell.has(event.target).length === 0 && 
                    !toggle.is(event.target) && toggle.has(event.target).length === 0) {
                    navShell.removeClass('show');
                }
            }
        });
        
        // Attendance modal handler (from original code)
        const attendanceModal = document.getElementById('attendanceModal');
        if (attendanceModal) {
            attendanceModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const scheduleId = button.getAttribute('data-schedule-id');
                const subject = button.getAttribute('data-subject');
                const time = button.getAttribute('data-time');
                
                // Your existing attendance modal logic here
            });
        }
    }

    window.addEventListener('pageshow', function() {
        ensureStudentPageUnlocked();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSiswaPageUi, { once: true });
    } else {
        initializeSiswaPageUi();
    }
    </script>
</body>
</html>
