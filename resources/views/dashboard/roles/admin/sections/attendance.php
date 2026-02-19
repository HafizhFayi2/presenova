<?php

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

$filter_date_single = $_GET['date'] ?? '';
$filter_date_from = $_GET['date_from'] ?? ($filter_date_single !== '' ? $filter_date_single : $today_date);
$filter_date_to = $_GET['date_to'] ?? ($filter_date_single !== '' ? $filter_date_single : $filter_date_from);
$filter_class = trim((string)($_GET['class'] ?? ''));
$filter_status = trim((string)($_GET['status'] ?? ''));
$chart_mode = strtolower(trim((string)($_GET['chart_mode'] ?? 'line')));
if (!in_array($chart_mode, ['line', 'bar'], true)) {
    $chart_mode = 'line';
}

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

// Daily recap and chart source data (by actual date, not weekday).
// Rule:
// - If schedule window not closed yet -> "Menunggu", not counted as final alpa.
// - Chart only uses final (closed) dates so admin does not see premature "Tidak Hadir".
$daily_schedule_sql = "SELECT
                        ss.student_schedule_id,
                        ss.schedule_date,
                        COALESCE(ss.time_in, sh.time_in) as schedule_time_in,
                        COALESCE(ss.time_out, sh.time_out) as schedule_time_out,
                        p.presence_id
                    FROM student_schedule ss
                    JOIN student s ON ss.student_id = s.id
                    JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
                    LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
                    LEFT JOIN presence p ON p.student_schedule_id = ss.student_schedule_id
                    WHERE ss.schedule_date BETWEEN ? AND ?";
$daily_schedule_params = [$cycle_start_date, $cycle_end_date];
if ($filter_class) {
    $daily_schedule_sql .= " AND s.class_id = ?";
    $daily_schedule_params[] = $filter_class;
}
$daily_schedule_sql .= " ORDER BY ss.schedule_date ASC, COALESCE(ss.time_in, sh.time_in) ASC";
$daily_schedule_stmt = $db->query($daily_schedule_sql, $daily_schedule_params);
$daily_schedule_rows = $daily_schedule_stmt ? $daily_schedule_stmt->fetchAll() : [];

$daily_buckets = [];
foreach ($daily_schedule_rows as $schedule_row) {
    $schedule_date = trim((string)($schedule_row['schedule_date'] ?? ''));
    if ($schedule_date === '') {
        continue;
    }

    if (!isset($daily_buckets[$schedule_date])) {
        $daily_buckets[$schedule_date] = [
            'date' => $schedule_date,
            'total' => 0,
            'recorded' => 0,
            'closed_total' => 0,
            'closed_recorded' => 0,
            'pending' => 0,
        ];
    }

    $daily_buckets[$schedule_date]['total']++;
    $has_presence = !empty($schedule_row['presence_id']);
    if ($has_presence) {
        $daily_buckets[$schedule_date]['recorded']++;
    }

    [, $schedule_end_dt] = buildScheduleWindow(
        $schedule_date,
        (string)($schedule_row['schedule_time_in'] ?? '00:00:00'),
        (string)($schedule_row['schedule_time_out'] ?? '00:00:00'),
        $tz,
        0
    );

    if ($now_wib > $schedule_end_dt) {
        $daily_buckets[$schedule_date]['closed_total']++;
        if ($has_presence) {
            $daily_buckets[$schedule_date]['closed_recorded']++;
        }
    } else {
        $daily_buckets[$schedule_date]['pending']++;
    }
}

$daily_summary_rows = [];
$chart_series_rows = [];
if (!empty($daily_buckets)) {
    krsort($daily_buckets); // newest first for recap table
    foreach ($daily_buckets as $daily_bucket) {
        $total = (int)($daily_bucket['total'] ?? 0);
        $recorded = (int)($daily_bucket['recorded'] ?? 0);
        $closed_total = (int)($daily_bucket['closed_total'] ?? 0);
        $closed_recorded = (int)($daily_bucket['closed_recorded'] ?? 0);
        $pending = (int)($daily_bucket['pending'] ?? 0);
        $final_absent = max(0, $closed_total - $closed_recorded);

        $is_waiting = $pending > 0 || ($closed_total <= 0 && $total > 0);
        $status_key = $is_waiting ? 'menunggu' : 'closed';
        $final_percentage = $closed_total > 0 ? (int)round(($closed_recorded / $closed_total) * 100) : 0;

        $daily_row = [
            'date' => (string)$daily_bucket['date'],
            'total' => $total,
            'recorded' => $recorded,
            'closed_total' => $closed_total,
            'closed_recorded' => $closed_recorded,
            'final_absent' => $final_absent,
            'pending' => $pending,
            'status_key' => $status_key,
            'status_label' => $is_waiting ? 'Menunggu' : 'Final',
            'final_percentage' => $final_percentage,
        ];
        $daily_summary_rows[] = $daily_row;

        if (!$is_waiting && $closed_total > 0) {
            $chart_series_rows[] = [
                'date' => (string)$daily_bucket['date'],
                'present' => $closed_recorded,
                'absent' => $final_absent,
                'total' => $closed_total,
                'percentage' => $final_percentage,
            ];
        }
    }
}

// Chart payload sorted ascending by date.
usort($chart_series_rows, static function ($a, $b) {
    return strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
});

$chart_labels = [];
$chart_present = [];
$chart_absent = [];
foreach ($chart_series_rows as $series_row) {
    $chart_labels[] = date('d/m', strtotime((string)$series_row['date']));
    $chart_present[] = (int)($series_row['present'] ?? 0);
    $chart_absent[] = (int)($series_row['absent'] ?? 0);
}

$chart_max = 0;
if (!empty($chart_present) || !empty($chart_absent)) {
    $chart_max = max(array_merge($chart_present, $chart_absent));
}
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

// Shared presentation payload for report output (print/excel)
$printed_at = $now_wib->format('d F Y H:i:s') . ' WIB';
$printed_by = trim((string)($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin'));
if ($printed_by === '') {
    $printed_by = 'Admin';
}

$selected_class_label = 'Semua Kelas';
if ($filter_class !== '') {
    foreach ($classes as $class_item) {
        if ((string)($class_item['class_id'] ?? '') === (string)$filter_class) {
            $selected_class_label = (string)($class_item['class_name'] ?? $selected_class_label);
            break;
        }
    }
}

$export_class_detail_label = $selected_class_label;
if ($filter_class === '') {
    $allClassNames = [];
    foreach ($classes as $class_item) {
        $className = trim((string)($class_item['class_name'] ?? ''));
        if ($className !== '') {
            $allClassNames[] = $className;
        }
    }
    $allClassNames = array_values(array_unique($allClassNames));
    if (!empty($allClassNames)) {
        $export_class_detail_label = 'Semua Kelas (' . implode(', ', $allClassNames) . ')';
    }
}

$selected_status_label = 'Semua Status';
if ($is_alpa_filter) {
    $selected_status_label = 'Alpa';
} elseif ($filter_status !== '') {
    foreach ($statuses as $status_item) {
        if ((string)($status_item['present_id'] ?? '') === (string)$filter_status) {
            $selected_status_label = (string)($status_item['present_name'] ?? $selected_status_label);
            break;
        }
    }
}
$chart_mode_label = $chart_mode === 'bar' ? 'Grafik Batang' : 'Grafik Garis';

$format_report_attendance = static function (array $attendance) use ($resolve_status_category): array {
    $status_category = $resolve_status_category($attendance);
    $present_name = trim((string)($attendance['present_name'] ?? '-'));
    $status_label = $present_name !== '' ? $present_name : '-';
    $status_class = 'status-neutral';
    $row_class = 'row-neutral';
    $status_bg = '#e2e8f0';
    $status_text = '#334155';

    if ($status_category === 'hadir') {
        if (($attendance['is_late'] ?? 'N') === 'Y') {
            $status_label = 'Terlambat';
            $status_class = 'status-late';
            $row_class = 'row-late';
            $status_bg = '#fde68a';
            $status_text = '#92400e';
        } else {
            $status_label = 'Hadir';
            $status_class = 'status-hadir';
            $row_class = 'row-hadir';
            $status_bg = '#bbf7d0';
            $status_text = '#166534';
        }
    } elseif ($status_category === 'sakit') {
        $status_label = 'Sakit';
        $status_class = 'status-sakit';
        $row_class = 'row-sakit';
        $status_bg = '#fed7aa';
        $status_text = '#9a3412';
    } elseif ($status_category === 'izin') {
        $status_label = 'Izin';
        $status_class = 'status-izin';
        $row_class = 'row-izin';
        $status_bg = '#bae6fd';
        $status_text = '#0e7490';
    } elseif ($status_category === 'alpa') {
        $status_label = 'Alpa';
        $status_class = 'status-alpa';
        $row_class = 'row-alpa';
        $status_bg = '#fecaca';
        $status_text = '#991b1b';
    }

    $late_label = '0 menit';
    if ($status_category === 'alpa') {
        $late_label = 'Tidak hadir';
    } elseif ($status_category === 'hadir' && ($attendance['is_late'] ?? 'N') === 'Y') {
        $late_label = ((int)($attendance['late_time'] ?? 0)) . ' menit';
    }

    $location_label = '-';
    if (!empty($attendance['latitude_in']) && !empty($attendance['longitude_in'])) {
        $location_label = number_format((float)$attendance['latitude_in'], 6, '.', '') . ', ' .
            number_format((float)$attendance['longitude_in'], 6, '.', '');
    }

    return [
        'status_label' => $status_label,
        'status_class' => $status_class,
        'row_class' => $row_class,
        'late_label' => $late_label,
        'location_label' => $location_label,
        'status_bg' => $status_bg,
        'status_text' => $status_text,
    ];
};

$logo_base64 = '';
$logo_path = public_path('assets/images/presenova.png');
if (!is_file($logo_path)) {
    $logo_path = public_path('assets/images/logo-192.png');
}
if (is_file($logo_path)) {
    $logo_base64 = base64_encode((string)file_get_contents($logo_path));
}

// Handle export (native XLSX without extension warning)
if (isset($_GET['export'])) {
    if (function_exists('clear_output_buffers_for_binary_download')) {
        clear_output_buffers_for_binary_download();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    $autoload_path = base_path('vendor/autoload.php');
    if (!is_file($autoload_path)) {
        http_response_code(500);
        echo 'Dependency autoload tidak ditemukan. Jalankan composer install.';
        exit();
    }
    if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
        http_response_code(500);
        echo 'Dependency PhpSpreadsheet tidak ditemukan. Jalankan composer install.';
        exit();
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Absensi');

    $spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);
    // Auto row height supaya teks panjang tidak terpotong.
    $sheet->getDefaultRowDimension()->setRowHeight(-1);

    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'C7D9EA'],
            ],
        ],
    ];

    $metaLabelStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9F2FB'],
        ],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ];
    $metaValueStyle = [
        'font' => ['color' => ['rgb' => '0F172A']],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9F2FB'],
        ],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ];
    $metaValueBoldStyle = $metaValueStyle;
    $metaValueBoldStyle['font']['bold'] = true;

    $summaryStyles = [
        'total' => [
            'font' => ['bold' => true, 'color' => ['rgb' => '1E40AF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ],
        'hadir' => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '22C55E']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ],
        'sakit' => [
            'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDE68A']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ],
        'izin' => [
            'font' => ['bold' => true, 'color' => ['rgb' => '0E7490']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BAE6FD']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ],
        'alpa' => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EF4444']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ],
    ];

    $tableHeaderStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '123E68'],
        ],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ];

    $dataTopStyle = [
        'alignment' => [
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            'wrapText' => true,
        ],
    ];
    $dataMidStyle = [
        'alignment' => [
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            'wrapText' => true,
        ],
    ];

    $rowPaletteMap = [
        'status-hadir' => ['fill' => 'DCFCE7', 'font' => '14532D'],
        'status-late' => ['fill' => 'FEF3C7', 'font' => '78350F'],
        'status-sakit' => ['fill' => 'FFEDD5', 'font' => '9A3412'],
        'status-izin' => ['fill' => 'CFFAFE', 'font' => '155E75'],
        'status-alpa' => ['fill' => 'FEE2E2', 'font' => '7F1D1D'],
        'status-neutral' => ['fill' => 'FFFFFF', 'font' => '0F172A'],
    ];

    // Header info rows.
    $sheet->setCellValue('A1', 'Periode');
    $sheet->setCellValue('B1', $cycle_start_date . ' s/d ' . $cycle_end_date);
    $sheet->setCellValue('F1', 'Kelas');
    $sheet->setCellValue('G1', $export_class_detail_label);
    $sheet->mergeCells('B1:E1');
    $sheet->mergeCells('G1:J1');

    $sheet->setCellValue('A2', 'Status');
    $sheet->setCellValue('B2', $selected_status_label . ' | ' . $chart_mode_label);
    $sheet->setCellValue('F2', 'Printed');
    $sheet->setCellValue('G2', $printed_at . ' oleh ' . $printed_by);
    $sheet->mergeCells('B2:E2');
    $sheet->mergeCells('G2:J2');

    $sheet->setCellValue('A3', 'Total: ' . (int)($stats['total'] ?? 0));
    $sheet->setCellValue('C3', 'Hadir: ' . (int)($stats['present'] ?? 0));
    $sheet->setCellValue('E3', 'Sakit: ' . (int)($stats['sick'] ?? 0));
    $sheet->setCellValue('G3', 'Izin: ' . (int)($stats['permission'] ?? 0));
    $sheet->setCellValue('I3', 'Alpa: ' . (int)$alpa_total);
    $sheet->mergeCells('A3:B3');
    $sheet->mergeCells('C3:D3');
    $sheet->mergeCells('E3:F3');
    $sheet->mergeCells('G3:H3');
    $sheet->mergeCells('I3:J3');

    $headers = ['NISN', 'Nama', 'Kelas', 'Tanggal', 'Jam', 'Mata Pelajaran', 'Status', 'Keterlambatan', 'Lokasi', 'Keterangan'];
    foreach ($headers as $idx => $headerText) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
        $sheet->setCellValue($columnLetter . '4', $headerText);
    }

    $columnOrder = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    $columnMinWidth = [
        'A' => 10, 'B' => 14, 'C' => 12, 'D' => 12, 'E' => 8,
        'F' => 14, 'G' => 10, 'H' => 14, 'I' => 20, 'J' => 24,
    ];
    $columnMaxWidth = [
        'A' => 18, 'B' => 28, 'C' => 20, 'D' => 16, 'E' => 10,
        'F' => 28, 'G' => 14, 'H' => 18, 'I' => 30, 'J' => 64,
    ];
    $columnMaxTextLength = [];
    $stringLength = static function ($value): int {
        $text = trim((string)$value);
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    };
    foreach ($columnOrder as $idx => $col) {
        $columnMaxTextLength[$col] = $stringLength($headers[$idx] ?? '');
    }

    $sheet->getStyle('A1:A2')->applyFromArray($metaLabelStyle);
    $sheet->getStyle('F1:F2')->applyFromArray($metaLabelStyle);
    $sheet->getStyle('B1:E1')->applyFromArray($metaValueBoldStyle);
    $sheet->getStyle('G1:J1')->applyFromArray($metaValueStyle);
    $sheet->getStyle('B2:E2')->applyFromArray($metaValueStyle);
    $sheet->getStyle('G2:J2')->applyFromArray($metaValueBoldStyle);

    $sheet->getStyle('A3:B3')->applyFromArray($summaryStyles['total']);
    $sheet->getStyle('C3:D3')->applyFromArray($summaryStyles['hadir']);
    $sheet->getStyle('E3:F3')->applyFromArray($summaryStyles['sakit']);
    $sheet->getStyle('G3:H3')->applyFromArray($summaryStyles['izin']);
    $sheet->getStyle('I3:J3')->applyFromArray($summaryStyles['alpa']);

    $sheet->getStyle('A4:J4')->applyFromArray($tableHeaderStyle);

    $rowIndex = 5;
    foreach ($attendances as $attendance) {
        $report_row = $format_report_attendance((array)$attendance);
        $isMidRow = in_array($report_row['row_class'], ['row-hadir', 'row-late'], true);

        $presenceDate = !empty($attendance['presence_date']) ? date('d/m/Y', strtotime((string)$attendance['presence_date'])) : '-';
        $timeIn = !empty($attendance['time_in']) ? date('H:i', strtotime((string)$attendance['time_in'])) : '-';
        $rowData = [
            'A' => (string)($attendance['student_nisn'] ?? '-'),
            'B' => (string)($attendance['student_name'] ?? '-'),
            'C' => (string)($attendance['class_name'] ?? '-'),
            'D' => $presenceDate,
            'E' => $timeIn,
            'F' => (string)($attendance['subject'] ?? '-'),
            'G' => (string)$report_row['status_label'],
            'H' => (string)$report_row['late_label'],
            'I' => (string)$report_row['location_label'],
            'J' => (string)($attendance['information'] ?? '-'),
        ];

        $sheet->setCellValueExplicit('A' . $rowIndex, $rowData['A'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $sheet->setCellValue($col . $rowIndex, $rowData[$col]);
        }

        foreach ($columnOrder as $col) {
            $lineParts = preg_split('/\R/u', (string)$rowData[$col]) ?: [''];
            foreach ($lineParts as $linePart) {
                $columnMaxTextLength[$col] = max($columnMaxTextLength[$col], $stringLength($linePart));
            }
        }

        $sheet->getStyle('A' . $rowIndex . ':J' . $rowIndex)->applyFromArray($isMidRow ? $dataMidStyle : $dataTopStyle);
        $sheet->getRowDimension($rowIndex)->setRowHeight(-1);

        $rowPalette = $rowPaletteMap[$report_row['status_class']] ?? $rowPaletteMap['status-neutral'];
        $sheet->getStyle('A' . $rowIndex . ':J' . $rowIndex)->applyFromArray([
            'font' => ['color' => ['rgb' => $rowPalette['font']]],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => $rowPalette['fill']],
            ],
        ]);
        $sheet->getStyle('G' . $rowIndex)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $rowPalette['font']]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);

        $rowIndex++;
    }

    $lastDataRow = max(4, $rowIndex - 1);
    $sheet->getStyle('A1:J' . $lastDataRow)->applyFromArray($borderStyle);
    if ($lastDataRow >= 5) {
        $sheet->getStyle('A5:J' . $lastDataRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('A5:J' . $lastDataRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    }
    foreach ($columnOrder as $col) {
        $targetWidth = $columnMaxTextLength[$col] + 2;
        if ($col === 'J') {
            $targetWidth = min(max($targetWidth, $columnMinWidth[$col]), $columnMaxWidth[$col]);
            $sheet->getStyle('J:J')->getAlignment()->setWrapText(true);
        } else {
            $targetWidth = min(max($targetWidth, $columnMinWidth[$col]), $columnMaxWidth[$col]);
        }
        $sheet->getColumnDimension($col)->setWidth($targetWidth);
    }
    $sheet->freezePane('A5');

    // Export chart sheet (mode follows selected chart view from UI).
    $chartSheet = $spreadsheet->createSheet();
    $chartSheet->setTitle('Grafik Absensi');
    $chartSheet->setCellValue('A1', 'Grafik Statistik Absensi');
    $chartSheet->mergeCells('A1:I1');
    $chartSheet->setCellValue('A2', 'Periode');
    $chartSheet->setCellValue('B2', $cycle_start_date . ' s/d ' . $cycle_end_date);
    $chartSheet->mergeCells('B2:C2');
    $chartSheet->setCellValue('D2', 'Mode');
    $chartSheet->setCellValue('E2', $chart_mode_label);
    $chartSheet->mergeCells('E2:I2');
    $chartSheet->setCellValue('A3', 'Kelas');
    $chartSheet->setCellValue('B3', $export_class_detail_label);
    $chartSheet->mergeCells('B3:I3');
    $chartSheet->setCellValue('A4', 'Keterangan');
    $chartSheet->setCellValue('B4', 'Hanya tanggal berstatus FINAL (closed). Tanggal menunggu tidak dihitung sebagai Tidak Hadir.');
    $chartSheet->mergeCells('B4:I4');

    $chartTableHeaderRow = 5;
    $chartSheet->setCellValue('A' . $chartTableHeaderRow, 'Tanggal');
    $chartSheet->setCellValue('B' . $chartTableHeaderRow, 'Hadir');
    $chartSheet->setCellValue('C' . $chartTableHeaderRow, 'Tidak Hadir');
    $chartSheet->setCellValue('D' . $chartTableHeaderRow, 'Total Final');
    $chartSheet->setCellValue('E' . $chartTableHeaderRow, '% Hadir');
    $chartSheet->mergeCells('E' . $chartTableHeaderRow . ':I' . $chartTableHeaderRow);

    $chartSheet->getStyle('A1:I1')->applyFromArray($tableHeaderStyle);
    $chartSheet->getStyle('A2:A4')->applyFromArray($metaLabelStyle);
    $chartSheet->getStyle('B2:C2')->applyFromArray($metaValueStyle);
    $chartSheet->getStyle('D2')->applyFromArray($metaLabelStyle);
    $chartSheet->getStyle('E2:I2')->applyFromArray($metaValueStyle);
    $chartSheet->getStyle('B3:I4')->applyFromArray($metaValueStyle);
    $chartSheet->getStyle('A' . $chartTableHeaderRow . ':I' . $chartTableHeaderRow)->applyFromArray($tableHeaderStyle);
    $chartSheet->getStyle('A1:I1')->getFont()->setBold(true);
    $chartSheet->getStyle('A2:A4')->getFont()->setBold(true);
    $chartSheet->getStyle('D2')->getFont()->setBold(true);
    $chartSheet->getStyle('A' . $chartTableHeaderRow . ':I' . $chartTableHeaderRow)->getFont()->setBold(true);
    $chartSheet->getStyle('A1:I1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $chartSheet->getStyle('A' . $chartTableHeaderRow . ':I' . $chartTableHeaderRow)
        ->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $chartSheet->getStyle('B3:I4')->getAlignment()->setWrapText(true);

    $chartExportRows = $chart_series_rows;
    $chartHasRealRows = !empty($chartExportRows);
    if (!$chartHasRealRows) {
        $chartExportRows = [[
            'date' => '-',
            'present' => 0,
            'absent' => 0,
            'total' => 0,
            'percentage' => 0,
        ]];
    }

    $chartDataStartRow = $chartTableHeaderRow + 1;
    $chartRowPointer = $chartDataStartRow;
    foreach ($chartExportRows as $chartRow) {
        $chartDateLabel = (string)($chartRow['date'] ?? '-');
        if ($chartDateLabel !== '-' && strtotime($chartDateLabel) !== false) {
            $chartDateLabel = date('d/m/Y', strtotime($chartDateLabel));
        }
        $presentCount = (int)($chartRow['present'] ?? 0);
        $absentCount = (int)($chartRow['absent'] ?? 0);
        $totalCount = (int)($chartRow['total'] ?? 0);
        $percentageCount = (int)($chartRow['percentage'] ?? 0);

        $chartSheet->setCellValue('A' . $chartRowPointer, $chartDateLabel);
        $chartSheet->setCellValue('B' . $chartRowPointer, $presentCount . ' (siswa)');
        $chartSheet->setCellValue('C' . $chartRowPointer, $absentCount . ' (siswa)');
        $chartSheet->setCellValue('D' . $chartRowPointer, $totalCount . ' (siswa)');
        $chartSheet->setCellValue('E' . $chartRowPointer, $percentageCount);
        $chartSheet->mergeCells('E' . $chartRowPointer . ':I' . $chartRowPointer);
        $chartRowPointer++;
    }
    $chartDataEndRow = max($chartDataStartRow, $chartRowPointer - 1);
    $chartSheet->getStyle('A1:I4')->applyFromArray($borderStyle);
    $chartSheet->getStyle('A' . $chartTableHeaderRow . ':I' . $chartDataEndRow)->applyFromArray($borderStyle);
    $chartSheet->getStyle('B' . $chartDataStartRow . ':D' . $chartDataEndRow)
        ->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $chartSheet->getStyle('E' . $chartDataStartRow . ':I' . $chartDataEndRow)
        ->getAlignment()
        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $chartSheet->getStyle('B' . $chartDataStartRow . ':I' . $chartDataEndRow)->getFont()->setBold(true);
    $chartSheet->getStyle('E' . $chartDataStartRow . ':I' . $chartDataEndRow)->getNumberFormat()->setFormatCode('0');
    $chartSheet->getColumnDimension('A')->setWidth(16);
    $chartSheet->getColumnDimension('B')->setWidth(15);
    $chartSheet->getColumnDimension('C')->setWidth(18);
    $chartSheet->getColumnDimension('D')->setWidth(16);
    $chartSheet->getColumnDimension('E')->setWidth(13);
    $chartSheet->getColumnDimension('F')->setWidth(4);
    $chartSheet->getColumnDimension('G')->setWidth(4);
    $chartSheet->getColumnDimension('H')->setWidth(4);
    $chartSheet->getColumnDimension('I')->setWidth(4);

    if (!$chartHasRealRows) {
        $chartSheet->setCellValue('A' . ($chartDataEndRow + 2), 'Belum ada data FINAL pada rentang tanggal ini.');
        $chartSheet->mergeCells('A' . ($chartDataEndRow + 2) . ':I' . ($chartDataEndRow + 2));
    }

    // Generate chart as embedded PNG image for maximum Excel compatibility.
    // Native chart XML from PhpSpreadsheet may be repaired/removed by some Excel versions.
    $chartImageTempPath = null;
    if (function_exists('imagecreatetruecolor')) {
        $chartWidth = 1320;
        $chartHeight = 560;
        $chartImage = imagecreatetruecolor($chartWidth, $chartHeight);
        if ($chartImage instanceof \GdImage) {
            if (function_exists('imageantialias')) {
                @imageantialias($chartImage, true);
            }

            $colBg = imagecolorallocate($chartImage, 255, 255, 255);
            $colPanel = imagecolorallocate($chartImage, 243, 248, 253);
            $colBorder = imagecolorallocate($chartImage, 203, 213, 225);
            $colGrid = imagecolorallocate($chartImage, 226, 232, 240);
            $colAxis = imagecolorallocate($chartImage, 100, 116, 139);
            $colText = imagecolorallocate($chartImage, 15, 23, 42);
            $colMuted = imagecolorallocate($chartImage, 71, 85, 105);
            $colPresent = imagecolorallocate($chartImage, 22, 163, 74);
            $colAbsent = imagecolorallocate($chartImage, 220, 38, 38);
            $colPresentFill = imagecolorallocatealpha($chartImage, 22, 163, 74, 90);
            $colAbsentFill = imagecolorallocatealpha($chartImage, 220, 38, 38, 90);

            imagefilledrectangle($chartImage, 0, 0, $chartWidth, $chartHeight, $colBg);
            imagefilledrectangle($chartImage, 1, 1, $chartWidth - 2, $chartHeight - 2, $colPanel);
            imagerectangle($chartImage, 0, 0, $chartWidth - 1, $chartHeight - 1, $colBorder);

            imagestring($chartImage, 5, 24, 18, 'Statistik Absensi per Tanggal - ' . $chart_mode_label, $colText);
            imagestring($chartImage, 2, 24, 42, 'Periode: ' . $cycle_start_date . ' s/d ' . $cycle_end_date, $colMuted);
            imagestring($chartImage, 2, 24, 58, 'Kelas: ' . $selected_class_label, $colMuted);

            $legendY = 80;
            imagefilledrectangle($chartImage, 24, $legendY, 42, $legendY + 12, $colPresent);
            imagerectangle($chartImage, 24, $legendY, 42, $legendY + 12, $colPresent);
            imagestring($chartImage, 3, 48, $legendY - 1, 'Hadir', $colText);
            imagefilledrectangle($chartImage, 122, $legendY, 140, $legendY + 12, $colAbsent);
            imagerectangle($chartImage, 122, $legendY, 140, $legendY + 12, $colAbsent);
            imagestring($chartImage, 3, 146, $legendY - 1, 'Tidak Hadir', $colText);

            $plotLeft = 86;
            $plotTop = 102;
            $plotRight = $chartWidth - 54;
            $plotBottom = $chartHeight - 78;
            $plotWidth = max(1, $plotRight - $plotLeft);
            $plotHeight = max(1, $plotBottom - $plotTop);

            imagefilledrectangle($chartImage, $plotLeft, $plotTop, $plotRight, $plotBottom, $colBg);
            imagerectangle($chartImage, $plotLeft, $plotTop, $plotRight, $plotBottom, $colBorder);

            $chartLabelsExport = [];
            $chartPresentExport = [];
            $chartAbsentExport = [];
            foreach ($chartExportRows as $chartRow) {
                $rawDate = (string)($chartRow['date'] ?? '-');
                if ($rawDate !== '-' && strtotime($rawDate) !== false) {
                    $chartLabelsExport[] = date('d/m', strtotime($rawDate));
                } else {
                    $chartLabelsExport[] = $rawDate;
                }
                $chartPresentExport[] = (int)($chartRow['present'] ?? 0);
                $chartAbsentExport[] = (int)($chartRow['absent'] ?? 0);
            }

            $pointCount = count($chartLabelsExport);
            if (!$chartHasRealRows || $pointCount <= 0) {
                imagestring($chartImage, 4, $plotLeft + 20, (int)(($plotTop + $plotBottom) / 2) - 10, 'Belum ada data FINAL untuk ditampilkan pada grafik.', $colMuted);
            } else {
                $maxValue = max(1, max($chartPresentExport), max($chartAbsentExport));
                $gridRows = 5;
                $axisMax = max(1, (int)ceil($maxValue / $gridRows) * $gridRows);

                $valueToY = static function (int $value) use ($plotBottom, $plotHeight, $axisMax): int {
                    $safe = max(0, $value);
                    return $plotBottom - (int)round(($safe / max(1, $axisMax)) * $plotHeight);
                };

                for ($i = 0; $i <= $gridRows; $i++) {
                    $gridY = $plotBottom - (int)round(($plotHeight / $gridRows) * $i);
                    imageline($chartImage, $plotLeft, $gridY, $plotRight, $gridY, $colGrid);
                    $gridValue = (int)round(($axisMax / $gridRows) * $i);
                    imagestring($chartImage, 2, max(4, $plotLeft - 46), $gridY - 7, (string)$gridValue, $colAxis);
                }
                imageline($chartImage, $plotLeft, $plotTop, $plotLeft, $plotBottom, $colAxis);
                imageline($chartImage, $plotLeft, $plotBottom, $plotRight, $plotBottom, $colAxis);

                $xCenters = [];
                if ($pointCount === 1) {
                    $xCenters[] = $plotLeft + (int)round($plotWidth / 2);
                } else {
                    $stepX = $plotWidth / max(1, ($pointCount - 1));
                    for ($i = 0; $i < $pointCount; $i++) {
                        $xCenters[] = $plotLeft + (int)round($stepX * $i);
                    }
                }

                if ($chart_mode === 'bar') {
                    $slotWidth = $plotWidth / max(1, $pointCount);
                    $barWidth = (int)max(7, min(38, floor($slotWidth * 0.32)));
                    for ($i = 0; $i < $pointCount; $i++) {
                        $centerX = $pointCount === 1
                            ? $xCenters[$i]
                            : (int)round($plotLeft + ($slotWidth * $i) + ($slotWidth / 2));
                        $presentY = $valueToY($chartPresentExport[$i] ?? 0);
                        $absentY = $valueToY($chartAbsentExport[$i] ?? 0);

                        $presentX1 = $centerX - $barWidth - 2;
                        $presentX2 = $centerX - 2;
                        $absentX1 = $centerX + 2;
                        $absentX2 = $centerX + $barWidth + 2;

                        imagefilledrectangle($chartImage, $presentX1, $presentY, $presentX2, $plotBottom - 1, $colPresentFill);
                        imagerectangle($chartImage, $presentX1, $presentY, $presentX2, $plotBottom - 1, $colPresent);
                        imagefilledrectangle($chartImage, $absentX1, $absentY, $absentX2, $plotBottom - 1, $colAbsentFill);
                        imagerectangle($chartImage, $absentX1, $absentY, $absentX2, $plotBottom - 1, $colAbsent);
                    }
                } else {
                    $presentPoints = [];
                    $absentPoints = [];
                    for ($i = 0; $i < $pointCount; $i++) {
                        $x = $xCenters[$i];
                        $presentPoints[] = ['x' => $x, 'y' => $valueToY($chartPresentExport[$i] ?? 0)];
                        $absentPoints[] = ['x' => $x, 'y' => $valueToY($chartAbsentExport[$i] ?? 0)];
                    }
                    for ($i = 0; $i < $pointCount - 1; $i++) {
                        imageline(
                            $chartImage,
                            $presentPoints[$i]['x'],
                            $presentPoints[$i]['y'],
                            $presentPoints[$i + 1]['x'],
                            $presentPoints[$i + 1]['y'],
                            $colPresent
                        );
                        imageline(
                            $chartImage,
                            $absentPoints[$i]['x'],
                            $absentPoints[$i]['y'],
                            $absentPoints[$i + 1]['x'],
                            $absentPoints[$i + 1]['y'],
                            $colAbsent
                        );
                    }
                    foreach ($presentPoints as $pt) {
                        imagefilledellipse($chartImage, $pt['x'], $pt['y'], 9, 9, $colPresent);
                    }
                    foreach ($absentPoints as $pt) {
                        imagefilledellipse($chartImage, $pt['x'], $pt['y'], 9, 9, $colAbsent);
                    }
                }

                $labelStride = max(1, (int)ceil($pointCount / 10));
                for ($i = 0; $i < $pointCount; $i++) {
                    $isBoundary = ($i === 0 || $i === $pointCount - 1);
                    if (!$isBoundary && ($i % $labelStride) !== 0) {
                        continue;
                    }
                    $x = $xCenters[$i];
                    $label = (string)($chartLabelsExport[$i] ?? '-');
                    $labelWidth = max(6, strlen($label) * 6);
                    imagestring($chartImage, 2, max(4, $x - (int)($labelWidth / 2)), $plotBottom + 8, $label, $colAxis);
                }
            }

            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }
            if (is_dir($tempDir)) {
                $chartImageTempPath = $tempDir . DIRECTORY_SEPARATOR . 'attendance-chart-' . uniqid('', true) . '.png';
                if (!@imagepng($chartImage, $chartImageTempPath)) {
                    $chartImageTempPath = null;
                }
            }

            imagedestroy($chartImage);

            if ($chartImageTempPath !== null && is_file($chartImageTempPath)) {
                $chartImageAnchorRow = $chartDataEndRow + 3;
                $chartDrawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $chartDrawing->setName('Grafik Absensi');
                $chartDrawing->setDescription('Grafik Statistik Absensi');
                $chartDrawing->setPath($chartImageTempPath, true);
                $chartDrawing->setCoordinates('A' . $chartImageAnchorRow);
                $chartDrawing->setOffsetX(6);
                $chartDrawing->setOffsetY(6);
                $chartDrawing->setHeight(350);
                $chartDrawing->setWorksheet($chartSheet);
            }
        }
    }
    $spreadsheet->setActiveSheetIndex(0);

    $fileRangeLabel = $filter_date_from === $filter_date_to
        ? $filter_date_from
        : ($filter_date_from . '_sd_' . $filter_date_to);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="absensi_' . $fileRangeLabel . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->setIncludeCharts(true);
    $writer->setPreCalculateFormulas(false);
    $writer->save('php://output');
    $spreadsheet->disconnectWorksheets();
    if (isset($chartImageTempPath) && is_string($chartImageTempPath) && $chartImageTempPath !== '' && is_file($chartImageTempPath)) {
        @unlink($chartImageTempPath);
    }
    exit();
}

// Handle print (admin-styled report)
if (isset($_GET['print'])) {
    if (function_exists('clear_output_buffers_for_binary_download')) {
        clear_output_buffers_for_binary_download();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    $printRangeLabel = $filter_date_from === $filter_date_to
        ? $filter_date_from
        : ($filter_date_from . ' s/d ' . $filter_date_to);
    $printTitle = 'Laporan Absensi Admin - Presenova';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($printTitle, ENT_QUOTES, 'UTF-8'); ?></title>
        <style>
            :root {
                --bg: #eef3f8;
                --card: #ffffff;
                --line: #d3e0ec;
                --text: #0f172a;
                --muted: #475569;
                --head-1: #0f172a;
                --head-2: #1e40af;
                --accent: #38bdf8;
                --hadir: #16a34a;
                --late: #d97706;
                --sakit: #f97316;
                --izin: #06b6d4;
                --alpa: #dc2626;
            }

            * {
                box-sizing: border-box;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body {
                margin: 0;
                padding: 14px;
                font-family: "Inter", "Segoe UI", Arial, sans-serif;
                background: linear-gradient(135deg, #f8fafc 0%, var(--bg) 100%);
                color: var(--text);
                font-size: 11px;
            }

            .admin-sheet {
                max-width: 1120px;
                margin: 0 auto;
                background: var(--card);
                border: 1px solid var(--line);
                border-radius: 14px;
                overflow: hidden;
                box-shadow: 0 18px 42px rgba(15, 23, 42, 0.14);
            }

            .sheet-header {
                position: relative;
                background: linear-gradient(120deg, var(--head-1) 0%, var(--head-2) 88%);
                color: #ffffff;
                padding: 16px 20px;
                overflow: hidden;
            }

            .sheet-header::after {
                content: "";
                position: absolute;
                right: -90px;
                top: -85px;
                width: 260px;
                height: 260px;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(56, 189, 248, 0.34), transparent 70%);
            }

            .header-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 16px;
                position: relative;
                z-index: 1;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
            }

            .brand-logo {
                width: 50px;
                height: 50px;
                object-fit: contain;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 10px;
                padding: 5px;
            }

            .brand h1 {
                margin: 0;
                font-size: 22px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }

            .brand p {
                margin: 3px 0 0;
                font-size: 12px;
                opacity: 0.92;
            }

            .meta-box {
                min-width: 255px;
                border: 1px solid rgba(255, 255, 255, 0.3);
                background: rgba(15, 23, 42, 0.28);
                border-radius: 10px;
                padding: 8px 10px;
                display: grid;
                gap: 5px;
            }

            .meta-box span {
                display: block;
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: 0.4px;
                opacity: 0.8;
            }

            .meta-box strong {
                display: block;
                font-size: 11px;
                margin-top: 1px;
            }

            .filter-strip {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 8px;
                padding: 10px 20px;
                border-bottom: 1px solid var(--line);
                background: #f4f8fc;
            }

            .filter-item {
                border: 1px solid #d7e5f0;
                border-radius: 8px;
                padding: 6px 8px;
                background: #ffffff;
            }

            .filter-item span {
                display: block;
                font-size: 9px;
                color: var(--muted);
                text-transform: uppercase;
                letter-spacing: 0.35px;
            }

            .filter-item strong {
                display: block;
                margin-top: 3px;
                font-size: 11px;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 8px;
                padding: 12px 20px 10px;
            }

            .stats-item {
                border-radius: 10px;
                padding: 7px 8px;
                color: #ffffff;
                font-weight: 700;
                text-align: center;
            }

            .stats-item small {
                display: block;
                font-size: 9px;
                text-transform: uppercase;
                letter-spacing: 0.35px;
                opacity: 0.9;
            }

            .stats-item strong {
                display: block;
                margin-top: 2px;
                font-size: 18px;
                line-height: 1.1;
            }

            .stats-total { background: linear-gradient(130deg, #1d4ed8, #3b82f6); }
            .stats-hadir { background: linear-gradient(130deg, #15803d, #22c55e); }
            .stats-sakit { background: linear-gradient(130deg, #b45309, #f59e0b); }
            .stats-izin { background: linear-gradient(130deg, #0e7490, #22d3ee); }
            .stats-alpa { background: linear-gradient(130deg, #b91c1c, #ef4444); }

            .table-wrap {
                padding: 0 20px 12px;
            }

            table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                border: 1px solid var(--line);
                border-radius: 10px;
                overflow: hidden;
            }

            thead th {
                background: #1e3a8a;
                color: #ffffff;
                padding: 7px 8px;
                border-right: 1px solid #284ea3;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.35px;
                text-align: left;
            }

            thead th:last-child {
                border-right: 0;
            }

            tbody td {
                border-top: 1px solid #deebf5;
                border-right: 1px solid #e0ebf5;
                padding: 6px 8px;
                vertical-align: top;
                font-size: 10.4px;
            }

            tbody td:last-child {
                border-right: 0;
            }

            tbody tr:nth-child(even) td {
                background: #f9fcff;
            }

            tbody tr.row-hadir td { background: #ecfdf3; }
            tbody tr.row-alpa td { background: #fff1f2; }

            .status-pill {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 68px;
                border-radius: 999px;
                font-size: 9px;
                font-weight: 700;
                letter-spacing: 0.3px;
                padding: 2px 8px;
                border: 1px solid transparent;
            }

            .status-hadir { background: #bbf7d0; color: #166534; border-color: #86efac; }
            .status-late { background: #fde68a; color: #92400e; border-color: #fcd34d; }
            .status-sakit { background: #fed7aa; color: #9a3412; border-color: #fdba74; }
            .status-izin { background: #bae6fd; color: #0e7490; border-color: #7dd3fc; }
            .status-alpa { background: #fecaca; color: #991b1b; border-color: #fca5a5; }
            .status-neutral { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }

            .row-hadir td:first-child { border-left: 3px solid var(--hadir); }
            .row-late td:first-child { border-left: 3px solid var(--late); }
            .row-sakit td:first-child { border-left: 3px solid var(--sakit); }
            .row-izin td:first-child { border-left: 3px solid var(--izin); }
            .row-alpa td:first-child { border-left: 3px solid var(--alpa); }
            tbody tr.row-hadir td,
            tbody tr.row-late td {
                vertical-align: middle;
            }

            .sheet-footer {
                display: flex;
                justify-content: space-between;
                gap: 8px;
                padding: 10px 20px;
                border-top: 1px solid var(--line);
                background: #f8fafc;
                color: var(--muted);
                font-size: 10px;
            }

            @media print {
                @page {
                    margin: 9mm;
                }

                body {
                    background: #ffffff;
                    padding: 0;
                }

                .admin-sheet {
                    max-width: none;
                    margin: 0;
                    border: 0;
                    border-radius: 0;
                    box-shadow: none;
                }

                .sheet-header,
                .filter-strip,
                .stats-grid,
                .sheet-footer {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }

                table thead {
                    display: table-header-group;
                }

                table tr,
                table td,
                table th {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
            }
        </style>
    </head>
    <body>
        <main class="admin-sheet">
            <header class="sheet-header">
                <div class="header-row">
                    <div class="brand">
                        <?php if ($logo_base64 !== ''): ?>
                            <img class="brand-logo" src="data:image/png;base64,<?php echo $logo_base64; ?>" alt="Logo Presenova">
                        <?php endif; ?>
                        <div>
                            <h1>Laporan Absensi Admin</h1>
                            <p>Presenova Monitoring Center</p>
                        </div>
                    </div>
                    <div class="meta-box">
                        <div>
                            <span>Printed At</span>
                            <strong><?php echo htmlspecialchars($printed_at, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div>
                            <span>Printed By</span>
                            <strong><?php echo htmlspecialchars($printed_by, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                    </div>
                </div>
            </header>

            <section class="filter-strip">
                <div class="filter-item">
                    <span>Periode</span>
                    <strong><?php echo htmlspecialchars($printRangeLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="filter-item">
                    <span>Kelas</span>
                    <strong><?php echo htmlspecialchars($selected_class_label, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="filter-item">
                    <span>Status</span>
                    <strong><?php echo htmlspecialchars($selected_status_label, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="filter-item">
                    <span>Total Data</span>
                    <strong><?php echo count($attendances); ?> baris</strong>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stats-item stats-total"><small>Total</small><strong><?php echo (int)($stats['total'] ?? 0); ?></strong></div>
                <div class="stats-item stats-hadir"><small>Hadir</small><strong><?php echo (int)($stats['present'] ?? 0); ?></strong></div>
                <div class="stats-item stats-sakit"><small>Sakit</small><strong><?php echo (int)($stats['sick'] ?? 0); ?></strong></div>
                <div class="stats-item stats-izin"><small>Izin</small><strong><?php echo (int)($stats['permission'] ?? 0); ?></strong></div>
                <div class="stats-item stats-alpa"><small>Alpa</small><strong><?php echo (int)$alpa_total; ?></strong></div>
            </section>

            <section class="table-wrap">
                <table aria-label="Laporan Absensi Admin">
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
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendances as $attendance): ?>
                            <?php $report_row = $format_report_attendance((array)$attendance); ?>
                            <tr class="<?php echo htmlspecialchars($report_row['row_class'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="main-cell"><?php echo htmlspecialchars((string)($attendance['student_nisn'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="main-cell"><?php echo htmlspecialchars((string)($attendance['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="main-cell"><?php echo htmlspecialchars((string)($attendance['class_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="main-cell"><?php echo !empty($attendance['presence_date']) ? date('d/m/Y', strtotime((string)$attendance['presence_date'])) : '-'; ?></td>
                                <td class="main-cell"><?php echo !empty($attendance['time_in']) ? date('H:i', strtotime((string)$attendance['time_in'])) : '-'; ?></td>
                                <td class="main-cell"><?php echo htmlspecialchars((string)($attendance['subject'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="main-cell">
                                    <span class="status-pill <?php echo htmlspecialchars($report_row['status_class'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($report_row['status_label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="main-cell"><?php echo htmlspecialchars($report_row['late_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="main-cell"><?php echo htmlspecialchars($report_row['location_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($attendance['information'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <footer class="sheet-footer">
                <div>Presenova Admin Panel</div>
                <div>Printed at <?php echo htmlspecialchars($printed_at, ENT_QUOTES, 'UTF-8'); ?> by <?php echo htmlspecialchars($printed_by, ENT_QUOTES, 'UTF-8'); ?></div>
            </footer>
        </main>

        <script>
            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 220);
            });
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
            <a id="attendanceExportExcelBtn"
               href="?table=attendance&export=excel&date_from=<?php echo urlencode($filter_date_from); ?>&date_to=<?php echo urlencode($filter_date_to); ?>&class=<?php echo urlencode($filter_class); ?>&status=<?php echo urlencode($filter_status); ?>&chart_mode=<?php echo urlencode($chart_mode); ?>" 
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
            <input type="date" class="form-control" id="filterDateTo" value="<?php echo htmlspecialchars($filter_date_to); ?>" min="<?php echo htmlspecialchars($filter_date_from); ?>">
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
            <button class="btn btn-primary w-100" id="attendanceFilterBtn" onclick="applyFilters()">
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
                                   class="btn btn-sm btn-danger"
                                   onclick="return AppDialog.inlineConfirm(this, 'Hapus data absensi ini?')">
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
    <div class="row mt-4 g-3">
        <div class="col-lg-7">
            <div class="table-container attendance-chart-panel">
                <div class="attendance-chart-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> <?php echo htmlspecialchars($statistik_title); ?></h5>
                    <div class="btn-group attendance-chart-type-toggle" role="group" aria-label="Pilih tipe grafik">
                        <button type="button" class="btn btn-sm <?php echo $chart_mode === 'line' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-chart-mode="line">
                            <i class="bi bi-activity"></i> Grafik Garis
                        </button>
                        <button type="button" class="btn btn-sm <?php echo $chart_mode === 'bar' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-chart-mode="bar">
                            <i class="bi bi-bar-chart-line"></i> Grafik Batang
                        </button>
                    </div>
                </div>

                <input type="hidden" id="attendanceChartModeInput" value="<?php echo htmlspecialchars($chart_mode, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="attendance-chart-wrap attendance-chart-scroll" id="attendanceChartScroll">
                    <div class="attendance-chart-inner" id="attendanceChartInner">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
                <div class="attendance-chart-slider-wrap">
                    <label for="attendanceChartXSlider">Geser Sumbu X</label>
                    <input type="range" id="attendanceChartXSlider" min="0" max="0" value="0" step="1">
                </div>
                <div class="attendance-chart-note">
                    Data grafik dihitung per tanggal dan hanya menampilkan tanggal berstatus <strong>Final (Closed)</strong>.
                    Tanggal yang masih menunggu tidak dihitung sebagai tidak hadir.
                </div>

                <div class="attendance-chart-table-switch">
                    <button type="button" class="btn btn-sm <?php echo $chart_mode === 'line' ? 'btn-primary' : 'btn-outline-secondary'; ?>" data-chart-table="line">
                        Tabel Grafik Garis
                    </button>
                    <button type="button" class="btn btn-sm <?php echo $chart_mode === 'bar' ? 'btn-primary' : 'btn-outline-secondary'; ?>" data-chart-table="bar">
                        Tabel Grafik Batang
                    </button>
                </div>

                <div class="table-responsive attendance-graph-data-table <?php echo $chart_mode === 'bar' ? 'd-none' : ''; ?>" id="chartDataTableLineWrap">
                    <table class="table table-sm no-card-table attendance-daily-summary-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Total Final</th>
                                <th>Hadir</th>
                                <th>Tidak Hadir</th>
                                <th>% Hadir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($chart_series_rows)): ?>
                                <tr>
                                    <td colspan="5" class="text-muted text-center">Belum ada data final (closed) untuk ditampilkan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($chart_series_rows as $chart_row): ?>
                                <?php
                                    $chartPercentage = (int)($chart_row['percentage'] ?? 0);
                                    $chartColor = $chartPercentage >= 80 ? 'success' : ($chartPercentage >= 60 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime((string)$chart_row['date'])); ?></td>
                                    <td><?php echo (int)($chart_row['total'] ?? 0); ?></td>
                                    <td><?php echo (int)($chart_row['present'] ?? 0); ?></td>
                                    <td><?php echo (int)($chart_row['absent'] ?? 0); ?></td>
                                    <td><span class="badge bg-<?php echo $chartColor; ?>"><?php echo $chartPercentage; ?>%</span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive attendance-graph-data-table <?php echo $chart_mode === 'line' ? 'd-none' : ''; ?>" id="chartDataTableBarWrap">
                    <table class="table table-sm no-card-table attendance-daily-summary-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Total Final</th>
                                <th>Hadir</th>
                                <th>Tidak Hadir</th>
                                <th>% Hadir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($chart_series_rows)): ?>
                                <tr>
                                    <td colspan="5" class="text-muted text-center">Belum ada data final (closed) untuk ditampilkan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($chart_series_rows as $chart_row): ?>
                                <?php
                                    $chartPercentage = (int)($chart_row['percentage'] ?? 0);
                                    $chartColor = $chartPercentage >= 80 ? 'success' : ($chartPercentage >= 60 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime((string)$chart_row['date'])); ?></td>
                                    <td><?php echo (int)($chart_row['total'] ?? 0); ?></td>
                                    <td><?php echo (int)($chart_row['present'] ?? 0); ?></td>
                                    <td><?php echo (int)($chart_row['absent'] ?? 0); ?></td>
                                    <td><span class="badge bg-<?php echo $chartColor; ?>"><?php echo $chartPercentage; ?>%</span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="table-container">
                <h5><i class="bi bi-calendar-month"></i> Rekap Harian</h5>
                <div id="dailySummary">
                    <div class="table-responsive">
                        <table class="table table-sm no-card-table attendance-daily-summary-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Total Jadwal</th>
                                    <th>Berhasil Absen</th>
                                    <th>Tidak Hadir Final</th>
                                    <th>% Final</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($daily_summary_rows)): ?>
                                    <tr>
                                        <td colspan="6" class="text-muted text-center">Tidak ada data jadwal pada rentang tanggal ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($daily_summary_rows as $day): ?>
                                    <?php
                                        $isWaitingSummary = (($day['status_key'] ?? '') === 'menunggu');
                                        $percentage = (int)($day['final_percentage'] ?? 0);
                                        $color = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                                    ?>
                                    <tr class="<?php echo $isWaitingSummary ? 'attendance-summary-waiting-row' : ''; ?>">
                                        <td><?php echo date('d/m', strtotime((string)$day['date'])); ?></td>
                                        <td><?php echo (int)($day['total'] ?? 0); ?></td>
                                        <td><?php echo (int)($day['recorded'] ?? 0); ?></td>
                                        <td><?php echo $isWaitingSummary ? '-' : (string)(int)($day['final_absent'] ?? 0); ?></td>
                                        <td>
                                            <?php if ($isWaitingSummary): ?>
                                                <span class="badge bg-secondary">Menunggu</span>
                                            <?php else: ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $percentage; ?>%</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $isWaitingSummary ? 'bg-info text-dark' : 'bg-success'; ?>">
                                                <?php echo $isWaitingSummary ? 'Menunggu' : 'Final'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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

function enforceAttendanceDateRange() {
    const dateFromEl = document.getElementById('filterDateFrom');
    const dateToEl = document.getElementById('filterDateTo');
    if (!dateFromEl || !dateToEl) {
        return;
    }

    const dateFrom = dateFromEl.value || '';
    const dateTo = dateToEl.value || '';
    dateToEl.min = dateFrom;

    if (dateFrom && dateTo && dateTo < dateFrom) {
        dateToEl.value = dateFrom;
    }
}

function applyFilters() {
    enforceAttendanceDateRange();

    let dateFrom = $('#filterDateFrom').val();
    let dateTo = $('#filterDateTo').val();
    const classId = $('#filterClass').val();
    const status = $('#filterStatus').val();
    const chartMode = ($('#attendanceChartModeInput').val() || 'line').toLowerCase() === 'bar' ? 'bar' : 'line';

    if (dateFrom && dateTo && dateTo < dateFrom) {
        dateTo = dateFrom;
        $('#filterDateTo').val(dateTo);
    }
    
    let url = '?table=attendance';
    
    if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
    if (dateTo) url += '&date_to=' + encodeURIComponent(dateTo);
    if (classId) url += '&class=' + encodeURIComponent(classId);
    if (status) url += '&status=' + encodeURIComponent(status);
    url += '&chart_mode=' + encodeURIComponent(chartMode);
    
    window.location.href = url;
}

$(document).ready(function() {
    const dateFromEl = document.getElementById('filterDateFrom');
    const dateToEl = document.getElementById('filterDateTo');
    if (dateFromEl && dateToEl) {
        enforceAttendanceDateRange();
        dateFromEl.addEventListener('change', enforceAttendanceDateRange);
        dateToEl.addEventListener('change', enforceAttendanceDateRange);
    }

    const chartLabels = <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>;
    const chartPresentData = <?php echo json_encode($chart_present); ?>;
    const chartAbsentData = <?php echo json_encode($chart_absent); ?>;
    const chartSuggestedMax = <?php echo (int)$chart_suggested_max; ?>;
    const chartModeInputEl = document.getElementById('attendanceChartModeInput');
    const chartScrollEl = document.getElementById('attendanceChartScroll');
    const chartInnerEl = document.getElementById('attendanceChartInner');
    const chartSliderEl = document.getElementById('attendanceChartXSlider');
    const chartTypeButtons = Array.from(document.querySelectorAll('[data-chart-mode]'));
    const chartTableButtons = Array.from(document.querySelectorAll('[data-chart-table]'));
    const lineTableWrap = document.getElementById('chartDataTableLineWrap');
    const barTableWrap = document.getElementById('chartDataTableBarWrap');
    const exportExcelBtn = document.getElementById('attendanceExportExcelBtn');
    const chartCtxEl = document.getElementById('attendanceChart');
    const chartCtx = chartCtxEl ? chartCtxEl.getContext('2d') : null;

    let currentChartMode = chartModeInputEl && chartModeInputEl.value === 'bar' ? 'bar' : 'line';
    let attendanceChartInstance = null;

    const updateExportLink = () => {
        if (!exportExcelBtn) {
            return;
        }
        try {
            const url = new URL(exportExcelBtn.getAttribute('href'), window.location.href);
            url.searchParams.set('chart_mode', currentChartMode);
            exportExcelBtn.setAttribute('href', url.pathname + '?' + url.searchParams.toString());
        } catch (error) {
            // no-op
        }
    };

    const updateChartViewport = () => {
        if (!chartScrollEl || !chartInnerEl) {
            return;
        }
        const minPerPoint = currentChartMode === 'line' ? 120 : 96;
        const targetWidth = Math.max(chartScrollEl.clientWidth, Math.max(1, chartLabels.length) * minPerPoint);
        chartInnerEl.style.minWidth = targetWidth + 'px';

        if (chartSliderEl) {
            const maxScroll = Math.max(0, chartScrollEl.scrollWidth - chartScrollEl.clientWidth);
            chartSliderEl.max = String(maxScroll);
            chartSliderEl.value = String(Math.min(maxScroll, Math.round(chartScrollEl.scrollLeft)));
            chartSliderEl.disabled = maxScroll <= 0;
        }

        if (attendanceChartInstance) {
            attendanceChartInstance.resize();
        }
    };

    const updateChartTypeButtons = () => {
        chartTypeButtons.forEach((button) => {
            const mode = (button.getAttribute('data-chart-mode') || '').toLowerCase() === 'bar' ? 'bar' : 'line';
            button.classList.toggle('btn-primary', mode === currentChartMode);
            button.classList.toggle('btn-outline-primary', mode !== currentChartMode);
        });
    };

    const updateChartTableVisibility = () => {
        if (lineTableWrap) {
            lineTableWrap.classList.toggle('d-none', currentChartMode !== 'line');
        }
        if (barTableWrap) {
            barTableWrap.classList.toggle('d-none', currentChartMode !== 'bar');
        }
        chartTableButtons.forEach((button) => {
            const mode = (button.getAttribute('data-chart-table') || '').toLowerCase() === 'bar' ? 'bar' : 'line';
            button.classList.toggle('btn-primary', mode === currentChartMode);
            button.classList.toggle('btn-outline-secondary', mode !== currentChartMode);
        });
    };

    const buildDatasets = (mode) => {
        if (mode === 'line') {
            return [
                {
                    label: 'Hadir',
                    data: chartPresentData,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22, 163, 74, 0.22)',
                    borderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#16a34a',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    fill: true,
                    tension: 0.35
                },
                {
                    label: 'Tidak Hadir',
                    data: chartAbsentData,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.17)',
                    borderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#dc2626',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    fill: true,
                    tension: 0.35
                }
            ];
        }

        return [
            {
                label: 'Hadir',
                data: chartPresentData,
                backgroundColor: 'rgba(22, 163, 74, 0.86)',
                borderColor: '#166534',
                borderWidth: 1.2,
                borderRadius: 6,
                maxBarThickness: 36
            },
            {
                label: 'Tidak Hadir',
                data: chartAbsentData,
                backgroundColor: 'rgba(220, 38, 38, 0.86)',
                borderColor: '#7f1d1d',
                borderWidth: 1.2,
                borderRadius: 6,
                maxBarThickness: 36
            }
        ];
    };

    const renderAttendanceChart = (mode) => {
        if (!chartCtx) {
            return;
        }

        if (attendanceChartInstance) {
            attendanceChartInstance.destroy();
            attendanceChartInstance = null;
        }

        attendanceChartInstance = new Chart(chartCtx, {
            type: mode,
            data: {
                labels: chartLabels,
                datasets: buildDatasets(mode)
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 450
                },
                layout: {
                    padding: {
                        top: 4,
                        right: 8,
                        bottom: 0,
                        left: 0
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'start',
                        labels: {
                            boxWidth: 28,
                            boxHeight: 10,
                            usePointStyle: mode === 'line'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            minRotation: 0
                        },
                        grid: {
                            display: mode === 'bar'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: chartSuggestedMax,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        updateChartViewport();
    };

    if (chartScrollEl && chartSliderEl) {
        chartScrollEl.addEventListener('scroll', () => {
            const maxScroll = Math.max(0, chartScrollEl.scrollWidth - chartScrollEl.clientWidth);
            chartSliderEl.max = String(maxScroll);
            chartSliderEl.value = String(Math.min(maxScroll, Math.round(chartScrollEl.scrollLeft)));
        });
        chartSliderEl.addEventListener('input', () => {
            chartScrollEl.scrollLeft = Number(chartSliderEl.value || 0);
        });
    }

    chartTypeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const mode = (button.getAttribute('data-chart-mode') || '').toLowerCase() === 'bar' ? 'bar' : 'line';
            currentChartMode = mode;
            if (chartModeInputEl) {
                chartModeInputEl.value = mode;
            }
            updateChartTypeButtons();
            updateChartTableVisibility();
            updateExportLink();
            renderAttendanceChart(mode);
        });
    });

    chartTableButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const mode = (button.getAttribute('data-chart-table') || '').toLowerCase() === 'bar' ? 'bar' : 'line';
            currentChartMode = mode;
            if (chartModeInputEl) {
                chartModeInputEl.value = mode;
            }
            updateChartTypeButtons();
            updateChartTableVisibility();
            updateExportLink();
            renderAttendanceChart(mode);
        });
    });

    updateChartTypeButtons();
    updateChartTableVisibility();
    updateExportLink();
    renderAttendanceChart(currentChartMode);
    window.addEventListener('resize', updateChartViewport);
    
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
