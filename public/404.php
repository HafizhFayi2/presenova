<?php
http_response_code(404);
$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - presenova</title>
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#03010f" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex, nofollow">
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon_404.png?v=20260212c">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16_404.png?v=20260212c">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32_404.png?v=20260212c">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon_404.ico?v=20260212c">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat+Brush&family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #03010f;
            --panel: #05031a;
            --edge: rgba(146, 115, 255, 0.22);
            --text: #ffffff;
            --muted: #aeb4d3;
            --accent: #5af0ff;
            --accent-soft: rgba(90, 240, 255, 0.26);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 18% 18%, rgba(88, 255, 213, 0.12), transparent 42%),
                radial-gradient(circle at 78% 82%, rgba(125, 103, 255, 0.14), transparent 45%),
                var(--bg);
            display: grid;
            place-items: center;
            padding: 24px;
            overflow: hidden;
        }

        .noise {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.07;
            z-index: 0;
            background-image:
                radial-gradient(circle at 2px 2px, rgba(255, 255, 255, 0.33) 1px, transparent 0);
            background-size: 3px 3px;
        }

        .card {
            position: relative;
            width: min(960px, 100%);
            min-height: min(520px, 86vh);
            border: 1px solid var(--edge);
            border-radius: 24px;
            background:
                linear-gradient(180deg, rgba(13, 9, 36, 0.88) 0%, rgba(6, 3, 22, 0.92) 100%);
            box-shadow:
                0 25px 65px rgba(0, 0, 0, 0.48),
                inset 0 0 0 1px rgba(255, 255, 255, 0.02);
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: clamp(26px, 5vw, 46px);
        }

        .fuzzy-canvas {
            display: block;
            image-rendering: pixelated;
            max-width: 100%;
        }

        .fuzzy-main {
            margin-bottom: clamp(2px, 1.2vw, 12px);
        }

        .fuzzy-sub {
            margin-bottom: clamp(18px, 3vw, 30px);
        }

        .description {
            margin: 0;
            text-align: center;
            font-size: clamp(0.92rem, 2.5vw, 1.06rem);
            color: var(--muted);
            max-width: 560px;
            line-height: 1.6;
        }

        .request-path {
            margin: 14px 0 0;
            color: #d3dcff;
            font-size: clamp(0.78rem, 2vw, 0.86rem);
            opacity: 0.75;
            word-break: break-all;
            text-align: center;
        }

        .actions {
            margin-top: 26px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .action-btn {
            border-radius: 999px;
            border: 1px solid rgba(127, 211, 255, 0.28);
            background: rgba(42, 28, 92, 0.46);
            color: #f6fbff;
            text-decoration: none;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            box-shadow: 0 0 0 0 var(--accent-soft);
        }

        .action-btn:hover {
            transform: translateY(-1px);
            background: rgba(72, 56, 144, 0.5);
            box-shadow: 0 0 0 4px var(--accent-soft);
        }

        @media (max-width: 640px) {
            .card {
                min-height: 74vh;
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="noise" aria-hidden="true"></div>
    <main class="card" role="main">
        <canvas id="fuzzy404" class="fuzzy-canvas fuzzy-main" aria-label="404"></canvas>
        <canvas id="fuzzyLabel" class="fuzzy-canvas fuzzy-sub" aria-label="not found"></canvas>
        <p class="description">Halaman yang Anda cari tidak ditemukan atau sudah dipindahkan.</p>
        <p class="request-path">Request: <?php echo htmlspecialchars($requestedPath, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="actions">
            <a class="action-btn" href="./index.php">Kembali ke Beranda</a>
            <a class="action-btn" href="javascript:history.back()">Halaman Sebelumnya</a>
        </div>
    </main>

    <script>
        (function () {
            function debounce(callback, wait) {
                let timeoutId;
                return function () {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(callback, wait);
                };
            }

            function createFuzzyText(canvas, options) {
                const config = Object.assign({
                    text: '',
                    fontSize: 'clamp(2rem, 10vw, 10rem)',
                    fontWeight: 900,
                    fontFamily: 'inherit',
                    color: '#fff',
                    enableHover: true,
                    baseIntensity: 0.18,
                    hoverIntensity: 0.5,
                    fuzzRange: 30,
                    fps: 60,
                    direction: 'horizontal',
                    transitionDuration: 0,
                    clickEffect: false,
                    glitchMode: false,
                    glitchInterval: 2000,
                    glitchDuration: 200,
                    gradient: null,
                    letterSpacing: 0
                }, options || {});

                let animationFrameId = 0;
                let glitchTimeoutId = 0;
                let glitchEndTimeoutId = 0;
                let clickTimeoutId = 0;
                let cancelled = false;
                let cleanup = function () {};

                async function init() {
                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        return;
                    }

                    const computedFontFamily = config.fontFamily === 'inherit'
                        ? (window.getComputedStyle(canvas).fontFamily || 'sans-serif')
                        : config.fontFamily;
                    const fontSizeString = typeof config.fontSize === 'number'
                        ? config.fontSize + 'px'
                        : config.fontSize;
                    const fontString = config.fontWeight + ' ' + fontSizeString + ' ' + computedFontFamily;

                    if (document.fonts && document.fonts.load) {
                        try {
                            await document.fonts.load(fontString);
                        } catch (error) {
                            if (document.fonts.ready) {
                                await document.fonts.ready;
                            }
                        }
                    }
                    if (cancelled) {
                        return;
                    }

                    let numericFontSize = 16;
                    if (typeof config.fontSize === 'number') {
                        numericFontSize = config.fontSize;
                    } else {
                        const temp = document.createElement('span');
                        temp.style.position = 'absolute';
                        temp.style.opacity = '0';
                        temp.style.pointerEvents = 'none';
                        temp.style.fontSize = config.fontSize;
                        temp.textContent = 'M';
                        document.body.appendChild(temp);
                        numericFontSize = parseFloat(window.getComputedStyle(temp).fontSize) || 16;
                        temp.remove();
                    }

                    const text = String(config.text || '');
                    const offscreen = document.createElement('canvas');
                    const offCtx = offscreen.getContext('2d');
                    if (!offCtx) {
                        return;
                    }

                    offCtx.font = fontString;
                    offCtx.textBaseline = 'alphabetic';

                    let totalWidth = 0;
                    if (config.letterSpacing !== 0) {
                        for (let index = 0; index < text.length; index += 1) {
                            totalWidth += offCtx.measureText(text[index]).width + config.letterSpacing;
                        }
                        totalWidth -= config.letterSpacing;
                    } else {
                        totalWidth = offCtx.measureText(text).width;
                    }

                    const metrics = offCtx.measureText(text);
                    const actualLeft = metrics.actualBoundingBoxLeft || 0;
                    const actualRight = config.letterSpacing !== 0
                        ? totalWidth
                        : (metrics.actualBoundingBoxRight || metrics.width);
                    const actualAscent = metrics.actualBoundingBoxAscent || numericFontSize;
                    const actualDescent = metrics.actualBoundingBoxDescent || (numericFontSize * 0.2);

                    const textBoundingWidth = Math.ceil(config.letterSpacing !== 0 ? totalWidth : actualLeft + actualRight);
                    const tightHeight = Math.ceil(actualAscent + actualDescent);

                    const extraWidthBuffer = 10;
                    const offscreenWidth = textBoundingWidth + extraWidthBuffer;

                    offscreen.width = Math.max(1, offscreenWidth);
                    offscreen.height = Math.max(1, tightHeight);

                    const xOffset = extraWidthBuffer / 2;
                    offCtx.font = fontString;
                    offCtx.textBaseline = 'alphabetic';

                    if (Array.isArray(config.gradient) && config.gradient.length >= 2) {
                        const grad = offCtx.createLinearGradient(0, 0, offscreenWidth, 0);
                        for (let i = 0; i < config.gradient.length; i += 1) {
                            grad.addColorStop(i / (config.gradient.length - 1), config.gradient[i]);
                        }
                        offCtx.fillStyle = grad;
                    } else {
                        offCtx.fillStyle = config.color;
                    }

                    if (config.letterSpacing !== 0) {
                        let xPos = xOffset;
                        for (let i = 0; i < text.length; i += 1) {
                            const char = text[i];
                            offCtx.fillText(char, xPos, actualAscent);
                            xPos += offCtx.measureText(char).width + config.letterSpacing;
                        }
                    } else {
                        offCtx.fillText(text, xOffset - actualLeft, actualAscent);
                    }

                    const horizontalMargin = config.fuzzRange + 20;
                    const verticalMargin = 0;
                    canvas.width = offscreenWidth + (horizontalMargin * 2);
                    canvas.height = tightHeight + (verticalMargin * 2);
                    ctx.setTransform(1, 0, 0, 1, 0, 0);
                    ctx.translate(horizontalMargin, verticalMargin);

                    const interactiveLeft = horizontalMargin + xOffset;
                    const interactiveTop = verticalMargin;
                    const interactiveRight = interactiveLeft + textBoundingWidth;
                    const interactiveBottom = interactiveTop + tightHeight;

                    let isHovering = false;
                    let isClicking = false;
                    let isGlitching = false;
                    let currentIntensity = config.baseIntensity;
                    let targetIntensity = config.baseIntensity;
                    let lastFrameTime = 0;
                    const frameDuration = 1000 / Math.max(1, config.fps);

                    function startGlitchLoop() {
                        if (!config.glitchMode || cancelled) {
                            return;
                        }
                        glitchTimeoutId = window.setTimeout(function () {
                            if (cancelled) {
                                return;
                            }
                            isGlitching = true;
                            glitchEndTimeoutId = window.setTimeout(function () {
                                isGlitching = false;
                                startGlitchLoop();
                            }, config.glitchDuration);
                        }, config.glitchInterval);
                    }

                    if (config.glitchMode) {
                        startGlitchLoop();
                    }

                    function run(timestamp) {
                        if (cancelled) {
                            return;
                        }

                        if (timestamp - lastFrameTime < frameDuration) {
                            animationFrameId = window.requestAnimationFrame(run);
                            return;
                        }
                        lastFrameTime = timestamp;

                        ctx.clearRect(
                            -config.fuzzRange - 20,
                            -config.fuzzRange - 10,
                            offscreenWidth + (2 * (config.fuzzRange + 20)),
                            tightHeight + (2 * (config.fuzzRange + 10))
                        );

                        if (isClicking || isGlitching) {
                            targetIntensity = 1;
                        } else if (isHovering) {
                            targetIntensity = config.hoverIntensity;
                        } else {
                            targetIntensity = config.baseIntensity;
                        }

                        if (config.transitionDuration > 0) {
                            const step = 1 / (config.transitionDuration / frameDuration);
                            if (currentIntensity < targetIntensity) {
                                currentIntensity = Math.min(currentIntensity + step, targetIntensity);
                            } else if (currentIntensity > targetIntensity) {
                                currentIntensity = Math.max(currentIntensity - step, targetIntensity);
                            }
                        } else {
                            currentIntensity = targetIntensity;
                        }

                        if (config.direction === 'horizontal') {
                            for (let y = 0; y < tightHeight; y += 1) {
                                const dx = Math.floor(currentIntensity * (Math.random() - 0.5) * config.fuzzRange);
                                ctx.drawImage(offscreen, 0, y, offscreenWidth, 1, dx, y, offscreenWidth, 1);
                            }
                        } else if (config.direction === 'vertical') {
                            for (let x = 0; x < offscreenWidth; x += 1) {
                                const dy = Math.floor(currentIntensity * (Math.random() - 0.5) * config.fuzzRange);
                                ctx.drawImage(offscreen, x, 0, 1, tightHeight, x, dy, 1, tightHeight);
                            }
                        } else {
                            for (let y = 0; y < tightHeight; y += 1) {
                                const dx = Math.floor(currentIntensity * (Math.random() - 0.5) * config.fuzzRange);
                                ctx.drawImage(offscreen, 0, y, offscreenWidth, 1, dx, y, offscreenWidth, 1);
                            }
                            const tempData = ctx.getImageData(0, 0, offscreenWidth + config.fuzzRange, tightHeight + config.fuzzRange);
                            ctx.clearRect(
                                -config.fuzzRange - 20,
                                -config.fuzzRange - 10,
                                offscreenWidth + (2 * (config.fuzzRange + 20)),
                                tightHeight + (2 * (config.fuzzRange + 10))
                            );
                            ctx.putImageData(tempData, 0, 0);
                            for (let x = 0; x < offscreenWidth + config.fuzzRange; x += 1) {
                                const dy = Math.floor(currentIntensity * (Math.random() - 0.5) * config.fuzzRange * 0.5);
                                const colData = ctx.getImageData(x, 0, 1, tightHeight + config.fuzzRange);
                                ctx.clearRect(x, -config.fuzzRange, 1, tightHeight + (2 * config.fuzzRange));
                                ctx.putImageData(colData, x, dy);
                            }
                        }

                        animationFrameId = window.requestAnimationFrame(run);
                    }

                    animationFrameId = window.requestAnimationFrame(run);

                    function isInsideTextArea(x, y) {
                        return x >= interactiveLeft && x <= interactiveRight && y >= interactiveTop && y <= interactiveBottom;
                    }

                    function handleMouseMove(event) {
                        if (!config.enableHover) {
                            return;
                        }
                        const rect = canvas.getBoundingClientRect();
                        const x = event.clientX - rect.left;
                        const y = event.clientY - rect.top;
                        isHovering = isInsideTextArea(x, y);
                    }

                    function handleMouseLeave() {
                        isHovering = false;
                    }

                    function handleClick() {
                        if (!config.clickEffect) {
                            return;
                        }
                        isClicking = true;
                        clearTimeout(clickTimeoutId);
                        clickTimeoutId = window.setTimeout(function () {
                            isClicking = false;
                        }, 150);
                    }

                    function handleTouchMove(event) {
                        if (!config.enableHover) {
                            return;
                        }
                        event.preventDefault();
                        const rect = canvas.getBoundingClientRect();
                        const touch = event.touches[0];
                        const x = touch.clientX - rect.left;
                        const y = touch.clientY - rect.top;
                        isHovering = isInsideTextArea(x, y);
                    }

                    function handleTouchEnd() {
                        isHovering = false;
                    }

                    if (config.enableHover) {
                        canvas.addEventListener('mousemove', handleMouseMove);
                        canvas.addEventListener('mouseleave', handleMouseLeave);
                        canvas.addEventListener('touchmove', handleTouchMove, { passive: false });
                        canvas.addEventListener('touchend', handleTouchEnd);
                    }

                    if (config.clickEffect) {
                        canvas.addEventListener('click', handleClick);
                    }

                    cleanup = function () {
                        window.cancelAnimationFrame(animationFrameId);
                        clearTimeout(glitchTimeoutId);
                        clearTimeout(glitchEndTimeoutId);
                        clearTimeout(clickTimeoutId);

                        if (config.enableHover) {
                            canvas.removeEventListener('mousemove', handleMouseMove);
                            canvas.removeEventListener('mouseleave', handleMouseLeave);
                            canvas.removeEventListener('touchmove', handleTouchMove);
                            canvas.removeEventListener('touchend', handleTouchEnd);
                        }

                        if (config.clickEffect) {
                            canvas.removeEventListener('click', handleClick);
                        }
                    };
                }

                init();

                return function destroy() {
                    cancelled = true;
                    window.cancelAnimationFrame(animationFrameId);
                    clearTimeout(glitchTimeoutId);
                    clearTimeout(glitchEndTimeoutId);
                    clearTimeout(clickTimeoutId);
                    cleanup();
                };
            }

            let destroyMain = null;
            let destroySub = null;

            function mountFuzzyTexts() {
                if (destroyMain) {
                    destroyMain();
                }
                if (destroySub) {
                    destroySub();
                }

                destroyMain = createFuzzyText(document.getElementById('fuzzy404'), {
                    text: '404',
                    fontSize: 'clamp(4rem, 15vw, 11rem)',
                    fontWeight: 900,
                    fontFamily: '\'Inter\', sans-serif',
                    color: '#ffffff',
                    enableHover: true,
                    baseIntensity: 0.48,
                    hoverIntensity: 0.38,
                    fuzzRange: 30,
                    fps: 60,
                    direction: 'horizontal',
                    transitionDuration: 0,
                    clickEffect: false,
                    glitchMode: false
                });

                destroySub = createFuzzyText(document.getElementById('fuzzyLabel'), {
                    text: 'not found',
                    fontSize: 'clamp(1.4rem, 5.2vw, 3.15rem)',
                    fontWeight: 400,
                    fontFamily: '\'Caveat Brush\', cursive',
                    color: '#ffffff',
                    enableHover: true,
                    baseIntensity: 0.36,
                    hoverIntensity: 0.3,
                    fuzzRange: 24,
                    fps: 60,
                    direction: 'horizontal',
                    transitionDuration: 0,
                    clickEffect: false,
                    glitchMode: false,
                    letterSpacing: 0.5
                });
            }

            mountFuzzyTexts();
            window.addEventListener('resize', debounce(mountFuzzyTexts, 150));
        })();
    </script>
</body>
</html>
