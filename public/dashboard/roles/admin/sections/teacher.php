<?php
// Get all teachers
$sql = "SELECT * FROM teacher ORDER BY teacher_code";
$stmt = $db->query($sql);
$teachers = $stmt->fetchAll();
?>

<div class="data-table-container">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-chalkboard-teacher text-primary me-2"></i>Daftar Guru</h5>
        <button class="btn btn-primary add-btn" data-table="teacher">
            <i class="fas fa-plus-circle me-2"></i>Tambah Guru
        </button>
    </div>
    
    <!-- Filters -->
    <div class="filter-section">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Cari Guru</label>
                <input type="text" class="form-control" id="searchTeacher" placeholder="Cari berdasarkan nama atau username...">
            </div>
            <div class="col-md-6">
                <label class="form-label">Filter Jenis Guru</label>
                <select class="form-select" id="filterType">
                    <option value="">Semua Jenis</option>
                    <option value="UMUM">UMUM</option>
                    <option value="KEJURUAN">KEJURUAN</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover data-table" id="teacherTable">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Kode</th>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Mata Pelajaran</th>
                    <th>Jenis</th>
                    <th width="150">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($teachers as $index => $teacher): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><span class="badge badge-primary"><?php echo $teacher['teacher_code']; ?></span></td>
                    <td><?php echo $teacher['teacher_username']; ?></td>
                    <td><?php echo $teacher['teacher_name']; ?></td>
                    <td><?php echo $teacher['subject']; ?></td>
                    <td>
                        <span class="badge <?php echo $teacher['teacher_type'] == 'UMUM' ? 'badge-primary' : 'badge-success'; ?>">
                            <?php echo $teacher['teacher_type']; ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-warning edit-btn" data-id="<?php echo $teacher['id']; ?>" data-table="teacher">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="resetPassword(<?php echo $teacher['id']; ?>, 'teacher')">
                                <i class="fas fa-key"></i>
                            </button>
                            <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                                <button class="btn btn-outline-danger" disabled title="Operator tidak dapat menghapus data master">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <a href="?table=teacher&action=delete&id=<?php echo $teacher['id']; ?>" 
                                   class="btn btn-outline-danger" onclick="return confirm('Hapus guru ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable with better search
    const table = $('#teacherTable').DataTable({
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data",
            "infoFiltered": "(difilter dari _MAX_ total data)",
            "zeroRecords": "Data tidak ditemukan",
            "paginate": {
                "previous": "Sebelumnya",
                "next": "Selanjutnya"
            }
        },
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[3, 'asc']], // Sort by name
        "responsive": true
    });
    
    // Enhanced search for all columns
    $('#searchTeacher').on('keyup', function() {
        table.search($(this).val()).draw();
    });
    
    // Filter by type
    $('#filterType').on('change', function() {
        const type = $(this).val();
        if(type) {
            table.column(5).search('^' + type + '$', true, false).draw();
        } else {
            table.column(5).search('').draw();
        }
    });
    
    // Reset password function
    window.resetPassword = function(teacherId, type) {
        if(confirm('Reset password guru ini ke "guru123"?')) {
            $.ajax({
                url: 'ajax/reset_password.php',
                method: 'POST',
                dataType: 'json',
                data: { id: teacherId, type: type },
                success: function(result) {
                    if(result && result.success) {
                        alert(result.message);
                        location.reload();
                    } else {
                        alert(result && result.message ? result.message : 'Gagal mereset password');
                    }
                }
            });
        }
    };
});
</script>
