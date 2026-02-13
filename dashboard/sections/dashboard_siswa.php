<?php
require_once '../includes/database_helper.php';

$student_id = $_SESSION['student_id'];
$class_id = $student['class_id'] ?? null;
$student_name = $student['student_name'] ?? 'Siswa';
$first_name = explode(' ', trim($student_name))[0] ?? $student_name;

$dbHelper = new DatabaseHelper($db);

$site_stmt = $db->query("SELECT time_tolerance FROM site LIMIT 1");
$site_setting = $site_stmt ? $site_stmt->fetch(PDO::FETCH_ASSOC) : null;
$time_tolerance = isset($site_setting['time_tolerance']) ? (int) $site_setting['time_tolerance'] : 15;
if ($time_tolerance < 0) {
    $time_tolerance = 0;
}
$tz = new DateTimeZone('Asia/Jakarta');

$day_mapping = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$today_indonesian = $day_mapping[date('l')] ?? date('l');
$tomorrow_date = new DateTime('tomorrow');
$tomorrow_indonesian = $day_mapping[$tomorrow_date->format('l')] ?? $tomorrow_date->format('l');
$tomorrow_date_label = $tomorrow_date->format('d F Y');

$schedules = [];
$today_schedules = [];
$tomorrow_schedules = [];
if ($class_id) {
    $schedules = $dbHelper->getStudentSchedule($student_id, $class_id);
    foreach ($schedules as $schedule) {
        if ($schedule['day_name'] === $today_indonesian) {
            $today_schedules[] = $schedule;
        }
        if ($schedule['day_name'] === $tomorrow_indonesian) {
            $tomorrow_schedules[] = $schedule;
        }
    }
}

// Ensure schedules are ordered by time for consistent "next schedule"
if (count($today_schedules) > 1) {
    usort($today_schedules, function($a, $b) {
        $ta = strtotime($a['time_in'] ?? '00:00:00');
        $tb = strtotime($b['time_in'] ?? '00:00:00');
        if ($ta === $tb) {
            return (int)($a['schedule_id'] ?? 0) <=> (int)($b['schedule_id'] ?? 0);
        }
        return $ta <=> $tb;
    });
}
if (count($tomorrow_schedules) > 1) {
    usort($tomorrow_schedules, function($a, $b) {
        $ta = strtotime($a['time_in'] ?? '00:00:00');
        $tb = strtotime($b['time_in'] ?? '00:00:00');
        if ($ta === $tb) {
            return (int)($a['schedule_id'] ?? 0) <=> (int)($b['schedule_id'] ?? 0);
        }
        return $ta <=> $tb;
    });
}

// Attendance stats for current month (include alpa when schedule closed without attendance)
$month_start = (new DateTime('first day of this month', $tz))->format('Y-m-d');
$month_end = (new DateTime('last day of this month', $tz))->format('Y-m-d');
$monthly_sql = "
    SELECT 
        ss.student_schedule_id,
        ss.schedule_date,
        ss.time_in,
        ss.time_out,
        (
            SELECT p.present_id
            FROM presence p
            WHERE p.student_schedule_id = ss.student_schedule_id
            ORDER BY p.time_in DESC
            LIMIT 1
        ) as present_id,
        (
            SELECT p.is_late
            FROM presence p
            WHERE p.student_schedule_id = ss.student_schedule_id
            ORDER BY p.time_in DESC
            LIMIT 1
        ) as is_late
    FROM student_schedule ss
    WHERE ss.student_id = ?
    AND ss.schedule_date BETWEEN ? AND ?
";
$monthly_rows = $db->query($monthly_sql, [$student_id, $month_start, $month_end])->fetchAll() ?: [];

$attendance_present = 0;
$attendance_sick = 0;
$attendance_permission = 0;
$attendance_alpa = 0;
$attendance_late = 0;
$attendance_finished = 0;
$now_dt = new DateTime('now', $tz);

foreach ($monthly_rows as $row) {
    $present_id = (int)($row['present_id'] ?? 0);
    $is_late = ($row['is_late'] ?? '') === 'Y';
    $schedule_date = $row['schedule_date'] ?? $month_start;
    [$start_dt, $end_dt] = buildScheduleWindow(
        $schedule_date,
        $row['time_in'] ?? '00:00:00',
        $row['time_out'] ?? '00:00:00',
        $tz,
        (int) $time_tolerance
    );

    if ($present_id > 0) {
        $attendance_finished++;
        if ($present_id === 1) {
            $attendance_present++;
            if ($is_late) {
                $attendance_late++;
            }
        } elseif ($present_id === 2) {
            $attendance_sick++;
        } elseif ($present_id === 3) {
            $attendance_permission++;
        } elseif ($present_id === 4) {
            $attendance_alpa++;
        }
        continue;
    }

    if ($now_dt > $end_dt) {
        $attendance_alpa++;
        $attendance_finished++;
    }
}

$attendance_rate = $attendance_finished > 0
    ? round(($attendance_present / $attendance_finished) * 100)
    : 0;

// Attendance today
$today_attendance = $db->query(
    "SELECT COUNT(*) as total FROM presence WHERE student_id = ? AND presence_date = CURDATE()",
    [$student_id]
)->fetch() ?: [];
$today_attendance_count = (int)($today_attendance['total'] ?? 0);

// Recent attendance (last 3)
$recent_sql = "
    SELECT
        p.presence_date,
        p.time_in,
        p.present_id,
        p.is_late,
        p.late_time,
        ps.present_name,
        ts.subject,
        t.teacher_name
    FROM presence p
    LEFT JOIN present_status ps ON p.present_id = ps.present_id
    LEFT JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
    LEFT JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
    LEFT JOIN teacher t ON ts.teacher_id = t.id
    WHERE p.student_id = ?
    ORDER BY p.presence_date DESC, p.time_in DESC
    LIMIT 3
";
$recent_records = $db->query($recent_sql, [$student_id])->fetchAll() ?: [];

// Weekly summary
$week_sql = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN present_id = 1 THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN present_id = 2 THEN 1 ELSE 0 END) as sakit,
        SUM(CASE WHEN present_id = 3 THEN 1 ELSE 0 END) as izin,
        SUM(CASE WHEN is_late = 'Y' THEN 1 ELSE 0 END) as terlambat
    FROM presence
    WHERE student_id = ?
    AND presence_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
";
$week_stats = $db->query($week_sql, [$student_id])->fetch() ?: [];
$week_total = (int)($week_stats['total'] ?? 0);
$week_present = (int)($week_stats['hadir'] ?? 0);
$week_sick = (int)($week_stats['sakit'] ?? 0);
$week_permission = (int)($week_stats['izin'] ?? 0);
$week_late = (int)($week_stats['terlambat'] ?? 0);

// Next schedule for today
$next_schedule = null;
$now_time = date('H:i:s');
foreach ($today_schedules as $schedule) {
    if ($now_time < $schedule['time_in']) {
        $next_schedule = $schedule;
        break;
    }
}
?>

<?php if (!$class_id): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Anda belum terdaftar di kelas manapun. Silakan hubungi administrator.
    </div>
<?php else: ?>
    <div class="dashboard-hero">
        <div class="hero-left">
            <div class="hero-pill">
                <i class="fas fa-layer-group"></i>
                <span>Portal Siswa</span>
            </div>
            <h3>Halo, <?php echo htmlspecialchars($first_name); ?>.</h3>
            <p class="hero-subtitle">
                Kelas <?php echo htmlspecialchars($student['class_name'] ?? '-'); ?> -
                <?php echo htmlspecialchars($student['jurusan_name'] ?? '-'); ?>
            </p>
            <div class="hero-meta">
                <div class="meta-item">
                    <span>Absen Hari Ini</span>
                    <strong><?php echo $today_attendance_count; ?></strong>
                </div>
                <div class="meta-item">
                    <span>Persentase Hadir</span>
                    <strong><?php echo $attendance_rate; ?>%</strong>
                </div>
                <div class="meta-item">
                    <span>Jadwal Hari Ini</span>
                    <strong><?php echo count($today_schedules); ?></strong>
                </div>
            </div>
        </div>
        <div class="hero-right">
            <div class="next-class-card">
                <div class="next-class-label">Jadwal Berikutnya</div>
                <?php if ($next_schedule): ?>
                    <div class="next-class-title"><?php echo htmlspecialchars($next_schedule['subject']); ?></div>
                    <div class="next-class-meta">
                        <i class="fas fa-clock"></i>
                        <?php echo date('H:i', strtotime($next_schedule['time_in'])); ?> - <?php echo date('H:i', strtotime($next_schedule['time_out'])); ?>
                    </div>
                    <div class="next-class-meta">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <?php echo htmlspecialchars($next_schedule['teacher_name']); ?>
                    </div>
                <?php else: ?>
                    <div class="next-class-empty">Tidak ada jadwal berikutnya.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stat-grid mt-1">
        <a href="?page=riwayat" class="stat-link">
            <div class="stat-tile">
            <div class="stat-icon bg-primary">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h4><?php echo $attendance_present; ?></h4>
                <p>Hadir</p>
            </div>
            </div>
        </a>
        <a href="?page=riwayat" class="stat-link">
            <div class="stat-tile">
            <div class="stat-icon bg-warning">
                <i class="fas fa-notes-medical"></i>
            </div>
            <div class="stat-content">
                <h4><?php echo $attendance_sick; ?></h4>
                <p>Sakit</p>
            </div>
            </div>
        </a>
        <a href="?page=riwayat" class="stat-link">
            <div class="stat-tile">
            <div class="stat-icon bg-info">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <h4><?php echo $attendance_permission; ?></h4>
                <p>Izin</p>
            </div>
            </div>
        </a>
        <a href="?page=riwayat" class="stat-link">
            <div class="stat-tile">
            <div class="stat-icon bg-danger">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stat-content">
                <h4><?php echo $attendance_alpa; ?></h4>
                <p>Alpa</p>
            </div>
            </div>
        </a>
        <a href="?page=riwayat" class="stat-link">
            <div class="stat-tile">
            <div class="stat-icon bg-success">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h4><?php echo $attendance_rate; ?>%</h4>
                <p>Rasio Kehadiran</p>
            </div>
            </div>
        </a>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-lg-8">
            <div class="row g-4">
                <div class="col-12 col-xl-6">
                    <div class="glass-panel">
                        <div class="panel-header">
                            <div>
                                <h5>Jadwal Absensi Hari Ini</h5>
                                <p><?php echo $today_indonesian . ', ' . date('d F Y'); ?></p>
                            </div>
                            <a href="?page=jadwal" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-calendar-alt me-1"></i> Lihat Jadwal
                            </a>
                        </div>

                        <?php if (count($today_schedules) > 0): ?>
                            <div class="schedule-list">
                                <?php foreach ($today_schedules as $schedule): ?>
                                    <?php
                                        $attendance_count = (int)($schedule['attendance_count'] ?? 0);
                                        $status_text = 'Menunggu';
                                        $status_class = 'status-muted';
                                        $show_action = false;

                                $time_in = $schedule['time_in'];
                                $time_out = $schedule['time_out'];
                                $base_date = !empty($schedule['schedule_date']) ? $schedule['schedule_date'] : date('Y-m-d');
                                $now_dt = new DateTime('now', $tz);
                                [$start_dt, $end_dt, $base_end_dt] = buildScheduleWindow(
                                    $base_date,
                                    $time_in,
                                    $time_out,
                                    $tz,
                                    (int) $time_tolerance
                                );
                                $start_ts = $start_dt->getTimestamp();
                                $end_ts = $end_dt->getTimestamp();
                                $base_end_ts = $base_end_dt->getTimestamp();
                                $countdown_start_ts = $start_ts - 120;
                                $overdue_end_ts = $end_ts;
                                $now_ts = $now_dt->getTimestamp();

                                if ($attendance_count > 0) {
                                    $status_text = ($schedule['attendance_is_late'] ?? '') === 'Y' ? 'OVERDUE' : 'SUCCESS';
                                    $status_class = ($schedule['attendance_is_late'] ?? '') === 'Y' ? 'status-warning' : 'status-success';
                                } else {
                                    if ($now_ts < $countdown_start_ts) {
                                        $status_text = 'MENUNGGU';
                                        $status_class = 'status-muted';
                                    } elseif ($now_ts >= $countdown_start_ts && $now_ts < $start_ts) {
                                        $remaining = max(0, $start_ts - $now_ts);
                                        $mins = floor($remaining / 60);
                                        $secs = $remaining % 60;
                                        $status_text = sprintf('COUNTDOWN %02d:%02d', $mins, $secs);
                                        $status_class = 'status-info';
                                    } elseif ($now_ts >= $start_ts && $now_ts <= $base_end_ts) {
                                        $status_text = 'ACTIVE';
                                        $status_class = 'status-info';
                                        $show_action = true;
                                    } elseif ($now_ts > $base_end_ts && $now_ts <= $overdue_end_ts) {
                                        $status_text = 'OVERDUE';
                                        $status_class = 'status-warning';
                                        $show_action = true;
                                    } else {
                                        $status_text = 'ALPA';
                                        $status_class = 'status-danger';
                                    }
                                }
                                    ?>
                                    <div class="schedule-item <?php echo $attendance_count === 0 ? 'live-schedule-item' : ''; ?>"
                                         <?php if ($attendance_count === 0): ?>
                                             data-countdown-start="<?php echo (int)$countdown_start_ts; ?>"
                                             data-start="<?php echo (int)$start_ts; ?>"
                                             data-base-end="<?php echo (int)$base_end_ts; ?>"
                                             data-end="<?php echo (int)$end_ts; ?>"
                                         <?php endif; ?>>
                                        <div class="schedule-time">
                                            <span><?php echo date('H:i', strtotime($schedule['time_in'])); ?></span>
                                            <small><?php echo date('H:i', strtotime($schedule['time_out'])); ?></small>
                                        </div>
                                        <div class="schedule-info">
                                            <div class="schedule-subject"><?php echo htmlspecialchars($schedule['subject']); ?></div>
                                            <div class="schedule-meta">
                                                <span><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($schedule['teacher_name']); ?></span>
                                                <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($schedule['shift_name']); ?></span>
                                            </div>
                                        </div>
                                        <div class="schedule-actions">
                                            <span class="status-pill js-live-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            <?php if ($attendance_count === 0): ?>
                                                <a href="?page=jadwal" class="btn-attendance btn-sm js-live-action <?php echo $show_action ? '' : 'd-none'; ?>">
                                                    <i class="fas fa-camera"></i> Absen
                                                </a>
                                            <?php elseif ($show_action): ?>
                                                <a href="?page=jadwal" class="btn-attendance btn-sm">
                                                    <i class="fas fa-camera"></i> Absen
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h6>Tidak ada jadwal hari ini</h6>
                                <p>Gunakan waktu untuk belajar mandiri.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="glass-panel">
                        <div class="panel-header">
                            <div>
                                <h5>Jadwal Esok Hari</h5>
                                <p><?php echo $tomorrow_indonesian . ', ' . $tomorrow_date_label; ?></p>
                            </div>
                        </div>

                        <?php if (count($tomorrow_schedules) > 0): ?>
                            <div class="schedule-list">
                                <?php foreach ($tomorrow_schedules as $schedule): ?>
                                    <div class="schedule-item">
                                        <div class="schedule-time">
                                            <span><?php echo date('H:i', strtotime($schedule['time_in'])); ?></span>
                                            <small><?php echo date('H:i', strtotime($schedule['time_out'])); ?></small>
                                        </div>
                                        <div class="schedule-info">
                                            <div class="schedule-subject"><?php echo htmlspecialchars($schedule['subject']); ?></div>
                                            <div class="schedule-meta">
                                                <span><i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($schedule['teacher_name']); ?></span>
                                                <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($schedule['shift_name']); ?></span>
                                            </div>
                                        </div>
                                        <div class="schedule-actions">
                                            <span class="status-pill status-muted">Menunggu</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h6>Tidak ada jadwal esok hari</h6>
                                <p>Siapkan diri untuk jadwal berikutnya.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="glass-panel mt-4">
                <div class="panel-header">
                    <div>
                        <h5>Akses Cepat</h5>
                        <p>Menu utama siswa</p>
                    </div>
                </div>
                <div class="quick-links">
                    <a href="?page=jadwal" class="quick-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Jadwal</span>
                    </a>
                    <a href="?page=riwayat" class="quick-link">
                        <i class="fas fa-history"></i>
                        <span>Riwayat</span>
                    </a>
                    <a href="?page=profil" class="quick-link">
                        <i class="fas fa-user-circle"></i>
                        <span>Profil</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-panel">
                <div class="panel-header">
                    <div>
                        <h5>Ringkasan 7 Hari</h5>
                        <p>Aktivitas absensi terbaru</p>
                    </div>
                </div>
                <div class="summary-grid">
                    <div class="summary-item">
                        <span>Hadir</span>
                        <strong><?php echo $week_present; ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Izin</span>
                        <strong><?php echo $week_permission; ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Sakit</span>
                        <strong><?php echo $week_sick; ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Terlambat</span>
                        <strong><?php echo $week_late; ?></strong>
                    </div>
                    <div class="summary-item full">
                        <span>Total</span>
                        <strong><?php echo $week_total; ?></strong>
                    </div>
                </div>
                <div class="recent-list">
                    <?php if (!empty($recent_records)): ?>
                        <?php foreach ($recent_records as $record): ?>
                            <?php
                                $recent_class = 'status-warning';
                                if ((int)($record['present_id'] ?? 0) === 1) {
                                    $recent_class = 'status-success';
                                } elseif ((int)($record['present_id'] ?? 0) === 3) {
                                    $recent_class = 'status-info';
                                } elseif ((int)($record['present_id'] ?? 0) > 3) {
                                    $recent_class = 'status-danger';
                                }
                            ?>
                            <div class="recent-item">
                                <div>
                                    <div class="recent-title"><?php echo htmlspecialchars($record['subject'] ?? 'Mata Pelajaran'); ?></div>
                                    <div class="recent-meta">
                                        <?php echo date('d M Y', strtotime($record['presence_date'])); ?>
                                        - <?php echo date('H:i', strtotime($record['time_in'])); ?>
                                    </div>
                                </div>
                                <span class="status-pill <?php echo $recent_class; ?>">
                                    <?php echo htmlspecialchars($record['present_name'] ?? 'Hadir'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state small">
                            <i class="fas fa-history"></i>
                            <p>Belum ada riwayat absensi.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="?page=riwayat" class="btn btn-outline-primary w-100 mt-3">
                    <i class="fas fa-history me-1"></i> Lihat Riwayat
                </a>
            </div>

        </div>
    </div>
<?php endif; ?>

<style>
.dashboard-hero {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    padding: 24px;
    border-radius: 22px;
    background: var(--card-color);
    border: 1px solid var(--border);
    box-shadow: var(--glass-shadow);
    backdrop-filter: blur(var(--blur)) saturate(150%);
    -webkit-backdrop-filter: blur(var(--blur)) saturate(150%);
    margin-bottom: 22px;
    flex-wrap: wrap;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.dashboard-hero:hover {
    transform: translateY(-4px);
    box-shadow: var(--glass-shadow-strong);
}
.hero-left h3 {
    margin-top: 12px;
    margin-bottom: 6px;
}

.hero-subtitle {
    color: var(--text-secondary-color);
    margin-bottom: 18px;
}

.hero-pill {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(53, 121, 246, 0.12);
    color: var(--primary-blue);
    font-weight: 600;
    font-size: 0.85rem;
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.meta-item {
    min-width: 140px;
    padding: 12px 14px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.45);
    border: 1px solid var(--border);
    backdrop-filter: blur(var(--blur));
    -webkit-backdrop-filter: blur(var(--blur));
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.meta-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--glass-shadow);
}

[data-theme="dark"] .meta-item {
    background: rgba(15, 23, 42, 0.55);
}

.meta-item span {
    display: block;
    font-size: 0.8rem;
    color: var(--text-secondary-color);
}

.meta-item strong {
    font-size: 1.1rem;
}

.hero-right {
    flex: 1 1 220px;
    display: flex;
    justify-content: flex-end;
}

.next-class-card {
    min-width: 220px;
    padding: 18px;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(53, 121, 246, 0.18) 0%, rgba(255, 159, 107, 0.12) 100%);
    border: 1px solid var(--border);
    box-shadow: var(--glass-shadow);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    animation: floatCard 6s ease-in-out infinite;
}

.next-class-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--glass-shadow-strong);
}

@keyframes floatCard {
    0% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
    100% { transform: translateY(0); }
}

.next-class-label {
    font-size: 0.8rem;
    color: var(--text-secondary-color);
    margin-bottom: 8px;
}

.next-class-title {
    font-weight: 700;
    margin-bottom: 10px;
}

.next-class-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: var(--text-secondary-color);
    margin-bottom: 6px;
}

.next-class-empty {
    font-size: 0.9rem;
    color: var(--text-secondary-color);
}

.stat-tile {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px;
    border-radius: 18px;
    background: var(--card-color);
    border: 1px solid var(--border);
    box-shadow: var(--glass-shadow);
    backdrop-filter: blur(var(--blur)) saturate(150%);
    -webkit-backdrop-filter: blur(var(--blur)) saturate(150%);
    height: 100%;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.stat-link:hover .stat-tile {
    transform: translateY(-3px);
    box-shadow: var(--glass-shadow-strong);
}

.stat-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.stat-link:focus-visible .stat-tile {
    outline: 2px solid rgba(59, 130, 246, 0.6);
    outline-offset: 3px;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 14px;
    width: 100%;
}

@media (max-width: 1400px) {
    .stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 992px) {
    .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 576px) {
    .stat-grid {
        grid-template-columns: 1fr;
    }
}

.dashboard-hero:hover .next-class-card {
    transform: translateY(-4px);
    box-shadow: var(--glass-shadow-strong);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.2rem;
}

.stat-content h4 {
    margin: 0;
    font-size: 1.4rem;
}

.stat-content p {
    margin: 0;
    color: var(--text-secondary-color);
    font-size: 0.85rem;
}

.glass-panel {
    background: var(--card-color);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 20px;
    box-shadow: var(--glass-shadow);
    backdrop-filter: blur(var(--blur)) saturate(150%);
    -webkit-backdrop-filter: blur(var(--blur)) saturate(150%);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.glass-panel:hover {
    transform: translateY(-3px);
    box-shadow: var(--glass-shadow-strong);
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.panel-header h5 {
    margin-bottom: 4px;
}

.panel-header p {
    margin-bottom: 0;
    color: var(--text-secondary-color);
    font-size: 0.85rem;
}

.schedule-list {
    display: grid;
    gap: 12px;
}

.schedule-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.45);
    border: 1px solid var(--border);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.schedule-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--glass-shadow);
}

[data-theme="dark"] .schedule-item {
    background: rgba(15, 23, 42, 0.55);
}

[data-theme="light"] .schedule-item {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(148, 163, 184, 0.32);
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
}

.schedule-time {
    min-width: 72px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(53, 121, 246, 0.12);
    text-align: center;
    font-weight: 600;
}

[data-theme="light"] .schedule-time {
    background: rgba(53, 121, 246, 0.16);
    border: 1px solid rgba(53, 121, 246, 0.28);
}

.schedule-time small {
    display: block;
    font-size: 0.75rem;
    color: var(--text-secondary-color);
}

.schedule-info {
    flex: 1;
}

.schedule-subject {
    font-weight: 600;
}

.schedule-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.8rem;
    color: var(--text-secondary-color);
}

.schedule-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.status-pill {
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid transparent;
}

.status-success {
    background: rgba(25, 135, 84, 0.15);
    color: #2b8a57;
    border-color: rgba(25, 135, 84, 0.25);
}

.status-warning {
    background: rgba(255, 193, 7, 0.18);
    color: #b08900;
    border-color: rgba(255, 193, 7, 0.35);
}

.status-danger {
    background: rgba(220, 53, 69, 0.15);
    color: #d94848;
    border-color: rgba(220, 53, 69, 0.3);
}

.status-info {
    background: rgba(53, 121, 246, 0.15);
    color: #3563f6;
    border-color: rgba(53, 121, 246, 0.3);
}

.status-muted {
    background: rgba(148, 163, 184, 0.2);
    color: var(--text-secondary-color);
    border-color: rgba(148, 163, 184, 0.25);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.summary-item {
    padding: 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.45);
    border: 1px solid var(--border);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.summary-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--glass-shadow);
}

[data-theme="dark"] .summary-item {
    background: rgba(15, 23, 42, 0.55);
}

[data-theme="light"] .summary-item {
    background: rgba(255, 255, 255, 0.88);
    border-color: rgba(148, 163, 184, 0.32);
}

.summary-item span {
    display: block;
    font-size: 0.8rem;
    color: var(--text-secondary-color);
}

.summary-item strong {
    font-size: 1.2rem;
}

.summary-item.full {
    grid-column: span 2;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-list {
    margin-top: 18px;
    display: grid;
    gap: 10px;
}

.recent-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.45);
    border: 1px solid var(--border);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.recent-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--glass-shadow);
}

[data-theme="dark"] .recent-item {
    background: rgba(15, 23, 42, 0.55);
}

[data-theme="light"] .recent-item {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(148, 163, 184, 0.32);
}

.recent-title {
    font-weight: 600;
}

.recent-meta {
    font-size: 0.75rem;
    color: var(--text-secondary-color);
}

.quick-links {
    display: grid;
    gap: 10px;
}

.quick-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.45);
    border: 1px solid var(--border);
    color: var(--text-color);
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.quick-link:hover {
    transform: translateY(-2px);
    box-shadow: var(--glass-shadow);
}

[data-theme="dark"] .quick-link {
    background: rgba(15, 23, 42, 0.55);
    color: var(--text-color);
}

[data-theme="light"] .quick-link {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(148, 163, 184, 0.3);
}

.empty-state {
    text-align: center;
    padding: 24px 16px;
    color: var(--text-secondary-color);
}

.empty-state i {
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.empty-state.small {
    padding: 12px 0;
}

@media (max-width: 992px) {
    .hero-right {
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .panel-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .schedule-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .schedule-actions {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }
}

@media (max-width: 576px) {
    .dashboard-hero {
        padding: 18px;
    }

    .hero-meta {
        flex-direction: column;
        width: 100%;
    }

    .meta-item {
        width: 100%;
    }

    .stat-tile {
        padding: 14px;
    }

    .glass-panel {
        padding: 16px;
    }
}
</style>

<script>
(function initLiveCountdown() {
    const liveItems = document.querySelectorAll('.live-schedule-item');
    if (!liveItems.length) return;

    const serverNowEpoch = <?php echo (int)$now_wib->getTimestamp(); ?>;
    const clientStartMs = Date.now();

    const pad = (num) => String(num).padStart(2, '0');
    const getNowEpoch = () => serverNowEpoch + Math.floor((Date.now() - clientStartMs) / 1000);

    function evaluateStatus(nowEpoch, countdownStart, start, baseEnd, end) {
        if (nowEpoch < countdownStart) {
            return { text: 'MENUNGGU', css: 'status-muted', canAttend: false };
        }
        if (nowEpoch >= countdownStart && nowEpoch < start) {
            const remaining = Math.max(0, start - nowEpoch);
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            return { text: `COUNTDOWN ${pad(mins)}:${pad(secs)}`, css: 'status-info', canAttend: false };
        }
        if (nowEpoch >= start && nowEpoch <= baseEnd) {
            return { text: 'ACTIVE', css: 'status-info', canAttend: true };
        }
        if (nowEpoch > baseEnd && nowEpoch <= end) {
            return { text: 'OVERDUE', css: 'status-warning', canAttend: true };
        }
        return { text: 'ALPA', css: 'status-danger', canAttend: false };
    }

    function tick() {
        const nowEpoch = getNowEpoch();

        liveItems.forEach((item) => {
            const countdownStart = parseInt(item.dataset.countdownStart || '0', 10);
            const start = parseInt(item.dataset.start || '0', 10);
            const baseEnd = parseInt(item.dataset.baseEnd || '0', 10);
            const end = parseInt(item.dataset.end || '0', 10);

            if (!countdownStart || !start || !end) return;

            const result = evaluateStatus(nowEpoch, countdownStart, start, baseEnd, end);

            const statusEl = item.querySelector('.js-live-status');
            if (statusEl) {
                statusEl.classList.remove('status-muted', 'status-info', 'status-warning', 'status-danger', 'status-success');
                statusEl.classList.add(result.css);
                statusEl.textContent = result.text;
            }

            const actionEl = item.querySelector('.js-live-action');
            if (actionEl) {
                actionEl.classList.toggle('d-none', !result.canAttend);
            }
        });
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
