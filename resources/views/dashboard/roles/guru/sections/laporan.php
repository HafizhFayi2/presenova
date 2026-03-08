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
$startDate = $parseDate($_GET['start_date'] ?? '', $tz, $defaultFrom);
$endDate = $parseDate($_GET['end_date'] ?? '', $tz, $defaultTo);
if ($startDate > $endDate) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
}
$filterClass = trim((string) ($_GET['class'] ?? ''));

// Kelas yang diampu guru.
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
$classNameById = [];
foreach ($classes as $classRow) {
    $classNameById[(string) ($classRow['class_id'] ?? '')] = (string) ($classRow['class_name'] ?? '-');
}

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

$newBucket = static function (): array {
    return [
        'total' => 0,
        'hadir' => 0,
        'terlambat' => 0,
        'sakit' => 0,
        'izin' => 0,
        'alpa' => 0,
    ];
};

// Dataset jadwal + presence untuk rentang laporan.
$reportSql = "
    SELECT
        ss.student_schedule_id,
        ss.student_id,
        ss.schedule_date,
        COALESCE(ss.time_in, sh.time_in, '00:00:00') as schedule_time_in,
        COALESCE(ss.time_out, sh.time_out, '00:00:00') as schedule_time_out,
        ts.class_id,
        c.class_name,
        s.student_nisn,
        s.student_name,
        p.present_id,
        p.is_late
    FROM student_schedule ss
    JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
    JOIN student s ON ss.student_id = s.id
    JOIN class c ON s.class_id = c.class_id
    LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
    LEFT JOIN presence p ON p.student_schedule_id = ss.student_schedule_id
    WHERE ts.teacher_id = ?
      AND ss.schedule_date BETWEEN ? AND ?
";
$reportParams = [$teacher_id, $startDate, $endDate];
if ($filterClass !== '') {
    $reportSql .= " AND ts.class_id = ?";
    $reportParams[] = $filterClass;
}
$reportSql .= " ORDER BY ss.schedule_date ASC";
$reportStmt = $db->query($reportSql, $reportParams);
$reportRows = $reportStmt ? $reportStmt->fetchAll() : [];

$overall = $newBucket();
$monthly = [];
$classStats = [];
$studentStats = [];

foreach ($reportRows as $row) {
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

    $monthKey = date('Y-m', strtotime($scheduleDate));
    if (!isset($monthly[$monthKey])) {
        $monthly[$monthKey] = $newBucket();
    }

    $classId = (string) ($row['class_id'] ?? '');
    if ($classId === '') {
        $classId = 'unknown';
    }
    if (!isset($classStats[$classId])) {
        $classStats[$classId] = array_merge($newBucket(), [
            'class_name' => (string) ($row['class_name'] ?? ($classNameById[$classId] ?? '-')),
        ]);
    }

    $studentId = (int) ($row['student_id'] ?? 0);
    if ($studentId > 0 && !isset($studentStats[$studentId])) {
        $studentStats[$studentId] = array_merge($newBucket(), [
            'student_id' => $studentId,
            'student_nisn' => (string) ($row['student_nisn'] ?? '-'),
            'student_name' => (string) ($row['student_name'] ?? '-'),
            'class_name' => (string) ($row['class_name'] ?? '-'),
        ]);
    }

    $applyStatus = static function (array &$bucket, int $presentId, string $statusName, string $isLate): void {
        $bucket['total']++;
        if ($presentId <= 0) {
            $bucket['alpa']++;
            return;
        }

        if ($statusName === 'hadir' || $presentId === 1) {
            $bucket['hadir']++;
            if ($isLate === 'Y') {
                $bucket['terlambat']++;
            }
            return;
        }
        if ($statusName === 'sakit' || $presentId === 2) {
            $bucket['sakit']++;
            return;
        }
        if ($statusName === 'izin' || $presentId === 3) {
            $bucket['izin']++;
            return;
        }
        if ($statusName === 'alpa' || $statusName === 'tidak hadir' || $presentId === 4) {
            $bucket['alpa']++;
            return;
        }
    };

    $statusName = $statusMap[(string) $presentId] ?? '';
    $isLate = (string) ($row['is_late'] ?? 'N');

    $applyStatus($overall, $presentId, $statusName, $isLate);
    $applyStatus($monthly[$monthKey], $presentId, $statusName, $isLate);
    $applyStatus($classStats[$classId], $presentId, $statusName, $isLate);
    if ($studentId > 0 && isset($studentStats[$studentId])) {
        $applyStatus($studentStats[$studentId], $presentId, $statusName, $isLate);
    }
}

$calcRate = static function (array $bucket): float {
    $total = (int) ($bucket['total'] ?? 0);
    $hadir = (int) ($bucket['hadir'] ?? 0);
    if ($total <= 0) {
        return 0.0;
    }
    return round(($hadir / $total) * 100, 1);
};

$monthlyRows = [];
foreach ($monthly as $monthKey => $bucket) {
    $monthlyRows[] = array_merge($bucket, [
        'month_key' => $monthKey,
        'attendance_rate' => $calcRate($bucket),
    ]);
}
usort($monthlyRows, static function (array $a, array $b): int {
    return strcmp((string) ($b['month_key'] ?? ''), (string) ($a['month_key'] ?? ''));
});

$classRows = array_values($classStats);
foreach ($classRows as &$classRow) {
    $classRow['attendance_rate'] = $calcRate($classRow);
}
unset($classRow);
usort($classRows, static function (array $a, array $b): int {
    $rateCompare = ($b['attendance_rate'] <=> $a['attendance_rate']);
    if ($rateCompare !== 0) {
        return $rateCompare;
    }
    return strcmp((string) ($a['class_name'] ?? ''), (string) ($b['class_name'] ?? ''));
});

$studentRows = array_values($studentStats);
foreach ($studentRows as &$studentRow) {
    $studentRow['attendance_rate'] = $calcRate($studentRow);
}
unset($studentRow);
usort($studentRows, static function (array $a, array $b): int {
    $classCompare = strcmp((string) ($a['class_name'] ?? ''), (string) ($b['class_name'] ?? ''));
    if ($classCompare !== 0) {
        return $classCompare;
    }
    return strcmp((string) ($a['student_name'] ?? ''), (string) ($b['student_name'] ?? ''));
});

$selectedClassLabel = 'Semua Kelas';
if ($filterClass !== '' && isset($classNameById[$filterClass])) {
    $selectedClassLabel = $classNameById[$filterClass];
}
?>

<div class="data-table-container">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-chart-bar text-primary me-2"></i>Laporan Rekap Guru</h5>
        <div class="d-flex gap-2">
            <a href="?page=laporan" class="btn btn-outline-secondary">
                <i class="fas fa-rotate me-1"></i>Reset
            </a>
        </div>
    </div>

    <div class="filter-section mb-4">
        <form method="GET" action="" id="guruReportFilterForm">
            <input type="hidden" name="page" value="laporan">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control datepicker" id="guruReportStartDate" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control datepicker" id="guruReportEndDate" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
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

    <div class="mb-3 text-muted small">
        Periode: <strong><?php echo date('d M Y', strtotime($startDate)); ?></strong> -
        <strong><?php echo date('d M Y', strtotime($endDate)); ?></strong>,
        Kelas: <strong><?php echo htmlspecialchars($selectedClassLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
    </div>

    <div class="attendance-summary mb-4">
        <div class="summary-card">
            <div class="summary-value text-primary"><?php echo (int) ($overall['total'] ?? 0); ?></div>
            <div class="summary-label">Total Sesi Final</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-success"><?php echo (int) ($overall['hadir'] ?? 0); ?></div>
            <div class="summary-label">Hadir</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-warning"><?php echo (int) ($overall['terlambat'] ?? 0); ?></div>
            <div class="summary-label">Terlambat</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-info"><?php echo (int) ($overall['sakit'] ?? 0); ?></div>
            <div class="summary-label">Sakit</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-purple"><?php echo (int) ($overall['izin'] ?? 0); ?></div>
            <div class="summary-label">Izin</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-danger"><?php echo (int) ($overall['alpa'] ?? 0); ?></div>
            <div class="summary-label">Alpa</div>
        </div>
    </div>

    <h6 class="mb-3">Rekap Bulanan</h6>
    <div class="table-responsive mb-4">
        <table class="table table-hover align-middle data-table" id="guruMonthlyReportTable">
            <thead>
                <tr>
                    <th>Bulan</th>
                    <th>Total</th>
                    <th>Hadir</th>
                    <th>Terlambat</th>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Tidak Hadir (Alpa)</th>
                    <th>% Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthlyRows as $row): ?>
                    <?php $rate = (float) ($row['attendance_rate'] ?? 0); ?>
                    <tr>
                        <td><strong><?php echo date('F Y', strtotime((string) ($row['month_key'] ?? '') . '-01')); ?></strong></td>
                        <td><?php echo (int) ($row['total'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['hadir'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['terlambat'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['sakit'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['izin'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['alpa'] ?? 0); ?></td>
                        <td><?php echo number_format($rate, 1); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h6 class="mb-3">Performa Per Kelas</h6>
    <div class="table-responsive mb-4">
        <table class="table table-hover align-middle data-table" id="guruClassReportTable">
            <thead>
                <tr>
                    <th>Kelas</th>
                    <th>Total</th>
                    <th>Hadir</th>
                    <th>Terlambat</th>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Tidak Hadir (Alpa)</th>
                    <th>% Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classRows as $row): ?>
                    <?php $rate = (float) ($row['attendance_rate'] ?? 0); ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars((string) ($row['class_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                        <td><?php echo (int) ($row['total'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['hadir'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['terlambat'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['sakit'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['izin'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['alpa'] ?? 0); ?></td>
                        <td><?php echo number_format($rate, 1); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="guru-export-hub mb-4">
        <div class="guru-export-card">
            <div class="guru-export-icon">
                <i class="fas fa-file-excel"></i>
            </div>
            <div class="guru-export-body">
                <h6>Download Excel</h6>
                <p>Ekspor rekap per siswa ke Excel untuk pengolahan lanjutan.</p>
            </div>
            <button type="button" class="guru-export-btn is-excel" data-export-table="guruStudentReportTable" data-export-action="excel">
                Unduh Excel
            </button>
        </div>
        <div class="guru-export-card">
            <div class="guru-export-icon">
                <i class="fas fa-file-pdf"></i>
            </div>
            <div class="guru-export-body">
                <h6>Download PDF</h6>
                <p>Hasilkan PDF siap arsip atau dikirim ke wali kelas.</p>
            </div>
            <button type="button" class="guru-export-btn is-pdf" data-export-table="guruStudentReportTable" data-export-action="pdf">
                Unduh PDF
            </button>
        </div>
        <div class="guru-export-card">
            <div class="guru-export-icon">
                <i class="fas fa-print"></i>
            </div>
            <div class="guru-export-body">
                <h6>Cetak Laporan</h6>
                <p>Masuk mode print dengan layout tabel yang rapi dan siap cetak.</p>
            </div>
            <button type="button" class="guru-export-btn is-print" data-export-table="guruStudentReportTable" data-export-action="print">
                Print
            </button>
        </div>
    </div>

    <h6 class="mb-3">Rekap Detail Per Siswa</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle data-table-export" id="guruStudentReportTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NISN</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Total</th>
                    <th>Hadir</th>
                    <th>Terlambat</th>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Tidak Hadir (Alpa)</th>
                    <th>% Kehadiran</th>
                    <th>Status Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studentRows as $index => $row): ?>
                    <?php
                    $total = (int) ($row['total'] ?? 0);
                    $hadir = (int) ($row['hadir'] ?? 0);
                    $alpa = (int) ($row['alpa'] ?? 0);
                    $rate = (float) ($row['attendance_rate'] ?? 0);
                    $statusLabel = 'Belum Ada Rekap';
                    $statusClass = 'secondary';
                    if ($total > 0) {
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
                        <td><?php echo $total; ?></td>
                        <td><?php echo $hadir; ?></td>
                        <td><?php echo (int) ($row['terlambat'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['sakit'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['izin'] ?? 0); ?></td>
                        <td><?php echo $alpa; ?></td>
                        <td><?php echo number_format($rate, 1); ?>%</td>
                        <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($studentRows)): ?>
        <div class="text-center py-5">
            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">Belum ada data rekap final pada periode ini.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startEl = document.getElementById('guruReportStartDate');
    const endEl = document.getElementById('guruReportEndDate');
    if (!startEl || !endEl) {
        return;
    }

    const syncRange = function() {
        const start = startEl.value || '';
        const end = endEl.value || '';
        endEl.min = start;
        if (start && end && end < start) {
            endEl.value = start;
        }
    };

    syncRange();
    startEl.addEventListener('change', syncRange);
    endEl.addEventListener('change', syncRange);
});
</script>
