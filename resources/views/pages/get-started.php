<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Presenova - Get Started">
    <meta name="robots" content="all">
    <link rel="canonical" href="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <title>get started - presenova</title>
    <meta name="theme-color" content="#04091d">
    <link rel="manifest" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>manifest.json?v=20260220pwa">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/apple-touch-icon-white background.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-32x32_login.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Orbitron:wght@500;700;800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-1: #02060f;
            --bg-2: #031126;
            --bg-3: #03172f;
            --text: #ecf5ff;
            --muted: #aec3e3;
            --soft-border: rgba(145, 175, 235, 0.28);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background:
                radial-gradient(920px circle at 10% 0%, rgba(58, 116, 255, 0.15), transparent 44%),
                radial-gradient(920px circle at 95% 88%, rgba(4, 251, 53, 0.06), transparent 44%),
                #02050d;
            min-height: 100svh;
            overflow: hidden;
        }

        .page-shell {
            width: 100%;
            min-height: 100svh;
        }

        .hero-wrapper {
            width: 100%;
            height: 100svh;
        }

        .hero-stage {
            position: relative;
            overflow: hidden;
            width: 100%;
            min-height: 100svh;
            height: 100svh;
            background:
                radial-gradient(circle at 50% -5%, rgba(59, 132, 255, 0.16), transparent 52%),
                linear-gradient(90deg, var(--bg-1) 0%, var(--bg-2) 42%, var(--bg-3) 60%, var(--bg-1) 100%);
        }

        .hero-stage::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(98, 126, 178, 0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(98, 126, 178, 0.07) 1px, transparent 1px);
            background-size: 28px 28px;
            opacity: 0.2;
        }

        .light-pillar-wrap {
            position: absolute;
            inset: 0;
            z-index: 1;
            mix-blend-mode: screen;
            filter: contrast(1.22) saturate(1.36) brightness(1.08);
            opacity: 1;
        }

        .light-pillar-wrap canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
            filter: contrast(1.06) saturate(1.12) brightness(1.04);
        }

        .light-pillar-wrap::before,
        .light-pillar-wrap::after {
            content: '';
            position: absolute;
            pointer-events: none;
        }

        .light-pillar-wrap::before {
            inset: -12% -30%;
            background:
                radial-gradient(36% 62% at 52% 60%, rgba(56, 248, 229, 0.34) 0%, rgba(48, 186, 255, 0.22) 45%, rgba(0, 0, 0, 0) 78%);
            filter: blur(24px);
            opacity: 0.86;
            mix-blend-mode: screen;
        }

        .light-pillar-wrap::after {
            top: -10%;
            bottom: -14%;
            left: 50%;
            width: min(44vw, 420px);
            transform: translateX(-50%) rotate(2deg);
            background: linear-gradient(
                180deg,
                rgba(204, 255, 253, 0) 0%,
                rgba(120, 246, 255, 0.22) 22%,
                rgba(82, 240, 255, 0.3) 46%,
                rgba(93, 255, 206, 0.24) 64%,
                rgba(177, 255, 247, 0) 100%
            );
            filter: blur(14px);
            opacity: 0.82;
            mix-blend-mode: screen;
        }

        .pillar-aura {
            position: absolute;
            inset: -30%;
            z-index: 2;
            pointer-events: none;
            mix-blend-mode: screen;
            filter: blur(32px);
            will-change: transform, opacity;
        }

        .pillar-aura-1 {
            background:
                radial-gradient(40% 60% at 52% 62%, rgba(50, 255, 224, 0.72) 0%, rgba(12, 176, 255, 0.46) 38%, rgba(0, 0, 0, 0) 78%);
            opacity: 0.45;
            animation: pillarAuraPulse 2.8s ease-in-out infinite;
        }

        .pillar-aura-2 {
            background:
                radial-gradient(32% 46% at 59% 40%, rgba(90, 255, 176, 0.6) 0%, rgba(0, 210, 255, 0.34) 40%, rgba(0, 0, 0, 0) 79%);
            opacity: 0.34;
            animation: pillarAuraPulse 3.7s ease-in-out infinite reverse;
        }

        .pillar-aura-3 {
            background:
                radial-gradient(18% 40% at 54% 56%, rgba(188, 255, 245, 0.72) 0%, rgba(48, 238, 255, 0.38) 42%, rgba(0, 0, 0, 0) 80%);
            opacity: 0.24;
            filter: blur(18px);
            animation: pillarAuraPulse 2.1s ease-in-out infinite;
        }

        @keyframes pillarAuraPulse {
            0%,
            100% {
                opacity: 0.32;
                transform: scale(0.96);
            }
            50% {
                opacity: 0.62;
                transform: scale(1.08);
            }
        }

        .hero-vignette {
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
            background:
                linear-gradient(90deg, rgba(2, 6, 18, 0.42) 0%, rgba(2, 9, 23, 0.04) 48%, rgba(2, 6, 18, 0.4) 100%),
                radial-gradient(circle at center, transparent 22%, rgba(1, 6, 19, 0.08) 100%);
        }

        .ai-fx-layer {
            position: absolute;
            inset: 0;
            z-index: 3;
            pointer-events: none;
        }

        .ai-fx-cluster {
            position: absolute;
            min-width: 238px;
            max-width: 282px;
            animation-fill-mode: both;
        }

        .ai-fx-cluster::before {
            content: '';
            position: absolute;
            inset: -14px -16px;
            border-radius: 999px;
            border: 1px solid rgba(119, 231, 255, 0.22);
            opacity: 0.62;
        }

        .ai-fx-cluster::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            right: 12px;
            top: -7px;
            background: #74f8ff;
            box-shadow: 0 0 14px rgba(124, 248, 255, 0.9);
            transform-origin: -72px 24px;
        }

        .ai-fx-card {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid rgba(134, 214, 255, 0.42);
            background: linear-gradient(135deg, rgba(8, 38, 78, 0.72), rgba(7, 83, 118, 0.56));
            color: #dff6ff;
            font-family: 'Sora', sans-serif;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            box-shadow: inset 0 0 0 1px rgba(170, 229, 255, 0.1), 0 14px 28px rgba(1, 20, 50, 0.35);
            overflow: hidden;
        }

        .ai-fx-card::before {
            content: '';
            position: absolute;
            inset: 1px;
            border-radius: inherit;
            pointer-events: none;
            opacity: 0;
        }

        .ai-fx-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(100deg, rgba(255, 255, 255, 0) 20%, rgba(154, 243, 255, 0.3) 50%, rgba(255, 255, 255, 0) 80%);
            transform: translateX(-130%);
            animation: aiSweep 3.2s ease-in-out infinite;
        }

        .ai-fx-card i {
            font-size: 0.84rem;
            color: #74efff;
            filter: drop-shadow(0 0 10px rgba(70, 235, 255, 0.6));
        }

        .ai-fx-label {
            color: #90efff;
            font-weight: 700;
        }

        .ai-typewriter {
            color: #f0fcff;
            font-weight: 700;
            min-width: 122px;
            display: inline-block;
            position: relative;
        }

        .ai-typewriter::after {
            content: '|';
            margin-left: 2px;
            color: #7ff2ff;
            animation: aiCaretBlink 0.8s steps(1, end) infinite;
        }

        .ai-fx-tl {
            top: 20%;
            left: 7%;
            animation: aiFloatHover 7.4s ease-in-out infinite;
        }

        .ai-fx-tl::before {
            animation: aiPulseRing 2.7s ease-in-out infinite;
        }

        .ai-fx-tl::after {
            animation: aiOrbitDot 2.7s linear infinite;
        }

        .ai-fx-tl .ai-fx-card {
            animation: aiCardBreathe 3.1s ease-in-out infinite;
        }

        .ai-fx-tr {
            top: 25%;
            right: 6%;
            animation: aiDriftSide 9.8s ease-in-out infinite;
        }

        .ai-fx-tr::before {
            animation: aiScanHalo 3.6s linear infinite;
        }

        .ai-fx-tr::after {
            animation: aiDotPing 1.9s ease-in-out infinite;
        }

        .ai-fx-tr .ai-fx-card::before {
            opacity: 0.68;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(139, 243, 255, 0.45) 50%, rgba(255, 255, 255, 0) 100%);
            animation: aiScanLine 2.4s linear infinite;
        }

        .ai-fx-ml {
            top: 62%;
            left: 23%;
            animation: aiFloatDiagonal 8.4s ease-in-out infinite;
        }

        .ai-fx-ml::before {
            animation: aiPulseRing 4.3s ease-in-out infinite;
        }

        .ai-fx-ml::after {
            animation: aiDataBlink 1.45s steps(2, end) infinite;
        }

        .ai-fx-ml .ai-fx-card {
            animation: aiHoloFlicker 4.1s steps(2, end) infinite;
        }

        .ai-fx-br {
            top: 74%;
            right: 9%;
            animation: aiHoverWide 10.4s ease-in-out infinite;
        }

        .ai-fx-br::before {
            animation: aiRadarRing 3.8s ease-in-out infinite;
        }

        .ai-fx-br::after {
            animation: aiOrbitDot 3.4s linear infinite reverse;
            transform-origin: -82px 22px;
        }

        .ai-fx-br .ai-fx-card::before {
            opacity: 0.55;
            background:
                linear-gradient(90deg, rgba(140, 244, 255, 0.08) 1px, transparent 1px),
                linear-gradient(rgba(140, 244, 255, 0.08) 1px, transparent 1px);
            background-size: 18px 18px;
            animation: aiGridShift 5.8s linear infinite;
        }

        @keyframes aiFloatHover {
            0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
            50% { transform: translate3d(0, -9px, 0) rotate(-1.4deg); }
        }

        @keyframes aiDriftSide {
            0%, 100% { transform: translate3d(0, 0, 0); }
            25% { transform: translate3d(7px, -4px, 0); }
            75% { transform: translate3d(-8px, 5px, 0); }
        }

        @keyframes aiFloatDiagonal {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(10px, 9px, 0); }
        }

        @keyframes aiHoverWide {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50% { transform: translate3d(-6px, -10px, 0) scale(1.02); }
        }

        @keyframes aiPulseRing {
            0%, 100% { opacity: 0.32; transform: scale(0.96); }
            50% { opacity: 0.7; transform: scale(1.06); }
        }

        @keyframes aiOrbitDot {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes aiDotPing {
            0%, 100% { opacity: 0.35; transform: scale(0.82); }
            45% { opacity: 1; transform: scale(1.3); }
        }

        @keyframes aiSweep {
            0%, 24% { transform: translateX(-130%); }
            60% { transform: translateX(130%); }
            100% { transform: translateX(130%); }
        }

        @keyframes aiCardBreathe {
            0%, 100% { box-shadow: inset 0 0 0 1px rgba(170, 229, 255, 0.1), 0 14px 28px rgba(1, 20, 50, 0.35); }
            50% { box-shadow: inset 0 0 0 1px rgba(186, 244, 255, 0.22), 0 18px 32px rgba(1, 20, 50, 0.45); }
        }

        @keyframes aiScanHalo {
            0% { transform: scale(0.96) rotate(0deg); opacity: 0.2; }
            50% { transform: scale(1.08) rotate(180deg); opacity: 0.78; }
            100% { transform: scale(0.96) rotate(360deg); opacity: 0.2; }
        }

        @keyframes aiScanLine {
            0% { transform: translateX(-130%); }
            100% { transform: translateX(130%); }
        }

        @keyframes aiDataBlink {
            0%, 100% { opacity: 0.38; }
            50% { opacity: 1; }
        }

        @keyframes aiHoloFlicker {
            0%, 18%, 22%, 50%, 54%, 100% {
                filter: brightness(1) saturate(1);
                transform: translate3d(0, 0, 0);
            }
            20% {
                filter: brightness(1.16) saturate(1.18);
                transform: translate3d(1px, 0, 0);
            }
            52% {
                filter: brightness(0.94) saturate(0.95);
                transform: translate3d(-1px, 0, 0);
            }
        }

        @keyframes aiRadarRing {
            0% { opacity: 0.2; transform: scale(0.88); }
            50% { opacity: 0.82; transform: scale(1.12); }
            100% { opacity: 0.2; transform: scale(0.88); }
        }

        @keyframes aiGridShift {
            0% { background-position: 0 0, 0 0; }
            100% { background-position: 18px 0, 0 18px; }
        }

        @keyframes aiCaretBlink {
            0%, 49% { opacity: 1; }
            50%, 100% { opacity: 0.2; }
        }

        .ai-static-text {
            color: #e9fbff;
            font-weight: 700;
            letter-spacing: 0.015em;
            white-space: nowrap;
        }

        .ai-fx-writer {
            animation: aiWriterFloat 7.2s ease-in-out infinite;
        }

        .ai-fx-writer::before {
            border-color: rgba(122, 226, 255, 0.3);
            animation: aiPulseRing 3s ease-in-out infinite;
        }

        .ai-fx-writer::after {
            animation: aiDotPing 1.7s ease-in-out infinite;
        }

        .ai-fx-gear {
            animation: aiGearDrift 8.4s ease-in-out infinite;
        }

        .ai-fx-gear::before {
            border-style: dashed;
            border-color: rgba(124, 237, 255, 0.36);
            animation: aiRingSpin 6.8s linear infinite;
        }

        .ai-fx-gear::after {
            animation: aiOrbitDot 2.2s linear infinite;
        }

        .ai-card-gear {
            gap: 10px;
            padding: 10px 14px 10px 12px;
        }

        .gear-stack {
            position: relative;
            width: 28px;
            height: 28px;
            display: inline-block;
        }

        .gear-main,
        .gear-mini {
            position: absolute;
            color: #82f3ff;
            filter: drop-shadow(0 0 10px rgba(118, 241, 255, 0.65));
        }

        .gear-main {
            top: 4px;
            left: 4px;
            font-size: 1rem;
            animation: gearSpin 2.4s linear infinite;
        }

        .gear-mini {
            right: 0;
            bottom: 0;
            font-size: 0.62rem;
            animation: gearSpinReverse 1.8s linear infinite;
        }

        .ai-fx-eq {
            animation: aiEqFloat 7.8s ease-in-out infinite;
        }

        .ai-fx-eq::before {
            border-color: rgba(108, 220, 255, 0.28);
            animation: aiPulseRing 4.1s ease-in-out infinite;
        }

        .ai-fx-eq::after {
            animation: aiDataBlink 1.2s steps(2, end) infinite;
        }

        .ai-card-eq {
            gap: 10px;
            padding: 10px 14px;
        }

        .eq-icon i {
            font-size: 0.9rem;
            color: #76eaff;
            filter: drop-shadow(0 0 9px rgba(106, 236, 255, 0.6));
        }

        .eq-bars {
            display: inline-flex;
            align-items: flex-end;
            gap: 3px;
            height: 14px;
            margin-left: 2px;
        }

        .eq-bars span {
            width: 3px;
            border-radius: 4px;
            background: linear-gradient(180deg, #a8fbff 0%, #4fdfff 100%);
            box-shadow: 0 0 8px rgba(112, 240, 255, 0.55);
            animation: eqBounce 0.95s ease-in-out infinite;
        }

        .eq-bars span:nth-child(1) { height: 5px; animation-delay: 0s; }
        .eq-bars span:nth-child(2) { height: 9px; animation-delay: 0.1s; }
        .eq-bars span:nth-child(3) { height: 12px; animation-delay: 0.2s; }
        .eq-bars span:nth-child(4) { height: 8px; animation-delay: 0.3s; }
        .eq-bars span:nth-child(5) { height: 6px; animation-delay: 0.4s; }

        .ai-fx-radar {
            animation: aiRadarHover 9.2s ease-in-out infinite;
        }

        .ai-fx-radar::before {
            border-color: rgba(126, 234, 255, 0.3);
            animation: aiRadarRingWide 4.2s ease-in-out infinite;
        }

        .ai-fx-radar::after {
            animation: aiOrbitDot 3.4s linear infinite reverse;
        }

        .ai-card-radar {
            gap: 10px;
            padding: 10px 14px;
        }

        .radar-stack {
            position: relative;
            width: 18px;
            height: 18px;
            display: inline-block;
        }

        .radar-ring {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            border: 1px solid rgba(128, 236, 255, 0.75);
            opacity: 0;
            animation: radarExpand 2.2s ease-out infinite;
        }

        .radar-ring.r2 {
            animation-delay: 0.7s;
        }

        .radar-dot {
            position: absolute;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #8ff7ff;
            box-shadow: 0 0 12px rgba(143, 247, 255, 0.85);
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }

        .ai-fx-writer .ai-fx-card::before,
        .ai-fx-gear .ai-fx-card::before,
        .ai-fx-eq .ai-fx-card::before,
        .ai-fx-radar .ai-fx-card::before {
            opacity: 0;
        }

        .ai-fx-writer .ai-fx-card::after {
            animation: aiSweep 2.6s ease-in-out infinite;
        }

        .ai-fx-gear .ai-fx-card::after {
            animation: aiSweep 4.1s ease-in-out infinite;
        }

        .ai-fx-eq .ai-fx-card::after {
            animation: aiSweep 3.7s ease-in-out infinite;
        }

        .ai-fx-radar .ai-fx-card::after {
            animation: aiSweep 5s ease-in-out infinite;
        }

        @keyframes aiWriterFloat {
            0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
            50% { transform: translate3d(0, -9px, 0) rotate(-1deg); }
        }

        @keyframes aiGearDrift {
            0%, 100% { transform: translate3d(0, 0, 0); }
            25% { transform: translate3d(7px, -4px, 0); }
            75% { transform: translate3d(-6px, 6px, 0); }
        }

        @keyframes aiEqFloat {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(8px, 8px, 0); }
        }

        @keyframes aiRadarHover {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50% { transform: translate3d(-6px, -9px, 0) scale(1.02); }
        }

        @keyframes aiRingSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes gearSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes gearSpinReverse {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(-360deg); }
        }

        @keyframes eqBounce {
            0%, 100% { transform: scaleY(0.65); opacity: 0.75; }
            50% { transform: scaleY(1.2); opacity: 1; }
        }

        @keyframes radarExpand {
            0% { transform: scale(0.4); opacity: 0.75; }
            100% { transform: scale(1.4); opacity: 0; }
        }

        @keyframes aiRadarRingWide {
            0%, 100% { transform: scale(0.9); opacity: 0.18; }
            50% { transform: scale(1.1); opacity: 0.6; }
        }

        /* Override to match index-like floating coding icons */
        .ai-fx-cluster,
        .ai-fx-card {
            display: none !important;
        }

        .ai-fx-layer {
            pointer-events: none;
        }

        .floating-tech {
            position: absolute;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            opacity: 0.36;
            filter: drop-shadow(0 0 16px rgba(62, 220, 255, 0.42));
            transform-origin: center;
            will-change: transform, opacity;
        }

        .floating-tech::before {
            content: '';
            position: absolute;
            inset: -14px -16px;
            border-radius: 999px;
            background: radial-gradient(circle at 50% 50%, rgba(74, 222, 255, 0.1), rgba(3, 22, 52, 0));
            z-index: -1;
        }

        .fx-icon {
            width: 44px;
            height: 44px;
            position: relative;
            border-radius: 50%;
            border: 1px solid rgba(126, 226, 255, 0.26);
            background: radial-gradient(circle at 30% 30%, rgba(27, 146, 189, 0.42), rgba(7, 29, 64, 0.22));
            display: grid;
            place-items: center;
            backdrop-filter: blur(1px);
            box-shadow: inset 0 0 0 1px rgba(139, 230, 255, 0.1), 0 0 18px rgba(63, 213, 255, 0.23);
        }

        .fx-icon i {
            color: #6eeeff;
            font-size: 1.05rem;
            display: inline-block;
            transform-origin: center;
            transform-style: preserve-3d;
            backface-visibility: hidden;
            text-shadow: 0 0 12px rgba(91, 236, 255, 0.74);
        }

        .fx-glow-dot {
            position: absolute;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #7cf5ff;
            box-shadow: 0 0 12px rgba(124, 245, 255, 0.95);
            right: -6px;
            top: -8px;
            animation: fxDotPulse 2.2s ease-in-out infinite;
        }

        .fx-writing {
            color: #d6f8ff;
            font-family: 'Sora', sans-serif;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            text-shadow: 0 0 8px rgba(112, 234, 255, 0.35);
            white-space: nowrap;
        }

        .fx-writing .ai-typewriter {
            min-width: 132px;
        }

        .floating-tech:is(.fx-writer, .fx-rocket-jet, .fx-face, .fx-location, .fx-protected, .fx-storage) {
            min-width: 228px;
            height: 54px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(145, 227, 255, 0.34);
            background: linear-gradient(90deg, rgba(3, 18, 42, 0.72) 0%, rgba(10, 48, 86, 0.32) 50%, rgba(3, 18, 42, 0.72) 100%);
            box-shadow: inset 0 0 0 1px rgba(173, 233, 255, 0.14), 0 12px 24px rgba(1, 14, 36, 0.34);
            backdrop-filter: blur(2px);
        }

        .floating-tech:is(.fx-writer, .fx-rocket-jet, .fx-face, .fx-location, .fx-protected, .fx-storage)::before {
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(170, 237, 255, 0.14), rgba(170, 237, 255, 0));
            opacity: 0.72;
        }

        .floating-tech:is(.fx-writer, .fx-rocket-jet, .fx-face, .fx-location, .fx-protected, .fx-storage) .fx-writing {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: 0.05em;
            font-size: 0.74rem;
        }

        .floating-tech:is(.fx-writer, .fx-rocket-jet, .fx-face, .fx-location, .fx-protected, .fx-storage) .fx-writing .ai-typewriter {
            min-width: 142px;
        }

        .fx-writer {
            top: 22%;
            left: 7%;
            animation: techFloatWriter 10.2s ease-in-out infinite;
        }

        .fx-gear {
            top: 24%;
            right: 8%;
            animation: techFloatGear 13.4s ease-in-out infinite;
        }

        .fx-code {
            top: 64%;
            left: 20%;
            animation: techFloatCode 11.8s ease-in-out infinite;
        }

        .fx-pulse {
            top: 78%;
            right: 9%;
            animation: techFloatPulse 14.2s ease-in-out infinite;
        }

        .fx-rocket-jet {
            top: 40%;
            left: 7%;
            animation: techFloatRocketJet 9.8s ease-in-out infinite;
            opacity: 0.36;
        }

        .rocket-pack {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 64px;
            margin-right: 2px;
            animation: rocketBodyDrift 1.4s ease-in-out infinite;
        }

        .rocket-body {
            position: absolute;
            left: 50%;
            top: 1px;
            transform: translateX(-50%);
            display: grid;
            place-items: center;
            width: 22px;
            height: 22px;
            z-index: 2;
        }

        .rocket-body i {
            color: #8af4ff;
            font-size: 1.02rem;
            line-height: 1;
            text-shadow: 0 0 14px rgba(91, 236, 255, 0.85);
            transform: rotate(-45deg);
        }

        .rocket-flame {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 32% 32% 55% 55%;
            pointer-events: none;
        }

        .rocket-flame-main {
            top: 22px;
            width: 8px;
            height: 16px;
            background: linear-gradient(180deg, rgba(255, 232, 149, 0.95) 0%, rgba(255, 140, 64, 0.92) 62%, rgba(255, 80, 70, 0.85) 100%);
            filter: blur(0.3px);
            animation: rocketFlameMain 0.24s ease-in-out infinite;
            z-index: 1;
        }

        .rocket-flame-a {
            top: 24px;
            left: 50%;
            width: 5px;
            height: 10px;
            background: linear-gradient(180deg, rgba(255, 214, 122, 0.86), rgba(255, 111, 76, 0.86));
            animation: rocketFlameSideLeft 0.22s ease-in-out infinite;
            z-index: 0;
        }

        .rocket-flame-b {
            top: 24px;
            left: 50%;
            width: 5px;
            height: 10px;
            background: linear-gradient(180deg, rgba(255, 214, 122, 0.86), rgba(255, 111, 76, 0.86));
            animation: rocketFlameSideRight 0.26s ease-in-out infinite reverse;
            z-index: 0;
        }

        .rocket-trail {
            position: absolute;
            left: 50%;
            top: 34px;
            width: 8px;
            height: 22px;
            transform: translateX(-50%);
            border-radius: 0 0 14px 14px;
            background: linear-gradient(180deg, rgba(239, 252, 255, 0.96) 0%, rgba(205, 243, 255, 0.84) 40%, rgba(175, 231, 255, 0.5) 72%, rgba(133, 213, 255, 0.1) 100%);
            filter: blur(0.3px);
            animation: rocketTrailPulse 0.32s ease-in-out infinite;
            z-index: 0;
            pointer-events: none;
        }

        .rocket-smoke {
            position: absolute;
            left: 50%;
            bottom: 4px;
            border-radius: 50%;
            background: rgba(204, 244, 255, 0.62);
            filter: blur(0.1px);
            pointer-events: none;
            opacity: 0;
            z-index: 0;
        }

        .rocket-smoke.m1 {
            width: 5px;
            height: 5px;
            animation: rocketSmokePuff 1.15s ease-out infinite;
        }

        .rocket-smoke.m2 {
            width: 4px;
            height: 4px;
            animation: rocketSmokePuff 1.15s ease-out infinite 0.3s;
        }

        .rocket-smoke.m3 {
            width: 3px;
            height: 3px;
            animation: rocketSmokePuff 1.15s ease-out infinite 0.58s;
        }

        .rocket-spark {
            position: absolute;
            left: 50%;
            top: 34px;
            width: 2px;
            height: 2px;
            border-radius: 50%;
            background: #9af9ff;
            box-shadow: 0 0 10px rgba(154, 249, 255, 0.8);
            pointer-events: none;
            opacity: 0;
        }

        .rocket-spark.s1 {
            animation: rocketSparkTrail 0.92s linear infinite;
        }

        .rocket-spark.s2 {
            width: 2px;
            height: 2px;
            animation: rocketSparkTrail 0.85s linear infinite 0.2s;
        }

        .rocket-spark.s3 {
            width: 2px;
            height: 2px;
            animation: rocketSparkTrail 0.8s linear infinite 0.42s;
        }

        .fx-star {
            top: 12%;
            left: 31%;
            animation: techFloatStar 12.4s ease-in-out infinite;
        }

        .fx-satellite {
            top: 13%;
            right: 23%;
            animation: techFloatSatellite 15.4s ease-in-out infinite;
        }

        .fx-cpu {
            top: 48%;
            left: 4.5%;
            animation: techFloatCpu 13.1s ease-in-out infinite;
        }

        .fx-network {
            top: 49%;
            right: 4.5%;
            animation: techFloatNetwork 14.3s ease-in-out infinite;
        }

        .fx-rocket {
            top: 84%;
            left: 9%;
            animation: techFloatRocket 11.3s ease-in-out infinite;
        }

        .fx-shield {
            top: 86%;
            right: 21%;
            animation: techFloatShield 16.1s ease-in-out infinite;
        }

        .fx-location {
            top: 22%;
            right: 7%;
            animation: techFloatLocation 12.1s ease-in-out infinite;
        }

        .fx-face {
            top: 58%;
            left: 7%;
            animation: techFloatFace 10.9s ease-in-out infinite;
        }

        .fx-protected {
            top: 40%;
            right: 7%;
            animation: techFloatProtected 13.7s ease-in-out infinite;
        }

        .fx-storage {
            top: 58%;
            right: 7%;
            animation: techFloatStorage 12.8s ease-in-out infinite;
        }

        .fx-sync {
            top: 27%;
            left: 21%;
            animation: techFloatSync 12.5s ease-in-out infinite;
        }

        .fx-cloud {
            top: 57%;
            left: 2.2%;
            animation: techFloatCloud 13.2s ease-in-out infinite;
        }

        .fx-analytics {
            top: 74%;
            left: 23%;
            animation: techFloatAnalytics 14.1s ease-in-out infinite;
        }

        .fx-writer .fx-icon i {
            animation: writerTap 1.2s steps(2, end) infinite;
        }

        .fx-gear .fx-icon i {
            animation: techGearSpin 4s linear infinite;
        }

        .fx-code .fx-icon i {
            animation: techCodeBlink 1.6s ease-in-out infinite;
        }

        .fx-pulse .fx-icon i {
            animation: techPulseGlow 2s ease-in-out infinite;
        }

        .fx-star .fx-icon i {
            animation: techSparkle 1.9s ease-in-out infinite;
        }

        .fx-satellite .fx-icon i {
            animation: techSatellite 3.8s linear infinite;
        }

        .fx-cpu .fx-icon i {
            animation: techCodeBlink 1.3s ease-in-out infinite;
        }

        .fx-network .fx-icon i {
            animation: techPulseGlow 2.3s ease-in-out infinite;
        }

        .fx-rocket .fx-icon i {
            animation: techRocket 2.5s ease-in-out infinite;
        }

        .fx-shield .fx-icon i {
            animation: techShield 2.8s ease-in-out infinite;
        }

        .fx-location .fx-icon i {
            animation: locationPin 1.8s ease-in-out infinite;
        }

        .fx-location .fx-icon::after {
            content: '';
            position: absolute;
            inset: 6px;
            border: 1px solid rgba(140, 236, 255, 0.46);
            border-radius: 50%;
            animation: locationRing 1.8s ease-out infinite;
            pointer-events: none;
        }

        .fx-face .fx-icon {
            overflow: hidden;
        }

        .fx-face .fx-icon::before {
            content: '';
            position: absolute;
            inset: 7px;
            border: 1px solid rgba(124, 235, 255, 0.45);
            border-radius: 9px;
            pointer-events: none;
        }

        .fx-face .fx-icon::after {
            content: '';
            position: absolute;
            left: 8px;
            right: 8px;
            top: 9px;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(62, 227, 255, 0), rgba(162, 247, 255, 0.96), rgba(62, 227, 255, 0));
            animation: faceScanLine 2.1s linear infinite;
            pointer-events: none;
        }

        .fx-face .fx-icon i {
            animation: facePulse 2s ease-in-out infinite;
        }

        .fx-protected .fx-icon i {
            animation: protectPulse 2.2s ease-in-out infinite;
        }

        .fx-protected .fx-icon::after {
            content: '';
            position: absolute;
            width: 7px;
            height: 7px;
            right: 5px;
            bottom: 5px;
            border-radius: 50%;
            background: #72f5a8;
            box-shadow: 0 0 8px rgba(114, 245, 168, 0.92);
            animation: protectDot 1.5s ease-in-out infinite;
            pointer-events: none;
        }

        .fx-storage .fx-icon i {
            animation: storageBob 2.4s ease-in-out infinite;
        }

        .fx-storage .fx-icon::after {
            content: '';
            position: absolute;
            left: 10px;
            right: 10px;
            bottom: 7px;
            height: 3px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(88, 228, 255, 0.25), rgba(184, 246, 255, 0.96), rgba(88, 228, 255, 0.25));
            animation: storageFlow 1.8s ease-in-out infinite;
            pointer-events: none;
        }

        .fx-sync .fx-icon i {
            animation: techGearSpin 3.8s linear infinite;
        }

        .fx-cloud .fx-icon i {
            animation: cloudPulse 2.1s ease-in-out infinite;
        }

        .fx-analytics .fx-icon i {
            animation: graphPulse 1.9s ease-in-out infinite;
        }

        .fx-rocket-jet .fx-writing .ai-typewriter {
            min-width: 152px;
        }

        @keyframes techFloatWriter {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(0, -10px, 0); }
        }

        @keyframes techFloatGear {
            0%, 100% { transform: translate3d(0, 0, 0); }
            25% { transform: translate3d(8px, -4px, 0); }
            75% { transform: translate3d(-7px, 7px, 0); }
        }

        @keyframes techFloatCode {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(10px, 6px, 0); }
        }

        @keyframes techFloatPulse {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50% { transform: translate3d(-8px, -9px, 0) scale(1.03); }
        }

        @keyframes techFloatRocketJet {
            0%, 100% { transform: translate3d(0, 0, 0); }
            30% { transform: translate3d(10px, -6px, 0); }
            60% { transform: translate3d(-5px, 8px, 0); }
        }

        @keyframes techFloatStar {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(7px, -7px, 0); }
        }

        @keyframes techFloatSatellite {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(-8px, -5px, 0); }
        }

        @keyframes techFloatCpu {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(7px, 5px, 0); }
        }

        @keyframes techFloatNetwork {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(-7px, 8px, 0); }
        }

        @keyframes techFloatRocket {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(8px, -10px, 0); }
        }

        @keyframes techFloatShield {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(-8px, -6px, 0); }
        }

        @keyframes techFloatLocation {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(-7px, -6px, 0); }
        }

        @keyframes techFloatFace {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(8px, -5px, 0); }
        }

        @keyframes techFloatProtected {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(-6px, 7px, 0); }
        }

        @keyframes techFloatStorage {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(7px, 7px, 0); }
        }

        @keyframes techFloatSync {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(8px, -6px, 0); }
        }

        @keyframes techFloatCloud {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(6px, 8px, 0); }
        }

        @keyframes techFloatAnalytics {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(8px, -8px, 0); }
        }

        @keyframes fxDotPulse {
            0%, 100% { opacity: 0.36; transform: scale(0.86); }
            50% { opacity: 1; transform: scale(1.22); }
        }

        @keyframes writerTap {
            0%, 100% { transform: translateY(0); opacity: 0.82; }
            50% { transform: translateY(-1px); opacity: 1; }
        }

        @keyframes techGearSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes techCodeBlink {
            0%, 100% { opacity: 0.66; transform: scale(0.95); }
            50% { opacity: 1; transform: scale(1.08); }
        }

        @keyframes techPulseGlow {
            0%, 100% { transform: scale(0.95); text-shadow: 0 0 12px rgba(91, 236, 255, 0.6); }
            50% { transform: scale(1.08); text-shadow: 0 0 20px rgba(129, 245, 255, 0.95); }
        }

        @keyframes techSparkle {
            0%, 100% { transform: scale(0.9); opacity: 0.68; }
            50% { transform: scale(1.15); opacity: 1; }
        }

        @keyframes techSatellite {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes techRocket {
            0%, 100% { transform: translateY(0) rotate(-8deg); }
            50% { transform: translateY(-3px) rotate(6deg); }
        }

        @keyframes techShield {
            0%, 100% { opacity: 0.66; transform: scale(0.94); }
            50% { opacity: 1; transform: scale(1.07); }
        }

        @keyframes locationPin {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(0.96); }
            20% { transform: translateY(-3px) rotate(-12deg) scale(1.02); }
            45% { transform: translateY(0) rotate(8deg) scale(1.04); }
            70% { transform: translateY(-2px) rotate(-6deg) scale(1); }
        }

        @keyframes locationRing {
            0% { opacity: 0.62; transform: scale(0.65); }
            100% { opacity: 0; transform: scale(1.25); }
        }

        @keyframes faceScanLine {
            0% { top: 10px; opacity: 0.16; }
            10% { opacity: 0.92; }
            50% { top: 28px; opacity: 0.9; }
            90% { opacity: 0.25; }
            100% { top: 10px; opacity: 0.16; }
        }

        @keyframes facePulse {
            0%, 100% { transform: perspective(280px) rotateY(0deg) scale(0.94); opacity: 0.82; }
            45% { transform: perspective(280px) rotateY(180deg) scale(1.02); opacity: 1; }
            55% { transform: perspective(280px) rotateY(180deg) scale(1.02); opacity: 1; }
        }

        @keyframes protectPulse {
            0%, 100% { transform: perspective(260px) rotateX(0deg) scale(0.94); opacity: 0.75; }
            40% { transform: perspective(260px) rotateX(22deg) scale(1.03); opacity: 1; }
            70% { transform: perspective(260px) rotateX(-16deg) scale(1.01); opacity: 0.95; }
        }

        @keyframes protectDot {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.18); }
        }

        @keyframes storageBob {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(0.95); }
            25% { transform: translateY(-2px) rotate(-12deg) scale(1); }
            50% { transform: translateY(-3px) rotate(8deg) scale(1.05); }
            75% { transform: translateY(-1px) rotate(-6deg) scale(1); }
        }

        @keyframes storageFlow {
            0%, 100% { opacity: 0.35; transform: scaleX(0.82); }
            50% { opacity: 1; transform: scaleX(1); }
        }

        @keyframes cloudPulse {
            0%, 100% { opacity: 0.66; transform: scale(0.95); }
            50% { opacity: 1; transform: scale(1.08); }
        }

        @keyframes graphPulse {
            0%, 100% { opacity: 0.64; transform: scale(0.94); }
            50% { opacity: 1; transform: scale(1.07); }
        }

        @keyframes rocketBodyDrift {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }

        @keyframes rocketFlameMain {
            0%, 100% { height: 17px; opacity: 0.76; transform: translateX(-50%) scaleY(0.92); }
            50% { height: 24px; opacity: 1; transform: translateX(-50%) scaleY(1.08); }
        }

        @keyframes rocketFlameSideLeft {
            0%, 100% { height: 11px; opacity: 0.62; transform: translateX(-120%) scaleY(0.9); }
            50% { height: 15px; opacity: 0.95; transform: translateX(-140%) scaleY(1.07); }
        }

        @keyframes rocketFlameSideRight {
            0%, 100% { height: 11px; opacity: 0.62; transform: translateX(20%) scaleY(0.9); }
            50% { height: 15px; opacity: 0.95; transform: translateX(40%) scaleY(1.07); }
        }

        @keyframes rocketSparkTrail {
            0% { opacity: 0; transform: translate3d(-50%, 0, 0) scale(0.55); }
            18% { opacity: 1; }
            100% { opacity: 0; transform: translate3d(-50%, 24px, 0) scale(0.08); }
        }

        @keyframes rocketTrailPulse {
            0%, 100% { height: 28px; opacity: 0.7; }
            50% { height: 36px; opacity: 0.95; }
        }

        @keyframes rocketSmokePuff {
            0% { opacity: 0; transform: translate(-50%, 0) scale(0.5); }
            22% { opacity: 0.64; }
            100% { opacity: 0; transform: translate(-50%, 14px) scale(1.4); }
        }

        .hero-content-shell {
            position: relative;
            z-index: 4;
            min-height: 100svh;
            padding: 24px 22px 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .hero-topbar {
            width: min(760px, calc(100% - 8px));
            margin-top: 2px;
        }

        .hero-topbar-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 22px;
            border-radius: 999px;
            border: 1px solid var(--soft-border);
            background:
                linear-gradient(90deg, rgba(8, 22, 48, 0.86), rgba(17, 40, 76, 0.44) 50%, rgba(8, 22, 48, 0.86));
            backdrop-filter: blur(4px);
            box-shadow: inset 0 0 0 1px rgba(152, 212, 255, 0.14), 0 14px 30px rgba(5, 25, 56, 0.3);
        }

        .hero-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #ecf5ff;
            text-decoration: none;
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 1.22rem;
        }

        .hero-brand img {
            width: 36px;
            height: 36px;
            object-fit: contain;
            padding: 5px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(4, 251, 53, 0.22), rgba(65, 122, 236, 0.42));
            border: 1px solid rgba(121, 171, 255, 0.55);
            filter: drop-shadow(0 0 12px rgba(65, 122, 236, 0.5));
        }

        .hero-links {
            display: inline-flex;
            align-items: center;
            gap: 16px;
        }

        .hero-links a {
            text-decoration: none;
            color: #deebff;
            font-weight: 700;
            font-size: 1rem;
            transition: opacity 0.2s ease;
        }

        .hero-links a:hover {
            opacity: 0.78;
        }

        .hero-center {
            flex: 1;
            width: 100%;
            max-width: 980px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding-top: 28px;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 15px;
            border: 1px solid rgba(121, 171, 255, 0.38);
            background: rgba(55, 90, 156, 0.48);
            color: #dbeaff;
            font-weight: 700;
            font-size: 0.84rem;
            margin-bottom: 18px;
            letter-spacing: 0.02em;
        }

        .hero-title {
            font-family: 'Sora', sans-serif;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.03;
            font-size: clamp(1.9rem, 4vw, 4.1rem);
            max-width: 920px;
            margin-bottom: 14px;
            text-shadow: 0 0 22px rgba(106, 168, 255, 0.24);
            text-transform: uppercase;
        }

        .hero-title-main {
            display: block;
            color: #f2f8ff;
            text-shadow: 0 0 16px rgba(145, 196, 255, 0.28);
            font-size: clamp(2.3rem, 5.2vw, 5rem);
            line-height: 1;
        }

        .hero-title-tagline {
            display: block;
            margin-top: 6px;
            background: linear-gradient(135deg, #54f8d2 0%, #ffffff 44%, #f5fcff 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: none;
            font-size: clamp(1.55rem, 3.65vw, 3.3rem);
            line-height: 1.05;
            white-space: nowrap;
        }

        .hero-desc {
            font-size: clamp(0.95rem, 1.08vw, 1.08rem);
            line-height: 1.3;
            color: #b8ebff;
            max-width: fit-content;
            margin: 0 auto 30px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid rgba(131, 229, 255, 0.5);
            background: linear-gradient(90deg, rgba(8, 52, 95, 0.58), rgba(11, 88, 120, 0.62));
            box-shadow: 0 10px 24px rgba(0, 91, 142, 0.25);
        }

        .hero-desc .ai-typewriter {
            min-width: clamp(176px, 28vw, 320px);
            text-align: center;
            color: inherit;
        }

        .hero-desc .ai-typewriter::after {
            color: #b8f6ff;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-btn {
            text-decoration: none;
            border-radius: 999px;
            min-width: 220px;
            height: 58px;
            padding: 0 24px;
            font-weight: 700;
            font-size: 1rem;
            transition: transform 0.22s ease, box-shadow 0.22s ease, opacity 0.2s ease, color 0.22s ease;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
            isolation: isolate;
        }

        .hero-btn:hover {
            transform: translateY(-2px);
        }

        .hero-btn-get {
            background: linear-gradient(135deg, #ebf8ff 0%, #ffffff 50%, #ddf8ff 100%);
            color: #112240;
            gap: 0.72rem;
            padding: 0 26px 0 14px;
            box-shadow: 0 14px 30px rgba(199, 225, 255, 0.25);
            border-color: rgba(190, 232, 255, 0.78);
            transition: transform 0.22s ease, box-shadow 0.22s ease, color 0.22s ease, background 0.28s ease;
        }

        .hero-btn-get::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #0da4ea 0%, #12c3d5 46%, #00d38c 100%);
            opacity: 0;
            transition: opacity 0.25s ease;
            z-index: 0;
        }

        .hero-btn-get .button__icon-wrapper {
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            position: relative;
            color: #172947;
            background-color: #ffffff;
            border-radius: 50%;
            display: grid;
            place-items: center;
            overflow: hidden;
            box-shadow: 0 6px 15px rgba(6, 25, 52, 0.24);
            transition: color 0.25s ease, background-color 0.25s ease, box-shadow 0.25s ease;
            z-index: 1;
        }

        .hero-btn-get span {
            position: relative;
            z-index: 1;
        }

        .hero-btn-get .button__icon-svg--copy {
            position: absolute;
            transform: translate(-150%, 150%);
        }

        .hero-btn-get:hover {
            color: #ffffff;
            box-shadow: 0 18px 36px rgba(2, 162, 248, 0.38);
            border-color: rgba(149, 230, 255, 0.95);
        }

        .hero-btn-get:hover::before {
            opacity: 1;
        }

        .hero-btn-get:hover .button__icon-wrapper {
            color: #05a8e8;
            background-color: #ffffff;
            box-shadow: 0 9px 18px rgba(7, 22, 44, 0.33);
        }

        .hero-btn-get:hover .button__icon-svg:first-child {
            transition: transform 0.3s ease-in-out;
            transform: translate(150%, -150%);
        }

        .hero-btn-get:hover .button__icon-svg--copy {
            transition: transform 0.3s ease-in-out 0.1s;
            transform: translate(0);
        }

        .hero-btn-know {
            color: #deebff;
            border: 1px solid rgba(130, 193, 255, 0.62);
            background: linear-gradient(135deg, rgba(15, 48, 92, 0.68), rgba(14, 33, 68, 0.95));
            gap: 0.65rem;
            box-shadow: inset 0 0 0 1px rgba(146, 212, 255, 0.12), 0 10px 25px rgba(3, 28, 70, 0.28);
            transition: transform 0.22s ease, box-shadow 0.22s ease, color 0.22s ease, background 0.25s ease, border-color 0.25s ease;
        }

        .hero-btn-know::before {
            content: '';
            position: absolute;
            top: -12%;
            left: -46%;
            width: 58%;
            height: 124%;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.08), rgba(123, 232, 255, 0.54), rgba(255, 255, 255, 0.08));
            transform: translateX(-190%) skewX(-18deg);
            transition: transform 0.55s ease;
            z-index: 0;
        }

        .hero-btn-know > * {
            position: relative;
            z-index: 1;
        }

        .hero-btn-know i {
            font-size: 0.88rem;
            transition: transform 0.25s ease;
        }

        .hero-btn-know:hover::before {
            transform: translateX(350%) skewX(-18deg);
        }

        .hero-btn-know:hover {
            color: #f1f8ff;
            border-color: rgba(152, 227, 255, 0.97);
            background: linear-gradient(135deg, rgba(14, 83, 140, 0.86), rgba(20, 52, 102, 0.95));
            box-shadow: inset 0 0 0 1px rgba(181, 240, 255, 0.2), 0 14px 30px rgba(0, 122, 224, 0.32);
        }

        .hero-btn-know:hover i {
            transform: translateX(4px);
        }

        @media (max-width: 992px) {
            .light-pillar-wrap {
                filter: contrast(1.18) saturate(1.28) brightness(1.08);
            }

            .pillar-aura-2,
            .pillar-aura-3 {
                opacity: 0.22;
            }

            .floating-tech {
                transform: none;
                opacity: 0.4;
            }

            .floating-tech:is(.fx-writer, .fx-rocket-jet, .fx-face, .fx-location, .fx-protected, .fx-storage) {
                min-width: 192px;
                height: 46px;
                padding: 6px 10px;
            }

            .floating-tech:is(.fx-writer, .fx-rocket-jet, .fx-face, .fx-location, .fx-protected, .fx-storage) .fx-writing {
                max-width: 118px;
                font-size: 0.66rem;
            }

            .floating-tech:is(.fx-writer, .fx-rocket-jet, .fx-face, .fx-location, .fx-protected, .fx-storage) .fx-writing .ai-typewriter {
                min-width: 112px;
            }

            .fx-icon {
                width: 36px;
                height: 36px;
            }

            .fx-writer { top: 21%; left: 2.2%; }
            .fx-rocket-jet { top: 39%; left: 2.2%; }
            .fx-face { top: 57%; left: 2.2%; }
            .fx-location { top: 21%; right: 2.2%; }
            .fx-protected { top: 39%; right: 2.2%; }
            .fx-storage { top: 57%; right: 2.2%; }

            .hero-title {
                font-size: clamp(2rem, 7vw, 3.5rem);
            }

            .hero-title-main {
                font-size: clamp(2.1rem, 6.2vw, 4rem);
            }

            .hero-title-tagline {
                font-size: clamp(1.45rem, 4.3vw, 2.5rem);
            }

            .hero-desc {
                font-size: clamp(1rem, 2.6vw, 1.18rem);
            }
        }

        @media (max-width: 768px) {
            .ai-fx-layer {
                display: block;
            }

            .light-pillar-wrap {
                filter: contrast(1.2) saturate(1.32) brightness(1.16);
            }

            .light-pillar-wrap::before {
                inset: -8% -24%;
                opacity: 0.95;
                filter: blur(20px);
                background:
                    radial-gradient(42% 70% at 52% 58%, rgba(66, 252, 236, 0.4) 0%, rgba(30, 178, 255, 0.26) 42%, rgba(0, 0, 0, 0) 78%);
            }

            .light-pillar-wrap::after {
                width: min(58vw, 380px);
                transform: translateX(-50%) rotate(1deg);
                opacity: 0.96;
                filter: blur(12px);
            }

            .hero-desc .ai-typewriter {
                min-width: 172px;
            }

            .pillar-aura {
                display: block;
            }

            .pillar-aura-1 {
                opacity: 0.36;
                filter: blur(24px);
            }

            .pillar-aura-2 {
                opacity: 0.2;
                filter: blur(20px);
            }

            .pillar-aura-3 {
                opacity: 0.16;
                filter: blur(16px);
            }

            .hero-vignette {
                background:
                    linear-gradient(90deg, rgba(2, 6, 18, 0.26) 0%, rgba(2, 9, 23, 0.02) 50%, rgba(2, 6, 18, 0.24) 100%),
                    radial-gradient(circle at center, transparent 16%, rgba(1, 6, 19, 0.05) 100%);
            }

            .floating-tech {
                transform: none;
                opacity: 0.42;
            }

            .fx-face,
            .fx-storage {
                display: none;
            }

            .floating-tech.fx-writer,
            .floating-tech.fx-rocket-jet,
            .floating-tech.fx-location,
            .floating-tech.fx-protected {
                min-width: 114px;
                height: 36px;
                padding: 5px 8px;
                border-color: rgba(145, 227, 255, 0.3);
                backdrop-filter: none;
                opacity: 0.64;
                box-shadow: inset 0 0 0 1px rgba(173, 233, 255, 0.16), 0 8px 18px rgba(1, 14, 36, 0.38);
            }

            .floating-tech.fx-writer .fx-writing,
            .floating-tech.fx-rocket-jet .fx-writing,
            .floating-tech.fx-location .fx-writing,
            .floating-tech.fx-protected .fx-writing {
                display: block !important;
                max-width: 70px;
                font-size: 0.58rem;
                letter-spacing: 0.035em;
                text-overflow: clip;
                overflow: hidden;
                white-space: nowrap;
                opacity: 0.95;
            }

            .floating-tech.fx-writer .fx-writing .ai-typewriter,
            .floating-tech.fx-rocket-jet .fx-writing .ai-typewriter,
            .floating-tech.fx-location .fx-writing .ai-typewriter,
            .floating-tech.fx-protected .fx-writing .ai-typewriter {
                min-width: 0;
            }

            .floating-tech.fx-rocket-jet {
                opacity: 0.5;
            }

            .fx-icon {
                width: 26px;
                height: 26px;
            }

            .fx-glow-dot {
                width: 5px;
                height: 5px;
                right: -2px;
                top: -5px;
                box-shadow: 0 0 12px rgba(124, 245, 255, 0.98);
            }

            .fx-writer { top: 19%; left: 0.8%; }
            .fx-rocket-jet { top: 34%; left: 0.8%; }
            .fx-location { top: 19%; right: 0.8%; }
            .fx-protected { top: 34%; right: 0.8%; }

            .fx-rocket-jet .rocket-pack {
                transform: scale(0.64);
                margin-right: 0;
            }

            body {
                overflow-y: auto;
            }

            .hero-wrapper,
            .hero-stage,
            .hero-content-shell {
                height: auto;
                min-height: 100vh;
            }

            .hero-content-shell {
                padding: 18px 16px 38px;
            }

            .hero-topbar {
                width: 100%;
                margin-top: 4px;
            }

            .hero-topbar-nav {
                padding: 8px 12px;
            }

            .hero-brand span {
                font-size: 1rem;
            }

            .hero-brand img {
                width: 30px;
                height: 30px;
                padding: 4px;
            }

            .hero-center {
                padding-top: 20px;
            }

            .hero-title-tagline {
                white-space: normal;
            }

            .hero-chip {
                margin-bottom: 16px;
            }

            .hero-actions {
                width: 100%;
                max-width: 430px;
            }

            .hero-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <main class="hero-wrapper">
            <section class="hero-stage">
                <div id="lightPillarRoot" class="light-pillar-wrap" aria-hidden="true"></div>
                <div class="pillar-aura pillar-aura-1" aria-hidden="true"></div>
                <div class="pillar-aura pillar-aura-2" aria-hidden="true"></div>
                <div class="pillar-aura pillar-aura-3" aria-hidden="true"></div>
                <div class="hero-vignette" aria-hidden="true"></div>
                <div class="ai-fx-layer" aria-hidden="true">
                    <div class="floating-tech fx-writer">
                        <span class="fx-icon"><i class="fa-solid fa-keyboard"></i></span>
                        <span class="fx-writing"><span class="ai-typewriter" data-words="writer|auto verify|smart typing" data-words-mobile="writer|auto|smart">writer</span></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                    <div class="floating-tech fx-rocket-jet">
                        <span class="rocket-pack">
                            <span class="rocket-body"><i class="fa-solid fa-rocket"></i></span>
                            <span class="rocket-flame rocket-flame-main"></span>
                            <span class="rocket-flame rocket-flame-a"></span>
                            <span class="rocket-flame rocket-flame-b"></span>
                            <span class="rocket-trail"></span>
                            <span class="rocket-smoke m1"></span>
                            <span class="rocket-smoke m2"></span>
                            <span class="rocket-smoke m3"></span>
                            <span class="rocket-spark s1"></span>
                            <span class="rocket-spark s2"></span>
                            <span class="rocket-spark s3"></span>
                        </span>
                        <span class="fx-writing"><span class="ai-typewriter" data-words="launch mode|booster online|ignite thrust" data-words-mobile="boost|ignite|launch">launch mode</span></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                    <div class="floating-tech fx-face">
                        <span class="fx-icon"><i class="fa-solid fa-user-check"></i></span>
                        <span class="fx-writing"><span class="ai-typewriter" data-words="face recognition|scan identity|match secured"></span></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                    <div class="floating-tech fx-location">
                        <span class="fx-icon"><i class="fa-solid fa-location-dot"></i></span>
                        <span class="fx-writing"><span class="ai-typewriter" data-words="location valid|gps lock|radius safe" data-words-mobile="gps|lock|radius">location valid</span></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                    <div class="floating-tech fx-protected">
                        <span class="fx-icon"><i class="fa-solid fa-lock"></i></span>
                        <span class="fx-writing"><span class="ai-typewriter" data-words="protected mode|security active|access guarded" data-words-mobile="secure|shield|guard">protected mode</span></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                    <div class="floating-tech fx-storage">
                        <span class="fx-icon"><i class="fa-solid fa-database"></i></span>
                        <span class="fx-writing"><span class="ai-typewriter" data-words="file storage|backup synced|data archived"></span></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                </div>
                <div class="hero-content-shell">
                    <header class="hero-topbar">
                        <nav class="hero-topbar-nav">
                            <a href="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>" class="hero-brand">
                                <img src="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/presenova.png" alt="Presenova">
                                <span>Presenova</span>
                            </a>
                            <div class="hero-links">
                                <a href="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
                            </div>
                        </nav>
                    </header>

                    <div class="hero-center">
                        <span class="hero-chip"><i class="fa-solid fa-bolt"></i> AI Attendance Engine</span>
                        <h1 class="hero-title">
                            <span class="hero-title-main">Presenova</span>
                            <span class="hero-title-tagline">Bringing Back, Learning Time</span>
                        </h1>
                        <p class="hero-desc">
                            <span
                                class="ai-typewriter"
                                data-words="AI Engine|Face Recognition|Location Guard|Push Notification|Attendance Analytics"
                                data-words-mobile="AI Engine|Face Scan|GPS Guard|Push Alert"
                            >AI Engine</span>
                        </p>
                        <div class="hero-actions">
                            <a class="hero-btn hero-btn-get" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="button__icon-wrapper">
                                    <svg
                                        width="12"
                                        class="button__icon-svg"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 14 15"
                                    >
                                        <path
                                            fill="currentColor"
                                            d="M13.376 11.552l-.264-10.44-10.44-.24.024 2.28 6.96-.048L.2 12.56l1.488 1.488 9.432-9.432-.048 6.912 2.304.024z"
                                        ></path>
                                    </svg>
                                    <svg
                                        class="button__icon-svg button__icon-svg--copy"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="12"
                                        fill="none"
                                        viewBox="0 0 14 15"
                                    >
                                        <path
                                            fill="currentColor"
                                            d="M13.376 11.552l-.264-10.44-10.44-.24.024 2.28 6.96-.048L.2 12.56l1.488 1.488 9.432-9.432-.048 6.912 2.304.024z"
                                        ></path>
                                    </svg>
                                </span>
                                <span>Get Started</span>
                            </a>
                            <a class="hero-btn hero-btn-know" href="<?php echo htmlspecialchars($indexUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                <span>Let's Get To Know About Us</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/three@0.160.1/build/three.min.js"></script>
    <script>
        (function () {
            const root = document.getElementById('lightPillarRoot');
            if (!root || typeof THREE === 'undefined') {
                return;
            }

            const config = {
                topColor: '#417aec',
                bottomColor: '#04fb35',
                intensity: 1.24,
                rotationSpeed: 1.04,
                interactive: false,
                glowAmount: 0.0025,
                pillarWidth: 2.84,
                pillarHeight: 0.4,
                noiseIntensity: 0.06,
                mixBlendMode: 'screen',
                pillarRotation: 25,
                quality: 'medium'
            };

            root.style.mixBlendMode = config.mixBlendMode;

            function supportsWebGL() {
                try {
                    const canvas = document.createElement('canvas');
                    return !!(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
                } catch (error) {
                    return false;
                }
            }

            if (!supportsWebGL()) {
                return;
            }

            const scene = new THREE.Scene();
            const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
            const mouse = new THREE.Vector2(0, 0);

            const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const saveDataEnabled = !!(navigator.connection && navigator.connection.saveData);
            const memoryHint = typeof navigator.deviceMemory === 'number' ? navigator.deviceMemory : 8;
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isLowEndDevice = isMobile || (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4) || memoryHint <= 4;
            let effectiveQuality = config.quality;
            if (isLowEndDevice && effectiveQuality === 'high') {
                effectiveQuality = 'medium';
            }
            if (memoryHint <= 2 || saveDataEnabled || prefersReducedMotion) {
                effectiveQuality = 'low';
            }
            if (isMobile && effectiveQuality !== 'low') {
                effectiveQuality = 'low';
            }

            if (isMobile) {
                config.intensity = 1.42;
                config.glowAmount = 0.0036;
                config.noiseIntensity = 0.045;
                config.pillarWidth = 2.62;
                config.pillarHeight = 0.44;
            }

            const qualitySettings = {
                low: { iterations: 20, waveIterations: 1, pixelRatio: 0.5, precision: 'mediump', stepMultiplier: 1.52, targetFPS: 26 },
                medium: { iterations: 32, waveIterations: 2, pixelRatio: 0.6, precision: 'mediump', stepMultiplier: 1.28, targetFPS: 40 },
                high: { iterations: 56, waveIterations: 3, pixelRatio: Math.min(window.devicePixelRatio || 1, 1.35), precision: 'highp', stepMultiplier: 1.08, targetFPS: 52 }
            };
            const settings = qualitySettings[effectiveQuality] || qualitySettings.medium;

            let renderer;
            try {
                renderer = new THREE.WebGLRenderer({
                    antialias: false,
                    alpha: true,
                    powerPreference: effectiveQuality === 'high' ? 'high-performance' : 'low-power',
                    precision: settings.precision,
                    stencil: false,
                    depth: false
                });
            } catch (error) {
                return;
            }

            const parseColor = function (hex) {
                const color = new THREE.Color(hex);
                return new THREE.Vector3(color.r, color.g, color.b);
            };

            const vertexShader = `
                varying vec2 vUv;
                void main() {
                    vUv = uv;
                    gl_Position = vec4(position, 1.0);
                }
            `;

            const fragmentShader = `
                precision ${settings.precision} float;

                uniform float uTime;
                uniform vec2 uResolution;
                uniform vec2 uMouse;
                uniform vec3 uTopColor;
                uniform vec3 uBottomColor;
                uniform float uIntensity;
                uniform bool uInteractive;
                uniform float uGlowAmount;
                uniform float uPillarWidth;
                uniform float uPillarHeight;
                uniform float uNoiseIntensity;
                uniform float uRotCos;
                uniform float uRotSin;
                uniform float uPillarRotCos;
                uniform float uPillarRotSin;
                uniform float uWaveSin;
                uniform float uWaveCos;
                varying vec2 vUv;

                const float STEP_MULT = ${settings.stepMultiplier.toFixed(1)};
                const int MAX_ITER = ${settings.iterations};
                const int WAVE_ITER = ${settings.waveIterations};

                void main() {
                    vec2 uv = (vUv * 2.0 - 1.0) * vec2(uResolution.x / uResolution.y, 1.0);
                    uv = vec2(uPillarRotCos * uv.x - uPillarRotSin * uv.y, uPillarRotSin * uv.x + uPillarRotCos * uv.y);

                    vec3 ro = vec3(0.0, 0.0, -10.0);
                    vec3 rd = normalize(vec3(uv, 1.0));

                    float rotC = uRotCos;
                    float rotS = uRotSin;
                    if (uInteractive && (uMouse.x != 0.0 || uMouse.y != 0.0)) {
                        float a = uMouse.x * 6.283185;
                        rotC = cos(a);
                        rotS = sin(a);
                    }

                    vec3 col = vec3(0.0);
                    float t = 0.1;

                    for (int i = 0; i < MAX_ITER; i++) {
                        vec3 p = ro + rd * t;
                        p.xz = vec2(rotC * p.x - rotS * p.z, rotS * p.x + rotC * p.z);

                        vec3 q = p;
                        q.y = p.y * uPillarHeight + uTime;

                        float freq = 1.0;
                        float amp = 1.0;
                        for (int j = 0; j < WAVE_ITER; j++) {
                            q.xz = vec2(uWaveCos * q.x - uWaveSin * q.z, uWaveSin * q.x + uWaveCos * q.z);
                            q += cos(q.zxy * freq - uTime * float(j) * 2.0) * amp;
                            freq *= 2.0;
                            amp *= 0.5;
                        }

                        float d = length(cos(q.xz)) - 0.2;
                        float bound = length(p.xz) - uPillarWidth;
                        float k = 4.0;
                        float h = max(k - abs(d - bound), 0.0);
                        d = max(d, bound) + h * h * 0.0625 / k;
                        d = abs(d) * 0.15 + 0.01;

                        float lineA = abs(sin((q.x * 6.1 + q.y * 3.0 - q.z * 4.0) - uTime * 2.2));
                        float lineB = abs(sin((q.z * 6.8 - q.y * 2.0 + q.x * 1.7) + uTime * 1.7));
                        float lineField = 1.0 - min(lineA, lineB);
                        lineField = pow(clamp(lineField, 0.0, 1.0), 9.0);
                        float streakDrift = 0.62 + 0.38 * abs(sin(q.y * 1.6 + uTime * 2.7));
                        float surfaceMask = 1.0 / (1.0 + d * 62.0);
                        float lineBoost = lineField * streakDrift * surfaceMask;

                        float grad = clamp((15.0 - p.y) / 30.0, 0.0, 1.0);
                        vec3 baseColor = mix(uBottomColor, uTopColor, grad);
                        col += baseColor * (1.0 + lineBoost * 1.22) / d;
                        col += vec3(0.08, 0.18, 0.22) * lineBoost * 0.05 / d;

                        t += d * STEP_MULT;
                        if (t > 50.0) break;
                    }

                    float widthNorm = uPillarWidth / 3.0;
                    vec3 mapped = tanh(col * uGlowAmount / widthNorm);
                    vec3 detailed = pow(clamp(mapped, 0.0, 1.0), vec3(0.92));
                    col = mix(mapped, detailed, 0.2);
                    col -= fract(sin(dot(gl_FragCoord.xy, vec2(12.9898, 78.233))) * 43758.5453) / 20.0 * uNoiseIntensity;

                    gl_FragColor = vec4(col * uIntensity, 1.0);
                }
            `;

            const width = root.clientWidth || window.innerWidth;
            const height = root.clientHeight || window.innerHeight;
            let dynamicPixelRatio = settings.pixelRatio;
            const minPixelRatio = Math.max(0.34, settings.pixelRatio * 0.75);
            renderer.setSize(width, height);
            renderer.setPixelRatio(dynamicPixelRatio);
            root.appendChild(renderer.domElement);

            const pillarRotRad = (config.pillarRotation * Math.PI) / 180;
            const waveSin = Math.sin(0.4);
            const waveCos = Math.cos(0.4);

            const material = new THREE.ShaderMaterial({
                vertexShader: vertexShader,
                fragmentShader: fragmentShader,
                uniforms: {
                    uTime: { value: 0 },
                    uResolution: { value: new THREE.Vector2(width, height) },
                    uMouse: { value: mouse },
                    uTopColor: { value: parseColor(config.topColor) },
                    uBottomColor: { value: parseColor(config.bottomColor) },
                    uIntensity: { value: config.intensity },
                    uInteractive: { value: config.interactive },
                    uGlowAmount: { value: config.glowAmount },
                    uPillarWidth: { value: config.pillarWidth },
                    uPillarHeight: { value: config.pillarHeight },
                    uNoiseIntensity: { value: config.noiseIntensity },
                    uRotCos: { value: 1.0 },
                    uRotSin: { value: 0.0 },
                    uPillarRotCos: { value: Math.cos(pillarRotRad) },
                    uPillarRotSin: { value: Math.sin(pillarRotRad) },
                    uWaveSin: { value: waveSin },
                    uWaveCos: { value: waveCos }
                },
                transparent: true,
                depthWrite: false,
                depthTest: false
            });

            const geometry = new THREE.PlaneGeometry(2, 2);
            const mesh = new THREE.Mesh(geometry, material);
            scene.add(mesh);

            let animationId = null;
            let lastTime = performance.now();
            let shaderTime = 0;
            const targetFPS = settings.targetFPS || (effectiveQuality === 'low' ? 30 : 60);
            const frameTime = 1000 / targetFPS;
            let slowFrames = 0;
            let fastFrames = 0;

            const animate = function (currentTime) {
                const deltaTime = currentTime - lastTime;
                if (deltaTime >= frameTime) {
                    shaderTime += 0.029 * config.rotationSpeed;
                    material.uniforms.uTime.value = shaderTime;
                    material.uniforms.uRotCos.value = Math.cos(shaderTime * 0.5);
                    material.uniforms.uRotSin.value = Math.sin(shaderTime * 0.5);
                    renderer.render(scene, camera);
                    lastTime = currentTime - (deltaTime % frameTime);

                    if (deltaTime > frameTime * 1.8) {
                        slowFrames += 1;
                        fastFrames = Math.max(0, fastFrames - 1);
                    } else if (deltaTime < frameTime * 0.92) {
                        fastFrames += 1;
                        slowFrames = Math.max(0, slowFrames - 1);
                    } else {
                        slowFrames = Math.max(0, slowFrames - 1);
                        fastFrames = Math.max(0, fastFrames - 1);
                    }

                    if (slowFrames >= 8 && dynamicPixelRatio > minPixelRatio) {
                        dynamicPixelRatio = Math.max(minPixelRatio, dynamicPixelRatio - 0.08);
                        const nowWidth = root.clientWidth || window.innerWidth;
                        const nowHeight = root.clientHeight || window.innerHeight;
                        renderer.setPixelRatio(dynamicPixelRatio);
                        renderer.setSize(nowWidth, nowHeight, false);
                        slowFrames = 0;
                        fastFrames = 0;
                    } else if (fastFrames >= 26 && dynamicPixelRatio < settings.pixelRatio) {
                        dynamicPixelRatio = Math.min(settings.pixelRatio, dynamicPixelRatio + 0.04);
                        const nowWidth = root.clientWidth || window.innerWidth;
                        const nowHeight = root.clientHeight || window.innerHeight;
                        renderer.setPixelRatio(dynamicPixelRatio);
                        renderer.setSize(nowWidth, nowHeight, false);
                        fastFrames = 0;
                    }
                }
                animationId = requestAnimationFrame(animate);
            };

            const startAnimation = function () {
                if (animationId) {
                    return;
                }
                lastTime = performance.now();
                animationId = requestAnimationFrame(animate);
            };

            const stopAnimation = function () {
                if (!animationId) {
                    return;
                }
                cancelAnimationFrame(animationId);
                animationId = null;
            };

            const handleVisibilityChange = function () {
                if (document.hidden) {
                    stopAnimation();
                    return;
                }
                startAnimation();
            };

            document.addEventListener('visibilitychange', handleVisibilityChange, { passive: true });
            startAnimation();

            let resizeTimeout = null;
            const handleResize = function () {
                if (resizeTimeout) {
                    clearTimeout(resizeTimeout);
                }
                resizeTimeout = setTimeout(function () {
                    const nextWidth = root.clientWidth || window.innerWidth;
                    const nextHeight = root.clientHeight || window.innerHeight;
                    renderer.setPixelRatio(dynamicPixelRatio);
                    renderer.setSize(nextWidth, nextHeight);
                    material.uniforms.uResolution.value.set(nextWidth, nextHeight);
                }, 150);
            };
            window.addEventListener('resize', handleResize, { passive: true });

            window.addEventListener('beforeunload', function () {
                document.removeEventListener('visibilitychange', handleVisibilityChange);
                window.removeEventListener('resize', handleResize);
                stopAnimation();
                geometry.dispose();
                material.dispose();
                renderer.dispose();
                if (typeof renderer.forceContextLoss === 'function') {
                    renderer.forceContextLoss();
                }
            });
        })();

        (function () {
            const typeNodes = document.querySelectorAll('.ai-typewriter');
            if (!typeNodes.length) {
                return;
            }

            const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const compactViewport = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;

            if (prefersReducedMotion) {
                typeNodes.forEach(function (node) {
                    node.textContent = '';
                });
                return;
            }

            typeNodes.forEach(function (node, index) {
                const wordsSource = compactViewport
                    ? (node.dataset.wordsMobile || node.dataset.words || '')
                    : (node.dataset.words || '');

                const words = wordsSource
                    .split('|')
                    .map(function (item) { return item.trim(); })
                    .filter(Boolean);

                if (!words.length) {
                    return;
                }

                const computedStyle = window.getComputedStyle(node);
                const visible = computedStyle.display !== 'none' && computedStyle.visibility !== 'hidden';
                if (!visible) {
                    node.textContent = '';
                    return;
                }

                let wordIndex = 0;
                let charIndex = 0;
                let deleting = false;
                let pauseTicks = 0;

                const tick = function () {
                    if (document.hidden) {
                        return;
                    }

                    const activeWord = words[wordIndex];

                    if (pauseTicks > 0) {
                        pauseTicks -= 1;
                        return;
                    }

                    if (!deleting) {
                        charIndex += 1;
                        if (charIndex >= activeWord.length) {
                            charIndex = activeWord.length;
                            deleting = true;
                            pauseTicks = 14;
                        }
                    } else {
                        charIndex -= 1;
                        if (charIndex <= 0) {
                            charIndex = 0;
                            deleting = false;
                            wordIndex = (wordIndex + 1) % words.length;
                            pauseTicks = 5;
                        }
                    }

                    node.textContent = activeWord.slice(0, charIndex);
                };

                node.textContent = '';
                tick();
                const speed = compactViewport ? (84 + (index * 6)) : (60 + (index * 9));
                const timer = setInterval(tick, speed);
                window.addEventListener('beforeunload', function () {
                    clearInterval(timer);
                }, { once: true });
            });
        })();
    </script>
</body>
</html>
