<?php
// sections/profil.php
$student_id = $_SESSION['student_id'];

// Query data lengkap siswa
$sql_student = "
    SELECT s.*, c.class_name, j.name as jurusan_name, sl.location_name
    FROM student s
    LEFT JOIN class c ON s.class_id = c.class_id
    LEFT JOIN jurusan j ON s.jurusan_id = j.jurusan_id
    LEFT JOIN school_location sl ON s.location_id = sl.location_id
    WHERE s.id = ?
";

$stmt = $db->query($sql_student, [$student_id]);
$student_data = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
$profileImageUrl = null;
if (!empty($student_data['photo'])) {
    $photoPath = __DIR__ . '/../uploads/faces/' . $student_data['photo'];
    if (file_exists($photoPath)) {
        $profileImageUrl = '../uploads/faces/' . $student_data['photo'];
    }
}
if (!$profileImageUrl && !empty($student_data['photo_reference'])) {
    $photoPath = __DIR__ . '/../uploads/faces/' . $student_data['photo_reference'];
    if (file_exists($photoPath)) {
        $profileImageUrl = '../uploads/faces/' . $student_data['photo_reference'];
    }
}
if (!$profileImageUrl && !empty($student_data['student_nisn']) && class_exists('FaceMatcher')) {
    $faceMatcher = new FaceMatcher();
    $referencePath = $faceMatcher->getReferencePath($student_data['student_nisn']);
    if ($referencePath) {
        $profileImageUrl = $referencePath;
    }
}
if (!$profileImageUrl) {
    $profileImageUrl = '../assets/images/presenova.png';
}
?>

<div class="card profil-card">
    <div class="card-header bg-info text-white">
        <h4><i class="fas fa-user-circle"></i> Profil Siswa</h4>
    </div>
    <div class="card-body">
        <?php if ($student_data): ?>
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="profile-container">
                        <div class="profile-avatar-large mb-3">
                            <img src="<?= $profileImageUrl ?>" class="img-fluid rounded profile-photo" alt="Foto Profil">
                        </div>
                        
                        <div class="mt-3">
                            <h5 class="mb-1"><?= htmlspecialchars($student_data['student_name']) ?></h5>
                            <p class="text-muted mb-0"><?= htmlspecialchars($student_data['student_nisn']) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <h5 class="mb-4 section-title"><i class="fas fa-info-circle"></i> Informasi Pribadi</h5>
                    <table class="table table-bordered info-table">
                        <tbody>
                            <tr>
                                <th width="30%"><i class="fas fa-id-card"></i> NISN</th>
                                <td><?= htmlspecialchars($student_data['student_nisn']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-barcode"></i> Kode Siswa</th>
                                <td><?= htmlspecialchars($student_data['student_code']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-user"></i> Nama Lengkap</th>
                                <td><?= htmlspecialchars($student_data['student_name']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-graduation-cap"></i> Kelas</th>
                                <td>
                                    <span class="badge bg-primary"><?= htmlspecialchars($student_data['class_name']) ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-book"></i> Jurusan</th>
                                <td>
                                    <span class="badge bg-success"><?= htmlspecialchars($student_data['jurusan_name']) ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-map-marker-alt"></i> Lokasi Sekolah</th>
                                <td><?= htmlspecialchars($student_data['location_name']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-sign-in-alt"></i> Status Login</th>
                                <td>
                                    <?php if ($student_data['created_login']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> Aktif
                                        </span>
                                        <small class="text-muted ms-2">
                                            Terakhir: <?= date('d/m/Y H:i', strtotime($student_data['created_login'])) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-exclamation-circle"></i> Belum Login
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="alert alert-info info-note mt-4">
                        <i class="fas fa-info-circle"></i>
                        <strong>Informasi:</strong> 
                        Untuk mengubah data pribadi, silakan hubungi administrator sekolah.
                    </div>
                    
                    <!-- Additional Stats -->
                    <div class="mt-4">
                        <h6 class="mb-3"><i class="fas fa-chart-bar"></i> Statistik Kehadiran Bulan Ini</h6>
                        <?php
                        // Query statistik
                        $sql_stats = "
                            SELECT 
                                COUNT(CASE WHEN present_id = 1 AND is_late = 'N' THEN 1 END) as hadir,
                                COUNT(CASE WHEN present_id = 1 AND is_late = 'Y' THEN 1 END) as terlambat,
                                COUNT(CASE WHEN present_id = 2 THEN 1 END) as sakit,
                                COUNT(CASE WHEN present_id = 3 THEN 1 END) as izin
                            FROM presence
                            WHERE student_id = ?
                            AND MONTH(presence_date) = MONTH(CURDATE())
                            AND YEAR(presence_date) = YEAR(CURDATE())
                        ";
                        $stmt_stats = $db->query($sql_stats, [$student_id]);
                        $stats = $stmt_stats ? $stmt_stats->fetch(PDO::FETCH_ASSOC) : null;
                        ?>
                        
                        <?php if ($stats): ?>
                            <div class="row">
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="stat-box bg-success text-white">
                                        <div class="stat-value"><?= $stats['hadir'] ?></div>
                                        <div class="stat-label">Hadir</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="stat-box bg-warning">
                                        <div class="stat-value"><?= $stats['terlambat'] ?></div>
                                        <div class="stat-label">Terlambat</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="stat-box bg-info text-white">
                                        <div class="stat-value"><?= $stats['sakit'] ?></div>
                                        <div class="stat-label">Sakit</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2">
                                    <div class="stat-box bg-secondary text-white">
                                        <div class="stat-value"><?= $stats['izin'] ?></div>
                                        <div class="stat-label">Izin</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> 
                Data siswa tidak ditemukan.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Dark mode support */
[data-bs-theme="dark"] .profil-card {
    background-color: #2b3035;
    border-color: #3e4349;
}

[data-bs-theme="dark"] .info-table {
    color: #dee2e6;
}

[data-bs-theme="dark"] .info-table th,
[data-bs-theme="dark"] .info-table td {
    border-color: rgba(148, 163, 184, 0.25);
}

[data-bs-theme="dark"] .info-table tbody tr:nth-of-type(odd) {
    background-color: rgba(255, 255, 255, 0.04);
}

[data-bs-theme="dark"] .info-table tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.08);
}

[data-bs-theme="dark"] .info-note {
    background-color: rgba(13, 110, 253, 0.15);
    border-color: #0d6efd;
    color: #dee2e6;
}

[data-bs-theme="dark"] .section-title {
    color: #dee2e6;
    border-bottom-color: #3e4349;
}

[data-bs-theme="dark"] .profile-avatar-large {
    background: rgba(18, 24, 38, 0.7);
}

[data-bs-theme="dark"] .text-muted {
    color: #adb5bd !important;
}

[data-bs-theme="dark"] .stat-box {
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

/* General styles */
.profil-card {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 16px;
    box-shadow: 0 12px 24px rgba(31, 41, 55, 0.16);
}

[data-bs-theme="dark"] .profil-card {
    background: rgba(18, 24, 38, 0.62);
    border-color: rgba(148, 163, 184, 0.22);
    box-shadow: 0 12px 24px rgba(0,0,0,0.35);
}

.profile-container {
    padding: 20px;
}

.profile-avatar-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.45);
    box-shadow: 0 10px 20px rgba(31, 41, 55, 0.15);
    margin-bottom: 15px;
    overflow: hidden;
}

.profile-photo {
    max-height: 230px;
    border: 2px solid rgba(255, 255, 255, 0.55);
    box-shadow: 0 10px 18px rgba(31, 41, 55, 0.18);
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

[data-bs-theme="dark"] .profile-photo {
    border-color: rgba(148, 163, 184, 0.25);
}

.section-title {
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.35);
    margin-bottom: 20px;
}

.info-table {
    font-size: 0.95rem;
}

.info-table th {
    background-color: rgba(255, 255, 255, 0.6);
    font-weight: 600;
    border-right: 1px solid rgba(255, 255, 255, 0.45);
}

.info-table td {
    background-color: rgba(255, 255, 255, 0.4);
}

[data-bs-theme="dark"] .info-table th {
    background-color: rgba(18, 24, 38, 0.7);
}

.info-table th i {
    margin-right: 8px;
    width: 20px;
    text-align: center;
}

.info-table tbody tr {
    transition: background-color 0.2s;
}

.info-table tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.08);
}

.info-note {
    border-left: 4px solid #0dcaf0;
}

.stat-box {
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>
