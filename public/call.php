<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';

// Generate canonical URL
$fullurl = ($_SERVER['PHP_SELF']);
$trimmed = trim($fullurl, ".php");
$canonical = rtrim($trimmed, '/' . '/?');

// Set title halaman
$pageTitle = "call - presenova";

// Cek apakah ada aksi logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    // Hapus semua data session
    session_unset();
    session_destroy();
    
    // Redirect ke halaman login
    header('Location: login.php');
    exit();
}

// Cek status login dari session
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
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
    <link href="<?php echo SITE_URL; ?>" rel="home"/>
    <link href="<?php echo SITE_URL . $fullurl; ?>" rel="canonical"/>
    
    <!-- Title -->
    <title><?php echo $pageTitle; ?></title>
    
    <!-- OG Tags -->
    <meta property="og:type" content="website"/>
    <meta property="og:title" content="call - presenova"/>
    <meta property="og:description" content="Halaman registrasi akun peserta Presenova melalui administrator"/>
    <meta property="og:url" content="<?php echo SITE_URL . $fullurl; ?>"/>
    <meta property="og:site_name" content="Presenova"/>
    <meta property="og:image" content="<?php echo SITE_URL; ?>assets/images/presenova.png"/>
    <meta property="og:image:width" content="1200"/>
    <meta property="og:image:height" content="630"/>
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="call - presenova"/>
    <meta name="twitter:description" content="Halaman registrasi akun peserta Presenova melalui administrator"/>
    <meta name="twitter:image" content="<?php echo SITE_URL; ?>assets/images/presenova.png"/>
    
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
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 16px;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 2.5rem;
            transition: all 0.3s ease;
        }

        .redirect-section:hover {
            border-color: var(--neon-blue);
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.1);
            transform: translateY(-5px);
        }

        .redirect-icon {
            position: absolute;
            top: 20px;
            left: 20px;
            color: var(--neon-blue);
            font-size: 2rem;
            opacity: 0.3;
        }

        .redirect-title {
            color: var(--neon-blue);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .email-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid var(--neon-blue);
            border-radius: 12px;
            padding: 1rem 2rem;
            margin: 1.5rem 0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .email-container:hover {
            background: rgba(0, 212, 255, 0.1);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.2);
        }

        .email-icon {
            color: var(--neon-blue);
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .email-address {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--neon-blue);
            text-decoration: none;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .email-address:hover {
            color: var(--text-primary);
            text-shadow: 0 0 10px var(--neon-blue);
        }

        .redirect-note {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-top: 1.5rem;
            font-style: italic;
        }

        .countdown {
            display: inline-block;
            background: var(--gradient-neon);
            color: var(--dark-bg);
            font-weight: 800;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-left: 0.5rem;
            font-family: 'Orbitron', sans-serif;
            animation: pulse-countdown 1s infinite;
        }

        @keyframes pulse-countdown {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
            .action-buttons { gap: 0.5rem; }
            .btn-logout, .btn-login { padding: 0.5rem 1rem; font-size: 0.8rem; }
            .nav-menu { display: none; }
            .main-content { padding: 120px 16px 70px; }
        }

        @media (max-width: 767px) {
            .brand-title { font-size: 2.2rem; }
            .logo-large { width: 120px; height: 120px; }
            .email-address { font-size: 1.2rem; }
            .email-container { flex-direction: column; padding: 1rem; }
            .email-icon { margin-right: 0; margin-bottom: 0.5rem; }
            .registration-container { padding: 1.5rem; }
            .message-content, .redirect-title { font-size: 1rem; }
            .action-buttons { flex-direction: row; justify-content: center; }
            .navbar-container { flex-direction: column; }
            .main-content { padding: 110px 14px 60px; }
        }

        @media (max-width: 575px) {
            .brand-title { font-size: 1.8rem; }
            .logo-large { width: 100px; height: 100px; border-radius: 20px; }
            .btn-neon { width: 100%; justify-content: center; }
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
                <a href="<?php echo SITE_URL; ?>" class="logo-container">
                    <img src="<?php echo SITE_URL; ?>assets/images/presenova.png" alt="Presenova Logo" class="logo-img">
                    <span class="logo-text">PRESENOVA</span>
                </a>
                
                <!-- Navigation Menu -->
                <ul class="nav-menu d-none d-lg-flex">
                    <li class="nav-item">
                        <a href="<?php echo SITE_URL; ?>" class="nav-link">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a href="login.php" class="nav-link">Login</a>
                    </li>
                    <li class="nav-item">
                        <a href="register.php" class="nav-link">Registrasi</a>
                    </li>
                    <li class="nav-item">
                        <a href="#features" class="nav-link">Fitur</a>
                    </li>
                    <li class="nav-item">
                        <a href="#contact" class="nav-link">Kontak</a>
                    </li>
                </ul>
                
                <!-- Action Buttons & User Info -->
                <div class="action-buttons">
                    <?php if ($isLoggedIn): ?>
                        <div class="user-info d-none d-md-flex">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></span>
                        </div>
                        <a href="?action=logout" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn-login">
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
                                <img src="<?php echo SITE_URL; ?>assets/images/presenova.png" alt="Presenova Logo">
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
                            <i class="fas fa-paper-plane redirect-icon"></i>
                            <h3 class="redirect-title">
                                <i class="fas fa-redo-alt"></i> Redirecting untuk mengarah ke pengiriman email
                            </h3>
                            
                            <div class="email-container">
                                <i class="fas fa-envelope email-icon"></i>
                                <a href="mailto:adm290805@presenova.my.id" class="email-address">
                                    adm290805@presenova.my.id
                                </a>
                            </div>
                            
                            <p class="redirect-note">
                                Anda akan diarahkan ke aplikasi email dalam 
                                <span class="countdown" id="countdown">10</span> detik
                            </p>
                            
                            <a href="mailto:adm290805@presenova.my.id" class="btn-neon">
                                <i class="fas fa-paper-plane"></i> Kirim Email Sekarang
                            </a>
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
                                <a href="<?php echo $isLoggedIn ? 'dashboard.php' : 'login.php'; ?>" 
                                   class="btn btn-outline-neon w-100 py-3" 
                                   style="border-color: var(--neon-green); color: var(--neon-green);">
                                    <i class="fas fa-tachometer-alt me-2"></i>
                                    <?php echo $isLoggedIn ? 'Ke Dashboard' : 'Login ke Sistem'; ?>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="<?php echo SITE_URL; ?>" 
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
                <img src="<?php echo SITE_URL; ?>assets/images/presenova.png" alt="Presenova">
                <span>PRESENOVA</span>
            </div>
            <p class="footer-text">
                &copy; <?php echo date('Y'); ?> Presenova - Platform Absensi Cerdas dengan Teknologi AI & GPS
            </p>
            <p class="footer-text mt-2" style="font-size: 0.8rem;">
                <?php echo $isLoggedIn ? 'Status: Login aktif' : 'Status: Belum login'; ?> | 
                <a href="<?php echo $isLoggedIn ? '?action=logout' : 'login.php'; ?>" 
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
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        let redirectCancelled = false;
        
        const countdownInterval = setInterval(function() {
            if (!redirectCancelled) {
                countdown--;
                countdownElement.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    // Redirect to email client
                    window.location.href = "mailto:adm290805@presenova.my.id";
                }
            }
        }, 1000);
        
        // Cancel auto-redirect if user interacts
        document.addEventListener('click', function() {
            if (!redirectCancelled) {
                redirectCancelled = true;
                clearInterval(countdownInterval);
                countdownElement.textContent = "0";
                countdownElement.parentElement.innerHTML = "Redirect dibatalkan. Silakan klik tombol di atas untuk mengirim email.";
            }
        });
        
        // Email link enhancement
        document.querySelector('.email-address').addEventListener('click', function(e) {
            e.preventDefault();
            redirectCancelled = true;
            clearInterval(countdownInterval);
            
            // Animation effect
            this.style.transform = "scale(1.1)";
            this.style.textShadow = "0 0 15px var(--neon-blue)";
            
            setTimeout(() => {
                window.location.href = "mailto:adm290805@presenova.my.id";
            }, 300);
        });
        
        // Button enhancement
        const neonButton = document.querySelector('.btn-neon');
        if (neonButton) {
            neonButton.addEventListener('mouseenter', function() {
                this.innerHTML = '<i class="fas fa-rocket"></i> Kirim Email Sekarang';
            });
            
            neonButton.addEventListener('mouseleave', function() {
                this.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Email Sekarang';
            });
        }
        
        // Logout confirmation
        const logoutButton = document.querySelector('.btn-logout');
        if (logoutButton) {
            logoutButton.addEventListener('click', function(e) {
                if (!confirm('Apakah Anda yakin ingin logout?')) {
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
