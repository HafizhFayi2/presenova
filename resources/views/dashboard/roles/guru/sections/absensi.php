<?php
$tz = new DateTimeZone('Asia/Jakarta');
$nowWib = new DateTime('now', $tz);

$parseDate = static function ($raw, DateTimeZone $timezone, string $fallback): string {
    $value = trim((string) $raw);
    if ($value === '') {
        return $fallback;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value, $timezone);
    if ($dt instanceof DateTime) {
        return $dt->format('Y-m-d');
    }

    try {
        return (new DateTime($value, $timezone))->format('Y-m-d');
    } catch (Throwable) {
        return $fallback;
    }
};

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d');
$filterDateFrom = $parseDate($_GET['date_from'] ?? '', $tz, $defaultFrom);
$filterDateTo = $parseDate($_GET['date_to'] ?? '', $tz, $defaultTo);
if ($filterDateFrom > $filterDateTo) {
    $tmp = $filterDateFrom;
    $filterDateFrom = $filterDateTo;
    $filterDateTo = $tmp;
}
$filterClass = trim((string) ($_GET['class'] ?? ''));

// Kelas yang diajar guru.
$classStmt = $db->query(
    "
    SELECT DISTINCT c.class_id, c.class_name
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    WHERE ts.teacher_id = ?
    ORDER BY c.class_name
",
    [$teacher_id]
);
$classes = $classStmt ? $classStmt->fetchAll() : [];

// Mapping status.
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

// Daftar siswa yang diajar guru (berbasis kelas yang diampu).
$studentsSql = "
    SELECT DISTINCT s.id, s.student_nisn, s.student_name, c.class_id, c.class_name
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    JOIN student s ON s.class_id = c.class_id
    WHERE ts.teacher_id = ?
";
$studentsParams = [$teacher_id];
if ($filterClass !== '') {
    $studentsSql .= " AND c.class_id = ?";
    $studentsParams[] = $filterClass;
}
$studentsSql .= " ORDER BY c.class_name ASC, s.student_name ASC";
$studentsStmt = $db->query($studentsSql, $studentsParams);
$students = $studentsStmt ? $studentsStmt->fetchAll() : [];

$studentStats = [];
foreach ($students as $student) {
    $studentId = (int) ($student['id'] ?? 0);
    if ($studentId <= 0) {
        continue;
    }
    $studentStats[$studentId] = [
        'id' => $studentId,
        'student_nisn' => (string) ($student['student_nisn'] ?? '-'),
        'student_name' => (string) ($student['student_name'] ?? '-'),
        'class_name' => (string) ($student['class_name'] ?? '-'),
        'total_sesi' => 0,
        'hadir' => 0,
        'terlambat' => 0,
        'sakit' => 0,
        'izin' => 0,
        'alpa' => 0,
    ];
}

// Rekap jadwal+presence untuk rentang tanggal filter.
$scheduleSql = "
    SELECT
        ss.student_schedule_id,
        ss.student_id,
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
";
$scheduleParams = [$teacher_id, $filterDateFrom, $filterDateTo];
if ($filterClass !== '') {
    $scheduleSql .= " AND ts.class_id = ?";
    $scheduleParams[] = $filterClass;
}
$scheduleSql .= " ORDER BY ss.schedule_date DESC";
$scheduleStmt = $db->query($scheduleSql, $scheduleParams);
$scheduleRows = $scheduleStmt ? $scheduleStmt->fetchAll() : [];

foreach ($scheduleRows as $row) {
    $studentId = (int) ($row['student_id'] ?? 0);
    if (!isset($studentStats[$studentId])) {
        continue;
    }

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

    $studentStats[$studentId]['total_sesi']++;
    if ($presentId <= 0) {
        $studentStats[$studentId]['alpa']++;
        continue;
    }

    $statusName = $statusMap[(string) $presentId] ?? '';
    if ($statusName === 'hadir' || $presentId === 1) {
        $studentStats[$studentId]['hadir']++;
        if (($row['is_late'] ?? 'N') === 'Y') {
            $studentStats[$studentId]['terlambat']++;
        }
        continue;
    }

    if ($statusName === 'sakit' || $presentId === 2) {
        $studentStats[$studentId]['sakit']++;
        continue;
    }

    if ($statusName === 'izin' || $presentId === 3) {
        $studentStats[$studentId]['izin']++;
        continue;
    }

    if ($statusName === 'alpa' || $statusName === 'tidak hadir' || $presentId === 4) {
        $studentStats[$studentId]['alpa']++;
    }
}

$studentRows = array_values($studentStats);
usort($studentRows, static function (array $a, array $b): int {
    $classCompare = strcmp((string) ($a['class_name'] ?? ''), (string) ($b['class_name'] ?? ''));
    if ($classCompare !== 0) {
        return $classCompare;
    }
    return strcmp((string) ($a['student_name'] ?? ''), (string) ($b['student_name'] ?? ''));
});

$summary = [
    'total_students' => count($studentRows),
    'students_with_record' => 0,
    'total_sesi' => 0,
    'hadir' => 0,
    'terlambat' => 0,
    'sakit' => 0,
    'izin' => 0,
    'alpa' => 0,
    'need_attention' => 0,
];

foreach ($studentRows as $row) {
    $totalSesi = (int) ($row['total_sesi'] ?? 0);
    $hadir = (int) ($row['hadir'] ?? 0);
    $alpa = (int) ($row['alpa'] ?? 0);

    if ($totalSesi > 0) {
        $summary['students_with_record']++;
    }

    $summary['total_sesi'] += $totalSesi;
    $summary['hadir'] += $hadir;
    $summary['terlambat'] += (int) ($row['terlambat'] ?? 0);
    $summary['sakit'] += (int) ($row['sakit'] ?? 0);
    $summary['izin'] += (int) ($row['izin'] ?? 0);
    $summary['alpa'] += $alpa;

    $rate = $totalSesi > 0 ? (($hadir / $totalSesi) * 100) : 0;
    if ($totalSesi > 0 && ($alpa > 0 || $rate < 75)) {
        $summary['need_attention']++;
    }
}
?>

<div class="data-table-container">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-users text-primary me-2"></i>Daftar Siswa Yang Diajar</h5>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="?page=absensi">
                <i class="fas fa-rotate me-1"></i>Reset
            </a>
        </div>
    </div>

    <div class="filter-section mb-4">
        <form method="GET" action="" id="guruStudentFilterForm">
            <input type="hidden" name="page" value="absensi">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control datepicker" name="date_from" id="guruDateFrom" value="<?php echo htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control datepicker" name="date_to" id="guruDateTo" value="<?php echo htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelas</label>
                    <select class="form-select" name="class">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($classes as $class): ?>
                            <?php $classId = (string) ($class['class_id'] ?? ''); ?>
                            <option value="<?php echo htmlspecialchars($classId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filterClass === $classId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($class['class_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Terapkan
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="attendance-summary mb-4">
        <div class="summary-card">
            <div class="summary-value text-primary"><?php echo (int) $summary['total_students']; ?></div>
            <div class="summary-label">Total Siswa</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-info"><?php echo (int) $summary['students_with_record']; ?></div>
            <div class="summary-label">Siswa Terekap</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-success"><?php echo (int) $summary['hadir']; ?></div>
            <div class="summary-label">Hadir</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-warning"><?php echo (int) $summary['terlambat']; ?></div>
            <div class="summary-label">Terlambat</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-danger"><?php echo (int) $summary['alpa']; ?></div>
            <div class="summary-label">Alpa</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-secondary"><?php echo (int) $summary['need_attention']; ?></div>
            <div class="summary-label">Perlu Perhatian</div>
        </div>
    </div>

    <div class="guru-export-hub mb-4">
        <div class="guru-export-card">
            <div class="guru-export-icon">
                <i class="fas fa-file-excel"></i>
            </div>
            <div class="guru-export-body">
                <h6>Download Excel</h6>
                <p>Unduh rekap siswa per kelas untuk analisis lanjutan.</p>
            </div>
            <button type="button" class="guru-export-btn is-excel" data-export-table="guruStudentMonitorTable" data-export-action="excel">
                Unduh Excel
            </button>
        </div>
        <div class="guru-export-card">
            <div class="guru-export-icon">
                <i class="fas fa-file-pdf"></i>
            </div>
            <div class="guru-export-body">
                <h6>Download PDF</h6>
                <p>Simpan laporan rapi siap kirim ke wali kelas atau manajemen.</p>
            </div>
            <button type="button" class="guru-export-btn is-pdf" data-export-table="guruStudentMonitorTable" data-export-action="pdf">
                Unduh PDF
            </button>
        </div>
        <div class="guru-export-card">
            <div class="guru-export-icon">
                <i class="fas fa-print"></i>
            </div>
            <div class="guru-export-body">
                <h6>Cetak Laporan</h6>
                <p>Buka mode print untuk cetak cepat tanpa edit manual.</p>
            </div>
            <button type="button" class="guru-export-btn is-print" data-export-table="guruStudentMonitorTable" data-export-action="print">
                Print
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle data-table-export" id="guruStudentMonitorTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NISN</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Sesi Final</th>
                    <th>Hadir</th>
                    <th>Terlambat</th>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Alpa</th>
                    <th>% Kehadiran</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($studentRows)): ?>
                    <?php foreach ($studentRows as $index => $row): ?>
                        <?php
                        $totalSesi = (int) ($row['total_sesi'] ?? 0);
                        $hadir = (int) ($row['hadir'] ?? 0);
                        $alpa = (int) ($row['alpa'] ?? 0);
                        $rate = $totalSesi > 0 ? round(($hadir / $totalSesi) * 100, 1) : 0;
                        $statusLabel = 'Belum Ada Rekap';
                        $statusClass = 'secondary';
                        if ($totalSesi > 0) {
                            if ($alpa > 0 || $rate < 75) {
                                $statusLabel = 'Perlu Perhatian';
                                $statusClass = 'danger';
                            } else {
                                $statusLabel = 'Baik';
                                $statusClass = 'success';
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['student_nisn'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><strong><?php echo htmlspecialchars((string) ($row['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) ($row['class_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $totalSesi; ?></td>
                            <td><?php echo $hadir; ?></td>
                            <td><?php echo (int) ($row['terlambat'] ?? 0); ?></td>
                            <td><?php echo (int) ($row['sakit'] ?? 0); ?></td>
                            <td><?php echo (int) ($row['izin'] ?? 0); ?></td>
                            <td><?php echo $alpa; ?></td>
                            <td><?php echo number_format((float) $rate, 1); ?>%</td>
                            <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($studentRows)): ?>
        <div class="text-center py-5">
            <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">Tidak ada siswa pada filter yang dipilih.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fromEl = document.getElementById('guruDateFrom');
    const toEl = document.getElementById('guruDateTo');
    if (!fromEl || !toEl) {
        return;
    }

    const syncRange = function() {
        const from = fromEl.value || '';
        const to = toEl.value || '';
        toEl.min = from;
        if (from && to && to < from) {
            toEl.value = from;
        }
    };

    syncRange();
    fromEl.addEventListener('change', syncRange);
    toEl.addEventListener('change', syncRange);
});
</script>
