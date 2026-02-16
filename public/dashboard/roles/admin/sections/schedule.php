<?php
// Periksa koneksi database
if (!isset($db) || !$db) {
    echo '<div class="alert alert-danger">Koneksi database tidak valid</div>';
    return;
}

require_once __DIR__ . '/../../../../helpers/jp_time_helper.php';

// Get filter parameters
$filter_day = $_GET['filter_day'] ?? '';
$filter_teacher = $_GET['filter_teacher'] ?? '';
$filter_class = $_GET['filter_class'] ?? '';

// Initialize variables
$schedules = [];
$teachers = [];
$classes = [];
$days = [];
$shifts = [];
$day_configs = [];

// Fungsi untuk menangani query dengan error handling
function executeQuery($db, $sql, $params = []) {
    try {
        if (!empty($params)) {
            $stmt = $db->query($sql, $params);
        } else {
            $stmt = $db->query($sql);
        }
        
        if ($stmt === false) {
            return false;
        }
        
        return $stmt;
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage());
        return false;
    }
}

// Get data for dropdowns dengan error handling yang lebih baik
try {
    // Get teachers
    $teacherSql = "SELECT * FROM teacher ORDER BY teacher_name";
    $teacherStmt = executeQuery($db, $teacherSql);
    if ($teacherStmt) {
        $teachers = $teacherStmt->fetchAll() ?: [];
    }
    
    // Get classes
    $classSql = "SELECT * FROM class ORDER BY class_name";
    $classStmt = executeQuery($db, $classSql);
    if ($classStmt) {
        $classes = $classStmt->fetchAll() ?: [];
    }
    
    // Get days - PERHATIAN: tabel namanya 'day' bukan 'days'
    $daySql = "SELECT * FROM day WHERE is_active = 'Y' ORDER BY day_order";
    $dayStmt = executeQuery($db, $daySql);
    if ($dayStmt) {
        $days = $dayStmt->fetchAll() ?: [];
    }
    
    // Get shifts - PERHATIAN: tabel namanya 'shift' bukan 'shifts'
    $shiftSql = "SELECT * FROM shift ORDER BY time_in";
    $shiftStmt = executeQuery($db, $shiftSql);
    if ($shiftStmt) {
        $shifts = $shiftStmt->fetchAll() ?: [];
    }

    // Get day schedule config
    $configStmt = executeQuery($db, "SELECT day_id, school_start_time, activity1_label, activity1_minutes, activity2_label, activity2_minutes FROM day_schedule_config");
    if ($configStmt) {
        foreach ($configStmt->fetchAll() as $cfg) {
            $day_configs[(int)$cfg['day_id']] = $cfg;
        }
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading dropdown data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    // Set default empty arrays
    $teachers = $teachers ?: [];
    $classes = $classes ?: [];
    $days = $days ?: [];
    $shifts = $shifts ?: [];
    $day_configs = $day_configs ?: [];
}

// Build base SQL for schedules - PERBAIKI JOIN TABEL
$sql = "SELECT ts.*, 
               t.teacher_name, 
               t.subject as teacher_subject, 
               c.class_name, 
               d.day_name, 
               d.day_order, 
               s.time_in, 
               s.time_out, 
               s.shift_name
        FROM teacher_schedule ts
        LEFT JOIN teacher t ON ts.teacher_id = t.id
        LEFT JOIN class c ON ts.class_id = c.class_id
        LEFT JOIN day d ON ts.day_id = d.day_id
        LEFT JOIN shift s ON ts.shift_id = s.shift_id
        WHERE 1=1";

// Apply filters
$params = [];
if ($filter_day !== '') {
    if (is_numeric($filter_day)) {
        $sql .= " AND ts.day_id = ?";
        $params[] = intval($filter_day);
    } else {
        $sql .= " AND LOWER(d.day_name) = ?";
        $params[] = strtolower(trim((string)$filter_day));
    }
}
if ($filter_teacher && is_numeric($filter_teacher)) {
    $sql .= " AND ts.teacher_id = ?";
    $params[] = intval($filter_teacher);
}
if ($filter_class && is_numeric($filter_class)) {
    $sql .= " AND ts.class_id = ?";
    $params[] = intval($filter_class);
}

$sql .= " ORDER BY d.day_order, s.time_in, ts.schedule_id";

// Execute query dengan error handling yang lebih kuat
try {
    $stmt = executeQuery($db, $sql, $params);
    
    if ($stmt) {
        $schedules = $stmt->fetchAll() ?: [];
        foreach ($schedules as &$schedule) {
            $computedTimes = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', $schedule['day_id'] ?? 0);
            if ($computedTimes) {
                $schedule['time_in'] = $computedTimes[0];
                $schedule['time_out'] = $computedTimes[1];
            }
        }
        unset($schedule);
    } else {
        // Log error untuk debugging
        error_log("Schedule query failed: " . $sql);
        echo '<div class="alert alert-warning">Tidak dapat memuat data jadwal. Periksa koneksi database dan struktur tabel.</div>';
        $schedules = [];
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading schedules: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $schedules = [];
}

// Create teacher data for JavaScript
$teacher_data = [];
foreach ($teachers as $teacher) {
    $teacher_data[$teacher['id']] = [
        'name' => $teacher['teacher_name'] ?? '',
        'subject' => $teacher['subject'] ?? ''
    ];
}
?>

<div class="alert alert-info mb-3">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Tips:</strong> Mata pelajaran akan otomatis terisi berdasarkan guru yang dipilih. Anda tetap bisa mengeditnya jika diperlukan.
</div>

<div class="data-table-container mb-4">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-clock text-warning me-2"></i>Pengaturan Jam Absensi</h5>
    </div>
    <form method="POST" action="admin.php?table=schedule">
        <input type="hidden" name="config_action" value="day_schedule">
        <div class="table-responsive">
            <table class="table table-hover schedule-config-table">
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Masuk Sekolah</th>
                        <th>Kegiatan 1</th>
                        <th>Kegiatan 2</th>
                        <th>JP1 Mulai</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($days)): ?>
                    <?php foreach ($days as $day): 
                        $dayId = (int)($day['day_id'] ?? 0);
                        $cfg = $day_configs[$dayId] ?? [
                            'school_start_time' => '06:30:00',
                            'activity1_label' => '',
                            'activity1_minutes' => 0,
                            'activity2_label' => '',
                            'activity2_minutes' => 0
                        ];
                        $preMinutes = (int)($cfg['activity1_minutes'] ?? 0) + (int)($cfg['activity2_minutes'] ?? 0);
                        $jp1Times = calculateJpTimeRange(1, 1, $cfg['school_start_time'], $preMinutes);
                        $jp1Start = $jp1Times[0] ?? '--:--';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($day['day_name'] ?? '-'); ?></td>
                        <td style="min-width: 140px;">
                            <input type="time" class="form-control form-control-sm" step="60"
                                   name="day_config[<?php echo $dayId; ?>][school_start_time]"
                                   value="<?php echo htmlspecialchars(substr($cfg['school_start_time'], 0, 5)); ?>">
                        </td>
                        <td style="min-width: 220px;">
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm"
                                       name="day_config[<?php echo $dayId; ?>][activity1_label]"
                                       value="<?php echo htmlspecialchars($cfg['activity1_label'] ?? ''); ?>"
                                       placeholder="Kegiatan 1">
                                <input type="number" class="form-control form-control-sm" min="0" max="180" step="5"
                                       name="day_config[<?php echo $dayId; ?>][activity1_minutes]"
                                       value="<?php echo htmlspecialchars((int)($cfg['activity1_minutes'] ?? 0)); ?>"
                                       placeholder="Menit">
                            </div>
                        </td>
                        <td style="min-width: 220px;">
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm"
                                       name="day_config[<?php echo $dayId; ?>][activity2_label]"
                                       value="<?php echo htmlspecialchars($cfg['activity2_label'] ?? ''); ?>"
                                       placeholder="Kegiatan 2">
                                <input type="number" class="form-control form-control-sm" min="0" max="180" step="5"
                                       name="day_config[<?php echo $dayId; ?>][activity2_minutes]"
                                       value="<?php echo htmlspecialchars((int)($cfg['activity2_minutes'] ?? 0)); ?>"
                                       placeholder="Menit">
                            </div>
                        </td>
                        <td>
                            <span class="badge schedule-jp1-badge"><?php echo htmlspecialchars(substr($jp1Start, 0, 5)); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">Data hari tidak tersedia</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">JP5 & JP9 adalah jam istirahat 15 menit dan tidak dapat dipilih untuk jadwal.</small>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-save me-1"></i> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>

<div class="data-table-container mb-4">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-list text-primary me-2"></i>Detail JP (Guru & Kelas)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>JP</th>
                    <th>Hari</th>
                    <th>Guru</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Jam Mulai</th>
                    <th>Jam Selesai</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($schedules)): ?>
                    <?php foreach ($schedules as $schedule): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($schedule['shift_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($schedule['day_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($schedule['teacher_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($schedule['class_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($schedule['subject'] ?? '-'); ?></td>
                        <td><?php echo !empty($schedule['time_in']) ? date('H:i', strtotime($schedule['time_in'])) : '-'; ?></td>
                        <td><?php echo !empty($schedule['time_out']) ? date('H:i', strtotime($schedule['time_out'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada detail JP</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="data-table-container">
    <div class="table-header">
        <h5 class="table-title"><i class="fas fa-calendar-alt text-primary me-2"></i>Jadwal Mengajar</h5>
        <button class="btn btn-primary add-btn" data-table="schedule">
            <i class="fas fa-plus-circle me-2"></i>Tambah Jadwal
        </button>
    </div>
    
    <!-- Filters -->
    <div class="filter-section mb-3">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Hari</label>
                <select class="form-select" id="filterDay">
                    <option value="">Semua Hari</option>
                    <?php foreach($days as $day): ?>
                    <option value="<?php echo $day['day_id'] ?? ''; ?>" <?php echo ($filter_day == ($day['day_id'] ?? '')) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($day['day_name'] ?? ''); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Shift</label>
                <select class="form-select" id="filterShift">
                    <option value="">Semua Shift</option>
                    <?php foreach($shifts as $shift): ?>
                    <option value="<?php echo $shift['shift_id'] ?? ''; ?>">
                        <?php echo htmlspecialchars($shift['shift_name'] ?? ''); ?> 
                        (<?php echo isset($shift['time_in']) ? date('H:i', strtotime($shift['time_in'])) : '--:--'; ?> - 
                         <?php echo isset($shift['time_out']) ? date('H:i', strtotime($shift['time_out'])) : '--:--'; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Guru</label>
                <select class="form-select" id="filterTeacher">
                    <option value="">Semua Guru</option>
                    <?php foreach($teachers as $teacher): ?>
                    <option value="<?php echo $teacher['id'] ?? ''; ?>" <?php echo ($filter_teacher == ($teacher['id'] ?? '')) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($teacher['teacher_name'] ?? ''); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kelas</label>
                <select class="form-select" id="filterClass">
                    <option value="">Semua Kelas</option>
                    <?php foreach($classes as $class): ?>
                    <option value="<?php echo $class['class_id'] ?? ''; ?>" <?php echo ($filter_class == ($class['class_id'] ?? '')) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name'] ?? ''); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-12">
                <div class="d-flex justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary me-2" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="applyFilters()">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover data-table" id="scheduleTable">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Guru</th>
                    <th>Kelas</th>
                    <th>Mata Pelajaran</th>
                    <th>Hari</th>
                    <th>Shift</th>
                    <th>Waktu</th>
                    <th width="150">Aksi</th>
                </tr>
            </thead>
            <tbody>
    <?php if (!empty($schedules)): ?>
        <?php foreach($schedules as $index => $schedule): ?>
        <tr data-day-id="<?php echo (int)($schedule['day_id'] ?? 0); ?>"
            data-day-name="<?php echo htmlspecialchars(strtolower(trim((string)($schedule['day_name'] ?? ''))), ENT_QUOTES); ?>"
            data-shift-id="<?php echo (int)($schedule['shift_id'] ?? 0); ?>"
            data-shift-name="<?php echo htmlspecialchars(strtolower(trim((string)($schedule['shift_name'] ?? ''))), ENT_QUOTES); ?>"
            data-teacher-id="<?php echo (int)($schedule['teacher_id'] ?? 0); ?>"
            data-teacher-name="<?php echo htmlspecialchars(strtolower(trim((string)($schedule['teacher_name'] ?? ''))), ENT_QUOTES); ?>"
            data-class-id="<?php echo (int)($schedule['class_id'] ?? 0); ?>">
            <td><?php echo $index + 1; ?></td>
            <td>
                <?php echo htmlspecialchars($schedule['teacher_name'] ?? 'Unknown'); ?>
                <?php if (!empty($schedule['teacher_subject'])): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($schedule['teacher_subject']); ?></small>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($schedule['class_name'] ?? 'Unknown'); ?></td>
            <td><?php echo htmlspecialchars($schedule['subject'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($schedule['day_name'] ?? 'Unknown'); ?></td>
            <td><?php echo htmlspecialchars($schedule['shift_name'] ?? 'Unknown'); ?></td>
            <td>
                <?php if (!empty($schedule['time_in']) && !empty($schedule['time_out'])): ?>
                    <?php echo date('H:i', strtotime($schedule['time_in'])); ?> - <?php echo date('H:i', strtotime($schedule['time_out'])); ?>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-warning edit-btn" 
                            data-id="<?php echo $schedule['schedule_id'] ?? ''; ?>" 
                            data-table="schedule">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                        <button class="btn btn-outline-danger" disabled title="Operator tidak dapat menghapus data master">
                            <i class="fas fa-trash"></i>
                        </button>
                    <?php else: ?>
                        <a href="admin.php?table=schedule&action=delete&id=<?php echo $schedule['schedule_id'] ?? ''; ?>" 
                           class="btn btn-outline-danger" 
                           onclick="return confirm('Hapus jadwal ini?')">
                            <i class="fas fa-trash"></i>
                        </a>
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

<!-- Schedule Calendar View -->
<div class="row mt-4">
    <div class="col-12">
        <div class="data-table-container">
            <h5 class="mb-3"><i class="fas fa-calendar-week text-info me-2"></i>Kalender Jadwal Mingguan</h5>
            <div id="scheduleCalendar">
                <div class="row">
                    <?php 
                    // Group schedules by day
                    $scheduleByDay = [];
                    foreach($schedules as $schedule) {
                        $dayName = $schedule['day_name'] ?? 'Unknown';
                        if (!isset($scheduleByDay[$dayName])) {
                            $scheduleByDay[$dayName] = [];
                        }
                        $scheduleByDay[$dayName][] = $schedule;
                    }
                    
                    // Display each day
                    if (!empty($days)):
                        foreach($days as $day):
                            if(($day['is_active'] ?? 'Y') == 'Y'):
                    ?>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <strong><?php echo htmlspecialchars($day['day_name'] ?? ''); ?></strong>
                            </div>
                            <div class="card-body p-3" style="max-height: 300px; overflow-y: auto;">
                                <?php if(isset($scheduleByDay[$day['day_name']]) && !empty($scheduleByDay[$day['day_name']])): ?>
                                    <?php foreach($scheduleByDay[$day['day_name']] as $schedule): ?>
                                    <div class="border-start border-3 border-primary p-2 mb-2 bg-light">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-clock"></i> 
                                                    <?php echo !empty($schedule['time_in']) ? date('H:i', strtotime($schedule['time_in'])) : '--:--'; ?> 
                                                    - 
                                                    <?php echo !empty($schedule['time_out']) ? date('H:i', strtotime($schedule['time_out'])) : '--:--'; ?>
                                                </small>
                                                <strong class="d-block text-primary"><?php echo htmlspecialchars($schedule['subject'] ?? '-'); ?></strong>
                                                <small class="d-block"><?php echo htmlspecialchars($schedule['teacher_name'] ?? 'Unknown'); ?></small>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($schedule['class_name'] ?? 'Unknown'); ?></small>
                                            </div>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($schedule['shift_name'] ?? 'Unknown'); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-calendar-times fa-2x mb-2"></i><br>
                                        <small>Tidak ada jadwal</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php 
                            endif;
                        endforeach; 
                    else: ?>
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Data hari tidak tersedia
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mt-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="dashboard-card clickable" onclick="window.location.href='admin.php?table=teacher'">
            <div class="card-icon blue">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <h4 class="card-value"><?php echo count($teachers); ?></h4>
            <p class="card-title">Total Guru</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="dashboard-card clickable" onclick="window.location.href='admin.php?table=class'">
            <div class="card-icon green">
                <i class="fas fa-school"></i>
            </div>
            <h4 class="card-value"><?php echo count($classes); ?></h4>
            <p class="card-title">Total Kelas</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="dashboard-card clickable" onclick="window.location.href='admin.php?table=schedule'">
            <div class="card-icon teal">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h4 class="card-value"><?php echo count($schedules); ?></h4>
            <p class="card-title">Total Jadwal</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="dashboard-card clickable" onclick="applyActiveFilter()">
            <div class="card-icon purple">
                <i class="fas fa-check-circle"></i>
            </div>
            <h4 class="card-value"><?php echo count($days); ?></h4>
            <p class="card-title">Hari Aktif</p>
        </div>
    </div>
</div>

<!-- Di akhir file schedule.php, perbaiki bagian JavaScript: -->
<script>
// Teacher data from PHP
const teacherData = <?php echo json_encode($teacher_data); ?>;

// Filter functions
function applyFilters() {
    const day = document.getElementById('filterDay').value;
    const teacher = document.getElementById('filterTeacher').value;
    const classId = document.getElementById('filterClass').value;
    
    const url = new URL(window.location.href);
    
    if (day) {
        url.searchParams.set('filter_day', day);
    } else {
        url.searchParams.delete('filter_day');
    }
    
    if (teacher) {
        url.searchParams.set('filter_teacher', teacher);
    } else {
        url.searchParams.delete('filter_teacher');
    }
    
    if (classId) {
        url.searchParams.set('filter_class', classId);
    } else {
        url.searchParams.delete('filter_class');
    }
    
    window.location.href = url.toString();
}

function resetFilters() {
    window.location.href = 'admin.php?table=schedule';
}

function applyActiveFilter() {
    // Filter only active days
    window.location.href = 'admin.php?table=schedule&filter_day=active';
}

// DataTable jadwal diinisialisasi di bagian bawah file ini
</script>

<script>
$(document).ready(function() {
    // Initialize DataTable dengan konfigurasi lengkap
    const table = $('#scheduleTable').DataTable({
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data jadwal yang tersedia",
            "infoFiltered": "(difilter dari _MAX_ total data)",
            "zeroRecords": "Data tidak ditemukan",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            },
            "processing": "Memproses..."
        },
        "pageLength": 10,
        "lengthMenu": [10, 25, 50, 100],
        "order": [[0, 'asc']],
        "responsive": true,
        "autoWidth": false,
        "drawCallback": function(settings) {
            // Update nomor urut setelah filter/paging
            const api = this.api();
            const pageInfo = api.page.info();
            
            api.column(0, { page: 'current' }).nodes().each(function(cell, i) {
                cell.innerHTML = pageInfo.start + i + 1;
            });
            
            // Re-attach event listeners untuk tombol edit
            $('.edit-btn[data-table="schedule"]').off('click').on('click', function() {
                const scheduleId = $(this).data('id');
                loadScheduleForm(scheduleId);
            });
        }
    });
    
    // Filter functionality
    $('#filterDay').on('change', applyDataTableFilter);
    $('#filterShift').on('change', applyDataTableFilter);
    $('#filterTeacher').on('change', applyDataTableFilter);
    $('#filterClass').on('change', applyDataTableFilter);
    
    function applyDataTableFilter() {
        table.draw();
    }
    
    // Custom filtering function
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'scheduleTable') {
                return true;
            }

            const dayFilter = $('#filterDay').val();
            const shiftFilter = $('#filterShift').val();
            const teacherFilter = $('#filterTeacher').val();
            const classFilter = $('#filterClass').val();

            const rowNode = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
            if (!rowNode) {
                return true;
            }

            const rowDayId = String($(rowNode).data('day-id') ?? '');
            const rowDayName = String($(rowNode).data('day-name') ?? '').toLowerCase();
            const rowShiftId = String($(rowNode).data('shift-id') ?? '');
            const rowShiftName = String($(rowNode).data('shift-name') ?? '').toLowerCase();
            const rowTeacherId = String($(rowNode).data('teacher-id') ?? '');
            const rowTeacherName = String($(rowNode).data('teacher-name') ?? '').toLowerCase();
            const rowClassId = String($(rowNode).data('class-id') ?? '');
            const dayFilterVal = String(dayFilter ?? '').toLowerCase();
            const shiftFilterVal = String(shiftFilter ?? '').toLowerCase();
            const teacherFilterVal = String(teacherFilter ?? '').toLowerCase();

            if (dayFilter && rowDayId !== String(dayFilter) && rowDayName !== dayFilterVal) {
                return false;
            }

            if (shiftFilter && rowShiftId !== String(shiftFilter) && rowShiftName !== shiftFilterVal) {
                return false;
            }

            if (teacherFilter && rowTeacherId !== String(teacherFilter) && rowTeacherName !== teacherFilterVal) {
                return false;
            }

            if (classFilter && rowClassId !== String(classFilter)) {
                return false;
            }

            return true;
        }
    );
    
    // Load schedule form
    function loadScheduleForm(scheduleId) {
        $('#loadingOverlay').show();
        
        $.ajax({
            url: 'ajax/get_schedule_form.php',
            method: 'POST',
            data: { 
                table: 'schedule', 
                id: scheduleId 
            },
            success: function(response) {
                $('#addModal .modal-content').html(response);
                $('#addModal').modal('show');
                $('#loadingOverlay').hide();
            },
            error: function(xhr, status, error) {
                $('#loadingOverlay').hide();
                alert('Terjadi kesalahan saat memuat data: ' + error);
            }
        });
    }
    
    // Attach event untuk tombol edit
    $(document).on('click', '.edit-btn[data-table="schedule"]', function() {
        const scheduleId = $(this).data('id');
        loadScheduleForm(scheduleId);
    });
    
    // Attach event untuk tombol tambah jadwal
    $('.add-btn[data-table="schedule"]').on('click', function() {
        loadScheduleForm(0);
    });
});
</script>
