<?php
// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'monthly';

// Get attendance summary
$summary = $db->query("
    SELECT 
        DATE_FORMAT(p.presence_date, '%Y-%m') as month,
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
    AND p.presence_date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(p.presence_date, '%Y-%m')
    ORDER BY month DESC
", [$teacher_id, $start_date, $end_date])->fetchAll();

// Get class-wise statistics
$classStats = $db->query("
    SELECT 
        c.class_name,
        COUNT(*) as total,
        SUM(CASE WHEN p.present_id = 1 THEN 1 ELSE 0 END) as hadir,
        ROUND(SUM(CASE WHEN p.present_id = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as attendance_rate
    FROM presence p
    JOIN student s ON p.student_id = s.id
    JOIN class c ON s.class_id = c.class_id
    JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
    JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
    WHERE ts.teacher_id = ?
    AND p.presence_date BETWEEN ? AND ?
    GROUP BY c.class_id
    ORDER BY attendance_rate DESC
", [$teacher_id, $start_date, $end_date])->fetchAll();
?>

<div class="data-table-container">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-chart-bar text-primary me-2"></i>Laporan & Statistik</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-success-custom" onclick="exportReport()">
                <i class="fas fa-file-excel me-2"></i>Export Laporan
            </button>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="" id="reportFilterForm">
            <input type="hidden" name="page" value="laporan">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control datepicker" name="start_date" 
                           value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control datepicker" name="end_date" 
                           value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipe Laporan</label>
                    <select class="form-select" name="report_type">
                        <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                        <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Mingguan</option>
                        <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Harian</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-chart-line me-2"></i>Generate
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h5 class="mb-3">Ringkasan Periode <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></h5>
            <div class="attendance-summary">
                <?php
                $total_summary = $db->query("
                    SELECT 
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
                    AND p.presence_date BETWEEN ? AND ?
                ", [$teacher_id, $start_date, $end_date])->fetch();
                ?>
                <div class="summary-card">
                    <div class="summary-value text-primary"><?php echo $total_summary['total'] ?? 0; ?></div>
                    <div class="summary-label">Total</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value text-success"><?php echo $total_summary['hadir'] ?? 0; ?></div>
                    <div class="summary-label">Hadir</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value text-warning"><?php echo $total_summary['terlambat'] ?? 0; ?></div>
                    <div class="summary-label">Terlambat</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value text-info"><?php echo $total_summary['sakit'] ?? 0; ?></div>
                    <div class="summary-label">Sakit</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value text-purple"><?php echo $total_summary['izin'] ?? 0; ?></div>
                    <div class="summary-label">Izin</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value text-danger"><?php echo $total_summary['alpa'] ?? 0; ?></div>
                    <div class="summary-label">Alpa</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="chart-container">
                <h6 class="mb-3">Trend Kehadiran Bulanan</h6>
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h6 class="mb-3">Persentase Kehadiran per Kelas</h6>
                <canvas id="classPerformanceChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Detailed Report Table -->
    <div class="table-responsive">
        <table class="table table-hover data-table-export" id="reportTable">
            <thead>
                <tr>
                    <th>Bulan</th>
                    <th>Total</th>
                    <th>Hadir</th>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Alpa</th>
                    <th>Terlambat</th>
                    <th>% Hadir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($summary as $row): 
                    $attendance_rate = $row['total'] > 0 ? round(($row['hadir'] / $row['total']) * 100, 1) : 0;
                ?>
                <tr>
                    <td><strong><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></strong></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php echo $row['hadir']; ?></td>
                    <td><?php echo $row['sakit']; ?></td>
                    <td><?php echo $row['izin']; ?></td>
                    <td><?php echo $row['alpa']; ?></td>
                    <td><?php echo $row['terlambat']; ?></td>
                    <td>
                        <?php 
                        $color_class = '';
                        if($attendance_rate >= 80) $color_class = 'text-success';
                        elseif($attendance_rate >= 60) $color_class = 'text-warning';
                        else $color_class = 'text-danger';
                        ?>
                        <span class="<?php echo $color_class; ?> fw-bold">
                            <?php echo $attendance_rate; ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Class Performance Table -->
    <div class="mt-4">
        <h5 class="mb-3">Performansi Kelas</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Kelas</th>
                        <th>Total Absensi</th>
                        <th>Hadir</th>
                        <th>% Kehadiran</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($classStats as $stat): 
                        $color_class = '';
                        $status = '';
                        if($stat['attendance_rate'] >= 90) {
                            $color_class = 'text-success';
                            $status = 'Sangat Baik';
                        } elseif($stat['attendance_rate'] >= 80) {
                            $color_class = 'text-success';
                            $status = 'Baik';
                        } elseif($stat['attendance_rate'] >= 70) {
                            $color_class = 'text-warning';
                            $status = 'Cukup';
                        } else {
                            $color_class = 'text-danger';
                            $status = 'Perlu Perhatian';
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo $stat['class_name']; ?></strong></td>
                        <td><?php echo $stat['total']; ?></td>
                        <td><?php echo $stat['hadir']; ?></td>
                        <td class="<?php echo $color_class; ?> fw-bold"><?php echo $stat['attendance_rate']; ?>%</td>
                        <td><span class="badge bg-<?php echo $color_class == 'text-success' ? 'success' : ($color_class == 'text-warning' ? 'warning' : 'danger'); ?>">
                            <?php echo $status; ?>
                        </span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportReport() {
    const table = document.getElementById('reportTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Laporan Absensi"});
    XLSX.writeFile(wb, 'Laporan_Absensi_<?php echo date('Y-m-d'); ?>.xlsx');
}

$(document).ready(function() {
    // Initialize table
    const table = $('#reportTable').DataTable({
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
                title: 'Laporan Absensi Guru - <?php echo $teacher['teacher_name']; ?>',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger',
                title: 'Laporan Absensi Guru - <?php echo $teacher['teacher_name']; ?>',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-primary',
                title: 'Laporan Absensi Guru - <?php echo $teacher['teacher_name']; ?>',
                exportOptions: {
                    columns: ':visible'
                }
            }
        ]
    });
    
    // Monthly trend chart
    <?php
    $monthLabels = [];
    $monthData = [];
    foreach($summary as $row) {
        $monthLabels[] = date('M Y', strtotime($row['month'] . '-01'));
        $attendance_rate = $row['total'] > 0 ? round(($row['hadir'] / $row['total']) * 100, 1) : 0;
        $monthData[] = $attendance_rate;
    }
    ?>
    
    const trendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    const monthlyTrendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Persentase Kehadiran (%)',
                data: <?php echo json_encode($monthData); ?>,
                borderColor: 'rgb(26, 86, 219)',
                backgroundColor: 'rgba(26, 86, 219, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
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
    
    // Class performance chart
    <?php
    $classLabels = [];
    $classPerformance = [];
    foreach($classStats as $stat) {
        $classLabels[] = $stat['class_name'];
        $classPerformance[] = $stat['attendance_rate'];
    }
    ?>
    
    const classCtx = document.getElementById('classPerformanceChart').getContext('2d');
    const classPerformanceChart = new Chart(classCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($classLabels); ?>,
            datasets: [{
                label: 'Persentase Kehadiran (%)',
                data: <?php echo json_encode($classPerformance); ?>,
                backgroundColor: [
                    'rgba(16, 185, 129, 0.7)',
                    'rgba(59, 130, 246, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(139, 92, 246, 0.7)',
                    'rgba(239, 68, 68, 0.7)'
                ],
                borderColor: [
                    'rgb(16, 185, 129)',
                    'rgb(59, 130, 246)',
                    'rgb(245, 158, 11)',
                    'rgb(139, 92, 246)',
                    'rgb(239, 68, 68)'
                ],
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