<?php
// Get filter from GET parameters
$filter_jurusan = $_GET['filter_jurusan'] ?? '';

// Notifikasi sukses/error ditampilkan di admin.php (hindari double alert)

// Handle POST requests for CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // TAMBAH/EDIT KELAS
    if (isset($_POST['class_id'])) {
        $class_id = intval($_POST['class_id']);
        $class_name = trim($_POST['class_name']);
        $jurusan_id = intval($_POST['jurusan_id']);
        
        // Validasi input
        if (empty($class_name) || $jurusan_id <= 0) {
            $error = "Nama kelas dan jurusan harus diisi!";
            header("Location: admin.php?table=class&error=" . urlencode($error));
            exit();
        }
        
        // Cek apakah nama kelas sudah ada (untuk nama yang unik)
        $check_sql = "SELECT COUNT(*) as total FROM class WHERE class_name = ? AND class_id != ?";
        $check_stmt = $db->query($check_sql, [$class_name, $class_id]);
        $result = $check_stmt->fetch();
        
        if ($result['total'] > 0) {
            $error = "Nama kelas '$class_name' sudah ada!";
            header("Location: admin.php?table=class&error=" . urlencode($error));
            exit();
        }
        
        if ($class_id == 0) {
            // Tambah kelas baru
            $sql = "INSERT INTO class (class_name, jurusan_id) VALUES (?, ?)";
            $stmt = $db->query($sql, [$class_name, $jurusan_id]);
            if ($stmt) {
                $success = "Kelas berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan kelas!";
            }
        } else {
            // Edit kelas
            $sql = "UPDATE class SET class_name = ?, jurusan_id = ? WHERE class_id = ?";
            $stmt = $db->query($sql, [$class_name, $jurusan_id, $class_id]);
            if ($stmt) {
                $success = "Kelas berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui kelas!";
            }
        }
        header("Location: admin.php?table=class&" . (isset($success) ? "success=" . urlencode($success) : "error=" . urlencode($error)));
        exit();
    }
    
    // TAMBAH JURUSAN
    if (isset($_POST['code']) && isset($_POST['name'])) {
        $code = strtoupper(trim($_POST['code']));
        $name = trim($_POST['name']);
        
        // Validasi input
        if (empty($code) || empty($name)) {
            $error = "Kode dan nama jurusan harus diisi!";
            header("Location: admin.php?table=class&error=" . urlencode($error));
            exit();
        }
        
        // Cek apakah kode jurusan sudah ada
        $check_sql = "SELECT COUNT(*) as total FROM jurusan WHERE code = ?";
        $check_stmt = $db->query($check_sql, [$code]);
        $result = $check_stmt->fetch();
        
        if ($result['total'] > 0) {
            $error = "Kode jurusan '$code' sudah ada!";
            header("Location: admin.php?table=class&error=" . urlencode($error));
            exit();
        }
        
        $sql = "INSERT INTO jurusan (code, name) VALUES (?, ?)";
        $stmt = $db->query($sql, [$code, $name]);
        if ($stmt) {
            $success = "Jurusan berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan jurusan!";
        }
        header("Location: admin.php?table=class&" . (isset($success) ? "success=" . urlencode($success) : "error=" . urlencode($error)));
        exit();
    }
    
    // EDIT JURUSAN
    if (isset($_POST['edit_jurusan_id'])) {
        $jurusan_id = intval($_POST['edit_jurusan_id']);
        $code = strtoupper(trim($_POST['edit_code']));
        $name = trim($_POST['edit_name']);
        
        // Validasi input
        if (empty($code) || empty($name)) {
            $error = "Kode dan nama jurusan harus diisi!";
            header("Location: admin.php?table=class&error=" . urlencode($error));
            exit();
        }
        
        // Cek apakah kode jurusan sudah ada (kecuali untuk jurusan ini sendiri)
        $check_sql = "SELECT COUNT(*) as total FROM jurusan WHERE code = ? AND jurusan_id != ?";
        $check_stmt = $db->query($check_sql, [$code, $jurusan_id]);
        $result = $check_stmt->fetch();
        
        if ($result['total'] > 0) {
            $error = "Kode jurusan '$code' sudah digunakan!";
            header("Location: admin.php?table=class&error=" . urlencode($error));
            exit();
        }
        
        $sql = "UPDATE jurusan SET code = ?, name = ? WHERE jurusan_id = ?";
        $stmt = $db->query($sql, [$code, $name, $jurusan_id]);
        if ($stmt) {
            $success = "Jurusan berhasil diperbarui!";
        } else {
            $error = "Gagal memperbarui jurusan!";
        }
        header("Location: admin.php?table=class&" . (isset($success) ? "success=" . urlencode($success) : "error=" . urlencode($error)));
        exit();
    }
}

// Handle DELETE requests - SEDERHANAKAN SEPERTI TEACHER.PHP
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    if (isset($canDeleteMaster) && !$canDeleteMaster) {
        $error = "Operator tidak memiliki izin menghapus data master.";
        header("Location: admin.php?table=class&error=" . urlencode($error));
        exit();
    }
    // HAPUS KELAS
    if (isset($_GET['delete_class'])) {
        $class_id = intval($_GET['delete_class']);
        
        try {
            // Mulai transaksi
            $db->beginTransaction();
            
            // 1. Cek apakah ada siswa di kelas ini
            $check_sql = "SELECT COUNT(*) as total FROM student WHERE class_id = ?";
            $check_stmt = $db->query($check_sql, [$class_id]);
            $student_result = $check_stmt->fetch();
            
            if ($student_result['total'] > 0) {
                throw new Exception("Tidak dapat menghapus kelas yang masih memiliki " . $student_result['total'] . " siswa!");
            }
            
            // 2. Cek apakah ada jadwal untuk kelas ini
            $check_schedule_sql = "SELECT COUNT(*) as total FROM teacher_schedule WHERE class_id = ?";
            $check_schedule_stmt = $db->query($check_schedule_sql, [$class_id]);
            $schedule_result = $check_schedule_stmt->fetch();
            
            if ($schedule_result['total'] > 0) {
                throw new Exception("Tidak dapat menghapus kelas yang masih memiliki " . $schedule_result['total'] . " jadwal mengajar!");
            }
            
            // 3. Hapus data kelas
            $sql = "DELETE FROM class WHERE class_id = ?";
            $stmt = $db->query($sql, [$class_id]);
            
            if ($stmt) {
                $db->commit();
                if (function_exists('resetAutoIncrementIfEmpty')) {
                    resetAutoIncrementIfEmpty($db, 'class', 0);
                }
                $success = "Kelas berhasil dihapus!";
            } else {
                throw new Exception("Gagal menghapus kelas!");
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
        
        header("Location: admin.php?table=class&" . (isset($success) ? "success=" . urlencode($success) : "error=" . urlencode($error)));
        exit();
    }
    
    // HAPUS JURUSAN
    if (isset($_GET['delete_jurusan'])) {
        $jurusan_id = intval($_GET['delete_jurusan']);
        
        try {
            // Mulai transaksi
            $db->beginTransaction();
            
            // 1. Cek apakah ada kelas dengan jurusan ini
            $check_class_sql = "SELECT COUNT(*) as total FROM class WHERE jurusan_id = ?";
            $check_class_stmt = $db->query($check_class_sql, [$jurusan_id]);
            $class_result = $check_class_stmt->fetch();
            
            if ($class_result['total'] > 0) {
                throw new Exception("Tidak dapat menghapus jurusan yang masih memiliki " . $class_result['total'] . " kelas!");
            }
            
            // 2. Cek apakah ada siswa dengan jurusan ini (langsung)
            $check_student_sql = "SELECT COUNT(*) as total FROM student WHERE jurusan_id = ?";
            $check_student_stmt = $db->query($check_student_sql, [$jurusan_id]);
            $student_result = $check_student_stmt->fetch();
            
            if ($student_result['total'] > 0) {
                throw new Exception("Tidak dapat menghapus jurusan yang masih memiliki " . $student_result['total'] . " siswa!");
            }
            
            // 3. Hapus data jurusan
            $sql = "DELETE FROM jurusan WHERE jurusan_id = ?";
            $stmt = $db->query($sql, [$jurusan_id]);
            
            if ($stmt) {
                $db->commit();
                if (function_exists('resetAutoIncrementIfEmpty')) {
                    resetAutoIncrementIfEmpty($db, 'jurusan', 0);
                }
                $success = "Jurusan berhasil dihapus!";
            } else {
                throw new Exception("Gagal menghapus jurusan!");
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
        
        header("Location: admin.php?table=class&" . (isset($success) ? "success=" . urlencode($success) : "error=" . urlencode($error)));
        exit();
    }
}

// Get all classes with major info using parameter binding
$sql = "SELECT c.*, j.name as jurusan_name, j.code as jurusan_code, j.jurusan_id
        FROM class c 
        LEFT JOIN jurusan j ON c.jurusan_id = j.jurusan_id";

// Apply filter if set
if ($filter_jurusan) {
    $sql .= " WHERE c.jurusan_id = ?";
    $sql .= " ORDER BY c.class_name";
    $stmt = $db->query($sql, [$filter_jurusan]);
} else {
    $sql .= " ORDER BY c.class_name";
    $stmt = $db->query($sql);
}

$classes = $stmt->fetchAll();

// Get all majors for filters and forms
$majors = $db->query("SELECT * FROM jurusan ORDER BY name")->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <div class="data-table-container mb-4">
            <h5 class="mb-4"><i class="fas fa-plus-circle text-primary me-2"></i>Tambah Kelas Baru</h5>
            <form method="POST" action="admin.php?table=class" id="formAddClass">
                <input type="hidden" name="class_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nama Kelas</label>
                    <input type="text" class="form-control" name="class_name" required 
                           placeholder="Contoh: X TKJ 1">
                    <small class="form-text">Format: [Tingkat] [Jurusan] [Nomor]</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Jurusan</label>
                    <select class="form-select" name="jurusan_id" required>
                        <option value="">Pilih Jurusan</option>
                        <?php foreach($majors as $major): ?>
                        <option value="<?php echo $major['jurusan_id']; ?>">
                            <?php echo htmlspecialchars($major['code']); ?> - <?php echo htmlspecialchars($major['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save me-2"></i> Simpan Kelas
                </button>
            </form>
        </div>
        
        <div class="data-table-container">
            <h5 class="mb-4"><i class="fas fa-tags text-success me-2"></i>Tambah Jurusan Baru</h5>
            <form method="POST" action="admin.php?table=class" id="formAddJurusan">
                <div class="mb-3">
                    <label class="form-label">Kode Jurusan</label>
                    <input type="text" class="form-control" name="code" required 
                           placeholder="Contoh: TKJ" maxlength="10" style="text-transform: uppercase;">
                    <small class="form-text">Maksimal 10 karakter (akan diubah ke huruf besar)</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Jurusan</label>
                    <input type="text" class="form-control" name="name" required 
                           placeholder="Contoh: Teknik Komputer dan Jaringan">
                </div>
                <button type="submit" class="btn btn-success w-100">
                    <i class="fas fa-plus-lg me-2"></i> Tambah Jurusan
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="data-table-container mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="fas fa-school text-primary me-2"></i>Daftar Kelas</h5>
                <div class="d-flex gap-2">
                    <select class="form-select" id="filterJurusan" style="width: 250px;" onchange="applyFilter()">
                        <option value="">Semua Jurusan</option>
                        <?php foreach($majors as $major): ?>
                        <option value="<?php echo $major['jurusan_id']; ?>" <?php echo $filter_jurusan == $major['jurusan_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($major['code']); ?> - <?php echo htmlspecialchars($major['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="form-control" id="searchClass" placeholder="Cari kelas..." style="width: 200px;">
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover no-card-table" id="classTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Nama Kelas</th>
                            <th>Jurusan</th>
                            <th style="width: 100px;">Kode</th>
                            <th style="width: 120px;">Jumlah Siswa</th>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($classes) > 0): ?>
                            <?php foreach($classes as $index => $class): 
                                // Count students in class
                                $count_sql = "SELECT COUNT(*) as total FROM student WHERE class_id = ?";
                                $count_stmt = $db->query($count_sql, [$class['class_id']]);
                                $student_count = $count_stmt->fetch()['total'];
                                
                                // Count schedules for class
                                $schedule_sql = "SELECT COUNT(*) as total FROM teacher_schedule WHERE class_id = ?";
                                $schedule_stmt = $db->query($schedule_sql, [$class['class_id']]);
                                $schedule_count = $schedule_stmt->fetch()['total'];
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($class['jurusan_name'] ?? '-'); ?></td>
                                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($class['jurusan_code'] ?? '-'); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $student_count > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <i class="fas fa-users me-1"></i><?php echo $student_count; ?> siswa
                                    </span>
                                    <?php if($schedule_count > 0): ?>
                                    <br>
                                    <span class="badge badge-info mt-1">
                                        <i class="fas fa-calendar me-1"></i><?php echo $schedule_count; ?> jadwal
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-warning edit-class-btn" 
                                                data-id="<?php echo $class['class_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($class['class_name']); ?>"
                                                data-jurusan="<?php echo $class['jurusan_id']; ?>"
                                                title="Edit Kelas">
                                            <i class="fas fa-pencil"></i>
                                        </button>
                                        <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                                            <button class="btn btn-sm btn-danger" disabled title="Operator tidak dapat menghapus data master">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php elseif($student_count == 0 && $schedule_count == 0): ?>
                                            <a href="admin.php?table=class&action=delete&delete_class=<?php echo $class['class_id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return AppDialog.inlineConfirm(this, 'Hapus kelas <?php echo addslashes($class['class_name']); ?>? Tindakan ini tidak dapat dibatalkan!')"
                                               title="Hapus Kelas">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-danger" disabled 
                                                    title="<?php echo $student_count > 0 ? "Kelas masih memiliki {$student_count} siswa" : "Kelas masih memiliki {$schedule_count} jadwal"; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 40px;">
                                    <div style="opacity: 0.5;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px;"></i><br>
                                        <p class="mb-0">Tidak ada data kelas<?php echo $filter_jurusan ? ' untuk jurusan ini' : ''; ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="data-table-container">
            <h5 class="mb-4"><i class="fas fa-tags text-success me-2"></i>Daftar Jurusan</h5>
            <div class="row">
                <?php if (count($majors) > 0): ?>
                    <?php foreach($majors as $major): 
                        // Count classes for this major
                        $count_sql = "SELECT COUNT(*) as total FROM class WHERE jurusan_id = ?";
                        $count_stmt = $db->query($count_sql, [$major['jurusan_id']]);
                        $class_count = $count_stmt->fetch()['total'];
                        
                        // Count students for this major (directly)
                        $student_sql = "SELECT COUNT(*) as total FROM student WHERE jurusan_id = ?";
                        $student_stmt = $db->query($student_sql, [$major['jurusan_id']]);
                        $student_count = $student_stmt->fetch()['total'];
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="card" style="border: 1px solid var(--border); transition: all 0.3s ease;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-2" style="color: var(--primary-blue); font-weight: 700;">
                                            <i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($major['code']); ?>
                                        </h6>
                                        <p class="mb-2" style="font-size: 0.9rem; color: var(--text-color);">
                                            <?php echo htmlspecialchars($major['name']); ?>
                                        </p>
                                        <div class="d-flex gap-2">
                                            <span class="badge badge-primary">
                                                <i class="fas fa-school me-1"></i><?php echo $class_count; ?> Kelas
                                            </span>
                                            <?php if($student_count > 0): ?>
                                            <span class="badge badge-info">
                                                <i class="fas fa-users me-1"></i><?php echo $student_count; ?> Siswa
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-1">
                                        <button class="btn btn-sm btn-outline-warning edit-jurusan-btn" 
                                                data-id="<?php echo $major['jurusan_id']; ?>"
                                                data-code="<?php echo htmlspecialchars($major['code']); ?>"
                                                data-name="<?php echo htmlspecialchars($major['name']); ?>"
                                                title="Edit Jurusan">
                                            <i class="fas fa-pencil"></i>
                                        </button>
                                        <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                                            <button class="btn btn-sm btn-outline-danger" disabled title="Operator tidak dapat menghapus data master">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php elseif ($class_count == 0 && $student_count == 0): ?>
                                            <a href="admin.php?table=class&action=delete&delete_jurusan=<?php echo $major['jurusan_id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return AppDialog.inlineConfirm(this, 'Hapus jurusan <?php echo addslashes($major['code']); ?> - <?php echo addslashes($major['name']); ?>? Tindakan ini tidak dapat dibatalkan!')"
                                               title="Hapus Jurusan">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-danger" disabled 
                                                    title="<?php echo $class_count > 0 ? "Jurusan masih memiliki {$class_count} kelas" : "Jurusan masih memiliki {$student_count} siswa"; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center" style="padding: 40px; opacity: 0.5;">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px;"></i><br>
                            <p class="mb-0">Belum ada jurusan</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="admin.php?table=class">
                <div class="modal-body">
                    <input type="hidden" name="class_id" id="editClassId">
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas</label>
                        <input type="text" class="form-control" name="class_name" id="editClassName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jurusan</label>
                        <select class="form-select" name="jurusan_id" id="editJurusanId" required>
                            <option value="">Pilih Jurusan</option>
                            <?php foreach($majors as $major): ?>
                            <option value="<?php echo $major['jurusan_id']; ?>">
                                <?php echo htmlspecialchars($major['code']); ?> - <?php echo htmlspecialchars($major['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Jurusan Modal -->
<div class="modal fade" id="editJurusanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Jurusan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="admin.php?table=class">
                <div class="modal-body">
                    <input type="hidden" name="edit_jurusan_id" id="editJurusanIdInput">
                    <div class="mb-3">
                        <label class="form-label">Kode Jurusan</label>
                        <input type="text" class="form-control" name="edit_code" id="editJurusanCode" required 
                               maxlength="10" style="text-transform: uppercase;">
                        <small class="form-text">Maksimal 10 karakter</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Jurusan</label>
                        <input type="text" class="form-control" name="edit_name" id="editJurusanName" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter functionality
function applyFilter() {
    const jurusanId = document.getElementById('filterJurusan').value;
    const url = new URL(window.location.href);
    
    if (jurusanId) {
        url.searchParams.set('filter_jurusan', jurusanId);
    } else {
        url.searchParams.delete('filter_jurusan');
    }
    
    // Save filter to localStorage
    localStorage.setItem('classFilterJurusan', jurusanId);
    
    window.location.href = url.toString();
}

$(document).ready(function() {
    // Restore filter from localStorage
    const savedFilter = localStorage.getItem('classFilterJurusan');
    if (savedFilter) {
        $('#filterJurusan').val(savedFilter);
    }
    
    // Search functionality
    $('#searchClass').on('keyup', function() {
        const search = $(this).val().toLowerCase();
        $('#classTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1);
        });
    });
    
    // Edit class button
    $('.edit-class-btn').click(function() {
        const classId = $(this).data('id');
        const className = $(this).data('name');
        const jurusanId = $(this).data('jurusan');
        
        $('#editClassId').val(classId);
        $('#editClassName').val(className);
        $('#editJurusanId').val(jurusanId);
        
        $('#editClassModal').modal('show');
    });
    
    // Edit jurusan button
    $('.edit-jurusan-btn').click(function() {
        const jurusanId = $(this).data('id');
        const jurusanCode = $(this).data('code');
        const jurusanName = $(this).data('name');
        
        $('#editJurusanIdInput').val(jurusanId);
        $('#editJurusanCode').val(jurusanCode);
        $('#editJurusanName').val(jurusanName);
        
        $('#editJurusanModal').modal('show');
    });
    
    // Form validation
    $('#formAddClass').on('submit', function(e) {
        const className = $('input[name="class_name"]', this).val().trim();
        const jurusanId = $('select[name="jurusan_id"]', this).val();
        
        if (!className) {
            e.preventDefault();
            alert('Nama kelas tidak boleh kosong');
            return false;
        }
        
        if (!jurusanId) {
            e.preventDefault();
            alert('Silakan pilih jurusan');
            return false;
        }
    });
    
    $('#formAddJurusan').on('submit', function(e) {
        const code = $('input[name="code"]', this).val().trim();
        const name = $('input[name="name"]', this).val().trim();
        
        if (!code || !name) {
            e.preventDefault();
            alert('Kode dan nama jurusan tidak boleh kosong');
            return false;
        }
        
        // Convert to uppercase
        $(this).find('input[name="code"]').val(code.toUpperCase());
    });
    
    // Auto uppercase for code fields
    $('input[name="code"], input[name="edit_code"]').on('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // Hover effect for cards
    $('.card').hover(
        function() {
            $(this).css({
                'transform': 'translateY(-5px)',
                'box-shadow': '0 10px 20px rgba(37, 99, 235, 0.15)'
            });
        },
        function() {
            $(this).css({
                'transform': 'translateY(0)',
                'box-shadow': 'none'
            });
        }
    );
    
    // Auto-dismiss alerts after 5 seconds
    $('.alert').delay(5000).fadeOut('slow');
    
});
</script>
