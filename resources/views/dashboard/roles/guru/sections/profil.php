<?php
// Get teacher schedule summary
$scheduleSummary = $db->query("
    SELECT 
        COUNT(DISTINCT ts.schedule_id) as total_schedule,
        COUNT(DISTINCT c.class_id) as total_classes,
        COUNT(DISTINCT s.id) as total_students,
        GROUP_CONCAT(DISTINCT c.class_name ORDER BY c.class_name SEPARATOR ', ') as classes
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    LEFT JOIN student s ON c.class_id = s.class_id
    WHERE ts.teacher_id = ?
", [$teacher_id])->fetch();

// Get recent activities
$recentActivities = $db->query("
    SELECT 
        'Absensi' as type,
        CONCAT('Mengambil absensi untuk ', c.class_name) as description,
        p.presence_date as date
    FROM presence p
    JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
    JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
    JOIN class c ON ts.class_id = c.class_id
    WHERE ts.teacher_id = ?
    UNION
    SELECT 
        'Jadwal' as type,
        CONCAT('Mengajar ', ts.subject, ' di ', c.class_name) as description,
        CURDATE() as date
    FROM teacher_schedule ts
    JOIN class c ON ts.class_id = c.class_id
    WHERE ts.teacher_id = ?
    ORDER BY date DESC
    LIMIT 5
", [$teacher_id, $teacher_id])->fetchAll();
?>

<div class="row">
    <!-- Profile Info -->
    <div class="col-md-4 mb-4">
        <div class="dashboard-card">
            <div class="text-center mb-4">
                <div class="user-avatar mx-auto" style="width: 100px; height: 100px; font-size: 2.5rem;">
                    <?php echo strtoupper(substr($teacher['teacher_name'], 0, 1)); ?>
                </div>
                <h4 class="mt-3 mb-1"><?php echo $teacher['teacher_name']; ?></h4>
                <p class="text-muted mb-3"><?php echo $teacher['subject']; ?></p>
                <span class="badge bg-primary"><?php echo $teacher['teacher_type']; ?></span>
            </div>
            
            <div class="profile-info">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Kode Guru</span>
                    <strong><?php echo $teacher['teacher_code']; ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Tipe Guru</span>
                    <strong><?php echo $teacher['teacher_type']; ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Total Kelas</span>
                    <strong><?php echo $scheduleSummary['total_classes']; ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Total Siswa</span>
                    <strong><?php echo $scheduleSummary['total_students']; ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Total Jadwal</span>
                    <strong><?php echo $scheduleSummary['total_schedule']; ?></strong>
                </div>
            </div>
            
            <div class="mt-4 pt-3 border-top">
                <button class="btn btn-outline-primary w-100" onclick="changePassword()">
                    <i class="fas fa-key me-2"></i>Ganti Password
                </button>
            </div>
        </div>
    </div>
    
    <!-- Teaching Info -->
    <div class="col-md-8">
        <div class="data-table-container mb-4">
            <h5 class="table-title mb-3"><i class="fas fa-chalkboard-teacher text-primary me-2"></i>Informasi Mengajar</h5>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="dashboard-card h-100">
                        <div class="card-icon">
                            <i class="fas fa-school"></i>
                        </div>
                        <h4 class="mb-2"><?php echo $scheduleSummary['total_classes']; ?></h4>
                        <p class="mb-0 text-muted">Kelas yang Diajar</p>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="dashboard-card h-100">
                        <div class="card-icon gold">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="mb-2"><?php echo $scheduleSummary['total_students']; ?></h4>
                        <p class="mb-0 text-muted">Total Siswa</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <h6 class="mb-3">Daftar Kelas</h6>
                <?php if($scheduleSummary['classes']): 
                    $classes = explode(', ', $scheduleSummary['classes']);
                ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach($classes as $class): ?>
                    <span class="badge bg-primary p-2"><?php echo $class; ?></span>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">Belum ada kelas yang diajar</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="data-table-container">
            <h5 class="table-title mb-3"><i class="fas fa-history text-info me-2"></i>Aktivitas Terbaru</h5>
            
            <?php if(count($recentActivities) > 0): ?>
            <div class="timeline">
                <?php foreach($recentActivities as $activity): ?>
                <div class="timeline-item mb-3">
                    <div class="d-flex">
                        <div class="timeline-icon me-3">
                            <i class="fas fa-circle text-<?php echo $activity['type'] == 'Absensi' ? 'success' : 'primary'; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <h6 class="mb-1"><?php echo $activity['description']; ?></h6>
                            <p class="text-muted mb-0 small">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('d M Y', strtotime($activity['date'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <p class="text-muted">Belum ada aktivitas terbaru</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ganti Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ajax/change_password.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Password Lama</label>
                        <input type="password" class="form-control" name="old_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changePassword() {
    $('#changePasswordModal').modal('show');
}

// Handle password change form submission
$('#changePasswordModal form').on('submit', function(e) {
    e.preventDefault();
    
    const form = $(this);
    const formData = form.serialize();
    
    $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: formData,
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if(result.success) {
                    alert('Password berhasil diubah');
                    $('#changePasswordModal').modal('hide');
                    form[0].reset();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch(e) {
                alert('Terjadi kesalahan saat memproses');
            }
        },
        error: function() {
            alert('Gagal mengubah password. Silakan coba lagi.');
        }
    });
});
</script>
