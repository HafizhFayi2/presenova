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
    <link rel="manifest" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>manifest.json">
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
            filter: contrast(1.2) saturate(1.32) brightness(1.06);
            opacity: 1;
        }

        .light-pillar-wrap canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
            filter: contrast(1.05) saturate(1.08);
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
            opacity: 0.28;
            filter: drop-shadow(0 0 14px rgba(62, 220, 255, 0.34));
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

        .fx-writer {
            top: 18%;
            left: 6.5%;
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
            .floating-tech {
                transform: scale(0.86);
                transform-origin: center;
            }

            .fx-writer { top: 18%; left: 3.5%; }
            .fx-gear { top: 24%; right: 3.5%; }
            .fx-code { top: 64%; left: 12%; }
            .fx-pulse { top: 76%; right: 3%; }

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
                display: none;
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
                        <span class="fx-writing"><span class="ai-typewriter" data-words="writer|auto verify|smart typing"></span></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                    <div class="floating-tech fx-gear">
                        <span class="fx-icon"><i class="fa-solid fa-gear"></i></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                    <div class="floating-tech fx-code">
                        <span class="fx-icon"><i class="fa-solid fa-code"></i></span>
                        <span class="fx-glow-dot"></span>
                    </div>
                    <div class="floating-tech fx-pulse">
                        <span class="fx-icon"><i class="fa-solid fa-heart"></i></span>
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
                        <p class="hero-desc">LET'S GET TO KNOW MORE ABOUT US</p>
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
                                <span>More To Know About Us</span>
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
                quality: 'high'
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

            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isLowEndDevice = isMobile || (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4);
            let effectiveQuality = config.quality;
            if (isLowEndDevice && effectiveQuality === 'high') {
                effectiveQuality = 'medium';
            }
            if (isMobile && effectiveQuality !== 'low') {
                effectiveQuality = 'low';
            }

            const qualitySettings = {
                low: { iterations: 24, waveIterations: 1, pixelRatio: 0.5, precision: 'mediump', stepMultiplier: 1.5 },
                medium: { iterations: 40, waveIterations: 2, pixelRatio: 0.65, precision: 'mediump', stepMultiplier: 1.2 },
                high: { iterations: 80, waveIterations: 4, pixelRatio: Math.min(window.devicePixelRatio || 1, 2), precision: 'highp', stepMultiplier: 1.0 }
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
            renderer.setSize(width, height);
            renderer.setPixelRatio(settings.pixelRatio);
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
            const targetFPS = effectiveQuality === 'low' ? 30 : 60;
            const frameTime = 1000 / targetFPS;

            const animate = function (currentTime) {
                const deltaTime = currentTime - lastTime;
                if (deltaTime >= frameTime) {
                    shaderTime += 0.029 * config.rotationSpeed;
                    material.uniforms.uTime.value = shaderTime;
                    material.uniforms.uRotCos.value = Math.cos(shaderTime * 0.5);
                    material.uniforms.uRotSin.value = Math.sin(shaderTime * 0.5);
                    renderer.render(scene, camera);
                    lastTime = currentTime - (deltaTime % frameTime);
                }
                animationId = requestAnimationFrame(animate);
            };
            animationId = requestAnimationFrame(animate);

            let resizeTimeout = null;
            const handleResize = function () {
                if (resizeTimeout) {
                    clearTimeout(resizeTimeout);
                }
                resizeTimeout = setTimeout(function () {
                    const nextWidth = root.clientWidth || window.innerWidth;
                    const nextHeight = root.clientHeight || window.innerHeight;
                    renderer.setSize(nextWidth, nextHeight);
                    material.uniforms.uResolution.value.set(nextWidth, nextHeight);
                }, 150);
            };
            window.addEventListener('resize', handleResize, { passive: true });

            window.addEventListener('beforeunload', function () {
                window.removeEventListener('resize', handleResize);
                if (animationId) {
                    cancelAnimationFrame(animationId);
                }
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

            typeNodes.forEach(function (node, index) {
                const words = (node.dataset.words || '')
                    .split('|')
                    .map(function (item) { return item.trim(); })
                    .filter(Boolean);

                if (!words.length) {
                    return;
                }

                if (prefersReducedMotion) {
                    node.textContent = words[0];
                    return;
                }

                let wordIndex = 0;
                let charIndex = 0;
                let deleting = false;
                let pauseTicks = 0;

                const tick = function () {
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
                const speed = 60 + (index * 9);
                const timer = setInterval(tick, speed);
                window.addEventListener('beforeunload', function () {
                    clearInterval(timer);
                }, { once: true });
            });
        })();
    </script>
</body>
</html>
