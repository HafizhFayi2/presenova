<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();

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

// Get page parameter for navigation
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

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
    
    
<link rel="stylesheet" href="../assets/css/guru.css" data-inline-style="extracted">
    <link rel="stylesheet" href="../assets/css/app-dialog.css">
    <?php if ($active_guru_section_css !== null): ?>
    <link rel="stylesheet" href="../assets/css/sections/<?php echo $active_guru_section_css; ?>">
    <?php endif; ?>

</head>
<body>
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
                        <a href="?page=absensi" class="nav-pill <?php echo $page == 'absensi' ? 'active' : ''; ?>">Absensi</a>
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
                            'absensi' => 'Rekap Absensi',
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
                        include 'roles/guru/sections/jadwal.php';
                        break;
                    case 'absensi':
                        include 'roles/guru/sections/absensi.php';
                        break;
                    case 'laporan':
                        include 'roles/guru/sections/laporan.php';
                        break;
                    case 'profil':
                        include 'roles/guru/sections/profil.php';
                        break;
                    default: // dashboard
                        include 'roles/guru/sections/dashboard.php';
                }
                ?>
            </div>
        </div>
    </div>
    
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
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    
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
    
$(document).ready(function() {
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
            $('.data-table-export').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                "pageLength": 10,
                "responsive": false,
                "scrollX": false,
                "scrollCollapse": false,
                "dom": 'Bfrtip',
                "buttons": [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success',
                        title: 'Laporan Absensi Guru',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger',
                        title: 'Laporan Absensi Guru',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn btn-primary',
                        title: 'Laporan Absensi Guru',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ]
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

        // Mobile navigation toggle
        $('#navToggle').on('click', function() {
            $('#topNav').toggleClass('nav-open').removeClass('nav-hidden');
        });

        // Close mobile navigation on link click
        $('#topNav .nav-links a').on('click', function() {
            $('#topNav').removeClass('nav-open');
        });

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
    });

    // Hide/show top navigation on scroll
    (function() {
        const nav = document.getElementById('topNav');
        if (!nav) return;
        let lastScroll = window.scrollY;

        window.addEventListener('scroll', function() {
            const currentScroll = window.scrollY;
            if (nav.classList.contains('nav-open')) {
                lastScroll = currentScroll;
                return;
            }

            if (currentScroll > lastScroll && currentScroll > 120) {
                nav.classList.add('nav-hidden');
            } else {
                nav.classList.remove('nav-hidden');
            }

            lastScroll = currentScroll;
        }, { passive: true });
    })();
    
    // Function to export table to Excel
    function exportToExcel(tableId, filename) {
        const table = document.getElementById(tableId);
        const wb = XLSX.utils.table_to_book(table, {sheet: "Sheet 1"});
        XLSX.writeFile(wb, filename + '.xlsx');
    }
    </script>
</body>
</html>
