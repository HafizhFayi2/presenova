<?php
// Get all students
$sql = "SELECT s.*, c.class_name, j.name as jurusan_name, j.code as jurusan_code
        FROM student s 
        LEFT JOIN class c ON s.class_id = c.class_id 
        LEFT JOIN jurusan j ON s.jurusan_id = j.jurusan_id 
        ORDER BY s.student_code";

$studentsResult = $db->query($sql);

// Check if query was successful
if($studentsResult === false) {
    die("Error in query: " . print_r($db->errorInfo(), true));
}

$students = $studentsResult->fetchAll();

// Get classes with jurusan info for the modal
$classesResult = $db->query("SELECT c.*, j.jurusan_id, j.name as jurusan_name, j.code as jurusan_code
                              FROM class c 
                              LEFT JOIN jurusan j ON c.jurusan_id = j.jurusan_id 
                              ORDER BY c.class_name");

if($classesResult === false) {
    die("Error in query: " . print_r($db->errorInfo(), true));
}

$classes = $classesResult->fetchAll();

// Get majors for filters
$majorsResult = $db->query("SELECT * FROM jurusan ORDER BY name");
if($majorsResult === false) {
    die("Error in query: " . print_r($db->errorInfo(), true));
}
$majors = $majorsResult->fetchAll();
?>

<div class="data-table-container">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-users text-primary me-2"></i>Daftar Siswa</h5>
        <button class="btn btn-primary add-btn" data-table="student">
            <i class="fas fa-plus-circle me-2"></i>Tambah Siswa
        </button>
    </div>
    
    <!-- Filters -->
    <div class="filter-section">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Filter Kelas</label>
                <select class="form-select" id="filterClass">
                    <option value="">Semua Kelas</option>
                    <?php foreach($classes as $class): ?>
                    <option value="<?php echo htmlspecialchars($class['class_name']); ?>">
                        <?php echo htmlspecialchars($class['class_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Filter Jurusan</label>
                <select class="form-select" id="filterJurusan">
                    <option value="">Semua Jurusan</option>
                    <?php foreach($majors as $major): ?>
                    <option value="<?php echo htmlspecialchars($major['name']); ?>">
                        <?php echo htmlspecialchars($major['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cari Siswa</label>
                <input type="text" class="form-control" id="searchStudent" placeholder="Cari berdasarkan nama atau NISN...">
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover table-bordered data-table" id="studentTable">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Kode</th>
                    <th>NISN</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Jurusan</th>
                    <th>Status Wajah</th>
                    <th width="150">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($students) > 0): ?>
                    <?php foreach($students as $index => $student): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><span class="badge badge-primary"><?php echo htmlspecialchars($student['student_code']); ?></span></td>
                        <td><?php echo htmlspecialchars($student['student_nisn']); ?></td>
                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['jurusan_name']); ?></td>
                        <td>
                            <?php if(!empty($student['photo_reference'])): ?>
                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Terdaftar</span>
                            <?php else: ?>
                            <span class="badge badge-warning"><i class="fas fa-exclamation-circle"></i> Belum</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-warning edit-btn" 
                                        data-id="<?php echo $student['id']; ?>"
                                        data-table="student"
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-info reset-password-btn" 
                                        data-id="<?php echo $student['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($student['student_name']); ?>"
                                        title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                                    <button class="btn btn-outline-danger" disabled title="Operator tidak dapat menghapus data master">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-danger delete-btn" 
                                            data-id="<?php echo $student['id']; ?>"
                                            data-table="student"
                                            data-name="<?php echo htmlspecialchars($student['student_name']); ?>"
                                            title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="admin.php?table=student&action=delete" id="deleteStudentForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash-alt text-danger me-2"></i>Hapus Data Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="delete_student_id" id="deleteStudentId">
                    <p class="mb-3">Anda akan menghapus data siswa: <strong id="deleteStudentName">-</strong></p>
                    <label for="deleteStudentReason" class="form-label">Alasan penghapusan <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="deleteStudentReason" name="delete_reason" rows="3" required placeholder="Tuliskan alasan menghapus data siswa..."></textarea>
                    <div class="form-text text-muted">Alasan wajib diisi untuk audit.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<!-- Tambahkan script CRUD yang lebih baik -->
<script>
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#studentTable').DataTable({
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data siswa",
            "emptyTable": "Belum ada data siswa",
            "infoFiltered": "(difilter dari _MAX_ total data)",
            "zeroRecords": "Data tidak ditemukan",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        },
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[0, 'asc']],
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": [7] }, // Kolom aksi tidak bisa diurutkan
            { "searchable": false, "targets": [0, 7] } // Kolom nomor dan aksi tidak bisa dicari
        ]
    });
    
    // Filter by class
    $('#filterClass').on('change', function() {
        const className = $(this).val();
        table.column(4).search(className).draw();
    });
    
    // Filter by major
    $('#filterJurusan').on('change', function() {
        const jurusanName = $(this).val();
        table.column(5).search(jurusanName).draw();
    });
    
    // Search functionality
    $('#searchStudent').on('keyup', function() {
        table.search($(this).val()).draw();
    });
    
    // Reset Password Button Click
    $(document).on('click', '.reset-password-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        if(confirm(`Apakah Anda yakin ingin mereset password siswa "${name}" ke NISN?`)) {
            $.ajax({
                url: 'ajax/reset_password.php',
                method: 'POST',
                dataType: 'json',
                data: { 
                    id: id,
                    type: 'student'
                },
                success: function(result) {
                    if(result && result.success) {
                        alert(result.message);
                        location.reload();
                    } else {
                        alert(result && result.message ? result.message : 'Gagal mereset password');
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan saat mereset password');
                }
            });
        }
    });
    
    // Delete Button Click
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#deleteStudentId').val(id);
        $('#deleteStudentName').text(name || '-');
        $('#deleteStudentReason').val('');
        $('#deleteStudentModal').modal('show');
    });
    
    // Edit Button Click - Load form via AJAX
    $(document).on('click', '.edit-btn', function() {
        const id = $(this).data('id');
        const tableType = $(this).data('table');
        
        $('#loadingOverlay').show();
        
        $.ajax({
            url: 'ajax/get_form.php',
            method: 'POST',
            data: {
                table: tableType,
                id: id
            },
            success: function(response) {
                $('#addModal .modal-content').html(response);
                $('#addModal').modal('show');
                $('#loadingOverlay').hide();
            },
            error: function() {
                $('#loadingOverlay').hide();
                alert('Terjadi kesalahan saat memuat data');
            }
        });
    });
    
    // Add Button Click
    $('.add-btn[data-table="student"]').on('click', function() {
        const tableType = $(this).data('table');
        
        $('#loadingOverlay').show();
        
        $.ajax({
            url: 'ajax/get_form.php',
            method: 'POST',
            data: {
                table: tableType,
                id: 0
            },
            success: function(response) {
                $('#addModal .modal-content').html(response);
                $('#addModal').modal('show');
                $('#loadingOverlay').hide();
            },
            error: function() {
                $('#loadingOverlay').hide();
                alert('Terjadi kesalahan saat memuat form');
            }
        });
    });
});
</script>
