<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/face_matcher.php';

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

require_once '../includes/database.php';
$db = new Database();

// Get student info
$student_id = $_SESSION['student_id'];
$sql = "SELECT s.*, c.class_name, j.name as jurusan_name 
        FROM student s 
        LEFT JOIN class c ON s.class_id = c.class_id 
        LEFT JOIN jurusan j ON s.jurusan_id = j.jurusan_id 
        WHERE s.id = ?";
$stmt = $db->query($sql, [$student_id]);
$student = $stmt->fetch();

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
    $referencePath = $faceMatcher->getReferencePath($student['student_nisn']);
    if ($referencePath) {
        $profileImageUrl = $referencePath;
    }
}
if (!$profileImageUrl) {
    $profileImageUrl = '../assets/images/presenova.png';
}

// Get page parameter for navigation
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Get today's schedule for dashboard
$today = date('Y-m-d');
$day_of_week = date('N'); // 1=Monday, 7=Sunday

// Check if theme is set in cookie, otherwise default to light
$theme = isset($_COOKIE['siswa_theme']) ? $_COOKIE['siswa_theme'] : 'light';
$siswa_touch_icon = '../assets/images/apple-touch-icon_student.png?v=20260212c';
$siswa_icon_light = '../assets/images/favicon-32x32_student.png?v=20260212c';
$siswa_icon_dark = '../assets/images/favicon-32x32_student.png?v=20260212c';
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
            const savedTheme = document.cookie.match(/siswa_theme=([^;]+)/);
            const theme = savedTheme ? savedTheme[1] : 'light';
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
    
    <style>
        :root {
            --primary-blue: #3579f6;
            --secondary-blue: #5b6b8a;
            --accent-warm: #ff9f6b;
            --accent-soft: #ffd1b0;
            --accent-cool: #7dc7ff;
            --light-blue: #eef2ff;
            --white: #ffffff;
            --text-primary: #1b2a4a;
            --text-secondary: #5b6b8a;
            --border-color: rgba(255, 255, 255, 0.22);
            --glass-bg: rgba(255, 255, 255, 0.55);
            --glass-bg-strong: rgba(255, 255, 255, 0.72);
            --glass-border: rgba(255, 255, 255, 0.45);
            --glass-highlight: rgba(255, 255, 255, 0.2);
            --glass-hover: rgba(255, 255, 255, 0.25);
            --shell-bg: rgba(255, 255, 255, 0.55);
            --shell-border: rgba(255, 255, 255, 0.5);
            --glass-shadow: 0 18px 40px rgba(31, 41, 55, 0.16);
            --glass-shadow-strong: 0 28px 60px rgba(31, 41, 55, 0.22);
            --card-shadow: var(--glass-shadow);
            --blur: 26px;
            --bg-gradient: none;
            --sidebar-width: 280px;
            --transition: all 0.3s ease;
            --shell-gap: 18px;
            --shell-radius: 26px;

            /* Manual size controls (top bar + toggle curve) */
            --siswa-topbar-width: 98%;
            --siswa-topbar-min-height: 74px;
            --siswa-topbar-padding-y: 14px;
            --siswa-topbar-padding-x: 24px;
            --siswa-topbar-radius: 16px;
            --orbit-start-x: 34px;
            --orbit-start-y: 14px;
            --orbit-control-x: 76px;
            --orbit-control-y: 6px;
            --orbit-end-x: 92px;
            --orbit-end-y: 40px;
            --orbit-offset-x: 4px;
            --orbit-offset-y: -6px;
            --orbit-path: path("M 34 14 Q 76 6 92 40");
            --orbit-duration: 0.38s;

            /* Dark Theme Variables */
            --dark-bg: #0f141c;
            --dark-card: rgba(18, 24, 38, 0.62);
            --dark-border: rgba(148, 163, 184, 0.18);
            --dark-text: #e5e7eb;
            --dark-text-secondary: rgba(229, 231, 235, 0.68);

            --font-heading: 'Sora', sans-serif;
            --font-body: 'Manrope', sans-serif;
        }
        
        /* Theme Variables */
        [data-theme="light"] {
            --bg-color: #f7f8fc;
            --bg-gradient: radial-gradient(circle at 15% 12%, rgba(255, 220, 200, 0.35) 0%, rgba(255, 220, 200, 0) 48%),
                           radial-gradient(circle at 82% 8%, rgba(163, 209, 255, 0.28) 0%, rgba(163, 209, 255, 0) 45%),
                           radial-gradient(circle at 35% 82%, rgba(200, 230, 255, 0.25) 0%, rgba(200, 230, 255, 0) 45%);
            --card-color: var(--glass-bg);
            --text-color: #1f2937;
            --text-secondary-color: #52607a;
            --border: var(--glass-border);
            --sidebar-bg: linear-gradient(160deg, rgba(255, 255, 255, 0.72) 0%, rgba(255, 255, 255, 0.45) 100%);
            --sidebar-text: #1f2937;
            --sidebar-text-muted: rgba(31, 41, 55, 0.6);
            --sidebar-hover: rgba(53, 121, 246, 0.12);
            --sidebar-active: rgba(53, 121, 246, 0.2);
            --topbar-bg: rgba(255, 255, 255, 0.6);
            --topbar-text: #1f2937;
            --nav-shadow: inset 1px 1px 2px rgba(255, 255, 255, 0.45),
                          inset -1px -1px 2px rgba(0, 0, 0, 0.08);
            --nav-shell-bg: rgba(255, 255, 255, 0.35);
            --nav-surface: rgba(255, 255, 255, 0.6);
            --shell-bg: rgba(255, 255, 255, 0.6);
            --liquid-row-bg: rgba(255, 255, 255, 0.55);
            --liquid-row-hover: rgba(255, 255, 255, 0.75);
            --liquid-head-bg: rgba(255, 255, 255, 0.55);
            --field-bg: rgba(255, 255, 255, 0.7);
            --field-bg-focus: rgba(255, 255, 255, 0.85);
            --field-border: rgba(31, 41, 55, 0.14);
            --field-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
            --nav-item-bg: rgba(255, 255, 255, 0.55);
            --nav-item-hover: rgba(255, 255, 255, 0.75);
            --nav-item-active: rgba(53, 121, 246, 0.18);
            --nav-item-border: rgba(53, 121, 246, 0.12);
        }
        
        [data-theme="dark"] {
            --bg-color: var(--dark-bg);
            --bg-gradient: radial-gradient(circle at 18% 8%, rgba(105, 160, 255, 0.18) 0%, rgba(105, 160, 255, 0) 46%),
                           radial-gradient(circle at 78% 10%, rgba(255, 159, 107, 0.14) 0%, rgba(255, 159, 107, 0) 40%),
                           radial-gradient(circle at 30% 85%, rgba(80, 140, 240, 0.12) 0%, rgba(80, 140, 240, 0) 40%);
            --card-color: var(--dark-card);
            --text-color: var(--dark-text);
            --text-secondary-color: var(--dark-text-secondary);
            --border: var(--dark-border);
            --sidebar-bg: linear-gradient(160deg, rgba(18, 24, 38, 0.85) 0%, rgba(22, 30, 46, 0.7) 100%);
            --sidebar-text: rgba(255, 255, 255, 0.92);
            --sidebar-text-muted: rgba(255, 255, 255, 0.68);
            --sidebar-hover: rgba(99, 165, 255, 0.18);
            --sidebar-active: rgba(99, 165, 255, 0.28);
            --topbar-bg: rgba(18, 24, 38, 0.62);
            --topbar-text: var(--dark-text);
            --glass-border: rgba(148, 163, 184, 0.18);
            --nav-shadow: inset 1px 1px 2px rgba(255, 255, 255, 0.08),
                          inset -1px -1px 3px rgba(0, 0, 0, 0.35);
            --nav-shell-bg: rgba(14, 20, 32, 0.68);
            --nav-surface: rgba(22, 30, 46, 0.78);
            --glass-bg: rgba(18, 24, 38, 0.6);
            --glass-bg-strong: rgba(18, 24, 38, 0.72);
            --glass-border: rgba(148, 163, 184, 0.22);
            --glass-shadow: 0 18px 40px rgba(0, 0, 0, 0.38);
            --glass-shadow-strong: 0 30px 60px rgba(0, 0, 0, 0.48);
            --shell-bg: rgba(18, 24, 38, 0.6);
            --dark-card: var(--glass-bg);
            --dark-border: rgba(148, 163, 184, 0.22);
            --liquid-row-bg: rgba(18, 24, 38, 0.6);
            --liquid-row-hover: rgba(18, 24, 38, 0.8);
            --liquid-head-bg: rgba(18, 24, 38, 0.65);
            --field-bg: rgba(18, 24, 38, 0.55);
            --field-bg-focus: rgba(18, 24, 38, 0.72);
            --field-border: rgba(148, 163, 184, 0.25);
            --field-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25);
            --nav-item-bg: rgba(18, 24, 38, 0.6);
            --nav-item-hover: rgba(18, 24, 38, 0.78);
            --nav-item-active: rgba(99, 165, 255, 0.22);
            --nav-item-border: rgba(99, 165, 255, 0.2);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        body {
            font-family: var(--font-body);
            background-color: var(--bg-color);
            background-image: var(--bg-gradient);
            background-attachment: fixed;
            color: var(--text-color);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: url("../assets/images/presenova.png") center/cover no-repeat;
            opacity: 0.22;
            filter: blur(22px) saturate(120%);
            transform: scale(1.08);
            z-index: 0;
            pointer-events: none;
        }

        [data-theme="dark"] body::before {
            opacity: 0.16;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-heading);
            font-weight: 600;
            color: var(--text-color);
            letter-spacing: -0.2px;
        }

        ::selection {
            background: rgba(53, 121, 246, 0.25);
        }

        .bg-orbs {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }

        .bg-orbs .orb {
            position: absolute;
            border-radius: 50%;
            opacity: 0.7;
            filter: blur(0px);
            animation: float 16s ease-in-out infinite;
        }

        .bg-orbs .orb-1 {
            width: 420px;
            height: 420px;
            right: -120px;
            top: -140px;
            background: radial-gradient(circle at 30% 30%, rgba(255, 182, 140, 0.8), rgba(255, 182, 140, 0));
            animation-duration: 18s;
        }

        .bg-orbs .orb-2 {
            width: 360px;
            height: 360px;
            left: -160px;
            top: 30%;
            background: radial-gradient(circle at 40% 40%, rgba(126, 199, 255, 0.7), rgba(126, 199, 255, 0));
            animation-duration: 20s;
        }

        .bg-orbs .orb-3 {
            width: 300px;
            height: 300px;
            right: 10%;
            bottom: -160px;
            background: radial-gradient(circle at 50% 50%, rgba(165, 214, 255, 0.65), rgba(165, 214, 255, 0));
            animation-duration: 22s;
        }

        @keyframes float {
            0% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(18px) translateX(-10px); }
            100% { transform: translateY(0) translateX(0); }
        }
        
        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            z-index: 1;
            gap: var(--shell-gap);
        }

        .student-shell {
            position: relative;
            min-height: 100vh;
            width: 100%;
            padding: var(--shell-gap);
        }

        .student-shell::before {
            content: "";
            position: absolute;
            inset: var(--shell-gap);
            border-radius: var(--shell-radius);
            background: var(--shell-bg);
            border: 1px solid var(--shell-border);
            box-shadow: var(--glass-shadow-strong);
            backdrop-filter: blur(calc(var(--blur) + 6px)) saturate(160%);
            -webkit-backdrop-filter: blur(calc(var(--blur) + 6px)) saturate(160%);
            z-index: 0;
            pointer-events: none;
        }

        [data-theme="dark"] .student-shell::before {
            background: var(--shell-bg);
            border-color: var(--shell-border);
        }

        .nav-shell {
            position: fixed;
            top: var(--shell-gap);
            left: var(--shell-gap);
            bottom: var(--shell-gap);
            width: var(--sidebar-width);
            padding: 10px;
            border-radius: var(--shell-radius);
            background: var(--nav-shell-bg);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow-strong);
            backdrop-filter: blur(calc(var(--blur) + 6px)) saturate(170%);
            -webkit-backdrop-filter: blur(calc(var(--blur) + 6px)) saturate(170%);
            transition: all 0.3s ease;
            z-index: 1050;
        }

        [data-theme="dark"] .nav-shell {
            background: rgb(26 26 26 / 65%);
            border-color: rgba(148, 163, 184, 0.18);
        }

        
        /* Sidebar - Blue Gradient */
        .sidebar {
            width: 100%;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            transition: all 0.3s ease;
            z-index: 1050;
            position: relative;
            height: 100%;
            overflow-y: auto;
            overflow-x: visible;
            box-shadow: var(--glass-shadow);
            border-right: 1px solid var(--border);
            backdrop-filter: blur(var(--blur)) saturate(140%);
            -webkit-backdrop-filter: blur(var(--blur)) saturate(140%);
            border-radius: calc(var(--shell-radius) - 8px);
        }
        
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid var(--border);
            position: relative;
        }
        
        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--sidebar-text);
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 6px 18px rgba(53, 121, 246, 0.25);
            padding: 6px;
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(12px) saturate(140%);
            -webkit-backdrop-filter: blur(12px) saturate(140%);
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 6px;
        }

        .logo-icon.profile-photo {
            border-radius: 50%;
            padding: 0;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.18);
        }

        .logo-icon.profile-photo img {
            border-radius: 50%;
            object-fit: cover;
        }

        .logo-icon.profile-photo .logo-initial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #e7edf8;
            font-size: 1rem;
        }

        [data-theme="dark"] .logo-icon {
            background: rgba(255, 255, 255, 0.18);
        }
        
        .logo-text h4 {
            color: var(--sidebar-text);
            margin-bottom: 0;
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .logo-text small {
            color: var(--sidebar-text-muted);
            font-size: 0.8rem;
        }
        
        .sidebar-toggle {
            position: absolute;
            right: -15px;
            top: 30px;
            background: var(--glass-bg-strong);
            border: 1px solid var(--border);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            cursor: pointer;
            box-shadow: var(--glass-shadow);
            z-index: 1051;
            transition: all 0.3s ease;
            backdrop-filter: blur(var(--blur));
        }
        
        .sidebar-toggle:hover {
            transform: scale(1.1);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu .nav-item {
            margin-bottom: 10px;
            position: relative;
        }
        
        .sidebar-menu .nav-link {
            padding: 12px 20px;
            color: var(--sidebar-text);
            font-weight: 500;
            border-radius: 18px;
            margin: 0 10px;
            border: 1px solid var(--nav-item-border);
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            background: var(--nav-item-bg);
            position: relative;
            overflow: visible;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .sidebar-menu .nav-item:hover .nav-link,
        .sidebar-menu .nav-item.active .nav-link {
            background-color: var(--nav-item-hover);
            color: var(--sidebar-text);
            border-left-color: rgba(53, 121, 246, 0.5);
            box-shadow: var(--nav-shadow);
        }

        .sidebar-menu .nav-item.active .nav-link {
            border-left-color: var(--primary-blue);
            font-weight: 600;
            z-index: 2;
            background: linear-gradient(135deg, var(--nav-item-active), var(--nav-item-hover));
        }
        
        .sidebar-menu .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Fluid nav highlight removed per request */
        
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--border);
            margin-top: auto;
            position: absolute;
            bottom: 0;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 14px;
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
            background: linear-gradient(135deg, rgba(53, 121, 246, 0.65) 0%, rgba(125, 199, 255, 0.35) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 6px 16px rgba(53, 121, 246, 0.2);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--sidebar-text);
        }
        
        .user-role {
            font-size: 0.8rem;
            color: var(--sidebar-text-muted);
        }

        .sidebar-logout {
            display: flex;
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 14px;
            border-radius: 14px;
            text-decoration: none;
            color: var(--sidebar-text);
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            box-shadow: inset 1px 1px 2px rgba(255, 255, 255, 0.08),
                        inset -1px -1px 2px rgba(0, 0, 0, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        [data-theme="dark"] .logout-link {
            background: var(--glass-bg);
            border-color: var(--glass-border);
            box-shadow: inset 1px 1px 2px rgba(255, 255, 255, 0.06),
                        inset -1px -1px 3px rgba(0, 0, 0, 0.4);
        }

        .logout-link:hover {
            transform: translateY(-2px);
            box-shadow: var(--glass-shadow);
        }

        .logout-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(53, 121, 246, 0.15);
            color: var(--primary-blue);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: calc(var(--sidebar-width) + (var(--shell-gap) * 2));
            padding: 24px 24px 28px;
            transition: all 0.3s ease;
            min-height: 100vh;
        }
        
        
        /* Top Bar */
        .top-bar {
            background-color: var(--topbar-bg);
            border-radius: var(--siswa-topbar-radius);
            width: var(--siswa-topbar-width);
            min-height: var(--siswa-topbar-min-height);
            padding: var(--siswa-topbar-padding-y) var(--siswa-topbar-padding-x);
            margin: 17px 0 25px 0;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border);
            backdrop-filter: blur(var(--blur)) saturate(140%);
            -webkit-backdrop-filter: blur(var(--blur)) saturate(140%);
            position: relative;
            overflow: visible;
        }
        
        .top-bar .page-title h3 {
            margin-bottom: 5px;
            color: var(--text-color);
            font-size: 1.4rem;
            font-weight: 700;
        }
        
        .top-bar .page-title p {
            color: var(--text-secondary-color);
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 18px;
            position: relative;
        }

        #enablePushBtn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--field-bg);
            color: var(--text-color);
            font-weight: 600;
            font-size: 0.85rem;
            box-shadow: var(--nav-shadow);
            transition: var(--transition);
        }

        #enablePushBtn i {
            font-size: 0.9rem;
        }

        #enablePushBtn:hover {
            transform: translateY(-1px);
            box-shadow: var(--glass-shadow);
        }

        #enablePushBtn[data-state="enabled"] {
            background: rgba(16, 185, 129, 0.18);
            border-color: rgba(16, 185, 129, 0.4);
            color: #10b981;
        }

        #enablePushBtn[data-state="blocked"],
        #enablePushBtn[data-state="unsupported"] {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(239, 68, 68, 0.4);
            color: #ef4444;
        }

        .time-orbit-wrap {
            position: relative;
            display: flex;
            align-items: center;
            padding-right: 54px;
            gap: 10px;
            flex-wrap: nowrap;
        }

        .theme-orbit {
            position: absolute;
            right: -71px;
            top: -48px;
            transform: translateX(-10px);
            z-index: 6;
            pointer-events: auto;
        }
        
        .date-time-display {
            background: linear-gradient(135deg, rgba(53, 121, 246, 0.95) 0%, rgba(255, 159, 107, 0.9) 100%);
            border-radius: 12px;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-heading);
            color: white;
            font-weight: 600;
            box-shadow: 0 8px 20px rgba(53, 121, 246, 0.25);
        }
        
        .theme-toggle {
            --orbit-thumb-size: 36px;
            --orbit-x: var(--orbit-start-x);
            --orbit-y: var(--orbit-start-y);
            --orbit-distance: 0%;
            --orbit-scale: 1;
            --orbit-thumb-offset-x: var(--orbit-offset-x);
            --orbit-thumb-offset-y: var(--orbit-offset-y);
            width: 104px;
            height: 64px;
            border: none;
            background: transparent;
            padding: 0;
            margin: 0;
            cursor: pointer;
            position: relative;
            overflow: visible;
            display: block;
            transition: transform var(--orbit-duration) ease, filter var(--orbit-duration) ease;
        }

        [data-theme="dark"] .theme-toggle {
            --orbit-distance: 100%;
            --orbit-x: var(--orbit-end-x);
            --orbit-y: var(--orbit-end-y);
        }

        .theme-toggle:focus-visible {
            outline: 2px solid var(--primary-blue);
            outline-offset: 6px;
            border-radius: 999px;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            filter: drop-shadow(0 12px 20px rgba(15, 23, 42, 0.18));
        }

        .theme-toggle:active {
            --orbit-scale: 0.96;
        }

        .theme-orbit-curve {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .theme-orbit-curve .orbit-path,
        .theme-orbit-curve .orbit-glow {
            fill: none;
            stroke-linecap: round;
            transition: opacity var(--orbit-duration) ease;
        }

        .theme-orbit-curve .orbit-path {
            stroke-width: 8;
            opacity: 0.75;
        }

        .theme-orbit-curve .orbit-glow {
            stroke-width: 14;
            opacity: 0.3;
            filter: blur(6px);
        }

        .theme-orbit-curve .orbit-path-dark,
        .theme-orbit-curve .orbit-glow-dark {
            opacity: 0;
        }

        [data-theme="dark"] .theme-orbit-curve .orbit-path-light,
        [data-theme="dark"] .theme-orbit-curve .orbit-glow-light {
            opacity: 0;
        }

        [data-theme="dark"] .theme-orbit-curve .orbit-path-dark {
            opacity: 0.8;
        }

        [data-theme="dark"] .theme-orbit-curve .orbit-glow-dark {
            opacity: 0.4;
        }

        .theme-orbit-thumb {
            position: absolute;
            width: var(--orbit-thumb-size);
            height: var(--orbit-thumb-size);
            border-radius: 50%;
            background: radial-gradient(circle at 35% 30%, #ffffff 0%, #e8eefb 45%, rgba(255, 176, 130, 0.95) 100%);
            box-shadow: 0 12px 24px rgba(53, 121, 246, 0.25), 0 8px 18px rgba(255, 159, 107, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transform: translate(calc(var(--orbit-x) - (var(--orbit-thumb-size) / 2) + var(--orbit-thumb-offset-x)), calc(var(--orbit-y) - (var(--orbit-thumb-size) / 2) + var(--orbit-thumb-offset-y))) scale(var(--orbit-scale));
            transition: transform var(--orbit-duration) cubic-bezier(0.22, 0.8, 0.25, 1),
                        box-shadow var(--orbit-duration) ease,
                        background var(--orbit-duration) ease;
            will-change: transform;
        }

        .theme-orbit-thumb::after {
            content: "";
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 190, 150, 0.55), rgba(255, 190, 150, 0));
            filter: blur(10px);
            opacity: 0.7;
            z-index: -1;
            transition: opacity 0.6s ease, background 0.6s ease;
        }

        .theme-orbit-icon {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--glass-bg-strong);
            box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.12),
                        inset 0 -2px 4px rgba(0, 0, 0, 0.25);
        }

        .theme-orbit-icon i {
            font-size: 0.95rem;
            color: var(--text-color);
            transition: transform var(--orbit-duration) ease, color var(--orbit-duration) ease;
        }

        [data-theme="dark"] .theme-orbit-thumb {
            background: radial-gradient(circle at 35% 30%, #f8fafc 0%, #cbd5f5 45%, rgba(153, 80, 240, 0.95) 100%);
            box-shadow: 0 14px 26px rgba(9, 10, 22, 0.6), 0 0 16px rgba(153, 80, 240, 0.55);
        }

        [data-theme="dark"] .theme-orbit-thumb::after {
            background: radial-gradient(circle, rgba(151, 112, 255, 0.7), rgba(151, 112, 255, 0));
            opacity: 0.9;
        }

        [data-theme="dark"] .theme-orbit-icon {
            background: var(--glass-bg-strong);
        }

        [data-theme="dark"] .theme-orbit-icon i {
            color: var(--text-color);
            transform: rotate(180deg);
        }

        @supports (offset-path: path("M 0 0 L 1 1")) {
            .theme-orbit-thumb {
                left: 0;
                top: 0;
                offset-path: var(--orbit-path);
                offset-distance: var(--orbit-distance);
                offset-rotate: 0deg;
                offset-anchor: 50% 50%;
                transform: translate(var(--orbit-thumb-offset-x), var(--orbit-thumb-offset-y)) scale(var(--orbit-scale));
                transition: offset-distance var(--orbit-duration) cubic-bezier(0.22, 0.8, 0.25, 1),
                            transform var(--orbit-duration) ease,
                            box-shadow var(--orbit-duration) ease,
                            background var(--orbit-duration) ease;
                will-change: offset-distance, transform;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .theme-toggle,
            .theme-orbit-thumb,
            .theme-orbit-icon i,
            .theme-orbit-curve .orbit-path,
            .theme-orbit-curve .orbit-glow {
                transition: none !important;
            }
        }
        
        .mobile-menu-toggle {
            background: linear-gradient(135deg, rgba(53, 121, 246, 0.95) 0%, rgba(125, 199, 255, 0.95) 100%);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 8px 18px rgba(53, 121, 246, 0.25);
            transition: all 0.3s ease;
        }
        
        /* Cards */
        .dashboard-card {
            background-color: var(--card-color);
            border-radius: 18px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            height: 100%;
            backdrop-filter: blur(var(--blur)) saturate(140%);
            -webkit-backdrop-filter: blur(var(--blur)) saturate(140%);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--glass-shadow-strong);
        }

        .dashboard-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .dashboard-card:hover::before {
            opacity: 0.35;
        }

        .dashboard-card > * {
            position: relative;
            z-index: 1;
        }

        .profile-compact {
            height: auto;
            padding: 16px;
        }

        .profile-compact .mb-4 {
            margin-bottom: 16px !important;
        }

        .profile-compact .progress {
            margin-bottom: 12px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 1.5rem;
            color: white;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--accent-warm) 100%);
            box-shadow: 0 10px 20px rgba(53, 121, 246, 0.25);
        }
        
        .welcome-card {
            background: linear-gradient(135deg, rgba(53, 121, 246, 0.95) 0%, rgba(255, 159, 107, 0.9) 100%);
            color: white;
            border: none;
        }
        
        .welcome-card h3, .welcome-card p {
            color: white !important;
        }
        
        .attendance-card {
            background-color: var(--card-color);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            backdrop-filter: blur(var(--blur)) saturate(140%);
            -webkit-backdrop-filter: blur(var(--blur)) saturate(140%);
        }
        
        .attendance-card:hover {
            border-color: var(--primary-blue);
            box-shadow: var(--glass-shadow);
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
        
        /* Dark Mode untuk Tabel */
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
    background-color: rgba(44, 123, 229, 0.1);
    color: var(--dark-text);
}

[data-theme="dark"] .table-primary {
    --bs-table-bg: rgba(44, 123, 229, 0.2);
    --bs-table-striped-bg: rgba(44, 123, 229, 0.25);
    --bs-table-striped-color: #fff;
    --bs-table-active-bg: rgba(44, 123, 229, 0.3);
    --bs-table-active-color: #fff;
    --bs-table-hover-bg: rgba(44, 123, 229, 0.25);
    --bs-table-hover-color: #fff;
    color: #fff;
    border-color: rgba(44, 123, 229, 0.3);
}

[data-theme="dark"] .table-warning {
    --bs-table-bg: rgba(255, 193, 7, 0.2);
    --bs-table-striped-bg: rgba(255, 193, 7, 0.25);
    --bs-table-striped-color: #000;
    --bs-table-active-bg: rgba(255, 193, 7, 0.3);
    --bs-table-active-color: #000;
    --bs-table-hover-bg: rgba(255, 193, 7, 0.25);
    --bs-table-hover-color: #000;
    color: var(--dark-text);
    border-color: rgba(255, 193, 7, 0.3);
}

[data-theme="dark"] .list-group-item {
    background-color: var(--dark-card);
    border-color: var(--dark-border);
    color: var(--dark-text);
}

[data-theme="dark"] .list-group-item-success {
    background-color: rgba(25, 135, 84, 0.2);
    border-color: rgba(25, 135, 84, 0.3);
    color: #75b798;
}

[data-theme="dark"] .list-group-item-warning {
    background-color: rgba(255, 193, 7, 0.2);
    border-color: rgba(255, 193, 7, 0.3);
    color: #ffda6a;
}

[data-theme="dark"] .accordion-button {
    background-color: var(--dark-card);
    color: var(--dark-text);
    border-color: var(--dark-border);
}

[data-theme="dark"] .accordion-button:not(.collapsed) {
    background-color: rgba(44, 123, 229, 0.2);
    color: var(--dark-text);
}

[data-theme="dark"] .accordion-item {
    background-color: var(--dark-card);
    border-color: var(--dark-border);
}

[data-theme="dark"] .accordion-body {
    background-color: rgba(0, 0, 0, 0.1);
}

.modal-content {
    background-color: var(--card-color);
    color: var(--text-color);
    border: 1px solid var(--border);
    border-radius: 18px;
    box-shadow: var(--glass-shadow);
    backdrop-filter: blur(var(--blur)) saturate(140%);
    -webkit-backdrop-filter: blur(var(--blur)) saturate(140%);
}

.modal-header,
.modal-footer {
    border-color: var(--border);
}

[data-theme="dark"] .modal-content {
    background-color: var(--dark-card);
    color: var(--dark-text);
}

[data-theme="dark"] .modal-header {
    border-bottom-color: var(--dark-border);
}

[data-theme="dark"] .modal-footer {
    border-top-color: var(--dark-border);
}

[data-theme="dark"] .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

.modal-content {
    background-color: var(--glass-bg-strong);
    border: 1px solid var(--glass-border);
    backdrop-filter: blur(calc(var(--blur) + 4px)) saturate(160%);
    -webkit-backdrop-filter: blur(calc(var(--blur) + 4px)) saturate(160%);
}

.form-control,
.form-select {
    background-color: var(--field-bg);
    border-color: var(--field-border);
    color: var(--text-color);
    border-radius: 12px;
    box-shadow: var(--field-shadow);
}

.form-control:focus,
.form-select:focus {
    background-color: var(--field-bg-focus);
    border-color: rgba(53, 121, 246, 0.6);
    color: var(--text-color);
    box-shadow: 0 0 0 0.2rem rgba(53, 121, 246, 0.15), var(--field-shadow);
}

[data-theme="dark"] .form-control,
[data-theme="dark"] .form-select {
    background-color: var(--field-bg);
    border-color: var(--field-border);
    color: var(--text-color);
}

[data-theme="dark"] .form-control:focus,
[data-theme="dark"] .form-select:focus {
    background-color: var(--field-bg-focus);
    border-color: rgba(125, 199, 255, 0.6);
    color: var(--text-color);
    box-shadow: 0 0 0 0.2rem rgba(99, 165, 255, 0.2), var(--field-shadow);
}
        .btn-attendance {
            background: linear-gradient(135deg, rgba(53, 121, 246, 0.95) 0%, rgba(125, 199, 255, 0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 18px rgba(53, 121, 246, 0.25);
        }
        
        .btn-attendance:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 22px rgba(53, 121, 246, 0.35);
            color: white;
        }
        
        .btn-attendance:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .btn-primary {
            background: linear-gradient(135deg, rgba(53, 121, 246, 0.95) 0%, rgba(125, 199, 255, 0.95) 100%);
            border: none;
            box-shadow: 0 10px 20px rgba(53, 121, 246, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(53, 121, 246, 0.32);
        }

        .btn-outline-primary {
            border: 1px solid rgba(53, 121, 246, 0.4);
            color: var(--primary-blue);
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur));
            -webkit-backdrop-filter: blur(var(--blur));
        }

        [data-theme="dark"] .btn-outline-primary {
            background: var(--glass-bg);
            color: #cfe0ff;
            border-color: rgba(125, 199, 255, 0.35);
        }
        
        .attendance-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-present {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.3);
        }
        
        .status-absent {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.3);
        }
        
        .status-late {
            background: rgba(246, 173, 85, 0.1);
            color: #f6ad55;
            border: 1px solid rgba(246, 173, 85, 0.3);
        }
        
        /* Tables */
        .data-table-container {
            background-color: var(--card-color);
            border-radius: 18px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid var(--border);
            backdrop-filter: blur(var(--blur)) saturate(140%);
            -webkit-backdrop-filter: blur(var(--blur)) saturate(140%);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .data-table-container:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: var(--glass-shadow-strong);
        }

        .table {
            color: var(--text-color);
        }

        .table thead th {
            background: rgba(53, 121, 246, 0.08);
            border-bottom: 1px solid var(--border);
            color: var(--text-color);
        }

        .table thead.table-primary th {
            background: rgba(53, 121, 246, 0.16);
        }

        .table thead.table-warning th {
            background: rgba(255, 193, 7, 0.2);
        }

        .table thead.table-success th {
            background: rgba(25, 135, 84, 0.2);
        }

        .table tbody td,
        .table tbody th {
            border-color: var(--border);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(53, 121, 246, 0.08);
        }

        /* Liquid Glass Table Sections */
        .liquidGlass-wrapper {
            position: relative;
            display: block;
            overflow: hidden;
            border-radius: 22px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            background: var(--liquid-bg, var(--glass-bg));
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.6);
        }

        .liquidGlass-wrapper:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: var(--glass-shadow-strong);
        }

        .liquidGlass-wrapper:hover .liquidGlass-shine {
            box-shadow: inset 2px 2px 1px 0 rgba(255, 255, 255, 0.2),
                        inset -2px -2px 2px 1px rgba(255, 255, 255, 0.1);
        }

        .liquidGlass-wrapper:hover .table tbody tr {
            background-color: var(--glass-hover);
        }

        .liquidGlass-effect {
            position: absolute;
            z-index: 0;
            inset: 0;
            backdrop-filter: blur(12px) saturate(160%);
            -webkit-backdrop-filter: blur(12px) saturate(160%);
            filter: url(#glass-distortion);
            overflow: hidden;
            isolation: isolate;
        }

        .liquidGlass-tint {
            z-index: 1;
            position: absolute;
            inset: 0;
            background: var(--liquid-tint, rgba(255, 255, 255, 0.08));
        }

        .liquidGlass-shine {
            position: absolute;
            inset: 0;
            z-index: 2;
            overflow: hidden;
            box-shadow: inset 2px 2px 1px 0 rgba(255, 255, 255, 0.18),
                        inset -1px -1px 1px 1px rgba(255, 255, 255, 0.12);
        }

        .liquidGlass-content {
            position: relative;
            z-index: 3;
            padding: 14px;
        }

        .liquid-table .liquidGlass-content {
            padding: 12px;
        }

        .liquidGlass-wrapper .table {
            background: transparent;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .liquidGlass-wrapper .schedule-table {
            box-shadow: none;
            border: none;
        }

        .liquidGlass-wrapper .table tbody tr {
            transition: background-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
            background: var(--liquid-row-bg, var(--glass-bg-strong));
            box-shadow: 0 10px 20px rgba(8, 12, 24, 0.35);
        }

        .liquidGlass-wrapper .table tbody tr td {
            background: transparent;
            border-top: 1px solid var(--glass-border);
            border-bottom: 1px solid var(--glass-border);
        }

        .liquidGlass-wrapper .table tbody tr td:first-child {
            border-top-left-radius: 16px;
            border-bottom-left-radius: 16px;
            border-left: 1px solid var(--glass-border);
        }

        .liquidGlass-wrapper .table tbody tr td:last-child {
            border-top-right-radius: 16px;
            border-bottom-right-radius: 16px;
            border-right: 1px solid var(--glass-border);
        }

        .liquidGlass-wrapper .table tbody tr:hover {
            transform: translateY(-3px);
            background: var(--liquid-row-hover, var(--glass-hover));
            box-shadow: 0 14px 26px rgba(8, 12, 24, 0.45);
        }

        .liquidGlass-wrapper .table thead th {
            background: var(--liquid-head-bg, rgba(18, 24, 38, 0.55));
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border: none;
            color: var(--text-color);
            font-weight: 600;
            padding: 14px 16px;
        }

        .liquidGlass-wrapper .table thead tr {
            background: linear-gradient(135deg, rgba(198, 226, 255, 0.75), rgba(221, 240, 255, 0.45));
        }

        .liquidGlass-wrapper .table thead th:first-child {
            border-top-left-radius: 16px;
        }

        .liquidGlass-wrapper .table thead th:last-child {
            border-top-right-radius: 16px;
        }

        .liquidGlass-wrapper .table tbody td {
            padding: 14px 16px;
        }

        [data-theme="dark"] .liquidGlass-wrapper .table thead th {
            background: rgba(15, 23, 42, 0.6);
            color: #f8fafc;
        }

        [data-theme="dark"] .liquidGlass-wrapper .table thead tr {
            background: linear-gradient(135deg, rgba(26, 40, 64, 0.85), rgba(20, 30, 46, 0.6));
        }

        [data-theme="dark"] .liquidGlass-wrapper .table,
        [data-theme="dark"] .liquidGlass-wrapper .table tbody td {
            color: #f8fafc;
        }

        [data-theme="dark"] .liquidGlass-wrapper .table .text-muted {
            color: rgba(248, 250, 252, 0.7) !important;
        }

        [data-theme="dark"] .liquidGlass-wrapper .table tbody tr {
            background: rgb(47 61 82 / 75%);
            box-shadow: 0 10px 22px rgba(0, 0, 0, 0.4);
        }

        [data-theme="dark"] .liquidGlass-wrapper .table tbody tr td {
            border-top: 1px solid rgba(148, 163, 184, 0.16);
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
        }

        [data-theme="dark"] .liquidGlass-wrapper .table tbody tr td:first-child,
        [data-theme="dark"] .liquidGlass-wrapper .table tbody tr td:last-child {
            border-color: rgba(148, 163, 184, 0.2);
        }

        [data-theme="dark"] .liquidGlass-wrapper .table tbody tr:hover {
            background: rgb(40 65 101 / 75%);
            box-shadow: 0 14px 26px rgba(0, 0, 0, 0.5);
        }

        [data-theme="dark"] .liquidGlass-wrapper .table tbody tr:hover td {
            border-color: transparent;
        }

        .liquid-theme-sky {
            --liquid-bg: radial-gradient(circle at 20% 15%, rgb(255 255 255 / 35%), rgba(120, 180, 255, 0) 55%), linear-gradient(135deg, rgb(106 137 191 / 65%), rgb(114 147 212 / 45%));
            --liquid-tint: rgb(133 168 219 / 42%);
        }

        .liquid-theme-peach {
            --liquid-bg: radial-gradient(circle at 80% 20%, rgba(255, 170, 130, 0.35), rgba(255, 170, 130, 0) 55%),
                         linear-gradient(135deg, rgba(36, 28, 26, 0.7), rgba(22, 18, 20, 0.5));
            --liquid-tint: rgba(165, 90, 70, 0.2);
        }

        .liquid-theme-mint {
            --liquid-bg: radial-gradient(circle at 15% 25%, rgba(120, 210, 190, 0.35), rgba(120, 210, 190, 0) 55%),
                         linear-gradient(135deg, rgba(18, 34, 32, 0.72), rgba(14, 26, 24, 0.5));
            --liquid-tint: rgba(60, 120, 110, 0.2);
        }

        [data-theme="dark"] .liquidGlass-wrapper {
            border-color: rgb(255 255 255 / 8%);
            background: rgb(82 87 101 / 48%);
        }

        [data-theme="dark"] .liquidGlass-tint {
            background: rgba(12, 18, 33, 0.55);
        }

        [data-theme="dark"] .liquidGlass-shine {
            box-shadow: inset 1px 1px 1px 0 rgba(255, 255, 255, 0.12),
                        inset -1px -1px 2px 1px rgba(0, 0, 0, 0.4);
        }

        [data-theme="dark"] .liquid-theme-sky {
            --liquid-bg: radial-gradient(circle at 20% 15%, rgba(78, 120, 175, 0.35), rgba(78, 120, 175, 0) 55%),
                         linear-gradient(135deg, rgba(20, 32, 52, 0.75), rgba(18, 26, 40, 0.5));
            --liquid-tint: rgba(20, 32, 52, 0.55);
        }

        [data-theme="dark"] .liquid-theme-peach {
            --liquid-bg: radial-gradient(circle at 80% 20%, rgba(173, 110, 84, 0.35), rgba(173, 110, 84, 0) 55%),
                         linear-gradient(135deg, rgba(40, 28, 26, 0.7), rgba(20, 16, 20, 0.5));
            --liquid-tint: rgba(36, 26, 24, 0.6);
        }

        [data-theme="dark"] .liquid-theme-mint {
            --liquid-bg: radial-gradient(circle at 15% 25%, rgba(80, 138, 125, 0.35), rgba(80, 138, 125, 0) 55%),
                         linear-gradient(135deg, rgba(18, 34, 32, 0.75), rgba(15, 26, 24, 0.5));
            --liquid-tint: rgba(16, 30, 28, 0.6);
        }
        
        /* Dark Mode Fixes */
        [data-theme="dark"] table.dataTable {
            background-color: var(--dark-card) !important;
            color: var(--dark-text) !important;
        }
        
        [data-theme="dark"] table.dataTable thead th {
            background-color: rgba(44, 123, 229, 0.2) !important;
            color: #93c5fd !important;
        }
        
        [data-theme="dark"] table.dataTable tbody td {
            color: var(--dark-text) !important;
            background-color: var(--dark-card) !important;
        }
        
        [data-theme="dark"] .table {
            color: var(--dark-text);
        }
        
        [data-theme="dark"] .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.02);
        }
        
        [data-theme="dark"] .table-hover tbody tr:hover {
            background-color: rgba(44, 123, 229, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .nav-shell {
                transform: translateX(-120%);
                width: var(--sidebar-width);
                top: 12px;
                bottom: 12px;
                left: 12px;
            }
            
            .nav-shell.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 16px;
            }
            
            .top-bar {
                padding: 15px 20px;
            }
        }
        
        @media (max-width: 768px) {
            :root {
                --orbit-start-x: 18px;
                --orbit-start-y: 12px;
                --orbit-control-x: 44px;
                --orbit-control-y: 6px;
                --orbit-end-x: 60px;
                --orbit-end-y: 41px;
                --orbit-offset-x: 1px;
                --orbit-offset-y: -2px;
                --orbit-path: path("M 18 12 Q 44 6 60 41");
            }

            .top-bar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .top-bar-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
            }

            .time-orbit-wrap {
                padding-right: 0;
                width: auto;
                flex: 1;
                justify-content: flex-start;
                gap: 8px;
                align-items: center;
            }

            .theme-orbit {
                position: static;
                transform: none;
                display: flex;
                align-items: center;
                margin-left: -2px;
                margin-top: -1px;
                align-self: center;
            }

            .theme-toggle {
                --orbit-thumb-size: 30px;
                width: 76px;
                height: 48px;
            }

            [data-theme="dark"] .theme-toggle {
                --orbit-distance: 61%;
            }

            .theme-orbit-curve .orbit-path {
                stroke-width: 7;
            }

            .theme-orbit-curve .orbit-glow {
                stroke-width: 12;
                filter: blur(5px);
            }
        }

        @media (max-width: 992px) {
            .student-shell {
                padding: 12px;
            }

            .student-shell::before {
                inset: 12px;
                border-radius: 20px;
            }
        }

        @media (max-width: 576px) {
            :root {
                --orbit-start-x: 18px;
                --orbit-start-y: 12px;
                --orbit-control-x: 44px;
                --orbit-control-y: 6px;
                --orbit-end-x: 60px;
                --orbit-end-y: 41px;
                --orbit-offset-x: 0px;
                --orbit-offset-y: -1px;
                --orbit-path: path("M 18 12 Q 44 6 60 41");
            }

            .nav-shell {
                width: 90%;
                max-width: 320px;
            }

            .top-bar {
                padding: 14px;
            }

            .date-time-display {
                padding: 8px 12px;
                font-size: 0.85rem;
            }

            .liquidGlass-wrapper .table {
                border-spacing: 0 8px;
                font-size: 0.85rem;
            }

            .liquidGlass-wrapper .table thead th {
                font-size: 0.75rem;
            }

            .time-orbit-wrap {
                padding-right: 0;
                gap: 6px;
            }

            .theme-orbit {
                position: static;
                transform: none;
                display: flex;
                align-items: center;
                margin-left: -36px;
                margin-top: -26px;
                align-self: center;
            }

            #enablePushBtn span {
                display: none;
            }

            .theme-toggle {
                --orbit-thumb-size: 28px;
                width: 70px;
                height: 44px;
            }

            [data-theme="dark"] .theme-toggle {
                --orbit-distance: 61%;
            }

            .theme-orbit-curve .orbit-path {
                stroke-width: 6;
            }

            .theme-orbit-curve .orbit-glow {
                stroke-width: 10;
                filter: blur(4px);
            }
        }
        
        /* Profile Page Styles */
        .profile-card {
            background-color: var(--card-color);
            border-radius: 16px;
            padding: 22px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            backdrop-filter: blur(var(--blur)) saturate(140%);
            -webkit-backdrop-filter: blur(var(--blur)) saturate(140%);
        }

        .profile-card .student-info .form-label,
        .profile-card .student-info .text-muted {
            color: var(--text-secondary-color) !important;
            font-weight: 600;
            letter-spacing: 0.2px;
        }
        
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--glass-bg-strong);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 12px 24px rgba(31, 41, 55, 0.18);
            overflow: hidden;
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(18, 24, 38, 0.35);
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, rgba(53, 121, 246, 0.9) 0%, rgba(255, 159, 107, 0.9) 100%);
            border-radius: 6px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, rgba(53, 121, 246, 1) 0%, rgba(255, 159, 107, 1) 100%);
        }
    </style>
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
                        include 'sections/jadwal.php';
                        break;
                    case 'face_recognition':
                        include 'sections/face_recognition.php';
                        break;
                    case 'riwayat':
                        include 'sections/riwayat.php';
                        break;
                    case 'profil':
                        include 'sections/profil.php';
                        break;
                    default: // dashboard
                        include 'sections/dashboard_siswa.php';
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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="../assets/js/pwa.js"></script>
    
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
        updateThemeOrbitPath();

        // Initialize DataTables for history page
        if ($('.data-table').length) {
            $('.data-table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                "pageLength": 10,
                "responsive": true
            });
        }
        
        // Mobile menu toggle
        $('#mobileMenuToggle').click(function() {
            $('#navShell').toggleClass('show');
        });
        
        // Theme toggle
        $('#themeToggle').click(function() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
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

        updateSiswaBranding(document.documentElement.getAttribute('data-theme') || '<?php echo $theme; ?>');
        
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
    });
    </script>
</body>
</html>
