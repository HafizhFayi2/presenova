<?php
$tz = new DateTimeZone('Asia/Jakarta');
$nowWib = new DateTime('now', $tz);

// Ambil jadwal mengajar hari ini.
$todayDayId = (int) date('N'); // 1=Monday, 7=Sunday
$todayDate = date('Y-m-d');
$todayStmt = $db->query(
    "
    SELECT ts.*, c.class_name, d.day_name, sh.shift_name, sh.time_in, sh.time_out
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    JOIN day d ON ts.day_id = d.day_id
    JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE ts.teacher_id = ? AND ts.day_id = ?
    ORDER BY sh.time_in
",
    [$teacher_id, $todayDayId]
);
$todaySchedules = $todayStmt ? $todayStmt->fetchAll() : [];

foreach ($todaySchedules as &$schedule) {
    $computed = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', (int) ($schedule['day_id'] ?? 0));
    if ($computed) {
        $schedule['time_in'] = $computed[0];
        $schedule['time_out'] = $computed[1];
    }
}
unset($schedule);

// Ambil jadwal besok.
$tomorrowDayId = $todayDayId === 7 ? 1 : $todayDayId + 1;
$tomorrowStmt = $db->query(
    "
    SELECT ts.*, c.class_name, d.day_name, sh.shift_name, sh.time_in, sh.time_out
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    JOIN day d ON ts.day_id = d.day_id
    JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE ts.teacher_id = ? AND ts.day_id = ?
    ORDER BY sh.time_in
    LIMIT 3
",
    [$teacher_id, $tomorrowDayId]
);
$tomorrowSchedules = $tomorrowStmt ? $tomorrowStmt->fetchAll() : [];

foreach ($tomorrowSchedules as &$schedule) {
    $computed = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', (int) ($schedule['day_id'] ?? 0));
    if ($computed) {
        $schedule['time_in'] = $computed[0];
        $schedule['time_out'] = $computed[1];
    }
}
unset($schedule);

// Hitung total siswa yang diajar.
$studentsStmt = $db->query(
    "
    SELECT COUNT(DISTINCT s.id) as total
    FROM student s
    JOIN teacher_schedule ts ON s.class_id = ts.class_id
    WHERE ts.teacher_id = ?
",
    [$teacher_id]
);
$studentsRow = $studentsStmt ? $studentsStmt->fetch() : [];
$totalStudents = (int) (($studentsRow['total'] ?? 0) ?: 0);

// Mapping status absensi.
$statusStmt = $db->query("SELECT present_id, present_name FROM present_status");
$statusRows = $statusStmt ? $statusStmt->fetchAll() : [];
$statusMap = [];
foreach ($statusRows as $statusRow) {
    $statusId = (string) ($statusRow['present_id'] ?? '');
    if ($statusId === '') {
        continue;
    }
    $statusMap[$statusId] = strtolower(trim((string) ($statusRow['present_name'] ?? '')));
}

// Statistik bulan ini berbasis jadwal (sinkron termasuk alpa).
$monthStart = date('Y-m-01');
$monthEnd = $todayDate;
$monthRowsStmt = $db->query(
    "
    SELECT
        ss.student_schedule_id,
        ss.schedule_date,
        COALESCE(ss.time_in, sh.time_in, '00:00:00') as schedule_time_in,
        COALESCE(ss.time_out, sh.time_out, '00:00:00') as schedule_time_out,
        p.present_id,
        p.is_late
    FROM student_schedule ss
    JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
    LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
    LEFT JOIN presence p ON p.student_schedule_id = ss.student_schedule_id
    WHERE ts.teacher_id = ?
      AND ss.schedule_date BETWEEN ? AND ?
",
    [$teacher_id, $monthStart, $monthEnd]
);
$monthRows = $monthRowsStmt ? $monthRowsStmt->fetchAll() : [];

$attendanceStats = [
    'total' => 0,
    'hadir' => 0,
    'sakit' => 0,
    'izin' => 0,
    'alpa' => 0,
    'terlambat' => 0,
];

foreach ($monthRows as $row) {
    $scheduleDate = (string) ($row['schedule_date'] ?? '');
    if ($scheduleDate === '') {
        continue;
    }

    $scheduleTimeIn = (string) ($row['schedule_time_in'] ?? '00:00:00');
    $scheduleTimeOut = (string) ($row['schedule_time_out'] ?? '00:00:00');
    [, $scheduleEnd] = buildScheduleWindow($scheduleDate, $scheduleTimeIn, $scheduleTimeOut, $tz, 0);

    $presentId = isset($row['present_id']) ? (int) $row['present_id'] : 0;
    if ($presentId <= 0 && $nowWib <= $scheduleEnd) {
        continue;
    }

    $attendanceStats['total']++;
    if ($presentId <= 0) {
        $attendanceStats['alpa']++;
        continue;
    }

    $statusName = $statusMap[(string) $presentId] ?? '';
    if ($statusName === 'hadir' || $presentId === 1) {
        $attendanceStats['hadir']++;
        if (($row['is_late'] ?? 'N') === 'Y') {
            $attendanceStats['terlambat']++;
        }
        continue;
    }

    if ($statusName === 'sakit' || $presentId === 2) {
        $attendanceStats['sakit']++;
        continue;
    }

    if ($statusName === 'izin' || $presentId === 3) {
        $attendanceStats['izin']++;
        continue;
    }

    if ($statusName === 'alpa' || $statusName === 'tidak hadir' || $presentId === 4) {
        $attendanceStats['alpa']++;
    }
}

$totalAttendance = max(0, (int) ($attendanceStats['total'] ?? 0));
$hadirCount = max(0, (int) ($attendanceStats['hadir'] ?? 0));
$terlambatCount = max(0, (int) ($attendanceStats['terlambat'] ?? 0));
$sakitCount = max(0, (int) ($attendanceStats['sakit'] ?? 0));
$izinCount = max(0, (int) ($attendanceStats['izin'] ?? 0));
$alpaCount = max(0, (int) ($attendanceStats['alpa'] ?? 0));
$attendanceBase = $totalAttendance > 0 ? $totalAttendance : 1;
$hadirRate = max(0, min(100, $totalAttendance > 0 ? (int) round(($hadirCount / $attendanceBase) * 100) : 0));
$terlambatRate = max(0, min(100, $totalAttendance > 0 ? (int) round(($terlambatCount / $attendanceBase) * 100) : 0));
$sakitRate = max(0, min(100, $totalAttendance > 0 ? (int) round(($sakitCount / $attendanceBase) * 100) : 0));
$izinRate = max(0, min(100, $totalAttendance > 0 ? (int) round(($izinCount / $attendanceBase) * 100) : 0));
$alpaRate = max(0, min(100, $totalAttendance > 0 ? (int) round(($alpaCount / $attendanceBase) * 100) : 0));
$teacherName = htmlspecialchars((string) ($teacher['teacher_name'] ?? '-'), ENT_QUOTES, 'UTF-8');
$teacherSubject = htmlspecialchars((string) ($teacher['subject'] ?? '-'), ENT_QUOTES, 'UTF-8');
?>

<div class="teacher-dashboard">
    <section class="teacher-hero">
        <div class="teacher-hero-copy">
            <p class="teacher-eyebrow">Dashboard Guru</p>
            <h3>Selamat datang, <?php echo $teacherName; ?></h3>
            <p>
                Anda mengajar mata pelajaran <strong><?php echo $teacherSubject; ?></strong> untuk
                <strong><?php echo (int) $totalStudents; ?> siswa</strong>.
            </p>
            <div class="teacher-chip-row">
                <span class="teacher-chip">
                    <i class="fas fa-calendar-day"></i>
                    <?php echo date('d F Y'); ?>
                </span>
                <span class="teacher-chip">
                    <i class="fas fa-clock"></i>
                    <?php echo count($todaySchedules); ?> sesi hari ini
                </span>
            </div>
        </div>
        <div class="teacher-hero-side">
            <div class="teacher-hero-metric">
                <span>Total Rekap Bulan Ini</span>
                <strong><?php echo $totalAttendance; ?></strong>
            </div>
            <div class="teacher-hero-metric">
                <span>Rasio Kehadiran</span>
                <strong><?php echo $hadirRate; ?>%</strong>
            </div>
        </div>
    </section>

    <section class="teacher-kpi-grid">
        <article class="teacher-kpi-card">
            <div class="teacher-kpi-icon"><i class="fas fa-users"></i></div>
            <div class="teacher-kpi-body">
                <h4><?php echo (int) $totalStudents; ?></h4>
                <p>Total Siswa</p>
            </div>
        </article>
        <article class="teacher-kpi-card">
            <div class="teacher-kpi-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="teacher-kpi-body">
                <h4><?php echo $hadirCount; ?></h4>
                <p>Hadir Bulan Ini</p>
            </div>
        </article>
        <article class="teacher-kpi-card">
            <div class="teacher-kpi-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="teacher-kpi-body">
                <h4><?php echo count($todaySchedules); ?></h4>
                <p>Jadwal Hari Ini</p>
            </div>
        </article>
        <article class="teacher-kpi-card">
            <div class="teacher-kpi-icon"><i class="fas fa-user-clock"></i></div>
            <div class="teacher-kpi-body">
                <h4><?php echo $terlambatCount; ?></h4>
                <p>Keterlambatan</p>
            </div>
        </article>
    </section>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="teacher-panel">
                <div class="teacher-panel-header">
                    <h5 class="teacher-panel-title">
                        <i class="fas fa-chalkboard"></i>
                        Jadwal Mengajar Hari Ini
                    </h5>
                    <a href="?page=jadwal" class="teacher-quiet-btn">Lihat Semua</a>
                </div>

                <?php if (!empty($todaySchedules)): ?>
                    <div class="teacher-schedule-grid">
                        <?php foreach ($todaySchedules as $schedule): ?>
                            <?php
                            $attendanceCheckStmt = $db->query(
                                "
                                SELECT COUNT(*) as total_rekap
                                FROM presence p
                                JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
                                WHERE ss.teacher_schedule_id = ?
                                  AND DATE(p.presence_date) = CURDATE()
                            ",
                                [(int) ($schedule['schedule_id'] ?? 0)]
                            );
                            $attendanceCheckRow = $attendanceCheckStmt ? $attendanceCheckStmt->fetch() : [];
                            $attendanceCheck = (int) (($attendanceCheckRow['total_rekap'] ?? 0) ?: 0);
                            $statusClass = $attendanceCheck > 0 ? 'is-done' : 'is-pending';
                            $statusLabel = $attendanceCheck > 0 ? 'Rekap Tersedia' : 'Belum Ada Rekap';
                            $subjectName = htmlspecialchars((string) ($schedule['subject'] ?? '-'), ENT_QUOTES, 'UTF-8');
                            $className = htmlspecialchars((string) ($schedule['class_name'] ?? '-'), ENT_QUOTES, 'UTF-8');
                            $shiftName = htmlspecialchars((string) ($schedule['shift_name'] ?? ($schedule['shift_id'] ?? '-')), ENT_QUOTES, 'UTF-8');
                            $timeIn = !empty($schedule['time_in']) ? date('H:i', strtotime((string) $schedule['time_in'])) : '--:--';
                            $timeOut = !empty($schedule['time_out']) ? date('H:i', strtotime((string) $schedule['time_out'])) : '--:--';
                            $classId = (int) ($schedule['class_id'] ?? 0);
                            ?>
                            <article class="teacher-schedule-card">
                                <div class="teacher-schedule-top">
                                    <div>
                                        <h6><?php echo $subjectName; ?></h6>
                                        <p>
                                            <i class="fas fa-clock"></i>
                                            <?php echo $timeIn; ?> - <?php echo $timeOut; ?>
                                        </p>
                                    </div>
                                    <span class="teacher-status-pill <?php echo $statusClass; ?>">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </div>
                                <div class="teacher-schedule-bottom">
                                    <div class="teacher-schedule-meta">
                                        <span><i class="fas fa-school"></i><?php echo $className; ?></span>
                                        <span><i class="fas fa-layer-group"></i><?php echo $shiftName; ?></span>
                                    </div>
                                    <a href="?page=absensi&class=<?php echo $classId; ?>&date_from=<?php echo $todayDate; ?>&date_to=<?php echo $todayDate; ?>" class="teacher-quiet-btn teacher-quiet-btn--soft">
                                        Lihat Siswa
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="teacher-empty">
                        <i class="fas fa-calendar-check"></i>
                        <p>Tidak ada jadwal mengajar hari ini.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="teacher-panel teacher-panel--compact">
                <div class="teacher-panel-header">
                    <h5 class="teacher-panel-title">
                        <i class="fas fa-calendar-plus"></i>
                        Jadwal Besok
                    </h5>
                </div>
                <?php if (!empty($tomorrowSchedules)): ?>
                    <ul class="teacher-compact-list">
                        <?php foreach ($tomorrowSchedules as $schedule): ?>
                            <?php
                            $subjectName = htmlspecialchars((string) ($schedule['subject'] ?? '-'), ENT_QUOTES, 'UTF-8');
                            $className = htmlspecialchars((string) ($schedule['class_name'] ?? '-'), ENT_QUOTES, 'UTF-8');
                            $timeIn = !empty($schedule['time_in']) ? date('H:i', strtotime((string) $schedule['time_in'])) : '--:--';
                            $timeOut = !empty($schedule['time_out']) ? date('H:i', strtotime((string) $schedule['time_out'])) : '--:--';
                            ?>
                            <li class="teacher-compact-item">
                                <div>
                                    <strong><?php echo $subjectName; ?></strong>
                                    <span><?php echo $className; ?></span>
                                </div>
                                <span class="teacher-compact-time"><?php echo $timeIn; ?> - <?php echo $timeOut; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="?page=jadwal" class="teacher-quiet-btn teacher-quiet-btn--ghost">Lihat Jadwal Lengkap</a>
                <?php else: ?>
                    <div class="teacher-empty teacher-empty--small">
                        <i class="fas fa-calendar"></i>
                        <p>Tidak ada jadwal besok.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="teacher-panel teacher-panel--compact">
                <div class="teacher-panel-header">
                    <h5 class="teacher-panel-title">
                        <i class="fas fa-chart-line"></i>
                        Statistik Bulan Ini
                    </h5>
                </div>
                <div class="teacher-stat-list">
                    <div class="teacher-stat-row">
                        <div class="teacher-stat-head">
                            <span>Hadir</span>
                            <strong><?php echo $hadirCount; ?></strong>
                        </div>
                        <div class="teacher-progress"><span style="width: <?php echo $hadirRate; ?>%;"></span></div>
                    </div>
                    <div class="teacher-stat-row">
                        <div class="teacher-stat-head">
                            <span>Terlambat</span>
                            <strong><?php echo $terlambatCount; ?></strong>
                        </div>
                        <div class="teacher-progress"><span style="width: <?php echo $terlambatRate; ?>%;"></span></div>
                    </div>
                    <div class="teacher-stat-row">
                        <div class="teacher-stat-head">
                            <span>Sakit</span>
                            <strong><?php echo $sakitCount; ?></strong>
                        </div>
                        <div class="teacher-progress"><span style="width: <?php echo $sakitRate; ?>%;"></span></div>
                    </div>
                    <div class="teacher-stat-row">
                        <div class="teacher-stat-head">
                            <span>Izin</span>
                            <strong><?php echo $izinCount; ?></strong>
                        </div>
                        <div class="teacher-progress"><span style="width: <?php echo $izinRate; ?>%;"></span></div>
                    </div>
                    <div class="teacher-stat-row">
                        <div class="teacher-stat-head">
                            <span>Alpa</span>
                            <strong><?php echo $alpaCount; ?></strong>
                        </div>
                        <div class="teacher-progress"><span style="width: <?php echo $alpaRate; ?>%;"></span></div>
                    </div>
                </div>
                <a href="?page=laporan" class="teacher-quiet-btn teacher-quiet-btn--ghost">Lihat Laporan Detail</a>
            </section>
        </div>
    </div>
</div>
