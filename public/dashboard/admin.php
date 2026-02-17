<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base path
define('BASE_PATH', dirname(__DIR__));

try {
    require_once BASE_PATH . '/includes/config.php';
    require_once BASE_PATH . '/includes/auth.php';
    require_once BASE_PATH . '/includes/database.php';
    require_once BASE_PATH . '/includes/functions.php';
    require_once BASE_PATH . '/helpers/jp_time_helper.php';
    require_once BASE_PATH . '/includes/database_helper.php';
} catch (Exception $e) {
    die('Error loading configuration: ' . $e->getMessage());
}

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// ROUTING BERDASARKAN ROLE
// Jika siswa, redirect ke dashboard siswa
if (isset($_SESSION['student_id'])) {
    header("Location: dashboard/siswa.php");
    exit();
}

// Jika guru, redirect ke dashboard guru
if (isset($_SESSION['teacher_id'])) {
    header("Location: dashboard/guru.php");
    exit();
}

// Jika bukan admin/operator, redirect ke login
if (!isset($_SESSION['level']) || !in_array((int) $_SESSION['level'], [1, 2], true)) {
    header("Location: login.php");
    exit();
}

$isOperator = isset($_SESSION['level']) && (int) $_SESSION['level'] === 2;
$canDeleteMaster = !$isOperator;
$canManageSystemUsers = !$isOperator;
$canDeleteSchedule = true;
$canDeleteStudent = true;
$canDeleteTeacher = !$isOperator;

require_once '../includes/database.php';
$db = new Database();

if (!isset($_SESSION['last_admin_dashboard_log']) || (time() - $_SESSION['last_admin_dashboard_log']) > 300) {
    if (!empty($_SESSION['user_id'])) {
        logActivity((int) $_SESSION['user_id'], 'admin', 'dashboard_access', 'Admin dashboard accessed');
        $_SESSION['last_admin_dashboard_log'] = time();
    }
}

// Handle actions
$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? 'dashboard';

// Initialize variables
$success = '';
$error = '';

if (!function_exists('getJpDurationMinutes')) {
    function getJpDurationMinutes($jp) {
        return ($jp == 5 || $jp == 9) ? 15 : 45;
    }
}

if (!function_exists('getTimeToleranceMinutesFromSite')) {
    function getTimeToleranceMinutesFromSite($db) {
        try {
            $row = $db->query("SELECT time_tolerance FROM site LIMIT 1")?->fetch();
            $minutes = isset($row['time_tolerance']) ? (int) $row['time_tolerance'] : 0;
            return max(0, $minutes);
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('calculateJpTimeRange')) {
    function calculateJpTimeRange($jp_start, $jp_end, $base_start = '07:00', $pre_minutes = 0, $tolerance_minutes = 0) {
        $base = DateTime::createFromFormat('H:i', (string) $base_start);
        if (!$base) {
            $base = DateTime::createFromFormat('H:i:s', (string) $base_start);
        }
        if (!$base) {
            return null;
        }

        $pre_minutes = max(0, intval($pre_minutes));
        if ($pre_minutes > 0) {
            $base->modify('+' . $pre_minutes . ' minutes');
        }

        $time_in_obj = clone $base;
        $minutes_before = 0;
        for ($jp = 1; $jp < $jp_start; $jp++) {
            $minutes_before += getJpDurationMinutes($jp);
        }
        if ($minutes_before > 0) {
            $time_in_obj->modify('+' . $minutes_before . ' minutes');
        }

        $duration_minutes = 0;
        for ($jp = $jp_start; $jp <= $jp_end; $jp++) {
            $duration_minutes += getJpDurationMinutes($jp);
        }

        $time_out_obj = clone $time_in_obj;
        $time_out_obj->modify('+' . $duration_minutes . ' minutes');

        $tolerance_minutes = max(0, (int) $tolerance_minutes);
        if ($tolerance_minutes > 0) {
            $time_out_obj->modify('+' . $tolerance_minutes . ' minutes');
        }

        return [
            $time_in_obj->format('H:i:s'),
            $time_out_obj->format('H:i:s')
        ];
    }
}

if (!function_exists('parseJpRangeFromShift')) {
    function parseJpRangeFromShift($shift_name) {
        if (preg_match('/JP(\d+)-JP(\d+)/i', $shift_name, $m)) {
            return [intval($m[1]), intval($m[2])];
        }
        return null;
    }
}

if (!function_exists('syncJpShiftTimes')) {
    function syncJpShiftTimes($db, $shift_id, $shift_name, $base_start = '07:00', $pre_minutes = 0, $tolerance_minutes = 0) {
        $range = parseJpRangeFromShift($shift_name);
        if (!$range) return false;
        [$jp_start, $jp_end] = $range;
        $times = calculateJpTimeRange($jp_start, $jp_end, $base_start, $pre_minutes, $tolerance_minutes);
        if (!$times) return false;

        [$expected_in, $expected_out] = $times;
        $shift_row = $db->query("SELECT time_in, time_out FROM shift WHERE shift_id = ?", [$shift_id])->fetch();
        if (!$shift_row) return false;

        if ($shift_row['time_in'] !== $expected_in || $shift_row['time_out'] !== $expected_out) {
            $db->query("UPDATE shift SET time_in = ?, time_out = ? WHERE shift_id = ?", [$expected_in, $expected_out, $shift_id]);
        }

        return [$expected_in, $expected_out];
    }
}

if (!function_exists('syncAllJpShiftTimes')) {
    function syncAllJpShiftTimes($db, $base_start = '07:00', $pre_minutes = 0, $tolerance_minutes = 0) {
        $shift_rows = $db->query("SELECT shift_id, shift_name, time_in, time_out FROM shift WHERE shift_name LIKE 'JP%-%'")?->fetchAll();
        if (!$shift_rows) return;
        foreach ($shift_rows as $row) {
            syncJpShiftTimes($db, $row['shift_id'], $row['shift_name'], $base_start, $pre_minutes, $tolerance_minutes);
        }
    }
}

if (!function_exists('generateStudentCodeCandidate')) {
    function generateStudentCodeCandidate() {
        $letter = chr(random_int(65, 90));
        $digits = str_split(str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT));
        $insertPosition = random_int(0, 3);
        array_splice($digits, $insertPosition, 0, [$letter]);
        return 'SW' . implode('', $digits);
    }
}

if (!function_exists('generateUniqueStudentCode')) {
    function generateUniqueStudentCode($db, $maxAttempts = 200) {
        $attempts = max(10, (int) $maxAttempts);
        for ($i = 0; $i < $attempts; $i++) {
            $candidate = generateStudentCodeCandidate();
            $existsStmt = $db->query("SELECT id FROM student WHERE UPPER(student_code) = ? LIMIT 1", [strtoupper($candidate)]);
            $exists = $existsStmt ? $existsStmt->fetch() : null;
            if (!$exists) {
                return $candidate;
            }
        }

        throw new RuntimeException('Tidak dapat membuat kode siswa unik. Coba lagi.');
    }
}

if (!function_exists('hasTeacherScheduleTriggers')) {
    function hasTeacherScheduleTriggers($db) {
        try {
            $schema = defined('DB_NAME') ? DB_NAME : null;
            if (!$schema) {
                return false;
            }
            $stmt = $db->query(
                "SELECT COUNT(*) as total
                 FROM information_schema.TRIGGERS
                 WHERE TRIGGER_SCHEMA = ?
                 AND TRIGGER_NAME IN ('after_teacher_schedule_insert', 'after_teacher_schedule_update', 'before_teacher_schedule_delete')",
                [$schema]
            );
            $row = $stmt ? $stmt->fetch() : null;
            return !empty($row['total']);
        } catch (Exception $e) {
            return false;
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($table === 'student' && $action === 'delete') {
        if (isset($canDeleteStudent) && !$canDeleteStudent) {
            $error = "Tidak memiliki izin menghapus data siswa.";
            header("Location: admin.php?table=student&error=" . urlencode($error));
            exit();
        }
        $studentId = isset($_POST['delete_student_id']) ? (int)$_POST['delete_student_id'] : 0;
        $reason = trim((string)($_POST['delete_reason'] ?? ''));

        if ($studentId <= 0) {
            $error = "ID siswa tidak valid.";
            header("Location: admin.php?table=student&error=" . urlencode($error));
            exit();
        }
        if ($reason === '') {
            $error = "Alasan penghapusan wajib diisi.";
            header("Location: admin.php?table=student&error=" . urlencode($error));
            exit();
        }

        $backupResult = backupStudentData($db, $studentId, $reason);
        if (empty($backupResult['success'])) {
            $error = $backupResult['error'] ?? 'Gagal melakukan backup data siswa.';
            header("Location: admin.php?table=student&error=" . urlencode($error));
            exit();
        }

        $db->beginTransaction();
        $ok = true;
        $ok = $ok && $db->query("DELETE FROM presence WHERE student_id = ?", [$studentId]);
        $ok = $ok && $db->query("DELETE FROM student_schedule WHERE student_id = ?", [$studentId]);
        $ok = $ok && $db->query("DELETE FROM activity_logs WHERE user_type = 'student' AND user_id = ?", [$studentId]);
        $ok = $ok && $db->query("DELETE FROM student WHERE id = ?", [$studentId]);

        if ($ok) {
            $db->commit();
        } else {
            $db->rollBack();
            $error = "Gagal menghapus data siswa.";
            header("Location: admin.php?table=student&error=" . urlencode($error));
            exit();
        }

        resetAutoIncrementIfEmpty($db, ['presence', 'student_schedule', 'activity_logs', 'student'], 0);

        $filesToDelete = array_merge(
            $backupResult['copied_references'] ?? [],
            $backupResult['copied_attendance'] ?? [],
            $backupResult['match_log_files'] ?? []
        );
        $filesToDelete = array_unique($filesToDelete);
        foreach ($filesToDelete as $filePath) {
            if ($filePath && is_file($filePath)) {
                @unlink($filePath);
            }
        }

        $success = "Siswa berhasil dihapus dan backup tersimpan.";
        header("Location: admin.php?table=student&success=" . urlencode($success));
        exit();
    }

    // Handle shift JP creation (from schedule section)
    if (isset($_POST['shift_action'])) {
        $jp_start = isset($_POST['jp_start']) ? intval($_POST['jp_start']) : 0;
        $jp_end = isset($_POST['jp_end']) ? intval($_POST['jp_end']) : 0;
        $jp_start_time = $_POST['jp_start_time'] ?? '07:00';

        if ($jp_start < 1 || $jp_start > 12 || $jp_end < 1 || $jp_end > 12 || $jp_end < $jp_start) {
            $error = "Rentang JP tidak valid.";
            header("Location: admin.php?table=schedule&error=" . urlencode($error));
            exit();
        }

        if (in_array($jp_start, [5, 9], true) || in_array($jp_end, [5, 9], true)) {
            $error = "JP5 dan JP9 adalah jam istirahat, tidak dapat dipilih.";
            header("Location: admin.php?table=schedule&error=" . urlencode($error));
            exit();
        }

        $time_obj = DateTime::createFromFormat('H:i', $jp_start_time);
        if (!$time_obj) {
            $error = "Format jam mulai JP1 tidak valid.";
            header("Location: admin.php?table=schedule&error=" . urlencode($error));
            exit();
        }

        $tolerance_minutes = getTimeToleranceMinutesFromSite($db);
        $time_range = calculateJpTimeRange($jp_start, $jp_end, $jp_start_time, 0, $tolerance_minutes);
        if (!$time_range) {
            $error = "Format jam mulai JP1 tidak valid.";
            header("Location: admin.php?table=schedule&error=" . urlencode($error));
            exit();
        }
        [$time_in, $time_out] = $time_range;

        $shift_name = "JP{$jp_start}-JP{$jp_end}";

        // Cek duplikasi shift
        $check_shift = $db->query("SELECT COUNT(*) as total FROM shift WHERE shift_name = ?", [$shift_name])->fetch();
        if (!empty($check_shift['total'])) {
            $error = "Shift {$shift_name} sudah ada.";
            header("Location: admin.php?table=schedule&error=" . urlencode($error));
            exit();
        }

        $sql = "INSERT INTO shift (shift_name, time_in, time_out) VALUES (?, ?, ?)";
        $stmt = $db->query($sql, [$shift_name, $time_in, $time_out]);

        if ($stmt) {
            $success = "Shift {$shift_name} berhasil ditambahkan!";
            header("Location: admin.php?table=schedule&success=" . urlencode($success));
        } else {
            $error = "Gagal menambahkan shift.";
            header("Location: admin.php?table=schedule&error=" . urlencode($error));
        }
        exit();
    }

    if (!empty($_POST['config_action']) && $_POST['config_action'] === 'day_schedule') {
        $configs = $_POST['day_config'] ?? [];
        if (!is_array($configs)) {
            $error = "Data pengaturan jadwal tidak valid.";
            header("Location: admin.php?table=schedule&error=" . urlencode($error));
            exit();
        }

        foreach ($configs as $dayId => $config) {
            $dayId = (int) $dayId;
            if ($dayId <= 0) {
                continue;
            }

            $schoolStart = trim((string)($config['school_start_time'] ?? ''));
            if ($schoolStart === '') {
                $schoolStart = '06:30';
            }
            $timeObj = DateTime::createFromFormat('H:i', $schoolStart);
            if (!$timeObj) {
                $timeObj = DateTime::createFromFormat('H:i:s', $schoolStart);
            }
            if (!$timeObj) {
                $error = "Format jam masuk sekolah tidak valid untuk hari ID {$dayId}.";
                header("Location: admin.php?table=schedule&error=" . urlencode($error));
                exit();
            }

            $activity1Label = trim((string)($config['activity1_label'] ?? ''));
            $activity2Label = trim((string)($config['activity2_label'] ?? ''));
            $activity1Minutes = max(0, (int)($config['activity1_minutes'] ?? 0));
            $activity2Minutes = max(0, (int)($config['activity2_minutes'] ?? 0));
            $activity1Label = $activity1Label === '' ? null : $activity1Label;
            $activity2Label = $activity2Label === '' ? null : $activity2Label;

            $db->query(
                "INSERT INTO day_schedule_config (day_id, school_start_time, activity1_label, activity1_minutes, activity2_label, activity2_minutes)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE school_start_time = VALUES(school_start_time),
                                         activity1_label = VALUES(activity1_label),
                                         activity1_minutes = VALUES(activity1_minutes),
                                         activity2_label = VALUES(activity2_label),
                                         activity2_minutes = VALUES(activity2_minutes)",
                [
                    $dayId,
                    $timeObj->format('H:i:s'),
                    $activity1Label,
                    $activity1Minutes,
                    $activity2Label,
                    $activity2Minutes
                ]
            );
        }

        // Recalculate future student schedules based on updated config
        $affectedDays = array_keys($configs);
        foreach ($affectedDays as $dayId) {
            recalculateStudentSchedulesForDay($db, (int)$dayId);
        }

        $success = "Pengaturan jam absensi berhasil diperbarui.";
        header("Location: admin.php?table=schedule&success=" . urlencode($success));
        exit();
    }

    switch ($table) {
        case 'student':
            $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
            $student_nisn = trim((string) ($_POST['student_nisn'] ?? ''));
            $student_name = trim((string) ($_POST['student_name'] ?? ''));
            $class_id = isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0;
            $jurusan_id = isset($_POST['jurusan_id']) ? (int) $_POST['jurusan_id'] : 0;
            $password_input = trim((string) ($_POST['password'] ?? ''));
            $submitted_student_code = strtoupper(preg_replace('/\s+/', '', trim((string) ($_POST['student_code'] ?? ''))));
            if ($submitted_student_code !== '' && strpos($submitted_student_code, 'SW') !== 0) {
                $submitted_student_code = 'SW' . $submitted_student_code;
            }

            if ($student_nisn === '' || $student_name === '' || $class_id <= 0 || $jurusan_id <= 0) {
                $error = "Data siswa wajib diisi lengkap.";
                header("Location: admin.php?table=student&error=" . urlencode($error));
                exit();
            }

            $duplicateNisnStmt = $db->query(
                "SELECT id FROM student WHERE student_nisn = ? AND id != ? LIMIT 1",
                [$student_nisn, $student_id]
            );
            if ($duplicateNisnStmt && $duplicateNisnStmt->fetch()) {
                $error = "NISN sudah digunakan oleh siswa lain.";
                header("Location: admin.php?table=student&error=" . urlencode($error));
                exit();
            }

            if ($student_id === 0) {
                try {
                    $student_code = generateUniqueStudentCode($db);
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                    header("Location: admin.php?table=student&error=" . urlencode($error));
                    exit();
                }

                $password_to_use = $student_code;
                $password = hash('sha256', $password_to_use . PASSWORD_SALT);

                $sql = "INSERT INTO student (student_code, student_nisn, student_password, student_name, class_id, jurusan_id, location_id) 
                        VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = $db->query($sql, [$student_code, $student_nisn, $password, $student_name, $class_id, $jurusan_id]);

                if (!$stmt) {
                    $error = "Gagal menambahkan siswa.";
                    header("Location: admin.php?table=student&error=" . urlencode($error));
                    exit();
                }

                $success = "Siswa berhasil ditambahkan. Password default mengikuti kode siswa.";
            } else {
                $existingStudentStmt = $db->query(
                    "SELECT student_code FROM student WHERE id = ? LIMIT 1",
                    [$student_id]
                );
                $existingStudent = $existingStudentStmt ? $existingStudentStmt->fetch() : null;
                if (!$existingStudent) {
                    $error = "Data siswa tidak ditemukan.";
                    header("Location: admin.php?table=student&error=" . urlencode($error));
                    exit();
                }

                // Kode siswa dipertahankan dan tidak ditampilkan untuk operator.
                $student_code = strtoupper(trim((string) ($existingStudent['student_code'] ?? '')));
                if ($student_code === '' && $submitted_student_code !== '') {
                    $student_code = $submitted_student_code;
                }
                if ($student_code === '') {
                    try {
                        $student_code = generateUniqueStudentCode($db);
                    } catch (RuntimeException $e) {
                        $error = $e->getMessage();
                        header("Location: admin.php?table=student&error=" . urlencode($error));
                        exit();
                    }
                }

                if ($password_input !== '') {
                    $password = hash('sha256', $password_input . PASSWORD_SALT);
                    $sql = "UPDATE student SET student_code = ?, student_nisn = ?, student_password = ?, student_name = ?, class_id = ?, jurusan_id = ? WHERE id = ?";
                    $stmt = $db->query($sql, [$student_code, $student_nisn, $password, $student_name, $class_id, $jurusan_id, $student_id]);
                    $success = "Siswa berhasil diperbarui. Password diubah.";
                } else {
                    $sql = "UPDATE student SET student_code = ?, student_nisn = ?, student_name = ?, class_id = ?, jurusan_id = ? WHERE id = ?";
                    $stmt = $db->query($sql, [$student_code, $student_nisn, $student_name, $class_id, $jurusan_id, $student_id]);
                    $success = "Siswa berhasil diperbarui.";
                }

                if (!$stmt) {
                    $error = "Gagal memperbarui data siswa.";
                    header("Location: admin.php?table=student&error=" . urlencode($error));
                    exit();
                }
            }

            header("Location: admin.php?table=student&success=" . urlencode($success));
            exit();
            break;

        case 'teacher':
            $teacher_id = $_POST['teacher_id'] ?? 0;
            $teacher_code = $_POST['teacher_code'];
            $teacher_name = $_POST['teacher_name'];
            $subject = $_POST['subject'];
            $teacher_type = $_POST['teacher_type'];
            $password_input = $_POST['password'] ?? '';
            
            if ($teacher_id == 0) {
                $username = strtolower(str_replace(' ', '.', $teacher_name));
                // Gunakan password input jika ada, jika tidak gunakan 'guru123'
                $password_to_use = !empty($password_input) ? $password_input : 'guru123';
                $password = hash('sha256', $password_to_use . PASSWORD_SALT);
                
                $sql = "INSERT INTO teacher (teacher_code, teacher_username, teacher_password, teacher_name, subject, teacher_type, location_id) 
                        VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = $db->query($sql, [$teacher_code, $username, $password, $teacher_name, $subject, $teacher_type]);
                $success = "Guru berhasil ditambahkan! Username: $username, Password: $password_to_use";
            } else {
                // Untuk edit, periksa apakah password diisi
                if (!empty($password_input)) {
                    $password = hash('sha256', $password_input . PASSWORD_SALT);
                    $sql = "UPDATE teacher SET teacher_code = ?, teacher_name = ?, subject = ?, teacher_type = ?, teacher_password = ? WHERE id = ?";
                    $stmt = $db->query($sql, [$teacher_code, $teacher_name, $subject, $teacher_type, $password, $teacher_id]);
                    $success = "Guru berhasil diperbarui! Password diubah";
                } else {
                    $sql = "UPDATE teacher SET teacher_code = ?, teacher_name = ?, subject = ?, teacher_type = ? WHERE id = ?";
                    $stmt = $db->query($sql, [$teacher_code, $teacher_name, $subject, $teacher_type, $teacher_id]);
                    $success = "Guru berhasil diperbarui!";
                }
            }
            header("Location: admin.php?table=teacher&success=" . urlencode($success));
            exit();
            break;
            
        case 'class':
            if (isset($_POST['edit_jurusan_id'])) {
                $jurusan_id = intval($_POST['edit_jurusan_id']);
                $code = strtoupper(trim($_POST['edit_code'] ?? ''));
                $name = trim($_POST['edit_name'] ?? '');

                if ($jurusan_id <= 0 || $code === '' || $name === '') {
                    $error = "Kode dan nama jurusan harus diisi!";
                    header("Location: admin.php?table=class&error=" . urlencode($error));
                    exit();
                }

                $check_sql = "SELECT COUNT(*) as total FROM jurusan WHERE code = ? AND jurusan_id != ?";
                $check_stmt = $db->query($check_sql, [$code, $jurusan_id]);
                $result = $check_stmt ? $check_stmt->fetch() : ['total' => 0];
                if (!empty($result['total'])) {
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
            } elseif (isset($_POST['class_id'])) {
                $class_id = intval($_POST['class_id']);
                $class_name = trim($_POST['class_name'] ?? '');
                $jurusan_id = intval($_POST['jurusan_id'] ?? 0);

                if ($class_name === '' || $jurusan_id <= 0) {
                    $error = "Nama kelas dan jurusan harus diisi!";
                    header("Location: admin.php?table=class&error=" . urlencode($error));
                    exit();
                }

                $check_sql = "SELECT COUNT(*) as total FROM class WHERE class_name = ? AND class_id != ?";
                $check_stmt = $db->query($check_sql, [$class_name, $class_id]);
                $result = $check_stmt ? $check_stmt->fetch() : ['total' => 0];
                if (!empty($result['total'])) {
                    $error = "Nama kelas '$class_name' sudah ada!";
                    header("Location: admin.php?table=class&error=" . urlencode($error));
                    exit();
                }

                if ($class_id == 0) {
                    $sql = "INSERT INTO class (class_name, jurusan_id) VALUES (?, ?)";
                    $stmt = $db->query($sql, [$class_name, $jurusan_id]);
                    $success = $stmt ? "Kelas berhasil ditambahkan!" : "Gagal menambahkan kelas!";
                } else {
                    $sql = "UPDATE class SET class_name = ?, jurusan_id = ? WHERE class_id = ?";
                    $stmt = $db->query($sql, [$class_name, $jurusan_id, $class_id]);
                    $success = $stmt ? "Kelas berhasil diperbarui!" : "Gagal memperbarui kelas!";
                }
            } elseif (isset($_POST['code']) && isset($_POST['name'])) {
                $code = strtoupper(trim($_POST['code'] ?? ''));
                $name = trim($_POST['name'] ?? '');

                if ($code === '' || $name === '') {
                    $error = "Kode dan nama jurusan harus diisi!";
                    header("Location: admin.php?table=class&error=" . urlencode($error));
                    exit();
                }

                $check_sql = "SELECT COUNT(*) as total FROM jurusan WHERE code = ?";
                $check_stmt = $db->query($check_sql, [$code]);
                $result = $check_stmt ? $check_stmt->fetch() : ['total' => 0];
                if (!empty($result['total'])) {
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
            } else {
                $error = "Permintaan tidak valid.";
                header("Location: admin.php?table=class&error=" . urlencode($error));
                exit();
            }

            header("Location: admin.php?table=class&" . (isset($success) ? "success=" . urlencode($success) : "error=" . urlencode($error)));
            exit();
            break;
            
        // Di bagian POST schedule, tambahkan kode untuk membuat jadwal siswa
        case 'schedule':
            $schedule_id = $_POST['schedule_id'] ?? 0;
            $teacher_id = $_POST['teacher_id'];
            $class_id = $_POST['class_id'];
            $subject = $_POST['subject'];
            $day_id = $_POST['day_id'];
            $jp_start = isset($_POST['jp_start']) ? intval($_POST['jp_start']) : 0;
            $jp_end = isset($_POST['jp_end']) ? intval($_POST['jp_end']) : 0;
            $shift_id = $_POST['shift_id'] ?? 0;

            if (in_array($jp_start, [5, 9], true) || in_array($jp_end, [5, 9], true)) {
                $error = "JP5 dan JP9 adalah jam istirahat, tidak dapat dipilih.";
                header("Location: admin.php?table=schedule&error=" . urlencode($error));
                exit();
            }

            if ($jp_start > 0 && $jp_end > 0) {
                $shift_name = "JP{$jp_start}-JP{$jp_end}";
                $shift_row = $db->query("SELECT shift_id, time_in, time_out FROM shift WHERE shift_name = ?", [$shift_name])->fetch();

            if ($shift_row) {
                $shift_id = $shift_row['shift_id'];
        $defaultDayId = getDefaultDayId($db);
        $defaultConfig = getDayScheduleConfig($db, $defaultDayId);
        $tolerance_minutes = getTimeToleranceMinutesFromSite($db);
        syncJpShiftTimes($db, $shift_id, $shift_name, $defaultConfig['school_start_time'], $defaultConfig['pre_minutes'], $tolerance_minutes);
            } else {
                $defaultDayId = getDefaultDayId($db);
                $defaultConfig = getDayScheduleConfig($db, $defaultDayId);
                $tolerance_minutes = getTimeToleranceMinutesFromSite($db);
                $time_range = calculateJpTimeRange($jp_start, $jp_end, $defaultConfig['school_start_time'], $defaultConfig['pre_minutes'], $tolerance_minutes);
                    if (!$time_range) {
                        $error = "Format jam mulai JP1 tidak valid.";
                        header("Location: admin.php?table=schedule&error=" . urlencode($error));
                        exit();
                    }
                    [$time_in, $time_out] = $time_range;

                    $db->query(
                        "INSERT INTO shift (shift_name, time_in, time_out) VALUES (?, ?, ?)",
                        [$shift_name, $time_in, $time_out]
                    );
                    $shift_id = $db->lastInsertId();
                }
            }
            
            // Check conflict
            $conflict_sql = "SELECT COUNT(*) as total FROM teacher_schedule 
                            WHERE teacher_id = ? AND day_id = ? AND shift_id = ? AND schedule_id != ?";
    $conflict_stmt = $db->query($conflict_sql, [$teacher_id, $day_id, $shift_id, $schedule_id]);
    $conflict = $conflict_stmt->fetch();
    
    if ($conflict['total'] > 0) {
        $error = "Guru sudah memiliki jadwal di hari dan shift yang sama!";
    } else {
        if ($schedule_id == 0) {
            // Insert new schedule
            $sql = "INSERT INTO teacher_schedule (teacher_id, class_id, subject, day_id, shift_id) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->query($sql, [$teacher_id, $class_id, $subject, $day_id, $shift_id]);
            
            if ($stmt) {
                $new_schedule_id = $db->lastInsertId();
                $success = "Jadwal berhasil ditambahkan!";
                
                // Generate student schedules (skip if DB triggers already handle it)
                if (!hasTeacherScheduleTriggers($db)) {
                    generateStudentSchedules($new_schedule_id, $db);
                }
            } else {
                $error = "Gagal menambahkan jadwal!";
            }
        } else {
            $sql = "UPDATE teacher_schedule SET teacher_id = ?, class_id = ?, subject = ?, day_id = ?, shift_id = ? 
                    WHERE schedule_id = ?";
            $stmt = $db->query($sql, [$teacher_id, $class_id, $subject, $day_id, $shift_id, $schedule_id]);
            $success = "Jadwal berhasil diperbarui!";
            
            // Update student schedules
            updateStudentSchedules($schedule_id, $db);
        }
    }
    
    if (isset($success) && $success) {
        header("Location: admin.php?table=schedule&success=" . urlencode($success));
    } else {
        header("Location: admin.php?table=schedule&error=" . urlencode($error ?? "Terjadi kesalahan"));
    }
    exit();
    break;
    }
}

// Sync JP shifts on schedule page load
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $table === 'schedule') {
    $defaultDayId = getDefaultDayId($db);
    $defaultConfig = getDayScheduleConfig($db, $defaultDayId);
    $tolerance_minutes = getTimeToleranceMinutesFromSite($db);
    syncAllJpShiftTimes($db, $defaultConfig['school_start_time'], $defaultConfig['pre_minutes'], $tolerance_minutes);
    $dbHelper = new DatabaseHelper($db);
    $dbHelper->ensureStudentSchedulesForAll(6);
}

// Fungsi untuk generate jadwal siswa
function generateStudentSchedules($teacher_schedule_id, $db) {
    // Get schedule details
    $schedule_sql = "SELECT ts.*, sh.shift_name, sh.time_in, sh.time_out 
                     FROM teacher_schedule ts
                     LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
                     WHERE ts.schedule_id = ?";
    $schedule = $db->query($schedule_sql, [$teacher_schedule_id])->fetch();
    
    if (!$schedule) return false;
    
    // Get students in the class
    $students_sql = "SELECT id FROM student WHERE class_id = ?";
    $students = $db->query($students_sql, [$schedule['class_id']])->fetchAll();
    
    $tz = new DateTimeZone('Asia/Jakarta');
    $today = new DateTime('today', $tz);
    $endDate = (clone $today)->modify('+6 months');
    $dayId = (int) $schedule['day_id'];
    if ($dayId <= 0) {
        return false;
    }
    $startDow = (int) $today->format('N');
    $diff = ($dayId - $startDow + 7) % 7;
    $schedule_date = (clone $today)->modify('+' . $diff . ' days');

    $computedTimes = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', $schedule['day_id']);
    $timeIn = $computedTimes[0] ?? $schedule['time_in'];
    $timeOut = $computedTimes[1] ?? $schedule['time_out'];

    for ($date = $schedule_date; $date <= $endDate; $date->modify('+7 days')) {
        $schedule_date = $date->format('Y-m-d');

        foreach ($students as $student) {
            $check_sql = "SELECT COUNT(*) as count FROM student_schedule 
                          WHERE student_id = ? 
                          AND teacher_schedule_id = ? 
                          AND schedule_date = ?";
            $check = $db->query($check_sql, [$student['id'], $teacher_schedule_id, $schedule_date])->fetch();

            if ($check['count'] == 0) {
                $insert_sql = "INSERT INTO student_schedule 
                              (student_id, teacher_schedule_id, schedule_date, time_in, time_out, status) 
                              VALUES (?, ?, ?, ?, ?, 'ACTIVE')";
                $db->query($insert_sql, [
                    $student['id'],
                    $teacher_schedule_id,
                    $schedule_date,
                    $timeIn,
                    $timeOut
                ]);
            }
        }
    }
    
    return true;
}

// Fungsi untuk update jadwal siswa
function updateStudentSchedules($teacher_schedule_id, $db) {
    // Get updated schedule details
    $schedule_sql = "SELECT ts.*, sh.shift_name, sh.time_in, sh.time_out 
                     FROM teacher_schedule ts
                     LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
                     WHERE ts.schedule_id = ?";
    $schedule = $db->query($schedule_sql, [$teacher_schedule_id])->fetch();
    
    if (!$schedule) return false;
    
    $computedTimes = calculateJpTimeRangeFromShiftForDay($db, $schedule['shift_name'] ?? '', $schedule['day_id']);
    $timeIn = $computedTimes[0] ?? $schedule['time_in'];
    $timeOut = $computedTimes[1] ?? $schedule['time_out'];

    // Update future student schedules
    $today = date('Y-m-d');
    $update_sql = "UPDATE student_schedule 
                   SET time_in = ?, time_out = ?
                   WHERE teacher_schedule_id = ? 
                   AND schedule_date >= ?
                   AND status = 'ACTIVE'";
    
    $db->query($update_sql, [
        $timeIn,
        $timeOut,
        $teacher_schedule_id,
        $today
    ]);
    
    return true;
}

function recalculateStudentSchedulesForDay($db, $day_id) {
    $day_id = (int) $day_id;
    if ($day_id <= 0) {
        return false;
    }

    $rows = $db->query(
        "SELECT ts.schedule_id, ts.day_id, sh.shift_name, sh.time_in, sh.time_out
         FROM teacher_schedule ts
         JOIN shift sh ON ts.shift_id = sh.shift_id
         WHERE ts.day_id = ?",
        [$day_id]
    )?->fetchAll();

    if (!$rows) return false;

    foreach ($rows as $row) {
        $computedTimes = calculateJpTimeRangeFromShiftForDay($db, $row['shift_name'] ?? '', $row['day_id']);
        $timeIn = $computedTimes[0] ?? $row['time_in'];
        $timeOut = $computedTimes[1] ?? $row['time_out'];
        $db->query(
            "UPDATE student_schedule
             SET time_in = ?, time_out = ?
             WHERE teacher_schedule_id = ?
             AND schedule_date >= CURDATE()
             AND status = 'ACTIVE'",
            [$timeIn, $timeOut, $row['schedule_id']]
        );
    }

    return true;
}

function sanitizeBackupPart($value) {
    $value = trim((string) $value);
    $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value);
    $value = trim($value, '_');
    return $value;
}

function ensureDirectory($path) {
    if (is_dir($path)) {
        return true;
    }
    return mkdir($path, 0777, true);
}

function writeTextFile($path, $content) {
    if (is_array($content)) {
        $content = implode(PHP_EOL, array_map('strval', $content));
    }
    return file_put_contents($path, (string)$content);
}

function writeCsvFile($path, $rows, $headers = null) {
    $fp = fopen($path, 'w');
    if (!$fp) {
        return false;
    }
    fwrite($fp, "\xEF\xBB\xBF");

    if ($headers === null && is_array($rows) && !empty($rows)) {
        $first = reset($rows);
        if (is_array($first)) {
            $headers = array_keys($first);
        }
    }
    if ($headers && is_array($headers)) {
        fputcsv($fp, $headers);
    }

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (is_array($row)) {
                if ($headers) {
                    $line = [];
                    foreach ($headers as $header) {
                        $line[] = $row[$header] ?? '';
                    }
                    fputcsv($fp, $line);
                } else {
                    fputcsv($fp, $row);
                }
            } else {
                fputcsv($fp, [$row]);
            }
        }
    }

    fclose($fp);
    return true;
}

function resolveAttendanceFile($basePath, $date, $filename) {
    if (!$filename) return null;
    $filename = ltrim($filename, '/\\');
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        $candidate = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (is_file($candidate)) {
            return $candidate;
        }
        $candidate = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'attendance' . DIRECTORY_SEPARATOR . $filename;
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    if ($date) {
        $candidate = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'attendance' . DIRECTORY_SEPARATOR . $date . DIRECTORY_SEPARATOR . $filename;
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    $candidate = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'attendance' . DIRECTORY_SEPARATOR . $filename;
    if (is_file($candidate)) {
        return $candidate;
    }
    return null;
}

function copyFilesUnique($files, $destDir) {
    $copied = [];
    if (!ensureDirectory($destDir)) {
        return $copied;
    }
    foreach ($files as $file) {
        if (!$file || !is_file($file)) continue;
        $real = realpath($file) ?: $file;
        if (isset($copied[$real])) continue;
        $destPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . basename($file);
        if (@copy($file, $destPath)) {
            $copied[$real] = $destPath;
        }
    }
    return $copied;
}

function resetAutoIncrementIfEmpty($db, $tableNames, $nextValue = 0) {
    $tables = is_array($tableNames) ? $tableNames : [$tableNames];

    foreach ($tables as $tableName) {
        $tableName = trim((string) $tableName);
        if ($tableName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            continue;
        }

        $statusStmt = $db->query("SHOW TABLE STATUS LIKE '{$tableName}'");
        $statusRow = $statusStmt ? $statusStmt->fetch() : null;
        if (!$statusRow || $statusRow['Auto_increment'] === null) {
            continue;
        }

        $rowStmt = $db->query("SELECT COUNT(*) as total FROM `{$tableName}`");
        $row = $rowStmt ? $rowStmt->fetch() : null;
        $total = (int) ($row['total'] ?? 0);

        if ($total === 0) {
            $next = (int) $nextValue;
            if ($next < 0) {
                $next = 0;
            }
            $db->query("ALTER TABLE `{$tableName}` AUTO_INCREMENT = {$next}");
        }
    }
}

function backupStudentData($db, $studentId, $reason) {
    $studentStmt = $db->query("SELECT * FROM student WHERE id = ?", [$studentId]);
    $student = $studentStmt ? $studentStmt->fetch() : null;
    if (!$student) {
        return ['success' => false, 'error' => 'Data siswa tidak ditemukan.'];
    }

    $nisn = trim((string)($student['student_nisn'] ?? ''));
    $name = trim((string)($student['student_name'] ?? ''));
    $safeNisn = sanitizeBackupPart($nisn ?: ('id_' . $studentId));
    $safeName = sanitizeBackupPart($name);
    $folderName = $safeNisn . ($safeName ? '_' . $safeName : '');

    $backupRoot = BASE_PATH . '/uploads/temp/junk-capture-student';
    if (!ensureDirectory($backupRoot)) {
        return ['success' => false, 'error' => 'Gagal membuat folder backup.'];
    }

    $backupDir = rtrim($backupRoot, '/\\') . DIRECTORY_SEPARATOR . $folderName;
    if (is_dir($backupDir)) {
        $backupDir .= '_' . date('Ymd_His');
    }
    if (!ensureDirectory($backupDir)) {
        return ['success' => false, 'error' => 'Gagal membuat folder backup siswa.'];
    }

    $logsDir = $backupDir . DIRECTORY_SEPARATOR . 'logs';
    $attendanceDir = $backupDir . DIRECTORY_SEPARATOR . 'attendance';
    $attendancePhotosDir = $attendanceDir . DIRECTORY_SEPARATOR . 'photos';
    $referencesDir = $backupDir . DIRECTORY_SEPARATOR . 'references';
    $dataDir = $backupDir . DIRECTORY_SEPARATOR . 'data';

    ensureDirectory($logsDir);
    ensureDirectory($attendanceDir);
    ensureDirectory($attendancePhotosDir);
    ensureDirectory($referencesDir);
    ensureDirectory($dataDir);

    $readmeLines = [
        'Backup Data Siswa',
        '=================',
        'Folder ini berisi arsip data siswa yang dihapus.',
        'Semua file disimpan dalam format TXT/CSV agar mudah dibuka.',
        '',
        'Isi folder:',
        '- data/student.txt',
        '- data/student_schedule.csv',
        '- attendance/presence_records.csv',
        '- attendance/summary.txt',
        '- logs/activity_logs.csv',
        '- logs/attendance_log.csv',
        '- logs/match_logs.csv',
        '- references/* (foto referensi)',
        '- attendance/photos/* (foto absensi & validation card)'
    ];
    writeTextFile($backupDir . DIRECTORY_SEPARATOR . 'README.txt', $readmeLines);

    // Activity logs
    $activityLogsStmt = $db->query(
        "SELECT * FROM activity_logs WHERE user_type = 'student' AND user_id = ? ORDER BY created_at DESC",
        [$studentId]
    );
    $activityLogs = $activityLogsStmt ? $activityLogsStmt->fetchAll() : [];
    writeCsvFile($logsDir . DIRECTORY_SEPARATOR . 'activity_logs.csv', $activityLogs);

    // Presence records
    $presenceStmt = $db->query(
        "SELECT * FROM presence WHERE student_id = ? ORDER BY presence_date DESC, time_in DESC",
        [$studentId]
    );
    $presenceRecords = $presenceStmt ? $presenceStmt->fetchAll() : [];
    writeCsvFile($attendanceDir . DIRECTORY_SEPARATOR . 'presence_records.csv', $presenceRecords);

    // Attendance summary
    $summaryStmt = $db->query(
        "SELECT 
            SUM(CASE WHEN present_id = 1 THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN present_id = 2 THEN 1 ELSE 0 END) as sakit,
            SUM(CASE WHEN present_id = 3 THEN 1 ELSE 0 END) as izin,
            SUM(CASE WHEN present_id = 4 THEN 1 ELSE 0 END) as alpa,
            SUM(CASE WHEN is_late = 'Y' THEN 1 ELSE 0 END) as terlambat,
            COUNT(*) as total
        FROM presence WHERE student_id = ?",
        [$studentId]
    );
    $summary = $summaryStmt ? $summaryStmt->fetch() : [];
    $summaryLines = [
        'Ringkasan Kehadiran',
        '--------------------',
        'Hadir: ' . ($summary['hadir'] ?? 0),
        'Sakit: ' . ($summary['sakit'] ?? 0),
        'Izin: ' . ($summary['izin'] ?? 0),
        'Alpa: ' . ($summary['alpa'] ?? 0),
        'Terlambat: ' . ($summary['terlambat'] ?? 0),
        'Total: ' . ($summary['total'] ?? 0)
    ];
    writeTextFile($attendanceDir . DIRECTORY_SEPARATOR . 'summary.txt', $summaryLines);

    // Student schedule backup
    $scheduleStmt = $db->query(
        "SELECT * FROM student_schedule WHERE student_id = ? ORDER BY schedule_date DESC",
        [$studentId]
    );
    $studentSchedules = $scheduleStmt ? $scheduleStmt->fetchAll() : [];
    writeCsvFile($dataDir . DIRECTORY_SEPARATOR . 'student_schedule.csv', $studentSchedules);

    // Student snapshot
    $studentLines = ['Data Siswa', '-----------'];
    foreach ($student as $key => $value) {
        $studentLines[] = $key . ': ' . (is_scalar($value) ? (string)$value : json_encode($value));
    }
    writeTextFile($dataDir . DIRECTORY_SEPARATOR . 'student.txt', $studentLines);

    // Reference images
    $referenceFiles = [];
    $facesDir = BASE_PATH . '/uploads/faces/';
    if (!empty($student['photo'])) {
        $referenceFiles[] = $facesDir . $student['photo'];
    }
    if (!empty($student['photo_reference'])) {
        $referenceFiles[] = $facesDir . $student['photo_reference'];
    }
    if ($nisn) {
        $nisnMatches = glob($facesDir . '*' . $nisn . '*');
        if ($nisnMatches) {
            $referenceFiles = array_merge($referenceFiles, $nisnMatches);
        }
    }
    $copiedReferences = copyFilesUnique($referenceFiles, $referencesDir);

    // Match logs
    $matchLogs = glob(BASE_PATH . '/uploads/temp/match_log_' . $studentId . '_*.json') ?: [];
    $matchLogEntries = [];
    foreach ($matchLogs as $matchLogFile) {
        if (!is_file($matchLogFile)) continue;
        $raw = file_get_contents($matchLogFile);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $isList = array_keys($decoded) === range(0, count($decoded) - 1);
            if ($isList) {
                foreach ($decoded as $entry) {
                    if (is_array($entry)) {
                        $entry['source_file'] = basename($matchLogFile);
                        $matchLogEntries[] = $entry;
                    }
                }
            } else {
                $decoded['source_file'] = basename($matchLogFile);
                $matchLogEntries[] = $decoded;
            }
        } else {
            $matchLogEntries[] = [
                'source_file' => basename($matchLogFile),
                'raw' => $raw
            ];
        }
    }
    writeCsvFile($logsDir . DIRECTORY_SEPARATOR . 'match_logs.csv', $matchLogEntries);

    // Attendance logs from temp/capture
    $attendanceLogEntries = [];
    $attendanceLogFile = BASE_PATH . '/uploads/temp/capture/attendance_log.txt';
    if (is_file($attendanceLogFile)) {
        $handle = fopen($attendanceLogFile, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') continue;
                $decoded = json_decode($line, true);
                if (!is_array($decoded)) continue;
                if ((int)($decoded['student_id'] ?? 0) !== (int)$studentId) continue;
                $attendanceLogEntries[] = $decoded;
            }
            fclose($handle);
        }
    }
    writeCsvFile($logsDir . DIRECTORY_SEPARATOR . 'attendance_log.csv', $attendanceLogEntries);

    // Attendance photos
    $attendanceFiles = [];
    foreach ($presenceRecords as $record) {
        $presenceDate = $record['presence_date'] ?? '';
        foreach (['picture_in', 'picture_out'] as $field) {
            if (!empty($record[$field])) {
                $resolved = resolveAttendanceFile(BASE_PATH, $presenceDate, $record[$field]);
                if ($resolved) {
                    $attendanceFiles[] = $resolved;
                }
            }
        }
    }
    foreach ($attendanceLogEntries as $entry) {
        foreach (['attendance_path', 'validation_path'] as $field) {
            if (!empty($entry[$field])) {
                $candidate = BASE_PATH . DIRECTORY_SEPARATOR . ltrim($entry[$field], '/\\');
                if (is_file($candidate)) {
                    $attendanceFiles[] = $candidate;
                }
            }
        }
    }
    $copiedAttendance = copyFilesUnique($attendanceFiles, $attendancePhotosDir);

    $metaLines = [
        'Backup Metadata',
        '---------------',
        'Deleted at: ' . date('c'),
        'Deleted by (user_id): ' . ($_SESSION['user_id'] ?? '-'),
        'Reason: ' . $reason,
        'Student ID: ' . $studentId,
        'NISN: ' . ($nisn ?: '-'),
        'Name: ' . ($name ?: '-'),
        'Presence records: ' . count($presenceRecords),
        'Activity logs: ' . count($activityLogs),
        'Attendance log entries: ' . count($attendanceLogEntries),
        'Reference files: ' . count($copiedReferences),
        'Attendance files: ' . count($copiedAttendance),
        'Match logs: ' . count($matchLogEntries)
    ];
    writeTextFile($backupDir . DIRECTORY_SEPARATOR . 'backup_meta.txt', $metaLines);

    return [
        'success' => true,
        'backup_dir' => $backupDir,
        'student' => $student,
        'copied_references' => array_keys($copiedReferences),
        'copied_attendance' => array_keys($copiedAttendance),
        'match_log_files' => $matchLogs
    ];
}

// Handle deletions
if ($action == 'delete') {
    switch ($table) {
        case 'student':
            $error = "Penghapusan siswa wajib menggunakan alasan melalui modal.";
            header("Location: admin.php?table=student&error=" . urlencode($error));
            exit();
            break;
            
        case 'teacher':
            if (isset($canDeleteTeacher) && !$canDeleteTeacher) {
                $error = "Penghapusan data guru tidak diizinkan.";
                header("Location: admin.php?table=teacher&error=" . urlencode($error));
                exit();
            }
            $id = $_GET['id'];
            $sql = "DELETE FROM teacher WHERE id = ?";
            $db->query($sql, [$id]);
            resetAutoIncrementIfEmpty($db, 'teacher', 0);
            $success = "Guru berhasil dihapus!";
            header("Location: admin.php?table=teacher&success=" . urlencode($success));
            exit();
            break;
            
        case 'schedule':
            if (isset($canDeleteSchedule) && !$canDeleteSchedule) {
                $error = "Tidak memiliki izin menghapus jadwal.";
                header("Location: admin.php?table=schedule&error=" . urlencode($error));
                exit();
            }
            $id = $_GET['id'];
            $db->beginTransaction();
            $db->query("DELETE FROM student_schedule WHERE teacher_schedule_id = ?", [$id]);
            $db->query("DELETE FROM teacher_schedule WHERE schedule_id = ?", [$id]);
            $db->commit();
            resetAutoIncrementIfEmpty($db, 'teacher_schedule', 0);
            $success = "Jadwal berhasil dihapus!";
            header("Location: admin.php?table=schedule&success=" . urlencode($success));
            exit();
            break;

        case 'attendance':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) {
                $error = "ID absensi tidak valid.";
                header("Location: admin.php?table=attendance&error=" . urlencode($error));
                exit();
            }

            $row = $db->query(
                "SELECT p.presence_id, p.student_schedule_id, p.presence_date,
                        ss.schedule_date,
                        COALESCE(ss.time_in, sh.time_in) as schedule_time_in,
                        COALESCE(ss.time_out, sh.time_out) as schedule_time_out
                 FROM presence p
                 LEFT JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
                 LEFT JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
                 LEFT JOIN shift sh ON ts.shift_id = sh.shift_id
                 WHERE p.presence_id = ?",
                [$id]
            )->fetch();

            if (!$row) {
                $error = "Data absensi tidak ditemukan.";
                header("Location: admin.php?table=attendance&error=" . urlencode($error));
                exit();
            }

            $tz = new DateTimeZone('Asia/Jakarta');
            $now = new DateTime('now', $tz);
            $schedule_date = $row['schedule_date'] ?? '';
            if (!$schedule_date && !empty($row['presence_date'])) {
                $schedule_date = date('Y-m-d', strtotime($row['presence_date']));
            }

            if (!$schedule_date || ($schedule_date !== $now->format('Y-m-d'))) {
                $error = "Hapus hanya diizinkan untuk jadwal hari ini.";
                header("Location: admin.php?table=attendance&error=" . urlencode($error));
                exit();
            }

            $time_in = $row['schedule_time_in'] ?? '';
            $time_out = $row['schedule_time_out'] ?? '';
            if (!$time_in || !$time_out) {
                $error = "Waktu jadwal tidak valid.";
                header("Location: admin.php?table=attendance&error=" . urlencode($error));
                exit();
            }

            [$start_dt, $end_dt] = buildScheduleWindow($schedule_date, $time_in, $time_out, $tz, 0);
            if ($now < $start_dt || $now > $end_dt) {
                $error = "Hapus hanya diizinkan saat jam pelajaran masih berlangsung.";
                header("Location: admin.php?table=attendance&error=" . urlencode($error));
                exit();
            }

            $db->beginTransaction();
            $db->query("DELETE FROM presence WHERE presence_id = ?", [$id]);
            if (!empty($row['student_schedule_id'])) {
                $db->query(
                    "UPDATE student_schedule SET status = 'ACTIVE', updated_at = NOW() WHERE student_schedule_id = ?",
                    [$row['student_schedule_id']]
                );
            }
            $db->commit();

            $success = "Absensi berhasil dihapus. Siswa dapat mengulang absensi selama jadwal masih aktif.";
            header("Location: admin.php?table=attendance&success=" . urlencode($success));
            exit();
            break;

    }
}

// Get success/error from URL
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Check if theme is set in cookie, otherwise default to light
$theme = isset($_COOKIE['admin_theme']) ? strtolower(trim((string) $_COOKIE['admin_theme'])) : 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}
$admin_touch_icon = '../assets/images/logo-192.png';
$admin_icon_light = '../assets/images/favicon-admin-light.png?v=20260212b';
$admin_icon_dark = '../assets/images/favicon-admin-dark.png?v=20260212b';
$admin_section_css = [
    'attendance' => 'attendance.css',
    'class' => 'class.css',
    'location' => 'location.css',
    'schedule' => 'schedule.css',
    'student' => 'student.css',
];
$active_admin_section_css = $admin_section_css[$table] ?? null;
$active_admin_section_css_href = null;
if ($active_admin_section_css !== null) {
    $active_admin_section_css_file = __DIR__ . '/../assets/css/sections/' . $active_admin_section_css;
    $active_admin_section_css_version = is_file($active_admin_section_css_file) ? (string) filemtime($active_admin_section_css_file) : date('YmdHis');
    $active_admin_section_css_href = '../assets/css/sections/' . $active_admin_section_css . '?v=' . rawurlencode($active_admin_section_css_version);
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>dashboard admin - presenova</title>
    <meta name="color-scheme" content="light dark">
    <meta id="adminThemeColor" name="theme-color" content="<?php echo $theme === 'dark' ? '#0f172a' : '#f8fafc'; ?>">
    <link rel="apple-touch-icon" href="<?php echo $admin_touch_icon; ?>">
    <link id="adminFavicon" rel="icon" type="image/png" href="<?php echo $admin_icon_light; ?>" data-light="<?php echo $admin_icon_light; ?>" data-dark="<?php echo $admin_icon_dark; ?>">
    <link id="adminShortcutIcon" rel="shortcut icon" type="image/png" href="<?php echo $theme === 'dark' ? $admin_icon_dark : $admin_icon_light; ?>">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <!-- Import style.css -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/app-dialog.css">

    <!-- Ion Icons -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

    <!-- jQuery (needed for inline section scripts) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables JS (needed for inline section scripts) -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Prevent theme flash with inline critical CSS -->
    <script>
        // Set theme immediately before page renders
        (function() {
            const savedTheme = document.cookie.match(/(?:^|;\s*)admin_theme=([^;]+)/);
            let rawTheme = '';
            if (savedTheme) {
                try {
                    rawTheme = decodeURIComponent(savedTheme[1]).toLowerCase().trim();
                } catch (error) {
                    rawTheme = String(savedTheme[1] || '').toLowerCase().trim();
                }
            }
            const theme = rawTheme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);

            const favicon = document.getElementById('adminFavicon');
            if (favicon) {
                const light = favicon.getAttribute('data-light');
                const dark = favicon.getAttribute('data-dark');
                favicon.setAttribute('href', theme === 'dark' ? dark : light);
            }
            const shortcutIcon = document.getElementById('adminShortcutIcon');
            if (shortcutIcon) {
                const light = favicon ? favicon.getAttribute('data-light') : '<?php echo $admin_icon_light; ?>';
                const dark = favicon ? favicon.getAttribute('data-dark') : '<?php echo $admin_icon_dark; ?>';
                shortcutIcon.setAttribute('href', theme === 'dark' ? dark : light);
            }
            const themeColorMeta = document.getElementById('adminThemeColor');
            if (themeColorMeta) {
                themeColorMeta.setAttribute('content', theme === 'dark' ? '#0f172a' : '#f8fafc');
            }
        })();
    </script>
    
    
    <?php if ($active_admin_section_css_href !== null): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($active_admin_section_css_href, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    
    
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>
    
    <div class="container-fluid p-0">
        <!-- Navigation Sidebar -->
        <div class="navigation" id="navigation">
            <ul>
                <li>
                    <a href="#">
                        <span class="icon">
                            <ion-icon name="person-circle-outline"></ion-icon>
                        </span>
                        <span class="title">Admin Panel</span>
                    </a>
                </li>
                <li class="<?php echo ($table == 'dashboard' || $table == '') ? 'hovered' : ''; ?>">
                    <a href="admin.php?table=dashboard">
                        <span class="icon">
                            <ion-icon name="home-outline"></ion-icon>
                        </span>
                        <span class="title">Dashboard</span>
                    </a>
                </li>
                <li class="<?php echo $table == 'student' ? 'hovered' : ''; ?>">
                    <a href="admin.php?table=student">
                        <span class="icon">
                            <ion-icon name="people-outline"></ion-icon>
                        </span>
                        <span class="title">Manajemen Siswa</span>
                    </a>
                </li>
                <li class="<?php echo $table == 'teacher' ? 'hovered' : ''; ?>">
                    <a href="admin.php?table=teacher">
                        <span class="icon">
                            <ion-icon name="school-outline"></ion-icon>
                        </span>
                        <span class="title">Manajemen Guru</span>
                    </a>
                </li>
                <li class="<?php echo $table == 'class' ? 'hovered' : ''; ?>">
                    <a href="admin.php?table=class">
                        <span class="icon">
                            <ion-icon name="business-outline"></ion-icon>
                        </span>
                        <span class="title">Kelas & Jurusan</span>
                    </a>
                </li>
                <li class="<?php echo $table == 'schedule' ? 'hovered' : ''; ?>">
                    <a href="admin.php?table=schedule">
                        <span class="icon">
                            <ion-icon name="calendar-outline"></ion-icon>
                        </span>
                        <span class="title">Jadwal Mengajar</span>
                    </a>
                </li>
                <li class="<?php echo $table == 'attendance' ? 'hovered' : ''; ?>">
                    <a href="admin.php?table=attendance">
                        <span class="icon">
                            <ion-icon name="clipboard-outline"></ion-icon>
                        </span>
                        <span class="title">Data Absensi</span>
                    </a>
                </li>
                <li class="<?php echo $table == 'location' ? 'hovered' : ''; ?>">
                    <a href="admin.php?table=location">
                        <span class="icon">
                            <ion-icon name="location-outline"></ion-icon>
                        </span>
                        <span class="title">Pengaturan Lokasi</span>
                    </a>
                </li>
                <li class="<?php echo $table == 'system' ? 'hovered' : ''; ?>">
                    <a href="admin.php?table=system">
                        <span class="icon">
                            <ion-icon name="settings-outline"></ion-icon>
                        </span>
                        <span class="title">Pengaturan Sistem</span>
                    </a>
                </li>
            </ul>
            <div class="navigation-logout">
                <a href="../logout.php">
                    <span class="icon">
                        <ion-icon name="log-out-outline"></ion-icon>
                    </span>
                    <span class="title">Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main" id="main">
            <!-- Topbar -->
            <div class="topbar">
                <div class="toggle">
                    <ion-icon name="menu-outline"></ion-icon>
                </div>

                <div class="topbar-time">
                    <div class="topbar-date" id="currentDate">--</div>
                    <div class="topbar-clock" id="currentTime">--:--:--</div>
                </div>

                <div class="topbar-actions">
                    <!-- Theme Toggle -->
                    <button id="themeToggle" title="Toggle Theme">
                        <i class="fas fa-moon"></i>
                    </button>

                    <?php
                    $adminRoleLabel = 'Administrator';
                    if (isset($_SESSION['level']) && (int) $_SESSION['level'] === 2) {
                        $adminRoleLabel = 'Operator';
                    }
                    $adminDisplayName = $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'User');
                    ?>
                    <div class="topbar-userinfo">
                        <div class="topbar-user-name"><?php echo htmlspecialchars($adminDisplayName); ?></div>
                        <div class="topbar-user-role"><?php echo htmlspecialchars($adminRoleLabel); ?></div>
                    </div>

                    <!-- User -->
                    <div class="user">
                        <?php
                        $nameParts = explode(' ', $_SESSION['fullname']);
                        $initials = '';
                        foreach ($nameParts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                            if (strlen($initials) >= 2) break;
                        }
                        ?>
                        <div class="avatar-text" style="
                            width: 40px;
                            height: 40px;
                            border-radius: 50%;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-weight: bold;
                            font-size: 16px;
                        ">
                            <?php echo $initials; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Content based on selected table -->
            <div id="content">
                <?php
                $role_section_file = "roles/admin/sections/$table.php";

                if (file_exists($role_section_file)) {
                    include $role_section_file;
                } else {
                    // Default dashboard
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

                    // Weekly cycle (Mon-Sat 15:00 WIB)
                    $tz = new DateTimeZone('Asia/Jakarta');
                    $now_wib = new DateTime('now', $tz);
                    $week_start = (clone $now_wib)->modify('monday this week')->setTime(0, 0, 0);
                    $week_reset = (clone $week_start)->modify('+5 days')->setTime(15, 0, 0);
                    if ($now_wib >= $week_reset) {
                        $week_start->modify('+7 days');
                        $week_reset->modify('+7 days');
                    }
                    $cycle_start = $week_start->format('Y-m-d H:i:s');
                    $cycle_end = $week_reset->format('Y-m-d H:i:s');

                    // Attendance in current cycle
                    $sql = "SELECT COUNT(*) as total FROM presence WHERE presence_date BETWEEN ? AND ?";
                    $stmt = $db->query($sql, [$cycle_start, $cycle_end]);
                    $stats['attendance_today'] = $stmt->fetch()['total'];

                    // Recent Activities (current cycle)
                    $sql = "SELECT p.*, s.student_name, c.class_name 
                            FROM presence p 
                            JOIN student s ON p.student_id = s.id 
                            JOIN class c ON s.class_id = c.class_id 
                            WHERE p.presence_date BETWEEN ? AND ?
                            ORDER BY p.presence_date DESC, p.time_in DESC 
                            LIMIT 10";
                    $stmt = $db->query($sql, [$cycle_start, $cycle_end]);
                    $recent_activities = $stmt->fetchAll();
                    ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-4">
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
                        <div class="col-md-3 col-sm-6 mb-4">
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
                        <div class="col-md-3 col-sm-6 mb-4">
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
                        <div class="col-md-3 col-sm-6 mb-4">
                            <a href="?table=attendance" class="dashboard-card clickable">
                                <div class="card-icon purple">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h4 class="card-value"><?php echo $stats['attendance_today']; ?></h4>
                                <p class="card-title">Absensi Minggu Ini</p>
                                <p class="card-change positive">
                                    <i class="fas fa-arrow-up"></i> Klik untuk melihat detail
                                </p>
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8 mb-4">
                            <div class="activity-table">
                                <h5 class="mb-4"><i class="fas fa-history text-primary me-2"></i>Aktivitas Terbaru</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Siswa</th>
                                                <th>Kelas</th>
                                                <th>Waktu</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
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
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 mb-4">
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
                                    <span>Export Absensi Minggu Ini</span>
                                </a>
                                <a href="?table=system" class="quick-action-item" data-no-loading="1">
                                    <i class="fas fa-cog"></i>
                                    <span>Pengaturan Sistem</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Modal content will be loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app-dialog.js"></script>
    <script src="../assets/js/schedule-print-dialog.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
    // Set cookie function
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + encodeURIComponent(value || "") + expires + "; path=/";
    }
    
    // Get cookie function
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) {
                const value = c.substring(nameEQ.length, c.length);
                try {
                    return decodeURIComponent(value);
                } catch (error) {
                    return value;
                }
            }
        }
        return null;
    }

    function normalizeTheme(theme) {
        return theme === 'dark' ? 'dark' : 'light';
    }
    
    // Real-time clock
    function updateClock() {
        const timeEl = document.getElementById('currentTime');
        const dateEl = document.getElementById('currentDate');
        if (!timeEl && !dateEl) return;

        const now = new Date();
        // Get UTC time
        const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
        // Create UTC+7 time (WIB)
        const wib = new Date(utc + (3600000 * 7));

        const hours = String(wib.getHours()).padStart(2, '0');
        const minutes = String(wib.getMinutes()).padStart(2, '0');
        const seconds = String(wib.getSeconds()).padStart(2, '0');

        if (timeEl) {
            timeEl.textContent = `${hours}:${minutes}:${seconds} WIB`;
        }

        if (dateEl) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateStr = wib.toLocaleDateString('id-ID', options);
            dateEl.textContent = dateStr;
        }
    }
    
    // Update every second if elements exist
    if (document.getElementById('currentTime') || document.getElementById('currentDate')) {
        setInterval(updateClock, 1000);
        updateClock(); // Initial call
    }
    
    $(document).ready(function() {
        // Fail-safe: clear stale body lock state (can happen after interrupted modal navigation)
        document.documentElement.classList.remove('scroll-locked');
        document.documentElement.style.overflow = '';
        document.documentElement.style.paddingRight = '';
        document.body.classList.remove('scroll-locked', 'modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';

        document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach((backdrop) => {
            backdrop.remove();
        });

    // Initialize DataTables with consistent styling
    $(document).on('init.dt', function(event, settings) {
        const $table = $(settings.nTable);
        const $wrapper = $table.closest('.dataTables_wrapper');
        const $scrollBody = $wrapper.find('.dataTables_scrollBody');
        const $scrollHead = $wrapper.find('.dataTables_scrollHead');
        if ($scrollBody.length && $scrollHead.length) {
            $wrapper.closest('.table-responsive').addClass('dt-scroll');
            $scrollBody.off('scroll.dt-sync').on('scroll.dt-sync', function() {
                $scrollHead.scrollLeft(this.scrollLeft);
            });
        } else {
            $wrapper.closest('.table-responsive').removeClass('dt-scroll');
        }
    });

    $('.data-table').each(function() {
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                    },
                    "pageLength": 10,
                    "lengthMenu": [10, 25, 50, 100],
                    "ordering": true,
                    "searching": true,
                    "responsive": false,
                    "scrollX": false,
                    "scrollCollapse": false,
                    "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                           "<'row'<'col-sm-12'tr>>" +
                           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                    "initComplete": function() {
                        const api = this.api();
                        const $wrapper = $(api.table().container());
                        const $scrollBody = $wrapper.find('.dataTables_scrollBody');
                        const $scrollHead = $wrapper.find('.dataTables_scrollHead');
                        if ($scrollBody.length && $scrollHead.length) {
                            $wrapper.closest('.table-responsive').addClass('dt-scroll');
                            $scrollBody.off('scroll.dt-sync').on('scroll.dt-sync', function() {
                                $scrollHead.scrollLeft(this.scrollLeft);
                            });
                        } else {
                            $wrapper.closest('.table-responsive').removeClass('dt-scroll');
                        }
                        api.columns.adjust();
                        // Apply custom styling to DataTables elements
                        $('.dataTables_filter input').addClass('form-control');
                        $('.dataTables_length select').addClass('form-select');
                    }
                });
            }
        });

        $(window).off('resize.adminDtAdjust').on('resize.adminDtAdjust', function() {
            if ($.fn.dataTable && $.fn.dataTable.tables) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            }
        });
        
        // Handle edit button click
      $(document).on('click', '.edit-btn', function() {
    const id = $(this).data('id');
    const table = $(this).data('table');
    
    $('#loadingOverlay').show();
    
    // Tentukan URL berdasarkan tabel
    let url = 'ajax/get_form.php';
    if (table === 'schedule') {
        url = 'ajax/get_schedule_form.php';
    }
    
    $.ajax({
        url: url,
        method: 'POST',
        data: { 
            id: id, 
            table: table 
        },
        success: function(response) {
            $('#addModal .modal-content').html(response);
            $('#addModal').modal('show');
            $('#loadingOverlay').hide();
        },
        error: function(xhr, status, error) {
            $('#loadingOverlay').hide();
            alert('Terjadi kesalahan saat memuat data: ' + error);
            console.error('AJAX Error:', error, xhr.responseText);
        }
    });
});

        
        // Handle add button click
       $(document).on('click', '.add-btn', function() {
    const table = $(this).data('table');
    
    $('#loadingOverlay').show();
    
    // Tentukan URL berdasarkan tabel
    let url = 'ajax/get_form.php';
    if (table === 'schedule') {
        url = 'ajax/get_schedule_form.php';
    }
    
    $.ajax({
        url: url,
        method: 'POST',
        data: { table: table },
        success: function(response) {
            $('#addModal .modal-content').html(response);
            $('#addModal').modal('show');
            $('#loadingOverlay').hide();
        },
        error: function(xhr, status, error) {
            $('#loadingOverlay').hide();
            alert('Terjadi kesalahan saat memuat form: ' + error);
        }
    });
});
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
        
        // Toggle sidebar
        $('#sidebarToggle').click(function() {
            const sidebar = $('#sidebar');
            const mainContent = $('#mainContent');
            const icon = $(this).find('i');
            
            sidebar.toggleClass('collapsed');
            mainContent.toggleClass('expanded');
            
            if (sidebar.hasClass('collapsed')) {
                icon.removeClass('fa-chevron-left').addClass('fa-chevron-right');
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                icon.removeClass('fa-chevron-right').addClass('fa-chevron-left');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        });
        
        // Mobile menu toggle
        $('#mobileMenuToggle').click(function() {
            $('#sidebar').toggleClass('show');
        });
        
        function updateAdminFavicon(theme) {
            const favicon = document.getElementById('adminFavicon');
            const shortcutIcon = document.getElementById('adminShortcutIcon');
            if (!favicon) {
                return;
            }
            const light = favicon.getAttribute('data-light');
            const dark = favicon.getAttribute('data-dark');
            favicon.setAttribute('href', theme === 'dark' ? dark : light);
            if (shortcutIcon) {
                shortcutIcon.setAttribute('href', theme === 'dark' ? dark : light);
            }

            const themeColorMeta = document.getElementById('adminThemeColor');
            if (themeColorMeta) {
                themeColorMeta.setAttribute('content', theme === 'dark' ? '#0f172a' : '#f8fafc');
            }
        }

        // Theme toggle with cookie persistence and smooth transition
        $('#themeToggle').click(function() {
            const html = document.documentElement;
            const currentTheme = normalizeTheme(html.getAttribute('data-theme'));
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            const icon = $(this).find('i');
            
            // Enable lighter transitions for theme change
            document.body.style.transition = 'background-color 0.15s ease, color 0.15s ease';
            
            // Set theme attribute
            html.setAttribute('data-theme', newTheme);
            updateAdminFavicon(newTheme);
            
            // Update cookie (expires in 365 days)
            setCookie('admin_theme', newTheme, 365);
            
            // Update icon
            if (newTheme === 'dark') {
                icon.removeClass('fa-moon').addClass('fa-sun');
                $(this).attr('title', 'Switch to Light Mode');
            } else {
                icon.removeClass('fa-sun').addClass('fa-moon');
                $(this).attr('title', 'Switch to Dark Mode');
            }
            
            // Reapply dark mode fixes and table styles after theme change
            setTimeout(function() {
                applyDarkModeFixes();
                updateTableStyles();
            }, 100);
        });
        
        // Restore sidebar state from localStorage
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
        if (sidebarCollapsed === 'true') {
            $('#sidebar').addClass('collapsed');
            $('#mainContent').addClass('expanded');
            $('#sidebarToggle i').removeClass('fa-chevron-left').addClass('fa-chevron-right');
        }
        
        // Update active menu item based on current page
        function updateActiveMenuItem() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentTable = urlParams.get('table') || 'dashboard';
            
            $('.sidebar-menu .nav-link').removeClass('active');
            $(`.sidebar-menu .nav-link[href*="table=${currentTable}"]`).addClass('active');
        }
        
        updateActiveMenuItem();
        
        // Close mobile sidebar when clicking outside
        $(document).click(function(event) {
            if ($(window).width() <= 992) {
                const sidebar = $('#sidebar');
                const toggle = $('#mobileMenuToggle');
                if (!sidebar.is(event.target) && sidebar.has(event.target).length === 0 && 
                    !toggle.is(event.target) && toggle.has(event.target).length === 0) {
                    sidebar.removeClass('show');
                }
            }
        });
        
        // Loading overlay for page transitions
        const downloadLikeHrefPattern = /([?&](export|download)=)|download_|download\//i;
        $(document).on('click', 'a:not([href^="#"]):not([href*="javascript"]):not([target="_blank"])', function(e) {
            const $link = $(this);
            const href = String($link.attr('href') || '');
            const onclickAttr = String($link.attr('onclick') || '');
            const hasInlineConfirm =
                onclickAttr.includes('confirm') ||
                onclickAttr.includes('AppDialog.inlineConfirm') ||
                onclickAttr.includes('inlineConfirm(');
            const isQuickAction = $link.hasClass('quick-action-item');
            const hasDownloadIntent = $link.is('[download]') || downloadLikeHrefPattern.test(href);

            if (e.isDefaultPrevented()) {
                $('#loadingOverlay').hide();
                return;
            }

            if ($link.data('no-loading') || hasInlineConfirm || isQuickAction || hasDownloadIntent) {
                $('#loadingOverlay').hide();
                return;
            }
            if (href && !href.startsWith('javascript:') && !href.startsWith('#')) {
                $('#loadingOverlay').show();
            }
        });

        // Ensure links that skip loading do not leave overlay visible
        $(document).on('click', 'a[data-no-loading="1"], a.quick-action-item', function() {
            $('#loadingOverlay').hide();
        });
        
        // Hide loading overlay when page is fully loaded
        $(window).on('load', function() {
            $('#loadingOverlay').hide();
        });
        
        // Theme is already set in head, just verify
        const savedTheme = normalizeTheme(getCookie('admin_theme') || '<?php echo $theme; ?>');
        // Only update if different (shouldn't happen)
        if (document.documentElement.getAttribute('data-theme') !== savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
        updateAdminFavicon(savedTheme);
        
        // Remove transition delay after initial load
        setTimeout(function() {
            document.body.style.transition = '';
            document.querySelectorAll('*').forEach(function(el) {
                if (el.style.transition === 'none') {
                    el.style.transition = '';
                }
            });
        }, 50);
        
        // Apply dark mode fixes to existing elements
        function applyDarkModeFixes() {
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            
            // Fix for Bootstrap dropdowns
            if (isDarkMode) {
                $('.dropdown-menu').addClass('dark-dropdown');
            } else {
                $('.dropdown-menu').removeClass('dark-dropdown');
            }
            
            // Fix for pagination
            if (isDarkMode) {
                $('.pagination').addClass('dark-pagination');
            } else {
                $('.pagination').removeClass('dark-pagination');
            }
        }
        
        // Apply fixes on page load
        applyDarkModeFixes();
        
        // Fungsi untuk memperbarui tampilan tabel setelah perubahan tema
        function updateTableStyles() {
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            
            // Update semua tabel di dalam content
            $('#content table').each(function() {
                if (isDarkMode) {
                    $(this).addClass('dark-table');
                    $(this).css({
                        'background-color': 'var(--dark-card)',
                        'color': 'var(--dark-text)'
                    });
                } else {
                    $(this).removeClass('dark-table');
                    $(this).css({
                        'background-color': '',
                        'color': ''
                    });
                }
            });
            
            // Update DataTables jika ada
            if ($.fn.DataTable.isDataTable('.data-table')) {
                $('.data-table').DataTable().draw();
            }
        }
        
        // Panggil saat halaman dimuat
        setTimeout(updateTableStyles, 500);
    });

    // Weekly calendar functions
function initWeeklyCalendar() {
    // Get current week
    const now = new Date();
    const currentWeek = getWeekDates(now);
    
    // Update calendar display
    updateCalendarDisplay(currentWeek);
}

function getWeekDates(date) {
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1); // Adjust for Sunday
    const monday = new Date(date.setDate(diff));
    
    const week = [];
    for (let i = 0; i < 7; i++) {
        const day = new Date(monday);
        day.setDate(monday.getDate() + i);
        week.push(day);
    }
    return week;
}

function updateCalendarDisplay(weekDates) {
    const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
    const calendarContainer = document.getElementById('weeklyCalendar');
    if (!calendarContainer) return;
    
    let html = '<div class="row text-center mb-3">';
    
    weekDates.forEach((date, index) => {
        const dayName = days[index];
        const dayNum = date.getDate();
        const month = months[date.getMonth()];
        const isToday = isSameDay(date, new Date());
        
        html += `
            <div class="col">
                <div class="calendar-day ${isToday ? 'calendar-today' : ''}">
                    <div class="calendar-day-name">${dayName}</div>
                    <div class="calendar-date">${dayNum}</div>
                    <div class="calendar-month">${month}</div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    calendarContainer.innerHTML = html;
}

function isSameDay(date1, date2) {
    return date1.getDate() === date2.getDate() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getFullYear() === date2.getFullYear();
}

// Panggil fungsi kalender saat halaman dimuat
$(document).ready(function() {
    initWeeklyCalendar();
});
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
