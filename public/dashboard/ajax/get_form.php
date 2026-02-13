<?php
// PERBAIKI PATH - gunakan path yang benar
$base_dir = dirname(dirname(__DIR__));
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/database.php';

// Periksa apakah request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="modal-header bg-danger text-white">
            <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Akses tidak valid. Form harus diakses melalui metode POST.
            </div>
          </div>';
    exit;
}

$table = $_POST['table'] ?? '';
$id = $_POST['id'] ?? 0;

// Check if table parameter exists
if (empty($table)) {
    echo '<div class="modal-header bg-danger text-white">
            <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Parameter "table" tidak ditemukan. Pastikan Anda mengakses form dengan benar.
            </div>
          </div>';
    exit;
}

$db = new Database();

// Determine form type
$isEdit = ($id > 0);
$title = $isEdit ? 'Edit' : 'Tambah';

switch ($table) {
    // ... kode sebelumnya ...

case 'student':
    // Get classes and jurusan for dropdown
    $classes = $db->query("SELECT c.*, j.jurusan_id, j.name as jurusan_name, j.code as jurusan_code 
                           FROM class c 
                           LEFT JOIN jurusan j ON c.jurusan_id = j.jurusan_id 
                           ORDER BY c.class_name")->fetchAll();
    
    if ($classes === false) {
        echo '<div class="alert alert-danger">Error: Gagal mengambil data kelas</div>';
        exit;
    }
    
    // Create class to jurusan mapping
    $classJurusanMap = [];
    foreach($classes as $class) {
        $classJurusanMap[$class['class_id']] = [
            'jurusan_id' => $class['jurusan_id'],
            'jurusan_name' => $class['jurusan_name'],
            'jurusan_code' => $class['jurusan_code']
        ];
    }
    
    // If edit, get student data
    $student = null;
    if ($isEdit) {
        $student = $db->query("SELECT * FROM student WHERE id = ?", [$id])->fetch();
        if (!$student) {
            echo '<div class="alert alert-danger">Data siswa tidak ditemukan</div>';
            exit;
        }
    }
    ?>
    
    <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
            <i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus-circle'; ?> me-2"></i>
            <?php echo $title; ?> Siswa
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    
    <form method="POST" action="admin.php?table=student" id="studentForm">
        <input type="hidden" name="student_id" value="<?php echo $id; ?>">
        
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="studentNisn" class="form-label">NISN <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="student_nisn" id="studentNisn" required
                           value="<?php echo htmlspecialchars($student['student_nisn'] ?? ''); ?>" 
                           placeholder="Masukkan NISN">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="studentCode" class="form-label">Kode Siswa <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="student_code" id="studentCode" required
                           value="<?php echo htmlspecialchars($student['student_code'] ?? ''); ?>" 
                           placeholder="Contoh: SW0001">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="studentName" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="student_name" id="studentName" required
                       value="<?php echo htmlspecialchars($student['student_name'] ?? ''); ?>" 
                       placeholder="Masukkan nama lengkap siswa">
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="student_class_modal" class="form-label">Kelas <span class="text-danger">*</span></label>
                    <select class="form-select" name="class_id" id="student_class_modal" required>
                        <option value="">Pilih Kelas</option>
                        <?php foreach($classes as $class): ?>
                        <option value="<?php echo $class['class_id']; ?>"
                                data-jurusan-id="<?php echo $class['jurusan_id']; ?>"
                                data-jurusan-name="<?php echo htmlspecialchars($class['jurusan_name']); ?>"
                                data-jurusan-code="<?php echo htmlspecialchars($class['jurusan_code']); ?>"
                                <?php echo (isset($student['class_id']) && $student['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="student_jurusan_display_modal" class="form-label">Jurusan <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="student_jurusan_display_modal" readonly
                           placeholder="Otomatis terisi"
                           style="background-color: var(--input-disabled-bg, #e9ecef);">
                    <input type="hidden" name="jurusan_id" id="student_jurusan_modal" 
                           value="<?php echo $student['jurusan_id'] ?? ''; ?>">
                </div>
            </div>
            
            <?php if ($isEdit): ?>
            <div class="mb-3">
                <label for="studentPassword" class="form-label">Password Baru</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="password" id="studentPassword"
                           placeholder="Masukkan password baru">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('studentPassword')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="text-muted ms-1">Kosongkan jika tidak ingin mengubah password</small>
            </div>
            <?php endif; ?>
            
            <div class="alert alert-info mt-4">
                <div class="d-flex">
                    <i class="fas fa-info-circle me-3 mt-1"></i>
                    <div>
                        <strong>Catatan:</strong>
                        <ul class="mb-0">
                            <li>Jurusan akan otomatis terisi berdasarkan kelas yang dipilih</li>
                            <?php if (!$isEdit): ?>
                            <li>Password default siswa adalah NISN yang dimasukkan</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-2"></i>Batal
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i><?php echo $isEdit ? 'Update' : 'Simpan'; ?>
            </button>
        </div>
    </form>
    
    <script>
    const classJurusanMapModal = <?php echo json_encode($classJurusanMap); ?>;
    
    function updateJurusanFromClass() {
        const classId = $('#student_class_modal').val();
        if (classId && classJurusanMapModal[classId]) {
            const jurusanData = classJurusanMapModal[classId];
            $('#student_jurusan_modal').val(jurusanData.jurusan_id);
            $('#student_jurusan_display_modal').val(jurusanData.jurusan_code + ' - ' + jurusanData.jurusan_name);
        } else {
            $('#student_jurusan_modal').val('');
            $('#student_jurusan_display_modal').val('');
        }
    }

    // Auto-fill jurusan when class changes (delegated for dynamic modal)
    $(document).on('change', '#student_class_modal', updateJurusanFromClass);
    
    // Trigger on load (add/edit)
    setTimeout(updateJurusanFromClass, 0);
    
    // Toggle password visibility
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
    }
    
    // Form validation
    $('#studentForm').on('submit', function(e) {
        const nisn = $('#studentNisn').val();
        const code = $('#studentCode').val();
        const name = $('#studentName').val();
        const classId = $('#student_class_modal').val();
        const jurusanId = $('#student_jurusan_modal').val();
        
        if (!nisn || !code || !name || !classId || !jurusanId) {
            e.preventDefault();
            alert('Harap lengkapi semua field yang wajib diisi!');
            return false;
        }
    });
    </script>
    <?php
    break;
        
    case 'teacher':
        // Get teacher data if editing
        $teacher = null;
        if ($isEdit) {
            $teacher = $db->query("SELECT * FROM teacher WHERE id = ?", [$id])->fetch();
            if (!$teacher) {
                echo '<div class="alert alert-danger">Data guru tidak ditemukan</div>';
                exit;
            }
        }
        ?>
        
        <div class="modal-header bg-success text-white">
            <h5 class="modal-title">
                <i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus-circle'; ?> me-2"></i>
                <?php echo $title; ?> Guru
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        
        <form method="POST" action="admin.php?table=teacher">
            <input type="hidden" name="teacher_id" value="<?php echo $id; ?>">
            
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kode Guru <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="teacher_code" required
                               value="<?php echo $teacher['teacher_code'] ?? ''; ?>" 
                               placeholder="Contoh: GR001">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="teacher_name" required
                               value="<?php echo $teacher['teacher_name'] ?? ''; ?>" 
                               placeholder="Masukkan nama lengkap">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject" required
                               value="<?php echo $teacher['subject'] ?? ''; ?>" 
                               placeholder="Contoh: Matematika">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipe Guru <span class="text-danger">*</span></label>
                        <select class="form-select" name="teacher_type" required>
                            <option value="">Pilih Tipe</option>
                            <option value="UMUM" <?php echo (isset($teacher['teacher_type']) && $teacher['teacher_type'] == 'UMUM') ? 'selected' : ''; ?>>Umum</option>
                            <option value="KEJURUAN" <?php echo (isset($teacher['teacher_type']) && $teacher['teacher_type'] == 'KEJURUAN') ? 'selected' : ''; ?>>Kejuruan</option>
                        </select>
                    </div>
                </div>
                
                <?php if ($isEdit): ?>
                <div class="mb-3">
                    <label class="form-label">Password Baru (Kosongkan jika tidak ingin mengubah)</label>
                    <input type="password" class="form-control" name="password"
                           placeholder="Masukkan password baru">
                </div>
                <?php endif; ?>
                
                <?php if (!$isEdit): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Catatan:</strong> Username akan dibuat otomatis dari nama (lowercase, spasi diganti titik). Password default adalah "guru123".
                </div>
                <?php endif; ?>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Batal
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-2"></i><?php echo $isEdit ? 'Update' : 'Simpan'; ?>
                </button>
            </div>
        </form>
        
        <?php
        break;
        
    default:
        ?>
        <div class="modal-header bg-danger text-white">
            <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Error</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Form tidak ditemukan untuk tabel "<?php echo htmlspecialchars($table); ?>".
            </div>
        </div>
        <?php
        break;
}
?>
