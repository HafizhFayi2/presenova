<?php
// sections/riwayat.php

$student_id = $_SESSION['student_id'];

// Ambil data siswa untuk mendapatkan class_id
$sql_student = "SELECT class_id FROM student WHERE id = ?";
$stmt_student = $db->query($sql_student, [$student_id]);
$student_info = $stmt_student ? $stmt_student->fetch(PDO::FETCH_ASSOC) : null;

if (!$student_info || !$student_info['class_id']) {
    echo '<div class="alert alert-danger">Data siswa tidak ditemukan atau belum memiliki kelas.</div>';
    exit;
}

// Ambil toleransi waktu dari setting admin
$site_stmt = $db->query("SELECT time_tolerance FROM site LIMIT 1");
$site_setting = $site_stmt ? $site_stmt->fetch(PDO::FETCH_ASSOC) : null;
$time_tolerance = isset($site_setting['time_tolerance']) ? (int) $site_setting['time_tolerance'] : 15;
if ($time_tolerance < 0) {
    $time_tolerance = 0;
}

function resolveLateInfo(array $item, int $time_tolerance): array {
    $presentId = (int)($item['present_id'] ?? 0);
    if ($presentId !== 1) {
        return ['is_late' => false, 'late_minutes' => 0, 'late_beyond' => 0];
    }

    $scheduleTime = $item['schedule_time_out'] ?? null;
    $scheduleStart = $item['schedule_time_in'] ?? null;
    $attendanceTime = $item['time_in'] ?? null;
    $baseDate = $item['presence_date'] ?? $item['schedule_date'] ?? date('Y-m-d');

    if ($scheduleTime && $attendanceTime) {
        $tz = new DateTimeZone('Asia/Jakarta');
        [$startDt, $endDt, $baseEndDt] = buildScheduleWindow(
            $baseDate,
            $scheduleStart ?: '00:00:00',
            $scheduleTime,
            $tz,
            $time_tolerance
        );
        $attendanceDt = new DateTime($baseDate . ' ' . $attendanceTime, $tz);
        $lateMinutes = (int) floor(($attendanceDt->getTimestamp() - $baseEndDt->getTimestamp()) / 60);
        if ($lateMinutes <= 0) {
            return ['is_late' => false, 'late_minutes' => 0, 'late_beyond' => 0];
        }
        return ['is_late' => true, 'late_minutes' => $lateMinutes, 'late_beyond' => $lateMinutes];
    }

    $isLate = (($item['is_late'] ?? '') === 'Y');
    $lateMinutes = (int)($item['late_time'] ?? 0);
    $lateBeyond = $isLate ? max(0, $lateMinutes) : 0;
    return ['is_late' => $isLate, 'late_minutes' => $lateMinutes, 'late_beyond' => $lateBeyond];
}

function resolveAttendancePhotoUrl($rawPhoto, $presenceDate = null): string {
    if (!$rawPhoto) {
        return '';
    }

    if (strpos($rawPhoto, 'uploads/attendance') === false && !preg_match('~^https?://~', $rawPhoto)) {
        $cleanPhoto = ltrim($rawPhoto, '/');
        if (strpos($cleanPhoto, '/') === false && !empty($presenceDate)) {
            $dateDir = date('Y-m-d', strtotime((string) $presenceDate));
            return '../uploads/attendance/' . $dateDir . '/' . $cleanPhoto;
        }
        return '../uploads/attendance/' . $cleanPhoto;
    }

    return $rawPhoto;
}

// Gunakan COALESCE untuk memastikan urutan prioritas data

$sql = "SELECT 
    ts.*,
    COALESCE(t.teacher_name, ts.teacher_name) as teacher_name_final,
    COALESCE(t.teacher_code, ts.teacher_code) as teacher_code_final
    ...";
    
$class_id = $student_info['class_id'];

// Query untuk jadwal yang BELUM terabsensi berdasarkan hari ini
// Pendekatan baru: cek langsung dari teacher_schedule berdasarkan hari
$today_day_id = date('N'); // 1=Senin, 7=Minggu
$today_date = date('Y-m-d');
$tz = new DateTimeZone('Asia/Jakarta');
$now_wib = new DateTime('now', $tz);

$sql_pending = "
    SELECT 
        ts.schedule_id,
        ts.subject,
        ts.teacher_id,
        ts.day_id,
        t.teacher_name,
        c.class_name,
        d.day_name,
        sh.shift_name,
        sh.time_in,
        sh.time_out,
        '$today_date' as schedule_date
    FROM teacher_schedule ts
    JOIN teacher t ON ts.teacher_id = t.id
    JOIN class c ON ts.class_id = c.class_id
    JOIN day d ON ts.day_id = d.day_id
    JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE ts.class_id = ?
    AND ts.day_id = ?
    AND d.is_active = 'Y'
    AND NOT EXISTS (
        SELECT 1 FROM presence p
        JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
        WHERE ss.teacher_schedule_id = ts.schedule_id
        AND p.student_id = ?
        AND DATE(p.presence_date) = CURDATE()
    )
    ORDER BY sh.time_in ASC
";

$stmt = $db->query($sql_pending, [$class_id, $today_day_id, $student_id]);
$pending_schedules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Tambahkan student_schedule_id untuk setiap jadwal
foreach ($pending_schedules as &$schedule) {
    $computed = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', $schedule['day_id'] ?? 0);
    if ($computed) {
        $schedule['time_in'] = $computed[0];
        $schedule['time_out'] = $computed[1];
    }
    $schedule['waktu'] = (isset($schedule['time_in'], $schedule['time_out']))
        ? (date('H:i', strtotime($schedule['time_in'])) . ' - ' . date('H:i', strtotime($schedule['time_out'])))
        : '-';

    // Cek atau buat student_schedule
    $sql_check_ss = "SELECT student_schedule_id FROM student_schedule 
                     WHERE student_id = ? 
                     AND teacher_schedule_id = ? 
                     AND schedule_date = CURDATE()";
    $stmt_check = $db->query($sql_check_ss, [$student_id, $schedule['schedule_id']]);
    $existing_ss = $stmt_check ? $stmt_check->fetch(PDO::FETCH_ASSOC) : null;
    
    if ($existing_ss) {
        $schedule['student_schedule_id'] = $existing_ss['student_schedule_id'];
    } else {
        // Buat student_schedule baru
        $sql_create_ss = "INSERT INTO student_schedule 
                         (student_id, teacher_schedule_id, schedule_date, time_in, time_out, status)
                         VALUES (?, ?, CURDATE(), ?, ?, 'ACTIVE')";
        $stmt_create = $db->query($sql_create_ss, [
            $student_id, 
            $schedule['schedule_id'], 
            $schedule['time_in'], 
            $schedule['time_out']
        ]);
        if ($stmt_create) {
            $schedule['student_schedule_id'] = $db->lastInsertId();
        }
    }
}
unset($schedule);

// Filter out CLOSED schedules from pending list (move to alpa instead)
$pending_filtered = [];
foreach ($pending_schedules as $schedule) {
    [$start_dt, $end_dt] = buildScheduleWindow(
        $today_date,
        $schedule['time_in'] ?? '00:00:00',
        $schedule['time_out'] ?? '00:00:00',
        $tz,
        (int) $time_tolerance
    );
    if ($now_wib <= $end_dt) {
        $pending_filtered[] = $schedule;
    }
}
$pending_schedules = $pending_filtered;

// Group by date (untuk konsistensi UI)
$pending_by_date = [];
if (count($pending_schedules) > 0) {
    $pending_by_date[$today_date] = $pending_schedules;
}

// Jadwal tidak terabsensi (alpa) dari seluruh riwayat jadwal
$sql_missed = "
    SELECT 
        ss.student_schedule_id,
        ss.schedule_date,
        ss.time_in,
        ss.time_out,
        ts.subject,
        t.teacher_name,
        d.day_name,
        sh.shift_name
    FROM student_schedule ss
    JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
    JOIN teacher t ON ts.teacher_id = t.id
    JOIN day d ON ts.day_id = d.day_id
    JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE ss.student_id = ?
    AND ss.schedule_date <= CURDATE()
    AND d.is_active = 'Y'
    AND NOT EXISTS (
        SELECT 1 FROM presence p WHERE p.student_schedule_id = ss.student_schedule_id
    )
    ORDER BY ss.schedule_date DESC, ss.time_in ASC
";
$stmt_missed = $db->query($sql_missed, [$student_id]);
$missed_rows = $stmt_missed ? $stmt_missed->fetchAll(PDO::FETCH_ASSOC) : [];
$missed_schedules = [];
foreach ($missed_rows as $row) {
    [$start_dt, $end_dt] = buildScheduleWindow(
        $row['schedule_date'],
        $row['time_in'] ?? '00:00:00',
        $row['time_out'] ?? '00:00:00',
        $tz,
        (int) $time_tolerance
    );
    if ($now_wib > $end_dt) {
        $row['waktu'] = (isset($row['time_in'], $row['time_out']))
            ? (date('H:i', strtotime($row['time_in'])) . ' - ' . date('H:i', strtotime($row['time_out'])))
            : '-';
        $missed_schedules[] = $row;
    }
}

// Query riwayat absensi yang sudah dilakukan (seluruh data)
$sql_riwayat = "
    SELECT 
        p.*,
        ps.present_name,
        ss.schedule_date,
        ts.subject,
        t.teacher_name,
        c.class_name,
        sh.shift_name,
        COALESCE(ss.time_in, sh.time_in) as schedule_time_in,
        COALESCE(ss.time_out, sh.time_out) as schedule_time_out
    FROM presence p
    LEFT JOIN present_status ps ON p.present_id = ps.present_id
    LEFT JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
    LEFT JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
    LEFT JOIN teacher t ON ts.teacher_id = t.id
    LEFT JOIN class c ON ts.class_id = c.class_id
    LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE p.student_id = ?
    ORDER BY p.presence_date DESC, p.time_in DESC
";

$stmt = $db->query($sql_riwayat, [$student_id]);
$riwayat = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Group completed attendance by date
$riwayat_by_date = [];
foreach ($riwayat as $item) {
    $date = $item['schedule_date'] ?? $item['presence_date'];
    if (!isset($riwayat_by_date[$date])) {
        $riwayat_by_date[$date] = [];
    }
    $riwayat_by_date[$date][] = $item;
}

$riwayat_sakit = array_values(array_filter($riwayat, fn($i) => (int)($i['present_id'] ?? 0) === 2));
$riwayat_izin = array_values(array_filter($riwayat, fn($i) => (int)($i['present_id'] ?? 0) === 3));
?>

<div class="card riwayat-card">
    <div class="card-header riwayat-header">
        <div class="header-left">
            <div class="header-icon">
                <i class="fas fa-history"></i>
            </div>
            <div>
                <h4>Riwayat Absensi</h4>
                <p class="header-subtitle">Pantau kehadiran dan jadwal harian Anda</p>
            </div>
        </div>
        <div class="header-chip">
            <i class="fas fa-calendar-alt"></i>
            <span><?php echo date('d F Y'); ?></span>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter dan Pencarian -->
        <div class="row mb-4 riwayat-filter-wrap">
            <div class="col-md-12">
                <div class="card filter-card">
                    <div class="card-body">
                        <div class="row g-3 filter-grid">
                            <div class="col-md-3">
                                <label class="form-label">Filter Tanggal</label>
                                <input type="date" id="filterDate" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select id="filterStatus" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="1">Hadir</option>
                                    <option value="2">Sakit</option>
                                    <option value="3">Izin</option>
                                    <option value="alpa">Alpa</option>
                                    <option value="pending">Belum Absen</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pencarian</label>
                                <input type="text" id="searchInput" class="form-control" 
                                       placeholder="Cari mapel, guru, atau keterangan...">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" id="resetFilter" class="btn btn-secondary w-100 btn-reset">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jadwal Belum Terlaksana (Hari Ini) -->
        <div class="mb-4 section-shell" id="pendingSection">
            <h5 class="section-title">
                <span class="title-icon warning"><i class="fas fa-clock"></i></span>
                Jadwal Belum Terlaksana (Hari Ini)
            </h5>
            <?php if (count($pending_by_date) > 0): ?>
                <div class="accordion" id="accordionPending">
                    <?php foreach ($pending_by_date as $date => $schedules): 
                        $date_obj = new DateTime($date);
                        $accordion_id = 'pending_' . str_replace('-', '_', $date);
                    ?>
                        <div class="accordion-item pending-item" data-date="<?= $date ?>" data-status="pending">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#<?= $accordion_id ?>">
                                    <div class="d-flex justify-content-between w-100 me-3">
                                        <div>
                                            <strong><?= $date_obj->format('d F Y') ?></strong>
                                            <span class="badge bg-primary ms-2">Hari Ini</span>
                                        </div>
                                        <div>
                                            <span class="badge bg-warning"><?= count($schedules) ?> Belum Absen</span>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="<?= $accordion_id ?>" class="accordion-collapse collapse show">
                                <div class="accordion-body p-0">
                                    <div class="liquidGlass-wrapper liquid-theme-sky soft-hover-gap liquid-table">
                                        <div class="liquidGlass-effect"></div>
                                        <div class="liquidGlass-tint"></div>
                                        <div class="liquidGlass-shine"></div>
                                        <div class="liquidGlass-content">
                                            <div class="table-responsive riwayat-table-responsive">
                                                <table class="table table-hover mb-0 riwayat-table pending-table">
                                            <thead class="table-warning">
                                                <tr>
                                                    <th width="80">Shift</th>
                                                    <th>Mata Pelajaran</th>
                                                    <th>Guru</th>
                                                    <th width="120">Waktu</th>
                                                    <th width="100">Status</th>
                                                    <th width="120">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($schedules as $schedule): 
                                                    $today_date = date('Y-m-d');
                                                    $tz = new DateTimeZone('Asia/Jakarta');
                                                    $now_dt = new DateTime('now', $tz);
                                                    [$start_dt, $end_dt, $base_end_dt] = buildScheduleWindow(
                                                        $today_date,
                                                        $schedule['time_in'] ?? '00:00:00',
                                                        $schedule['time_out'] ?? '00:00:00',
                                                        $tz,
                                                        (int) $time_tolerance
                                                    );
                                                    $now_ts = $now_dt->getTimestamp();
                                                    $start_ts = $start_dt->getTimestamp();
                                                    $end_ts = $end_dt->getTimestamp();
                                                    $base_end_ts = $base_end_dt->getTimestamp();
                                                    $countdown_start_ts = $start_ts - 120;
                                                    $overdue_end_ts = $end_ts;

                                                    $status_label = 'MENUNGGU';
                                                    $status_class = 'bg-secondary';
                                                    $can_attend = false;

                                                    if ($now_ts < $countdown_start_ts) {
                                                        $status_label = 'MENUNGGU';
                                                        $status_class = 'bg-secondary';
                                                    } elseif ($now_ts >= $countdown_start_ts && $now_ts < $start_ts) {
                                                        $remaining = max(0, $start_ts - $now_ts);
                                                        $mins = floor($remaining / 60);
                                                        $secs = $remaining % 60;
                                                        $status_label = sprintf('COUNTDOWN %02d:%02d', $mins, $secs);
                                                        $status_class = 'bg-info';
                                                    } elseif ($now_ts >= $start_ts && $now_ts <= $base_end_ts) {
                                                        $status_label = 'ACTIVE';
                                                        $status_class = 'bg-success';
                                                        $can_attend = true;
                                                    } elseif ($now_ts > $base_end_ts && $now_ts <= $overdue_end_ts) {
                                                        $status_label = 'OVERDUE';
                                                        $status_class = 'bg-warning';
                                                        $can_attend = true;
                                                    } else {
                                                        $status_label = 'CLOSED';
                                                        $status_class = 'bg-danger';
                                                    }
                                                ?>
                                                    <?php
                                                        $has_schedule_id = !empty($schedule['student_schedule_id']);
                                                        $attendance_href = '?page=face_recognition';
                                                        if ($has_schedule_id) {
                                                            $attendance_href .= '&schedule_id=' . urlencode((string) $schedule['student_schedule_id']);
                                                        }
                                                        $action_variant = 'secondary';
                                                        if ($can_attend) {
                                                            $action_variant = ($now_ts > $base_end_ts) ? 'warning' : 'success';
                                                        }
                                                    ?>
                                                    <tr class="schedule-row live-pending-row"
                                                        data-subject="<?= htmlspecialchars(strtolower((string)($schedule['subject'] ?? '')), ENT_QUOTES) ?>"
                                                        data-teacher="<?= htmlspecialchars(strtolower((string)($schedule['teacher_name'] ?? '')), ENT_QUOTES) ?>"
                                                        data-date="<?= $today_date ?>"
                                                        data-status="pending"
                                                        data-countdown-start="<?= (int) $countdown_start_ts ?>"
                                                        data-start="<?= (int) $start_ts ?>"
                                                        data-base-end="<?= (int) $base_end_ts ?>"
                                                        data-end="<?= (int) $overdue_end_ts ?>"
                                                        data-has-schedule-id="<?= $has_schedule_id ? '1' : '0' ?>">
                                                        <td><span class="badge bg-info"><?= $schedule['shift_name'] ?></span></td>
                                                        <td><strong><?= htmlspecialchars($schedule['subject']) ?></strong></td>
                                                        <td><?= htmlspecialchars($schedule['teacher_name']) ?></td>
                                                        <td><?= $schedule['waktu'] ?></td>
                                                        <td>
                                                            <span class="badge js-pending-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                                                        </td>
                                                        <td>
                                                                <a class="btn btn-sm btn-<?php echo $action_variant; ?> btn-absensi-now js-pending-action-link <?php echo ($can_attend && $has_schedule_id) ? '' : 'd-none'; ?>"
                                                                   href="<?= htmlspecialchars($attendance_href, ENT_QUOTES) ?>"
                                                                   data-schedule-id="<?= $has_schedule_id ? htmlspecialchars((string) $schedule['student_schedule_id'], ENT_QUOTES) : '' ?>"
                                                                   data-subject="<?= htmlspecialchars($schedule['subject']) ?>"
                                                                   data-time="<?= $schedule['time_in'] ?>">
                                                                    <i class="fas fa-camera"></i> Absen
                                                                </a>
                                                                <button class="btn btn-sm btn-secondary js-pending-action-btn <?php echo ($can_attend && $has_schedule_id) ? 'd-none' : ''; ?>" disabled>
                                                                    <i class="fas fa-camera"></i> Absen
                                                                </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-success modern-alert">
                    <i class="fas fa-check-circle"></i> Semua jadwal hari ini sudah terabsensi!
                </div>
            <?php endif; ?>
        </div>

        <!-- Jadwal Tidak Terabsensi (Alpa) -->
        <div class="mb-4 section-shell" id="missedSection">
            <h5 class="section-title">
                <span class="title-icon danger"><i class="fas fa-user-times"></i></span>
                Jadwal Tidak Terabsensi (Alpa)
            </h5>
            <?php if (count($missed_schedules) > 0): ?>
                <div class="table-responsive riwayat-table-responsive">
                    <table class="table table-hover mb-0 missed-table riwayat-table">
                        <thead class="table-danger">
                            <tr>
                                <th width="120">Tanggal</th>
                                <th width="90">Hari</th>
                                <th width="80">Shift</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th width="120">Waktu</th>
                                <th width="100">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missed_schedules as $missed): ?>
                                <tr class="missed-row"
                                    data-date="<?= htmlspecialchars(substr((string)$missed['schedule_date'], 0, 10)) ?>"
                                    data-status="alpa"
                                    data-subject="<?= htmlspecialchars(strtolower((string)($missed['subject'] ?? '')), ENT_QUOTES) ?>"
                                    data-teacher="<?= htmlspecialchars(strtolower((string)($missed['teacher_name'] ?? '')), ENT_QUOTES) ?>">
                                    <td><?= htmlspecialchars($missed['schedule_date']) ?></td>
                                    <td><?= htmlspecialchars($missed['day_name'] ?? '-') ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($missed['shift_name'] ?? '-') ?></span></td>
                                    <td><strong><?= htmlspecialchars($missed['subject'] ?? '-') ?></strong></td>
                                    <td><?= htmlspecialchars($missed['teacher_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($missed['waktu'] ?? '-') ?></td>
                                    <td><span class="badge bg-danger">CLOSED</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success modern-alert">
                    <i class="fas fa-check-circle"></i> Tidak ada jadwal yang terlewat pada riwayat saat ini.
                </div>
            <?php endif; ?>
        </div>

        <!-- Jadwal Sudah Terlaksana (Semua Data) -->
        <div id="riwayatSection" class="section-shell">
            <h5 class="section-title">
                <span class="title-icon success"><i class="fas fa-check-circle"></i></span>
                Jadwal Sudah Terlaksana
            </h5>
            <?php if (count($riwayat_by_date) > 0): ?>
                <div class="accordion" id="accordionRiwayat">
                    <?php foreach ($riwayat_by_date as $date => $items): 
                        $date_obj = new DateTime($date);
                        $today = new DateTime();
                        $is_today = $date_obj->format('Y-m-d') == $today->format('Y-m-d');
                        $day_name = '';
                        if ($is_today) {
                            $day_name = 'Hari Ini';
                        } elseif ($date_obj->format('Y-m-d') == $today->modify('-1 day')->format('Y-m-d')) {
                            $day_name = 'Kemarin';
                            $today->modify('+1 day');
                        } else {
                            $today->modify('+1 day');
                            $days_id = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                            $day_name = $days_id[$date_obj->format('N')];
                        }
                        $accordion_id = 'riwayat_' . str_replace('-', '_', $date);
                        
                        // Count status
                        $count_hadir = count(array_filter($items, fn($i) => $i['present_id'] == 1));
                        $count_sakit = count(array_filter($items, fn($i) => $i['present_id'] == 2));
                        $count_izin = count(array_filter($items, fn($i) => $i['present_id'] == 3));
                        $count_terlambat = count(array_filter($items, function($i) use ($time_tolerance) {
                            $lateInfo = resolveLateInfo($i, $time_tolerance);
                            return $lateInfo['is_late'];
                        }));
                    ?>
                        <div class="accordion-item riwayat-item" data-date="<?= $date ?>">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $is_today ? '' : 'collapsed' ?>" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#<?= $accordion_id ?>">
                                    <div class="d-flex justify-content-between w-100 me-3">
                                        <div>
                                            <strong><?= $date_obj->format('d F Y') ?></strong>
                                            <span class="badge bg-<?= $is_today ? 'primary' : 'secondary' ?> ms-2"><?= $day_name ?></span>
                                        </div>
                                        <div>
                                            <?php if ($count_hadir > 0): ?>
                                                <span class="badge bg-success"><?= $count_hadir ?> Hadir</span>
                                            <?php endif; ?>
                                            <?php if ($count_sakit > 0): ?>
                                                <span class="badge bg-warning"><?= $count_sakit ?> Sakit</span>
                                            <?php endif; ?>
                                            <?php if ($count_izin > 0): ?>
                                                <span class="badge bg-info"><?= $count_izin ?> Izin</span>
                                            <?php endif; ?>
                                            <?php if ($count_terlambat > 0): ?>
                                                <span class="badge bg-danger"><?= $count_terlambat ?> Terlambat</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="<?= $accordion_id ?>" class="accordion-collapse collapse <?= $is_today ? 'show' : '' ?>">
                                <div class="accordion-body p-0">
                                    <div class="liquidGlass-wrapper liquid-theme-mint liquid-table">
                                        <div class="liquidGlass-effect"></div>
                                        <div class="liquidGlass-tint"></div>
                                        <div class="liquidGlass-shine"></div>
                                        <div class="liquidGlass-content">
                                            <div class="table-responsive riwayat-table-responsive">
                                                <table class="table table-hover mb-0 riwayat-table completed-table">
                                            <thead class="table-success">
                                                <tr>
                                                    <th width="80">Shift</th>
                                                    <th>Mata Pelajaran</th>
                                                    <th>Guru</th>
                                                    <th width="100">Jam Absen</th>
                                                    <th width="100">Status</th>
                                                    <th width="100">Foto</th>
                                                    <th width="120">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $item): 
                                                    $status_class = '';
                                                    $status_text = $item['present_name'];
                                                    $late_info = resolveLateInfo($item, $time_tolerance);
                                                    
                                                    if ($item['present_id'] == 1) {
                                                        if ($late_info['is_late']) {
                                                            $status_class = 'bg-warning';
                                                            $late_minutes = $late_info['late_beyond'] ?: $late_info['late_minutes'];
                                                            $status_text = 'Terlambat (' . $late_minutes . ' menit)';
                                                        } else {
                                                            $status_class = 'bg-success';
                                                        }
                                                    } elseif ($item['present_id'] == 2) {
                                                        $status_class = 'bg-warning';
                                                    } elseif ($item['present_id'] == 3) {
                                                        $status_class = 'bg-info';
                                                    } else {
                                                        $status_class = 'bg-danger';
                                                        $status_text = 'Tidak Hadir';
                                                    }
                                                ?>
                                                    <tr class="attendance-row" 
                                                        data-subject="<?= htmlspecialchars(strtolower((string)($item['subject'] ?? '')), ENT_QUOTES) ?>"
                                                        data-teacher="<?= htmlspecialchars(strtolower((string)($item['teacher_name'] ?? '')), ENT_QUOTES) ?>"
                                                        data-date="<?= htmlspecialchars(substr((string)($item['schedule_date'] ?? $item['presence_date']), 0, 10)) ?>"
                                                        data-status="<?= $item['present_id'] ?>"
                                                        data-info="<?= htmlspecialchars(strtolower((string)($item['information'] ?? '')), ENT_QUOTES) ?>">
                                                        <td><span class="badge bg-secondary"><?= $item['shift_name'] ?></span></td>
                                                        <td><strong><?= htmlspecialchars($item['subject'] ?? '-') ?></strong></td>
                                                        <td><?= htmlspecialchars($item['teacher_name'] ?? '-') ?></td>
                                                        <td><?= $item['time_in'] ? date('H:i', strtotime($item['time_in'])) : '-' ?></td>
                                                        <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                                                        <td>
                                                            <?php $photoUrl = resolveAttendancePhotoUrl($item['picture_in'] ?? '', $item['presence_date'] ?? null); ?>
                                                            <?php if ($photoUrl): ?>
                                                                <button class="btn btn-sm btn-primary btn-view-photo" 
                                                                        data-photo="<?= htmlspecialchars($photoUrl, ENT_QUOTES) ?>"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#photoModal">
                                                                    <i class="fas fa-image"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-info btn-view-detail" 
                                                                    data-id="<?= $item['presence_id'] ?>"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#detailModal">
                                                                <i class="fas fa-eye"></i> Detail
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info modern-alert">
                    <i class="fas fa-info-circle"></i> Belum ada riwayat absensi tersimpan.
                </div>
            <?php endif; ?>
        </div>

        <!-- Rekap Jadwal Sakit -->
        <div class="mb-4 section-shell" id="sakitSection">
            <h5 class="section-title">
                <span class="title-icon warning"><i class="fas fa-notes-medical"></i></span>
                Rekap Jadwal Sakit
            </h5>
            <?php if (count($riwayat_sakit) > 0): ?>
                <div class="table-responsive riwayat-table-responsive">
                    <table class="table table-hover mb-0 riwayat-table sakit-table">
                        <thead class="table-warning">
                            <tr>
                                <th width="120">Tanggal</th>
                                <th width="80">Shift</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th width="100">Jam Absen</th>
                                <th width="120">Status</th>
                                <th width="100">Foto</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat_sakit as $item): ?>
                                <tr class="sakit-row"
                                    data-date="<?= htmlspecialchars(substr((string)($item['schedule_date'] ?? $item['presence_date']), 0, 10)) ?>"
                                    data-status="2"
                                    data-subject="<?= htmlspecialchars(strtolower((string)($item['subject'] ?? '')), ENT_QUOTES) ?>"
                                    data-teacher="<?= htmlspecialchars(strtolower((string)($item['teacher_name'] ?? '')), ENT_QUOTES) ?>">
                                    <td><?= htmlspecialchars($item['schedule_date'] ?? $item['presence_date']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($item['shift_name'] ?? '-') ?></span></td>
                                    <td><strong><?= htmlspecialchars($item['subject'] ?? '-') ?></strong></td>
                                    <td><?= htmlspecialchars($item['teacher_name'] ?? '-') ?></td>
                                    <td><?= $item['time_in'] ? date('H:i', strtotime($item['time_in'])) : '-' ?></td>
                                    <td><span class="badge bg-warning">Sakit</span></td>
                                    <td>
                                        <?php $photoUrl = resolveAttendancePhotoUrl($item['picture_in'] ?? '', $item['presence_date'] ?? null); ?>
                                        <?php if ($photoUrl): ?>
                                            <button class="btn btn-sm btn-primary btn-view-photo" 
                                                    data-photo="<?= htmlspecialchars($photoUrl, ENT_QUOTES) ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#photoModal">
                                                <i class="fas fa-image"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info btn-view-detail" 
                                                data-id="<?= $item['presence_id'] ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info modern-alert">
                    <i class="fas fa-info-circle"></i> Tidak ada rekap sakit tersimpan.
                </div>
            <?php endif; ?>
        </div>

        <!-- Rekap Jadwal Izin -->
        <div class="mb-4 section-shell" id="izinSection">
            <h5 class="section-title">
                <span class="title-icon info"><i class="fas fa-user-check"></i></span>
                Rekap Jadwal Izin
            </h5>
            <?php if (count($riwayat_izin) > 0): ?>
                <div class="table-responsive riwayat-table-responsive">
                    <table class="table table-hover mb-0 riwayat-table izin-table">
                        <thead class="table-info">
                            <tr>
                                <th width="120">Tanggal</th>
                                <th width="80">Shift</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th width="100">Jam Absen</th>
                                <th width="120">Status</th>
                                <th width="100">Foto</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat_izin as $item): ?>
                                <tr class="izin-row"
                                    data-date="<?= htmlspecialchars(substr((string)($item['schedule_date'] ?? $item['presence_date']), 0, 10)) ?>"
                                    data-status="3"
                                    data-subject="<?= htmlspecialchars(strtolower((string)($item['subject'] ?? '')), ENT_QUOTES) ?>"
                                    data-teacher="<?= htmlspecialchars(strtolower((string)($item['teacher_name'] ?? '')), ENT_QUOTES) ?>">
                                    <td><?= htmlspecialchars($item['schedule_date'] ?? $item['presence_date']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($item['shift_name'] ?? '-') ?></span></td>
                                    <td><strong><?= htmlspecialchars($item['subject'] ?? '-') ?></strong></td>
                                    <td><?= htmlspecialchars($item['teacher_name'] ?? '-') ?></td>
                                    <td><?= $item['time_in'] ? date('H:i', strtotime($item['time_in'])) : '-' ?></td>
                                    <td><span class="badge bg-info">Izin</span></td>
                                    <td>
                                        <?php $photoUrl = resolveAttendancePhotoUrl($item['picture_in'] ?? '', $item['presence_date'] ?? null); ?>
                                        <?php if ($photoUrl): ?>
                                            <button class="btn btn-sm btn-primary btn-view-photo" 
                                                    data-photo="<?= htmlspecialchars($photoUrl, ENT_QUOTES) ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#photoModal">
                                                <i class="fas fa-image"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info btn-view-detail" 
                                                data-id="<?= $item['presence_id'] ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info modern-alert">
                    <i class="fas fa-info-circle"></i> Tidak ada rekap izin tersimpan.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Absensi -->
<div class="modal fade" id="absensiModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-camera"></i> Absensi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="absensiForm">
                    <input type="hidden" name="student_schedule_id" id="inputScheduleId">
                    <input type="hidden" name="captured_image" id="inputCapturedImage">
                    <input type="hidden" name="latitude" id="inputLatitude">
                    <input type="hidden" name="longitude" id="inputLongitude">
                    <input type="hidden" name="distance" id="inputDistance">
                    
                    <div id="modalSubject" class="alert alert-info mb-3"></div>
                    
                    <!-- Step 1: Lokasi -->
                    <div id="step1" class="step">
                        <h6><i class="fas fa-map-marker-alt"></i> Step 1: Verifikasi Lokasi</h6>
                        <div id="locationStatus" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Mendapatkan lokasi Anda...</p>
                        </div>
                        <div id="locationInfo" style="display: none;">
                            <p>Jarak dari sekolah: <strong><span id="distance">-</span> meter</strong></p>
                            <div id="locationStatusText"></div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Kamera -->
                    <div id="step2" class="step" style="display: none;">
                        <h6><i class="fas fa-camera"></i> Step 2: Ambil Foto</h6>
                        <div id="cameraContainer" class="text-center">
                            <video id="video" autoplay playsinline></video>
                            <canvas id="canvas" style="display: none;"></canvas>
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" id="captureBtn" class="btn btn-primary">
                                <i class="fas fa-camera"></i> Ambil Foto
                            </button>
                        </div>
                        <div id="capturedPhoto" style="display: none;" class="mt-3 text-center">
                            <p>Foto yang diambil:</p>
                            <img id="capturedImg" class="img-fluid" style="max-width: 300px; border-radius: 8px;">
                        </div>
                    </div>
                    
                    <!-- Step 3: Konfirmasi -->
                    <div id="step3" class="step" style="display: none;">
                        <h6><i class="fas fa-check-circle"></i> Step 3: Konfirmasi & Keterangan</h6>
                        <div class="mb-3">
                            <label class="form-label">Status Kehadiran</label>
                            <select name="present_id" class="form-select" required>
                                <option value="1">Hadir</option>
                                <option value="2">Sakit</option>
                                <option value="3">Izin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan (Opsional)</label>
                            <textarea name="information" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check"></i> Simpan Absensi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Photo Viewer -->
<div class="modal fade riwayat-modal" id="photoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foto Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoView" class="img-fluid" style="max-width: 100%; border-radius: 8px;">
                <div id="photoViewFallback" class="text-muted small mt-3 d-none">
                    Foto tidak tersedia atau gagal dimuat.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail -->
<div class="modal fade riwayat-modal" id="detailModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <div class="text-center">
                    <div class="spinner-border" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function initRiwayatSection() {
    const waitForJquery = () => {
        if (!window.jQuery) {
            setTimeout(waitForJquery, 50);
            return;
        }
        const $ = window.jQuery;

        $(function() {
            let stream = null;
            let capturedImage = '';
            let currentLocation = null;

            ['photoModal', 'detailModal', 'absensiModal'].forEach((modalId) => {
                const modalEl = document.getElementById(modalId);
                if (modalEl && modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }
            });

            const updateRiwayatModalState = () => {
                const openCount = document.querySelectorAll('.riwayat-modal.show').length;
                document.body.classList.toggle('riwayat-modal-open', openCount > 0);
            };

            $('#photoModal, #detailModal').on('shown.bs.modal hidden.bs.modal', updateRiwayatModalState);

            $(document).on('click', '.riwayat-modal', function(event) {
                if (event.target === this) {
                    $(this).modal('hide');
                }
            });
    
    // Absensi Button Click -> arahkan ke validasi wajah
    $('.btn-absensi-now').click(function(e) {
        e.preventDefault();
        const scheduleId = $(this).data('schedule-id');
        if (scheduleId) {
            window.location.href = `?page=face_recognition&schedule_id=${scheduleId}`;
        } else {
            window.location.href = '?page=face_recognition';
        }
    });

    const pendingRows = Array.from(document.querySelectorAll('#pendingSection .live-pending-row'));
    if (pendingRows.length > 0) {
        const serverNowEpoch = <?php echo (int) $now_wib->getTimestamp(); ?>;
        const clientStartMs = Date.now();
        const statusClasses = ['bg-secondary', 'bg-info', 'bg-success', 'bg-warning', 'bg-danger'];
        const actionClasses = ['btn-secondary', 'btn-success', 'btn-warning'];
        const pad = (num) => String(num).padStart(2, '0');
        const getNowEpoch = () => serverNowEpoch + Math.floor((Date.now() - clientStartMs) / 1000);
        const formatCountdown = (seconds) => {
            const safe = Math.max(0, seconds);
            const mins = Math.floor(safe / 60);
            const secs = safe % 60;
            return `${pad(mins)}:${pad(secs)}`;
        };

        const evaluatePendingStatus = (nowEpoch, countdownStart, start, baseEnd, end) => {
            if (nowEpoch < countdownStart) {
                return { label: 'MENUNGGU', statusClass: 'bg-secondary', canAttend: false, actionClass: 'btn-secondary' };
            }
            if (nowEpoch >= countdownStart && nowEpoch < start) {
                const remaining = Math.max(0, start - nowEpoch);
                return {
                    label: `COUNTDOWN ${formatCountdown(remaining)}`,
                    statusClass: 'bg-info',
                    canAttend: false,
                    actionClass: 'btn-secondary'
                };
            }
            if (nowEpoch >= start && nowEpoch <= baseEnd) {
                return { label: 'ACTIVE', statusClass: 'bg-success', canAttend: true, actionClass: 'btn-success' };
            }
            if (nowEpoch > baseEnd && nowEpoch <= end) {
                return { label: 'OVERDUE', statusClass: 'bg-warning', canAttend: true, actionClass: 'btn-warning' };
            }
            return { label: 'CLOSED', statusClass: 'bg-danger', canAttend: false, actionClass: 'btn-secondary' };
        };

        const applyPendingStatus = (row, state) => {
            const statusEl = row.querySelector('.js-pending-status');
            if (statusEl) {
                statusEl.classList.remove(...statusClasses);
                statusEl.classList.add(state.statusClass);
                statusEl.textContent = state.label;
            }

            const hasScheduleId = row.dataset.hasScheduleId === '1';
            const actionLink = row.querySelector('.js-pending-action-link');
            const actionButton = row.querySelector('.js-pending-action-btn');
            const showActionLink = state.canAttend && hasScheduleId;

            if (actionLink) {
                actionLink.classList.remove(...actionClasses);
                actionLink.classList.add(state.actionClass);
                actionLink.classList.toggle('d-none', !showActionLink);
            }

            if (actionButton) {
                actionButton.classList.toggle('d-none', showActionLink);
            }
        };

        const updatePendingStatuses = () => {
            const nowEpoch = getNowEpoch();
            pendingRows.forEach((row) => {
                const countdownStart = parseInt(row.dataset.countdownStart || '0', 10);
                const start = parseInt(row.dataset.start || '0', 10);
                const baseEnd = parseInt(row.dataset.baseEnd || '0', 10);
                const end = parseInt(row.dataset.end || '0', 10);
                if (!countdownStart || !start || !baseEnd || !end) {
                    return;
                }

                const state = evaluatePendingStatus(nowEpoch, countdownStart, start, baseEnd, end);
                applyPendingStatus(row, state);
            });
        };

        updatePendingStatuses();
        setInterval(updatePendingStatuses, 1000);
    }
    
    function getLocation() {
        $('#locationStatus').show();
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    currentLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    };
                    
                    $('#inputLatitude').val(currentLocation.latitude);
                    $('#inputLongitude').val(currentLocation.longitude);
                    
                    // Validate location
                    $.ajax({
                        url: '../api/check_location.php',
                        method: 'POST',
                        data: currentLocation,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                const distance = Math.round(response.data.distance);
                                const withinRadius = response.data.within_radius;
                                
                                $('#distance').text(distance);
                                $('#inputDistance').val(distance);
                                $('#locationStatusText').html(
                                    withinRadius ? 
                                    '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Dalam Radius Sekolah</div>' :
                                    '<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Di Luar Radius Sekolah (' + distance + 'm > ' + response.data.radius_limit + 'm)</div>'
                                );
                                
                                $('#locationStatus').hide();
                                $('#locationInfo').show();
                                
                                if (withinRadius) {
                                    setTimeout(() => {
                                        $('#step1').hide();
                                        $('#step2').show();
                                        startCamera();
                                    }, 1500);
                                }
                            } else {
                                $('#locationStatus').html(
                                    '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>'
                                );
                            }
                        },
                        error: function() {
                            $('#locationStatus').html(
                                '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memvalidasi lokasi</div>'
                            );
                        }
                    });
                },
                function(error) {
                    let errorMsg = 'Gagal mendapatkan lokasi. ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg += 'Izinkan akses lokasi pada browser Anda.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg += 'Lokasi tidak tersedia.';
                            break;
                        case error.TIMEOUT:
                            errorMsg += 'Waktu habis.';
                            break;
                        default:
                            errorMsg += 'Pastikan GPS aktif.';
                    }
                    $('#locationStatus').html(
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + errorMsg + '</div>'
                    );
                },
                { 
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0 
                }
            );
        } else {
            $('#locationStatus').html(
                '<div class="alert alert-danger">Browser tidak mendukung geolocation.</div>'
            );
        }
    }
    
    function startCamera() {
        navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'user',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            } 
        })
        .then(function(s) {
            stream = s;
            const video = document.getElementById('video');
            video.srcObject = stream;
            video.play();
        })
        .catch(function(err) {
            $('#cameraContainer').html(
                '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Tidak dapat mengakses kamera: ' + err.message + '</div>'
            );
        });
    }
    
    $('#captureBtn').click(function() {
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const context = canvas.getContext('2d');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        capturedImage = canvas.toDataURL('image/jpeg', 0.8);
        $('#inputCapturedImage').val(capturedImage);
        
        $('#capturedImg').attr('src', capturedImage);
        $('#capturedPhoto').show();
        
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        
        setTimeout(() => {
            $('#step2').hide();
            $('#step3').show();
        }, 1000);
    });
    
    $('#absensiForm').submit(function(e) {
        e.preventDefault();
        
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
        
        $.ajax({
            url: '../api/save_attendance.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ Absensi berhasil disimpan!');
                    $('#absensiModal').modal('hide');
                    location.reload();
                } else {
                    alert('ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Gagal: ' + response.message);
                    submitBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Simpan Absensi');
                }
            },
            error: function(xhr) {
                alert('ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Terjadi kesalahan jaringan');
                submitBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Simpan Absensi');
            }
        });
    });
    
    $('#absensiModal').on('hidden.bs.modal', function() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        $('#absensiForm')[0].reset();
    });
    
    // View Photo
    $(document).on('click', '.btn-view-photo', function() {
        const photoUrl = $(this).data('photo');
        const photoView = $('#photoView');
        const fallback = $('#photoViewFallback');
        fallback.addClass('d-none');
        photoView.removeClass('d-none').attr('src', '');
        if (!photoUrl) {
            photoView.addClass('d-none');
            fallback.removeClass('d-none');
            return;
        }
        photoView.attr('src', photoUrl);
    });
    $('#photoView').on('error', function() {
        $(this).addClass('d-none');
        $('#photoViewFallback').removeClass('d-none');
    });
    $('#photoModal').on('hidden.bs.modal', function() {
        $('#photoView').attr('src', '').removeClass('d-none');
        $('#photoViewFallback').addClass('d-none');
    });
    
    // View Detail
    $(document).on('click', '.btn-view-detail', function() {
        const attendanceId = $(this).data('id');
        $('#detailContent').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
        
        $.ajax({
            url: '../api/get_attendance_details.php',
            method: 'POST',
            data: { id: attendanceId },
            success: function(response) {
                const trimmed = (response || '').trim();
                if (!trimmed) {
                    $('#detailContent').html('<div class="alert alert-warning">Detail absensi kosong.</div>');
                    return;
                }
                $('#detailContent').html(response);
            },
            error: function(xhr) {
                const msg = xhr && xhr.status ? `Gagal memuat detail (${xhr.status}).` : 'Gagal memuat detail';
                $('#detailContent').html(`<div class="alert alert-danger">${msg}</div>`);
            }
        });
    });
    
    // Filter functionality
    $('#filterDate, #filterStatus, #searchInput').on('change keyup', function() {
        filterRecords();
    });
    
    $('#resetFilter').click(function() {
        $('#filterDate').val('');
        $('#filterStatus').val('');
        $('#searchInput').val('');
        filterRecords();
    });
    
    function filterRecords() {
        const filterDate = $('#filterDate').val();
        const filterStatus = $('#filterStatus').val();
        const searchText = ($('#searchInput').val() || '').toLowerCase().trim();

        const normalize = (v) => (v === undefined || v === null) ? '' : String(v).toLowerCase();
        const matchDate = (v) => !filterDate || String(v || '').substring(0, 10) === filterDate;
        const matchSearch = (...parts) => {
            if (!searchText) return true;
            return parts.some((part) => normalize(part).includes(searchText));
        };
        const setSectionVisibility = (sectionSelector, visible) => {
            $(sectionSelector).toggle(!!visible);
            if (!visible) {
                $(sectionSelector).find('.filter-empty').remove();
            }
        };
        const setFilterEmptyMessage = (sectionSelector, hasVisibleData) => {
            const section = $(sectionSelector);
            section.find('.filter-empty').remove();
            if (!section.is(':visible') || hasVisibleData) return;
            const hasDataContainer = section.find('.accordion, .table-responsive').first();
            if (!hasDataContainer.length) return;
            hasDataContainer.after('<div class="alert alert-warning filter-empty mt-2">Tidak ada data yang sesuai dengan filter</div>');
        };

        const showPendingSection = (filterStatus === '' || filterStatus === 'pending');
        const showMissedSection = (filterStatus === '' || filterStatus === 'alpa');
        const showRiwayatSection = (filterStatus === '' || ['1', '2', '3'].includes(filterStatus));
        const showSakitSection = (filterStatus === '' || filterStatus === '2');
        const showIzinSection = (filterStatus === '' || filterStatus === '3');

        // Pending
        if (showPendingSection) {
            $('.pending-item').each(function() {
                let hasMatch = false;
                const itemDate = $(this).data('date');
                $(this).find('.schedule-row').each(function() {
                    const rowDate = $(this).data('date') || itemDate;
                    const rowMatch = matchDate(rowDate) &&
                        matchSearch($(this).data('subject'), $(this).data('teacher'));
                    $(this).toggle(rowMatch);
                    if (rowMatch) hasMatch = true;
                });
                $(this).toggle(hasMatch);
            });
        } else {
            $('.pending-item').hide();
        }

        // Alpa / missed
        if (showMissedSection) {
            $('#missedSection .missed-row').each(function() {
                const rowMatch = matchDate($(this).data('date')) &&
                    matchSearch($(this).data('subject'), $(this).data('teacher'));
                $(this).toggle(rowMatch);
            });
        } else {
            $('#missedSection .missed-row').hide();
        }

        // Riwayat terlaksana
        if (showRiwayatSection) {
            $('.riwayat-item').each(function() {
                let hasMatch = false;
                const itemDate = $(this).data('date');
                $(this).find('.attendance-row').each(function() {
                    let rowMatch = true;
                    if (!matchDate(itemDate)) rowMatch = false;
                    if (rowMatch && filterStatus && ['1', '2', '3'].includes(filterStatus) && String($(this).data('status')) !== filterStatus) {
                        rowMatch = false;
                    }
                    if (rowMatch && !matchSearch($(this).data('subject'), $(this).data('teacher'), $(this).data('info'))) {
                        rowMatch = false;
                    }
                    $(this).toggle(rowMatch);
                    if (rowMatch) hasMatch = true;
                });
                $(this).toggle(hasMatch);
            });
        } else {
            $('.riwayat-item').hide();
        }

        // Rekap sakit
        if (showSakitSection) {
            $('#sakitSection .sakit-row').each(function() {
                const rowMatch = matchDate($(this).data('date')) &&
                    matchSearch($(this).data('subject'), $(this).data('teacher'));
                $(this).toggle(rowMatch);
            });
        } else {
            $('#sakitSection .sakit-row').hide();
        }

        // Rekap izin
        if (showIzinSection) {
            $('#izinSection .izin-row').each(function() {
                const rowMatch = matchDate($(this).data('date')) &&
                    matchSearch($(this).data('subject'), $(this).data('teacher'));
                $(this).toggle(rowMatch);
            });
        } else {
            $('#izinSection .izin-row').hide();
        }

        setSectionVisibility('#pendingSection', showPendingSection);
        setSectionVisibility('#missedSection', showMissedSection);
        setSectionVisibility('#riwayatSection', showRiwayatSection);
        setSectionVisibility('#sakitSection', showSakitSection);
        setSectionVisibility('#izinSection', showIzinSection);

        setFilterEmptyMessage('#pendingSection', $('.pending-item:visible').length > 0);
        setFilterEmptyMessage('#missedSection', $('#missedSection .missed-row:visible').length > 0);
        setFilterEmptyMessage('#riwayatSection', $('.riwayat-item:visible').length > 0);
        setFilterEmptyMessage('#sakitSection', $('#sakitSection .sakit-row:visible').length > 0);
        setFilterEmptyMessage('#izinSection', $('#izinSection .izin-row:visible').length > 0);
            }
        });
    };

    waitForJquery();
})();
</script>
