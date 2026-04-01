<?php
$auth = new \App\Support\Core\Auth();

// Cek login menggunakan method isLoggedIn dari Auth
if (!$auth->isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$db = new Database();

if (!isset($_SESSION['last_guru_dashboard_log']) || (time() - $_SESSION['last_guru_dashboard_log']) > 300) {
    if (!empty($_SESSION['teacher_id'])) {
        logActivity((int) $_SESSION['teacher_id'], 'guru', 'dashboard_access', 'Guru dashboard accessed');
        $_SESSION['last_guru_dashboard_log'] = time();
    }
}

// Gunakan $_SESSION['teacher_id'] yang sudah diset di login
$teacher_id = $_SESSION['teacher_id'];

// Ambil data guru
$stmt = $db->query("SELECT * FROM teacher WHERE id = ?", [$teacher_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    // Jika tidak ditemukan di tabel teacher, logout
    $auth->logout();
    header("Location: ../login.php");
    exit();
}

$generatedTeacherPassword = null;
$teacherCodeForReset = trim((string) (($teacher['teacher_code'] ?? '') ?: ($teacher['teacher_username'] ?? '')));
if ($teacherCodeForReset === '') {
    $teacherCodeForReset = '-';
}
$teacherFullNameForReset = trim((string) ($teacher['teacher_name'] ?? ''));
if ($teacherFullNameForReset === '') {
    $teacherFullNameForReset = '-';
}
$defaultTeacherPasswordHash = hash('sha256', 'guru123' . PASSWORD_SALT);
$currentTeacherPasswordHash = (string) ($teacher['teacher_password'] ?? '');
if ($currentTeacherPasswordHash !== '' && hash_equals($defaultTeacherPasswordHash, $currentTeacherPasswordHash)) {
    try {
        $generatedTeacherPassword = 'P@ssw0rdTC' . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        $generatedTeacherPassword = 'P@ssw0rdTC' . str_pad((string) mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
    }

    $newTeacherPasswordHash = hash('sha256', $generatedTeacherPassword . PASSWORD_SALT);
    $updatedTeacherPassword = $db->query(
        "UPDATE teacher SET teacher_password = ? WHERE id = ?",
        [$newTeacherPasswordHash, $teacher_id]
    );

    if ($updatedTeacherPassword) {
        $teacher['teacher_password'] = $newTeacherPasswordHash;
        $_SESSION['teacher_password_rotation_notice'] = $generatedTeacherPassword;

        logActivity((int) $teacher_id, 'guru', 'password_auto_rotate', 'Teacher default password auto-rotated');
        if (function_exists('auditMasterData')) {
            auditMasterData(
                (int) $teacher_id,
                'guru',
                'credential',
                (string) $teacher_id,
                'auto_rotate_default_password',
                ['password' => 'default_masked'],
                ['password' => 'random_masked'],
                ['source' => 'dashboard/guru', 'sync' => 'admin_masked_only']
            );
        }
    } else {
        $generatedTeacherPassword = null;
    }
}

if (isset($_SESSION['teacher_password_rotation_notice']) && is_string($_SESSION['teacher_password_rotation_notice'])) {
    $generatedTeacherPassword = $_SESSION['teacher_password_rotation_notice'];
    unset($_SESSION['teacher_password_rotation_notice']);
}

// Get page parameter for navigation
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$page_class = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $page));
if ($page_class === '') {
    $page_class = 'dashboard';
}

// Get today's schedule for dashboard
$today = date('Y-m-d');
$day_of_week = date('N'); // 1=Monday, 7=Sunday

// Check if theme is set in cookie, otherwise default to light
$theme = isset($_COOKIE['guru_theme']) ? strtolower(trim((string) $_COOKIE['guru_theme'])) : 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
$guru_touch_icon = '../assets/images/apple-touch-icon_teach.png?v=20260212c';
$guru_icon_light = '../assets/images/favicon-32x32_teach.png?v=20260212c';
$guru_icon_dark = '../assets/images/favicon-32x32_teach.png?v=20260212c';
$guru_section_css = [
    'profil' => 'guru_profil.css',
];
$active_guru_section_css = $guru_section_css[$page] ?? null;
$guru_core_css_version = @filemtime(public_path('assets/css/guru.css')) ?: time();
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>dashboard guru - presenova</title>
    <meta name="color-scheme" content="light dark">
    <meta id="guruThemeColor" name="theme-color" content="<?php echo $theme === 'dark' ? '#0b1220' : '#f6f8fb'; ?>">
    <link rel="apple-touch-icon" href="<?php echo $guru_touch_icon; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon-16x16_teach.png?v=20260212c">
    <link id="guruFavicon" rel="icon" type="image/png" href="<?php echo $theme === 'dark' ? $guru_icon_dark : $guru_icon_light; ?>" data-light="<?php echo $guru_icon_light; ?>" data-dark="<?php echo $guru_icon_dark; ?>">
    <link id="guruShortcutIcon" rel="shortcut icon" type="image/png" href="<?php echo $theme === 'dark' ? $guru_icon_dark : $guru_icon_light; ?>">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon_teach.ico?v=20260212c">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    
    <!-- Datepicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        (function() {
            const savedTheme = document.cookie.match(/(?:^|;\s*)guru_theme=([^;]+)/);
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

            const favicon = document.getElementById('guruFavicon');
            if (favicon) {
                const light = favicon.getAttribute('data-light');
                const dark = favicon.getAttribute('data-dark');
                favicon.setAttribute('href', theme === 'dark' ? dark : light);
            }
            const shortcutIcon = document.getElementById('guruShortcutIcon');
            if (shortcutIcon) {
                const light = favicon ? favicon.getAttribute('data-light') : '<?php echo $guru_icon_light; ?>';
                const dark = favicon ? favicon.getAttribute('data-dark') : '<?php echo $guru_icon_dark; ?>';
                shortcutIcon.setAttribute('href', theme === 'dark' ? dark : light);
            }
            const themeColorMeta = document.getElementById('guruThemeColor');
            if (themeColorMeta) {
                themeColorMeta.setAttribute('content', theme === 'dark' ? '#0b1220' : '#f6f8fb');
            }
        })();
    </script>
    
    
<link rel="stylesheet" href="../assets/css/guru.css?v=<?php echo $guru_core_css_version; ?>" data-inline-style="extracted">
    <link rel="stylesheet" href="../assets/css/app-dialog.css">
    <?php if ($active_guru_section_css !== null): ?>
    <link rel="stylesheet" href="../assets/css/sections/<?php echo $active_guru_section_css; ?>">
    <?php endif; ?>

</head>
<body class="guru-page guru-page-<?php echo htmlspecialchars($page_class, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="bg-ornaments" aria-hidden="true">
        <span class="blob blob-1"></span>
        <span class="blob blob-2"></span>
        <span class="grid"></span>
    </div>
    
    <!-- App Container -->
    <div class="app-container">
        <header class="top-nav" id="topNav">
            <div class="nav-inner">
                <div class="nav-left">
                    <a href="?page=dashboard" class="nav-brand">
                        <div class="logo-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="logo-text">
                            <h4>Guru Portal</h4>
                            <small>SMKN 1 Cikarang</small>
                        </div>
                    </a>

                    <button class="nav-toggle d-lg-none" id="navToggle" aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                    </button>

                    <nav class="nav-links" id="navLinks">
                        <a href="?page=dashboard" class="nav-pill <?php echo $page == 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
                        <a href="?page=jadwal" class="nav-pill <?php echo $page == 'jadwal' ? 'active' : ''; ?>">Jadwal</a>
                        <a href="?page=absensi" class="nav-pill <?php echo $page == 'absensi' ? 'active' : ''; ?>">Siswa</a>
                        <a href="?page=laporan" class="nav-pill <?php echo $page == 'laporan' ? 'active' : ''; ?>">Laporan</a>
                        <a href="?page=profil" class="nav-pill <?php echo $page == 'profil' ? 'active' : ''; ?>">Profil</a>
                        <a href="../logout.php" class="nav-pill logout">Logout</a>
                    </nav>
                </div>

                <div class="nav-right">
                    <a href="?page=profil" class="nav-profile">
                        <div class="nav-avatar">
                            <?php echo strtoupper(substr($teacher['teacher_name'], 0, 1)); ?>
                        </div>
                        <div class="nav-meta">
                            <span class="nav-name"><?php echo $teacher['teacher_name']; ?></span>
                            <span class="nav-role">Guru <?php echo $teacher['subject']; ?></span>
                        </div>
                    </a>

                    <button class="theme-toggle" id="themeToggle" title="<?php echo $theme == 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'; ?>">
                        <i class="fas fa-<?php echo $theme == 'dark' ? 'sun' : 'moon'; ?>"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h3 id="pageTitle">
                        <?php
                        $pageTitles = [
                            'dashboard' => 'Dashboard Guru',
                            'jadwal' => 'Jadwal Mengajar',
                            'absensi' => 'Siswa Yang Diajar',
                            'laporan' => 'Laporan & Statistik',
                            'profil' => 'Profil Guru'
                        ];
                        echo $pageTitles[$page] ?? 'Dashboard Guru';
                        ?>
                    </h3>
                    <p id="dateDisplay"><?php echo date('l, d F Y'); ?></p>
                </div>
                
                <div class="top-bar-actions">
                    <div class="date-time-display">
                        <i class="fas fa-clock"></i>
                        <span id="clock"><?php echo date('H:i:s'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Content based on page -->
            <div id="content">
                <?php
                // Include content based on page
                switch($page) {
                    case 'jadwal':
                        echo view('dashboard.roles.guru.sections.jadwal', get_defined_vars())->render();
                        break;
                    case 'absensi':
                        echo view('dashboard.roles.guru.sections.absensi', get_defined_vars())->render();
                        break;
                    case 'laporan':
                        echo view('dashboard.roles.guru.sections.laporan', get_defined_vars())->render();
                        break;
                    case 'profil':
                        echo view('dashboard.roles.guru.sections.profil', get_defined_vars())->render();
                        break;
                    default: // dashboard
                        echo view('dashboard.roles.guru.sections.dashboard', get_defined_vars())->render();
                }
                ?>
            </div>
        </div>
    </div>

    <?php if (!empty($generatedTeacherPassword)): ?>
    <div class="modal fade teacher-password-modal" id="teacherAutoPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered teacher-password-dialog">
            <div class="modal-content teacher-password-card" id="teacherAutoPasswordCard">
                <div class="teacher-password-accent" aria-hidden="true"></div>
                <div class="modal-header teacher-password-header border-0">
                    <div class="teacher-password-icon-wrap" aria-hidden="true">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="teacher-password-headline">
                        <h5 class="modal-title">Password Guru Diperbarui</h5>
                        <p class="mb-0">Auto-rotate keamanan telah dilakukan.</p>
                    </div>
                </div>
                <div class="modal-body teacher-password-body">
                    <div class="teacher-password-alert">
                        <i class="fas fa-shield-alt"></i>
                        <span>Password default <code>guru123</code> terdeteksi dan sudah diganti otomatis agar akun lebih aman.</span>
                    </div>

                    <div class="teacher-password-meta" aria-label="Identitas guru yang direset">
                        <div class="teacher-password-meta-item">
                            <span class="teacher-password-meta-label">Kode Guru</span>
                            <strong class="teacher-password-meta-value"><?php echo htmlspecialchars($teacherCodeForReset, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="teacher-password-meta-item">
                            <span class="teacher-password-meta-label">Nama Lengkap</span>
                            <strong class="teacher-password-meta-value"><?php echo htmlspecialchars($teacherFullNameForReset, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>

                    <div class="teacher-password-field">
                        <label class="form-label mb-2">Password Baru</label>
                        <div class="input-group teacher-password-group">
                            <input id="teacherAutoPasswordValue" type="text" class="form-control teacher-password-input" readonly value="<?php echo htmlspecialchars($generatedTeacherPassword, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="button" class="btn teacher-password-copy-btn" id="copyTeacherAutoPasswordBtn">
                                <i class="fas fa-copy me-1"></i><span>Copy</span>
                            </button>
                        </div>
                    </div>

                    <div id="teacherAutoPasswordFeedback" class="teacher-password-feedback" aria-live="polite"></div>

                    <p class="teacher-password-note mb-0">
                        Simpan password ini sekarang. Anda dapat screenshot atau download PNG sebagai bukti.
                    </p>
                </div>
                <div class="modal-footer teacher-password-footer border-0">
                    <button type="button" class="btn teacher-password-download-btn" id="downloadTeacherAutoPasswordBtn">
                        <i class="fas fa-download me-1"></i> Download PNG
                    </button>
                    <button type="button" class="btn teacher-password-confirm-btn" data-bs-dismiss="modal">
                        Saya Sudah Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app-dialog.js"></script>
    <script src="../assets/js/schedule-print-dialog.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <?php if (!empty($generatedTeacherPassword)): ?>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <?php endif; ?>
    
    <script>
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

    function updateGuruBranding(theme) {
        const favicon = document.getElementById('guruFavicon');
        const shortcutIcon = document.getElementById('guruShortcutIcon');
        if (favicon) {
            const light = favicon.getAttribute('data-light');
            const dark = favicon.getAttribute('data-dark');
            favicon.setAttribute('href', theme === 'dark' ? dark : light);
            if (shortcutIcon) {
                shortcutIcon.setAttribute('href', theme === 'dark' ? dark : light);
            }
        }
        const themeColorMeta = document.getElementById('guruThemeColor');
        if (themeColorMeta) {
            themeColorMeta.setAttribute('content', theme === 'dark' ? '#0b1220' : '#f6f8fb');
        }
    }
    
    // Real-time clock
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds}`;
        
        // Update date if needed
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const dateStr = now.toLocaleDateString('id-ID', options);
        document.getElementById('dateDisplay').textContent = dateStr;
    }
    
    // Update every second
    setInterval(updateClock, 1000);
    updateClock();

    const guruPrefetchLoaded = new Set();

    function normalizeGuruPrefetchUrl(rawUrl) {
        if (!rawUrl) {
            return '';
        }
        try {
            const url = new URL(rawUrl, window.location.href);
            if (url.origin !== window.location.origin) {
                return '';
            }
            url.hash = '';
            return url.toString();
        } catch (error) {
            return '';
        }
    }

    function prefetchGuruUrl(rawUrl) {
        const url = normalizeGuruPrefetchUrl(rawUrl);
        if (!url || guruPrefetchLoaded.has(url) || url === window.location.href) {
            return;
        }
        if (shouldSkipGuruPrefetch(url)) {
            return;
        }
        guruPrefetchLoaded.add(url);
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Presenova-Prefetch': '1'
            }
        }).catch(() => {
            guruPrefetchLoaded.delete(url);
        });
    }

    function shouldSkipGuruPrefetch(url) {
        try {
            const parsed = new URL(url, window.location.origin);
            const pathname = parsed.pathname.toLowerCase();
            const params = parsed.searchParams;
            if (pathname.endsWith('/logout.php') || pathname.includes('/logout')) {
                return true;
            }
            if (params.has('download') || params.has('export') || params.has('autoprint') || params.has('action')) {
                return true;
            }
            return false;
        } catch (error) {
            return true;
        }
    }

    function initGuruSectionPrefetch() {
        const navLinks = Array.from(document.querySelectorAll('#topNav .nav-links a[href]'));
        if (!navLinks.length) {
            return;
        }

        const urls = [];
        navLinks.forEach((link) => {
            const normalized = normalizeGuruPrefetchUrl(link.href);
            if (!normalized) {
                return;
            }
            urls.push(normalized);
            link.addEventListener('mouseenter', () => prefetchGuruUrl(normalized), { passive: true });
            link.addEventListener('focus', () => prefetchGuruUrl(normalized), { passive: true });
            link.addEventListener('touchstart', () => prefetchGuruUrl(normalized), { passive: true, once: true });
        });

        const prime = () => {
            urls
                .filter((url) => url !== window.location.href)
                .slice(0, 3)
                .forEach((url, index) => {
                    setTimeout(() => prefetchGuruUrl(url), index * 220);
                });
        };

        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(prime, { timeout: 1800 });
            return;
        }
        setTimeout(prime, 650);
    }

    const guruExportIdentity = Object.freeze({
        teacherName: <?php echo json_encode((string) ($teacher['teacher_name'] ?? '-'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        teacherCode: <?php echo json_encode((string) (($teacher['teacher_code'] ?? '') ?: ($teacher['teacher_username'] ?? '-')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        subject: <?php echo json_encode((string) ($teacher['subject'] ?? '-'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        schoolName: 'SMKN 1 Cikarang'
    });

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function stripHtmlToText(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return $('<div>').html(String(value)).text().replace(/\s+/g, ' ').trim();
    }

    function parseNumericValue(value) {
        const clean = stripHtmlToText(value).replace(/[^0-9,.-]/g, '').replace(',', '.');
        const parsed = parseFloat(clean);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function parseIsoDate(value) {
        const raw = String(value || '').trim();
        const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) {
            return null;
        }
        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        if (!year || month < 1 || month > 12 || day < 1 || day > 31) {
            return null;
        }
        return new Date(Date.UTC(year, month - 1, day));
    }

    function formatDateId(value) {
        const date = parseIsoDate(value);
        if (!date) {
            return '-';
        }
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC'
        }).format(date);
    }

    function formatMonthYearId(value) {
        const date = parseIsoDate(value);
        if (!date) {
            return '-';
        }
        return new Intl.DateTimeFormat('id-ID', {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC'
        }).format(date);
    }

    function formatDateTimeWib() {
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'Asia/Jakarta'
        }).format(new Date()) + ' WIB';
    }

    function slugifyFilename(value) {
        return String(value || 'laporan-guru')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function buildGuruExportFilename(prefix) {
        const stamp = new Intl.DateTimeFormat('sv-SE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'Asia/Jakarta'
        }).format(new Date()).replace(/[:\s]/g, '-');
        return `${prefix}_${stamp}`;
    }

    function getSelectedOptionText(selectElement) {
        if (!selectElement || selectElement.selectedIndex < 0) {
            return 'Semua Kelas';
        }
        const option = selectElement.options[selectElement.selectedIndex];
        return option ? String(option.text || '').trim() : 'Semua Kelas';
    }

    function resolveGuruFilterContext(tableId) {
        let fromInput = null;
        let toInput = null;
        let classSelect = null;

        if (tableId === 'guruStudentMonitorTable') {
            fromInput = document.getElementById('guruDateFrom');
            toInput = document.getElementById('guruDateTo');
            classSelect = document.querySelector('#guruStudentFilterForm select[name="class"]');
        } else if (tableId === 'guruStudentReportTable') {
            fromInput = document.getElementById('guruReportStartDate');
            toInput = document.getElementById('guruReportEndDate');
            classSelect = document.querySelector('#guruReportFilterForm select[name="class"]');
        }

        if (!fromInput) {
            fromInput = document.getElementById('guruDateFrom') || document.getElementById('guruReportStartDate');
        }
        if (!toInput) {
            toInput = document.getElementById('guruDateTo') || document.getElementById('guruReportEndDate');
        }
        if (!classSelect) {
            classSelect = document.querySelector('#guruStudentFilterForm select[name="class"], #guruReportFilterForm select[name="class"]');
        }

        return {
            fromDate: fromInput ? String(fromInput.value || '').trim() : '',
            toDate: toInput ? String(toInput.value || '').trim() : '',
            classLabel: getSelectedOptionText(classSelect)
        };
    }

    function buildGuruPeriodLabels(fromDate, toDate) {
        const fromLabel = fromDate ? formatDateId(fromDate) : '-';
        const toLabel = toDate ? formatDateId(toDate) : '-';
        const monthFrom = fromDate ? formatMonthYearId(fromDate) : '-';
        const monthTo = toDate ? formatMonthYearId(toDate) : '-';

        let monthYearLabel = '-';
        if (monthFrom !== '-' || monthTo !== '-') {
            monthYearLabel = monthFrom === monthTo ? monthFrom : `${monthFrom} - ${monthTo}`;
        }

        return {
            periodLabel: `${fromLabel} - ${toLabel}`,
            monthYearLabel
        };
    }

    function extractGuruTableSummary(tableId) {
        const summary = {
            rowCount: 0,
            needAttention: 0,
            alpaTotal: 0
        };
        const tableSelector = `#${tableId}`;
        if (!$.fn.dataTable || !$.fn.dataTable.isDataTable(tableSelector)) {
            return summary;
        }

        const dt = $(tableSelector).DataTable();
        const rows = dt.rows({ search: 'applied' }).data().toArray();
        summary.rowCount = rows.length;

        rows.forEach((row) => {
            if (!Array.isArray(row)) {
                return;
            }
            summary.alpaTotal += Math.max(0, Math.round(parseNumericValue(row[9] ?? '0')));
            const statusText = stripHtmlToText(row[11] ?? '');
            if (/perlu perhatian/i.test(statusText)) {
                summary.needAttention++;
            }
        });

        return summary;
    }

    function getGuruExportContext(tableId, exportTitle) {
        const filter = resolveGuruFilterContext(tableId);
        const period = buildGuruPeriodLabels(filter.fromDate, filter.toDate);
        const summary = extractGuruTableSummary(tableId);
        const pageTitleElement = document.getElementById('pageTitle');
        const pageTitle = stripHtmlToText(pageTitleElement ? pageTitleElement.textContent : 'Dashboard Guru');

        return {
            exportTitle,
            pageTitle,
            teacherName: guruExportIdentity.teacherName || '-',
            teacherCode: guruExportIdentity.teacherCode || '-',
            subject: guruExportIdentity.subject || '-',
            schoolName: guruExportIdentity.schoolName,
            classLabel: filter.classLabel || 'Semua Kelas',
            periodLabel: period.periodLabel,
            periodMonthYear: period.monthYearLabel,
            printedAt: formatDateTimeWib(),
            rowCount: summary.rowCount,
            needAttention: summary.needAttention,
            alpaTotal: summary.alpaTotal
        };
    }

    function buildGuruExportMetaLines(context) {
        return [
            `Dokumen: ${context.exportTitle}`,
            `Dicetak oleh: ${context.teacherName} (${context.teacherCode})`,
            `Mapel: ${context.subject}`,
            `Halaman: ${context.pageTitle}`,
            `Filter kelas: ${context.classLabel}`,
            `Periode data: ${context.periodLabel}`,
            `Bulan/Tahun data: ${context.periodMonthYear}`,
            `Waktu cetak: ${context.printedAt}`,
            `Ringkasan: total baris ${context.rowCount}, perlu perhatian ${context.needAttention}, tidak hadir (alpa) ${context.alpaTotal}`
        ];
    }

    function buildGuruPrintHeaderHtml(context) {
        const metaRows = [
            ['Dokumen', context.exportTitle],
            ['Dicetak Oleh', `${context.teacherName} (${context.teacherCode})`],
            ['Mata Pelajaran', context.subject],
            ['Halaman', context.pageTitle],
            ['Filter Kelas', context.classLabel],
            ['Periode Data', context.periodLabel],
            ['Bulan/Tahun Data', context.periodMonthYear],
            ['Waktu Cetak', context.printedAt],
            ['Ringkasan', `Total baris ${context.rowCount} | Perlu perhatian ${context.needAttention} | Tidak hadir (alpa) ${context.alpaTotal}`]
        ];

        const rowsHtml = metaRows.map((row) => `
            <tr>
                <td>${escapeHtml(row[0])}</td>
                <td>${escapeHtml(row[1])}</td>
            </tr>
        `).join('');

        return `
            <div class="guru-print-header">
                <h2>${escapeHtml(context.exportTitle)}</h2>
                <p class="guru-print-school">${escapeHtml(context.schoolName)}</p>
                <table class="guru-print-meta">${rowsHtml}</table>
            </div>
        `;
    }

    function buildGuruExportOptions() {
        return {
            columns: ':visible',
            format: {
                header: function(data) {
                    return stripHtmlToText(data);
                },
                body: function(data) {
                    return stripHtmlToText(data);
                },
                footer: function(data) {
                    return stripHtmlToText(data);
                }
            }
        };
    }

    function createGuruTableExportButtons(tableId, exportTitle) {
        const filenamePrefix = slugifyFilename(exportTitle || 'laporan-guru');
        const exportOptions = buildGuruExportOptions();

        return [
            {
                extend: 'excelHtml5',
                title: exportTitle,
                filename: function() {
                    return buildGuruExportFilename(filenamePrefix);
                },
                messageTop: function() {
                    const context = getGuruExportContext(tableId, exportTitle);
                    return buildGuruExportMetaLines(context).join('\n');
                },
                autoFilter: true,
                exportOptions,
                customize: function(xlsx) {
                    const sheet = xlsx && xlsx.xl && xlsx.xl.worksheets ? xlsx.xl.worksheets['sheet1.xml'] : null;
                    if (!sheet) {
                        return;
                    }
                    const widthByTable = {
                        guruStudentMonitorTable: [6, 13, 28, 16, 12, 10, 13, 10, 10, 18, 14, 22],
                        guruStudentReportTable: [6, 13, 28, 16, 12, 10, 13, 10, 10, 18, 14, 22]
                    };
                    const widths = widthByTable[tableId] || [];
                    $('col', sheet).each(function(index) {
                        const width = widths[index];
                        if (width) {
                            $(this).attr('width', String(width));
                            $(this).attr('customWidth', '1');
                        }
                    });
                }
            },
            {
                extend: 'pdfHtml5',
                title: exportTitle,
                filename: function() {
                    return buildGuruExportFilename(filenamePrefix);
                },
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions,
                customize: function(doc) {
                    const context = getGuruExportContext(tableId, exportTitle);
                    const tableNode = Array.isArray(doc.content) ? doc.content.find((node) => node && node.table) : null;
                    if (!tableNode) {
                        return;
                    }

                    let columnCount = 0;
                    if (tableNode.table && Array.isArray(tableNode.table.body) && Array.isArray(tableNode.table.body[0])) {
                        columnCount = tableNode.table.body[0].length;
                    }
                    if (columnCount === 12) {
                        tableNode.table.widths = [20, 52, 102, 64, 46, 36, 46, 36, 36, 56, 54, 74];
                    } else if (columnCount === 11) {
                        tableNode.table.widths = [22, 54, 110, 66, 48, 40, 46, 40, 40, 62, 58];
                    }
                    tableNode.layout = {
                        hLineWidth: function() { return 0.7; },
                        vLineWidth: function() { return 0.7; },
                        hLineColor: function() { return '#475569'; },
                        vLineColor: function() { return '#475569'; },
                        paddingLeft: function() { return 4; },
                        paddingRight: function() { return 4; },
                        paddingTop: function() { return 3; },
                        paddingBottom: function() { return 3; }
                    };

                    doc.pageMargins = [20, 20, 20, 24];
                    doc.defaultStyle = doc.defaultStyle || {};
                    doc.defaultStyle.fontSize = 8;
                    doc.defaultStyle.color = '#0f172a';
                    doc.styles = doc.styles || {};
                    doc.styles.title = {
                        fontSize: 13,
                        bold: true,
                        color: '#0f172a',
                        alignment: 'center',
                        margin: [0, 0, 0, 8]
                    };
                    doc.styles.tableHeader = {
                        bold: true,
                        fontSize: 9,
                        color: '#ffffff',
                        fillColor: '#1f3b55',
                        alignment: 'center'
                    };

                    const metaTable = {
                        margin: [0, 0, 0, 10],
                        table: {
                            widths: [130, '*'],
                            body: buildGuruExportMetaLines(context).map((line) => {
                                const splitIndex = line.indexOf(':');
                                const label = splitIndex >= 0 ? line.slice(0, splitIndex).trim() : line;
                                const value = splitIndex >= 0 ? line.slice(splitIndex + 1).trim() : '-';
                                return [
                                    { text: label, bold: true, fillColor: '#eef2f7' },
                                    { text: value }
                                ];
                            })
                        },
                        layout: {
                            hLineWidth: function() { return 0.5; },
                            vLineWidth: function() { return 0.5; },
                            hLineColor: function() { return '#cbd5e1'; },
                            vLineColor: function() { return '#cbd5e1'; },
                            paddingLeft: function() { return 5; },
                            paddingRight: function() { return 5; },
                            paddingTop: function() { return 3; },
                            paddingBottom: function() { return 3; }
                        }
                    };

                    doc.content = [
                        { text: context.exportTitle, style: 'title' },
                        metaTable,
                        tableNode
                    ];
                }
            },
            {
                extend: 'print',
                title: exportTitle,
                exportOptions,
                customize: function(win) {
                    const context = getGuruExportContext(tableId, exportTitle);
                    const style = win.document.createElement('style');
                    style.type = 'text/css';
                    style.textContent = `
                        @page { size: A4 landscape; margin: 10mm; }
                        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; color: #0f172a; }
                        .guru-print-header { margin-bottom: 12px; }
                        .guru-print-header h2 { margin: 0 0 4px; font-size: 18px; text-align: center; }
                        .guru-print-school { margin: 0 0 10px; text-align: center; color: #334155; font-size: 12px; }
                        .guru-print-meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                        .guru-print-meta td { border: 1px solid #94a3b8; padding: 5px 7px; vertical-align: top; }
                        .guru-print-meta td:first-child { width: 190px; font-weight: 700; background: #eef2f7; }
                        table { width: 100% !important; border-collapse: collapse !important; table-layout: fixed; }
                        table thead th, table tbody td { border: 1px solid #475569 !important; padding: 6px 6px !important; vertical-align: top !important; word-break: break-word; }
                        table thead th { background: #1f3b55 !important; color: #ffffff !important; text-align: center !important; }
                        table tbody tr:nth-child(even) td { background: #f8fafc !important; }
                    `;
                    win.document.head.appendChild(style);

                    const currentTitle = win.document.body.querySelector('h1');
                    if (currentTitle) {
                        currentTitle.remove();
                    }
                    win.document.body.insertAdjacentHTML('afterbegin', buildGuruPrintHeaderHtml(context));
                }
            }
        ];
    }

    function triggerGuruTableExport(tableId, action) {
        if (!tableId || !action || !$.fn.dataTable) {
            return false;
        }

        const tableSelector = `#${tableId}`;
        if (!$(tableSelector).length || !$.fn.dataTable.isDataTable(tableSelector)) {
            return false;
        }

        const dt = $(tableSelector).DataTable();
        const buttonSelector = action === 'excel'
            ? '.buttons-excel'
            : action === 'pdf'
                ? '.buttons-pdf'
                : action === 'print'
                    ? '.buttons-print'
                    : '';
        if (!buttonSelector) {
            return false;
        }

        const buttonApi = dt.button(buttonSelector);
        if (!buttonApi || !buttonApi.length) {
            return false;
        }

        buttonApi.trigger();
        return true;
    }
    
$(document).ready(function() {
        initGuruSectionPrefetch();
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
        // Initialize DataTables with export buttons
        if ($('.data-table-export').length) {
            const exportTitles = {
                guruStudentMonitorTable: 'Monitoring Siswa Guru',
                guruStudentReportTable: 'Laporan Rekap Siswa Guru'
            };
            $('.data-table-export').each(function() {
                const tableElement = this;
                if ($.fn.dataTable.isDataTable(tableElement)) {
                    return;
                }

                const tableId = tableElement.getAttribute('id') || '';
                const exportTitle = exportTitles[tableId] || 'Laporan Guru';

                $(tableElement).DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                    },
                    "pageLength": 10,
                    "responsive": false,
                    "scrollX": false,
                    "scrollCollapse": false,
                    "dom": "<'dt-export-hidden'B>frtip",
                    "buttons": createGuruTableExportButtons(tableId, exportTitle)
                });
            });
        }
        
        // Initialize DataTables without export buttons
        if ($('.data-table').length && !$('.data-table').hasClass('data-table-export')) {
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

        $(document).on('click', '.guru-export-btn', function() {
            const tableId = String($(this).data('export-table') || '');
            const action = String($(this).data('export-action') || '').toLowerCase();
            const ok = triggerGuruTableExport(tableId, action);
            if (!ok) {
                console.warn('Ekspor gagal: tabel atau tombol DataTables tidak ditemukan.', tableId, action);
            }
        });

        $(window).off('resize.guruDtAdjust').on('resize.guruDtAdjust', function() {
            if ($.fn.dataTable && $.fn.dataTable.tables) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            }
        });
        
        // Initialize datepickers
        if ($('.datepicker').length) {
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                locale: "id",
                allowInput: true
            });
        }

        const topNavEl = document.getElementById('topNav');
        const navToggleEl = document.getElementById('navToggle');
        const navLinksEl = document.getElementById('navLinks');
        const compactViewportMq = window.matchMedia('(max-width: 992px)');

        const syncGuruTopNavOffset = () => {
            const mainContent = document.getElementById('mainContent');
            if (!topNavEl || !mainContent) {
                return;
            }
            const navTop = parseFloat(window.getComputedStyle(topNavEl).top) || 0;
            const navHeight = topNavEl.getBoundingClientRect().height;
            const extraGap = compactViewportMq.matches ? 12 : 22;
            const offsetValue = Math.ceil(navTop + navHeight + extraGap);
            document.documentElement.style.setProperty('--guru-top-nav-offset', `${offsetValue}px`);
        };

        const setGuruMobileNavOpen = (isOpen) => {
            if (!topNavEl) {
                return;
            }
            topNavEl.classList.toggle('nav-open', isOpen);
            topNavEl.classList.remove('nav-hidden');
            if (navToggleEl) {
                navToggleEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                const icon = navToggleEl.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-bars', !isOpen);
                    icon.classList.toggle('fa-times', isOpen);
                }
            }
            window.requestAnimationFrame(syncGuruTopNavOffset);
        };

        if (navToggleEl) {
            navToggleEl.setAttribute('aria-expanded', 'false');
            if (navLinksEl) {
                navToggleEl.setAttribute('aria-controls', 'navLinks');
            }
        }

        // Mobile navigation toggle
        $('#navToggle').on('click', function() {
            const isOpen = topNavEl ? !topNavEl.classList.contains('nav-open') : false;
            setGuruMobileNavOpen(isOpen);
        });

        // Close mobile navigation on link click
        $('#topNav .nav-links a').on('click', function() {
            setGuruMobileNavOpen(false);
        });

        // Close mobile navigation when tapping outside of navbar
        $(document).on('click', function(event) {
            if (!compactViewportMq.matches || !topNavEl || !topNavEl.classList.contains('nav-open')) {
                return;
            }
            if (topNavEl.contains(event.target)) {
                return;
            }
            setGuruMobileNavOpen(false);
        });

        window.requestAnimationFrame(syncGuruTopNavOffset);
        window.setTimeout(syncGuruTopNavOffset, 220);
        window.addEventListener('resize', syncGuruTopNavOffset, { passive: true });
        window.addEventListener('orientationchange', syncGuruTopNavOffset);

        // Theme toggle
        $('#themeToggle').click(function() {
            const html = document.documentElement;
            const currentTheme = normalizeTheme(html.getAttribute('data-theme'));
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            const icon = $(this).find('i');
            
            // Set theme attribute
            html.setAttribute('data-theme', newTheme);
            updateGuruBranding(newTheme);
            
            // Update cookie (expires in 365 days)
            setCookie('guru_theme', newTheme, 365);
            
            // Update icon
            if (newTheme === 'dark') {
                icon.removeClass('fa-moon').addClass('fa-sun');
                $(this).attr('title', 'Switch to Light Mode');
            } else {
                icon.removeClass('fa-sun').addClass('fa-moon');
                $(this).attr('title', 'Switch to Dark Mode');
            }
        });

        const currentTheme = normalizeTheme(document.documentElement.getAttribute('data-theme') || '<?php echo $theme; ?>');
        if (document.documentElement.getAttribute('data-theme') !== currentTheme) {
            document.documentElement.setAttribute('data-theme', currentTheme);
        }
        updateGuruBranding(currentTheme);
        
        // Attendance filter form submission
        $('#filterForm').on('submit', function(e) {
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...').prop('disabled', true);
            
            // Allow form to submit normally
        });

        <?php if (!empty($generatedTeacherPassword)): ?>
        const teacherPasswordModalEl = document.getElementById('teacherAutoPasswordModal');
        const teacherPasswordCardEl = document.getElementById('teacherAutoPasswordCard');
        const teacherPasswordFeedbackEl = document.getElementById('teacherAutoPasswordFeedback');
        const teacherPasswordCopyBtnEl = document.getElementById('copyTeacherAutoPasswordBtn');
        const teacherPasswordDownloadBtnEl = document.getElementById('downloadTeacherAutoPasswordBtn');
        const teacherPasswordModal = teacherPasswordModalEl ? bootstrap.Modal.getOrCreateInstance(teacherPasswordModalEl, {
            backdrop: 'static',
            keyboard: false
        }) : null;
        if (teacherPasswordModal) {
            teacherPasswordModal.show();
        }

        const notifyTeacherPasswordUpdate = async () => {
            if (!('Notification' in window) || Notification.permission !== 'granted' || !('serviceWorker' in navigator)) {
                return;
            }

            const teacherCode = <?php echo json_encode($teacherCodeForReset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const teacherName = <?php echo json_encode($teacherFullNameForReset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            try {
                const registration = await navigator.serviceWorker.ready;
                registration.showNotification('Password Guru Diperbarui', {
                    body: `Akun ${teacherName} (${teacherCode}) sudah dirotasi otomatis. Simpan password baru Anda sekarang.`,
                    icon: '../assets/images/logo-192.png',
                    badge: '../assets/images/logo-192.png',
                    data: { url: '?page=profil' }
                });
            } catch (error) {
                // Silent fail for unsupported browser states.
            }
        };
        notifyTeacherPasswordUpdate();

        let teacherFeedbackTimer = null;
        const setTeacherPasswordFeedback = (message, type = 'info') => {
            if (!teacherPasswordFeedbackEl) {
                return;
            }
            teacherPasswordFeedbackEl.textContent = message;
            teacherPasswordFeedbackEl.classList.remove('is-info', 'is-success', 'is-error');
            teacherPasswordFeedbackEl.classList.add(`is-${type}`);

            if (teacherFeedbackTimer) {
                clearTimeout(teacherFeedbackTimer);
            }
            teacherFeedbackTimer = setTimeout(() => {
                teacherPasswordFeedbackEl.textContent = '';
                teacherPasswordFeedbackEl.classList.remove('is-info', 'is-success', 'is-error');
            }, 2600);
        };

        $('#copyTeacherAutoPasswordBtn').on('click', async function() {
            const input = document.getElementById('teacherAutoPasswordValue');
            const value = input ? input.value : '';
            if (!value) {
                setTeacherPasswordFeedback('Password tidak tersedia untuk disalin.', 'error');
                return;
            }
            try {
                await navigator.clipboard.writeText(value);
                setTeacherPasswordFeedback('Password berhasil disalin ke clipboard.', 'success');
                if (teacherPasswordCopyBtnEl) {
                    teacherPasswordCopyBtnEl.innerHTML = '<i class="fas fa-check me-1"></i><span>Tersalin</span>';
                    teacherPasswordCopyBtnEl.classList.add('is-done');
                    setTimeout(() => {
                        teacherPasswordCopyBtnEl.innerHTML = '<i class="fas fa-copy me-1"></i><span>Copy</span>';
                        teacherPasswordCopyBtnEl.classList.remove('is-done');
                    }, 1500);
                }
            } catch (err) {
                let fallbackSuccess = false;
                if (input && typeof document.execCommand === 'function') {
                    try {
                        input.select();
                        fallbackSuccess = document.execCommand('copy') === true;
                    } catch (copyError) {
                        fallbackSuccess = false;
                    }
                }
                if (fallbackSuccess) {
                    setTeacherPasswordFeedback('Password berhasil disalin ke clipboard.', 'success');
                } else {
                    setTeacherPasswordFeedback('Gagal copy otomatis. Silakan salin manual.', 'error');
                }
            }
        });

        $('#downloadTeacherAutoPasswordBtn').on('click', function() {
            if (!teacherPasswordCardEl || typeof html2canvas === 'undefined') {
                setTeacherPasswordFeedback('Fitur download PNG belum tersedia pada browser ini.', 'error');
                return;
            }

            if (teacherPasswordDownloadBtnEl) {
                teacherPasswordDownloadBtnEl.disabled = true;
                teacherPasswordDownloadBtnEl.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyiapkan PNG...';
            }

            html2canvas(teacherPasswordCardEl, {
                backgroundColor: '#ffffff',
                scale: 2
            }).then((canvas) => {
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = 'password-guru-baru.png';
                link.click();
                setTeacherPasswordFeedback('PNG berhasil diunduh.', 'success');
            }).catch(() => {
                setTeacherPasswordFeedback('Gagal membuat PNG. Silakan gunakan screenshot manual.', 'error');
            }).finally(() => {
                if (teacherPasswordDownloadBtnEl) {
                    teacherPasswordDownloadBtnEl.disabled = false;
                    teacherPasswordDownloadBtnEl.innerHTML = '<i class="fas fa-download me-1"></i> Download PNG';
                }
            });
        });
        <?php endif; ?>
    });

    // Hide/show top navigation on scroll
    (function() {
        const nav = document.getElementById('topNav');
        if (!nav) {
            return;
        }

        const compactViewportMq = window.matchMedia('(max-width: 992px)');
        let lastScroll = window.scrollY || 0;
        let isTicking = false;

        const handleScrollState = () => {
            const currentScroll = window.scrollY || 0;
            const isMobile = compactViewportMq.matches;
            const hideOffset = isMobile ? 10 : 120;
            const deltaThreshold = isMobile ? 2 : 8;
            const delta = currentScroll - lastScroll;

            if (nav.classList.contains('nav-open')) {
                lastScroll = currentScroll;
                isTicking = false;
                return;
            }

            if (currentScroll <= 0) {
                nav.classList.remove('nav-hidden');
                lastScroll = 0;
                isTicking = false;
                return;
            }

            if (delta > deltaThreshold && currentScroll > hideOffset) {
                nav.classList.add('nav-hidden');
            } else if (delta < -deltaThreshold || currentScroll <= hideOffset) {
                nav.classList.remove('nav-hidden');
            }

            lastScroll = currentScroll;
            isTicking = false;
        };

        window.addEventListener('scroll', function() {
            if (isTicking) {
                return;
            }
            isTicking = true;
            window.requestAnimationFrame(handleScrollState);
        }, { passive: true });

        const resetOnViewportChange = () => {
            nav.classList.remove('nav-hidden');
            lastScroll = window.scrollY || 0;
        };
        if (typeof compactViewportMq.addEventListener === 'function') {
            compactViewportMq.addEventListener('change', resetOnViewportChange);
        } else if (typeof compactViewportMq.addListener === 'function') {
            compactViewportMq.addListener(resetOnViewportChange);
        }
    })();
    </script>
</body>
</html>
