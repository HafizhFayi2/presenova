<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo '<div class="alert alert-danger">Unauthorized</div>';
    exit;
}

$attendance_id = $_POST['id'] ?? null;
if (!$attendance_id) {
    echo '<div class="alert alert-warning">ID absensi tidak valid</div>';
    exit;
}

$db = new Database();
$sql = "SELECT p.*, s.student_name, s.student_nisn, c.class_name,
               ps.present_name, ts.subject, t.teacher_name,
               p.latitude_in, p.longitude_in, p.picture_in,
               p.late_time, p.information, p.distance_in
        FROM presence p
        JOIN student s ON p.student_id = s.id
        JOIN class c ON s.class_id = c.class_id
        JOIN present_status ps ON p.present_id = ps.present_id
        LEFT JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
        LEFT JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
        LEFT JOIN teacher t ON ts.teacher_id = t.id
        WHERE p.presence_id = ?";

$stmt = $db->query($sql, [$attendance_id]);
$attendance = $stmt ? $stmt->fetch() : null;

if (!$attendance) {
    echo '<div class="alert alert-warning">Data tidak ditemukan</div>';
    exit;
}
?>
<div class="row">
    <div class="col-md-6">
        <h6>Detail Siswa</h6>
        <table class="table table-sm">
            <tr>
                <th>NISN:</th>
                <td><?php echo htmlspecialchars($attendance['student_nisn'] ?? '-'); ?></td>
            </tr>
            <tr>
                <th>Nama:</th>
                <td><?php echo htmlspecialchars($attendance['student_name'] ?? '-'); ?></td>
            </tr>
            <tr>
                <th>Kelas:</th>
                <td><?php echo htmlspecialchars($attendance['class_name'] ?? '-'); ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6>Detail Absensi</h6>
        <table class="table table-sm">
            <tr>
                <th>Tanggal:</th>
                <td><?php echo $attendance['presence_date'] ? date('d/m/Y', strtotime($attendance['presence_date'])) : '-'; ?></td>
            </tr>
            <tr>
                <th>Jam:</th>
                <td><?php echo $attendance['time_in'] ? date('H:i:s', strtotime($attendance['time_in'])) : '-'; ?></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <?php if ((int)$attendance['present_id'] === 1): ?>
                        <span class="badge <?php echo $attendance['is_late'] === 'Y' ? 'bg-warning' : 'bg-success'; ?>">
                            <?php echo $attendance['is_late'] === 'Y' ? 'Terlambat' : ($attendance['present_name'] ?? 'Hadir'); ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-info"><?php echo htmlspecialchars($attendance['present_name'] ?? '-'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($attendance['is_late'] === 'Y'): ?>
            <tr>
                <th>Keterlambatan:</th>
                <td class="text-warning"><?php echo (int) ($attendance['late_time'] ?? 0); ?> menit</td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($attendance['subject'])): ?>
            <tr>
                <th>Mata Pelajaran:</th>
                <td><?php echo htmlspecialchars($attendance['subject']); ?></td>
            </tr>
            <tr>
                <th>Guru:</th>
                <td><?php echo htmlspecialchars($attendance['teacher_name'] ?? '-'); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if (!empty($attendance['latitude_in']) && !empty($attendance['longitude_in'])): ?>
<div class="mt-3">
    <h6>Lokasi Absensi</h6>
    <p class="mb-1">Koordinat: <?php echo $attendance['latitude_in']; ?>, <?php echo $attendance['longitude_in']; ?></p>
    <?php if (!empty($attendance['distance_in'])): ?>
        <p class="mb-0">Jarak dari sekolah: <?php echo (int) $attendance['distance_in']; ?> meter</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($attendance['information'])): ?>
<div class="mt-3">
    <h6>Keterangan</h6>
    <p><?php echo htmlspecialchars($attendance['information']); ?></p>
</div>
<?php endif; ?>

<?php
$photoPath = '';
if (!empty($attendance['picture_in'])) {
    $rawPhoto = $attendance['picture_in'];
    if ($rawPhoto && strpos($rawPhoto, 'uploads/attendance') === false && !preg_match('~^https?://~', $rawPhoto)) {
        $cleanPhoto = ltrim($rawPhoto, '/');
        if (strpos($cleanPhoto, '/') === false && !empty($attendance['presence_date'])) {
            $dateDir = date('Y-m-d', strtotime($attendance['presence_date']));
            $photoPath = '../uploads/attendance/' . $dateDir . '/' . $cleanPhoto;
        } else {
            $photoPath = '../uploads/attendance/' . $cleanPhoto;
        }
    } else {
        $photoPath = $rawPhoto;
    }
}
?>
<?php if (!empty($photoPath)): ?>
<div class="mt-3">
    <h6>Foto Absensi</h6>
    <img src="<?php echo htmlspecialchars($photoPath); ?>"
         class="img-fluid rounded" style="max-width: 320px;">
</div>
<?php endif; ?>
