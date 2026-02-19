<!DOCTYPE html>
<html lang="id-ID" xml:lang="id-ID">
<head>
    <!-- Meta Tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Presenova - Bringing Back, Learning Time">
    <meta name="keywords" content="absensi online, face recognition, gps tracking, presenova, sekolah digital, smart attendance">
    
    <!-- Canonical -->
    <meta content="all" name="robots"/>
    <link href="<?php echo $siteUrl; ?>" rel="home"/>
    <link href="<?php echo $siteUrl . $fullurl; ?>" rel="canonical"/>
    
    <!-- Title -->
    <title>home - presenova</title>
    
    <!-- OG Tags -->
    <meta property="og:type" content="website"/>
    <meta property="og:title" content="Presenova - Smart Attendance System"/>
    <meta property="og:description" content="Sistem absensi digital dengan AI Face Recognition & GPS Tracking untuk sekolah modern"/>
    <meta property="og:url" content="<?php echo $siteUrl . $fullurl; ?>"/>
    <meta property="og:site_name" content="Presenova"/>
    <meta property="og:image" content="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/presenova.png"/>
    <meta property="og:image:width" content="1200"/>
    <meta property="og:image:height" content="630"/>
    
    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image"/>
    <meta name="twitter:title" content="Presenova - Smart Attendance System"/>
    <meta name="twitter:description" content="Sistem absensi digital dengan AI Face Recognition & GPS Tracking"/>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/presenova.png"/>
    
    <!-- PWA -->
    <link rel="manifest" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>manifest.json">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0a0f1e" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Presenova">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/apple-touch-icon-white background.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-16x16-white background.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-16x16-black background.png" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" type="image/png" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-32x32_admin.png">
    
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
            --gradient-white: linear-gradient(135deg, #bfc3c180 0%, #474b4a 80%);
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

        /* Floating Shapes */
        .floating-shape {
            position: absolute;
            z-index: 1;
            opacity: 0.1;
            filter: blur(1px);
            animation-duration: 30s;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        .shape1 {
            top: 10%;
            left: 5%;
            width: 60px;
            animation: float-rotate-1 35s linear infinite;
        }

        .shape2 {
            top: 20%;
            right: 8%;
            width: 70px;
            animation: float-rotate-2 40s linear infinite reverse;
        }

        .shape3 {
            bottom: 15%;
            left: 10%;
            width: 80px;
            animation: float-rotate-3 38s linear infinite;
        }

        .shape4 {
            top: 50%;
            right: 5%;
            width: 65px;
            animation: float-rotate-4 42s linear infinite reverse;
        }

        .shape5 {
            bottom: 25%;
            right: 15%;
            width: 55px;
            animation: float-rotate-1 36s linear infinite;
        }

        .shape6 {
            top: 35%;
            left: 20%;
            width: 75px;
            animation: float-rotate-2 44s linear infinite reverse;
        }

        .shape7 {
            bottom: 35%;
            left: 8%;
            width: 60px;
            animation: float-rotate-3 39s linear infinite;
        }

        .shape8 {
            top: 60%;
            right: 20%;
            width: 70px;
            animation: float-rotate-4 41s linear infinite reverse;
        }

        @keyframes float-rotate-1 {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            25% { transform: translate(20px, -20px) rotate(90deg) scale(1.1); }
            50% { transform: translate(-15px, 15px) rotate(180deg) scale(0.9); }
            75% { transform: translate(15px, 25px) rotate(270deg) scale(1.05); }
            100% { transform: translate(0, 0) rotate(360deg) scale(1); }
        }

        @keyframes float-rotate-2 {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            25% { transform: translate(-25px, 15px) rotate(90deg) scale(1.15); }
            50% { transform: translate(20px, -10px) rotate(180deg) scale(0.85); }
            75% { transform: translate(-15px, -20px) rotate(270deg) scale(1.1); }
            100% { transform: translate(0, 0) rotate(360deg) scale(1); }
        }

        @keyframes float-rotate-3 {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            25% { transform: translate(30px, -10px) rotate(90deg) scale(1.2); }
            50% { transform: translate(-20px, 25px) rotate(180deg) scale(0.8); }
            75% { transform: translate(10px, 30px) rotate(270deg) scale(1.15); }
            100% { transform: translate(0, 0) rotate(360deg) scale(1); }
        }

        @keyframes float-rotate-4 {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            25% { transform: translate(-15px, -25px) rotate(90deg) scale(1.05); }
            50% { transform: translate(25px, 10px) rotate(180deg) scale(0.95); }
            75% { transform: translate(-10px, 20px) rotate(270deg) scale(1.1); }
            100% { transform: translate(0, 0) rotate(360deg) scale(1); }
        }

        /* ==================== NAVIGATION ==================== */
        .navbar-custom {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 1.2rem 0;
            background: transparent;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .navbar-custom.scrolled {
            background: rgba(10, 15, 30, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 255, 136, 0.15);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 0.8rem 0;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: transform 0.3s ease;
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

        .nav-links {
            display: flex;
            gap: 2.8rem;
            align-items: center;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-link-item {
            position: relative;
        }

        .nav-link-item a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 0.5rem;
        }

        .nav-link-item a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-neon);
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-link-item a:hover {
            color: var(--neon-green);
        }

        .nav-link-item a:hover::before {
            width: 100%;
        }

        .btn-neon {
            background: var(--gradient-white, linear-gradient(135deg, #f3fff9 0%, #d9fff0 100%));
            color: var(--dark-bg);
            padding: 0.8rem 2rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
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
        }

        .btn-neon i {
            color: currentColor;
            font-size: 1rem;
            line-height: 1;
        }

        .btn-neon.d-lg-none {
            min-width: 72px;
            min-height: 46px;
            justify-content: center;
            padding: 0.72rem 0.95rem;
            gap: 0;
            border-radius: 12px;
            background: rgba(0, 255, 136, 0.14);
            border-color: rgba(0, 255, 136, 0.42);
            color: var(--neon-green);
            box-shadow: 0 0 18px rgba(0, 255, 136, 0.26);
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

        /* ==================== HERO SECTION ==================== */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            padding-top: 200px;
            overflow: hidden;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 0.6rem 1.5rem;
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid var(--neon-green);
            border-radius: 8px;
            color: var(--neon-green);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            animation: pulse-tag 2s ease-in-out infinite;
        }

        @keyframes pulse-tag {
            0%, 100% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(0, 255, 136, 0); }
        }

        .hero-title {
            font-size: 4.8rem;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--neon-green) 0%, var(--neon-blue) 50%, var(--neon-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            background-size: 200% auto;
            animation: gradient-shift 3s ease-in-out infinite;
            text-transform: uppercase;
            letter-spacing: -1px;
        }

        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .hero-subtitle {
            font-size: 1.4rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            max-width: 600px;
            line-height: 1.6;
        }

        .hero-description {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 3rem;
            max-width: 550px;
            line-height: 1.8;
        }

        .hero-buttons {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 4rem;
        }

        .btn-hero-primary {
            background: var(--gradient-neon);
            color: var(--dark-bg);
            padding: 1.2rem 3rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            box-shadow: 0 10px 40px rgba(0, 255, 136, 0.3);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-hero-primary:hover {
            transform: translateY(-6px) scale(1.05);
            box-shadow: 0 20px 60px rgba(0, 255, 136, 0.5);
            border-color: var(--neon-green);
        }

        .btn-hero-secondary {
            background: transparent;
            color: var(--neon-blue);
            border: 2px solid var(--neon-blue);
            padding: 1.2rem 3rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-hero-secondary:hover {
            background: rgba(0, 212, 255, 0.1);
            transform: translateY(-6px) scale(1.05);
            box-shadow: 0 20px 40px rgba(0, 212, 255, 0.3);
            color: var(--neon-blue);
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0rem;
            max-width: 560px;
            padding: 1.5rem;
            background: rgba(15, 22, 41, 0.5);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            border-right: 1px solid var(--dark-border);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            border-color: var(--neon-green);
        }

        .stat-item:last-child {
            border-right: none;
        }

        .stat-value {
            font-size: 3rem;
            font-weight: 900;
            background: var(--gradient-neon);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
            margin-bottom: 0.5rem;
            animation: count-up 2s ease-out;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero-visual {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .device-mockup {
            position: relative;
            width: 344px;
            height: 650px;
            background: var(--dark-card);
            border-radius: 40px;
            border: 8px solid var(--dark-border);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .device-mockup:hover {
            transform: translateY(-20px) rotateX(5deg);
            box-shadow: 0 50px 120px rgba(0, 255, 136, 0.4);
            border-color: var(--neon-green);
        }

        .device-screen {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            background: linear-gradient(135deg, #0f1629 0%, #1a2332 100%);
            border-radius: 24px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .screen-content {
            text-align: center;
            padding: 2rem;
        }

        .app-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--gradient-neon);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--glow-green);
            animation: pulse-logo 2s ease-in-out infinite;
        }

        @keyframes pulse-logo {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .app-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: var(--gradient-neon);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .app-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .scan-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--gradient-neon);
            animation: scan 3s linear infinite;
            box-shadow: 0 0 10px var(--neon-green);
        }

        @keyframes scan {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }

        /* ==================== FEATURES SECTION ==================== */
        .features-section {
            padding: 8rem 0;
            position: relative;
        }

        .section-header {
            text-align: center;
            margin-bottom: 5rem;
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 0.6rem 1.5rem;
            background: rgba(180, 122, 255, 0.1);
            border: 1px solid var(--neon-purple);
            border-radius: 8px;
            color: var(--neon-purple);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--neon-purple) 0%, var(--neon-blue) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .section-description {
            font-size: 1.2rem;
            color: var(--text-secondary);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.8;
        }

        .feature-card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 20px;
            padding: 2.5rem;
            height: 100%;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 255, 136, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .feature-card:hover {
            transform: translateY(-15px);
            border-color: var(--neon-green);
            box-shadow: 0 30px 80px rgba(0, 255, 136, 0.2);
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-neon);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: var(--glow-green);
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: rotate(15deg) scale(1.1);
            box-shadow: 0 0 40px rgba(0, 255, 136, 0.8);
        }

        .feature-icon i {
            font-size: 2.2rem;
            color: var(--dark-bg);
        }

        .feature-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .feature-description {
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 0;
        }

        /* ==================== HOW IT WORKS ==================== */
        .how-it-works {
            padding: 8rem 0;
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.05) 0%, rgba(0, 212, 255, 0.05) 100%);
            position: relative;
        }

        .step-card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            height: 100%;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .step-card:hover {
            transform: translateY(-10px);
            border-color: var(--neon-purple);
            box-shadow: 0 20px 60px rgba(180, 122, 255, 0.2);
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: var(--gradient-purple);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--dark-bg);
            margin: 0 auto 1.5rem;
            box-shadow: var(--glow-purple);
        }

        .step-icon {
            width: 80px;
            height: 80px;
            background: rgba(180, 122, 255, 0.1);
            border: 2px solid var(--neon-purple);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--neon-purple);
            font-size: 2rem;
            transition: all 0.3s ease;
        }

        .step-card:hover .step-icon {
            background: var(--gradient-purple);
            color: var(--dark-bg);
            transform: rotate(360deg);
        }

        .step-title {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .step-description {
            color: var(--text-secondary);
            line-height: 1.7;
            margin: 0;
        }

        /* ==================== PWA SECTION ==================== */
        .pwa-section {
            padding: 8rem 0;
            position: relative;
        }

        .pwa-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .pwa-icon-large {
            font-size: 6rem;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, var(--neon-green), var(--neon-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .pwa-instructions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 4rem;
        }

        .instruction-card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .instruction-card:hover {
            transform: translateY(-10px);
            border-color: var(--neon-blue);
            box-shadow: 0 20px 40px rgba(0, 212, 255, 0.2);
        }

        .instruction-number {
            width: 40px;
            height: 40px;
            background: var(--gradient-neon);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: var(--dark-bg);
            margin: 0 auto 1rem;
        }

        .instruction-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--neon-blue);
        }

        .instruction-text {
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* ==================== FOOTER ==================== */
        .footer-section {
            background: rgba(10, 15, 30, 0.95);
            border-top: 1px solid var(--dark-border);
            padding: 5rem 0 2rem;
            position: relative;
        }

        .footer-widget {
            margin-bottom: 3rem;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .footer-logo img {
            height: 40px;
            filter: drop-shadow(0 0 10px rgba(0, 255, 136, 0.3));
        }

        .footer-logo span {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            background: var(--gradient-neon);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-description {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 1.5rem;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-link {
            width: 44px;
            height: 44px;
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid var(--neon-green);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--neon-green);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: var(--gradient-neon);
            color: var(--dark-bg);
            transform: translateY(-5px);
        }

        .footer-title {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            position: relative;
            padding-bottom: 0.5rem;
        }

        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--gradient-neon);
            border-radius: 2px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-links a:hover {
            color: var(--neon-green);
            transform: translateX(5px);
        }

        .footer-contact {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-contact li {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .footer-contact i {
            color: var(--neon-green);
            margin-top: 0.25rem;
        }

        .footer-contact a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-contact a:hover {
            color: var(--neon-green);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 3rem;
            margin-top: 3rem;
            border-top: 1px solid var(--dark-border);
            color: var(--text-secondary);
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
        @media (max-width: 1199px) {
            .hero-title { font-size: 4rem; }
            .section-title { font-size: 3rem; }
        }

        @media (max-width: 991px) {
            .nav-links { display: none; }
            .hero-title { font-size: 3.2rem; }
            .hero-visual { margin-top: 4rem; }
            .device-mockup { width: 280px; height: 580px; }
            .section-title { font-size: 2.5rem; }
            .hero-stats { grid-template-columns: 1fr; }
            .stat-item { border-right: none; border-bottom: 1px solid var(--dark-border); padding: 1.5rem; }
            .stat-item:last-child { border-bottom: none; }
        }

        @media (max-width: 767px) {
            .hero-title { font-size: 2.5rem; }
            .hero-buttons { flex-direction: column; }
            .btn-hero-primary, .btn-hero-secondary { width: 100%; justify-content: center; }
            .section-title { font-size: 2rem; }
            .pwa-instructions { grid-template-columns: 1fr; }
        }

        @media (max-width: 575px) {
            .hero-title { font-size: 2.2rem; }
            .section-title { font-size: 1.8rem; }
            .device-mockup { width: 344px; height: 520px; }
            .feature-card, .step-card { padding: 2rem; }

            .btn-neon.d-lg-none {
                min-width: 64px;
                min-height: 42px;
                padding: 0.62rem 0.78rem;
            }

            .btn-neon.d-lg-none i {
                font-size: 1.04rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background Effects -->
    <div class="grid-background"></div>
    <div class="glow-orb orb-1"></div>
    <div class="glow-orb orb-2"></div>
    <div class="glow-orb orb-3"></div>

    <!-- Floating Shapes -->
    <div class="floating-shape shape1">
        <i class="fas fa-code" style="font-size: 2rem; color: var(--neon-green);"></i>
    </div>
    <div class="floating-shape shape2">
        <i class="fas fa-bolt" style="font-size: 2.2rem; color: var(--neon-blue);"></i>
    </div>
    <div class="floating-shape shape3">
        <i class="fas fa-star" style="font-size: 1.8rem; color: var(--neon-purple);"></i>
    </div>
    <div class="floating-shape shape4">
        <i class="fas fa-cube" style="font-size: 2rem; color: var(--neon-green);"></i>
    </div>
    <div class="floating-shape shape5">
        <i class="fas fa-circle" style="font-size: 1.5rem; color: var(--neon-blue);"></i>
    </div>
    <div class="floating-shape shape6">
        <i class="fas fa-hexagon" style="font-size: 2rem; color: var(--neon-purple);"></i>
    </div>
    <div class="floating-shape shape7">
        <i class="fas fa-diamond" style="font-size: 1.8rem; color: var(--neon-green);"></i>
    </div>
    <div class="floating-shape shape8">
        <i class="fas fa-cog" style="font-size: 2.2rem; color: var(--neon-blue);"></i>
    </div>

    <!-- Navigation -->
    <nav class="navbar-custom" id="navbar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-6 col-lg-3">
                    <a href="<?php echo htmlspecialchars($getStartedUrl, ENT_QUOTES, 'UTF-8'); ?>" class="logo-container">
                        <img src="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/presenova.png" alt="Presenova Logo" class="logo-img">
                        <span class="logo-text">PRESENOVA</span>
                    </a>
                </div>
                <div class="col-6 col-lg-9 text-end">
                    <ul class="nav-links d-none d-lg-flex justify-content-end">
                        <li class="nav-link-item"><a href="#home">Beranda</a></li>
                        <li class="nav-link-item"><a href="#features">Fitur</a></li>
                        <li class="nav-link-item"><a href="#howto">Cara Kerja</a></li>
                        <li class="nav-link-item"><a href="#pwa">PWA</a></li>
                        <li class="nav-link-item"><a href="#contact">Kontak</a></li>
                        <li class="nav-link-item"><a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-neon"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    </ul>
                    <a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-neon d-lg-none"><i class="fas fa-sign-in-alt"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="hero-content">
                        <div class="hero-tag">
                            <i class="fas fa-rocket"></i>
                            <span>Bringing Back, Learning Time</span>
                        </div>
                        
                        <h1 class="hero-title">PRESENOVA</h1>
                        
                        <p class="hero-subtitle">
                            Mengubah cara sekolah mengelola kehadiran dengan teknologi AI & GPS
                        </p>
                        
                        <p class="hero-description">
                            Presenova adalah platform absensi cerdas yang menggabungkan teknologi pengenalan wajah (Face Recognition) 
                            dengan validasi GPS untuk memastikan kehadiran siswa yang akurat, real-time, dan tanpa kontak fisik.
                        </p>
                        
                        <div class="hero-buttons">
                            <a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-hero-primary">
                                <i class="fas fa-sign-in-alt"></i> Login Sekarang
                            </a>
                            <a href="#features" class="btn-hero-secondary">
                                <i class="fas fa-play-circle"></i> Lihat Demo
                            </a>
                        </div>
                        
                        <div class="hero-stats">
                            <div class="stat-item">
                                <span class="stat-value">99.9%</span>
                                <span class="stat-label">Akurasi Wajah</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value">&lt;3s</span>
                                <span class="stat-label">Waktu Proses</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value">24/7</span>
                                <span class="stat-label">Monitoring</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="hero-visual">
                        <div class="device-mockup">
                            <div class="device-screen">
                                <div class="screen-content">
                                    <div class="app-logo">
                                        <i class="fas fa-fingerprint" style="font-size: 2.5rem; color: var(--dark-bg);"></i>
                                    </div>
                                    <h3 class="app-title">PRESENOVA</h3>
                                    <p class="app-subtitle">Bringing Back, Learning Time</p>
                                    <div style="margin-top: 2rem;">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 1rem;">
                                            <i class="fas fa-check-circle" style="color: var(--neon-green);"></i>
                                            <span style="color: var(--text-secondary); font-size: 0.9rem;">Face Recognition Ready</span>
                                        </div>
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
                                            <i class="fas fa-map-marker-alt" style="color: var(--neon-blue);"></i>
                                            <span style="color: var(--text-secondary); font-size: 0.9rem;">GPS Location Valid</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">
                    <i class="fas fa-bolt"></i>
                    <span>Advanced Technology</span>
                </div>
                <h2 class="section-title">Teknologi Canggih dalam Satu Platform</h2>
                <p class="section-description">
                    Presenova menggabungkan berbagai teknologi terdepan untuk menciptakan sistem absensi yang 
                    akurat, efisien, dan aman untuk lingkungan sekolah modern.
                </p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3 class="feature-title">AI Face Recognition</h3>
                        <p class="feature-description">
                            Sistem pengenalan wajah berbasis AI dengan akurasi 99.9%. Tanpa sentuhan fisik, lebih higienis dan aman.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3 class="feature-title">GPS Geofencing</h3>
                        <p class="feature-description">
                            Validasi lokasi dengan radius tertentu. Pastikan siswa berada dalam area sekolah saat melakukan absensi.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3 class="feature-title">Smart Notification</h3>
                        <p class="feature-description">
                            Notifikasi otomatis 5 menit sebelum pelajaran dimulai via PWA. Tidak ada lagi yang terlambat.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3 class="feature-title">Real-time Analytics</h3>
                        <p class="feature-description">
                            Dashboard real-time dengan visualisasi data yang informatif untuk guru dan admin.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3 class="feature-title">PWA Support</h3>
                        <p class="feature-description">
                            Progressive Web App yang bisa diinstall di homescreen seperti aplikasi native tanpa download dari store.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="feature-title">Secure & Private</h3>
                        <p class="feature-description">
                            Data siswa terlindungi dengan enkripsi end-to-end dan hanya digunakan untuk keperluan absensi.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="howto">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">
                    <i class="fas fa-cogs"></i>
                    <span>Simple Process</span>
                </div>
                <h2 class="section-title">4 Langkah Mudah Absensi</h2>
                <p class="section-description">
                    Proses absensi yang sederhana namun powerful dengan teknologi terkini
                </p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h3 class="step-title">Registrasi Wajah</h3>
                        <p class="step-description">
                            Upload foto wajah pertama kali untuk dijadikan referensi oleh sistem AI.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3 class="step-title">Terima Notifikasi</h3>
                        <p class="step-description">
                            Dapatkan notifikasi push 5 menit sebelum pelajaran dimulai via PWA.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h3 class="step-title">Ambil Selfie</h3>
                        <p class="step-description">
                            Foto selfie untuk validasi wajah dan lokasi GPS secara bersamaan.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="step-title">Absensi Terekam</h3>
                        <p class="step-description">
                            Data tersimpan dengan foto, waktu, lokasi, dan status kehadiran.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PWA Section -->
    <section class="pwa-section" id="pwa">
        <div class="container">
            <div class="pwa-content">
                <div class="pwa-icon-large">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h2 class="section-title">Install sebagai Aplikasi Mobile</h2>
                <p class="section-description">
                    Dapatkan pengalaman terbaik dengan menginstall Presenova sebagai Progressive Web App (PWA). 
                    Nikmati fitur seperti aplikasi native tanpa perlu download dari app store!
                </p>
                
                <div class="pwa-instructions">
                    <div class="instruction-card">
                        <div class="instruction-number">1</div>
                        <div class="instruction-icon">
                            <i class="fab fa-chrome"></i>
                        </div>
                        <p class="instruction-text">Buka di Chrome/Edge</p>
                    </div>
                    
                    <div class="instruction-card">
                        <div class="instruction-number">2</div>
                        <div class="instruction-icon">
                            <i class="fas fa-ellipsis-v"></i>
                        </div>
                        <p class="instruction-text">Tap Menu (â‹®)</p>
                    </div>
                    
                    <div class="instruction-card">
                        <div class="instruction-number">3</div>
                        <div class="instruction-icon">
                            <i class="fas fa-plus-square"></i>
                        </div>
                        <p class="instruction-text">Add to Home Screen</p>
                    </div>
                    
                    <div class="instruction-card">
                        <div class="instruction-number">4</div>
                        <div class="instruction-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <p class="instruction-text">Siap Digunakan!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-section" id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="footer-widget">
                        <div class="footer-logo">
                            <img src="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/presenova.png" alt="Presenova">
                            <span>PRESENOVA</span>
                        </div>
                        <p class="footer-description">
                            Platform absensi digital dengan teknologi AI dan GPS untuk manajemen kehadiran 
                            siswa yang modern, efisien, dan akurat.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="footer-widget">
                        <h3 class="footer-title">Akses Cepat</h3>
                        <ul class="footer-links">
                            <li><a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-chevron-right"></i> Login Siswa</a></li>
                            <li><a href="<?php echo htmlspecialchars($loginAdminUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-chevron-right"></i> Login Admin</a></li>
                            <li><a href="<?php echo htmlspecialchars($loginGuruUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-chevron-right"></i> Login Guru</a></li>
                            <li><a href="<?php echo htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-chevron-right"></i> Registrasi Wajah</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="footer-widget">
                        <h3 class="footer-title">Kontak Kami</h3>
                        <ul class="footer-contact">
                            <li>
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:adm@presenova.my.id">adm@presenova.my.id</a>
                            </li>
                            <li>
                                <i class="fas fa-phone"></i>
                                <a href="tel:+6282377823390">0811-1444-240</a>
                            </li>
                            <li>
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Jl. Ciantra, Sukadami, Cikarang Selatan</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        <p class="mb-0">&copy; <?php echo date('Y'); ?> <strong>Presenova</strong>. All rights reserved.</p>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <p class="mb-0">PT.VERITY TEKNOLOGI INDONESIA</p>
                    </div>
                </div>
            </div>
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
        
        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#') return;
                
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Counter animation for stats
        function animateCounter(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.textContent = value === end ? end + (element.textContent.includes('%') ? '%' : '') : value;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }
        
        // Initialize counter when in viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statValues = entry.target.querySelectorAll('.stat-value');
                    statValues.forEach(stat => {
                        const value = stat.textContent;
                        if (value.includes('%')) {
                            const num = parseInt(value);
                            stat.textContent = '0%';
                            animateCounter(stat, 0, num, 2000);
                        } else if (value.includes('s')) {
                            const num = parseFloat(value.replace('s', ''));
                            stat.textContent = '0s';
                            setTimeout(() => {
                                stat.textContent = value;
                            }, 2200);
                        } else {
                            const num = parseInt(value);
                            stat.textContent = '0';
                            animateCounter(stat, 0, num, 2000);
                        }
                    });
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        // Observe hero section for counter animation
        const heroSection = document.querySelector('.hero-section');
        if (heroSection) {
            observer.observe(heroSection);
        }
        
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>service-worker.js')
                    .then(registration => {
                        console.log('ServiceWorker registered:', registration.scope);
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed:', err);
                    });
            });
        }
        
        // Add floating shapes dynamically
        function createFloatingShape() {
            const shape = document.createElement('div');
            shape.className = 'floating-shape';
            shape.style.left = Math.random() * 100 + '%';
            shape.style.top = Math.random() * 100 + '%';
            
            const icons = ['fa-code', 'fa-bolt', 'fa-star', 'fa-circle', 'fa-heart', 'fa-diamond', 'fa-cog', 'fa-rocket'];
            const colors = ['var(--neon-green)', 'var(--neon-blue)', 'var(--neon-purple)', 'var(--neon-pink)'];
            
            const icon = icons[Math.floor(Math.random() * icons.length)];
            const color = colors[Math.floor(Math.random() * colors.length)];
            const size = Math.random() * 2 + 1 + 'rem';
            
            shape.innerHTML = `<i class="fas ${icon}" style="font-size: ${size}; color: ${color};"></i>`;
            shape.style.animationDuration = (Math.random() * 20 + 20) + 's';
            shape.style.opacity = Math.random() * 0.1 + 0.05;
            
            document.body.appendChild(shape);
            
            // Remove shape after animation completes
            setTimeout(() => {
                shape.remove();
            }, parseFloat(shape.style.animationDuration) * 1000);
        }
        
        // Create initial floating shapes
        for (let i = 0; i < 15; i++) {
            setTimeout(() => createFloatingShape(), i * 300);
        }
        
        // Continue creating shapes periodically
        setInterval(() => {
            createFloatingShape();
        }, 5000);
        
        // Device mockup hover effect enhancement
        const deviceMockup = document.querySelector('.device-mockup');
        if (deviceMockup) {
            deviceMockup.addEventListener('mouseenter', () => {
                const screenContent = deviceMockup.querySelector('.screen-content');
                if (screenContent) {
                    screenContent.innerHTML = `
                        <div class="app-logo">
                            <i class="fas fa-user-check" style="font-size: 2.5rem; color: var(--dark-bg);"></i>
                        </div>
                        <h3 class="app-title" style="color: var(--neon-green);">ABSENSI BERHASIL</h3>
                        <p class="app-subtitle">${new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'})}</p>
                        <div style="margin-top: 2rem;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-user" style="color: var(--neon-green);"></i>
                                <span style="color: var(--text-secondary); font-size: 0.9rem;">Andi Wijaya - XII RPL 1</span>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
                                <i class="fas fa-map-marker-alt" style="color: var(--neon-blue);"></i>
                                <span style="color: var(--text-secondary); font-size: 0.9rem;">SMK Negeri 1 Cikarang</span>
                            </div>
                        </div>
                    `;
                }
            });
            
            deviceMockup.addEventListener('mouseleave', () => {
                const screenContent = deviceMockup.querySelector('.screen-content');
                if (screenContent) {
                    screenContent.innerHTML = `
                        <div class="app-logo">
                            <i class="fas fa-fingerprint" style="font-size: 2.5rem; color: var(--dark-bg);"></i>
                        </div>
                        <h3 class="app-title">PRESENOVA</h3>
                        <p class="app-subtitle">Bringing Back, Learning Time</p>
                        <div style="margin-top: 2rem;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 1rem;">
                                <i class="fas fa-check-circle" style="color: var(--neon-green);"></i>
                                <span style="color: var(--text-secondary); font-size: 0.9rem;">Face Recognition Ready</span>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
                                <i class="fas fa-map-marker-alt" style="color: var(--neon-blue);"></i>
                                <span style="color: var(--text-secondary); font-size: 0.9rem;">GPS Location Valid</span>
                            </div>
                        </div>
                    `;
                }
            });
        }
    </script>
</body>
</html>


