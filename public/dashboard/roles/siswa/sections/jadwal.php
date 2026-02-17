<?php
// sections/jadwal.php
require_once '../includes/database_helper.php';

$student_id = $_SESSION['student_id'];
// Ambil setting toleransi waktu absensi
$site_stmt = $db->query("SELECT time_tolerance FROM site LIMIT 1");
$site_setting = $site_stmt ? $site_stmt->fetch(PDO::FETCH_ASSOC) : null;
$time_tolerance = isset($site_setting['time_tolerance']) ? (int) $site_setting['time_tolerance'] : 15;

// Ambil data siswa untuk mendapatkan class_id dan jurusan_id
$sql_student = "
    SELECT s.*, c.class_name, j.name as jurusan_name
    FROM student s
    LEFT JOIN class c ON s.class_id = c.class_id
    LEFT JOIN jurusan j ON s.jurusan_id = j.jurusan_id
    WHERE s.id = ?
";

$stmt = $db->query($sql_student, [$student_id]);
$student_data = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

// Initialize Database Helper
$dbHelper = new DatabaseHelper($db);

$schedules = [];
$grouped_schedule = [];
$day_mapping = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$day_index_map = [
    'Senin' => 1,
    'Selasa' => 2,
    'Rabu' => 3,
    'Kamis' => 4,
    'Jumat' => 5,
    'Sabtu' => 6,
    'Minggu' => 7
];
$tz = new DateTimeZone('Asia/Jakarta');
$now_wib = new DateTime('now', $tz);
$today_indonesian = $day_mapping[$now_wib->format('l')] ?? '';
$today_label = $now_wib->format('d F Y');
$today_date = $now_wib->format('Y-m-d');
$today_day_id = (int) $now_wib->format('N'); // 1=Senin
$week_start = (clone $now_wib)->modify('monday this week')->setTime(0, 0, 0);
$reset_time = (clone $week_start)->modify('+5 days')->setTime(15, 0, 0);
if ($now_wib >= $reset_time) {
    $week_start->modify('+7 days');
    $reset_time->modify('+7 days');
}
$now_ts = $now_wib->getTimestamp();
$countdown_seconds = 120;
$tolerance_seconds = max(0, (int) $time_tolerance) * 60;

// Generate jadwal berdasarkan class_id siswa
if ($student_data && $student_data['class_id']) {
    $schedules = $dbHelper->getStudentSchedule($student_id, $student_data['class_id']);

    // Pastikan student_schedule tersedia untuk jadwal hari ini agar status realtime akurat
    foreach ($schedules as &$schedule) {
        if (($schedule['day_name'] ?? '') !== $today_indonesian) {
            continue;
        }

        if (!empty($schedule['student_schedule_id'])) {
            continue;
        }

        $teacherScheduleId = $schedule['schedule_id'] ?? null;
        if (!$teacherScheduleId) {
            continue;
        }

        $checkSql = "SELECT student_schedule_id FROM student_schedule 
                     WHERE student_id = ? AND teacher_schedule_id = ? AND schedule_date = CURDATE()
                     ORDER BY student_schedule_id ASC LIMIT 1";
        $checkStmt = $db->query($checkSql, [$student_id, $teacherScheduleId]);
        $existing = $checkStmt ? $checkStmt->fetch(PDO::FETCH_ASSOC) : null;

        if ($existing && !empty($existing['student_schedule_id'])) {
            $schedule['student_schedule_id'] = $existing['student_schedule_id'];
            continue;
        }

        $createSql = "INSERT INTO student_schedule 
                      (student_id, teacher_schedule_id, schedule_date, time_in, time_out, status)
                      VALUES (?, ?, CURDATE(), ?, ?, 'ACTIVE')";
        $createStmt = $db->query($createSql, [
            $student_id,
            $teacherScheduleId,
            $schedule['time_in'],
            $schedule['time_out']
        ]);
        if ($createStmt) {
            $schedule['student_schedule_id'] = $db->lastInsertId();
        }
    }
    unset($schedule);
    
    // Kelompokkan jadwal per hari
    foreach ($schedules as $schedule) {
        $day = $schedule['day_name'];
        if (!isset($grouped_schedule[$day])) {
            $grouped_schedule[$day] = [];
        }
        $grouped_schedule[$day][] = $schedule;
    }
}

$attended_count = 0;
$pending_count = 0;
$alpa_count = 0;
foreach ($schedules as $schedule) {
    $attendance_count = isset($schedule['attendance_count']) ? (int) $schedule['attendance_count'] : 0;
    if ($attendance_count > 0) {
        $attended_count++;
        continue;
    }

    $day = $schedule['day_name'] ?? '';
    $day_index = isset($schedule['day_id']) ? (int) $schedule['day_id'] : ($day_index_map[$day] ?? 0);
    if ($day_index <= 0) {
        $pending_count++;
        continue;
    }

    $schedule_date_obj = (clone $week_start)->modify('+' . ($day_index - 1) . ' days');
    $schedule_date = $schedule_date_obj->format('Y-m-d');
    if ($schedule_date > $today_date) {
        $pending_count++;
        continue;
    }

    [$start_dt, $end_dt] = buildScheduleWindow(
        $schedule_date,
        $schedule['time_in'] ?? '00:00:00',
        $schedule['time_out'] ?? '00:00:00',
        $tz,
        (int) $time_tolerance
    );
    if ($now_ts > $end_dt->getTimestamp()) {
        $alpa_count++;
    } else {
        $pending_count++;
    }
}
?>

<!-- START: Jadwal Section -->
<div class="jadwal-section fade-in">
    <!-- Header -->
    <div class="section-header">
        <div class="d-flex justify-content-between align-items-center mb-4 jadwal-header-bar">
            <div>
                <h4 class="section-title mb-1">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>Jadwal Pelajaran
                </h4>
                <p class="text-muted mb-0">SMKN 1 Cikarang Selatan - Tahun Ajaran 2023/2024</p>
            </div>
            <div class="d-flex gap-2 jadwal-header-actions">
                <button type="button" class="btn btn-sm btn-outline-primary btn-print" onclick="printSchedule()">
                    <i class="fas fa-print me-1"></i> Cetak
                </button>
                <button type="button" class="btn btn-sm btn-primary btn-refresh" onclick="refreshSchedule()">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </button>
            </div>
        </div>
    </div>
    
    <?php if ($student_data && $student_data['class_id']): ?>
        <!-- Student Info Card -->
        <div class="student-info-card mb-4">
            <div class="row g-3 student-info-grid">
                <div class="col-md-3">
                    <div class="info-item">
                        <div class="info-icon bg-primary">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="info-content">
                            <small class="text-muted">Nama Siswa</small>
                            <strong><?php echo htmlspecialchars($student_data['student_name']); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-item">
                        <div class="info-icon bg-info">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="info-content">
                            <small class="text-muted">Kelas</small>
                            <strong><?php echo htmlspecialchars($student_data['class_name']); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-item">
                        <div class="info-icon bg-success">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="info-content">
                            <small class="text-muted">Jurusan</small>
                            <strong><?php echo htmlspecialchars($student_data['jurusan_name']); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-item">
                        <div class="info-icon bg-warning">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="info-content">
                            <small class="text-muted">Hari/Tanggal</small>
                            <strong><?php echo $today_label; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Schedule Table -->
        <div class="liquidGlass-wrapper liquid-theme-sky liquid-table">
            <div class="liquidGlass-effect"></div>
            <div class="liquidGlass-tint"></div>
            <div class="liquidGlass-shine"></div>
            <div class="liquidGlass-content">
                <div class="schedule-container">
                    <div class="table-responsive jadwal-table-responsive">
                        <table class="table schedule-table table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th width="120">Hari</th>
                            <th width="100">Shift</th>
                            <th width="140">Waktu</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th width="100">Status</th>
                            <th width="100" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($grouped_schedule)): ?>
                            <?php 
                            $day_counter = 0;
                            foreach ($grouped_schedule as $day => $day_schedules): 
                                $rowspan = count($day_schedules);
                            ?>
                                <?php foreach ($day_schedules as $index => $schedule): 
                                    $attendance_count = isset($schedule['attendance_count']) ? (int)$schedule['attendance_count'] : 0;
                                    $is_attended = $attendance_count > 0;
                                    $attendance_is_late = isset($schedule['attendance_is_late']) && $schedule['attendance_is_late'] === 'Y';
                                    $student_schedule_id = $schedule['student_schedule_id'] ?? null;
                                    $day_index = isset($schedule['day_id']) ? (int)$schedule['day_id'] : ($day_index_map[$day] ?? 0);
                                    $schedule_date_obj = $day_index > 0
                                        ? (clone $week_start)->modify('+' . ($day_index - 1) . ' days')
                                        : (clone $now_wib);
                                    $schedule_date = $schedule_date_obj->format('Y-m-d');
                                    $is_today = ($schedule_date === $today_date);
                                    $is_future_day = ($schedule_date > $today_date);
                                    $action_enabled = false;
                                    $action_variant = 'secondary';
                                    
                                    // Initial status (will be updated realtime by JS)
                                    if ($is_attended) {
                                        if ($attendance_is_late) {
                                            $status_class = 'status-overdue';
                                            $status_text = 'OVERDUE';
                                            $status_icon = 'exclamation-triangle';
                                        } else {
                                            $status_class = 'status-success';
                                            $status_text = 'SUCCESS';
                                            $status_icon = 'check-circle';
                                        }
                                    } else {
                                        $status_class = 'status-muted';
                                        $status_text = 'MENUNGGU';
                                        $status_icon = 'clock';

                                        if ($is_future_day) {
                                            $status_class = 'status-muted';
                                            $status_text = 'MENUNGGU';
                                            $status_icon = 'clock';
                                        } else {
                                            [$start_dt, $end_dt, $base_end_dt] = buildScheduleWindow(
                                                $schedule_date,
                                                $schedule['time_in'] ?? '00:00:00',
                                                $schedule['time_out'] ?? '00:00:00',
                                                $tz,
                                                (int) $time_tolerance
                                            );
                                            $start_ts = $start_dt->getTimestamp();
                                            $end_ts = $end_dt->getTimestamp();
                                            $base_end_ts = $base_end_dt->getTimestamp();
                                            $countdown_start = $start_ts - $countdown_seconds;
                                            $overdue_end = $end_ts;

                                            if ($now_ts < $countdown_start) {
                                                $status_class = 'status-muted';
                                                $status_text = 'MENUNGGU';
                                                $status_icon = 'clock';
                                            } elseif ($now_ts >= $countdown_start && $now_ts < $start_ts) {
                                                $remaining = max(0, $start_ts - $now_ts);
                                                $mins = floor($remaining / 60);
                                                $secs = $remaining % 60;
                                                $status_class = 'status-countdown';
                                                $status_text = sprintf('COUNTDOWN %02d:%02d', $mins, $secs);
                                                $status_icon = 'hourglass-half';
                                            } elseif ($now_ts >= $start_ts && $now_ts <= $base_end_ts) {
                                                $status_class = 'status-active';
                                                $status_text = 'ACTIVE';
                                                $status_icon = 'clock';
                                                $action_enabled = true;
                                                $action_variant = 'success';
                                            } elseif ($now_ts > $base_end_ts && $now_ts <= $overdue_end) {
                                                $status_class = 'status-overdue';
                                                $status_text = 'OVERDUE';
                                                $status_icon = 'exclamation-triangle';
                                                $action_enabled = true;
                                                $action_variant = 'warning';
                                            } else {
                                                $status_class = 'status-closed';
                                                $status_text = 'CLOSED';
                                                $status_icon = 'times-circle';
                                            }
                                        }
                                    }
                        ?>
                                    <tr class="schedule-row"
                                        data-day="<?php echo $day; ?>"
                                        data-day-index="<?php echo $day_index; ?>"
                                        data-attended="<?php echo $is_attended ? '1' : '0'; ?>"
                                        data-time-in="<?php echo $schedule['time_in']; ?>"
                                        data-time-out="<?php echo $schedule['time_out']; ?>"
                                        data-schedule-date="<?php echo $schedule_date; ?>"
                                        data-shift="<?php echo htmlspecialchars($schedule['shift_name']); ?>"
                                        data-schedule-id="<?php echo $student_schedule_id ? $student_schedule_id : ''; ?>"
                                        data-attendance-late="<?php echo $attendance_is_late ? '1' : '0'; ?>">
                                        <?php if ($index === 0): ?>
                                            <td rowspan="<?php echo $rowspan; ?>" class="day-cell align-middle">
                                                <div class="day-content <?php echo $is_today ? 'today' : ''; ?>">
                                                    <strong><?php echo $day; ?></strong>
                                                    <?php if ($is_today): ?>
                                                        <span class="badge bg-primary mt-1">HARI INI</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <td>
                                            <span class="badge bg-info"><?php echo $schedule['shift_name']; ?></span>
                                        </td>
                                        
                                        <td class="time-cell">
                                            <i class="fas fa-clock text-primary me-1"></i>
                                            <?php echo date('H:i', strtotime($schedule['time_in'])); ?> 
                                            - 
                                            <?php echo date('H:i', strtotime($schedule['time_out'])); ?>
                                        </td>
                                        
                                        <td class="subject-cell">
                                            <strong><?php echo htmlspecialchars($schedule['subject']); ?></strong>
                                        </td>
                                        
                                        <td class="teacher-cell">
                                            <div class="teacher-info">
                                                <div class="teacher-name">
                                                    <?php echo htmlspecialchars($schedule['teacher_name']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $schedule['teacher_code']; ?>
                                                </small>
                                            </div>
                                        </td>
                                        
                                        <td>
                                            <span class="badge schedule-status <?php echo $status_class; ?>">
                                                <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        
                                        <td class="text-center schedule-action">
                                            <?php if ($is_attended): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check"></i> Done
                                                </span>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-<?php echo $action_variant; ?> btn-attend schedule-action-btn" 
                                                        data-id="<?php echo $student_schedule_id ? $student_schedule_id : ''; ?>"
                                                        data-subject="<?php echo htmlspecialchars($schedule['subject']); ?>"
                                                        data-teacher="<?php echo htmlspecialchars($schedule['teacher_name']); ?>"
                                                        data-time="<?php echo $schedule['time_in']; ?>"
                                                        onclick="return openFaceVerification('<?php echo $student_schedule_id ? $student_schedule_id : ''; ?>');"
                                                        <?php echo ($action_enabled && $student_schedule_id) ? '' : 'disabled'; ?>>
                                                    <i class="fas fa-camera"></i> Absen
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times fa-2x text-muted mb-3"></i>
                                        <h5>Belum ada jadwal</h5>
                                        <p class="text-muted">Jadwal untuk kelas Anda belum tersedia</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mt-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($schedules); ?></h3>
                        <p>Total Jadwal</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $attended_count; ?></h3>
                        <p>Sudah Absen</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $pending_count; ?></h3>
                        <p>Belum Absen</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php 
                            $teachers = array_unique(array_column($schedules, 'teacher_name'));
                            echo count(array_filter($teachers));
                        ?></h3>
                        <p>Total Guru</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Summary -->
        <?php 
        $today_schedules = array_filter($schedules, function($s) use ($today_indonesian) {
            return $s['day_name'] == $today_indonesian;
        });
        ?>
        
        <?php if (!empty($today_schedules)): ?>
            <div class="today-summary-card mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-day me-2"></i>Ringkasan Hari Ini
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $today_attended = array_filter($today_schedules, function($s) {
                            return isset($s['attendance_count']) && $s['attendance_count'] > 0;
                        });
                        $today_pending = array_filter($today_schedules, function($s) use ($today_date, $now_ts, $tz, $time_tolerance) {
                            $attendance_count = isset($s['attendance_count']) ? (int) $s['attendance_count'] : 0;
                            if ($attendance_count > 0) {
                                return false;
                            }

                            [$start_dt, $end_dt] = buildScheduleWindow(
                                $today_date,
                                $s['time_in'] ?? '00:00:00',
                                $s['time_out'] ?? '00:00:00',
                                $tz,
                                (int) $time_tolerance
                            );

                            return $now_ts <= $end_dt->getTimestamp();
                        });
                        ?>
                        <div class="col-md-4">
                            <div class="summary-item">
                                <div class="summary-icon bg-primary">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div class="summary-content">
                                    <h4><?php echo count($today_schedules); ?></h4>
                                    <p>Jadwal Hari Ini</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-item">
                                <div class="summary-icon bg-success">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="summary-content">
                                    <h4><?php echo count($today_attended); ?></h4>
                                    <p>Sudah Absen</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-item">
                                <div class="summary-icon bg-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="summary-content">
                                    <h4><?php echo count($today_pending); ?></h4>
                                    <p>Belum Absen</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Anda belum terdaftar di kelas manapun. Silakan hubungi administrator.
        </div>
    <?php endif; ?>
</div>

<!-- Attendance Modal (Same as in riwayat.php but simplified) -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-camera me-2"></i>Absensi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="attendanceContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Memuat form absensi...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Print schedule (global)
window.printSchedule = function() {
    const baseUrl = new URL('roles/siswa/print/jadwal_print.php', window.location.href);
    baseUrl.searchParams.set('t', String(Date.now()));

    const printUrl = new URL(baseUrl.toString());
    printUrl.searchParams.set('autoprint', '1');

    const pdfUrl = new URL(baseUrl.toString());
    pdfUrl.searchParams.set('download', 'pdf');

    if (window.SchedulePrintDialog && typeof window.SchedulePrintDialog.open === 'function') {
        window.SchedulePrintDialog.open({
            title: 'Output Jadwal Siswa',
            message: 'Pilih Print untuk langsung cetak, atau Download PDF untuk menyimpan file.',
            printUrl: printUrl.toString(),
            pdfUrl: pdfUrl.toString()
        });
    } else {
        const printWindow = window.open('', '_blank');
        if (printWindow) {
            try {
                printWindow.opener = null;
            } catch (e) {}
            try {
                printWindow.location.replace(printUrl.toString());
            } catch (e) {
                printWindow.location.href = printUrl.toString();
            }
        } else {
            window.location.assign(printUrl.toString());
        }
    }
    return false;
};

// Refresh schedule (global)
window.refreshSchedule = function() {
    const btn = $('.btn-refresh');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memuat...');
    location.reload();
};

// Open face verification page from action button
window.openFaceVerification = function(scheduleId) {
    const cleanId = (scheduleId || '').toString().trim();
    if (cleanId !== '') {
        window.location.href = `?page=face_recognition&schedule_id=${encodeURIComponent(cleanId)}`;
    } else {
        window.location.href = '?page=face_recognition';
    }
    return false;
};

$(document).ready(function() {
    // Attendance button click -> arahkan ke validasi wajah
    $(document).on('click', '.btn-attend', function(event) {
        event.preventDefault();
        const scheduleId = $(this).data('id');
        openFaceVerification(scheduleId);
    });

    // Ensure button handlers are bound (fallback)
    $(document).on('click', '.btn-print', function() {
        window.printSchedule();
    });
    $(document).on('click', '.btn-refresh', function() {
        window.refreshSchedule();
    });
    
    // Highlight today's rows
    $('.schedule-row[data-day="<?php echo $today_indonesian; ?>"]').addClass('today-row');

    // Realtime status update (UTC+7)
    const toleranceMinutes = <?php echo (int) $time_tolerance; ?>;
    const countdownSeconds = 120;

    function getWeekStartWIB(date) {
        const d = new Date(date);
        const day = d.getDay(); // 0=Sunday, 1=Monday
        const diff = (day === 0 ? -6 : 1) - day;
        d.setDate(d.getDate() + diff);
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function getWeekResetWIB(weekStart) {
        const reset = new Date(weekStart);
        reset.setDate(reset.getDate() + 5); // Saturday
        reset.setHours(15, 0, 0, 0); // 15:00 WIB
        return reset;
    }

    function getWIBNow() {
        const now = new Date();
        const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
        return new Date(utc + (7 * 60 * 60 * 1000));
    }

    function getWIBDayIndex(date) {
        const day = date.getDay(); // 0=Sunday
        return day === 0 ? 7 : day; // 1=Monday
    }

    function parseTimeToToday(timeStr, baseDate) {
        const parts = (timeStr || '00:00:00').split(':');
        const h = parseInt(parts[0], 10) || 0;
        const m = parseInt(parts[1], 10) || 0;
        const s = parseInt(parts[2], 10) || 0;
        const d = new Date(baseDate);
        d.setHours(h, m, s, 0);
        return d;
    }

    function parseDateString(dateStr) {
        if (!dateStr) return null;
        const parts = dateStr.split('-');
        if (parts.length !== 3) return null;
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        const d = parseInt(parts[2], 10);
        if (!y || !m || !d) return null;
        const date = new Date();
        date.setFullYear(y, m - 1, d);
        date.setHours(0, 0, 0, 0);
        return date;
    }

    function formatCountdown(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.max(0, seconds % 60);
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    function setStatus(row, status, label, icon) {
        const statusEl = row.querySelector('.schedule-status');
        if (!statusEl) return;
        statusEl.classList.remove(
            'status-muted',
            'status-countdown',
            'status-active',
            'status-overdue',
            'status-closed',
            'status-success'
        );
        statusEl.classList.add(status);
        statusEl.innerHTML = `<i class=\"fas fa-${icon} me-1\"></i>${label}`;
    }

    function setActionState(row, enabled, variant = 'secondary') {
        const btn = row.querySelector('.schedule-action-btn');
        if (!btn) return;
        btn.disabled = !enabled;
        btn.classList.remove('btn-secondary', 'btn-success', 'btn-warning');
        btn.classList.add(`btn-${variant}`);
    }

    function updateScheduleStatuses() {
        const now = getWIBNow();
        const todayDate = new Date(now);
        todayDate.setHours(0, 0, 0, 0);
        const weekStart = getWeekStartWIB(now);
        const resetTime = getWeekResetWIB(weekStart);
        const isReset = now >= resetTime;
        const baseWeekStart = isReset
            ? new Date(weekStart.getTime() + (7 * 24 * 60 * 60 * 1000))
            : weekStart;

        document.querySelectorAll('.schedule-row').forEach(row => {
            const attended = row.dataset.attended === '1' && !isReset;
            const attendedLate = row.dataset.attendanceLate === '1';
            const dayIndex = parseInt(row.dataset.dayIndex || '0', 10);
            const timeIn = row.dataset.timeIn;
            const timeOut = row.dataset.timeOut;
            const scheduleId = row.dataset.scheduleId;
            const scheduleDateStr = row.dataset.scheduleDate || '';
            const hasScheduleId = !!scheduleId;

            if (attended) {
                if (attendedLate) {
                    setStatus(row, 'status-overdue', 'OVERDUE', 'exclamation-triangle');
                } else {
                    setStatus(row, 'status-success', 'SUCCESS', 'check-circle');
                }
                setActionState(row, false, 'secondary');
                return;
            }

            let scheduleDate = parseDateString(scheduleDateStr);
            if (!scheduleDate) {
                if (!dayIndex) {
                    setStatus(row, 'status-muted', 'MENUNGGU', 'clock');
                    setActionState(row, false, 'secondary');
                    return;
                }
                scheduleDate = new Date(baseWeekStart);
                scheduleDate.setDate(baseWeekStart.getDate() + (dayIndex - 1));
            }
            const scheduleDateOnly = new Date(scheduleDate);
            scheduleDateOnly.setHours(0, 0, 0, 0);
            if (scheduleDateOnly.getTime() > todayDate.getTime()) {
                setStatus(row, 'status-muted', 'MENUNGGU', 'clock');
                setActionState(row, false, 'secondary');
                return;
            }

            const start = parseTimeToToday(timeIn, scheduleDate);
            const end = parseTimeToToday(timeOut, scheduleDate);
            if (end.getTime() <= start.getTime()) {
                end.setDate(end.getDate() + 1);
            }
            const toleranceMs = Math.max(0, toleranceMinutes) * 60 * 1000;
            const baseEnd = new Date(end.getTime() - toleranceMs);
            if (baseEnd.getTime() < start.getTime()) {
                baseEnd.setTime(start.getTime());
            }
            const overdueStart = baseEnd;
            const attendanceEnd = end;
            const countdownStart = new Date(start.getTime() - countdownSeconds * 1000);

            if (now < countdownStart) {
                setStatus(row, 'status-muted', 'MENUNGGU', 'clock');
                setActionState(row, false, 'secondary');
                return;
            }

            if (now >= countdownStart && now < start) {
                const remaining = Math.ceil((start.getTime() - now.getTime()) / 1000);
                setStatus(row, 'status-countdown', `COUNTDOWN ${formatCountdown(remaining)}`, 'hourglass-half');
                setActionState(row, false, 'secondary');
                return;
            }

            if (now >= start && now <= overdueStart) {
                setStatus(row, 'status-active', 'ACTIVE', 'clock');
                setActionState(row, hasScheduleId, 'success');
                return;
            }

            if (now > overdueStart && now <= attendanceEnd) {
                setStatus(row, 'status-overdue', 'OVERDUE', 'exclamation-triangle');
                setActionState(row, hasScheduleId, 'warning');
                return;
            }

            setStatus(row, 'status-closed', 'CLOSED', 'times-circle');
            setActionState(row, false, 'secondary');
        });
    }

    updateScheduleStatuses();
    setInterval(updateScheduleStatuses, 1000);
});
</script>
<!-- END: Jadwal Section -->
