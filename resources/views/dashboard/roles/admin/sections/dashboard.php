<?php
// Get statistics
$stats = [];

// Total Students
$sql = "SELECT COUNT(*) as total FROM student";
$stmt = $db->query($sql);
$stats['students'] = $stmt->fetch()['total'];

// Total Teachers
$sql = "SELECT COUNT(*) as total FROM teacher";
$stmt = $db->query($sql);
$stats['teachers'] = $stmt->fetch()['total'];

// Total Classes
$sql = "SELECT COUNT(*) as total FROM class";
$stmt = $db->query($sql);
$stats['classes'] = $stmt->fetch()['total'];

// Today's Attendance
$today = date('Y-m-d');
$sql = "SELECT COUNT(*) as total FROM presence WHERE DATE(presence_date) = ?";
$stmt = $db->query($sql, [$today]);
$stats['attendance_today'] = $stmt->fetch()['total'];

// Recent Activities
$sql = "SELECT p.*, s.student_name, c.class_name 
        FROM presence p 
        JOIN student s ON p.student_id = s.id 
        JOIN class c ON s.class_id = c.class_id 
        ORDER BY p.presence_date DESC, p.time_in DESC 
        LIMIT 10";
$stmt = $db->query($sql);
$recent_activities = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-3">
        <a href="?table=student" class="dashboard-card clickable">
            <div class="card-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <h4 class="card-value"><?php echo $stats['students']; ?></h4>
            <p class="card-title">Total Siswa</p>
            <p class="card-change positive">
                <i class="fas fa-arrow-up"></i> Klik untuk melihat daftar
            </p>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?table=teacher" class="dashboard-card clickable">
            <div class="card-icon green">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <h4 class="card-value"><?php echo $stats['teachers']; ?></h4>
            <p class="card-title">Total Guru</p>
            <p class="card-change positive">
                <i class="fas fa-arrow-up"></i> Klik untuk melihat daftar
            </p>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?table=class" class="dashboard-card clickable">
            <div class="card-icon teal">
                <i class="fas fa-school"></i>
            </div>
            <h4 class="card-value"><?php echo $stats['classes']; ?></h4>
            <p class="card-title">Total Kelas</p>
            <p class="card-change positive">
                <i class="fas fa-arrow-up"></i> Klik untuk mengelola
            </p>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?table=attendance" class="dashboard-card clickable">
            <div class="card-icon purple">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <h4 class="card-value"><?php echo $stats['attendance_today']; ?></h4>
            <p class="card-title">Absensi Hari Ini</p>
            <p class="card-change positive">
                <i class="fas fa-arrow-up"></i> Klik untuk melihat detail
            </p>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="activity-table">
            <h5 class="mb-4"><i class="fas fa-history text-primary me-2"></i>Aktivitas Terbaru</h5>
            <div class="table-responsive">
                <table class="table table-hover no-card-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Waktu</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach($recent_activities as $activity): ?>
                            <tr>
                                <td><?php echo $activity['student_name']; ?></td>
                                <td><?php echo $activity['class_name']; ?></td>
                                <td><?php echo date('H:i', strtotime($activity['time_in'])); ?></td>
                                <td>
                                    <span class="badge badge-success">Hadir</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Belum ada aktivitas terbaru</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="quick-actions">
            <h5 class="mb-4"><i class="fas fa-bolt text-success me-2"></i>Quick Actions</h5>
            <a href="?table=student" class="quick-action-item" data-no-loading="1">
                <i class="fas fa-plus-circle"></i>
                <span>Tambah Siswa Baru</span>
            </a>
            <a href="?table=teacher" class="quick-action-item" data-no-loading="1">
                <i class="fas fa-user-plus"></i>
                <span>Tambah Guru Baru</span>
            </a>
            <a href="?table=schedule" class="quick-action-item" data-no-loading="1">
                <i class="fas fa-calendar-plus"></i>
                <span>Buat Jadwal Baru</span>
            </a>
            <a href="?table=attendance&export=today" class="quick-action-item" data-no-loading="1">
                <i class="fas fa-download"></i>
                <span>Export Absensi Hari Ini</span>
            </a>
            <a href="?table=system" class="quick-action-item" data-no-loading="1">
                <i class="fas fa-cog"></i>
                <span>Pengaturan Sistem</span>
            </a>
        </div>
    </div>
</div>
