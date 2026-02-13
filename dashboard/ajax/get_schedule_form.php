<?php
// Pastikan session dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cari path includes yang benar
$base_dir = dirname(__DIR__, 2); // Naik 2 level dari ajax ke root
$config_path = $base_dir . '/includes/config.php';
$database_path = $base_dir . '/includes/database.php';

if (!file_exists($config_path) || !file_exists($database_path)) {
    $base_dir = dirname(__DIR__);
    $config_path = $base_dir . '/includes/config.php';
    $database_path = $base_dir . '/includes/database.php';
}

require_once $config_path;
require_once $database_path;

$db = new Database();

// Ambil ID jika ada (mode edit)
$schedule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$schedule = null;

if ($schedule_id > 0) {
    $sql = "SELECT * FROM teacher_schedule WHERE schedule_id = ?";
    $stmt = $db->query($sql, [$schedule_id]);
    $schedule = $stmt->fetch();
}

// Ambil data dropdown
try {
    $teachers = $db->query("SELECT id, teacher_code, teacher_name, subject FROM teacher ORDER BY teacher_name")->fetchAll();
    $classes = $db->query("SELECT class_id, class_name FROM class ORDER BY class_name")->fetchAll();
    $days = $db->query("SELECT day_id, day_name FROM day WHERE is_active = 'Y' ORDER BY day_order")->fetchAll();
    $shifts = $db->query("SELECT shift_id, shift_name, time_in, time_out FROM shift ORDER BY time_in")->fetchAll();
} catch (Exception $e) {
    die('Error loading data: ' . htmlspecialchars($e->getMessage()));
}

$jp_start = 1;
$jp_end = 1;
if ($schedule && !empty($schedule['shift_id'])) {
    $shiftInfo = $db->query("SELECT shift_name FROM shift WHERE shift_id = ?", [$schedule['shift_id']])->fetch();
    if ($shiftInfo && preg_match('/JP(\\d+)-JP(\\d+)/', $shiftInfo['shift_name'], $m)) {
        $jp_start = (int)$m[1];
        $jp_end = (int)$m[2];
    }
}
?>

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title">
        <i class="fas fa-<?php echo $schedule_id > 0 ? 'edit' : 'plus-circle'; ?> me-2"></i>
        <?php echo $schedule_id > 0 ? 'Edit' : 'Tambah'; ?> Jadwal Mengajar
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form method="POST" action="admin.php?table=schedule" id="scheduleForm">
    <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
    
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Guru <span class="text-danger">*</span></label>
                <select class="form-select" name="teacher_id" id="teacherSelect" required>
                    <option value="">Pilih Guru</option>
                    <?php foreach ($teachers as $teacher): ?>
                    <option value="<?php echo $teacher['id']; ?>" 
                        data-subject="<?php echo htmlspecialchars($teacher['subject'] ?? ''); ?>"
                        <?php echo ($schedule && $schedule['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                        <?php if (!empty($teacher['subject'])): ?>
                            (<?php echo htmlspecialchars($teacher['subject']); ?>)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Mata pelajaran akan otomatis terisi</div>
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                <select class="form-select" name="class_id" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['class_id']; ?>" 
                        <?php echo ($schedule && $schedule['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="subject" id="subjectInput" 
                   value="<?php echo $schedule ? htmlspecialchars($schedule['subject']) : ''; ?>" 
                   placeholder="Contoh: Matematika, Bahasa Indonesia" required>
            <div class="form-text">Otomatis terisi berdasarkan guru yang dipilih</div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Hari <span class="text-danger">*</span></label>
                <select class="form-select" name="day_id" required>
                    <option value="">Pilih Hari</option>
                    <?php foreach ($days as $day): ?>
                    <option value="<?php echo $day['day_id']; ?>" 
                        <?php echo ($schedule && $schedule['day_id'] == $day['day_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($day['day_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3 mb-3">
                <label class="form-label">JP Mulai <span class="text-danger">*</span></label>
                <select class="form-select" name="jp_start" id="jpStart" required>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                    <?php $is_break = ($i === 5 || $i === 9); ?>
                    <option value="<?php echo $i; ?>"
                        <?php echo ($jp_start == $i) ? 'selected' : ''; ?>
                        <?php echo $is_break ? 'disabled' : ''; ?>>
                        JP<?php echo $i; ?><?php echo $is_break ? ' (Istirahat)' : ''; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">JP Selesai <span class="text-danger">*</span></label>
                <select class="form-select" name="jp_end" id="jpEnd" required>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                    <?php $is_break = ($i === 5 || $i === 9); ?>
                    <option value="<?php echo $i; ?>"
                        <?php echo ($jp_end == $i) ? 'selected' : ''; ?>
                        <?php echo $is_break ? 'disabled' : ''; ?>>
                        JP<?php echo $i; ?><?php echo $is_break ? ' (Istirahat)' : ''; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i>
            <small>
                Mata pelajaran otomatis terisi berdasarkan guru yang dipilih. 
                Anda dapat mengubahnya jika diperlukan.
            </small>
        </div>
    </div>
    
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
    </div>
</form>

<script>
$(document).ready(function() {
    // Debug: Cek apakah jQuery bekerja
    console.log('Modal form loaded and jQuery working');
    
    const $teacherSelect = $('#teacherSelect');
    const $subjectInput = $('#subjectInput');
    
    // 1. Auto-fill mata pelajaran saat guru dipilih
    $teacherSelect.on('change', function() {
        console.log('Guru dipilih:', $(this).val());
        
        // Ambil opsi yang dipilih
        const selectedOption = $(this).find('option:selected');
        const teacherSubject = selectedOption.data('subject');
        
        console.log('Subject dari data attribute:', teacherSubject);
        
        // Isi field mata pelajaran jika ada data
        if (teacherSubject && teacherSubject.trim() !== '') {
            $subjectInput.val(teacherSubject);
            console.log('Subject diisi otomatis:', teacherSubject);
            
            // Efek visual
            $subjectInput.addClass('is-valid');
            setTimeout(() => {
                $subjectInput.removeClass('is-valid');
            }, 1000);
        } else {
            console.log('Tidak ada subject untuk guru ini');
            $subjectInput.val('');
        }
    });
    
    // 2. Untuk mode edit: trigger change jika sudah ada guru yang dipilih
    setTimeout(function() {
        if ($teacherSelect.val()) {
            console.log('Trigger change untuk guru yang sudah dipilih (edit mode)');
            $teacherSelect.trigger('change');
        }
    }, 500);
    
    function syncJpEndOptions() {
        const start = parseInt($('#jpStart').val(), 10);
        $('#jpEnd option').each(function() {
            const value = parseInt($(this).val(), 10);
            const isBreak = value === 5 || value === 9;
            $(this).prop('disabled', value < start || isBreak);
        });
        const currentEnd = parseInt($('#jpEnd').val(), 10);
        if (currentEnd < start || currentEnd === 5 || currentEnd === 9) {
            $('#jpEnd').val(start);
        }
    }

    $('#jpStart').on('change', syncJpEndOptions);
    syncJpEndOptions();

    // 3. Validasi form
    $('#scheduleForm').on('submit', function(e) {
        console.log('Form disubmit');
        
        const requiredFields = $(this).find('[required]');
        let isValid = true;
        
        requiredFields.each(function() {
            if ($(this).val() === '') {
                $(this).addClass('is-invalid');
                isValid = false;
                console.log('Field wajib kosong:', $(this).attr('name'));
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Harap lengkapi semua field yang wajib diisi!');
            return false;
        }
        
        // Tampilkan loading
        $('button[type="submit"]').html('<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...').prop('disabled', true);
    });
    
    // 4. User mengedit manual - beri indikator
    $subjectInput.on('input', function() {
        const currentTeacherId = $teacherSelect.val();
        const currentSubject = $(this).val();
        
        if (currentTeacherId) {
            const selectedOption = $teacherSelect.find('option:selected');
            const autoSubject = selectedOption.data('subject') || '';
            
            if (currentSubject !== autoSubject) {
                // User mengubah manual
                $(this).addClass('border-warning');
            } else {
                $(this).removeClass('border-warning');
            }
        }
    });
});
</script>
