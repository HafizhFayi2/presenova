<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>login - presenova</title>
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/apple-touch-icon_login.png?v=20260212c">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-16x16_login.png?v=20260212c">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon-32x32_login.png?v=20260212c">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon_login.ico?v=20260212c">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-green: #10b981;
            --light-green: #34d399;
            --dark-green: #059669;
            --gradient-green: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --white: #ffffff;
            --light-bg: #f8fafc;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --card-shadow: 0 10px 40px rgba(16, 185, 129, 0.15);
            --input-border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Login Container */
        .login-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        /* Header */
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-container {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .logo-container img {
            height: 48px;
            width: auto;
        }

        .brand-text {
            font-family: 'Poppins', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--gradient-green);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        /* Card */
        .login-card {
            background: var(--white);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(16, 185, 129, 0.1);
        }

        /* Messages */
        .message-box {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        .message-box.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }

        .message-box.success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--primary-green);
        }

        /* Role Selection */
        .role-selection {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: #f1f5f9;
            padding: 0.5rem;
            border-radius: 12px;
        }

        .role-btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }

        .role-btn i {
            color: inherit;
        }

        .role-btn:hover {
            color: var(--primary-green);
            background: rgba(16, 185, 129, 0.05);
        }

        .role-btn.active {
            background: white;
            color: var(--primary-green);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.15);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--input-border);
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            background: white;
            color: var(--text-dark);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        /* Password Input */
        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: var(--primary-green);
        }

        /* Remember Me */
        .remember-me {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .checkbox-container input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-green);
            cursor: pointer;
        }

        .checkbox-label {
            color: var(--text-light);
            font-size: 0.85rem;
            cursor: pointer;
        }

        .forgot-link {
            color: var(--primary-green);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            height: 52px;
            padding: 0 1rem;
            background: linear-gradient(135deg, #0f172a 0%, #111827 100%);
            border: none;
            border-radius: 999px;
            color: white;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: color 0.28s ease, transform 0.22s ease, box-shadow 0.22s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            margin-bottom: 1.5rem;
            font-family: 'Inter', sans-serif;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .btn-submit::after {
            content: '';
            background: #ffffff;
            position: absolute;
            z-index: -1;
            left: -20%;
            right: -20%;
            top: 0;
            bottom: 0;
            transform: skewX(-45deg) scale(0, 1);
            transition: transform 0.5s ease;
        }

        .btn-submit:hover {
            color: #0b1220;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.28);
        }

        .btn-submit:hover::after {
            transform: skewX(-45deg) scale(1, 1);
        }

        .btn-submit:disabled {
            opacity: 0.86;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-submit:disabled::after {
            transform: skewX(-45deg) scale(0, 1);
        }

        .btn-submit:disabled:hover {
            color: #ffffff;
            transform: none;
            box-shadow: none;
        }

        .btn-submit:disabled:hover::after {
            transform: skewX(-45deg) scale(0, 1);
        }

        .btn-submit.is-loading,
        .btn-submit.is-loading:hover {
            background: var(--gradient-green);
            color: #ffffff;
            transform: none;
            box-shadow: 0 10px 24px rgba(16, 185, 129, 0.28);
            cursor: progress;
        }

        .btn-submit.is-loading::after,
        .btn-submit.is-loading:hover::after {
            transform: skewX(-45deg) scale(0, 1);
        }

        /* Footer */
        .login-footer {
            text-align: center;
            color: var(--text-light);
            font-size: 0.85rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .login-footer a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* Home Link */
        .home-link {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 30;
            padding: 0.5rem 0.85rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(16, 185, 129, 0.2);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.1);
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.88rem;
            transition: all 0.2s ease;
        }

        .home-link i {
            font-size: 0.82rem;
        }

        .home-link:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
        }

        /* Responsive */
        @media (max-width: 576px) {
            body {
                align-items: flex-start;
                flex-direction: column;
                padding: 16px;
            }

            .login-container {
                max-width: 100%;
                width: 100%;
            }

            .login-header {
                margin-bottom: 1.5rem;
                text-align: left;
            }

            .logo-container {
                gap: 10px;
                margin-bottom: 1rem;
                justify-content: flex-start;
            }

            .logo-container img {
                height: 40px;
            }

            .brand-text {
                font-size: 1.5rem;
            }

            .login-card {
                padding: 1.5rem;
                border-radius: 14px;
            }

            .role-selection {
                padding: 0.4rem;
            }

            .role-btn {
                font-size: 0.85rem;
            }

            .home-link {
                position: relative;
                top: 0;
                left: 0;
                margin-bottom: 0.9rem;
                align-self: flex-start;
                width: auto;
                max-width: 100%;
                justify-content: flex-start;
                padding: 0.5rem 0.82rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .login-card {
                padding: 1.25rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .role-selection {
                flex-direction: column;
            }
            
            .home-link {
                position: relative;
                top: 0;
                left: 0;
                margin-bottom: 0.85rem;
                font-size: 0.84rem;
                padding: 0.46rem 0.72rem;
            }
        }
    </style>
</head>
<body>
    <!-- Home Link -->
    <a href="<?php echo htmlspecialchars($getStartedUrl, ENT_QUOTES, 'UTF-8'); ?>" class="home-link">
        <i class="fas fa-arrow-left"></i>
        <span>Kembali ke Home</span>
    </a>

    <!-- Login Container -->
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="logo-container">
                <!-- Pastikan logo ada di assets/images/presenova.png -->
                <!-- <img src="assets/images/presenova.png" alt="Presenova Logo"> -->
                 <img src="<?php echo htmlspecialchars($assetBaseUrl, ENT_QUOTES, 'UTF-8'); ?>assets/images/presenova.png" alt="Presenova Logo">
        <span class="brand-text">PRESENOVA</span>
    </div>
    <h1 class="login-title">Masuk ke Sistem</h1>
    <p class="login-subtitle">Login untuk mengakses dashboard Presenova</p>
</div>

        <!-- Card -->
        <div class="login-card">
            <?php if ($error): ?>
                <div class="message-box error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="message-box success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" autocomplete="off">
                <input type="hidden" name="role" id="loginRole" value="siswa">
                
                <!-- Role Selection -->
                <div class="role-selection">
                    <button type="button" class="role-btn active" data-role="siswa">
                        <i class="fas fa-user-graduate"></i> Siswa
                    </button>
                    <button type="button" class="role-btn" data-role="guru">
                        <i class="fas fa-chalkboard-teacher"></i> Guru
                    </button>
                    <button type="button" class="role-btn" data-role="admin">
                        <i class="fas fa-user-shield"></i> Admin
                    </button>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label class="form-label" for="username">
                        <span id="usernameLabel">NISN</span>
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           required 
                           placeholder="Masukkan NISN Anda"
                           inputmode="numeric"
                           autocapitalize="off"
                           autocomplete="username">
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">
                        Password
                    </label>
                    <div class="password-container">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               required 
                               placeholder="Masukkan password Anda"
                               autocomplete="current-password">
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="remember-me">
                    <label class="checkbox-container">
                        <input type="checkbox" id="remember" name="remember">
                        <span class="checkbox-label">Ingat saya</span>
                    </label>
                    <a href="<?php echo htmlspecialchars($forgotPasswordUrl, ENT_QUOTES, 'UTF-8'); ?>" class="forgot-link">
                        Lupa password?
                    </a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="login-footer">
                <p>Belum punya akun? 
                    <a href="<?php echo htmlspecialchars($registerCallUrl, ENT_QUOTES, 'UTF-8'); ?>">Registrasi sebagai Siswa Baru</a>
                </p>
                <p>Presenova &copy; <?php echo date('Y'); ?> Bringing Back, Learning Time</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const roleButtons = document.querySelectorAll('.role-btn');
            const roleInput = document.getElementById('loginRole');
            const usernameLabel = document.getElementById('usernameLabel');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const loginForm = document.getElementById('loginForm');

            function applyUsernameInputMode(role) {
                if (role === 'siswa') {
                    usernameInput.setAttribute('inputmode', 'numeric');
                    usernameInput.setAttribute('autocapitalize', 'off');
                    usernameInput.setAttribute('spellcheck', 'false');
                    return;
                }

                usernameInput.setAttribute('inputmode', 'text');
                usernameInput.setAttribute('autocapitalize', 'off');
                usernameInput.setAttribute('spellcheck', 'false');
            }

            // Role Selection
            roleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    roleButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Update hidden input
                    const role = this.dataset.role;
                    roleInput.value = role;
                    
                    // Update label and placeholder based on role
                    let label, placeholder;
                    switch(role) {
                        case 'admin':
                            label = 'Username';
                            placeholder = 'Masukkan username admin';
                            break;
                        case 'guru':
                            label = 'Kode Guru';
                            placeholder = 'Masukkan kode guru';
                            break;
                        default:
                            label = 'NISN';
                            placeholder = 'Masukkan NISN Anda';
                    }
                    
                    usernameLabel.textContent = label;
                    usernameInput.placeholder = placeholder;
                    applyUsernameInputMode(role);
                    
                    // Auto-focus on username field
                    usernameInput.focus();
                });
            });
            applyUsernameInputMode('siswa');

            // Toggle Password Visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });

            // Form Validation
            loginForm.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value.trim();
                
                if (!username || !password) {
                    e.preventDefault();
                    showError('Username dan password harus diisi!');
                    return false;
                }

                if (roleInput.value === 'siswa') {
                    usernameInput.value = username.replace(/\s+/g, '');
                }
                
                // Show loading state
                const submitBtn = this.querySelector('.btn-submit');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                submitBtn.classList.add('is-loading');
                submitBtn.disabled = true;
                
                // Auto-enable after 5 seconds (in case of error)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.classList.remove('is-loading');
                    submitBtn.disabled = false;
                }, 5000);
                
                return true;
            });

            // Error Display Function
            function showError(message) {
                // Remove existing error
                const existingError = document.querySelector('.message-box.error');
                if (existingError) {
                    existingError.remove();
                }
                
                // Create error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'message-box error';
                errorDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${message}</span>
                `;
                
                // Insert after login header
                const loginCard = document.querySelector('.login-card');
                loginCard.insertBefore(errorDiv, loginCard.firstChild);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    errorDiv.remove();
                }, 5000);
            }

            // Auto-focus on username field
            setTimeout(() => {
                usernameInput.focus();
            }, 300);

            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', async () => {
                try {
                    const registration = await navigator.serviceWorker.getRegistration();
                    if (!registration) {
                        return;
                    }

                    await registration.update();

                    if (registration.waiting) {
                        registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                    }

                    let isRefreshing = false;
                    navigator.serviceWorker.addEventListener('controllerchange', () => {
                        if (isRefreshing) {
                            return;
                        }
                        isRefreshing = true;
                        window.location.reload();
                    });
                } catch (error) {
                    console.warn('Service worker update check failed:', error);
                }
            });
        }
    </script>
</body>
</html>


