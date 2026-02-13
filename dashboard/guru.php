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
$theme = isset($_COOKIE['guru_theme']) ? $_COOKIE['guru_theme'] : 'light';
$guru_touch_icon = '../assets/images/apple-touch-icon_teach.png?v=20260212c';
$guru_icon_light = '../assets/images/favicon-32x32_teach.png?v=20260212c';
$guru_icon_dark = '../assets/images/favicon-32x32_teach.png?v=20260212c';
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
            const savedTheme = document.cookie.match(/guru_theme=([^;]+)/);
            const theme = savedTheme ? savedTheme[1] : 'light';
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
    
    <style>
        :root {
            /* Aurora Theme - Cyan & Amber */
            --primary-blue: #0ea5e9;
            --secondary-blue: #22d3ee;
            --accent-gold: #f97316;
            --emerald: #10b981;
            --light-blue: #e0f2fe;
            --white: #ffffff;
            --text-primary: #0f172a;
            --text-secondary: #6b7280;
            --border-color: #d7e3f4;
            --card-shadow: 0 20px 60px rgba(15, 23, 42, 0.08);
            --sidebar-width: 280px;
            --sidebar-collapsed: 84px;
            --transition: all 0.3s ease;
            --gradient-primary: linear-gradient(135deg, #0ea5e9 0%, #22d3ee 45%, #14b8a6 100%);
            --gradient-accent: linear-gradient(135deg, #f97316 0%, #f59e0b 45%, #fbbf24 100%);
            --glass: rgba(255, 255, 255, 0.45);
            --glass-strong: rgba(255, 255, 255, 0.65);
            
            /* Dark Theme Variables */
            --dark-bg: #0b1220;
            --dark-card: #111a2e;
            --dark-border: #1f2a44;
            --dark-text: #e2e8f0;
            --dark-text-secondary: #94a3b8;
            --dark-shadow: 0 18px 48px rgba(0, 0, 0, 0.35);
        }
        
        /* Theme Variables */
        [data-theme="light"] {
            --bg-color: #f6f8fb;
            --card-color: rgba(255, 255, 255, 0.92);
            --text-color: var(--text-primary);
            --text-secondary-color: var(--text-secondary);
            --border: #e7eef7;
            --sidebar-bg: linear-gradient(180deg, rgba(14, 165, 233, 0.95) 0%, rgba(34, 211, 238, 0.9) 45%, rgba(20, 184, 166, 0.92) 100%);
            --sidebar-text: rgba(255, 255, 255, 0.96);
            --sidebar-hover: rgba(255, 255, 255, 0.12);
            --sidebar-active: rgba(255, 255, 255, 0.18);
            --topbar-bg: rgba(255, 255, 255, 0.9);
            --topbar-text: var(--text-primary);
        }
        
        [data-theme="dark"] {
            --bg-color: var(--dark-bg);
            --card-color: rgba(17, 26, 46, 0.9);
            --text-color: var(--dark-text);
            --text-secondary-color: var(--dark-text-secondary);
            --border: var(--dark-border);
            --sidebar-bg: linear-gradient(180deg, rgba(14, 165, 233, 0.12) 0%, rgba(34, 211, 238, 0.1) 45%, rgba(17, 94, 89, 0.18) 100%);
            --sidebar-text: rgba(255, 255, 255, 0.9);
            --sidebar-hover: rgba(255, 255, 255, 0.08);
            --sidebar-active: rgba(255, 255, 255, 0.12);
            --topbar-bg: rgba(17, 26, 46, 0.9);
            --topbar-text: var(--dark-text);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, transform 0.2s ease;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Manrope', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            background-image:
                radial-gradient(circle at 18% 20%, rgba(14, 165, 233, 0.14), transparent 32%),
                radial-gradient(circle at 82% 0%, rgba(249, 115, 22, 0.12), transparent 30%),
                radial-gradient(circle at 70% 65%, rgba(20, 184, 166, 0.12), transparent 28%);
            color: var(--text-color);
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
        }
        
        html[data-theme="dark"] body {
            background-image:
                radial-gradient(circle at 20% 18%, rgba(14, 165, 233, 0.12), transparent 30%),
                radial-gradient(circle at 82% 12%, rgba(249, 115, 22, 0.08), transparent 36%),
                radial-gradient(circle at 60% 80%, rgba(20, 184, 166, 0.15), transparent 32%);
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Space Grotesk', 'Manrope', sans-serif;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--text-color);
        }
        
        /* Atmospheric background */
        .bg-ornaments {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
            opacity: 0.85;
        }
        
        .bg-ornaments .blob {
            position: absolute;
            width: 420px;
            height: 420px;
            filter: blur(90px);
            opacity: 0.6;
            border-radius: 50%;
            animation: floatBlob 18s ease-in-out infinite;
            background: var(--gradient-primary);
        }
        
        .bg-ornaments .blob-1 {
            top: -120px;
            left: -150px;
        }
        
        .bg-ornaments .blob-2 {
            bottom: -140px;
            right: -120px;
            background: var(--gradient-accent);
            animation-delay: 4s;
        }
        
        .bg-ornaments .grid {
            position: absolute;
            inset: 10% 8%;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px),
                linear-gradient(180deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
            background-size: 120px 120px;
            filter: blur(0.8px);
            opacity: 0.35;
        }
        
        @keyframes floatBlob {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(18px) scale(1.04); }
        }
        
        /* Layout */
        .app-container {
            display: block;
            min-height: 100vh;
            position: relative;
            z-index: 1;
            isolation: isolate;
        }

        /* Top Navigation */
        .top-nav {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(0);
            width: min(1180px, calc(100% - 32px));
            background: rgba(255, 255, 255, 0.82);
            border-radius: 26px;
            padding: 14px 18px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            z-index: 1100;
            transition: transform 0.35s ease, opacity 0.3s ease;
        }

        [data-theme="dark"] .top-nav {
            background: rgba(17, 26, 46, 0.82);
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: var(--dark-shadow);
        }

        .top-nav.nav-hidden {
            transform: translateX(-50%) translateY(-140%);
            opacity: 0;
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-color);
        }

        .nav-brand .logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: var(--gradient-primary);
            color: #0b1220;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 12px 30px rgba(14, 165, 233, 0.35);
        }

        .nav-brand .logo-text h4 {
            color: var(--text-color);
            margin-bottom: 0;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .nav-brand .logo-text small {
            color: var(--text-secondary-color);
            font-size: 0.75rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-pill {
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid rgba(14, 165, 233, 0.22);
            background: rgba(255, 255, 255, 0.7);
            color: var(--text-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.01em;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .nav-pill {
            background: rgba(17, 26, 46, 0.6);
            border-color: rgba(34, 211, 238, 0.2);
            color: var(--dark-text);
        }

        .nav-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(14, 165, 233, 0.18);
            color: var(--text-color);
        }

        .nav-pill.active {
            background: var(--gradient-primary);
            color: #0b1220;
            border-color: transparent;
        }

        .nav-pill.logout {
            background: var(--gradient-accent);
            color: #1f2937;
            border-color: transparent;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.4);
            text-decoration: none;
            color: var(--text-color);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        [data-theme="dark"] .nav-profile {
            background: rgba(17, 26, 46, 0.72);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .nav-avatar {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            background: var(--gradient-primary);
            color: #0b1220;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }

        .nav-meta {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .nav-name {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .nav-role {
            font-size: 0.75rem;
            color: var(--text-secondary-color);
        }

        .nav-toggle {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(14, 165, 233, 0.22);
            background: rgba(255, 255, 255, 0.7);
            color: var(--text-color);
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        }

        [data-theme="dark"] .nav-toggle {
            background: rgba(17, 26, 46, 0.7);
            border-color: rgba(34, 211, 238, 0.2);
            color: var(--dark-text);
        }
        
        /* Sidebar - Professional Blue */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            transition: all 0.3s ease;
            z-index: 1050;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 0 25px 70px rgba(15, 23, 42, 0.28);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }
        
        .sidebar.collapsed .sidebar-header .logo-text,
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .user-info {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-header {
            padding: 20px 10px;
        }
        
        .sidebar.collapsed .nav-link {
            padding: 15px;
            justify-content: center;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.3rem;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }
        
        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: white;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0b1220;
            font-size: 20px;
            box-shadow: 0 12px 30px rgba(14, 165, 233, 0.45);
        }
        
        .logo-text h4 {
            color: white;
            margin-bottom: 0;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .logo-text small {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
        }
        
        .sidebar-toggle {
            position: absolute;
            right: -15px;
            top: 30px;
            background: var(--gradient-primary);
            border: 1px solid rgba(255, 255, 255, 0.35);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0b1220;
            cursor: pointer;
            box-shadow: 0 15px 35px rgba(14, 165, 233, 0.35);
            z-index: 1051;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            transform: scale(1.1);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu .nav-item {
            margin-bottom: 5px;
        }
        
        .sidebar-menu .nav-link {
            padding: 12px 20px;
            color: var(--sidebar-text);
            font-weight: 500;
            border-radius: 8px;
            margin: 0 10px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            position: relative;
            isolation: isolate;
            backdrop-filter: blur(4px);
        }
        
        .sidebar-menu .nav-link::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0) 60%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }
        
        .sidebar-menu .nav-link:hover {
            background-color: var(--sidebar-hover);
            color: white;
            border-left-color: rgba(255, 255, 255, 0.5);
        }
        
        .sidebar-menu .nav-link:hover::after {
            opacity: 1;
        }
        
        .sidebar-menu .nav-link.active {
            background-color: var(--sidebar-active);
            color: white;
            border-left-color: #f97316;
            font-weight: 600;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.18);
        }
        
        .sidebar-menu .nav-link.active::after {
            opacity: 1;
        }
        
        .sidebar-menu .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        .sidebar-menu .nav-link span {
            position: relative;
            z-index: 1;
        }
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: auto;
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.92) 0%, rgba(20, 184, 166, 0.9) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0b1220;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 10px 30px rgba(14, 165, 233, 0.35);
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: white;
        }
        
        .user-role {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 0;
            padding: 140px 28px 28px;
            transition: all 0.3s ease;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }
        
        /* Top Bar */
        .top-bar {
            background: var(--topbar-bg);
            border-radius: 16px;
            padding: 18px 28px;
            margin-bottom: 26px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        
        [data-theme="dark"] .top-bar {
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: var(--dark-shadow);
        }
        
        .top-bar .page-title h3 {
            margin-bottom: 5px;
            color: var(--text-color);
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.015em;
        }
        
        .top-bar .page-title p {
            color: var(--text-secondary-color);
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .date-time-display {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border-radius: 8px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Space Grotesk', monospace;
            color: white;
            font-weight: 600;
            letter-spacing: 0.05em;
            box-shadow: 0 14px 35px rgba(14, 165, 233, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        
        .theme-toggle {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(14, 165, 233, 0.25);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        
        .theme-toggle:hover {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .mobile-menu-toggle {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
            transition: all 0.3s ease;
        }
        
        /* Cards */
        .dashboard-card {
            background-color: var(--card-color);
            border-radius: 16px;
            padding: 26px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.28);
            height: 100%;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            isolation: isolate;
        }
        
        .dashboard-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 26px 60px rgba(15, 23, 42, 0.15);
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-blue), var(--accent-gold));
            opacity: 0.85;
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.8rem;
            color: white;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
        }
        
        .card-icon.gold {
            background: linear-gradient(135deg, var(--accent-gold) 0%, #d97706 100%);
        }
        
        .card-icon.green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .card-icon.purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }
        
        .welcome-card {
            background: var(--gradient-primary);
            color: white;
            border: none;
            box-shadow: 0 26px 60px rgba(14, 165, 233, 0.32);
        }
        
        .welcome-card::before {
            display: none;
        }
        
        .welcome-card h3, .welcome-card p {
            color: white !important;
        }
        
        /* Stats Cards */
        .stats-card {
            border-left: 4px solid var(--primary-blue);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.09), rgba(255, 255, 255, 0));
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 40px rgba(14, 165, 233, 0.15);
        }
        
        /* Schedule Cards */
        .schedule-card {
            background-color: var(--card-color);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            transition: all 0.3s ease;
            border-left: 4px solid var(--accent-gold);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        
        .schedule-card:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 12px 32px rgba(14, 165, 233, 0.18);
            transform: translateY(-3px);
        }
        
        .subject-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
        }
        
        .subject-info {
            color: var(--text-secondary-color);
            font-size: 0.9rem;
            margin-bottom: 12px;
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border: 1px solid rgba(255, 255, 255, 0.24);
            color: #0b1220;
            font-weight: 700;
            padding: 10px 22px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            letter-spacing: 0.01em;
            transition: all 0.3s ease;
            box-shadow: 0 16px 40px rgba(14, 165, 233, 0.25);
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 46px rgba(14, 165, 233, 0.3);
            color: #0b1220;
        }
        
        .btn-success-custom {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: white;
            font-weight: 700;
            padding: 9px 18px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 16px 36px rgba(16, 185, 129, 0.25);
        }
        
        .btn-success-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 44px rgba(16, 185, 129, 0.35);
            color: white;
        }
        
        .btn-warning-custom {
            background: linear-gradient(135deg, var(--accent-gold) 0%, #d97706 100%);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: white;
            font-weight: 700;
            padding: 9px 18px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 16px 40px rgba(245, 158, 11, 0.25);
        }
        
        .btn-warning-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 46px rgba(245, 158, 11, 0.35);
            color: white;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-present {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-absent {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .status-late {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .status-sick {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .status-permission {
            background: rgba(168, 85, 247, 0.15);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.3);
        }
        
        /* Tables */
        .data-table-container {
            background-color: var(--card-color);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.26);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            isolation: isolate;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--card-color);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        /* Dark Mode untuk Tabel dan Form */
        [data-theme="dark"] .table {
            color: var(--dark-text);
            background-color: var(--dark-card);
            border-color: var(--dark-border);
        }
        
        [data-theme="dark"] .table-bordered {
            border: 1px solid var(--dark-border);
        }
        
        [data-theme="dark"] .table-bordered th,
        [data-theme="dark"] .table-bordered td {
            border: 1px solid var(--dark-border);
        }
        
        [data-theme="dark"] .table-hover tbody tr:hover {
            background-color: rgba(26, 86, 219, 0.15);
            color: var(--dark-text);
        }
        
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: var(--dark-border);
            color: var(--dark-text);
        }
        
        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-blue);
            color: var(--dark-text);
            box-shadow: 0 0 0 0.25rem rgba(26, 86, 219, 0.25);
        }
        
        /* Chart Container */
        .chart-container {
            background-color: var(--card-color);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        /* Attendance Summary */
        .attendance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .summary-card {
            background-color: var(--card-color);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.22);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.14);
        }
        
        [data-theme="dark"] .dashboard-card,
        [data-theme="dark"] .data-table-container,
        [data-theme="dark"] .filter-section,
        [data-theme="dark"] .chart-container,
        [data-theme="dark"] .schedule-card,
        [data-theme="dark"] .summary-card {
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: var(--dark-shadow);
        }
        
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-size: 0.9rem;
            color: var(--text-secondary-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .main-content {
                padding: 130px 18px 18px;
            }
            
            .top-nav {
                top: 14px;
                width: calc(100% - 24px);
                padding: 12px 14px;
                border-radius: 22px;
            }

            .nav-inner {
                flex-wrap: wrap;
            }

            .nav-toggle {
                display: inline-flex;
            }

            .nav-links {
                width: 100%;
                order: 3;
                display: none;
                padding-top: 12px;
                flex-direction: column;
                align-items: flex-start;
            }

            .top-nav.nav-open .nav-links {
                display: flex;
            }

            .top-bar {
                padding: 15px 20px;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .top-bar-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .attendance-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .attendance-summary {
                grid-template-columns: 1fr;
            }

            .nav-profile {
                width: 100%;
                justify-content: space-between;
            }

            .nav-right {
                width: 100%;
                justify-content: space-between;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--light-blue);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-blue);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #1e40af;
        }
    </style>
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
                        include 'sections/guru_jadwal.php';
                        break;
                    case 'absensi':
                        include 'sections/guru_absensi.php';
                        break;
                    case 'laporan':
                        include 'sections/guru_laporan.php';
                        break;
                    case 'profil':
                        include 'sections/guru_profil.php';
                        break;
                    default: // dashboard
                        include 'sections/guru_dashboard.php';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
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
        // Initialize DataTables with export buttons
        if ($('.data-table-export').length) {
            $('.data-table-export').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                "pageLength": 10,
                "responsive": true,
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
                "responsive": true
            });
        }
        
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
            const currentTheme = html.getAttribute('data-theme');
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

        updateGuruBranding(document.documentElement.getAttribute('data-theme') || '<?php echo $theme; ?>');
        
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
