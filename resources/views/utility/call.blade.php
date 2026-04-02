<?php
// Build URLs from current request context so links stay in this app (not XAMPP root).
$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
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

$canonicalUrl = $requestPath !== '' ? $scheme . '://' . $host . $requestPath : ($siteUrl . 'call.php');
$fullurl = $requestPath !== '' ? $requestPath : ($basePrefix . '/call.php');
$trimmed = trim($fullurl, ".php");
$canonical = rtrim($trimmed, '/' . '/?');

$loginUrl = $appPath('login.php');
$logoutUrl = $appPath('logout.php');
$registerUrl = $appPath('register.php');
$adminDashboardUrl = $appPath('dashboard/admin.php');
$guruDashboardUrl = $appPath('dashboard/guru.php');
$siswaDashboardUrl = $appPath('dashboard/siswa.php');

// Set title halaman
$pageTitle = "call - presenova";

$sessionRole = (string) ($_SESSION['role'] ?? '');
$isAdminLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && $sessionRole === 'admin';
$isTeacherLoggedIn = isset($_SESSION['teacher_id']) && !empty($_SESSION['teacher_id']) && $sessionRole === 'guru';
$isStudentLoggedIn = isset($_SESSION['student_id']) && !empty($_SESSION['student_id']) && $sessionRole === 'siswa';
$isLoggedIn = $isAdminLoggedIn || $isTeacherLoggedIn || $isStudentLoggedIn;
$dashboardUrl = $loginUrl;
if ($isAdminLoggedIn) {
    $dashboardUrl = $adminDashboardUrl;
} elseif ($isTeacherLoggedIn) {
    $dashboardUrl = $guruDashboardUrl;
} elseif ($isStudentLoggedIn) {
    $dashboardUrl = $siswaDashboardUrl;
}

$registrationEmail = 'adm@presenova.my.id';
$registrationMailto = 'mailto:' . $registrationEmail;
$registrationSubject = rawurlencode('Permohonan Registrasi Siswa Baru - Presenova');
$registrationBody = rawurlencode(
    "Halo Admin Presenova,\n\n"
    . "Saya ingin mengajukan registrasi siswa baru.\n\n"
    . "Nama Lengkap:\nNISN:\nKelas:\nNo. HP:\n\n"
    . "Terima kasih."
);
$registrationMailtoDraft = $registrationMailto . '?subject=' . $registrationSubject . '&body=' . $registrationBody;
?>
<!DOCTYPE html>
<html lang="id-ID" xml:lang="id-ID">
<head>
    <!-- Meta Tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Presenova - Registrasi Akun Peserta Melalui Administrator">
    <meta name="keywords" content="registrasi presenova, absensi online, face recognition, sekolah digital">
    
    <!-- Canonical -->
    <meta content="index, follow" name="robots"/>
    <link href="<?php echo $siteUrl; ?>" rel="home"/>
    <link href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>" rel="canonical"/>
    
    <!-- Title -->
    <title><?php echo $pageTitle; ?></title>
    
    <!-- OG Tags -->
    <meta property="og:type" content="website"/>
    <meta property="og:title" content="call - presenova"/>
    <meta property="og:description" content="Halaman registrasi akun peserta Presenova melalui administrator"/>
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="og:site_name" content="Presenova"/>
    <meta property="og:image" content="<?php echo $siteUrl; ?>assets/images/presenova.png"/>
    <meta property="og:image:width" content="1200"/>
    <meta property="og:image:height" content="630"/>
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="call - presenova"/>
    <meta name="twitter:description" content="Halaman registrasi akun peserta Presenova melalui administrator"/>
    <meta name="twitter:image" content="<?php echo $siteUrl; ?>assets/images/presenova.png"/>
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0a0f1e" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Presenova">
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon-white background.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-16x16-white background.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-16x16-black background.png" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon-32x32.png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Rajdhani:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app-dialog.css">
    
    <style>
        :root {
            --neon-green: #00ff88;
            --neon-blue: #00d4ff;
            --neon-purple: #b47aff;
            --neon-pink: #ff00ff;
            --dark-bg: #0a0f1e;
            --dark-card: #0f1629;
            --dark-border: #1a2332;
            --text-primary: #ffffff;
            --text-secondary: #8b92a8;
            --gradient-neon: linear-gradient(135deg, #00ff88 0%, #00d4ff 100%);
            --gradient-purple: linear-gradient(135deg, #b47aff 0%, #ff00ff 100%);
            --glow-green: 0 0 20px rgba(0, 255, 136, 0.5);
            --glow-blue: 0 0 20px rgba(0, 212, 255, 0.5);
            --glow-purple: 0 0 20px rgba(180, 122, 255, 0.5);
            --glow-strong: 0 0 40px rgba(0, 255, 136, 0.8);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
        }

        /* ==================== BACKGROUND EFFECTS ==================== */
        .grid-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0, 255, 136, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 136, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -2;
            animation: grid-move 20s linear infinite;
        }

        @keyframes grid-move {
            0% { transform: translateY(0); }
            100% { transform: translateY(50px); }
        }

        /* Glowing Orbs */
        .glow-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            pointer-events: none;
            z-index: -1;
            animation-duration: 20s;
            animation-timing-function: ease-in-out;
            animation-iteration-count: infinite;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, var(--neon-green) 0%, transparent 70%);
            top: -20%;
            left: -10%;
            animation: float-orb-1 25s ease-in-out infinite;
        }

        .orb-2 {
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, var(--neon-blue) 0%, transparent 70%);
            top: 60%;
            right: -8%;
            animation: float-orb-2 22s ease-in-out infinite 2s;
        }

        .orb-3 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, var(--neon-purple) 0%, transparent 70%);
            bottom: -10%;
            left: 30%;
            animation: float-orb-3 28s ease-in-out infinite 4s;
        }

        @keyframes float-orb-1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(40px, -40px) scale(1.1); }
            50% { transform: translate(-30px, 30px) scale(0.9); }
            75% { transform: translate(30px, 40px) scale(1.05); }
        }

        @keyframes float-orb-2 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(-30px, 30px) rotate(90deg); }
            50% { transform: translate(20px, -20px) rotate(180deg); }
            75% { transform: translate(-20px, -30px) rotate(270deg); }
        }

        @keyframes float-orb-3 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(25px, -25px) scale(1.15); }
            50% { transform: translate(-35px, 15px) scale(0.85); }
            75% { transform: translate(15px, 35px) scale(1.05); }
        }

        /* ==================== ANIMATED BACKGROUND ELEMENTS ==================== */
        .animated-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .floating-gear {
            position: absolute;
            color: rgba(0, 255, 136, 0.1);
            font-size: 4rem;
            animation: rotate-gear 20s linear infinite;
            z-index: -1;
        }

        .floating-gear:nth-child(1) {
            top: 10%;
            left: 5%;
            color: rgba(0, 255, 136, 0.08);
            font-size: 6rem;
            animation-duration: 25s;
            animation-direction: reverse;
        }

        .floating-gear:nth-child(2) {
            top: 70%;
            right: 8%;
            color: rgba(0, 212, 255, 0.08);
            font-size: 5rem;
            animation-duration: 30s;
        }

        .floating-gear:nth-child(3) {
            bottom: 20%;
            left: 15%;
            color: rgba(180, 122, 255, 0.08);
            font-size: 4.5rem;
            animation-duration: 22s;
            animation-direction: reverse;
        }

        .floating-gear:nth-child(4) {
            top: 40%;
            right: 15%;
            color: rgba(255, 0, 255, 0.08);
            font-size: 7rem;
            animation-duration: 35s;
        }

        .floating-gear:nth-child(5) {
            top: 80%;
            left: 20%;
            color: rgba(0, 255, 136, 0.05);
            font-size: 3.5rem;
            animation-duration: 18s;
        }

        @keyframes rotate-gear {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .binary-code {
            position: absolute;
            color: rgba(0, 255, 136, 0.05);
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            white-space: nowrap;
            animation: scroll-binary 40s linear infinite;
            z-index: -1;
        }

        .binary-code:nth-child(6) {
            top: 15%;
            width: 200%;
            animation-duration: 50s;
        }

        .binary-code:nth-child(7) {
            top: 60%;
            width: 200%;
            animation-duration: 45s;
            animation-direction: reverse;
            color: rgba(0, 212, 255, 0.05);
        }

        .binary-code:nth-child(8) {
            top: 85%;
            width: 200%;
            animation-duration: 55s;
            color: rgba(180, 122, 255, 0.05);
        }

        @keyframes scroll-binary {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .pulse-ring {
            position: absolute;
            border: 2px solid rgba(0, 255, 136, 0.1);
            border-radius: 50%;
            animation: pulse-ring 4s ease-out infinite;
            z-index: -1;
        }

        .pulse-ring:nth-child(9) {
            top: 30%;
            left: 40%;
            width: 100px;
            height: 100px;
            animation-delay: 0s;
        }

        .pulse-ring:nth-child(10) {
            top: 60%;
            left: 60%;
            width: 150px;
            height: 150px;
            animation-delay: 1s;
            border-color: rgba(0, 212, 255, 0.1);
        }

        .pulse-ring:nth-child(11) {
            top: 20%;
            left: 80%;
            width: 80px;
            height: 80px;
            animation-delay: 2s;
            border-color: rgba(180, 122, 255, 0.1);
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.1); opacity: 1; }
            70% { transform: scale(2); opacity: 0; }
            100% { transform: scale(2.5); opacity: 0; }
        }

        /* ==================== NAVIGATION ==================== */
        .navbar-custom {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 0.9rem 0;
            background: transparent;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .navbar-custom.scrolled {
            background: rgba(10, 15, 30, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 255, 136, 0.15);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 0.6rem 0;
        }

        .navbar-custom .container {
            max-width: 980px;
        }

        .navbar-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            position: relative;
            gap: 0.35rem;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: transform 0.3s ease;
            margin-bottom: 0.5rem;
        }

        .logo-container:hover {
            transform: translateY(-2px);
        }

        .logo-img {
            height: 52px;
            width: auto;
            filter: drop-shadow(0 0 15px rgba(0, 255, 136, 0.5));
            transition: all 0.3s ease;
        }

        .logo-text {
            font-family: 'Orbitron', sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            background: var(--gradient-neon);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        /* Navigation Menu */
        .nav-menu {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            padding: 0.5rem 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-neon);
            transition: width 0.3s ease;
        }

        .nav-link:hover {
            color: var(--neon-green);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 0.8rem;
        }

        .btn-logout {
            background: rgba(255, 59, 48, 0.1);
            color: #ff3b30;
            border: 1px solid rgba(255, 59, 48, 0.3);
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-logout:hover {
            background: rgba(255, 59, 48, 0.2);
            color: #ff3b30;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 59, 48, 0.3);
            border-color: #ff3b30;
        }

        .btn-login {
            background: var(--gradient-neon);
            color: var(--dark-bg);
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 255, 136, 0.3);
        }

        .user-info {
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ==================== MAIN CONTENT ==================== */
        .main-content {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 120px 20px 70px;
            position: relative;
        }

        .main-content .container {
            max-width: 980px;
        }

        .registration-container {
            background: rgba(15, 22, 41, 0.8);
            border: 1px solid var(--dark-border);
            border-radius: 24px;
            padding: 3rem;
            max-width: 720px;
            width: 100%;
            margin: 0 auto;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
        }

        .registration-container:hover {
            border-color: var(--neon-green);
            box-shadow: 0 40px 80px rgba(0, 255, 136, 0.2);
            transform: translateY(-10px);
        }

        .registration-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 255, 136, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .registration-container:hover::before {
            left: 100%;
        }

        .header-section {
            text-align: center;
            margin-bottom: 2.2rem;
            position: relative;
        }

        .logo-large {
            width: 140px;
            height: 140px;
            margin: 0 auto 1.2rem;
            background: var(--gradient-neon);
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--glow-green);
            animation: pulse-logo 2s ease-in-out infinite;
            padding: 15px;
            position: relative;
            overflow: hidden;
        }

        .logo-large::after {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            background: conic-gradient(from 0deg, transparent, var(--neon-green), transparent);
            z-index: -1;
            animation: rotate-border 3s linear infinite;
        }

        .logo-large img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(1.2);
            position: relative;
            z-index: 1;
        }

        @keyframes pulse-logo {
            0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(0, 255, 136, 0.5); }
            50% { transform: scale(1.05); box-shadow: 0 0 40px rgba(0, 255, 136, 0.8); }
        }

        @keyframes rotate-border {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .brand-title {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--neon-green) 0%, var(--neon-blue) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-transform: uppercase;
            letter-spacing: 1px;
        }


        .tagline {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 0;
            font-weight: 500;
            letter-spacing: 1px;
        }

        /* ==================== MESSAGE BOX ==================== */
        .message-box {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-left: 5px solid #ffc107;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .message-box:hover {
            border-color: #ffc107;
            box-shadow: 0 10px 30px rgba(255, 193, 7, 0.1);
            transform: translateY(-5px);
        }

        .message-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            color: #ffc107;
            font-size: 2rem;
            opacity: 0.3;
        }

        .message-title {
            color: #ffc107;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-title i {
            font-size: 1.2rem;
        }

        .message-content {
            color: #ffeaa7;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0;
        }

        /* ==================== REDIRECT SECTION ==================== */
        .redirect-section {
            background:
                radial-gradient(circle at top right, rgba(0, 212, 255, 0.12), transparent 50%),
                linear-gradient(150deg, rgba(10, 28, 45, 0.96) 0%, rgba(9, 24, 42, 0.92) 100%);
            border: 1px solid rgba(0, 212, 255, 0.32);
            border-radius: 18px;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .redirect-section::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
            border: 1px solid rgba(0, 212, 255, 0.12);
        }

        .redirect-section:hover {
            border-color: rgba(0, 212, 255, 0.5);
            box-shadow: 0 18px 40px rgba(0, 212, 255, 0.15);
            transform: translateY(-3px);
        }

        .redirect-head {
            margin-bottom: 1.2rem;
            position: relative;
            z-index: 1;
        }

        .redirect-title {
            color: var(--neon-blue);
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.45rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .redirect-subtitle {
            margin: 0;
            color: #9fc3d8;
            font-size: 0.95rem;
            line-height: 1.55;
        }

        .email-panel {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.8rem;
            align-items: stretch;
            margin: 1rem 0 1.1rem;
            position: relative;
            z-index: 1;
        }

        .email-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.8rem;
            background: rgba(2, 12, 24, 0.55);
            border: 1px solid rgba(0, 212, 255, 0.55);
            border-radius: 12px;
            padding: 0.9rem 1rem;
            transition: all 0.25s ease;
            min-width: 0;
        }

        .email-container:hover {
            background: rgba(0, 212, 255, 0.12);
            box-shadow: 0 10px 25px rgba(0, 212, 255, 0.2);
        }

        .email-icon {
            color: var(--neon-blue);
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .email-address {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1rem, 2.4vw, 1.45rem);
            font-weight: 700;
            color: #c8f4ff;
            text-decoration: none;
            letter-spacing: 0.4px;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            max-width: 100%;
        }

        .email-address:hover {
            color: #ffffff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.8);
        }

        .btn-copy-email {
            border: 1px solid rgba(0, 212, 255, 0.5);
            background: rgba(0, 212, 255, 0.14);
            color: #a2ecff;
            border-radius: 12px;
            padding: 0.78rem 1rem;
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            transition: all 0.25s ease;
            white-space: nowrap;
        }

        .btn-copy-email:hover {
            color: #001a2b;
            background: var(--neon-blue);
            border-color: var(--neon-blue);
            transform: translateY(-2px);
        }

        .redirect-meta {
            position: relative;
            z-index: 1;
        }

        .redirect-note {
            color: var(--text-secondary);
            font-size: 0.96rem;
            margin: 0;
            font-style: italic;
            line-height: 1.5;
        }

        .redirect-note.is-cancelled {
            color: #ffd27c;
        }

        .redirect-note.is-complete {
            color: #8ee6ff;
        }

        .countdown {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 46px;
            background: var(--gradient-neon);
            color: var(--dark-bg);
            font-weight: 800;
            padding: 0.36rem 0.62rem;
            border-radius: 8px;
            margin: 0 0.35rem;
            font-family: 'Orbitron', sans-serif;
            animation: pulse-countdown 1s infinite;
        }

        .countdown-track {
            width: 100%;
            height: 8px;
            margin-top: 0.8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            overflow: hidden;
        }

        .countdown-fill {
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #00ff88 0%, #00d4ff 100%);
            transition: width 0.25s ease;
        }

        @keyframes pulse-countdown {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.72; }
        }

        .redirect-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .btn-secondary-action {
            border: 1px solid rgba(148, 163, 184, 0.42);
            background: rgba(148, 163, 184, 0.12);
            color: #cbd5e1;
            border-radius: 12px;
            padding: 0.9rem 1.2rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.79rem;
            transition: all 0.25s ease;
        }

        .btn-secondary-action:hover {
            background: rgba(148, 163, 184, 0.24);
            border-color: rgba(148, 163, 184, 0.7);
            transform: translateY(-2px);
        }

        .btn-secondary-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* ==================== BUTTONS ==================== */
        .btn-neon {
            background: var(--gradient-neon);
            color: var(--dark-bg);
            padding: 1rem 2.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 800;
            font-family: 'Orbitron', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            box-shadow: var(--glow-green);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
        }

        .btn-neon::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn-neon:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 15px 50px rgba(0, 255, 136, 0.6);
            border-color: var(--neon-green);
        }

        .btn-neon:hover::before {
            left: 100%;
        }

        /* ==================== LOGIN STATUS ==================== */
        .login-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-status.logged-in {
            background: rgba(0, 255, 136, 0.1);
            color: var(--neon-green);
            border: 1px solid var(--neon-green);
        }

        .login-status.logged-out {
            background: rgba(255, 59, 48, 0.1);
            color: #ff3b30;
            border: 1px solid rgba(255, 59, 48, 0.3);
        }

        /* ==================== FOOTER ==================== */
        .footer-section {
            background: rgba(10, 15, 30, 0.95);
            border-top: 1px solid var(--dark-border);
            padding: 2rem 0;
            position: relative;
            text-align: center;
            z-index: 2;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 1rem;
        }

        .footer-logo img {
            height: 30px;
            filter: drop-shadow(0 0 10px rgba(0, 255, 136, 0.3));
        }

        .footer-logo span {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            background: var(--gradient-neon);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        /* ==================== SCROLL TO TOP ==================== */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            background: var(--gradient-neon);
            border: 2px solid var(--neon-green);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
            box-shadow: var(--glow-green);
        }

        .scroll-top.show {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            transform: translateY(-8px) scale(1.1);
            box-shadow: 0 15px 40px rgba(0, 255, 136, 0.6);
        }

        .scroll-top i {
            color: var(--dark-bg);
            font-size: 1.4rem;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 991px) {
            .brand-title { font-size: 2.8rem; }
            .registration-container { padding: 2rem; }
            .email-address { font-size: 1.5rem; }
            .redirect-section { padding: 1.7rem; }
            .email-panel { grid-template-columns: 1fr; }
            .btn-copy-email { width: 100%; }
            .action-buttons { gap: 0.5rem; }
            .btn-logout, .btn-login { padding: 0.5rem 1rem; font-size: 0.8rem; }
            .nav-menu { display: none; }
            .main-content { padding: 120px 16px 70px; }
        }

        @media (max-width: 767px) {
            .brand-title { font-size: 2.2rem; }
            .logo-large { width: 120px; height: 120px; }
            .email-address { font-size: 1.2rem; }
            .email-container { justify-content: center; flex-wrap: wrap; padding: 0.9rem; }
            .email-address { white-space: normal; overflow-wrap: anywhere; text-align: center; }
            .email-icon { margin-right: 0; }
            .redirect-actions { flex-direction: column; }
            .registration-container { padding: 1.5rem; }
            .message-content, .redirect-title { font-size: 1rem; }
            .action-buttons { flex-direction: row; justify-content: center; }
            .navbar-container { flex-direction: column; }
            .main-content { padding: 110px 14px 60px; }
        }

        @media (max-width: 575px) {
            .brand-title { font-size: 1.8rem; }
            .logo-large { width: 100px; height: 100px; border-radius: 20px; }
            .redirect-section { padding: 1.2rem; }
            .btn-neon, .btn-secondary-action, .btn-copy-email { width: 100%; justify-content: center; }
            .email-address { font-size: 1rem; }
            .action-buttons { flex-wrap: wrap; justify-content: center; }
            .user-info { display: none; }
            .main-content { padding: 100px 12px 50px; }
        }
    </style>
</head>
<body>
    <!-- Background Effects -->
    <div class="grid-background"></div>
    
    <!-- Animated Background Elements -->
    <div class="animated-background">
        <div class="floating-gear"><i class="fas fa-cog"></i></div>
        <div class="floating-gear"><i class="fas fa-cog"></i></div>
        <div class="floating-gear"><i class="fas fa-cog"></i></div>
        <div class="floating-gear"><i class="fas fa-cog"></i></div>
        <div class="floating-gear"><i class="fas fa-cog"></i></div>
        
        <div class="binary-code">01001000 01000101 01001100 01001100 01001111 00100000 01010000 01010010 01000101 01010011 01000101 01001110 01001111 01010110 01000001</div>
        <div class="binary-code">01000110 01000001 01000011 01000101 00100000 01010010 01000101 01000011 01001111 01000111 01001110 01001001 01010100 01001001 01001111 01001110</div>
        <div class="binary-code">01000111 01010000 01010011 00100000 01010100 01010010 01000001 01000011 01001011 01001001 01001110 01000111 00100000 01010011 01011001 01010011 01010100 01000101 01001101</div>
        
        <div class="pulse-ring"></div>
        <div class="pulse-ring"></div>
        <div class="pulse-ring"></div>
    </div>
    
    <div class="glow-orb orb-1"></div>
    <div class="glow-orb orb-2"></div>
    <div class="glow-orb orb-3"></div>

    <!-- Navigation -->
    <nav class="navbar-custom" id="navbar">
        <div class="container">
            <div class="navbar-container">
                <!-- Logo -->
                <a href="<?php echo $siteUrl; ?>" class="logo-container">
                    <img src="<?php echo $siteUrl; ?>assets/images/presenova.png" alt="Presenova Logo" class="logo-img">
                    <span class="logo-text">PRESENOVA</span>
                </a>
                
                <!-- Action Buttons & User Info -->
                <div class="action-buttons">
                    <?php if ($isLoggedIn): ?>
                        <div class="user-info d-none d-md-flex">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></span>
                        </div>
                        <a href="<?php echo $logoutUrl; ?>" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $loginUrl; ?>" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="registration-container">
                        <!-- Login Status Indicator -->
                        <?php if ($isLoggedIn): ?>
                            <div class="text-center mb-3">
                                <span class="login-status logged-in">
                                    <i class="fas fa-check-circle"></i> Anda sudah login
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="text-center mb-3">
                                <span class="login-status logged-out">
                                    <i class="fas fa-exclamation-circle"></i> Anda belum login
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="header-section">
                            <div class="logo-large">
                                <!-- Logo Presenova -->
                                <img src="<?php echo $siteUrl; ?>assets/images/presenova.png" alt="Presenova Logo">
                            </div>
                            <h1 class="brand-title">PRESENOVA</h1>
                            <p class="tagline">Bringing back, learning time</p>
                        </div>

                        <div class="message-box">
                            <i class="fas fa-exclamation-triangle message-icon"></i>
                            <h3 class="message-title">
                                <i class="fas fa-user-shield"></i> PENTING
                            </h3>
                            <p class="message-content">
                                HARAP REGISTRASI AKUN PESERTA MELALUI ADMINISTRATOR
                            </p>
                        </div>

                        <div class="redirect-section">
                            <div class="redirect-head">
                                <h3 class="redirect-title">
                                    <i class="fas fa-paper-plane"></i> Hubungi Administrator untuk Registrasi
                                </h3>
                                <p class="redirect-subtitle">
                                    Klik email admin di bawah ini. Sistem akan bantu membuka aplikasi email otomatis.
                                </p>
                            </div>

                            <div class="email-panel">
                                <div class="email-container">
                                    <i class="fas fa-envelope email-icon"></i>
                                    <a href="<?php echo htmlspecialchars($registrationMailtoDraft, ENT_QUOTES, 'UTF-8'); ?>" class="email-address" id="registrationEmailLink">
                                        <?php echo htmlspecialchars($registrationEmail, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </div>
                                <button type="button" class="btn-copy-email" id="copyEmailBtn">
                                    <i class="fas fa-copy"></i> Salin
                                </button>
                            </div>

                            <div class="redirect-meta">
                                <p class="redirect-note" id="redirectNote">
                                    Anda akan diarahkan ke aplikasi email dalam
                                    <span class="countdown" id="countdown">10</span> detik
                                </p>
                                <div class="countdown-track">
                                    <div class="countdown-fill" id="countdownFill"></div>
                                </div>
                            </div>

                            <div class="redirect-actions">
                                <a href="<?php echo htmlspecialchars($registrationMailtoDraft, ENT_QUOTES, 'UTF-8'); ?>" class="btn-neon" id="sendEmailBtn">
                                    <i class="fas fa-paper-plane"></i> Kirim Email Sekarang
                                </a>
                                <button type="button" class="btn-secondary-action" id="cancelRedirectBtn">
                                    <i class="fas fa-pause-circle"></i> Batalkan Auto Redirect
                                </button>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">
                                <i class="fas fa-info-circle"></i> 
                                Jika tidak diarahkan otomatis, klik tombol di atas atau salin alamat email secara manual
                            </p>
                        </div>

                        <!-- Quick Action Buttons -->
                        <div class="row mt-5">
                            <div class="col-md-6 mb-3">
                                <a href="<?php echo $isLoggedIn ? $dashboardUrl : $loginUrl; ?>" 
                                   class="btn btn-outline-neon w-100 py-3" 
                                   style="border-color: var(--neon-green); color: var(--neon-green);">
                                    <i class="fas fa-tachometer-alt me-2"></i>
                                    <?php echo $isLoggedIn ? 'Ke Dashboard' : 'Login ke Sistem'; ?>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="<?php echo $siteUrl; ?>" 
                                   class="btn btn-outline-neon w-100 py-3" 
                                   style="border-color: var(--neon-blue); color: var(--neon-blue);">
                                    <i class="fas fa-home me-2"></i>
                                    Kembali ke Beranda
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-section">
        <div class="container">
            <div class="footer-logo">
                <img src="<?php echo $siteUrl; ?>assets/images/presenova.png" alt="Presenova">
                <span>PRESENOVA</span>
            </div>
            <p class="footer-text">
                &copy; <?php echo date('Y'); ?> Presenova - Platform Absensi Cerdas dengan Teknologi AI & GPS
            </p>
            <p class="footer-text mt-2" style="font-size: 0.8rem;">
                <?php echo $isLoggedIn ? 'Status: Login aktif' : 'Status: Belum login'; ?> | 
                <a href="<?php echo $isLoggedIn ? $logoutUrl : $loginUrl; ?>" 
                   style="color: var(--neon-green); text-decoration: none;">
                    <?php echo $isLoggedIn ? 'Logout' : 'Login'; ?>
                </a>
            </p>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app-dialog.js"></script>
    
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            const scrollTop = document.getElementById('scrollTop');
            
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
                scrollTop.classList.add('show');
            } else {
                navbar.classList.remove('scrolled');
                scrollTop.classList.remove('show');
            }
        });
        
        // Scroll to Top
        document.getElementById('scrollTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Countdown and redirect
        const registrationEmail = <?php echo json_encode($registrationEmail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const defaultMailtoLink = <?php echo json_encode($registrationMailtoDraft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const countdownStart = 10;
        let countdown = countdownStart;
        let redirectCancelled = false;
        let countdownInterval = null;

        const countdownElement = document.getElementById('countdown');
        const countdownFill = document.getElementById('countdownFill');
        const redirectNote = document.getElementById('redirectNote');
        const cancelRedirectBtn = document.getElementById('cancelRedirectBtn');
        const sendEmailBtn = document.getElementById('sendEmailBtn');
        const emailLink = document.getElementById('registrationEmailLink');
        const copyEmailBtn = document.getElementById('copyEmailBtn');

        function updateCountdownUI() {
            if (countdownElement) {
                countdownElement.textContent = String(Math.max(0, countdown));
            }

            if (countdownFill) {
                const percentage = Math.max(0, (countdown / countdownStart) * 100);
                countdownFill.style.width = percentage + '%';
            }
        }

        function stopAutoRedirect(status = 'cancelled') {
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }

            if (redirectNote) {
                redirectNote.classList.remove('is-cancelled', 'is-complete');
                if (status === 'cancelled') {
                    redirectNote.classList.add('is-cancelled');
                    redirectNote.textContent = 'Auto redirect dibatalkan. Klik tombol "Kirim Email Sekarang" untuk lanjut.';
                } else if (status === 'complete') {
                    redirectNote.classList.add('is-complete');
                    redirectNote.textContent = 'Membuka aplikasi email... Jika gagal, klik alamat email atau tombol kirim.';
                } else if (status === 'copied') {
                    redirectNote.classList.add('is-complete');
                    redirectNote.textContent = 'Alamat email berhasil disalin. Tempelkan pada aplikasi email Anda.';
                }
            }

            if (cancelRedirectBtn) {
                cancelRedirectBtn.disabled = true;
            }
        }

        function resolveMailtoTarget() {
            if (sendEmailBtn && sendEmailBtn.href) {
                return sendEmailBtn.href;
            }

            return defaultMailtoLink;
        }

        function startAutoRedirect() {
            updateCountdownUI();
            countdownInterval = setInterval(function() {
                if (redirectCancelled) {
                    return;
                }

                countdown -= 1;
                updateCountdownUI();

                if (countdown <= 0) {
                    redirectCancelled = true;
                    stopAutoRedirect('complete');
                    window.location.href = resolveMailtoTarget();
                }
            }, 1000);
        }

        startAutoRedirect();

        if (cancelRedirectBtn) {
            cancelRedirectBtn.addEventListener('click', function() {
                if (redirectCancelled) {
                    return;
                }

                redirectCancelled = true;
                countdown = 0;
                updateCountdownUI();
                stopAutoRedirect('cancelled');
            });
        }

        if (emailLink) {
            emailLink.addEventListener('click', function() {
                redirectCancelled = true;
                stopAutoRedirect('complete');
            });
        }

        if (sendEmailBtn) {
            sendEmailBtn.addEventListener('click', function() {
                redirectCancelled = true;
                stopAutoRedirect('complete');
            });
        }

        if (copyEmailBtn) {
            copyEmailBtn.addEventListener('click', async function() {
                try {
                    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                        throw new Error('Clipboard API unavailable');
                    }

                    await navigator.clipboard.writeText(registrationEmail);
                    copyEmailBtn.innerHTML = '<i class="fas fa-check"></i> Tersalin';
                    stopAutoRedirect('copied');
                    setTimeout(() => {
                        copyEmailBtn.innerHTML = '<i class="fas fa-copy"></i> Salin';
                    }, 2200);
                } catch (error) {
                    copyEmailBtn.innerHTML = '<i class="fas fa-times"></i> Gagal';
                    setTimeout(() => {
                        copyEmailBtn.innerHTML = '<i class="fas fa-copy"></i> Salin';
                    }, 2200);
                }
            });
        }
        
        // Logout confirmation
        const logoutButton = document.querySelector('.btn-logout');
        if (logoutButton) {
            logoutButton.addEventListener('click', async function(e) {
                const confirmed = await AppDialog.confirm('Apakah Anda yakin ingin logout?', {
                    title: 'Konfirmasi Logout'
                });
                if (!confirmed) {
                    e.preventDefault();
                }
            });
        }
        
        // Create additional floating elements dynamically
        function createBinaryStream() {
            const binary = document.createElement('div');
            binary.className = 'binary-stream';
            binary.style.position = 'fixed';
            binary.style.color = 'rgba(0, 255, 136, 0.03)';
            binary.style.fontFamily = 'Courier New, monospace';
            binary.style.fontSize = '1rem';
            binary.style.whiteSpace = 'nowrap';
            binary.style.top = Math.random() * 100 + '%';
            binary.style.left = '0';
            binary.style.zIndex = '-1';
            binary.style.animation = `binary-flow ${Math.random() * 20 + 15}s linear infinite`;
            
            // Generate random binary string
            let binaryText = '';
            for (let i = 0; i < 50; i++) {
                binaryText += Math.round(Math.random()) + ' ';
            }
            binary.textContent = binaryText;
            
            document.body.appendChild(binary);
            
            setTimeout(() => {
                binary.remove();
            }, parseFloat(binary.style.animationDuration) * 1000);
        }
        
        // Create initial binary streams
        for (let i = 0; i < 5; i++) {
            setTimeout(() => createBinaryStream(), i * 1000);
        }
        
        // Create continuous binary streams
        setInterval(() => createBinaryStream(), 3000);
        
        // Add CSS for binary flow animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes binary-flow {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100vw); }
            }
            
            .btn-outline-neon {
                transition: all 0.3s ease;
                border-width: 2px;
                position: relative;
                overflow: hidden;
            }
            
            .btn-outline-neon:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 20px rgba(0, 255, 136, 0.2);
            }
            
            .btn-outline-neon::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(0, 255, 136, 0.1), transparent);
                transition: left 0.6s ease;
            }
            
            .btn-outline-neon:hover::before {
                left: 100%;
            }
        `;
        document.head.appendChild(style);
        
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('service-worker.js')
                    .then(registration => {
                        console.log('ServiceWorker registered:', registration.scope);
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed:', err);
                    });
            });
        }
        
        // Display login status message
        const loginStatus = document.querySelector('.login-status');
        if (loginStatus) {
            setTimeout(() => {
                if (loginStatus.classList.contains('logged-in')) {
                    loginStatus.innerHTML = '<i class="fas fa-user-check"></i> Anda sudah login sebagai ' + 
                        '<?php echo isset($_SESSION["user_name"]) ? htmlspecialchars($_SESSION["user_name"]) : "User"; ?>';
                }
            }, 1000);
        }
        
        // Add floating particle effect
        function createParticle() {
            const particle = document.createElement('div');
            particle.style.position = 'fixed';
            particle.style.width = '4px';
            particle.style.height = '4px';
            particle.style.backgroundColor = Math.random() > 0.5 ? 'rgba(0, 255, 136, 0.3)' : 'rgba(0, 212, 255, 0.3)';
            particle.style.borderRadius = '50%';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = '-10px';
            particle.style.zIndex = '-1';
            particle.style.boxShadow = '0 0 10px currentColor';
            particle.style.animation = `particle-fall ${Math.random() * 5 + 3}s linear infinite`;
            
            document.body.appendChild(particle);
            
            setTimeout(() => {
                particle.remove();
            }, parseFloat(particle.style.animationDuration) * 1000);
        }
        
        // Create initial particles
        for (let i = 0; i < 30; i++) {
            setTimeout(() => createParticle(), i * 100);
        }
        
        // Create continuous particles
        setInterval(() => createParticle(), 200);
        
        // Add particle animation
        const particleStyle = document.createElement('style');
        particleStyle.textContent = `
            @keyframes particle-fall {
                0% { transform: translateY(0) rotate(0deg); opacity: 0; }
                10% { opacity: 1; }
                90% { opacity: 1; }
                100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
            }
        `;
        document.head.appendChild(particleStyle);
    </script>
</body>
</html>
