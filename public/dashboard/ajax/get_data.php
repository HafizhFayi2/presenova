<?php
// PERBAIKI PATH - gunakan path yang benar
$base_dir = dirname(dirname(__DIR__));
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/database.php';

$db = new Database();
$table = $_POST['table'] ?? '';
$id = $_POST['id'] ?? 0;
$isOperator = isset($_SESSION['level']) && (int) $_SESSION['level'] === 2;

if(empty($table)) {
    echo '<div class="alert alert-danger">Parameter table tidak ditemukan</div>';
    exit;
}

if (
    !isset($_SESSION['logged_in'], $_SESSION['role'], $_SESSION['level']) ||
    $_SESSION['logged_in'] !== true ||
    $_SESSION['role'] !== 'admin' ||
    !in_array((int) $_SESSION['level'], [1, 2], true)
) {
    echo '<div class="alert alert-danger">Akses ditolak</div>';
    exit;
}

switch($table) {
    case 'student':
        // Get student data for editing
        if($id > 0) {
            $student = $db->query("SELECT * FROM student WHERE id = ?", [$id])->fetch();
            if(!$student) {
                echo '<div class="alert alert-danger">Data siswa tidak ditemukan</div>';
                exit;
            }
        }
        
        // Get classes and majors
        $classes = $db->query("SELECT * FROM class ORDER BY class_name")->fetchAll();
        $majors = $db->query("SELECT * FROM jurusan ORDER BY name")->fetchAll();
        
        if ($classes === false || $majors === false) {
            echo '<div class="alert alert-danger">Gagal mengambil data dropdown</div>';
            exit;
        }
        ?>
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><?php echo $id > 0 ? 'Edit' : 'Tambah'; ?> Siswa</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="admin.php?table=student">
            <input type="hidden" name="student_id" value="<?php echo $id; ?>">
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kode Siswa *</label>
                        <input type="text" class="form-control" value="<?php echo $id > 0 ? '******' : 'AUTO GENERATED'; ?>" readonly>
                        <input type="hidden" name="student_code" value="">
                        <?php if ($isOperator): ?>
                        <small class="text-muted">Operator tidak dapat melihat kode siswa.</small>
                        <?php else: ?>
                        <small class="text-muted">Kode siswa dibuat otomatis saat simpan (format SW + kode acak).</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NISN *</label>
                        <input type="text" class="form-control" name="student_nisn" required
                               value="<?php echo htmlspecialchars($student['student_nisn'] ?? ''); ?>"
                               placeholder="1234567890">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" class="form-control" name="student_name" required
                           value="<?php echo htmlspecialchars($student['student_name'] ?? ''); ?>"
                           placeholder="Nama siswa">
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kelas *</label>
                        <select class="form-select" name="class_id" required>
                            <option value="">Pilih Kelas</option>
                            <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"
                                    <?php echo (isset($student['class_id']) && $student['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jurusan *</label>
                        <select class="form-select" name="jurusan_id" required>
                            <option value="">Pilih Jurusan</option>
                            <?php foreach($majors as $major): ?>
                            <option value="<?php echo $major['jurusan_id']; ?>"
                                    <?php echo (isset($student['jurusan_id']) && $student['jurusan_id'] == $major['jurusan_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($major['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" 
                               placeholder="Kosongkan untuk menggunakan kode siswa">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="text-muted">
                        <?php echo $id > 0 ? 'Kosongkan jika tidak ingin mengubah password' : 'Password default mengikuti kode siswa'; ?>
                    </small>
                </div>
                
                <?php if($id == 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Password default akan menggunakan kode siswa otomatis.
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
        
        <script>
        function togglePassword(button) {
            const input = button.parentElement.querySelector('input');
            const icon = button.querySelector('i');
            if(input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        </script>
        <?php
        break;

    case 'teacher':
        // Get teacher data if editing
        $teacher = null;
        if($id > 0) {
            $teacher = $db->query("SELECT * FROM teacher WHERE id = ?", [$id])->fetch();
            if(!$teacher) {
                echo '<div class="alert alert-danger">Data guru tidak ditemukan</div>';
                exit;
            }
        }
        ?>
        <div class="modal-header bg-success text-white">
            <h5 class="modal-title"><?php echo $id > 0 ? 'Edit' : 'Tambah'; ?> Guru</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="admin.php?table=teacher">
            <div class="modal-body">
                <input type="hidden" name="teacher_id" value="<?php echo $id; ?>">
                <div class="mb-3">
                    <label class="form-label">Kode Guru *</label>
                    <input type="text" class="form-control" name="teacher_code" required 
                           value="<?php echo htmlspecialchars($teacher['teacher_code'] ?? ''); ?>"
                           placeholder="GR001">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" class="form-control" name="teacher_name" required
                           value="<?php echo htmlspecialchars($teacher['teacher_name'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Mata Pelajaran *</label>
                    <input type="text" class="form-control" name="subject" required
                           value="<?php echo htmlspecialchars($teacher['subject'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="teacherPassword" name="password" 
                               placeholder="Kosongkan untuk menggunakan 'guru123'">
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('teacherPassword')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="text-muted">Jika dikosongkan, password akan diatur ke 'guru123'</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Jenis Guru *</label>
                    <select class="form-select" name="teacher_type" required>
                        <option value="">Pilih Jenis Guru</option>
                        <option value="UMUM" <?php echo (isset($teacher['teacher_type']) && $teacher['teacher_type'] == 'UMUM') ? 'selected' : ''; ?>>UMUM</option>
                        <option value="KEJURUAN" <?php echo (isset($teacher['teacher_type']) && $teacher['teacher_type'] == 'KEJURUAN') ? 'selected' : ''; ?>>KEJURUAN</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
        
        <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
        }
        </script>
        <?php
        break;
    
    default:
        echo '<div class="alert alert-danger">Form tidak ditemukan untuk tabel: ' . htmlspecialchars($table) . '</div>';
}
?>
