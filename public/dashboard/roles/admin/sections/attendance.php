<?php
require_once __DIR__ . '/../../../../helpers/jp_time_helper.php';

// Get filter parameters (date range)
$tz = new DateTimeZone('Asia/Jakarta');
$now_wib = new DateTime('now', $tz);
$today_date = $now_wib->format('Y-m-d');

$parse_filter_date = static function ($rawDate, DateTimeZone $timezone, DateTime $fallbackDate): DateTime {
    $value = trim((string)$rawDate);
    if ($value === '') {
        return clone $fallbackDate;
    }

    $acceptedFormats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
    foreach ($acceptedFormats as $format) {
        $parsed = DateTime::createFromFormat('!' . $format, $value, $timezone);
        if ($parsed instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if ($errors === false || (((int)$errors['warning_count'] === 0) && ((int)$errors['error_count'] === 0))) {
                return $parsed;
            }
        }
    }

    try {
        return new DateTime($value, $timezone);
    } catch (Exception $e) {
        return clone $fallbackDate;
    }
};

$filter_date_legacy = $_GET['date'] ?? '';
$filter_date_from = $_GET['date_from'] ?? ($filter_date_legacy !== '' ? $filter_date_legacy : $today_date);
$filter_date_to = $_GET['date_to'] ?? ($filter_date_legacy !== '' ? $filter_date_legacy : $filter_date_from);
$filter_class = trim((string)($_GET['class'] ?? ''));
$filter_status = trim((string)($_GET['status'] ?? ''));

$fallbackDate = new DateTime($today_date, $tz);
$fromObj = $parse_filter_date($filter_date_from, $tz, $fallbackDate);
$toObj = $parse_filter_date($filter_date_to, $tz, $fromObj);
if ($fromObj > $toObj) {
    $tmp = $fromObj;
    $fromObj = $toObj;
    $toObj = $tmp;
}
$filter_date_from = $fromObj->format('Y-m-d');
$filter_date_to = $toObj->format('Y-m-d');
$cycle_start = $filter_date_from . ' 00:00:00';
$cycle_end = $filter_date_to . ' 23:59:59';
$cycle_start_date = $filter_date_from;
$cycle_end_date = $filter_date_to;

$site_stmt = $db->query("SELECT time_tolerance FROM site LIMIT 1");
$site_setting = $site_stmt ? $site_stmt->fetch(PDO::FETCH_ASSOC) : null;
$time_tolerance = isset($site_setting['time_tolerance']) ? (int) $site_setting['time_tolerance'] : 0;
if ($time_tolerance < 0) {
    $time_tolerance = 0;
}

// Get statuses (dipakai juga untuk deteksi filter Alpa dan mapping statistik)
$statuses = $db->query("SELECT * FROM present_status")->fetchAll();
$alpa_status_ids = [];
$status_name_by_id = [];
$present_status_ids = [];
foreach ($statuses as $status_item) {
    $status_id = (string)($status_item['present_id'] ?? '');
    $status_name = strtolower(trim((string)($status_item['present_name'] ?? '')));
    if ($status_id !== '') {
        $status_name_by_id[$status_id] = $status_name;
    }

    if (in_array($status_name, ['hadir', 'sakit', 'izin'], true) && $status_id !== '') {
        $present_status_ids[] = (int)$status_id;
    }

    if ($status_name === 'alpa' || $status_name === 'tidak hadir') {
        if ($status_id !== '') {
            $alpa_status_ids[] = $status_id;
        }
    }
}
$present_status_ids = array_values(array_unique($present_status_ids));
if (empty($present_status_ids)) {
    $present_status_ids = [1, 2, 3];
}
$alpa_present_id = !empty($alpa_status_ids) ? (int)$alpa_status_ids[0] : 4;
$filter_status_normalized = strtolower(trim((string)$filter_status));
$is_alpa_filter = $filter_status !== '' && (
    $filter_status_normalized === 'alpa' ||
    in_array((string)$filter_status, $alpa_status_ids, true)
);

$resolve_status_category = static function (array $row) use ($status_name_by_id): string {
    $status_name = strtolower(trim((string)($row['present_name'] ?? '')));
    if ($status_name === '') {
        $status_id = (string)($row['present_id'] ?? '');
        if ($status_id !== '' && isset($status_name_by_id[$status_id])) {
            $status_name = $status_name_by_id[$status_id];
        }
    }

    if ($status_name === 'tidak hadir') {
        return 'alpa';
    }
    if (in_array($status_name, ['hadir', 'sakit', 'izin', 'alpa'], true)) {
        return $status_name;
    }
    return '';
};

// Base query
$sql = "SELECT p.*, s.student_name, s.student_nisn, c.class_name, 
               ps.present_name, ts.subject,
               ss.schedule_date,
               COALESCE(ss.time_in, sh.time_in) as schedule_time_in,
               COALESCE(ss.time_out, sh.time_out) as schedule_time_out
        FROM presence p
        JOIN student s ON p.student_id = s.id
        JOIN class c ON s.class_id = c.class_id
        JOIN present_status ps ON p.present_id = ps.present_id
        LEFT JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
        LEFT JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
        LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
        WHERE p.presence_date BETWEEN ? AND ?";
        
$params = [$cycle_start, $cycle_end];

// Add filters
if ($filter_class) {
    $sql .= " AND s.class_id = ?";
    $params[] = $filter_class;
}

if ($filter_status) {
    if ($is_alpa_filter) {
        // Alpa tidak tersimpan di tabel presence, ambil dari student_schedule (query terpisah)
        $sql .= " AND 1 = 0";
    } else {
        $sql .= " AND p.present_id = ?";
        $params[] = $filter_status;
    }
}

$sql .= " ORDER BY p.presence_date DESC, p.time_in DESC";

$stmt = $db->query($sql, $params);
$attendances = $stmt->fetchAll();

// Get classes for filter
$classes = $db->query("SELECT * FROM class ORDER BY class_name")->fetchAll();

// Data Alpa (jadwal yang sudah berakhir tapi tidak ada record presence)
$alpa_sql = "SELECT 
                NULL as presence_id,
                ss.student_id,
                s.student_nisn,
                s.student_name,
                c.class_name,
                ss.schedule_date as presence_date,
                COALESCE(ss.time_out, sh.time_out) as time_in,
                NULL as time_out,
                ts.subject,
                {$alpa_present_id} as present_id,
                'Alpa' as present_name,
                'N' as is_late,
                0 as late_time,
                'Tidak hadir (alpa)' as information,
                NULL as latitude_in,
                NULL as longitude_in,
                NULL as distance_in,
                ss.schedule_date,
                COALESCE(ss.time_in, sh.time_in) as schedule_time_in,
                COALESCE(ss.time_out, sh.time_out) as schedule_time_out
             FROM student_schedule ss
             JOIN student s ON ss.student_id = s.id
             JOIN class c ON s.class_id = c.class_id
             JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
             LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
             LEFT JOIN presence p ON p.student_schedule_id = ss.student_schedule_id
             WHERE ss.schedule_date BETWEEN ? AND ?
             AND p.presence_id IS NULL";
$alpa_params = [$cycle_start_date, $cycle_end_date];
if ($filter_class) {
    $alpa_sql .= " AND s.class_id = ?";
    $alpa_params[] = $filter_class;
}
$alpa_sql .= " ORDER BY ss.schedule_date DESC, COALESCE(ss.time_in, sh.time_in) DESC";
$alpa_stmt = $db->query($alpa_sql, $alpa_params);
$alpa_rows_raw = $alpa_stmt ? $alpa_stmt->fetchAll() : [];

$alpa_attendances = [];
foreach ($alpa_rows_raw as $alpa_row) {
    $schedule_date = $alpa_row['schedule_date'] ?? '';
    if (!$schedule_date) {
        continue;
    }
    [$start_dt, $end_dt] = buildScheduleWindow(
        $schedule_date,
        $alpa_row['schedule_time_in'] ?? '00:00:00',
        $alpa_row['schedule_time_out'] ?? '00:00:00',
        $tz,
        0
    );
    if ($now_wib <= $end_dt) {
        continue;
    }
    $alpa_attendances[] = $alpa_row;
}

// Gabungkan data sesuai filter status
if ($is_alpa_filter) {
    $attendances = $alpa_attendances;
} elseif ($filter_status === '') {
    $attendances = array_merge($attendances, $alpa_attendances);
}

usort($attendances, function($a, $b) {
    $aDate = (string)($a['schedule_date'] ?? $a['presence_date'] ?? '1970-01-01');
    $bDate = (string)($b['schedule_date'] ?? $b['presence_date'] ?? '1970-01-01');
    $aTime = (string)($a['time_in'] ?? '00:00:00');
    $bTime = (string)($b['time_in'] ?? '00:00:00');
    $aTs = strtotime(substr($aDate, 0, 10) . ' ' . $aTime);
    $bTs = strtotime(substr($bDate, 0, 10) . ' ' . $bTime);
    return $bTs <=> $aTs;
});

// Statistik berbasis hasil filter aktual
$stats = [
    'total' => count($attendances),
    'present' => 0,
    'sick' => 0,
    'permission' => 0,
    'late' => 0
];
foreach ($attendances as $row) {
    $status_category = $resolve_status_category($row);
    if ($status_category === 'hadir') {
        $stats['present']++;
        if (($row['is_late'] ?? 'N') === 'Y') {
            $stats['late']++;
        }
    } elseif ($status_category === 'sakit') {
        $stats['sick']++;
    } elseif ($status_category === 'izin') {
        $stats['permission']++;
    }
}
$alpa_total = count(array_filter($attendances, static function ($row) use ($resolve_status_category) {
    return $resolve_status_category((array)$row) === 'alpa';
}));

// Chart data: monthly attendance (Mon-Sat) based on real records
$chart_labels = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$chart_present = array_fill(0, 6, 0);
$chart_absent = array_fill(0, 6, 0);

$chart_start = $cycle_start_date;
$chart_end = $cycle_end_date;
$present_status_ids_sql = implode(',', array_map('intval', $present_status_ids));

$chart_sql = "SELECT 
                DAYOFWEEK(ss.schedule_date) as day_idx,
                COUNT(ss.student_schedule_id) as total_sched,
                SUM(CASE WHEN p.present_id IN ($present_status_ids_sql) THEN 1 ELSE 0 END) as present
              FROM student_schedule ss
              LEFT JOIN presence p ON p.student_schedule_id = ss.student_schedule_id
              JOIN student s ON ss.student_id = s.id
              WHERE ss.schedule_date BETWEEN ? AND ?
              AND ss.schedule_date <= CURDATE()";

$chart_params = [$chart_start, $chart_end];

if ($filter_class) {
    $chart_sql .= " AND s.class_id = ?";
    $chart_params[] = $filter_class;
}

$chart_sql .= " GROUP BY DAYOFWEEK(ss.schedule_date)";

$chart_rows = $db->query($chart_sql, $chart_params)->fetchAll();

foreach ($chart_rows as $row) {
    $day_idx = (int)($row['day_idx'] ?? 0); // 1=Sunday ... 7=Saturday
    $present = (int)($row['present'] ?? 0);
    $total_sched = (int)($row['total_sched'] ?? 0);
    $absent = max(0, $total_sched - $present);

    // Map Monday(2) -> 0, Tuesday(3) -> 1, ..., Saturday(7) -> 5
    if ($day_idx >= 2 && $day_idx <= 7) {
        $pos = $day_idx - 2;
        if ($pos >= 0 && $pos < 6) {
            $chart_present[$pos] = $present;
            $chart_absent[$pos] = $absent;
        }
    }
}

$chart_max = max(array_merge($chart_present, $chart_absent));
$chart_suggested_max = $chart_max > 0 ? $chart_max : 1;

// Dynamic title for statistik based on selected date/range
$bulan_id = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$format_tanggal_id = static function (DateTime $dateObj, array $bulanMap, bool $withYear = true): string {
    $day = (int)$dateObj->format('j');
    $month = (int)$dateObj->format('n');
    $year = (int)$dateObj->format('Y');
    $bulan = $bulanMap[$month] ?? $dateObj->format('F');
    return $withYear ? ($day . ' ' . $bulan . ' ' . $year) : ($day . ' ' . $bulan);
};

$is_same_day_range = ($filter_date_from === $filter_date_to);
$is_today_default = $is_same_day_range && ($filter_date_from === $today_date);

if ($is_today_default) {
    $statistik_title = 'Statistik Absen Hari Ini';
} elseif ($is_same_day_range) {
    $statistik_title = 'Statistik Absen ' . $format_tanggal_id($fromObj, $bulan_id, true);
} else {
    $sameYear = $fromObj->format('Y') === $toObj->format('Y');
    $startLabel = $format_tanggal_id($fromObj, $bulan_id, !$sameYear);
    $endLabel = $format_tanggal_id($toObj, $bulan_id, true);
    $statistik_title = 'Statistik Absen ' . $startLabel . ' - ' . $endLabel;
}

// Handle export (Excel-friendly)
if (isset($_GET['export'])) {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/vnd.ms-excel');
    $fileRangeLabel = $filter_date_from === $filter_date_to
        ? $filter_date_from
        : ($filter_date_from . '_sd_' . $filter_date_to);
    header('Content-Disposition: attachment; filename="absensi_' . $fileRangeLabel . '.xls"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['NISN', 'Nama', 'Kelas', 'Tanggal', 'Jam', 'Status', 'Keterlambatan', 'Keterangan']);
    
    foreach($attendances as $attendance) {
        fputcsv($output, [
            $attendance['student_nisn'],
            $attendance['student_name'],
            $attendance['class_name'],
            $attendance['presence_date'],
            $attendance['time_in'],
            $attendance['present_name'],
            $attendance['is_late'] == 'Y' ? $attendance['late_time'] . ' menit' : '0',
            $attendance['information']
        ]);
    }
    
    fclose($output);
    exit();
}

// Handle print
if (isset($_GET['print'])) {
    if (ob_get_length()) {
        ob_clean();
    }
    $printRangeLabel = $filter_date_from === $filter_date_to
        ? $filter_date_from
        : ($filter_date_from . ' s/d ' . $filter_date_to);
    $printTitle = 'absensi - presenova';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($printTitle, ENT_QUOTES, 'UTF-8'); ?></title>
        <style>
            @page { margin: 12mm; }
            body { font-family: Arial, sans-serif; font-size: 12px; color: #111827; }
            h2 { margin: 0 0 6px; font-size: 16px; text-align: center; }
            .meta { text-align: center; font-size: 11px; color: #4b5563; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
            th { background: #f3f4f6; font-weight: 600; }
        </style>
    </head>
    <body>
        <h2>Data Absensi</h2>
        <div class="meta">Periode: <?php echo htmlspecialchars($cycle_start_date . ' s/d ' . $cycle_end_date); ?></div>
        <table>
            <thead>
                <tr>
                    <th>NISN</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Mata Pelajaran</th>
                    <th>Status</th>
                    <th>Keterlambatan</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($attendances as $attendance): ?>
                <tr>
                    <td><?php echo htmlspecialchars($attendance['student_nisn']); ?></td>
                    <td><?php echo htmlspecialchars($attendance['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($attendance['class_name']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($attendance['presence_date'])); ?></td>
                    <td><?php echo date('H:i', strtotime($attendance['time_in'])); ?></td>
                    <td><?php echo htmlspecialchars($attendance['subject'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($attendance['present_name'] ?? '-'); ?></td>
                    <td><?php echo ($attendance['is_late'] == 'Y') ? ((int)$attendance['late_time'] . ' menit') : '0'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    exit();
}
?>

<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="bi bi-clipboard-check"></i> Data Absensi</h5>
        <div class="d-flex gap-2">
            <a href="?table=attendance&export=excel&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>&class=<?php echo urlencode($filter_class); ?>&status=<?php echo urlencode($filter_status); ?>" 
               class="btn btn-success" data-no-loading="1">
                <i class="bi bi-download"></i> Export Excel
            </a>
            <a href="?table=attendance&print=true&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>&class=<?php echo urlencode($filter_class); ?>&status=<?php echo urlencode($filter_status); ?>" 
               class="btn btn-primary" target="_blank" data-no-loading="1">
                <i class="bi bi-printer"></i> Print
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-2">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" class="form-control" id="filterDateFrom" value="<?php echo htmlspecialchars($filter_date_from); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Tanggal Akhir</label>
            <input type="date" class="form-control" id="filterDateTo" value="<?php echo htmlspecialchars($filter_date_to); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Kelas</label>
            <select class="form-select" id="filterClass">
                <option value="">Semua Kelas</option>
                <?php foreach($classes as $class): ?>
                <option value="<?php echo $class['class_id']; ?>" <?php echo $filter_class == $class['class_id'] ? 'selected' : ''; ?>>
                    <?php echo $class['class_name']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" id="filterStatus">
                <option value="">Semua Status</option>
                <?php foreach($statuses as $status): ?>
                    <?php
                        $status_name = strtolower(trim((string)($status['present_name'] ?? '')));
                        $is_alpa_status = ($status_name === 'alpa' || $status_name === 'tidak hadir');
                    ?>
                    <?php if (!$is_alpa_status): ?>
                    <option value="<?php echo $status['present_id']; ?>" <?php echo ((string)$filter_status === (string)$status['present_id']) ? 'selected' : ''; ?>>
                        <?php echo $status['present_name']; ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
                <option value="alpa" <?php echo $is_alpa_filter ? 'selected' : ''; ?>>Alpa</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100" onclick="applyFilters()">
                <i class="bi bi-filter"></i> Filter
            </button>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="attendance-stats-grid mb-4">
        <div class="attendance-stat-card stat-total">
            <div class="attendance-stat-icon"><i class="bi bi-clipboard-check"></i></div>
            <div class="attendance-stat-content">
                <h5 id="statTotal"><?php echo $stats['total'] ?? 0; ?></h5>
                <p>Total Absen</p>
            </div>
        </div>
        <div class="attendance-stat-card stat-present">
            <div class="attendance-stat-icon"><i class="bi bi-check-circle"></i></div>
            <div class="attendance-stat-content">
                <h5 id="statPresent"><?php echo $stats['present'] ?? 0; ?></h5>
                <p>Hadir</p>
            </div>
        </div>
        <div class="attendance-stat-card stat-sick">
            <div class="attendance-stat-icon"><i class="bi bi-emoji-frown"></i></div>
            <div class="attendance-stat-content">
                <h5 id="statSick"><?php echo $stats['sick'] ?? 0; ?></h5>
                <p>Sakit</p>
            </div>
        </div>
        <div class="attendance-stat-card stat-izin">
            <div class="attendance-stat-icon"><i class="bi bi-person-check"></i></div>
            <div class="attendance-stat-content">
                <h5 id="statPermission"><?php echo $stats['permission'] ?? 0; ?></h5>
                <p>Izin</p>
            </div>
        </div>
        <div class="attendance-stat-card stat-alpa">
            <div class="attendance-stat-icon"><i class="bi bi-person-x"></i></div>
            <div class="attendance-stat-content">
                <h5 id="statAlpa"><?php echo $alpa_total; ?></h5>
                <p>Alpa</p>
            </div>
        </div>
    </div>
    
    <!-- Attendance Table -->
    <div class="table-responsive">
        <table class="table table-hover data-table">
            <thead>
                <tr>
                    <th>NISN</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Mata Pelajaran</th>
                    <th>Status</th>
                    <th>Keterlambatan</th>
                    <th>Lokasi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($attendances as $attendance): ?>
                <?php
                    $attendanceStatusCategory = $resolve_status_category($attendance);
                    $isAlpaRow = ($attendanceStatusCategory === 'alpa');
                    $attendance_schedule_date = $attendance['schedule_date'] ?? null;
                    if (!$attendance_schedule_date && !empty($attendance['presence_date'])) {
                        $attendance_schedule_date = date('Y-m-d', strtotime($attendance['presence_date']));
                    }
                    $canDelete = false;
                    if (!$isAlpaRow && $attendance_schedule_date && !empty($attendance['schedule_time_in']) && !empty($attendance['schedule_time_out'])) {
                        [$start_dt, $end_dt] = buildScheduleWindow(
                            $attendance_schedule_date,
                            $attendance['schedule_time_in'],
                            $attendance['schedule_time_out'],
                            $tz,
                            0
                        );
                        if ($attendance_schedule_date === $today_date && $now_wib >= $start_dt && $now_wib <= $end_dt) {
                            $canDelete = true;
                        }
                    }
                ?>
                <tr>
                    <td><?php echo $attendance['student_nisn']; ?></td>
                    <td><?php echo $attendance['student_name']; ?></td>
                    <td><?php echo $attendance['class_name']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($attendance['presence_date'])); ?></td>
                    <td><?php echo date('H:i', strtotime($attendance['time_in'])); ?></td>
                    <td><?php echo $attendance['subject'] ?? '-'; ?></td>
                    <td>
                        <?php if($attendanceStatusCategory === 'hadir'): ?>
                            <span class="badge <?php echo $attendance['is_late'] == 'Y' ? 'bg-warning' : 'bg-success'; ?>">
                                <?php echo $attendance['is_late'] == 'Y' ? 'Terlambat' : $attendance['present_name']; ?>
                            </span>
                        <?php elseif($attendanceStatusCategory === 'sakit'): ?>
                            <span class="badge bg-info"><?php echo $attendance['present_name']; ?></span>
                        <?php elseif($attendanceStatusCategory === 'izin'): ?>
                            <span class="badge bg-primary"><?php echo $attendance['present_name']; ?></span>
                        <?php elseif($isAlpaRow): ?>
                            <span class="badge bg-danger">Alpa</span>
                        <?php else: ?>
                            <span class="badge bg-primary"><?php echo $attendance['present_name']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($isAlpaRow): ?>
                            <span class="text-danger">Tidak hadir</span>
                        <?php elseif($attendanceStatusCategory === 'hadir' && $attendance['is_late'] == 'Y'): ?>
                            <span class="text-warning">
                                <i class="bi bi-clock-history"></i> <?php echo $attendance['late_time']; ?> menit
                            </span>
                        <?php else: ?>
                            <span class="text-muted">Tepat waktu</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($attendance['latitude_in']): ?>
                            <button class="btn btn-sm btn-outline-primary view-location-btn"
                                    data-lat="<?php echo $attendance['latitude_in']; ?>"
                                    data-lng="<?php echo $attendance['longitude_in']; ?>"
                                    data-distance="<?php echo $attendance['distance_in'] ?? ''; ?>">
                                <i class="bi bi-geo-alt"></i> Lihat
                            </button>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($attendance['presence_id'])): ?>
                            <button class="btn btn-sm btn-info view-details-btn"
                                    data-id="<?php echo $attendance['presence_id']; ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($canDelete): ?>
                                <a href="?table=attendance&action=delete&id=<?php echo $attendance['presence_id']; ?>" 
                                   class="btn btn-sm btn-danger" onclick="return confirm('Hapus data absensi ini?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Chart Section -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="table-container">
                <h5><i class="bi bi-bar-chart"></i> <?php echo htmlspecialchars($statistik_title); ?></h5>
                <canvas id="attendanceChart" height="200"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-container">
                <h5><i class="bi bi-calendar-month"></i> Rekap Harian</h5>
                <div id="dailySummary">
                    <?php
                    // Weekly summary (Mon-Sat 15:00 WIB)
                    $summary_sql = "SELECT 
                                        ss.schedule_date as date,
                                        COUNT(ss.student_schedule_id) as total,
                                        SUM(CASE WHEN p.present_id IN ($present_status_ids_sql) THEN 1 ELSE 0 END) as present
                                    FROM student_schedule ss
                                    LEFT JOIN presence p ON p.student_schedule_id = ss.student_schedule_id
                                    JOIN student s ON ss.student_id = s.id
                                    WHERE ss.schedule_date BETWEEN ? AND ?
                                    AND ss.schedule_date <= CURDATE()";
                    $summary_params = [$cycle_start_date, $cycle_end_date];
                    if ($filter_class) {
                        $summary_sql .= " AND s.class_id = ?";
                        $summary_params[] = $filter_class;
                    }
                    $summary_sql .= " GROUP BY ss.schedule_date ORDER BY ss.schedule_date DESC";

                    $summary_stmt = $db->query($summary_sql, $summary_params);
                    $summary = $summary_stmt->fetchAll();
                    ?>
                    
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Total</th>
                                <th>Hadir</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($summary as $day): ?>
                            <tr>
                                <td><?php echo date('d/m', strtotime($day['date'])); ?></td>
                                <td><?php echo $day['total']; ?></td>
                                <td><?php echo $day['present']; ?></td>
                                <td>
                                    <?php 
                                    $percentage = $day['total'] > 0 ? round(($day['present'] / $day['total']) * 100) : 0;
                                    $color = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo $percentage; ?>%</span>
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

<script>
function refreshAttendanceStats() {
    const dateFrom = $('#filterDateFrom').val();
    const dateTo = $('#filterDateTo').val();
    const cls = $('#filterClass').val();
    const status = $('#filterStatus').val();

    $.ajax({
        url: 'ajax/get_attendance_stats.php',
        method: 'GET',
        dataType: 'json',
        data: { date_from: dateFrom, date_to: dateTo, class: cls, status: status },
        success: function(resp) {
            if (resp && resp.success && resp.data) {
                $('#statTotal').text(resp.data.total);
                $('#statPresent').text(resp.data.present);
                $('#statSick').text(resp.data.sick);
                $('#statPermission').text(resp.data.permission);
                $('#statAlpa').text(resp.data.alpa);
            }
        }
    });
}

$(document).ready(function() {
    // refresh every 30 seconds
    setInterval(refreshAttendanceStats, 30000);
});
</script>

<!-- Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lokasi Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="map" style="height: 400px; border-radius: 8px;"></div>
                <div class="mt-3">
                    <p class="mb-1"><strong>Koordinat:</strong> <span id="coordinates"></span></p>
                    <p class="mb-0"><strong>Jarak dari sekolah:</strong> <span id="distance"></span> meter</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content attendance-details-modal">
            <div class="modal-header">
                <h5 class="modal-title">Detail Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body attendance-details-body" id="attendanceDetails">
                Loading...
            </div>
        </div>
    </div>
</div>



<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const SATELLITE_TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
let attendanceMap;
let attendanceMarker;

function ensureAttendanceMap() {
    if (attendanceMap) {
        return;
    }
    attendanceMap = L.map('map', {
        zoomControl: true,
        attributionControl: false,
        scrollWheelZoom: true
    });
    L.tileLayer(SATELLITE_TILE_URL, { maxZoom: 19, minZoom: 15 }).addTo(attendanceMap);
}

function applyFilters() {
    const dateFrom = $('#filterDateFrom').val();
    const dateTo = $('#filterDateTo').val();
    const classId = $('#filterClass').val();
    const status = $('#filterStatus').val();
    
    let url = '?table=attendance';
    
    if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
    if (dateTo) url += '&date_to=' + encodeURIComponent(dateTo);
    if (classId) url += '&class=' + encodeURIComponent(classId);
    if (status) url += '&status=' + encodeURIComponent(status);
    
    window.location.href = url;
}

$(document).ready(function() {
    // Initialize chart
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'Hadir',
                data: <?php echo json_encode($chart_present); ?>,
                backgroundColor: '#28a745'
            }, {
                label: 'Tidak Hadir',
                data: <?php echo json_encode($chart_absent); ?>,
                backgroundColor: '#dc3545'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: <?php echo (int)$chart_suggested_max; ?>
                }
            }
        }
    });
    
    // View location (Leaflet - student coordinates)
    $('.view-location-btn').click(function() {
        const lat = parseFloat($(this).data('lat'));
        const lng = parseFloat($(this).data('lng'));
        const distance = $(this).data('distance');

        if (!lat || !lng) {
            $('#map').html('<div class="alert alert-warning m-3 text-center">Lokasi tidak tersedia.</div>');
            $('#locationModal').modal('show');
            return;
        }

        $('#coordinates').text(lat.toFixed(6) + ', ' + lng.toFixed(6));
        if (distance !== undefined && distance !== null && distance !== '') {
            $('#distance').text(Math.round(parseFloat(distance)));
        } else {
            $('#distance').text('-');
        }

        ensureAttendanceMap();
        if (attendanceMarker) {
            attendanceMarker.setLatLng([lat, lng]);
        } else {
            attendanceMarker = L.marker([lat, lng]).addTo(attendanceMap);
        }
        attendanceMap.setView([lat, lng], 18);
        $('#locationModal').modal('show');
        setTimeout(function() {
            attendanceMap.invalidateSize();
        }, 200);
    });
    
    // View details
    $('.view-details-btn').click(function() {
        const attendanceId = $(this).data('id');

        $('#attendanceDetails').html('<div class="attendance-detail-empty">Memuat detail absensi...</div>');
        
        $.ajax({
            url: 'ajax/get_attendance_details.php',
            method: 'POST',
            data: { id: attendanceId },
            success: function(response) {
                $('#attendanceDetails').html(response);
                $('#detailsModal').modal('show');
            },
            error: function() {
                $('#attendanceDetails').html('<div class="attendance-detail-empty">Gagal memuat detail absensi.</div>');
                $('#detailsModal').modal('show');
            }
        });
    });
});

</script>
