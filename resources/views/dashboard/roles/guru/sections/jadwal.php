<?php

$filter_day = trim((string)($_GET['day_id'] ?? ''));
$filter_class = trim((string)($_GET['class_id'] ?? ''));

$availableDays = $db->query(
    "SELECT DISTINCT d.day_id, d.day_name
     FROM teacher_schedule ts
     JOIN day d ON ts.day_id = d.day_id
     WHERE ts.teacher_id = ?
     ORDER BY d.day_id",
    [$teacher_id]
)->fetchAll();

$availableClasses = $db->query(
    "SELECT DISTINCT c.class_id, c.class_name
     FROM teacher_schedule ts
     JOIN class c ON ts.class_id = c.class_id
     WHERE ts.teacher_id = ?
     ORDER BY c.class_name",
    [$teacher_id]
)->fetchAll();

$sql = "
    SELECT
        ts.schedule_id,
        ts.day_id,
        d.day_name,
        c.class_name,
        ts.subject,
        sh.shift_name,
        sh.time_in,
        sh.time_out
    FROM teacher_schedule ts
    JOIN day d ON ts.day_id = d.day_id
    JOIN class c ON ts.class_id = c.class_id
    JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE ts.teacher_id = ?
";
$params = [$teacher_id];

if ($filter_day !== '') {
    $sql .= " AND ts.day_id = ?";
    $params[] = $filter_day;
}

if ($filter_class !== '') {
    $sql .= " AND ts.class_id = ?";
    $params[] = $filter_class;
}

$sql .= " ORDER BY ts.day_id ASC, sh.time_in ASC, c.class_name ASC";

$schedules = $db->query($sql, $params)->fetchAll();

foreach ($schedules as &$schedule) {
    $computed = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', (int)($schedule['day_id'] ?? 0));
    if ($computed) {
        $schedule['time_in'] = $computed[0];
        $schedule['time_out'] = $computed[1];
    }
}
unset($schedule);
?>

<div class="data-table-container">
    <div class="table-header">
        <h5 class="table-title">
            <i class="fas fa-calendar-alt text-primary me-2"></i>Jadwal Mengajar
        </h5>
        <div class="d-flex gap-2">
            <a href="?page=jadwal" class="btn btn-outline-secondary">
                <i class="fas fa-rotate me-2"></i>Reset
            </a>
            <button type="button" class="btn btn-primary-custom" onclick="return openGuruSchedulePrintDialog();">
                <i class="fas fa-print me-2"></i>Cetak
            </button>
        </div>
    </div>

    <div class="filter-section mb-3">
        <form method="GET" action="">
            <input type="hidden" name="page" value="jadwal">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Hari</label>
                    <select class="form-select" name="day_id">
                        <option value="">Semua Hari</option>
                        <?php foreach ($availableDays as $day): ?>
                        <option value="<?php echo (int)$day['day_id']; ?>" <?php echo $filter_day === (string)$day['day_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($day['day_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kelas</label>
                    <select class="form-select" name="class_id">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($availableClasses as $class): ?>
                        <option value="<?php echo (int)$class['class_id']; ?>" <?php echo $filter_class === (string)$class['class_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Terapkan Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="guruScheduleTable">
            <thead>
                <tr>
                    <th style="width: 80px;">No</th>
                    <th>Hari</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Shift</th>
                    <th>Jam Masuk</th>
                    <th>Jam Selesai</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($schedules)): ?>
                    <?php foreach ($schedules as $index => $schedule): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($schedule['day_name']); ?></td>
                        <td><?php echo htmlspecialchars($schedule['class_name']); ?></td>
                        <td><?php echo htmlspecialchars($schedule['subject']); ?></td>
                        <td><?php echo htmlspecialchars($schedule['shift_name']); ?></td>
                        <td><?php echo date('H:i', strtotime((string)$schedule['time_in'])); ?></td>
                        <td><?php echo date('H:i', strtotime((string)$schedule['time_out'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            Belum ada jadwal mengajar.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const guruPrintConfiguredBase = <?php
$guruPathBase = trim((string) request()->getBasePath(), '/');
if ($guruPathBase === '') {
    $guruPathBase = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
}
echo json_encode($guruPathBase);
?>;

function resolveGuruPrintPrefix() {
    const path = String(window.location.pathname || '');
    const marker = '/dashboard/';
    const markerIndex = path.toLowerCase().indexOf(marker);
    if (markerIndex > 0) {
        return path.slice(0, markerIndex).replace(/\/+$/, '');
    }
    if (guruPrintConfiguredBase) {
        return '/' + String(guruPrintConfiguredBase).replace(/^\/+|\/+$/g, '');
    }
    return '';
}

const guruPrintPrefix = resolveGuruPrintPrefix();

function openGuruSchedulePrintDialog() {
    const dayId = String(document.querySelector('select[name="day_id"]')?.value || '').trim();
    const classId = String(document.querySelector('select[name="class_id"]')?.value || '').trim();

    const baseUrl = new URL(guruPrintPrefix + '/dashboard/roles/guru/print/jadwal_print.php', window.location.origin);
    baseUrl.searchParams.set('t', String(Date.now()));
    if (dayId !== '') {
        baseUrl.searchParams.set('day_id', dayId);
    }
    if (classId !== '') {
        baseUrl.searchParams.set('class_id', classId);
    }

    const printUrl = new URL(baseUrl.toString());
    printUrl.searchParams.set('autoprint', '1');

    const pdfUrl = new URL(baseUrl.toString());
    pdfUrl.searchParams.set('download', 'pdf');

    if (window.SchedulePrintDialog && typeof window.SchedulePrintDialog.open === 'function') {
        window.SchedulePrintDialog.open({
            title: 'Output Jadwal Guru',
            message: 'Pilih Print untuk cetak langsung, atau Download PDF untuk menyimpan laporan jadwal.',
            printUrl: printUrl.toString(),
            pdfUrl: pdfUrl.toString()
        });
    } else {
        const popup = window.open('', '_blank');
        if (popup) {
            try {
                popup.opener = null;
            } catch (e) {}
            try {
                popup.location.replace(printUrl.toString());
            } catch (e) {
                popup.location.href = printUrl.toString();
            }
        } else {
            window.location.assign(printUrl.toString());
        }
    }

    return false;
}

$(document).ready(function() {
    if (!$.fn.DataTable) {
        return;
    }

    $('#guruScheduleTable').DataTable({
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data jadwal",
            infoFiltered: "(difilter dari _MAX_ total data)",
            zeroRecords: "Data tidak ditemukan",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            }
        },
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc'], [5, 'asc']],
        responsive: false,
        scrollX: false,
        scrollCollapse: false,
        autoWidth: true,
        initComplete: function() {
            this.api().columns.adjust();
        }
    });

    $(window).off('resize.guruScheduleAdjust').on('resize.guruScheduleAdjust', function() {
        $('#guruScheduleTable').DataTable().columns.adjust();
    });
});
</script>
