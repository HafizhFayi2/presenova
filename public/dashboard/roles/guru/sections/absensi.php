<?php
// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_class = $_GET['class'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Get classes taught by this teacher
$classes = $db->query("
    SELECT DISTINCT c.class_id, c.class_name
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    WHERE ts.teacher_id = ?
    ORDER BY c.class_name
", [$teacher_id])->fetchAll();

// Build query for attendance
$sql = "SELECT p.*, s.student_name, s.student_nisn, c.class_name, 
               ps.present_name, ts.subject, DATE_FORMAT(p.presence_date, '%d/%m/%Y') as formatted_date
        FROM presence p
        JOIN student s ON p.student_id = s.id
        JOIN class c ON s.class_id = c.class_id
        JOIN present_status ps ON p.present_id = ps.present_id
        JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
        JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
        WHERE ts.teacher_id = ?
        AND DATE(p.presence_date) = ?";
        
$params = [$teacher_id, $filter_date];

// Add class filter
if ($filter_class) {
    $sql .= " AND c.class_id = ?";
    $params[] = $filter_class;
}

// Add status filter
if ($filter_status) {
    $sql .= " AND p.present_id = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY c.class_name, s.student_name";

$stmt = $db->query($sql, $params);
$attendances = $stmt->fetchAll();

// Get attendance statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN p.present_id = 1 THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN p.present_id = 2 THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN p.present_id = 3 THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN p.present_id = 4 THEN 1 ELSE 0 END) as alpa,
                SUM(CASE WHEN p.is_late = 'Y' THEN 1 ELSE 0 END) as terlambat
              FROM presence p
              JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
              JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
              WHERE ts.teacher_id = ?
              AND DATE(p.presence_date) = ?";
              
if ($filter_class) {
    $stats_sql .= " AND p.student_id IN (SELECT id FROM student WHERE class_id = ?)";
    $stats_params = [$teacher_id, $filter_date, $filter_class];
} else {
    $stats_params = [$teacher_id, $filter_date];
}

$stats_stmt = $db->query($stats_sql, $stats_params);
$stats = $stats_stmt->fetch();
?>

<div class="data-table-container">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-clipboard-check text-primary me-2"></i>Rekap Absensi</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-success-custom" onclick="exportToExcel('attendanceTable', 'Absensi_<?php echo date('Y-m-d'); ?>')">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </button>
            <button class="btn btn-primary-custom" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
            </button>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="" id="filterForm">
            <input type="hidden" name="page" value="absensi">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" class="form-control datepicker" name="date" 
                           value="<?php echo $filter_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelas</label>
                    <select class="form-select" name="class">
                        <option value="">Semua Kelas</option>
                        <?php foreach($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>" 
                            <?php echo $filter_class == $class['class_id'] ? 'selected' : ''; ?>>
                            <?php echo $class['class_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <option value="1" <?php echo $filter_status == '1' ? 'selected' : ''; ?>>Hadir</option>
                        <option value="2" <?php echo $filter_status == '2' ? 'selected' : ''; ?>>Sakit</option>
                        <option value="3" <?php echo $filter_status == '3' ? 'selected' : ''; ?>>Izin</option>
                        <option value="4" <?php echo $filter_status == '4' ? 'selected' : ''; ?>>Alpa</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Statistics Summary -->
    <div class="attendance-summary mb-4">
        <div class="summary-card">
            <div class="summary-value text-primary"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="summary-label">Total</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-success"><?php echo $stats['hadir'] ?? 0; ?></div>
            <div class="summary-label">Hadir</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-warning"><?php echo $stats['terlambat'] ?? 0; ?></div>
            <div class="summary-label">Terlambat</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-info"><?php echo $stats['sakit'] ?? 0; ?></div>
            <div class="summary-label">Sakit</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-purple"><?php echo $stats['izin'] ?? 0; ?></div>
            <div class="summary-label">Izin</div>
        </div>
        <div class="summary-card">
            <div class="summary-value text-danger"><?php echo $stats['alpa'] ?? 0; ?></div>
            <div class="summary-label">Alpa</div>
        </div>
    </div>
    
    <!-- Attendance Table -->
    <div class="table-responsive">
        <table class="table table-hover data-table-export" id="attendanceTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NISN</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Mata Pelajaran</th>
                    <th>Status</th>
                    <th>Keterlambatan</th>
                    <th>Keterangan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($attendances as $index => $attendance): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo $attendance['student_nisn']; ?></td>
                    <td><strong><?php echo $attendance['student_name']; ?></strong></td>
                    <td><?php echo $attendance['class_name']; ?></td>
                    <td><?php echo $attendance['formatted_date']; ?></td>
                    <td><?php echo date('H:i', strtotime($attendance['time_in'])); ?></td>
                    <td><?php echo $attendance['subject']; ?></td>
                    <td>
                        <?php 
                        $badge_class = '';
                        switch($attendance['present_id']) {
                            case 1: $badge_class = $attendance['is_late'] == 'Y' ? 'status-late' : 'status-present'; break;
                            case 2: $badge_class = 'status-sick'; break;
                            case 3: $badge_class = 'status-permission'; break;
                            case 4: $badge_class = 'status-absent'; break;
                        }
                        ?>
                        <span class="status-badge <?php echo $badge_class; ?>">
                            <?php echo $attendance['present_name']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($attendance['is_late'] == 'Y'): ?>
                            <span class="text-warning">
                                <i class="fas fa-clock"></i> <?php echo $attendance['late_time']; ?> menit
                            </span>
                        <?php else: ?>
                            <span class="text-muted">Tepat waktu</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $attendance['information'] ?: '-'; ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info" 
                                onclick="viewAttendanceDetails(<?php echo $attendance['presence_id']; ?>)"
                                title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if($attendance['picture_in']): ?>
                        <button class="btn btn-sm btn-outline-success" 
                                onclick="viewPhoto('<?php echo $attendance['picture_in']; ?>')"
                                title="Lihat Foto">
                            <i class="fas fa-camera"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if(empty($attendances)): ?>
    <div class="text-center py-5">
        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
        <p class="text-muted">Tidak ada data absensi untuk filter yang dipilih</p>
    </div>
    <?php endif; ?>
</div>

<!-- Chart Section -->
<div class="data-table-container mt-4">
    <h5 class="table-title mb-3"><i class="fas fa-chart-bar text-info me-2"></i>Statistik Absensi</h5>
    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <canvas id="classAttendanceChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foto Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="attendancePhoto" src="" class="img-fluid rounded" style="max-height: 500px;">
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, {sheet: "Absensi"});
    XLSX.writeFile(wb, filename + '.xlsx');
}

function viewAttendanceDetails(presenceId) {
    // Implement modal for attendance details
    $.ajax({
        url: 'ajax/get_attendance_details.php',
        method: 'POST',
        data: { id: presenceId },
        success: function(response) {
            // Show details in modal
            $('#detailsModal .modal-body').html(response);
            $('#detailsModal').modal('show');
        }
    });
}

function viewPhoto(photoPath) {
    $('#attendancePhoto').attr('src', '../uploads/attendance/' + photoPath);
    $('#photoModal').modal('show');
}

$(document).ready(function() {
    // Initialize table
    const table = $('#attendanceTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
        },
        "pageLength": 10,
        "responsive": true,
        "dom": 'Bfrtip',
        "buttons": [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success',
                title: 'Absensi Guru - <?php echo date('d-m-Y'); ?>',
                filename: 'Absensi_<?php echo date('Y-m-d'); ?>',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger',
                title: 'Absensi Guru - <?php echo date('d-m-Y'); ?>',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-primary',
                title: 'Absensi Guru - <?php echo date('d-m-Y'); ?>',
                exportOptions: {
                    columns: ':visible'
                }
            }
        ]
    });
    
    // Initialize charts
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const attendanceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Sakit', 'Izin', 'Alpa'],
            datasets: [{
                data: [
                    <?php echo $stats['hadir'] ?? 0; ?>,
                    <?php echo $stats['sakit'] ?? 0; ?>,
                    <?php echo $stats['izin'] ?? 0; ?>,
                    <?php echo $stats['alpa'] ?? 0; ?>
                ],
                backgroundColor: [
                    '#10b981',
                    '#3b82f6',
                    '#8b5cf6',
                    '#ef4444'
                ],
                borderWidth: 2,
                borderColor: 'var(--card-color)'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'var(--text-color)'
                    }
                }
            }
        }
    });
    
    // Class attendance chart
    <?php
    $classStats = $db->query("
        SELECT c.class_name, 
               COUNT(p.presence_id) as total,
               SUM(CASE WHEN p.present_id = 1 THEN 1 ELSE 0 END) as hadir
        FROM presence p
        JOIN student s ON p.student_id = s.id
        JOIN class c ON s.class_id = c.class_id
        JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
        JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
        WHERE ts.teacher_id = ? 
        AND DATE(p.presence_date) = ?
        GROUP BY c.class_id
    ", [$teacher_id, $filter_date])->fetchAll();
    
    $classLabels = [];
    $classData = [];
    foreach($classStats as $stat) {
        $percentage = $stat['total'] > 0 ? round(($stat['hadir'] / $stat['total']) * 100) : 0;
        $classLabels[] = $stat['class_name'];
        $classData[] = $percentage;
    }
    ?>
    
    const classCtx = document.getElementById('classAttendanceChart').getContext('2d');
    const classAttendanceChart = new Chart(classCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($classLabels); ?>,
            datasets: [{
                label: 'Persentase Kehadiran (%)',
                data: <?php echo json_encode($classData); ?>,
                backgroundColor: 'rgba(26, 86, 219, 0.7)',
                borderColor: 'rgba(26, 86, 219, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        color: 'var(--text-color)'
                    },
                    grid: {
                        color: 'var(--border)'
                    }
                },
                x: {
                    ticks: {
                        color: 'var(--text-color)'
                    },
                    grid: {
                        color: 'var(--border)'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: 'var(--text-color)'
                    }
                }
            }
        }
    });
});
</script>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                Loading...
            </div>
        </div>
    </div>
</div>