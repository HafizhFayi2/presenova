<?php
$changeAlert = null;
$studentId = (int) ($student['id'] ?? ($_SESSION['student_id'] ?? 0));
$studentNisn = trim((string) ($student['student_nisn'] ?? ''));
$studentCode = trim((string) ($student['student_code'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_student_password_forced'])) {
    $oldPassword = trim((string) ($_POST['old_password'] ?? ''));
    $newPassword = trim((string) ($_POST['new_password'] ?? ''));
    $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));

    $storedHash = (string) ($student['student_password'] ?? '');
    $oldHash = hash('sha256', $oldPassword . PASSWORD_SALT);

    $defaultCandidates = array_filter([
        $studentNisn,
        $studentCode,
        'siswa123',
    ], static fn ($value) => $value !== '');

    if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $changeAlert = ['type' => 'danger', 'message' => 'Semua field wajib diisi.'];
    } elseif ($storedHash === '' || !hash_equals($storedHash, $oldHash)) {
        $changeAlert = ['type' => 'danger', 'message' => 'Password lama tidak sesuai.'];
    } elseif (strlen($newPassword) < 8) {
        $changeAlert = ['type' => 'danger', 'message' => 'Password baru minimal 8 karakter.'];
    } elseif ($newPassword !== $confirmPassword) {
        $changeAlert = ['type' => 'danger', 'message' => 'Konfirmasi password baru tidak cocok.'];
    } else {
        $isDefaultNewPassword = false;
        foreach ($defaultCandidates as $candidate) {
            if (hash_equals($candidate, $newPassword)) {
                $isDefaultNewPassword = true;
                break;
            }
        }

        if ($isDefaultNewPassword) {
            $changeAlert = ['type' => 'danger', 'message' => 'Password baru tidak boleh memakai password default.'];
        } else {
            $newHash = hash('sha256', $newPassword . PASSWORD_SALT);
            $updated = $db->query('UPDATE student SET student_password = ? WHERE id = ?', [$newHash, $studentId]);
            if ($updated) {
                $student['student_password'] = $newHash;
                $_SESSION['student_password_updated_at'] = date('c');
                if (function_exists('logActivity')) {
                    logActivity($studentId, 'student', 'password_changed', 'Student changed password after forced default-password policy');
                }
                if (function_exists('pushNotifyStudent')) {
                    pushNotifyStudent(
                        (int) $studentId,
                        'password_changed',
                        'Password Berhasil Diperbarui',
                        'Password akun siswa Anda sudah diperbarui. Gunakan password baru untuk login berikutnya.',
                        '/dashboard/siswa.php?page=profil'
                    );
                }
                if (function_exists('auditMasterData')) {
                    auditMasterData(
                        $studentId,
                        'student',
                        'credential',
                        (string) $studentId,
                        'change_password_forced',
                        ['password' => 'masked'],
                        ['password' => 'masked'],
                        ['source' => 'siswa/change_password']
                    );
                }

                $changeAlert = ['type' => 'success', 'message' => 'Password berhasil diperbarui. Anda sekarang dapat menggunakan dashboard seperti biasa.'];
                header('Refresh: 1; URL=siswa.php?page=dashboard');
            } else {
                $changeAlert = ['type' => 'danger', 'message' => 'Gagal memperbarui password.'];
            }
        }
    }
}
?>

<div class="dashboard-card profile-compact">
    <h5 class="mb-4"><i class="fas fa-lock text-primary me-2"></i>Wajib Ganti Password</h5>
    <div class="alert alert-warning">
        <i class="fas fa-shield-alt me-2"></i>
        Demi keamanan akun, Anda harus mengganti password default sebelum melanjutkan ke dashboard.
    </div>

    <?php if ($changeAlert !== null): ?>
        <div class="alert alert-<?php echo htmlspecialchars((string) $changeAlert['type'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars((string) $changeAlert['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="siswa.php?page=change_password" autocomplete="off">
        <input type="hidden" name="change_student_password_forced" value="1">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Password Lama</label>
                <input type="password" class="form-control" name="old_password" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Password Baru</label>
                <input type="password" class="form-control" name="new_password" minlength="8" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Konfirmasi Password Baru</label>
                <input type="password" class="form-control" name="confirm_password" minlength="8" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Simpan Password Baru
        </button>
    </form>
</div>
