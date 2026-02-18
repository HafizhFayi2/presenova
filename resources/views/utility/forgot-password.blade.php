<?php
// Prevent caching of forgot password page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$error = '';
$success = '';
$siteInfo = null;
$db = new Database();

// Security tuning
$rateWindowMinutes = 15;
$maxAttemptsPerIp = 5;
$minIntervalSeconds = 8;

function safeStrLen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function newCsrfToken(): string
{
    try {
        return bin2hex(random_bytes(32));
    } catch (Exception $e) {
        return hash('sha256', uniqid('csrf', true) . mt_rand());
    }
}

function isForgotRateLimited(Database $db, string $ipAddress, int $windowMinutes, int $maxAttempts): bool
{
    $windowMinutes = max(1, $windowMinutes);
    $maxAttempts = max(1, $maxAttempts);

    $sql = "
        SELECT COUNT(*) AS total
        FROM activity_logs
        WHERE ip_address = ?
          AND action IN ('forgot_password_success', 'forgot_password_failed', 'forgot_password_blocked')
          AND created_at >= DATE_SUB(NOW(), INTERVAL {$windowMinutes} MINUTE)
    ";

    $stmt = $db->query($sql, [$ipAddress]);
    if (!$stmt) {
        return false;
    }

    $row = $stmt->fetch();
    return (int) ($row['total'] ?? 0) >= $maxAttempts;
}

function logForgotEvent(string $action, string $details): void
{
    if (!function_exists('logActivity')) {
        return;
    }
    logActivity(0, 'public', $action, $details);
}

if (empty($_SESSION['forgot_password_csrf'])) {
    $_SESSION['forgot_password_csrf'] = newCsrfToken();
}

$csrfToken = $_SESSION['forgot_password_csrf'];

// Load site contact info (optional)
try {
    $siteStmt = $db->query("SELECT site_name, site_phone, site_email FROM site LIMIT 1");
    if ($siteStmt) {
        $siteInfo = $siteStmt->fetch();
    }
} catch (Exception $e) {
    $siteInfo = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $role = trim($_POST['role'] ?? 'siswa');
    $identifier = strtoupper(trim($_POST['identifier'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $clientIp = getClientIP();
    $identifierHash = $identifier !== '' ? substr(hash('sha256', $identifier), 0, 16) : '-';

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Permintaan tidak valid. Muat ulang halaman lalu coba lagi.";
        logForgotEvent('forgot_password_blocked', "csrf_invalid|ip={$clientIp}");
    } elseif (isForgotRateLimited($db, $clientIp, $rateWindowMinutes, $maxAttemptsPerIp)) {
        $error = "Terlalu banyak percobaan. Coba lagi dalam 15 menit.";
        logForgotEvent('forgot_password_blocked', "rate_limit_ip|ip={$clientIp}");
    } elseif (
        isset($_SESSION['forgot_password_last_attempt']) &&
        (time() - (int) $_SESSION['forgot_password_last_attempt']) < $minIntervalSeconds
    ) {
        $error = "Tunggu beberapa detik sebelum mencoba lagi.";
        logForgotEvent('forgot_password_blocked', "cooldown|ip={$clientIp}");
    } elseif ($role !== 'siswa') {
        // Public page only allows student reset.
        $error = "Reset password guru hanya bisa dilakukan admin melalui dashboard admin.";
        logForgotEvent('forgot_password_blocked', "non_student_role|ip={$clientIp}|role={$role}");
    } elseif ($identifier === '' || $name === '') {
        $error = "Semua field wajib diisi.";
    } elseif (!preg_match('/^[A-Z0-9]{4,20}$/', $identifier)) {
        $error = "Format kode siswa tidak valid.";
        logForgotEvent('forgot_password_failed', "invalid_code|ip={$clientIp}|id_hash={$identifierHash}");
    } elseif (safeStrLen($name) < 3 || safeStrLen($name) > 120) {
        $error = "Nama tidak valid.";
    } else {
        $_SESSION['forgot_password_last_attempt'] = time();

        $stmt = $db->query(
            "SELECT id, student_code, student_name FROM student WHERE student_code = ? LIMIT 1",
            [$identifier]
        );

        if (!$stmt) {
            $error = "Terjadi kendala saat memeriksa data. Coba lagi.";
            logForgotEvent('forgot_password_failed', "query_failed|ip={$clientIp}|id_hash={$identifierHash}");
        } else {
            $student = $stmt->fetch();

            if (!$student) {
                $error = "Kode siswa tidak ditemukan di database.";
                logForgotEvent('forgot_password_failed', "student_not_found|ip={$clientIp}|id_hash={$identifierHash}");
            } else {
                // Strict validation: must match exactly (character by character).
                $dbName = trim((string) ($student['student_name'] ?? ''));
                $inputName = trim($name);

                if (!hash_equals($dbName, $inputName)) {
                    $error = "Nama/username tidak cocok dengan kode siswa tersebut.";
                    logForgotEvent('forgot_password_failed', "name_mismatch|ip={$clientIp}|id_hash={$identifierHash}");
                } else {
                    $newPassword = $student['student_code'];
                    $hashedPassword = hash('sha256', $newPassword . PASSWORD_SALT);
                    $updated = $db->query(
                        "UPDATE student SET student_password = ? WHERE id = ?",
                        [$hashedPassword, $student['id']]
                    );

                    if ($updated) {
                        $success = "Password berhasil direset. Silakan login dengan password kode siswa.";
                        logForgotEvent('forgot_password_success', "student_reset|ip={$clientIp}|id_hash={$identifierHash}");
                    } else {
                        $error = "Terjadi kendala saat reset password. Coba lagi.";
                        logForgotEvent('forgot_password_failed', "update_failed|ip={$clientIp}|id_hash={$identifierHash}");
                    }
                }
            }
        }
    }

    // Rotate CSRF token after every POST.
    $_SESSION['forgot_password_csrf'] = newCsrfToken();
    $csrfToken = $_SESSION['forgot_password_csrf'];
}

$contactParts = [];
if (!empty($siteInfo['site_phone'])) {
    $contactParts[] = htmlspecialchars($siteInfo['site_phone'], ENT_QUOTES, 'UTF-8');
}
if (!empty($siteInfo['site_email'])) {
    $contactParts[] = htmlspecialchars($siteInfo['site_email'], ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa password - presenova</title>
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#ffffff">
    <link rel="apple-touch-icon" href="assets/images/apple-touch-icon_login.png?v=20260212c">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16_login.png?v=20260212c">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32_login.png?v=20260212c">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon_login.ico?v=20260212c">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .login-container {
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            background: var(--gradient-green);
            border-radius: 16px;
            margin-bottom: 1rem;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.25);
        }

        .logo-container img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .login-title {
            font-size: 1.7rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.4rem;
        }

        .login-subtitle {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .login-card {
            background: var(--white);
            border-radius: 16px;
            padding: 2.25rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(16, 185, 129, 0.1);
        }

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

        .info-box {
            background: rgba(16, 185, 129, 0.06);
            border: 1px dashed rgba(16, 185, 129, 0.3);
            color: var(--text-dark);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .info-box strong {
            color: var(--dark-green);
        }

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
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
            background: #f3f2f2;
            color: var(--text-dark);
        }

        select.form-control {
            appearance: auto;
            -webkit-appearance: menulist;
            -moz-appearance: menulist;
        }

        select.form-control option {
            color: var(--text-dark);
            background: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .role-help {
            margin-top: 0.5rem;
            font-size: 0.82rem;
            color: var(--text-light);
        }

        .btn-submit {
            width: 100%;
            padding: 0.9rem;
            background: var(--gradient-green);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .login-footer a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-container">
                <img src="assets/images/presenova.png" alt="Presenova">
            </div>
            <h1 class="login-title">Lupa Password</h1>
            <p class="login-subtitle">Presenova Present</p>
        </div>

        <div class="login-card">
            <?php if (!empty($error)) : ?>
                <div class="message-box error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)) : ?>
                <div class="message-box success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <strong>Catatan:</strong> halaman ini hanya untuk reset akun <strong>Siswa</strong>.
                Akun Guru wajib direset oleh admin.
                <?php if (!empty($contactParts)) : ?>
                    <br>
                    <strong>Kontak:</strong> <?php echo implode(' | ', $contactParts); ?>
                <?php endif; ?>
            </div>

            <form method="post" id="resetForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label class="form-label" for="role">Role</label>
                    <select class="form-control" id="role" name="role">
                        <option value="siswa" selected>Siswa</option>
                        <option value="guru" disabled>Guru (reset via admin)</option>
                    </select>
                    <p class="role-help">Guru harap mereset melalui admin.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="identifier">Kode Siswa</label>
                    <input type="text"
                           class="form-control"
                           id="identifier"
                           name="identifier"
                           inputmode="text"
                           pattern="[A-Za-z0-9]{4,20}"
                           minlength="4"
                           maxlength="20"
                           autocapitalize="characters"
                           placeholder="Masukkan kode siswa"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="name">Nama Lengkap / Username</label>
                    <input type="text"
                           class="form-control"
                           id="name"
                           name="name"
                           minlength="3"
                           maxlength="120"
                           placeholder="Masukkan nama persis seperti di database"
                           required>
                    <p class="role-help">Wajib sama persis dengan data kode siswa anda (beda 1 huruf akan ditolak).</p>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-rotate-left"></i> Reset Password
                </button>
            </form>

            <div class="login-footer">
                <a href="login.php">Kembali ke Login</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const identifierInput = document.getElementById('identifier');
            if (identifierInput) {
                identifierInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/\s+/g, '');
                });
            }
        });
    </script>
</body>
</html>
