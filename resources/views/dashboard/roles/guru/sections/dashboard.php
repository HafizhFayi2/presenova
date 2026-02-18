<?php
// Ambil jadwal mengajar hari ini
$today = date('N'); // 1=Monday, 7=Sunday
$stmt = $db->query("
    SELECT ts.*, c.class_name, d.day_name, sh.shift_name, sh.time_in, sh.time_out
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    JOIN day d ON ts.day_id = d.day_id
    JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE ts.teacher_id = ? AND ts.day_id = ?
    ORDER BY sh.time_in
", [$teacher_id, $today]);
$todaySchedules = $stmt->fetchAll();

foreach ($todaySchedules as &$schedule) {
    $computed = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', $schedule['day_id'] ?? 0);
    if ($computed) {
        $schedule['time_in'] = $computed[0];
        $schedule['time_out'] = $computed[1];
    }
}
unset($schedule);

// Ambil statistik absensi bulan ini
$currentMonth = date('Y-m');
$attendanceStats = $db->query("
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
    AND DATE_FORMAT(p.presence_date, '%Y-%m') = ?
", [$teacher_id, $currentMonth])->fetch();

// Ambil jadwal besok
$tomorrow = $today == 7 ? 1 : $today + 1;
$stmt = $db->query("
    SELECT ts.*, c.class_name, d.day_name, sh.shift_name, sh.time_in, sh.time_out
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    JOIN day d ON ts.day_id = d.day_id
    JOIN shift sh ON ts.shift_id = sh.shift_id
    WHERE ts.teacher_id = ? AND ts.day_id = ?
    ORDER BY sh.time_in
    LIMIT 3
", [$teacher_id, $tomorrow]);
$tomorrowSchedules = $stmt->fetchAll();

foreach ($tomorrowSchedules as &$schedule) {
    $computed = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', $schedule['day_id'] ?? 0);
    if ($computed) {
        $schedule['time_in'] = $computed[0];
        $schedule['time_out'] = $computed[1];
    }
}
unset($schedule);

// Hitung total siswa yang diajar
$totalStudents = $db->query("
    SELECT COUNT(DISTINCT s.id) as total
    FROM student s
    JOIN teacher_schedule ts ON s.class_id = ts.class_id
    WHERE ts.teacher_id = ?
", [$teacher_id])->fetch()['total'];
?>

<!-- Welcome Card -->
<div class="dashboard-card welcome-card mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h3 class="mb-2">Selamat datang, <?php echo $teacher['teacher_name']; ?>!</h3>
            <p class="mb-0">Anda mengajar <?php echo $teacher['subject']; ?> dengan <?php echo $totalStudents; ?> siswa</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="date-time-display d-inline-flex">
                <i class="fas fa-calendar-day"></i>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="dashboard-card stats-card">
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
            <h4 class="mb-2"><?php echo $totalStudents; ?></h4>
            <p class="mb-0 text-muted">Total Siswa</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="dashboard-card stats-card">
            <div class="card-icon gold">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <h4 class="mb-2"><?php echo $attendanceStats['hadir'] ?? 0; ?></h4>
            <p class="mb-0 text-muted">Hadir Bulan Ini</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="dashboard-card stats-card">
            <div class="card-icon green">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h4 class="mb-2"><?php echo count($todaySchedules); ?></h4>
            <p class="mb-0 text-muted">Jadwal Hari Ini</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="dashboard-card stats-card">
            <div class="card-icon purple">
                <i class="fas fa-chart-line"></i>
            </div>
            <h4 class="mb-2"><?php echo $attendanceStats['total'] ?? 0; ?></h4>
            <p class="mb-0 text-muted">Total Absensi</p>
        </div>
    </div>
</div>

<div class="row">
    <!-- Jadwal Hari Ini -->
    <div class="col-lg-8 mb-4">
        <div class="data-table-container">
            <div class="table-header">
                <h5 class="table-title"><i class="fas fa-calendar-day text-primary me-2"></i>Jadwal Mengajar Hari Ini</h5>
                <a href="?page=jadwal" class="btn btn-primary-custom">
                    <i class="fas fa-calendar-alt me-2"></i>Lihat Semua
                </a>
            </div>
            
            <?php if (count($todaySchedules) > 0): ?>
                <div class="row">
                    <?php foreach($todaySchedules as $schedule): 
                        // Cek apakah sudah absen untuk jadwal ini
                        $attendanceCheck = $db->query("
                            SELECT COUNT(*) as sudah_absen
                            FROM presence p
                            JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
                            WHERE ss.teacher_schedule_id = ?
                            AND DATE(p.presence_date) = CURDATE()
                        ", [$schedule['schedule_id']])->fetch()['sudah_absen'];
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="schedule-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="subject-name mb-1"><?php echo $schedule['subject']; ?></h6>
                                    <p class="subject-info mb-0">
                                        <i class="fas fa-clock text-primary me-1"></i>
                                        <?php echo date('H:i', strtotime($schedule['time_in'])) . ' - ' . date('H:i', strtotime($schedule['time_out'])); ?>
                                    </p>
                                </div>
                                <span class="badge bg-<?php echo $attendanceCheck > 0 ? 'success' : 'warning'; ?>">
                                    <?php echo $attendanceCheck > 0 ? 'Sudah Absen' : 'Belum Absen'; ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="mb-0"><i class="fas fa-school me-1"></i> <?php echo $schedule['class_name']; ?></p>
                                    <p class="mb-0"><i class="fas fa-user-clock me-1"></i> Sesi: <?php echo $schedule['shift_id']; ?></p>
                                </div>
                                <a href="attendance_class.php?schedule=<?php echo $schedule['schedule_id']; ?>" 
                                   class="btn btn-success-custom btn-sm">
                                    <i class="fas fa-clipboard-list me-1"></i> Absensi
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Tidak ada jadwal mengajar hari ini</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Jadwal Besok & Statistik -->
    <div class="col-lg-4 mb-4">
        <!-- Jadwal Besok -->
        <div class="data-table-container mb-4">
            <h5 class="table-title mb-3"><i class="fas fa-calendar-plus text-warning me-2"></i>Jadwal Besok</h5>
            
            <?php if (count($tomorrowSchedules) > 0): ?>
                <?php foreach($tomorrowSchedules as $schedule): ?>
                <div class="schedule-card mb-3">
                    <h6 class="subject-name mb-2"><?php echo $schedule['subject']; ?></h6>
                    <p class="mb-2"><i class="fas fa-clock text-warning me-1"></i> 
                        <?php echo date('H:i', strtotime($schedule['time_in'])) . ' - ' . date('H:i', strtotime($schedule['time_out'])); ?>
                    </p>
                    <p class="mb-0"><i class="fas fa-school me-1"></i> <?php echo $schedule['class_name']; ?></p>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($tomorrowSchedules) > 2): ?>
                <div class="text-center mt-3">
                    <a href="?page=jadwal" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-1"></i> Lihat Semua
                    </a>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-3">
                    <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                    <p class="text-muted">Tidak ada jadwal besok</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Stats -->
        <div class="data-table-container">
            <h5 class="table-title mb-3"><i class="fas fa-chart-pie text-info me-2"></i>Statistik Bulan Ini</h5>
            <div class="attendance-summary">
                <div class="summary-card">
                    <div class="summary-value text-primary"><?php echo $attendanceStats['hadir'] ?? 0; ?></div>
                    <div class="summary-label">Hadir</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value text-warning"><?php echo $attendanceStats['terlambat'] ?? 0; ?></div>
                    <div class="summary-label">Terlambat</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value text-info"><?php echo $attendanceStats['sakit'] ?? 0; ?></div>
                    <div class="summary-label">Sakit</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value text-danger"><?php echo $attendanceStats['alpa'] ?? 0; ?></div>
                    <div class="summary-label">Alpa</div>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="?page=laporan" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-chart-bar me-1"></i> Lihat Detail
                </a>
            </div>
        </div>
    </div>
</div>
