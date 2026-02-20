<?php

$profileAlert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileAlert = [
            'type' => 'danger',
            'message' => 'Format email tidak valid.'
        ];
    } elseif (strlen($email) > 100) {
        $profileAlert = [
            'type' => 'danger',
            'message' => 'Email terlalu panjang (maksimal 100 karakter).'
        ];
    } elseif (strlen($phone) > 30) {
        $profileAlert = [
            'type' => 'danger',
            'message' => 'Nomor telepon terlalu panjang (maksimal 30 karakter).'
        ];
    } else {
        try {
            $pdo = $db->getConnection();
            $columnStmt = $pdo->query("SHOW COLUMNS FROM student");
            $availableColumns = $columnStmt ? array_column($columnStmt->fetchAll(PDO::FETCH_ASSOC), 'Field') : [];

            $emailColumn = in_array('email', $availableColumns, true)
                ? 'email'
                : (in_array('student_email', $availableColumns, true) ? 'student_email' : null);
            $phoneColumn = in_array('phone', $availableColumns, true)
                ? 'phone'
                : (in_array('student_phone', $availableColumns, true) ? 'student_phone' : null);

            // Fallback: buat kolom standar jika belum ada, agar data profil bisa disimpan.
            if ($emailColumn === null || $phoneColumn === null) {
                $alterParts = [];
                if ($emailColumn === null && !in_array('student_email', $availableColumns, true)) {
                    $alterParts[] = "ADD COLUMN email VARCHAR(100) NULL";
                    $emailColumn = 'email';
                }
                if ($phoneColumn === null && !in_array('student_phone', $availableColumns, true)) {
                    $alterParts[] = "ADD COLUMN phone VARCHAR(30) NULL";
                    $phoneColumn = 'phone';
                }
                if (!empty($alterParts)) {
                    $pdo->exec("ALTER TABLE student " . implode(', ', $alterParts));
                }
            }

            $updateFields = [];
            $updateParams = [];
            if ($emailColumn !== null) {
                $updateFields[] = "{$emailColumn} = ?";
                $updateParams[] = ($email === '' ? null : $email);
            }
            if ($phoneColumn !== null) {
                $updateFields[] = "{$phoneColumn} = ?";
                $updateParams[] = ($phone === '' ? null : $phone);
            }

            if (empty($updateFields)) {
                $profileAlert = [
                    'type' => 'danger',
                    'message' => 'Kolom data profil (email/telepon) tidak tersedia di database.'
                ];
            } else {
                $updateParams[] = $student_id;
                $updateSql = "UPDATE student SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $saved = $updateStmt->execute($updateParams);

                if ($saved) {
                    // Refresh data siswa agar nilai terbaru langsung tampil tanpa reload tambahan.
                    $reloadSql = "SELECT s.*, c.class_name, j.name as jurusan_name
                                  FROM student s
                                  LEFT JOIN class c ON s.class_id = c.class_id
                                  LEFT JOIN jurusan j ON s.jurusan_id = j.jurusan_id
                                  WHERE s.id = ?";
                    $reloadStmt = $db->query($reloadSql, [$student_id]);
                    if ($reloadStmt) {
                        $freshStudent = $reloadStmt->fetch(PDO::FETCH_ASSOC);
                        if ($freshStudent) {
                            $student = $freshStudent;
                        }
                    }

                    $profileAlert = [
                        'type' => 'success',
                        'message' => 'Profil berhasil diperbarui.'
                    ];
                } else {
                    $profileAlert = [
                        'type' => 'danger',
                        'message' => 'Gagal menyimpan perubahan profil.'
                    ];
                }
            }
        } catch (Throwable $e) {
            $profileAlert = [
                'type' => 'danger',
                'message' => 'Terjadi kesalahan saat menyimpan profil: ' . $e->getMessage()
            ];
        }
    }
}

if (!isset($profileImageUrl)) {
    $profileImageUrl = '';
    if (!empty($student['photo'])) {
        $profileImageUrl = face_reference_public_url((string) $student['photo']);
    }
    if ($profileImageUrl === '' && !empty($student['photo_reference'])) {
        $profileImageUrl = face_reference_public_url((string) $student['photo_reference']);
    }
    if ($profileImageUrl === '' && !empty($student['student_nisn']) && class_exists('FaceMatcher')) {
        $faceMatcher = new FaceMatcher();
        $referencePath = $faceMatcher->getReferencePath(
            $student['student_nisn'],
            $student['photo_reference'] ?? ''
        );
        if ($referencePath) {
            $normalizedReference = face_reference_relative_from_file($referencePath);
            if ($normalizedReference !== '') {
                $profileImageUrl = face_reference_public_url($normalizedReference);
            }
            if ($profileImageUrl === '') {
                $profileImageUrl = $faceMatcher->toPublicUrl($referencePath, '..');
            }
        }
    }
    if ($profileImageUrl === '') {
        $profileImageUrl = asset('assets/images/presenova.png');
    }
}

$profileEmailValue = (string)($student['email'] ?? ($student['student_email'] ?? ''));
$profilePhoneValue = (string)($student['phone'] ?? ($student['student_phone'] ?? ''));
?>

<?php if ($profileAlert !== null): ?>
<div class="alert alert-<?php echo htmlspecialchars((string)$profileAlert['type'], ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show mb-4" role="alert">
    <i class="fas <?php echo $profileAlert['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
    <?php echo htmlspecialchars((string)$profileAlert['message'], ENT_QUOTES, 'UTF-8'); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4 student-profile-layout">
    <div class="col-lg-4 student-profile-sidebar">
        <!-- Profile Card -->
        <div class="profile-card mb-4">
            <div class="profile-avatar-large mb-4">
                <img src="<?php echo $profileImageUrl; ?>" alt="Foto Profil Siswa">
            </div>
            
            <h4 class="text-center mb-3"><?php echo $student['student_name']; ?></h4>
            
            <div class="text-center mb-4">
                <span class="badge bg-primary"><?php echo $student['class_name']; ?></span>
                <span class="badge bg-secondary"><?php echo $student['jurusan_name']; ?></span>
            </div>
            
            <div class="student-info">
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">NISN</label>
                    <div class="fw-bold"><?php echo $student['student_nisn']; ?></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Kode Siswa</label>
                    <div class="d-flex align-items-center gap-2 student-code-row">
                        <div class="fw-bold" id="profileStudentCodeMask">******</div>
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                id="revealStudentCodeBtn"
                                title="Lihat kode siswa dengan password Anda">
                            <i class="fas fa-eye me-1"></i>Lihat
                        </button>
                    </div>
                    <small class="text-muted">Kode siswa hanya ditampilkan setelah verifikasi password.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Status</label>
                    <div>
                        <span class="badge bg-success">Aktif</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Terdaftar Sejak</label>
                    <div class="fw-bold">
                        <?php echo date('d F Y', strtotime($student['created_at'] ?? '2024-01-01')); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="dashboard-card profile-compact">
            <h5 class="mb-3"><i class="fas fa-chart-pie text-primary me-2"></i>Statistik Absensi</h5>
            
            <?php
            // Statistik bulan berjalan yang sinkron dengan data schedule + presence (termasuk Alpa)
            $tz = new DateTimeZone('Asia/Jakarta');
            $nowWib = new DateTime('now', $tz);
            $monthStartDate = $nowWib->format('Y-m-01');
            $monthEndDate = $nowWib->format('Y-m-t');
            $monthStartDateTime = $monthStartDate . ' 00:00:00';
            $monthEndDateTime = $monthEndDate . ' 23:59:59';

            $statusRows = $db->query("SELECT present_id, present_name FROM present_status")->fetchAll();
            $statusMap = [
                'hadir' => [],
                'sakit' => [],
                'izin' => [],
                'alpa' => []
            ];
            foreach ($statusRows as $statusRow) {
                $statusId = (int)($statusRow['present_id'] ?? 0);
                $statusName = strtolower(trim((string)($statusRow['present_name'] ?? '')));
                if ($statusId <= 0) {
                    continue;
                }
                if ($statusName === 'tidak hadir') {
                    $statusName = 'alpa';
                }
                if (array_key_exists($statusName, $statusMap)) {
                    $statusMap[$statusName][] = $statusId;
                }
            }
            if (empty($statusMap['hadir'])) {
                $statusMap['hadir'] = [1];
            }
            if (empty($statusMap['sakit'])) {
                $statusMap['sakit'] = [2];
            }
            if (empty($statusMap['izin'])) {
                $statusMap['izin'] = [3];
            }

            $presenceCountByStatusId = [];
            $presenceStatsSql = "SELECT p.present_id, COUNT(*) as total
                                FROM presence p
                                WHERE p.student_id = ?
                                AND p.presence_date BETWEEN ? AND ?
                                GROUP BY p.present_id";
            $presenceStatsStmt = $db->query($presenceStatsSql, [$student_id, $monthStartDateTime, $monthEndDateTime]);
            $presenceStatsRows = $presenceStatsStmt ? $presenceStatsStmt->fetchAll() : [];
            foreach ($presenceStatsRows as $presenceStatsRow) {
                $presenceStatusId = (int)($presenceStatsRow['present_id'] ?? 0);
                $presenceCountByStatusId[$presenceStatusId] = (int)($presenceStatsRow['total'] ?? 0);
            }

            $countHadir = 0;
            foreach ($statusMap['hadir'] as $statusId) {
                $countHadir += (int)($presenceCountByStatusId[$statusId] ?? 0);
            }
            $countSakit = 0;
            foreach ($statusMap['sakit'] as $statusId) {
                $countSakit += (int)($presenceCountByStatusId[$statusId] ?? 0);
            }
            $countIzin = 0;
            foreach ($statusMap['izin'] as $statusId) {
                $countIzin += (int)($presenceCountByStatusId[$statusId] ?? 0);
            }
            $countAlpaPresence = 0;
            foreach ($statusMap['alpa'] as $statusId) {
                $countAlpaPresence += (int)($presenceCountByStatusId[$statusId] ?? 0);
            }

            // Alpa tambahan: jadwal yang sudah lewat tapi belum ada record presence
            $alpaFromSchedule = 0;
            $alpaScheduleSql = "SELECT
                                    ss.schedule_date,
                                    COALESCE(ss.time_in, sh.time_in) AS schedule_time_in,
                                    COALESCE(ss.time_out, sh.time_out) AS schedule_time_out
                                FROM student_schedule ss
                                JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
                                LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
                                LEFT JOIN presence p ON p.student_schedule_id = ss.student_schedule_id
                                WHERE ss.student_id = ?
                                AND ss.schedule_date BETWEEN ? AND ?
                                AND p.presence_id IS NULL";
            $alpaScheduleStmt = $db->query($alpaScheduleSql, [$student_id, $monthStartDate, $monthEndDate]);
            $alpaScheduleRows = $alpaScheduleStmt ? $alpaScheduleStmt->fetchAll() : [];
            foreach ($alpaScheduleRows as $alpaScheduleRow) {
                $scheduleDate = (string)($alpaScheduleRow['schedule_date'] ?? '');
                if ($scheduleDate === '') {
                    continue;
                }
                [$scheduleStart, $scheduleEnd] = buildScheduleWindow(
                    $scheduleDate,
                    (string)($alpaScheduleRow['schedule_time_in'] ?? '00:00:00'),
                    (string)($alpaScheduleRow['schedule_time_out'] ?? '00:00:00'),
                    $tz,
                    0
                );
                if ($nowWib > $scheduleEnd) {
                    $alpaFromSchedule++;
                }
            }

            $countAlpa = $countAlpaPresence + $alpaFromSchedule;
            $countTidakHadir = $countSakit + $countIzin + $countAlpa;
            $totalRekap = $countHadir + $countTidakHadir;
            $attendance_rate = $totalRekap > 0 ? round(($countHadir / $totalRekap) * 100, 1) : 0;
            ?>
            
            <div class="text-center mb-4">
                <div class="display-4 fw-bold text-primary"><?php echo $attendance_rate; ?>%</div>
                <div class="text-muted">Kehadiran Bulan Ini</div>
            </div>
            
            <div class="progress mb-3" style="height: 10px;">
                <div class="progress-bar bg-success" style="width: <?php echo $attendance_rate; ?>%"></div>
            </div>
            
            <div class="row text-center g-2">
                <div class="col-6 col-md-3">
                    <div class="fw-bold profile-stat-value profile-stat-hadir"><?php echo $countHadir; ?></div>
                    <div class="profile-stat-label profile-stat-hadir">Hadir</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-bold profile-stat-value profile-stat-sakit"><?php echo $countSakit; ?></div>
                    <div class="profile-stat-label profile-stat-sakit">Sakit</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-bold profile-stat-value profile-stat-izin"><?php echo $countIzin; ?></div>
                    <div class="profile-stat-label profile-stat-izin">Izin</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fw-bold profile-stat-value profile-stat-alpa"><?php echo $countAlpa; ?></div>
                    <div class="profile-stat-label profile-stat-alpa">Alpa</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8 student-profile-content">
        <!-- Update Profile Form -->
        <div class="dashboard-card mb-4 profile-compact">
            <h5 class="mb-4"><i class="fas fa-user-edit text-primary me-2"></i>Edit Profil</h5>
            
            <form method="POST" action="">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" value="<?php echo $student['student_name']; ?>" readonly>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kode Siswa</label>
                        <input type="text" class="form-control" value="******" readonly id="profileStudentCodeField">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profileEmailValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan email">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profilePhoneValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan nomor telepon">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kelas</label>
                        <input type="text" class="form-control" value="<?php echo $student['class_name']; ?>" readonly>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jurusan</label>
                        <input type="text" class="form-control" value="<?php echo $student['jurusan_name']; ?>" readonly>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Security Settings -->
        <div class="dashboard-card profile-compact">
            <h5 class="mb-4"><i class="fas fa-shield-alt text-primary me-2"></i>Keamanan</h5>
            
            <div class="mb-4">
                <label class="form-label">Ganti Password</label>
                <div class="input-group security-reset-group">
                    <input type="text" class="form-control" value="Reset password melalui halaman khusus" readonly>
                    <a class="btn btn-outline-primary" href="../forgot-password.php">
                        <i class="fas fa-key"></i> Ganti
                    </a>
                </div>
                <div class="form-text">Masukkan kode siswa dan nama sesuai data saat reset password.</div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Verifikasi Wajah</label>
                <div class="d-flex align-items-center">
                    <div>
                        <?php if (isset($_SESSION['has_face']) && $_SESSION['has_face']): ?>
                            <span class="badge bg-success">Terverifikasi</span>
                        <?php else: ?>
                            <span class="badge bg-warning">Belum Verifikasi</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Untuk perubahan data penting seperti kode siswa, nama, atau kelas, silakan hubungi administrator.
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const revealButton = document.getElementById('revealStudentCodeBtn');
    const codeMask = document.getElementById('profileStudentCodeMask');
    const codeField = document.getElementById('profileStudentCodeField');

    if (!revealButton || !codeMask) {
        return;
    }

    const hiddenMask = '******';
    let isVisible = false;
    let cachedCode = '';

    function setCodeVisibility(visible, code) {
        if (visible) {
            const normalizedCode = String(code || '').toUpperCase().trim();
            if (!normalizedCode) {
                return;
            }
            codeMask.textContent = normalizedCode;
            if (codeField) {
                codeField.value = normalizedCode;
            }
            revealButton.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Sembunyikan';
            revealButton.title = 'Sembunyikan kode siswa';
            isVisible = true;
            return;
        }

        codeMask.textContent = hiddenMask;
        if (codeField) {
            codeField.value = hiddenMask;
        }
        revealButton.innerHTML = '<i class="fas fa-eye me-1"></i>Lihat';
        revealButton.title = 'Lihat kode siswa dengan password Anda';
        isVisible = false;
    }

    setCodeVisibility(false, '');

    revealButton.addEventListener('click', async function() {
        if (isVisible) {
            setCodeVisibility(false, '');
            return;
        }

        if (cachedCode) {
            setCodeVisibility(true, cachedCode);
            return;
        }

        const password = await AppDialog.prompt('Masukkan password siswa untuk melihat kode siswa:', {
            title: 'Verifikasi Password Siswa',
            inputType: 'password',
            placeholder: 'Masukkan password Anda',
            okText: 'Verifikasi'
        });
        if (!password) {
            return;
        }

        fetch('ajax/reveal_student_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: new URLSearchParams({ password: password }).toString()
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            if (result && result.success) {
                const code = String(result.student_code || '').toUpperCase() || '******';
                if (!code || code === hiddenMask) {
                    alert('Kode siswa kosong');
                    return;
                }
                cachedCode = code;
                setCodeVisibility(true, cachedCode);
            } else {
                alert(result && result.message ? result.message : 'Gagal menampilkan kode siswa');
            }
        })
        .catch(function() {
            alert('Terjadi kesalahan saat memeriksa password siswa');
        });
    });
});
</script>
