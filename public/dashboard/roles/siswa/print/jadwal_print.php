<?php
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth.php';
require_once '../../../../includes/database.php';
require_once '../../../../includes/database_helper.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    header('Location: ../../../../login.php');
    exit();
}

$db = new Database();
$student_id = (int) ($_SESSION['student_id'] ?? 0);
if ($student_id <= 0) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$sql_student = "
    SELECT s.*, c.class_name, j.name as jurusan_name
    FROM student s
    LEFT JOIN class c ON s.class_id = c.class_id
    LEFT JOIN jurusan j ON s.jurusan_id = j.jurusan_id
    WHERE s.id = ?
";
$stmt = $db->query($sql_student, [$student_id]);
$student_data = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$student_data || empty($student_data['class_id'])) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan atau belum terdaftar di kelas.');
}

$site_stmt = $db->query('SELECT time_tolerance FROM site LIMIT 1');
$site_setting = $site_stmt ? $site_stmt->fetch(PDO::FETCH_ASSOC) : null;
$time_tolerance = isset($site_setting['time_tolerance']) ? (int) $site_setting['time_tolerance'] : 15;
if ($time_tolerance < 0) {
    $time_tolerance = 0;
}

$tz = new DateTimeZone('Asia/Jakarta');
$now_wib = new DateTime('now', $tz);
$now_ts = $now_wib->getTimestamp();

$day_mapping = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu',
];
$day_index_map = [
    'Senin' => 1,
    'Selasa' => 2,
    'Rabu' => 3,
    'Kamis' => 4,
    'Jumat' => 5,
    'Sabtu' => 6,
    'Minggu' => 7,
];

$today_indonesian = $day_mapping[$now_wib->format('l')] ?? '';
$today_date = $now_wib->format('Y-m-d');
$today_label = $now_wib->format('d F Y');

$week_start = (clone $now_wib)->modify('monday this week')->setTime(0, 0, 0);
$reset_time = (clone $week_start)->modify('+5 days')->setTime(15, 0, 0);
if ($now_wib >= $reset_time) {
    $week_start->modify('+7 days');
    $reset_time->modify('+7 days');
}

$dbHelper = new DatabaseHelper($db);
$schedules = $dbHelper->getStudentSchedule($student_id, (int) $student_data['class_id']);

$grouped_schedule = [];
foreach ($schedules as $schedule) {
    $day = (string) ($schedule['day_name'] ?? '-');
    if (!isset($grouped_schedule[$day])) {
        $grouped_schedule[$day] = [];
    }
    $grouped_schedule[$day][] = $schedule;
}

uksort($grouped_schedule, function ($dayA, $dayB) use ($day_index_map) {
    $indexA = $day_index_map[$dayA] ?? 99;
    $indexB = $day_index_map[$dayB] ?? 99;

    if ($indexA === $indexB) {
        return strcmp((string) $dayA, (string) $dayB);
    }

    return $indexA <=> $indexB;
});

$countdown_seconds = 120;

$resolveStatus = function (array $schedule) use (
    $day_index_map,
    $week_start,
    $now_wib,
    $today_date,
    $now_ts,
    $tz,
    $time_tolerance,
    $countdown_seconds
) {
    $attendance_count = isset($schedule['attendance_count']) ? (int) $schedule['attendance_count'] : 0;
    $attendance_is_late = isset($schedule['attendance_is_late']) && $schedule['attendance_is_late'] === 'Y';

    $result = [
        'status_class' => 'status-muted',
        'status_text' => 'MENUNGGU',
        'action_text' => '-',
        'is_alpa' => false,
    ];

    if ($attendance_count > 0) {
        $result['status_class'] = $attendance_is_late ? 'status-overdue' : 'status-success';
        $result['status_text'] = $attendance_is_late ? 'OVERDUE' : 'SUCCESS';
        $result['action_text'] = 'Done';
        return $result;
    }

    $day = $schedule['day_name'] ?? '';
    $day_index = isset($schedule['day_id']) ? (int) $schedule['day_id'] : ($day_index_map[$day] ?? 0);
    $schedule_date_obj = $day_index > 0
        ? (clone $week_start)->modify('+' . ($day_index - 1) . ' days')
        : (clone $now_wib);
    $schedule_date = $schedule_date_obj->format('Y-m-d');

    if ($schedule_date > $today_date) {
        return $result;
    }

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

    if ($now_ts < $countdown_start) {
        return $result;
    }

    if ($now_ts >= $countdown_start && $now_ts < $start_ts) {
        $result['status_class'] = 'status-countdown';
        $result['status_text'] = 'COUNTDOWN';
        return $result;
    }

    if ($now_ts >= $start_ts && $now_ts <= $base_end_ts) {
        $result['status_class'] = 'status-active';
        $result['status_text'] = 'ACTIVE';
        $result['action_text'] = 'Absen';
        return $result;
    }

    if ($now_ts > $base_end_ts && $now_ts <= $end_ts) {
        $result['status_class'] = 'status-overdue';
        $result['status_text'] = 'OVERDUE';
        $result['action_text'] = 'Absen';
        return $result;
    }

    $result['status_class'] = 'status-closed';
    $result['status_text'] = 'CLOSED';
    $result['is_alpa'] = true;
    return $result;
};

$attended_count = 0;
$pending_count = 0;
$alpa_count = 0;
foreach ($schedules as $schedule) {
    $attendance_count = isset($schedule['attendance_count']) ? (int) $schedule['attendance_count'] : 0;
    if ($attendance_count > 0) {
        $attended_count++;
        continue;
    }

    $status = $resolveStatus($schedule);
    if ($status['is_alpa']) {
        $alpa_count++;
    } else {
        $pending_count++;
    }
}

$teachers = array_unique(array_column($schedules, 'teacher_name'));
$teacher_count = count(array_filter($teachers));

$logo_base64 = '';
$logo_path = __DIR__ . '/../../../../assets/images/presenova.png';
if (!is_file($logo_path)) {
    $logo_path = __DIR__ . '/../../../../assets/images/logo-192.png';
}
if (is_file($logo_path)) {
    $logo_base64 = base64_encode(file_get_contents($logo_path));
}

$printed_at = $now_wib->format('d F Y H:i:s') . ' WIB';
$printed_by = trim((string) ($student_data['student_name'] ?? $_SESSION['student_name'] ?? 'Siswa'));
if (!empty($student_data['student_nisn'])) {
    $printed_by .= ' (' . $student_data['student_nisn'] . ')';
}

$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';
$orientation = strtolower((string) ($_GET['orientation'] ?? 'auto'));
if (!in_array($orientation, ['auto', 'portrait', 'landscape'], true)) {
    $orientation = 'auto';
}

$page_size_css = '';
if ($orientation === 'portrait') {
    $page_size_css = '@media print { @page { size: A4 portrait; } }';
} elseif ($orientation === 'landscape') {
    $page_size_css = '@media print { @page { size: A4 landscape; } }';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Pelajaran - Presenova</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../../assets/css/print/jadwal-print.css?v=20260213i">
    <?php if ($page_size_css !== ''): ?>
    <style><?php echo $page_size_css; ?></style>
    <?php endif; ?>
</head>
<body>
    <main class="print-sheet">
        <header class="sheet-header section-keep">
            <div class="brand-area">
                <?php if ($logo_base64 !== ''): ?>
                    <img class="brand-logo" src="data:image/png;base64,<?php echo $logo_base64; ?>" alt="Logo Presenova">
                <?php endif; ?>
                <div class="brand-text">
                    <h1>Jadwal Pelajaran</h1>
                    <p>SMKN 1 Cikarang Selatan</p>
                    <p class="academic-year">Tahun Ajaran 2023/2024</p>
                </div>
            </div>
            <div class="print-meta">
                <div>
                    <span>Printed At</span>
                    <strong><?php echo htmlspecialchars($printed_at, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div>
                    <span>Printed By</span>
                    <strong><?php echo htmlspecialchars($printed_by, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            </div>
        </header>

        <section class="info-strip section-keep">
            <div class="info-item">
                <span>Nama Siswa</span>
                <strong><?php echo htmlspecialchars((string) ($student_data['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="info-item">
                <span>Kelas</span>
                <strong><?php echo htmlspecialchars((string) ($student_data['class_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="info-item">
                <span>Jurusan</span>
                <strong><?php echo htmlspecialchars((string) ($student_data['jurusan_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="info-item">
                <span>Tanggal</span>
                <strong><?php echo htmlspecialchars($today_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </section>

        <section class="table-section">
            <h2>Jadwal Minggu Ini</h2>

            <?php if (!empty($grouped_schedule)): ?>
                <div class="table-wrap">
                    <table class="schedule-table" aria-label="Jadwal Mingguan Siswa">
                        <thead>
                            <tr>
                                <th>Hari</th>
                                <th>Shift</th>
                                <th>Waktu</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <?php foreach ($grouped_schedule as $day => $day_schedules): ?>
                            <tbody class="day-group <?php echo $day === $today_indonesian ? 'is-today' : ''; ?>">
                                <?php foreach ($day_schedules as $index => $schedule): ?>
                                    <?php $status = $resolveStatus($schedule); ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td class="cell-day" rowspan="<?php echo count($day_schedules); ?>">
                                                <?php echo htmlspecialchars((string) $day, ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="cell-shift">
                                            <?php
                                            echo htmlspecialchars(
                                                (string) (
                                                    $schedule['shift']
                                                    ?? $schedule['shift_name']
                                                    ?? $schedule['shift_code']
                                                    ?? '-'
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </td>
                                        <td class="cell-time">
                                            <?php echo date('H:i', strtotime((string) ($schedule['time_in'] ?? '00:00:00'))); ?>
                                            -
                                            <?php echo date('H:i', strtotime((string) ($schedule['time_out'] ?? '00:00:00'))); ?>
                                        </td>
                                        <td class="cell-subject">
                                            <?php echo htmlspecialchars((string) ($schedule['subject'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="cell-teacher">
                                            <strong><?php echo htmlspecialchars((string) ($schedule['teacher_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if (!empty($schedule['teacher_code'])): ?>
                                                <span><?php echo htmlspecialchars((string) $schedule['teacher_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cell-status">
                                            <span class="status-chip <?php echo htmlspecialchars($status['status_class'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($status['status_text'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td class="cell-action">
                                            <?php echo htmlspecialchars($status['action_text'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">Jadwal belum tersedia untuk minggu ini.</p>
            <?php endif; ?>
        </section>

        <section class="summary-strip section-keep">
            <div class="summary-item">
                <span>Total Jadwal</span>
                <strong><?php echo count($schedules); ?></strong>
            </div>
            <div class="summary-item">
                <span>Sudah Absen</span>
                <strong><?php echo $attended_count; ?></strong>
            </div>
            <div class="summary-item">
                <span>Belum Absen</span>
                <strong><?php echo $pending_count; ?></strong>
            </div>
            <div class="summary-item">
                <span>Alpa</span>
                <strong><?php echo $alpa_count; ?></strong>
            </div>
            <div class="summary-item">
                <span>Total Guru</span>
                <strong><?php echo $teacher_count; ?></strong>
            </div>
        </section>

        <footer class="sheet-footer section-keep">
            <div>Presenova - Bringing Back Learning Time</div>
            <div>Printed at <?php echo htmlspecialchars($printed_at, ENT_QUOTES, 'UTF-8'); ?> by <?php echo htmlspecialchars($printed_by, ENT_QUOTES, 'UTF-8'); ?></div>
        </footer>
    </main>

    <?php if ($autoprint): ?>
    <script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 250);
    });
    </script>
    <?php endif; ?>
</body>
</html>
