<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';

$db = new Database();
$attendance_id = $_POST['id'] ?? null;

if (!$attendance_id) {
    echo '<div class="attendance-detail-empty">ID absensi tidak valid.</div>';
    exit;
}

$sql = "SELECT p.*, s.student_name, s.student_nisn, c.class_name,
               ps.present_name, ts.subject, t.teacher_name,
               p.picture_in
        FROM presence p
        JOIN student s ON p.student_id = s.id
        JOIN class c ON s.class_id = c.class_id
        JOIN present_status ps ON p.present_id = ps.present_id
        LEFT JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
        LEFT JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
        LEFT JOIN teacher t ON ts.teacher_id = t.id
        WHERE p.presence_id = ?";

$stmt = $db->query($sql, [$attendance_id]);
$attendance = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$attendance) {
    echo '<div class="attendance-detail-empty">Data absensi tidak ditemukan.</div>';
    exit;
}

$photoPath = '';
if (!empty($attendance['picture_in'])) {
    $rawPhoto = $attendance['picture_in'];
    if (preg_match('~^https?://~', $rawPhoto)) {
        $photoPath = $rawPhoto;
    } else {
        $cleanPhoto = ltrim((string)$rawPhoto, '/');
        if (strpos($cleanPhoto, 'uploads/') === 0) {
            $photoPath = '../' . $cleanPhoto;
        } elseif (strpos($cleanPhoto, '../') === 0) {
            $photoPath = $cleanPhoto;
        } elseif (strpos($cleanPhoto, '/') === false && !empty($attendance['presence_date'])) {
            $dateDir = date('Y-m-d', strtotime($attendance['presence_date']));
            $photoPath = '../uploads/attendance/' . $dateDir . '/' . $cleanPhoto;
        } elseif (strpos($cleanPhoto, 'attendance/') === 0) {
            $photoPath = '../uploads/' . $cleanPhoto;
        } else {
            $photoPath = '../uploads/attendance/' . $cleanPhoto;
        }
    }
}

$presentId = (int)($attendance['present_id'] ?? 0);
$statusClass = 'status-alpa';
if ($presentId === 1) {
    $statusClass = 'status-hadir';
} elseif ($presentId === 2) {
    $statusClass = 'status-sakit';
} elseif ($presentId === 3) {
    $statusClass = 'status-izin';
}

$subject = trim((string)($attendance['subject'] ?? '-')) ?: '-';
$teacher = trim((string)($attendance['teacher_name'] ?? '-')) ?: '-';
$student = trim((string)($attendance['student_name'] ?? '-')) ?: '-';
$className = trim((string)($attendance['class_name'] ?? '-')) ?: '-';
$dateLabel = !empty($attendance['presence_date']) ? date('d/m/Y', strtotime($attendance['presence_date'])) : '-';
$timeLabel = !empty($attendance['time_in']) ? date('H:i:s', strtotime($attendance['time_in'])) : '-';
$statusLabel = trim((string)($attendance['present_name'] ?? '-')) ?: '-';
$isLate = strtoupper((string)($attendance['is_late'] ?? 'N')) === 'Y';
$lateLabel = $isLate ? ((int)($attendance['late_time'] ?? 0) . ' menit') : 'Tepat waktu';
?>
<div class="attendance-detail-card">
    <div class="attendance-detail-card__image-wrap">
        <?php if (!empty($photoPath)): ?>
            <img src="<?php echo htmlspecialchars($photoPath, ENT_QUOTES); ?>" class="attendance-detail-card__image" alt="Foto absensi">
        <?php else: ?>
            <div class="attendance-detail-empty">Foto absensi belum tersedia.</div>
        <?php endif; ?>
    </div>
    <div class="attendance-detail-card__footer">
        <span class="attendance-detail-badge"><?php echo htmlspecialchars($dateLabel); ?></span>
        <span class="attendance-detail-badge"><?php echo htmlspecialchars($timeLabel); ?> WIB</span>
        <span class="attendance-detail-badge"><?php echo htmlspecialchars($subject); ?></span>
        <span class="attendance-detail-badge"><?php echo htmlspecialchars($teacher); ?></span>
        <span class="attendance-detail-badge"><?php echo htmlspecialchars($className); ?></span>
        <span class="attendance-detail-badge"><?php echo htmlspecialchars($student); ?></span>
        <span class="attendance-detail-badge"><?php echo htmlspecialchars($lateLabel); ?></span>
        <span class="attendance-detail-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
    </div>
</div>
