<?php
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth.php';
require_once '../../../../includes/database.php';
require_once '../../../../helpers/jp_time_helper.php';

$auth = new Auth();
if (
    !$auth->isLoggedIn()
    || isset($_SESSION['student_id'])
    || isset($_SESSION['teacher_id'])
    || !isset($_SESSION['level'])
    || !in_array((int) $_SESSION['level'], [1, 2], true)
) {
    header('Location: ../../../../login.php');
    exit();
}

$db = new Database();

$filter_day = isset($_GET['filter_day']) && ctype_digit((string) $_GET['filter_day']) ? (int) $_GET['filter_day'] : 0;
$filter_shift = isset($_GET['filter_shift']) && ctype_digit((string) $_GET['filter_shift']) ? (int) $_GET['filter_shift'] : 0;
$filter_teacher = isset($_GET['filter_teacher']) && ctype_digit((string) $_GET['filter_teacher']) ? (int) $_GET['filter_teacher'] : 0;
$filter_class = isset($_GET['filter_class']) && ctype_digit((string) $_GET['filter_class']) ? (int) $_GET['filter_class'] : 0;

$sql = "
    SELECT
        ts.schedule_id,
        ts.day_id,
        d.day_name,
        d.day_order,
        t.teacher_name,
        c.class_name,
        ts.subject,
        sh.shift_name,
        sh.time_in,
        sh.time_out
    FROM teacher_schedule ts
    LEFT JOIN teacher t ON ts.teacher_id = t.id
    LEFT JOIN class c ON ts.class_id = c.class_id
    LEFT JOIN day d ON ts.day_id = d.day_id
    LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE 1=1
";
$params = [];

if ($filter_day > 0) {
    $sql .= " AND ts.day_id = ?";
    $params[] = $filter_day;
}
if ($filter_shift > 0) {
    $sql .= " AND ts.shift_id = ?";
    $params[] = $filter_shift;
}
if ($filter_teacher > 0) {
    $sql .= " AND ts.teacher_id = ?";
    $params[] = $filter_teacher;
}
if ($filter_class > 0) {
    $sql .= " AND ts.class_id = ?";
    $params[] = $filter_class;
}

$sql .= " ORDER BY d.day_order ASC, sh.time_in ASC, ts.schedule_id ASC";
$stmt = $db->query($sql, $params);
$schedules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

foreach ($schedules as &$schedule) {
    $computed = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', (int) ($schedule['day_id'] ?? 0));
    if ($computed) {
        $schedule['time_in'] = $computed[0];
        $schedule['time_out'] = $computed[1];
    }
}
unset($schedule);

$lookupLabel = static function ($db, $table, $idColumn, $labelColumn, $id, $fallback) {
    if ($id <= 0) {
        return $fallback;
    }
    $stmt = $db->query("SELECT {$labelColumn} AS label FROM {$table} WHERE {$idColumn} = ? LIMIT 1", [$id]);
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return !empty($row['label']) ? (string) $row['label'] : $fallback;
};

$day_label = $lookupLabel($db, 'day', 'day_id', 'day_name', $filter_day, 'Semua Hari');
$shift_label = $lookupLabel($db, 'shift', 'shift_id', 'shift_name', $filter_shift, 'Semua Shift');
$teacher_label = $lookupLabel($db, 'teacher', 'id', 'teacher_name', $filter_teacher, 'Semua Guru');
$class_label = $lookupLabel($db, 'class', 'class_id', 'class_name', $filter_class, 'Semua Kelas');

$tz = new DateTimeZone('Asia/Jakarta');
$now_wib = new DateTime('now', $tz);
$printed_at = $now_wib->format('d F Y H:i:s') . ' WIB';
$printed_by = trim((string) ($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Administrator'));
if ($printed_by === '') {
    $printed_by = 'Administrator';
}

$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';
$download_pdf = isset($_GET['download']) && $_GET['download'] === 'pdf';
$orientation = strtolower((string) ($_GET['orientation'] ?? 'landscape'));
if (!in_array($orientation, ['auto', 'portrait', 'landscape'], true)) {
    $orientation = 'landscape';
}

$page_size_css = '';
if ($orientation === 'portrait') {
    $page_size_css = '@media print { @page { size: A4 portrait; } }';
} elseif ($orientation === 'landscape') {
    $page_size_css = '@media print { @page { size: A4 landscape; } }';
}

$pdf_orientation = $orientation === 'portrait' ? 'portrait' : 'landscape';
$pdf_filename = 'jadwal_admin_' . $now_wib->format('Ymd_His') . '.pdf';

$logo_base64 = '';
$logo_path = __DIR__ . '/../../../../assets/images/presenova.png';
if (!is_file($logo_path)) {
    $logo_path = __DIR__ . '/../../../../assets/images/logo-192.png';
}
if (is_file($logo_path)) {
    $logo_base64 = base64_encode((string) file_get_contents($logo_path));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Mengajar Admin - Presenova</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../../assets/css/print/jadwal-print.css?v=20260217a">
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
                    <h1>Jadwal Mengajar</h1>
                    <p>Panel Administrator / Operator</p>
                    <p class="academic-year">Presenova</p>
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
                <span>Filter Hari</span>
                <strong><?php echo htmlspecialchars($day_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="info-item">
                <span>Filter Shift</span>
                <strong><?php echo htmlspecialchars($shift_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="info-item">
                <span>Filter Guru</span>
                <strong><?php echo htmlspecialchars($teacher_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="info-item">
                <span>Filter Kelas</span>
                <strong><?php echo htmlspecialchars($class_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </section>

        <section class="table-section">
            <h2>Daftar Jadwal</h2>
            <?php if (!empty($schedules)): ?>
                <div class="table-wrap">
                    <table class="schedule-table" aria-label="Jadwal Mengajar Admin">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Hari</th>
                                <th>Shift</th>
                                <th>Waktu</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $index => $schedule): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($schedule['day_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($schedule['shift_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="cell-time">
                                        <?php echo date('H:i', strtotime((string) ($schedule['time_in'] ?? '00:00:00'))); ?>
                                        -
                                        <?php echo date('H:i', strtotime((string) ($schedule['time_out'] ?? '00:00:00'))); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) ($schedule['subject'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($schedule['teacher_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($schedule['class_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">Tidak ada data jadwal sesuai filter saat ini.</p>
            <?php endif; ?>
        </section>

        <section class="summary-strip section-keep">
            <div class="summary-item">
                <span>Total Jadwal</span>
                <strong><?php echo count($schedules); ?></strong>
            </div>
            <div class="summary-item">
                <span>Hari</span>
                <strong><?php echo htmlspecialchars($day_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="summary-item">
                <span>Shift</span>
                <strong><?php echo htmlspecialchars($shift_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="summary-item">
                <span>Guru</span>
                <strong><?php echo htmlspecialchars($teacher_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="summary-item">
                <span>Kelas</span>
                <strong><?php echo htmlspecialchars($class_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </section>

        <footer class="sheet-footer section-keep">
            <div>Presenova - Bringing Back Learning Time</div>
            <div>Printed at <?php echo htmlspecialchars($printed_at, ENT_QUOTES, 'UTF-8'); ?> by <?php echo htmlspecialchars($printed_by, ENT_QUOTES, 'UTF-8'); ?></div>
        </footer>
    </main>

    <?php if ($download_pdf): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    window.addEventListener('load', function () {
        const target = document.querySelector('.print-sheet');
        if (!target || typeof window.html2pdf === 'undefined') {
            window.print();
            return;
        }

        const opts = {
            margin: [8, 6, 8, 6],
            filename: <?php echo json_encode($pdf_filename); ?>,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: <?php echo json_encode($pdf_orientation); ?> },
            pagebreak: { mode: ['css', 'legacy'] }
        };

        setTimeout(function () {
            window.html2pdf().set(opts).from(target).save().then(function () {
                setTimeout(function () {
                    window.close();
                }, 250);
            });
        }, 220);
    });
    </script>
    <?php elseif ($autoprint): ?>
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
