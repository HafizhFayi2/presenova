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
$logo_path = __DIR__ . '/../../../../assets/images/presenova.png';
if (!is_file($logo_path)) {
    $logo_path = __DIR__ . '/../../../../assets/images/logo-192.png';
}
if (is_file($logo_path)) {
    $logo_base64 = base64_encode((string)file_get_contents($logo_path));
}

// Handle export (native XLSX without extension warning)
if (isset($_GET['export'])) {
    if (ob_get_length()) {
        ob_clean();
    }

    $autoload_path = dirname(__DIR__, 5) . '/vendor/autoload.php';
    if (!is_file($autoload_path)) {
        http_response_code(500);
        echo 'Dependency autoload tidak ditemukan. Jalankan composer install.';
        exit();
    }
    require_once $autoload_path;

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
    $sheet->setCellValue('G1', $selected_class_label);
    $sheet->mergeCells('B1:E1');
    $sheet->mergeCells('G1:J1');

    $sheet->setCellValue('A2', 'Status');
    $sheet->setCellValue('B2', $selected_status_label);
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
    $writer->setPreCalculateFormulas(false);
    $writer->save('php://output');
    $spreadsheet->disconnectWorksheets();
    exit();
}

// Handle print (admin-styled report)
if (isset($_GET['print'])) {
    if (ob_get_length()) {
        ob_clean();
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
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="table-container">
                <h5><i class="bi bi-bar-chart"></i> <?php echo htmlspecialchars($statistik_title); ?></h5>
                <div class="attendance-chart-wrap">
                    <canvas id="attendanceChart"></canvas>
                </div>
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
                    
                    <div class="table-responsive">
                        <table class="table table-sm no-card-table attendance-daily-summary-table">
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

    if (dateFrom && dateTo && dateTo < dateFrom) {
        dateTo = dateFrom;
        $('#filterDateTo').val(dateTo);
    }
    
    let url = '?table=attendance';
    
    if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
    if (dateTo) url += '&date_to=' + encodeURIComponent(dateTo);
    if (classId) url += '&class=' + encodeURIComponent(classId);
    if (status) url += '&status=' + encodeURIComponent(status);
    
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
            maintainAspectRatio: false,
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
                        usePointStyle: false
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: <?php echo (int)$chart_suggested_max; ?>,
                    ticks: {
                        precision: 0
                    }
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
