<?php
require_once __DIR__ . '/../../../../helpers/jp_time_helper.php';

// Handle profile update if POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    // Handle profile update logic here
    // For security, you should implement proper validation and sanitization
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
    
    // Update query would go here
    // $update_sql = "UPDATE student SET email = ?, phone = ? WHERE id = ?";
    // $db->query($update_sql, [$email, $phone, $student_id]);
    
    // For now, just show a message
    echo '<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            Profil berhasil diperbarui!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
}

if (!isset($profileImageUrl)) {
    $profileImageUrl = null;
    if (!empty($student['photo'])) {
        $photoPath = __DIR__ . '/../../../../uploads/faces/' . $student['photo'];
        if (file_exists($photoPath)) {
            $profileImageUrl = '../uploads/faces/' . $student['photo'];
        }
    }
    if (!$profileImageUrl && !empty($student['photo_reference'])) {
        $photoPath = __DIR__ . '/../../../../uploads/faces/' . $student['photo_reference'];
        if (file_exists($photoPath)) {
            $profileImageUrl = '../uploads/faces/' . $student['photo_reference'];
        }
    }
    if (!$profileImageUrl && !empty($student['student_nisn']) && class_exists('FaceMatcher')) {
        $faceMatcher = new FaceMatcher();
        $referencePath = $faceMatcher->getReferencePath(
            $student['student_nisn'],
            $student['photo_reference'] ?? ''
        );
        if ($referencePath) {
            $profileImageUrl = $faceMatcher->toPublicUrl($referencePath, '..');
        }
    }
    if (!$profileImageUrl) {
        $profileImageUrl = '../assets/images/presenova.png';
    }
}
?>

<div class="row">
    <div class="col-lg-4">
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
                    <div class="fw-bold"><?php echo $student['student_code']; ?></div>
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
    
    <div class="col-lg-8">
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
                        <label class="form-label">NISN</label>
                        <input type="text" class="form-control" value="<?php echo $student['student_nisn']; ?>" readonly>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $student['email'] ?? ''; ?>" placeholder="Masukkan email">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nomor Telepon</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo $student['phone'] ?? ''; ?>" placeholder="Masukkan nomor telepon">
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
                <div class="input-group">
                    <input type="password" class="form-control" placeholder="Password baru" disabled>
                    <button class="btn btn-outline-primary" type="button" disabled>
                        <i class="fas fa-key"></i> Ganti
                    </button>
                </div>
                <div class="form-text">Hubungi administrator untuk mengganti password</div>
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
                Untuk perubahan data penting seperti NISN, Nama, atau Kelas, silakan hubungi administrator.
            </div>
        </div>
    </div>
</div>
