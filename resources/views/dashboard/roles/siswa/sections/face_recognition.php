<?php

$faceMatcher = new FaceMatcher();
$nisn = $_SESSION['student_nisn'] ?? '';
$photoReference = '';
if (isset($student) && is_array($student) && !empty($student['photo_reference'])) {
    $photoReference = (string) $student['photo_reference'];
} elseif (!empty($_SESSION['photo_reference'])) {
    $photoReference = (string) $_SESSION['photo_reference'];
}
$studentKey = $nisn ?: ($_SESSION['student_id'] ?? '');
$referencePath = ($nisn || $photoReference) ? $faceMatcher->getReferencePath($nisn, $photoReference) : null;
$referenceUrl = $referencePath ? $faceMatcher->toPublicUrl($referencePath, '..') : '';
$referenceVersion = $referencePath && is_file($referencePath) ? (@filemtime($referencePath) ?: time()) : null;
if ($referenceUrl !== '' && $referenceVersion !== null) {
    $referenceUrl .= (str_contains($referenceUrl, '?') ? '&' : '?') . 'v=' . $referenceVersion;
}
$referenceFile = $referencePath ? basename($referencePath) : '';

$scheduleInfo = null;
$scheduleIdParam = $_GET['schedule_id'] ?? '';
if (!empty($scheduleIdParam) && isset($db) && isset($_SESSION['student_id'])) {
    $scheduleStmt = $db->query(
        "SELECT ss.schedule_date, ss.time_in AS schedule_time_in, ss.time_out AS schedule_time_out,
                sh.shift_name, ts.subject, d.day_name,
                t.teacher_name, s.student_name
         FROM student_schedule ss
         JOIN teacher_schedule ts ON ss.teacher_schedule_id = ts.schedule_id
         JOIN shift sh ON ts.shift_id = sh.shift_id
         JOIN student s ON ss.student_id = s.id
         LEFT JOIN teacher t ON ts.teacher_id = t.id
         LEFT JOIN day d ON ts.day_id = d.day_id
         WHERE ss.student_schedule_id = ? AND ss.student_id = ?
         LIMIT 1",
        [$scheduleIdParam, $_SESSION['student_id']]
    );
    $scheduleInfo = $scheduleStmt ? $scheduleStmt->fetch() : null;
}

// Location validation (admin-configured location)
$locationData = null;
$gpsEnabled = true;
if (isset($db)) {
    $siteStmt = $db->query("SELECT enable_gps_validation, default_location_id FROM site LIMIT 1");
    $siteSetting = $siteStmt ? $siteStmt->fetch() : null;

    $locationId = null;
    if (!empty($siteSetting['default_location_id'])) {
        $locationId = $siteSetting['default_location_id'];
    } elseif (isset($student) && !empty($student['location_id'])) {
        $locationId = $student['location_id'];
    }

    if ($locationId) {
        $locStmt = $db->query("SELECT * FROM school_location WHERE location_id = ? LIMIT 1", [$locationId]);
        $locationData = $locStmt ? $locStmt->fetch() : null;
    }

    if (!$locationData) {
        $locStmt = $db->query("SELECT * FROM school_location WHERE is_active = 'Y' ORDER BY location_id DESC LIMIT 1");
        $locationData = $locStmt ? $locStmt->fetch() : null;
    }
}

// GPS validation is required before camera activation
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="face-recognition-page"
     data-reference-url="<?php echo htmlspecialchars($referenceUrl, ENT_QUOTES); ?>"
     data-threshold="<?php echo defined('FACE_MATCH_THRESHOLD') ? FACE_MATCH_THRESHOLD : 89; ?>"
     data-descriptor-threshold="<?php echo defined('FACE_DESCRIPTOR_DISTANCE_THRESHOLD') ? FACE_DESCRIPTOR_DISTANCE_THRESHOLD : 0.55; ?>"
     data-face-label="<?php echo htmlspecialchars($student['student_name'] ?? $nisn, ENT_QUOTES); ?>"
     data-student-key="<?php echo htmlspecialchars($studentKey, ENT_QUOTES); ?>"
     data-schedule-id="<?php echo htmlspecialchars($_GET['schedule_id'] ?? '', ENT_QUOTES); ?>"
     data-gps-enabled="<?php echo $gpsEnabled ? '1' : '0'; ?>"
     data-school-lat="<?php echo $locationData ? htmlspecialchars($locationData['latitude'], ENT_QUOTES) : ''; ?>"
     data-school-lng="<?php echo $locationData ? htmlspecialchars($locationData['longitude'], ENT_QUOTES) : ''; ?>"
     data-school-radius="<?php echo $locationData ? htmlspecialchars($locationData['radius'], ENT_QUOTES) : ''; ?>"
     data-school-name="<?php echo $locationData ? htmlspecialchars($locationData['location_name'], ENT_QUOTES) : ''; ?>"
     data-school-address="<?php echo $locationData ? htmlspecialchars($locationData['address'], ENT_QUOTES) : ''; ?>"
     data-schedule-date="<?php echo $scheduleInfo ? htmlspecialchars($scheduleInfo['schedule_date'] ?? '', ENT_QUOTES) : ''; ?>"
     data-schedule-day="<?php echo $scheduleInfo ? htmlspecialchars($scheduleInfo['day_name'] ?? '', ENT_QUOTES) : ''; ?>"
     data-schedule-subject="<?php echo $scheduleInfo ? htmlspecialchars($scheduleInfo['subject'] ?? '', ENT_QUOTES) : ''; ?>"
     data-schedule-jp="<?php echo $scheduleInfo ? htmlspecialchars($scheduleInfo['shift_name'] ?? '', ENT_QUOTES) : ''; ?>"
     data-schedule-teacher="<?php echo $scheduleInfo ? htmlspecialchars($scheduleInfo['teacher_name'] ?? '', ENT_QUOTES) : ''; ?>"
     data-schedule-student="<?php echo $scheduleInfo ? htmlspecialchars($scheduleInfo['student_name'] ?? '', ENT_QUOTES) : ''; ?>">
    <div class="face-hero">
        <div>
            <div class="face-pill">
                <i class="fas fa-user-check"></i>
                <span>Face Matching</span>
            </div>
            <h4>Verifikasi Wajah Siswa</h4>
            <p>Gunakan kamera depan untuk mencocokkan wajah Anda dengan foto referensi di sistem.</p>
        </div>
        <div class="face-hero-action">
            <div class="face-hero-card">
                <span>Target Similarity</span>
                <strong><?php echo defined('FACE_MATCH_THRESHOLD') ? FACE_MATCH_THRESHOLD : 89; ?>%+</strong>
                <small>Minimal untuk lolos verifikasi</small>
            </div>
        </div>
    </div>

    <div class="location-lock-layer" id="locationLockLayer" aria-live="polite">
        <div class="location-loading" id="locationLoading" aria-hidden="true">
            <div class="loading-card">
                <div class="loading-wrap" id="loadingWrap" aria-hidden="true"></div>
                <span class="loading-text">Sedang Memuat Halaman Lokasi...</span>
            </div>
        </div>
        <div class="location-lock-card">
            <div class="lock-visual" aria-hidden="true">
                <svg viewBox="0 0 96 96" role="img" aria-hidden="true">
                    <defs>
                        <linearGradient id="lockGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#6ee7ff"/>
                            <stop offset="100%" stop-color="#5b7cfa"/>
                        </linearGradient>
                    </defs>
                    <rect x="18" y="40" width="60" height="44" rx="14" fill="url(#lockGradient)"/>
                    <path d="M30 40V30c0-10 8-18 18-18s18 8 18 18v10" fill="none" stroke="#e2e8f0" stroke-width="8" stroke-linecap="round"/>
                    <circle cx="48" cy="62" r="8" fill="#e2e8f0"/>
                    <rect x="45" y="62" width="6" height="14" rx="3" fill="#e2e8f0"/>
                </svg>
            </div>
            <h5 id="locationLockTitle">Absensi Terkunci</h5>
            <p id="locationLockMessage">Meminta izin lokasi untuk melanjutkan...</p>
            <div class="lock-metrics">
                <div>
                    <span>Batas jarak</span>
                    <strong id="locationRadiusText">-</strong>
                </div>
                <div>
                    <span>Jarak Anda</span>
                    <strong id="locationDistanceText">-</strong>
                </div>
            </div>
            <div class="lock-address" id="locationAddressText"></div>
            <div class="lock-excuse">
                <div class="lock-excuse-title">Berhalangan hadir?</div>
                <div class="lock-excuse-actions">
                    <button class="btn lock-btn btn-sm lock-btn-warning" id="excuseSickBtn" type="button">
                        <i class="fas fa-notes-medical"></i> Sakit
                    </button>
                    <button class="btn lock-btn btn-sm lock-btn-info" id="excuseIzinBtn" type="button">
                        <i class="fas fa-user-check"></i> Izin
                    </button>
                </div>
                <small>Anda tetap perlu verifikasi wajah untuk mengajukan izin/sakit.</small>
            </div>
            <div class="lock-actions">
                <button class="btn lock-btn btn-sm" id="locationRetryBtn" type="button">
                    <i class="fas fa-location-arrow"></i> Coba Lagi
                </button>
                <a class="btn lock-btn btn-sm" href="?page=dashboard">
                    <i class="fas fa-home"></i> Kembali ke Dashboard
                </a>
                <a class="btn lock-btn lock-btn-danger btn-sm" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Keluar
                </a>
            </div>
        </div>
    </div>

    <div class="face-layout">
        <div class="face-card camera-card">
            <div class="camera-header">
                <div>
                    <h5><i class="fas fa-camera"></i> Kamera Verifikasi</h5>
                    <p>Pastikan pencahayaan cukup dan wajah terlihat jelas.</p>
                </div>
                <div class="camera-header-actions">
                    <div class="camera-device">
                        <label for="cameraSelect">Pilih Kamera</label>
                        <div class="camera-device-row">
                            <select id="cameraSelect" class="form-select form-select-sm" disabled></select>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="refreshCameraBtn" title="Refresh kamera">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="camera-status" id="cameraStatus">Siap memulai</div>
                </div>
            </div>

            <div class="camera-frame">
                <video id="faceVideo" autoplay playsinline></video>
                <canvas id="faceCanvas"></canvas>
                <div class="camera-overlay">
                    <div class="face-guide"></div>
                    <div class="pulse-ring"></div>
                </div>
                <div class="camera-preview" id="cameraPreview">
                    <img id="facePreview" alt="Preview Wajah">
                </div>
            </div>

            <div class="camera-actions">
                <button class="btn btn-primary" id="faceStartBtn" <?php echo empty($referenceUrl) ? 'disabled' : ''; ?>>
                    <i class="fas fa-play"></i> Aktifkan Kamera
                </button>
                <button class="btn btn-success" id="faceCaptureBtn" disabled>
                    <i class="fas fa-camera"></i> Ambil & Cocokkan
                </button>
                <button class="btn btn-outline-secondary" id="faceRetryBtn" disabled>
                    <i class="fas fa-redo"></i> Ulangi
                </button>
            </div>
            <div class="camera-hint" id="faceHint">
                <i class="fas fa-info-circle"></i>
                <span>Pastikan wajah berada di tengah area panduan.</span>
            </div>
        </div>

        <div class="face-side">
            <div class="face-card status-card">
                <div class="status-header">
                    <h5><i class="fas fa-shield-halved"></i> Status Verifikasi</h5>
                    <span id="matchBadge" class="match-badge waiting">Menunggu</span>
                </div>
                <div class="status-meter">
                    <div class="meter-top">
                        <span>Similarity</span>
                        <strong id="similarityValue">0%</strong>
                    </div>
                    <div class="meter-track">
                        <span id="similarityBar"></span>
                    </div>
                </div>
            <div class="status-message" id="statusMessage">
                Mulai kamera untuk memuat model dan mempersiapkan verifikasi.
            </div>
            <div class="mode-info">
                <span class="mode-pill mode-hadir" id="attendanceModePill">Mode: Hadir</span>
                <button class="btn btn-sm btn-outline-light d-none" id="resetModeBtn" type="button">
                    Gunakan Mode Hadir
                </button>
            </div>
            <button class="btn btn-success w-100" id="faceProceedBtn" disabled>
                <i class="fas fa-arrow-right"></i> Lanjut ke Absensi
            </button>
            </div>

            <div class="face-card memory-card">
                <div class="status-header">
                    <h5><i class="fas fa-memory"></i> Monitoring RAM</h5>
                    <span id="memorySupportBadge" class="match-badge ready">Aktif</span>
                </div>
                <div class="memory-stats">
                    <div>
                        <span>RAM Terpakai (JS Heap)</span>
                        <strong id="memoryUsedText">0 MB</strong>
                    </div>
                    <div>
                        <span>Limit Heap</span>
                        <strong id="memoryLimitText">-</strong>
                    </div>
                </div>
                <div class="memory-bar">
                    <span id="memoryBarFill"></span>
                </div>
                <div class="status-message" id="memoryStatusMessage">
                    Memantau penggunaan RAM saat face recognition berjalan.
                </div>
            </div>

            <div class="face-card reference-card">
                <div class="status-header">
                    <h5><i class="fas fa-id-card"></i> Foto Referensi</h5>
                </div>
                <?php if (!empty($referenceUrl)): ?>
                    <div class="reference-preview" id="referencePreview">
                        <img src="<?php echo htmlspecialchars($referenceUrl, ENT_QUOTES); ?>" alt="Foto Referensi" loading="eager" decoding="async" fetchpriority="high">
                        <div class="reference-overlay">
                            <i class="fas fa-eye"></i>
                            <span>Klik untuk tampilkan</span>
                        </div>
                    </div>
                    <div class="reference-meta">
                        <div>
                            <span>NISN</span>
                            <strong><?php echo htmlspecialchars($nisn); ?></strong>
                        </div>
                        <div>
                            <span>File</span>
                            <strong><?php echo htmlspecialchars($referenceFile); ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-reference">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Foto referensi belum tersedia. Silakan hubungi admin untuk upload foto wajah.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="face-card tips-card">
                <h6><i class="fas fa-lightbulb"></i> Tips Akurasi Tinggi</h6>
                <ul>
                    <li>Pencahayaan cukup dan tidak backlight.</li>
                    <li>Wajah menghadap kamera, tidak miring.</li>
                    <li>Lepas masker, kacamata hitam, atau topi.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($referenceUrl)): ?>
<div class="modal fade" id="referenceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content reference-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-image me-2"></i>Foto Referensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <img id="referenceModalImg" src="" alt="Foto Referensi Full" loading="eager" decoding="async" fetchpriority="high">
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl attendance-modal-dialog">
        <div class="modal-content attendance-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-circle-check me-2"></i>Konfirmasi Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="attendance-modal-grid">
                    <div class="attendance-photo-card">
                        <div class="attendance-photo-header">
                            <h6><i class="fas fa-user"></i> Hasil Verifikasi Wajah</h6>
                            <span class="match-badge success" id="attendanceMatchBadge">Lolos</span>
                        </div>
                        <div class="attendance-photo-wrap">
                            <img id="attendanceFacePreview" alt="Hasil Wajah">
                            <div class="attendance-photo-overlay">
                                <div class="overlay-header">
                                    <div class="overlay-title">
                                        <i class="fas fa-location-dot"></i> Geolocation
                                    </div>
                                    <span class="match-badge ready" id="attendanceGeoBadge">Tervalidasi</span>
                                </div>
                                <div class="overlay-body">
                                    <div class="overlay-geo">
                                        <div class="overlay-geo-grid">
                                            <div class="geo-main">
                                                <strong id="attendanceGeoName">-</strong>
                                                <span id="attendanceGeoAddress">-</span>
                                            </div>
                                            <div class="geo-map" id="attendanceGeoMap"></div>
                                        </div>
                                        <div class="geo-detail-grid">
                                            <div>
                                                <span>Latitude</span>
                                                <strong id="attendanceGeoLat">-</strong>
                                            </div>
                                            <div>
                                                <span>Longitude</span>
                                                <strong id="attendanceGeoLng">-</strong>
                                            </div>
                                            <div>
                                                <span>Jarak</span>
                                                <strong id="attendanceGeoDistance">-</strong>
                                            </div>
                                            <div>
                                                <span>Akurasi</span>
                                                <strong id="attendanceGeoAccuracy">-</strong>
                                            </div>
                                        </div>
                                        <div class="geo-time" id="attendanceGeoTime">-</div>
                                    </div>
                                    <div class="overlay-info">
                                        <div class="overlay-subtitle">
                                            <i class="fas fa-clipboard-list"></i> Keterangan Absensi
                                        </div>
                                        <div class="attendance-info-grid overlay-info-grid">
                                            <div>
                                                <span>Waktu absen</span>
                                                <strong id="attendanceInfoTime">-</strong>
                                            </div>
                                            <div>
                                                <span>Nama mapel</span>
                                                <strong id="attendanceInfoSubject">-</strong>
                                            </div>
                                            <div>
                                                <span>Hari</span>
                                                <strong id="attendanceInfoDay">-</strong>
                                            </div>
                                            <div>
                                                <span>Tanggal</span>
                                                <strong id="attendanceInfoDate">-</strong>
                                            </div>
                                            <div>
                                                <span>Jam pelajaran ke</span>
                                                <strong id="attendanceInfoJp">-</strong>
                                            </div>
                                            <div>
                                                <span>Nama guru</span>
                                                <strong id="attendanceInfoTeacher">-</strong>
                                            </div>
                                            <div>
                                                <span>Nama siswa</span>
                                                <strong id="attendanceInfoStudent">-</strong>
                                            </div>
                                            <div>
                                                <span>Absen</span>
                                                <strong id="attendanceInfoStatus">-</strong>
                                            </div>
                                        </div>
                                        <div class="overlay-divider"></div>
                                        <div class="overlay-metrics">
                                            <div>
                                                <span>Similarity</span>
                                                <strong id="attendanceSimilarityText">0%</strong>
                                            </div>
                                            <div>
                                                <span>Status</span>
                                                <strong id="attendanceStatusText">Siap</strong>
                                            </div>
                                            <div>
                                                <span>Jenis Absensi</span>
                                                <strong id="attendanceModeText">Hadir</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="attendance-modal-note" id="attendanceModalMessage">
                    Pastikan data sudah benar sebelum mengirim absensi.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="attendanceCancelBtn">
                    Batal
                </button>
                <button type="button" class="btn btn-success" id="attendanceSubmitBtn">
                    <i class="fas fa-paper-plane"></i> Konfirmasi & Absen
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../face/faces_logics/face-api.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r134/three.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const page = document.querySelector('.face-recognition-page');
    if (!page) return;

    const referenceUrl = page.dataset.referenceUrl;
    const threshold = parseFloat(page.dataset.threshold || '89');
    const descriptorDistanceThreshold = parseFloat(page.dataset.descriptorThreshold || '0.55');
    const descriptorStrongThreshold = Math.max(0.38, descriptorDistanceThreshold - 0.08);
    const descriptorMaxDistance = 0.9;
    const descriptorHardFailDistance = Math.max(0.85, descriptorMaxDistance - 0.02);
    const faceLabel = page.dataset.faceLabel || '';
    const modelBase = '../face/faces_logics/models';
    const faceModelFiles = [
        'ssd_mobilenetv1_model-weights_manifest.json',
        'ssd_mobilenetv1_model-shard1',
        'ssd_mobilenetv1_model-shard2',
        'tiny_face_detector_model-weights_manifest.json',
        'tiny_face_detector_model-shard1',
        'face_landmark_68_model-weights_manifest.json',
        'face_landmark_68_model-shard1',
        'face_recognition_model-weights_manifest.json',
        'face_recognition_model-shard1',
        'face_recognition_model-shard2'
    ];
    const studentKey = (page.dataset.studentKey || '').trim() || 'anon';
    const scheduleIdFromPage = (page.dataset.scheduleId || '').trim();
    const scheduleParams = new URLSearchParams(window.location.search);
    const scheduleIdFromQuery = (scheduleParams.get('schedule_id') || '').trim();
    const studentScheduleId = scheduleIdFromQuery || scheduleIdFromPage;
    const locationStateKey = `face_location_state_${studentKey}`;
    const lastDistanceMaxAgeMs = 10 * 60 * 1000;

    const video = document.getElementById('faceVideo');
    const canvas = document.getElementById('faceCanvas');
    const previewImg = document.getElementById('facePreview');
    const previewWrap = document.getElementById('cameraPreview');

    const startBtn = document.getElementById('faceStartBtn');
    const captureBtn = document.getElementById('faceCaptureBtn');
    const retryBtn = document.getElementById('faceRetryBtn');
    const proceedBtn = document.getElementById('faceProceedBtn');
    const poseFlowCard = document.getElementById('poseFlowCard');
    const poseFlowBadge = document.getElementById('poseFlowBadge');
    const poseStartBtn = document.getElementById('poseStartBtn');
    const poseResetBtn = document.getElementById('poseResetBtn');
    const poseInstructionText = document.getElementById('poseInstructionText');
    const poseRightProgress = document.getElementById('poseRightProgress');
    const poseLeftProgress = document.getElementById('poseLeftProgress');
    const poseFrontProgress = document.getElementById('poseFrontProgress');

    const cameraStatus = document.getElementById('cameraStatus');
    const matchBadge = document.getElementById('matchBadge');
    const similarityValue = document.getElementById('similarityValue');
    const similarityBar = document.getElementById('similarityBar');
    const statusMessage = document.getElementById('statusMessage');
    const faceHint = document.getElementById('faceHint');

    const referencePreview = document.getElementById('referencePreview');
    const cameraSelect = document.getElementById('cameraSelect');
    const refreshCameraBtn = document.getElementById('refreshCameraBtn');
    const memoryUsedText = document.getElementById('memoryUsedText');
    const memoryLimitText = document.getElementById('memoryLimitText');
    const memoryBarFill = document.getElementById('memoryBarFill');
    const memoryStatusMessage = document.getElementById('memoryStatusMessage');
    const memorySupportBadge = document.getElementById('memorySupportBadge');
    const locationLockLayer = document.getElementById('locationLockLayer');
    const locationLockMessage = document.getElementById('locationLockMessage');
    const locationRadiusText = document.getElementById('locationRadiusText');
    const locationDistanceText = document.getElementById('locationDistanceText');
    const locationAddressText = document.getElementById('locationAddressText');
    const locationRetryBtn = document.getElementById('locationRetryBtn');
    const locationLoading = document.getElementById('locationLoading');
    const loadingWrap = document.getElementById('loadingWrap');
    const attendanceModalEl = document.getElementById('attendanceModal');
    const attendanceFacePreview = document.getElementById('attendanceFacePreview');
    const attendanceSimilarityText = document.getElementById('attendanceSimilarityText');
    const attendanceStatusText = document.getElementById('attendanceStatusText');
    const attendanceGeoName = document.getElementById('attendanceGeoName');
    const attendanceGeoAddress = document.getElementById('attendanceGeoAddress');
    const attendanceGeoLat = document.getElementById('attendanceGeoLat');
    const attendanceGeoLng = document.getElementById('attendanceGeoLng');
    const attendanceGeoDistance = document.getElementById('attendanceGeoDistance');
    const attendanceGeoAccuracy = document.getElementById('attendanceGeoAccuracy');
    const attendanceGeoTime = document.getElementById('attendanceGeoTime');
    const attendanceGeoMap = document.getElementById('attendanceGeoMap');
    const attendanceGeoBadge = document.getElementById('attendanceGeoBadge');
    const attendanceMatchBadge = document.getElementById('attendanceMatchBadge');
    const attendanceSubmitBtn = document.getElementById('attendanceSubmitBtn');
    const attendanceModalMessage = document.getElementById('attendanceModalMessage');
    const attendanceCancelBtn = document.getElementById('attendanceCancelBtn');
    const attendanceCloseBtn = attendanceModalEl ? attendanceModalEl.querySelector('.btn-close') : null;
    const attendanceModeText = document.getElementById('attendanceModeText');
    const attendanceModePill = document.getElementById('attendanceModePill');
    const resetModeBtn = document.getElementById('resetModeBtn');
    const excuseSickBtn = document.getElementById('excuseSickBtn');
    const excuseIzinBtn = document.getElementById('excuseIzinBtn');
    const attendanceInfoTime = document.getElementById('attendanceInfoTime');
    const attendanceInfoSubject = document.getElementById('attendanceInfoSubject');
    const attendanceInfoDay = document.getElementById('attendanceInfoDay');
    const attendanceInfoDate = document.getElementById('attendanceInfoDate');
    const attendanceInfoJp = document.getElementById('attendanceInfoJp');
    const attendanceInfoTeacher = document.getElementById('attendanceInfoTeacher');
    const attendanceInfoStudent = document.getElementById('attendanceInfoStudent');
    const attendanceInfoStatus = document.getElementById('attendanceInfoStatus');

    const gpsEnabled = (page.dataset.gpsEnabled === '1');
    const schoolLat = parseFloat(page.dataset.schoolLat || '');
    const schoolLng = parseFloat(page.dataset.schoolLng || '');
    const schoolRadius = parseFloat(page.dataset.schoolRadius || '');
    const schoolName = page.dataset.schoolName || 'Lokasi Sekolah';
    const schoolAddress = page.dataset.schoolAddress || '';
    const scheduleDateRaw = (page.dataset.scheduleDate || '').trim();
    const scheduleDayName = (page.dataset.scheduleDay || '').trim();
    const scheduleSubject = (page.dataset.scheduleSubject || '').trim();
    const scheduleJp = (page.dataset.scheduleJp || '').trim();
    const scheduleTeacher = (page.dataset.scheduleTeacher || '').trim();
    const scheduleStudent = (page.dataset.scheduleStudent || '').trim();
    const accuracyLimit = Number.isFinite(schoolRadius)
        ? Math.max(35, Math.min(150, schoolRadius * 0.9))
        : 100;
    const accuracySoftLimit = Number.isFinite(schoolRadius)
        ? Math.max(accuracyLimit, Math.min(220, schoolRadius * 2.2))
        : 180;
    const accuracyExtremeLimit = Number.isFinite(schoolRadius)
        ? Math.max(2000, schoolRadius * 15)
        : 2000;
    const locationThrottleMs = 350;
    const locationSampleWindow = 3;
    const stableWithinThreshold = 1;
    const stableOutsideThreshold = 2;
    const memoryMonitorIntervalMs = 1200;
    const memoryBudgetBytes = 4 * 1024 * 1024 * 1024; // Budget runtime tetap 4GB
    const memoryTurboPercent = 70;
    const memoryTurboStartBytes = 10 * 1024 * 1024; // Mode maksimal dipicu sejak 0-10MB
    const memoryWarningPercent = 85;
    const memoryCriticalPercent = 92;
    const memoryRecoverPercent = 78;
    const memoryTurboLagMs = 190;
    const memoryWarningLagMs = 360;
    const memoryCriticalLagMs = 850;
    const memoryRecoverLagMs = 180;
    const memoryCriticalHoldMs = 2600;
    const memoryRecoverRequiredTicks = 2;

    let stream = null;
    let modelsReady = false;
    let modelLoadPromise = null;
    let modelWarmupStarted = false;
    let referenceDescriptor = null;
    let referenceDescriptorPromise = null;
    let referencePrefetchStarted = false;
    let descriptorModelReady = false;
    let lastFaceDistance = null;
    let lastDescriptorSimilarity = 0;
    let lastSimilarity = 0;
    let currentDeviceId = null;
    let referenceModal = null;
    let lastWorkingDeviceId = null;
    let referenceReady = false;
    let memoryIntervalId = null;
    let locationAllowed = !gpsEnabled;
    let locationReady = !gpsEnabled;
    let locationWatchId = null;
    let lastLocationUpdate = 0;
    let lastDistance = null;
    let retryLoadingStart = null;
    let scrollLockPosition = 0;
    let isScrollLocked = false;
    let hasValidLocation = false;
    let loaderInitialized = false;
    let loaderActive = false;
    let loaderAnimationId = null;
    let attendanceModal = null;
    let attendanceMap = null;
    let attendanceMapMarker = null;
    let lastCapturedData = '';
    let lastServerMatchToken = '';
    let matchPassed = false;
    let attendanceSubmitting = false;
    let attendanceDone = false;
    let locationVerified = !gpsEnabled;
    let locationOverride = false;
    let absenceMode = 'hadir';
    let currentLat = null;
    let currentLng = null;
    let currentAccuracy = null;
    let currentDistance = null;
    let currentLocationTs = null;
    const distanceSamples = [];
    let stableWithinCount = 0;
    let stableOutsideCount = 0;
    const locationNotifyKey = `face_location_notify_${studentKey}`;
    const locationNotifyCooldownMs = 5 * 60 * 1000;
    let modalLocationSuspended = false;
    let attendanceInfoText = '';
    const SATELLITE_TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';

    let detectorType = 'tiny';
    const poseFlowEnabled = false;
    const poseRequiredPerSide = 5;
    const poseRequiredFront = 1;
    const poseYawSideThreshold = 0.12;
    const poseYawFrontThreshold = 0.08;
    const poseCaptureCooldownMs = 450;
    let poseStarted = false;
    let poseCompleted = false;
    let poseStep = 'right';
    let poseRightSign = null;
    let poseRightFrames = [];
    let poseLeftFrames = [];
    let poseFrontFrames = [];
    let poseMonitorId = null;
    let poseMonitorBusy = false;
    let poseLastCaptureAt = 0;
    let poseLastYaw = null;
    let poseLastDirection = 'front';
    let poseSaving = false;
    let poseSaved = false;
    let memoryPressureState = 'normal';
    let memoryGuardHoldUntil = 0;
    let memoryRecoverStableTicks = 0;
    let memoryLastTickAt = 0;
    let memoryLastLagMs = 0;
    let memoryLastPercent = 0;
    let memoryLastUsedBytes = 0;
    let memoryLastSupportsHeap = false;
    let memoryLastNotifyAt = 0;
    let poseMonitorSkipTick = 0;

    if (page) {
        page.classList.add('mode-hadir');
    }

    function getDetectorOptions() {
        if (detectorType === 'ssd') {
            return new faceapi.SsdMobilenetv1Options({
                minConfidence: 0.5
            });
        }

        return new faceapi.TinyFaceDetectorOptions({
            inputSize: 320,
            scoreThreshold: 0.5
        });
    }

    function setBadge(state, text) {
        matchBadge.className = 'match-badge ' + state;
        matchBadge.textContent = text;
    }

    function setStatus(text) {
        statusMessage.textContent = text;
    }

    function setCameraStatus(text) {
        cameraStatus.textContent = text;
    }

    function getMemoryProfile() {
        if (memoryPressureState === 'critical') {
            return 'critical';
        }
        if (memoryPressureState === 'warning') {
            return 'throttle';
        }
        const turboReady = memoryLastSupportsHeap
            && (memoryLastUsedBytes <= memoryTurboStartBytes || memoryLastPercent < memoryTurboPercent)
            && memoryLastLagMs <= memoryTurboLagMs;
        return turboReady ? 'turbo' : 'normal';
    }

    function getCapturePipelineConfig() {
        const profile = getMemoryProfile();
        if (profile === 'critical') {
            return {
                profile,
                maxSide: 440,
                jpegQuality: 0.8,
                snapshotScale: 1.25,
                snapshotDelayMs: 260
            };
        }
        if (profile === 'throttle') {
            return {
                profile,
                maxSide: 580,
                jpegQuality: 0.83,
                snapshotScale: 1.45,
                snapshotDelayMs: 200
            };
        }
        if (profile === 'turbo') {
            return {
                profile,
                maxSide: 980,
                jpegQuality: 0.9,
                snapshotScale: 1.95,
                snapshotDelayMs: 70
            };
        }
        return {
            profile,
            maxSide: 760,
            jpegQuality: 0.86,
            snapshotScale: 1.7,
            snapshotDelayMs: 140
        };
    }

    function isMemoryGuardActive() {
        return memoryPressureState === 'critical' || Date.now() < memoryGuardHoldUntil;
    }

    function shouldThrottlePoseLoop() {
        if (isMemoryGuardActive()) {
            return true;
        }
        if (memoryPressureState === 'warning') {
            poseMonitorSkipTick = (poseMonitorSkipTick + 1) % 2;
            return poseMonitorSkipTick === 1;
        }
        poseMonitorSkipTick = 0;
        return false;
    }

    async function waitForMemoryResponsive(maxWaitMs = 9000) {
        if (!isMemoryGuardActive()) {
            return true;
        }
        const deadline = Date.now() + Math.max(1500, maxWaitMs);
        while (Date.now() < deadline) {
            if (!isMemoryGuardActive()) {
                return true;
            }
            await new Promise((resolve) => setTimeout(resolve, 280));
        }
        return !isMemoryGuardActive();
    }

    function notifyMemoryHold(message) {
        const now = Date.now();
        if ((now - memoryLastNotifyAt) < 2200) {
            return;
        }
        memoryLastNotifyAt = now;
        setStatus(message);
    }

    function updateMemoryDrivenButtons() {
        updateStartButtonState();
        if (isMemoryGuardActive()) {
            captureBtn.disabled = true;
            if (poseFlowEnabled && poseStartBtn) {
                poseStartBtn.disabled = true;
            }
            return;
        }
        updateCaptureButtonByPose();
        refreshPoseButtons();
    }

    function setMemoryPressureState(nextState, context = {}) {
        if (!memoryBarFill || !memorySupportBadge || !memoryStatusMessage) {
            return;
        }

        const supportsHeap = !!context.supportsHeap;
        const percent = Number.isFinite(context.percent) ? context.percent : memoryLastPercent;
        const lagMs = Number.isFinite(context.lagMs) ? context.lagMs : memoryLastLagMs;
        const profile = String(context.profile || 'normal');
        const previousState = memoryPressureState;
        memoryPressureState = nextState;

        memoryBarFill.classList.remove('warning', 'danger');

        if (nextState === 'critical') {
            memorySupportBadge.className = 'match-badge error';
            memorySupportBadge.textContent = 'Menahan';
            memoryBarFill.classList.add('danger');
            const info = supportsHeap
                ? `Heap ${percent.toFixed(1)}% | Lag ${Math.round(lagMs)} ms`
                : `Lag ${Math.round(lagMs)} ms`;
            memoryStatusMessage.textContent = `Tekanan tinggi terdeteksi (${info}). Proses berat dijeda sementara.`;
        } else if (nextState === 'warning') {
            memorySupportBadge.className = 'match-badge warning';
            memorySupportBadge.textContent = 'Throttle';
            memoryBarFill.classList.add('warning');
            const info = supportsHeap
                ? `Heap ${percent.toFixed(1)}% | Lag ${Math.round(lagMs)} ms`
                : `Lag ${Math.round(lagMs)} ms`;
            memoryStatusMessage.textContent = `Pemakaian RAM melewati 85% (${info}). Throttling aktif agar aplikasi tidak hang.`;
        } else {
            if (supportsHeap) {
                memorySupportBadge.className = 'match-badge ready';
                if (profile === 'turbo') {
                    memorySupportBadge.textContent = 'Turbo';
                    memoryStatusMessage.textContent = `Mode cepat aktif (baseline 4GB, Heap awal 0-10MB dipacu maksimal | Lag ${Math.round(lagMs)} ms).`;
                } else {
                    memorySupportBadge.textContent = 'Aktif';
                    memoryStatusMessage.textContent = `Penggunaan RAM aman (Heap ${percent.toFixed(1)}% | Lag ${Math.round(lagMs)} ms).`;
                }
            } else {
                memorySupportBadge.className = 'match-badge warning';
                memorySupportBadge.textContent = 'Terbatas';
                memoryStatusMessage.textContent = `Browser tidak memberi data heap. Monitoring responsivitas aktif (Lag ${Math.round(lagMs)} ms).`;
            }
        }

        if (previousState !== nextState || nextState === 'critical') {
            updateMemoryDrivenButtons();
        }
    }

    function setSimilarity(value) {
        const safeValue = Number.isFinite(value) ? value : 0;
        lastSimilarity = safeValue;
        similarityValue.textContent = safeValue.toFixed(2) + '%';
        similarityBar.style.width = Math.min(100, Math.max(0, safeValue)) + '%';
    }

    function setPoseBadge(state, text) {
        if (!poseFlowEnabled) return;
        if (!poseFlowBadge) return;
        poseFlowBadge.className = 'match-badge ' + state;
        poseFlowBadge.textContent = text;
    }

    function setPoseInstruction(htmlText) {
        if (!poseFlowEnabled) return;
        if (!poseInstructionText) return;
        poseInstructionText.innerHTML = htmlText;
    }

    function updatePoseProgress() {
        if (!poseFlowEnabled) return;
        if (poseRightProgress) {
            poseRightProgress.textContent = `${poseRightFrames.length}/${poseRequiredPerSide}`;
        }
        if (poseLeftProgress) {
            poseLeftProgress.textContent = `${poseLeftFrames.length}/${poseRequiredPerSide}`;
        }
        if (poseFrontProgress) {
            poseFrontProgress.textContent = `${poseFrontFrames.length}/${poseRequiredFront}`;
        }
    }

    function setPoseStep(step) {
        if (!poseFlowEnabled) return;
        poseStep = step;
        if (poseFlowCard) {
            poseFlowCard.classList.remove('step-right', 'step-left', 'step-front', 'is-complete');
            if (poseCompleted) {
                poseFlowCard.classList.add('is-complete');
            } else {
                poseFlowCard.classList.add(`step-${step}`);
            }
        }
    }

    function stopPoseMonitor() {
        if (!poseFlowEnabled) return;
        if (poseMonitorId) {
            clearInterval(poseMonitorId);
            poseMonitorId = null;
        }
        poseMonitorBusy = false;
    }

    function getCurrentPoseTargetLabel() {
        if (!poseFlowEnabled) return 'depan';
        if (poseStep === 'left') return 'kiri';
        if (poseStep === 'front') return 'depan';
        return 'kanan';
    }

    function updateCaptureButtonByPose() {
        const hasStream = !!stream && !!video.srcObject;
        if (!poseFlowEnabled) {
            captureBtn.disabled = !(hasStream && referenceReady);
            return;
        }
        captureBtn.disabled = !(hasStream && referenceReady && poseCompleted);
    }

    function refreshPoseButtons() {
        if (!poseFlowEnabled) {
            if (poseFlowCard) {
                poseFlowCard.style.display = 'none';
            }
            return;
        }
        const hasStream = !!stream && !!video.srcObject;
        if (poseStartBtn) {
            poseStartBtn.disabled = !hasStream || poseStarted || poseCompleted;
        }
        if (poseResetBtn) {
            poseResetBtn.disabled = !hasStream;
        }
    }

    function resetPoseValidation(options = {}) {
        if (!poseFlowEnabled) {
            poseStarted = false;
            poseCompleted = true;
            poseSaving = false;
            poseSaved = true;
            if (poseFlowCard) {
                poseFlowCard.style.display = 'none';
            }
            updateCaptureButtonByPose();
            return;
        }
        const keepStatus = !!options.keepStatus;
        stopPoseMonitor();
        poseStarted = false;
        poseCompleted = false;
        poseStep = 'right';
        poseRightSign = null;
        poseRightFrames = [];
        poseLeftFrames = [];
        poseFrontFrames = [];
        poseLastYaw = null;
        poseLastDirection = 'front';
        poseLastCaptureAt = 0;
        poseSaving = false;
        poseSaved = false;
        setPoseStep('right');
        setPoseBadge('waiting', 'Belum Mulai');
        updatePoseProgress();
        if (!keepStatus) {
            setPoseInstruction('Aktifkan kamera, lalu klik <strong>Konfirmasi Siap & Mulai Otomatis</strong>.');
        }
        refreshPoseButtons();
        updateCaptureButtonByPose();
    }

    function averagePoint(points) {
        if (!points || !points.length) return null;
        let x = 0;
        let y = 0;
        for (const point of points) {
            x += point.x;
            y += point.y;
        }
        return {
            x: x / points.length,
            y: y / points.length
        };
    }

    function estimateHeadYaw(landmarks) {
        if (!landmarks) return null;
        const nose = landmarks.getNose();
        const leftEye = landmarks.getLeftEye();
        const rightEye = landmarks.getRightEye();
        if (!nose || !nose.length || !leftEye || !leftEye.length || !rightEye || !rightEye.length) {
            return null;
        }
        const noseTip = nose[3] || nose[Math.floor(nose.length / 2)];
        const leftEyeCenter = averagePoint(leftEye);
        const rightEyeCenter = averagePoint(rightEye);
        if (!noseTip || !leftEyeCenter || !rightEyeCenter) {
            return null;
        }
        const eyeSpan = Math.abs(rightEyeCenter.x - leftEyeCenter.x);
        if (eyeSpan < 1) {
            return null;
        }
        const eyeCenterX = (leftEyeCenter.x + rightEyeCenter.x) / 2;
        const normalized = (noseTip.x - eyeCenterX) / (eyeSpan / 2);
        return Math.max(-1.2, Math.min(1.2, normalized));
    }

    function classifyYawDirection(yaw) {
        if (!Number.isFinite(yaw)) return 'unknown';
        if (Math.abs(yaw) < poseYawFrontThreshold) return 'front';
        return yaw >= 0 ? 'right' : 'left';
    }

    function buildPoseFrameCanvas(maxSide = 640) {
        if (!video.videoWidth || !video.videoHeight) return null;
        const sourceWidth = video.videoWidth;
        const sourceHeight = video.videoHeight;
        const longest = Math.max(sourceWidth, sourceHeight);
        const scale = longest > maxSide ? (maxSide / longest) : 1;
        const width = Math.max(1, Math.round(sourceWidth * scale));
        const height = Math.max(1, Math.round(sourceHeight * scale));

        const poseCanvas = document.createElement('canvas');
        poseCanvas.width = width;
        poseCanvas.height = height;
        const poseCtx = poseCanvas.getContext('2d');
        poseCtx.save();
        poseCtx.scale(-1, 1);
        poseCtx.drawImage(video, -width, 0, width, height);
        poseCtx.restore();
        return poseCanvas;
    }

    async function detectPoseSample(options = {}) {
        if (!poseFlowEnabled) return null;
        const includeImage = !!options.includeImage;
        const poseCanvas = buildPoseFrameCanvas(640);
        if (!poseCanvas) return null;

        let detection = await faceapi
            .detectSingleFace(poseCanvas, getDetectorOptions())
            .withFaceLandmarks();

        if (!detection) {
            return null;
        }

        const yaw = estimateHeadYaw(detection.landmarks);
        if (!Number.isFinite(yaw)) {
            return null;
        }

        const sample = {
            yaw,
            direction: classifyYawDirection(yaw),
            timestamp: Date.now()
        };

        if (includeImage) {
            sample.imageData = poseCanvas.toDataURL('image/jpeg', 0.84);
        }

        return sample;
    }

    function updatePoseLiveInstruction(sample) {
        if (!poseFlowEnabled) return;
        if (!poseStarted || poseCompleted) return;
        const targetLabel = getCurrentPoseTargetLabel();
        if (!sample) {
            setPoseInstruction(`Target saat ini: <strong>${targetLabel}</strong>. Wajah belum terbaca, posisikan wajah di panduan. Sistem akan ambil frame otomatis.`);
            return;
        }
        const yawText = Number.isFinite(sample.yaw) ? sample.yaw.toFixed(3) : '-';
        const directionText = sample.direction === 'front'
            ? 'depan'
            : (sample.direction === 'right' ? 'kanan' : 'kiri');
        setPoseInstruction(`Target saat ini: <strong>${targetLabel}</strong>. Terdeteksi: <strong>${directionText}</strong> (yaw ${yawText}). Sistem akan menangkap frame otomatis.`);
    }

    async function savePoseFramesToServer() {
        if (!poseFlowEnabled) return true;
        if (poseSaved) return true;
        if (poseSaving) return false;
        poseSaving = true;
        try {
            const payload = {
                right: poseRightFrames.map(item => item.imageData).filter(Boolean),
                left: poseLeftFrames.map(item => item.imageData).filter(Boolean),
                front: poseFrontFrames.map(item => item.imageData).filter(Boolean)
            };
            const response = await fetch('../api/save_pose_frames.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data || !data.success) {
                throw new Error(data?.message || 'Gagal menyimpan frame pose.');
            }
            poseSaved = true;
            return true;
        } catch (error) {
            setStatus(error?.message || 'Gagal menyimpan frame pose di server.');
            return false;
        } finally {
            poseSaving = false;
        }
    }

    async function finalizePoseValidation() {
        if (!poseFlowEnabled) return;
        const saved = await savePoseFramesToServer();
        poseCompleted = saved;
        poseStarted = false;
        stopPoseMonitor();
        setPoseStep('front');
        if (poseFlowCard) {
            poseFlowCard.classList.add('is-complete');
        }
        if (saved) {
            setPoseBadge('success', 'Selesai');
            setPoseInstruction('Validasi pose selesai dan frame tersimpan. Lanjutkan dengan <strong>Ambil & Cocokkan</strong> untuk verifikasi wajah depan.');
            setStatus('Validasi pose selesai. Silakan ambil foto wajah depan untuk verifikasi DeepFace.');
        } else {
            setPoseBadge('warning', 'Data Belum Tersimpan');
            setPoseInstruction('Validasi pose selesai, namun penyimpanan frame gagal. Klik <strong>Reset Pose</strong> lalu ulangi.');
            if (poseFlowCard) {
                poseFlowCard.classList.remove('is-complete');
            }
        }
        refreshPoseButtons();
        updateCaptureButtonByPose();
    }

    async function processPoseSample(sample) {
        if (!poseFlowEnabled) return;
        if (!poseStarted || poseCompleted) return;
        if (!sample) return;

        const now = Date.now();
        if ((now - poseLastCaptureAt) < poseCaptureCooldownMs) {
            return;
        }

        poseLastYaw = sample.yaw;
        poseLastDirection = sample.direction;
        const absYaw = Math.abs(sample.yaw);
        const sign = sample.yaw >= 0 ? 1 : -1;

        if (poseStep === 'right') {
            if (absYaw < poseYawSideThreshold) {
                return;
            }
            if (poseRightSign === null) {
                poseRightSign = sign;
            }
            if (sign !== poseRightSign) {
                return;
            }
            if (poseRightFrames.length < poseRequiredPerSide) {
                poseRightFrames.push(sample);
                poseLastCaptureAt = now;
                updatePoseProgress();
                setStatus(`Frame kanan tersimpan (${poseRightFrames.length}/${poseRequiredPerSide}).`);
            }
            if (poseRightFrames.length >= poseRequiredPerSide) {
                setPoseStep('left');
                setPoseInstruction('Step 2/3: Sekarang menoleh ke <strong>kiri</strong>. Sistem akan menangkap frame otomatis.');
                setStatus('Frame kanan lengkap. Lanjutkan menoleh ke kiri.');
            }
            return;
        }

        if (poseStep === 'left') {
            if (absYaw < poseYawSideThreshold) {
                return;
            }
            if (poseRightSign === null || sign !== (poseRightSign * -1)) {
                return;
            }
            if (poseLeftFrames.length < poseRequiredPerSide) {
                poseLeftFrames.push(sample);
                poseLastCaptureAt = now;
                updatePoseProgress();
                setStatus(`Frame kiri tersimpan (${poseLeftFrames.length}/${poseRequiredPerSide}).`);
            }
            if (poseLeftFrames.length >= poseRequiredPerSide) {
                setPoseStep('front');
                setPoseInstruction('Step 3/3: Hadapkan wajah ke <strong>depan</strong>. Sistem akan ambil 1 frame otomatis.');
                setStatus('Frame kiri lengkap. Hadapkan wajah ke depan.');
            }
            return;
        }

        if (poseStep === 'front') {
            if (absYaw > poseYawFrontThreshold) {
                return;
            }
            if (poseFrontFrames.length < poseRequiredFront) {
                poseFrontFrames.push(sample);
                poseLastCaptureAt = now;
                updatePoseProgress();
            }
            if (poseFrontFrames.length >= poseRequiredFront) {
                await finalizePoseValidation();
            }
        }
    }

    function startPoseMonitor() {
        if (!poseFlowEnabled) return;
        stopPoseMonitor();
        poseMonitorId = setInterval(async () => {
            if (!poseStarted || poseCompleted || poseMonitorBusy || !stream || !video.videoWidth) {
                return;
            }
            if (shouldThrottlePoseLoop()) {
                if (isMemoryGuardActive()) {
                    notifyMemoryHold('Perangkat sedang sibuk. Validasi pose dijeda sementara hingga stabil.');
                }
                return;
            }
            poseMonitorBusy = true;
            try {
                const sample = await detectPoseSample({ includeImage: true });
                if (sample) {
                    poseLastYaw = sample.yaw;
                    poseLastDirection = sample.direction;
                }
                updatePoseLiveInstruction(sample);
                await processPoseSample(sample);
            } catch (error) {
                // Keep UI responsive even when frame detection misses.
            } finally {
                poseMonitorBusy = false;
            }
        }, 600);
    }

    function ensurePoseStarted() {
        if (!poseFlowEnabled) return false;
        if (!stream || !video.srcObject) {
            setStatus('Aktifkan kamera terlebih dahulu.');
            return false;
        }
        if (!modelsReady) {
            setStatus('Model belum siap. Tunggu sampai kamera aktif.');
            return false;
        }
        if (poseCompleted) {
            setStatus('Validasi pose sudah selesai. Lanjutkan Ambil & Cocokkan.');
            return false;
        }
        return true;
    }

    function startPoseValidationFlow() {
        if (!poseFlowEnabled) return;
        if (!ensurePoseStarted()) return;
        if (poseRightFrames.length || poseLeftFrames.length || poseFrontFrames.length) {
            resetPoseValidation({ keepStatus: true });
        }
        poseStarted = true;
        setPoseStep('right');
        setPoseBadge('loading', 'Berjalan');
        setPoseInstruction('Step 1/3: Menoleh ke <strong>kanan</strong>. Sistem akan mengambil 5 frame otomatis.');
        setStatus('Validasi pose otomatis aktif. Menoleh kanan, kiri, lalu depan.');
        refreshPoseButtons();
        updateCaptureButtonByPose();
        startPoseMonitor();
    }

    function updateLocationSnapshot(lat, lng, accuracy, distance, timestamp) {
        currentLat = Number.isFinite(lat) ? lat : currentLat;
        currentLng = Number.isFinite(lng) ? lng : currentLng;
        currentAccuracy = Number.isFinite(accuracy) ? accuracy : currentAccuracy;
        currentDistance = Number.isFinite(distance) ? distance : currentDistance;
        currentLocationTs = timestamp || Date.now();
    }

    function formatCoordinate(value) {
        if (!Number.isFinite(value)) return '-';
        return value.toFixed(6);
    }

    function formatAccuracy(value) {
        if (!Number.isFinite(value)) return '-';
        return `+/-${Math.round(value)} m`;
    }

    function formatDateTime(ms) {
        if (!ms) return '-';
        const dt = new Date(ms);
        const day = dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        const time = dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        return `${day} ${time}`;
    }

    function getIndonesianDayName(dateObj) {
        const names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        if (!(dateObj instanceof Date) || isNaN(dateObj)) return '-';
        return names[dateObj.getDay()] || '-';
    }

    function formatDateLabel(dateObj) {
        if (!(dateObj instanceof Date) || isNaN(dateObj)) return '-';
        return dateObj.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    }

    function ensureAttendanceMap(lat, lng) {
        if (!attendanceGeoMap || typeof L === 'undefined') return;
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            attendanceGeoMap.innerHTML = '<div class="geo-map-empty">Lokasi belum tersedia</div>';
            return;
        }
        if (!attendanceMap) {
            attendanceGeoMap.innerHTML = '';
            attendanceMap = L.map(attendanceGeoMap, {
                zoomControl: false,
                attributionControl: false,
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false,
                tap: false
            });
            L.tileLayer(SATELLITE_TILE_URL, {
                maxZoom: 20,
                minZoom: 14,
                tileSize: 512,
                zoomOffset: -1,
                detectRetina: true,
                crossOrigin: true
            }).addTo(attendanceMap);
        }
        const center = L.latLng(lat, lng);
        attendanceMap.setView(center, 19);
        if (!attendanceMapMarker) {
            attendanceMapMarker = L.circleMarker(center, {
                radius: 6,
                color: '#ef4444',
                weight: 2,
                fillColor: '#ef4444',
                fillOpacity: 0.9
            }).addTo(attendanceMap);
        } else {
            attendanceMapMarker.setLatLng(center);
        }
        requestAnimationFrame(() => {
            if (attendanceMap) {
                attendanceMap.invalidateSize();
            }
        });
    }

    async function captureAttendanceSnapshot() {
        const canCapture = await waitForMemoryResponsive(5000);
        if (!canCapture) {
            return '';
        }
        if (typeof html2canvas !== 'function') {
            return '';
        }
        const target = attendanceModalEl ? attendanceModalEl.querySelector('.attendance-photo-wrap') : null;
        if (!target) {
            return '';
        }
        const captureConfig = getCapturePipelineConfig();
        ensureAttendanceMap(currentLat, currentLng);
        await new Promise((resolve) => setTimeout(resolve, captureConfig.snapshotDelayMs));
        try {
            const canvas = await html2canvas(target, {
                backgroundColor: null,
                scale: captureConfig.snapshotScale,
                useCORS: true,
                allowTaint: false
            });
            return canvas.toDataURL('image/jpeg', 0.9);
        } catch (err) {
            return '';
        }
    }

    function buildAttendanceInfo() {
        const now = new Date();
        const timeText = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
        const scheduleDateObj = scheduleDateRaw ? new Date(`${scheduleDateRaw}T00:00:00`) : now;
        const dayText = scheduleDayName || getIndonesianDayName(scheduleDateObj);
        const dateText = formatDateLabel(scheduleDateObj);
        const subjectText = scheduleSubject || '-';
        const jpText = scheduleJp || '-';
        const teacherText = scheduleTeacher || '-';
        const studentText = scheduleStudent || faceLabel || '-';
        const absenceText = getModeLabel(absenceMode);

        return {
            timeText,
            subjectText,
            dayText,
            dateText,
            jpText,
            teacherText,
            studentText,
            absenceText
        };
    }

    function updateAttendanceInfo() {
        const info = buildAttendanceInfo();
        if (attendanceInfoTime) attendanceInfoTime.textContent = info.timeText;
        if (attendanceInfoSubject) attendanceInfoSubject.textContent = info.subjectText;
        if (attendanceInfoDay) attendanceInfoDay.textContent = info.dayText;
        if (attendanceInfoDate) attendanceInfoDate.textContent = info.dateText;
        if (attendanceInfoJp) attendanceInfoJp.textContent = info.jpText;
        if (attendanceInfoTeacher) attendanceInfoTeacher.textContent = info.teacherText;
        if (attendanceInfoStudent) attendanceInfoStudent.textContent = info.studentText;
        if (attendanceInfoStatus) attendanceInfoStatus.textContent = info.absenceText;

        const details = [
            `Waktu absen: ${info.timeText}`,
            `Nama mapel: ${info.subjectText}`,
            `Hari: ${info.dayText}`,
            `Tanggal: ${info.dateText}`,
            `Jam pelajaran ke: ${info.jpText}`,
            `Nama guru: ${info.teacherText}`,
            `Nama siswa: ${info.studentText}`,
            `Absen: ${info.absenceText}`
        ];
        let text = details.join(' | ');
        if (text.length > 250) {
            text = text.slice(0, 247) + '...';
        }
        attendanceInfoText = text;
    }

    function getAccuracyBufferValue(accuracy) {
        if (!Number.isFinite(accuracy) || accuracy <= 0) return 0;
        return Math.min(accuracy, Math.max(50, schoolRadius * 1.5));
    }

    function isWithinRadius(distance, accuracy) {
        if (!Number.isFinite(distance)) return false;
        const buffer = getAccuracyBufferValue(accuracy);
        return distance <= (schoolRadius + buffer);
    }

    function normalizeMode(mode) {
        if (mode === 'sakit' || mode === 'izin') return mode;
        return 'hadir';
    }

    function getPresentId(mode) {
        if (mode === 'sakit') return 2;
        if (mode === 'izin') return 3;
        return 1;
    }

    function getModeLabel(mode) {
        if (mode === 'sakit') return 'Sakit';
        if (mode === 'izin') return 'Izin';
        return 'Hadir';
    }

    function setAbsenceMode(mode) {
        const nextMode = normalizeMode(mode);
        const switchingToHadir = nextMode === 'hadir' && absenceMode !== 'hadir';
        absenceMode = nextMode;
        locationOverride = absenceMode !== 'hadir';
        if (attendanceModeText) {
            attendanceModeText.textContent = getModeLabel(absenceMode);
        }
        if (attendanceModePill) {
            attendanceModePill.textContent = `Mode: ${getModeLabel(absenceMode)}`;
            attendanceModePill.className = 'mode-pill mode-' + absenceMode;
        }
        if (resetModeBtn) {
            resetModeBtn.classList.toggle('d-none', absenceMode === 'hadir');
        }
        if (page) {
            page.classList.remove('mode-hadir', 'mode-sakit', 'mode-izin');
            page.classList.add('mode-' + absenceMode);
        }
        if (absenceMode !== 'hadir') {
            locationAllowed = true;
            locationReady = true;
            setStatus(`Mode ${getModeLabel(absenceMode)} aktif. Verifikasi wajah tetap diperlukan.`);
            if (gpsEnabled) {
                setLocationLock(false);
            }
        } else {
            setStatus('Mode hadir aktif. Pastikan berada di dalam radius lokasi.');
        }

        if (switchingToHadir && gpsEnabled) {
            const latestDistance = Number.isFinite(currentDistance) ? currentDistance : lastDistance;
            const validCurrentGeo = Number.isFinite(currentLat) && Number.isFinite(currentLng) && isWithinRadius(latestDistance, currentAccuracy);
            const lastDistanceState = loadLastDistance();
            const validLastGeo = isRecentDistance(lastDistanceState, true) && isWithinRadius(lastDistanceState.distance, lastDistanceState.accuracy);

            if (validCurrentGeo || validLastGeo) {
                locationAllowed = true;
                locationVerified = true;
                locationReady = true;
                hasValidLocation = true;
                stableWithinCount = stableWithinThreshold;
                stableOutsideCount = 0;
                if (!validCurrentGeo && validLastGeo) {
                    lastDistance = lastDistanceState.distance;
                    updateLocationSnapshot(
                        lastDistanceState.lat,
                        lastDistanceState.lng,
                        lastDistanceState.accuracy,
                        lastDistanceState.distance,
                        lastDistanceState.timestamp
                    );
                }
                clearLocationState();
                setLocationLock(false);
                setCameraStatus(`Lokasi valid (${formatDistance(Number.isFinite(currentDistance) ? currentDistance : lastDistance)})`);
                setStatus('Mode hadir aktif. Lokasi sudah tervalidasi, kamera bisa langsung diaktifkan.');
            } else {
                locationAllowed = false;
                locationVerified = false;
                locationReady = false;
                hasValidLocation = false;
                stableWithinCount = 0;
                stableOutsideCount = 0;
                distanceSamples.length = 0;
                clearLocationState();
                setLocationLock(true, 'Memeriksa ulang lokasi Anda...');
                setCameraStatus('Memeriksa lokasi');
                setStatus('Mode hadir aktif. Memeriksa ulang lokasi untuk memastikan Anda di dalam radius.');
                if (stream) {
                    stopCamera();
                }
                captureBtn.disabled = true;
                retryBtn.disabled = true;
                proceedBtn.disabled = true;
                previewWrap.classList.remove('show');
                setRetryLoading(true);
                startLocationWatch();
            }
        }
        updateAttendanceInfo();
        updateStartButtonState();
    }

    function formatMB(bytes) {
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function updateMemoryMonitor() {
        if (!memoryUsedText || !memoryLimitText || !memoryBarFill || !memoryStatusMessage || !memorySupportBadge) return;

        const now = Date.now();
        if (!memoryLastTickAt) {
            memoryLastTickAt = now;
        }
        const elapsed = now - memoryLastTickAt;
        memoryLastTickAt = now;
        const lagMs = Math.max(0, elapsed - memoryMonitorIntervalMs);

        const perfMemory = (typeof performance !== 'undefined' && performance && performance.memory) ? performance.memory : null;
        const used = perfMemory?.usedJSHeapSize || 0;
        const limit = perfMemory?.jsHeapSizeLimit || 0;
        const total = perfMemory?.totalJSHeapSize || 0;
        const supportsHeap = Number.isFinite(limit) && limit > 0;
        const effectiveLimit = supportsHeap
            ? Math.max(1, Math.min(limit, memoryBudgetBytes))
            : 0;
        const percent = supportsHeap ? Math.min(100, (used / effectiveLimit) * 100) : 0;

        memoryUsedText.textContent = supportsHeap ? formatMB(used) : '-';
        memoryLimitText.textContent = supportsHeap
            ? formatMB(effectiveLimit)
            : ((Number.isFinite(total) && total > 0) ? formatMB(total) : '-');
        memoryBarFill.style.width = supportsHeap
            ? (percent.toFixed(1) + '%')
            : (Math.min(100, lagMs / 10).toFixed(1) + '%');

        let nextState = 'normal';
        if (supportsHeap) {
            if (percent > memoryCriticalPercent || (percent > memoryWarningPercent && lagMs >= memoryCriticalLagMs)) {
                nextState = 'critical';
                memoryGuardHoldUntil = Math.max(memoryGuardHoldUntil, now + memoryCriticalHoldMs);
                memoryRecoverStableTicks = 0;
            } else if (percent > memoryWarningPercent) {
                nextState = 'warning';
                memoryRecoverStableTicks = 0;
            } else {
                const safeByHeap = percent <= memoryRecoverPercent;
                const safeByLag = lagMs <= memoryRecoverLagMs;
                if (safeByHeap && safeByLag) {
                    memoryRecoverStableTicks += 1;
                } else {
                    memoryRecoverStableTicks = 0;
                }

                if (now < memoryGuardHoldUntil) {
                    nextState = 'critical';
                } else if (memoryRecoverStableTicks < memoryRecoverRequiredTicks && memoryPressureState !== 'normal') {
                    nextState = 'warning';
                } else if (memoryRecoverStableTicks >= memoryRecoverRequiredTicks) {
                    memoryGuardHoldUntil = 0;
                }
            }
        } else {
            if (lagMs >= memoryCriticalLagMs) {
                nextState = 'critical';
                memoryGuardHoldUntil = Math.max(memoryGuardHoldUntil, now + memoryCriticalHoldMs);
                memoryRecoverStableTicks = 0;
            } else if (lagMs >= memoryWarningLagMs) {
                nextState = 'warning';
                memoryRecoverStableTicks = 0;
            } else {
                if (lagMs <= memoryRecoverLagMs) {
                    memoryRecoverStableTicks += 1;
                } else {
                    memoryRecoverStableTicks = 0;
                }
                if (now < memoryGuardHoldUntil) {
                    nextState = 'critical';
                } else if (memoryRecoverStableTicks < memoryRecoverRequiredTicks && memoryPressureState !== 'normal') {
                    nextState = 'warning';
                } else if (memoryRecoverStableTicks >= memoryRecoverRequiredTicks) {
                    memoryGuardHoldUntil = 0;
                }
            }
        }

        const profile = nextState === 'critical'
            ? 'critical'
            : (nextState === 'warning'
                ? 'throttle'
                : ((supportsHeap && percent < memoryTurboPercent && lagMs <= memoryTurboLagMs) ? 'turbo' : 'normal'));

        memoryLastPercent = supportsHeap ? percent : 0;
        memoryLastLagMs = lagMs;
        memoryLastUsedBytes = supportsHeap ? used : 0;
        memoryLastSupportsHeap = supportsHeap;
        setMemoryPressureState(nextState, {
            supportsHeap,
            percent,
            lagMs,
            profile
        });
    }

    function startMemoryMonitor() {
        if (memoryIntervalId) return;
        memoryLastTickAt = Date.now();
        updateMemoryMonitor();
        memoryIntervalId = setInterval(updateMemoryMonitor, memoryMonitorIntervalMs);
    }

    function stopMemoryMonitor() {
        if (memoryIntervalId) {
            clearInterval(memoryIntervalId);
            memoryIntervalId = null;
        }
        memoryPressureState = 'normal';
        memoryGuardHoldUntil = 0;
        memoryRecoverStableTicks = 0;
        memoryLastUsedBytes = 0;
        memoryLastSupportsHeap = false;
        poseMonitorSkipTick = 0;
    }

    function setScrollLock(locked) {
        const html = document.documentElement;
        const body = document.body;
        if (locked === isScrollLocked) {
            return;
        }

        if (locked) {
            scrollLockPosition = window.scrollY || window.pageYOffset || 0;
            html.classList.add('scroll-locked');
            body.classList.add('scroll-locked');
            body.style.top = `-${scrollLockPosition}px`;
            body.style.position = 'fixed';
            body.style.width = '100%';
            isScrollLocked = true;
        } else {
            html.classList.remove('scroll-locked');
            body.classList.remove('scroll-locked');
            body.style.position = '';
            body.style.top = '';
            body.style.width = '';
            isScrollLocked = false;
            window.scrollTo(0, scrollLockPosition);
        }
    }

    function suspendLocationLayerForModal() {
        if (!locationLockLayer) return;
        modalLocationSuspended = true;
        locationLockLayer.classList.add('modal-suspended');
        setScrollLock(false);
    }

    function restoreLocationLayerAfterModal() {
        if (!locationLockLayer || !modalLocationSuspended) return;
        locationLockLayer.classList.remove('modal-suspended');
        modalLocationSuspended = false;
    }

    function setRetryLoading(loading) {
        if (!locationLockLayer) return;
        locationLockLayer.classList.toggle('loading', loading);
        if (locationLoading) {
            locationLoading.setAttribute('aria-hidden', loading ? 'false' : 'true');
        }
        if (locationRetryBtn) {
            locationRetryBtn.disabled = loading;
        }
        if (loading) {
            retryLoadingStart = Date.now();
        } else {
            retryLoadingStart = null;
        }
        setLoaderActive(loading);
    }

    function finishRetryLoading() {
        if (!retryLoadingStart) return;
        const elapsed = Date.now() - retryLoadingStart;
        const remaining = Math.max(0, 450 - elapsed);
        setTimeout(() => {
            setRetryLoading(false);
        }, remaining);
    }

    function initLocationLoader() {
        if (loaderInitialized || !loadingWrap || !window.THREE) return;
        loaderInitialized = true;
        let isLight = document.documentElement.getAttribute('data-theme') === 'light';
        const wrapRect = loadingWrap.getBoundingClientRect();
        const canvassize = Math.max(320, Math.min(460, Math.floor(wrapRect.width || 320)));
        const length = 30;
        const radius = 5.6;
        const rotatevalue = 0.035;
        let acceleration = 0;
        let animatestep = 0;
        let toend = false;
        const pi2 = Math.PI * 2;

        const group = new THREE.Group();
        const scene = new THREE.Scene();
        scene.add(group);

        const camera = new THREE.PerspectiveCamera(65, 1, 1, 10000);
        camera.position.z = 150;

        class LoaderCurve extends THREE.Curve {
            getPoint(percent) {
                const x = length * Math.sin(pi2 * percent);
                const y = radius * Math.cos(pi2 * 3 * percent);
                let t = (percent % 0.25) / 0.25;
                t = (percent % 0.25) - (2 * (1 - t) * t * -0.0185 + t * t * 0.25);
                if (Math.floor(percent / 0.25) === 0 || Math.floor(percent / 0.25) === 2) {
                    t *= -1;
                }
                const z = radius * Math.sin(pi2 * 2 * (percent - t));
                return new THREE.Vector3(x, y, z);
            }
        }

        const tubeGeometry = new THREE.TubeGeometry(
            new LoaderCurve(),
            200,
            1.1,
            2,
            true
        );

        let meshColor = isLight ? 0x1d4ed8 : 0xe2e8f0;
        let ringColor = isLight ? 0x3b82f6 : 0xf8fafc;
        let coverColor = isLight ? 0x60a5fa : 0xcbd5f5;
        let shadowColor = isLight ? 0x1e3a8a : 0x0f172a;
        let shadowOpacity = isLight ? 0 : 0.08;

        const meshMaterial = new THREE.MeshBasicMaterial({ color: meshColor });
        const mesh = new THREE.Mesh(
            tubeGeometry,
            meshMaterial
        );
        group.add(mesh);

        const ringCoverMaterial = new THREE.MeshBasicMaterial({ color: coverColor, opacity: 0, transparent: true });
        const ringcover = new THREE.Mesh(
            new THREE.PlaneGeometry(50, 15, 1),
            ringCoverMaterial
        );
        ringcover.position.x = length + 1;
        ringcover.rotation.y = Math.PI / 2;
        group.add(ringcover);

        const ringMaterial = new THREE.MeshBasicMaterial({ color: ringColor, opacity: 0, transparent: true });
        const ring = new THREE.Mesh(
            new THREE.RingGeometry(4.3, 5.55, 32),
            ringMaterial
        );
        ring.position.x = length + 1.1;
        ring.rotation.y = Math.PI / 2;
        group.add(ring);

        const shadowPlanes = [];
        for (let i = 0; i < 10; i++) {
            const shadowMaterial = new THREE.MeshBasicMaterial({ color: shadowColor, transparent: true, opacity: shadowOpacity });
            const plain = new THREE.Mesh(
                new THREE.PlaneGeometry(length * 2 + 1, radius * 3, 1),
                shadowMaterial
            );
            plain.position.z = -2.5 + i * 0.5;
            group.add(plain);
            shadowPlanes.push(shadowMaterial);
        }

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(window.devicePixelRatio || 1);
        renderer.setSize(canvassize, canvassize);
        renderer.setClearColor(0x000000, 0);
        loadingWrap.innerHTML = '';
        loadingWrap.appendChild(renderer.domElement);

        const onResize = () => {
            if (!loadingWrap) return;
            const rect = loadingWrap.getBoundingClientRect();
            const size = Math.max(320, Math.min(460, Math.floor(rect.width || canvassize)));
            renderer.setSize(size, size);
        };

        window.addEventListener('resize', onResize);

        const onThemeChange = () => {
            const nextLight = document.documentElement.getAttribute('data-theme') === 'light';
            if (nextLight === isLight) return;
            isLight = nextLight;
            meshColor = isLight ? 0x1d4ed8 : 0xe2e8f0;
            ringColor = isLight ? 0x3b82f6 : 0xf8fafc;
            coverColor = isLight ? 0x60a5fa : 0xcbd5f5;
            shadowColor = isLight ? 0x1e3a8a : 0x0f172a;
            shadowOpacity = isLight ? 0 : 0.08;
            meshMaterial.color.setHex(meshColor);
            ringMaterial.color.setHex(ringColor);
            ringCoverMaterial.color.setHex(coverColor);
            shadowPlanes.forEach((material) => {
                material.color.setHex(shadowColor);
                material.opacity = shadowOpacity;
                material.needsUpdate = true;
            });
        };

        const themeObserver = new MutationObserver(onThemeChange);
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

        loadingWrap.addEventListener('mousedown', () => { toend = true; });
        loadingWrap.addEventListener('touchstart', () => { toend = true; }, { passive: true });
        loadingWrap.addEventListener('mouseup', () => { toend = false; });
        loadingWrap.addEventListener('touchend', () => { toend = false; }, { passive: true });

        function render() {
            animatestep = Math.max(0, Math.min(240, toend ? animatestep + 1 : animatestep - 4));
            acceleration = easing(animatestep, 0, 1, 240);

            if (acceleration > 0.35) {
                let progress = (acceleration - 0.35) / 0.65;
                group.rotation.y = -Math.PI / 2 * progress;
                group.position.z = 50 * progress;
                progress = Math.max(0, (acceleration - 0.97) / 0.03);
                mesh.material.opacity = 1 - progress;
                ringcover.material.opacity = ring.material.opacity = progress;
                ring.scale.x = ring.scale.y = 0.9 + 0.1 * progress;
            }

            renderer.render(scene, camera);
        }

        function animate() {
            if (loaderActive) {
                mesh.rotation.x += rotatevalue + acceleration;
                render();
            }
            loaderAnimationId = requestAnimationFrame(animate);
        }

        function easing(t, b, c, d) {
            if ((t /= d / 2) < 1) return c / 2 * t * t + b;
            return c / 2 * ((t -= 2) * t * t + 2) + b;
        }

        animate();
    }

    function setLoaderActive(active) {
        loaderActive = active;
        if (active) {
            initLocationLoader();
        }
    }

    function storeLocationState(isLocked, distance) {
        if (!window.sessionStorage) return;
        const signature = buildLocationSignature();
        const payload = {
            studentKey,
            locked: isLocked,
            distance: Number.isFinite(distance) ? distance : null,
            radius: Number.isFinite(schoolRadius) ? schoolRadius : null,
            signature,
            timestamp: Date.now()
        };
        sessionStorage.setItem(locationStateKey, JSON.stringify(payload));
    }

    function loadLocationState() {
        if (!window.sessionStorage) return null;
        const raw = sessionStorage.getItem(locationStateKey);
        if (!raw) return null;
        try {
            const parsed = JSON.parse(raw);
            if (parsed && parsed.studentKey && parsed.studentKey !== studentKey) {
                return null;
            }
            const signature = buildLocationSignature();
            if (parsed && parsed.signature && signature && parsed.signature !== signature) {
                return null;
            }
            return parsed;
        } catch (error) {
            return null;
        }
    }

    function clearLocationState() {
        if (!window.sessionStorage) return;
        sessionStorage.removeItem(locationStateKey);
    }

    function storeLastDistance(distance, accuracy, good, lat = null, lng = null) {
        if (!window.sessionStorage || !Number.isFinite(distance)) return;
        const signature = buildLocationSignature();
        sessionStorage.setItem('face_location_last', JSON.stringify({
            distance,
            accuracy: Number.isFinite(accuracy) ? accuracy : null,
            good: Boolean(good),
            lat: Number.isFinite(lat) ? lat : null,
            lng: Number.isFinite(lng) ? lng : null,
            signature,
            timestamp: Date.now()
        }));
    }

    function loadLastDistance() {
        if (!window.sessionStorage) return null;
        const raw = sessionStorage.getItem('face_location_last');
        if (!raw) return null;
        try {
            const parsed = JSON.parse(raw);
            const signature = buildLocationSignature();
            if (parsed && parsed.signature && signature && parsed.signature !== signature) {
                return null;
            }
            return parsed;
        } catch (error) {
            return null;
        }
    }

    function isRecentDistance(state, requireGood = true) {
        if (!state || !Number.isFinite(state.distance) || !state.timestamp) return false;
        if (requireGood && !state.good) return false;
        return (Date.now() - state.timestamp) <= lastDistanceMaxAgeMs;
    }

    function formatDistance(distance) {
        if (!Number.isFinite(distance)) return '-';
        if (distance >= 1000) {
            return (distance / 1000).toFixed(2) + ' km';
        }
        return Math.round(distance) + ' m';
    }

    function buildLocationSignature() {
        if (!Number.isFinite(schoolLat) || !Number.isFinite(schoolLng) || !Number.isFinite(schoolRadius)) {
            return null;
        }
        const lat = Number(schoolLat).toFixed(6);
        const lng = Number(schoolLng).toFixed(6);
        const radius = Math.round(Number(schoolRadius));
        return `${lat},${lng},${radius}`;
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const earthRadius = 6371000;
        const toRad = value => (value * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return earthRadius * c;
    }

    function getMedian(values) {
        if (!values.length) return 0;
        const sorted = [...values].sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        if (sorted.length % 2) return sorted[mid];
        return (sorted[mid - 1] + sorted[mid]) / 2;
    }

    function updateStartButtonState() {
        if (!startBtn) return;
        const referenceAvailable = !!referenceUrl;
        if (!referenceAvailable) {
            startBtn.disabled = true;
            return;
        }
        if (isMemoryGuardActive()) {
            startBtn.disabled = true;
            return;
        }
        const canStart = !gpsEnabled || locationAllowed || locationOverride;
        startBtn.disabled = !canStart;
    }

    function setLocationLock(locked, message) {
        if (!gpsEnabled) return;
        if (locationOverride && locked) {
            locked = false;
        }
        page.classList.toggle('location-locked', locked);
        if (locationLockLayer) {
            locationLockLayer.classList.toggle('show', locked);
        }
        setScrollLock(locked);
        if (message && locationLockMessage) {
            locationLockMessage.textContent = message;
        }
        if (locked) {
            startBtn.disabled = true;
        } else {
            updateStartButtonState();
            setRetryLoading(false);
        }
    }

    function getAccuracyNote(position) {
        const timestamp = position && typeof position.timestamp === 'number' ? position.timestamp : null;
        const latency = timestamp ? Math.max(0, Math.round(Date.now() - timestamp)) : 0;
        return ` Validasi lokasi akurasi rendah (+${latency}ms)`;
    }

    function canNotifyOutsideRadius() {
        const now = Date.now();
        const lastRaw = sessionStorage.getItem(locationNotifyKey);
        const last = lastRaw ? parseInt(lastRaw, 10) : 0;
        if (last && now - last < locationNotifyCooldownMs) {
            return false;
        }
        sessionStorage.setItem(locationNotifyKey, String(now));
        return true;
    }

    function canNotifyEvent(key, cooldownMs) {
        if (!key || cooldownMs <= 0) {
            return true;
        }
        const now = Date.now();
        const eventKey = `face_event_notify_${studentKey}_${key}`;
        const lastRaw = sessionStorage.getItem(eventKey);
        const last = lastRaw ? parseInt(lastRaw, 10) : 0;
        if (last && (now - last) < cooldownMs) {
            return false;
        }
        sessionStorage.setItem(eventKey, String(now));
        return true;
    }

    async function notifyStudentEvent(title, body, url, eventKey = '', cooldownMs = 0) {
        if (!canNotifyEvent(eventKey, cooldownMs)) return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        if (!('serviceWorker' in navigator)) return;

        try {
            const registration = await navigator.serviceWorker.ready;
            registration.showNotification(title, {
                body,
                icon: '/assets/images/logo-192.png',
                badge: '/assets/images/logo-192.png',
                data: { url: url || '?page=jadwal' }
            });
        } catch (error) {
            // Silent fail for unsupported environments
        }
    }

    async function notifyOutsideRadius(distance, accuracyText) {
        if (!canNotifyOutsideRadius()) return;
        const distanceText = Number.isFinite(distance) ? `${Math.round(distance)} m` : 'di luar radius';
        const extra = accuracyText ? ` (akurasi +/-${accuracyText} m)` : '';
        const body = `Anda berada di luar radius lokasi (${distanceText})${extra}.`;
        await notifyStudentEvent('Lokasi di luar radius', body, '?page=face_recognition', 'outside_radius', locationNotifyCooldownMs);
    }

    function handleLocationSuccess(position) {
        const now = Date.now();
        if (now - lastLocationUpdate < locationThrottleMs) return;
        lastLocationUpdate = now;

        const coords = position.coords || {};
        const latitude = coords.latitude;
        const longitude = coords.longitude;
        const accuracy = Number.isFinite(coords.accuracy) ? coords.accuracy : null;

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

        const distance = calculateDistance(latitude, longitude, schoolLat, schoolLng);
        locationVerified = false;
        updateLocationSnapshot(latitude, longitude, accuracy, distance, position.timestamp);

        if (locationOverride) {
            locationAllowed = true;
            locationVerified = isWithinRadius(distance, accuracy);
            lastDistance = distance;
            storeLastDistance(distance, accuracy, locationVerified, latitude, longitude);
            if (locationDistanceText) {
                locationDistanceText.textContent = formatDistance(distance);
            }
            if (locationRadiusText) {
                locationRadiusText.textContent = formatDistance(schoolRadius);
            }
            if (locationAddressText) {
                locationAddressText.textContent = schoolAddress
                    ? `${schoolName} - ${schoolAddress}`
                    : schoolName;
            }
            setLocationLock(false);
            setCameraStatus(`Mode ${getModeLabel(absenceMode)} aktif`);
            if (!stream) {
                setStatus(`Mode ${getModeLabel(absenceMode)} aktif. Anda bisa melanjutkan verifikasi wajah.`);
            }
            updateStartButtonState();
            finishRetryLoading();
            return;
        }

        if (accuracy && accuracy > accuracyExtremeLimit) {
            locationReady = true;
            const accuracyText = Math.round(accuracy);
            const message = `Akurasi GPS sangat rendah (+/-${accuracyText} m). Menunggu sinyal lebih presisi...`;
            const lastDistanceState = loadLastDistance();

            if (isRecentDistance(lastDistanceState, true) && lastDistanceState.distance <= schoolRadius) {
                locationAllowed = true;
                locationVerified = isWithinRadius(lastDistanceState.distance, lastDistanceState.accuracy);
                hasValidLocation = true;
                stableWithinCount = stableWithinThreshold;
                stableOutsideCount = 0;
                lastDistance = lastDistanceState.distance;
                updateLocationSnapshot(
                    lastDistanceState.lat ?? latitude,
                    lastDistanceState.lng ?? longitude,
                    lastDistanceState.accuracy ?? accuracy,
                    lastDistanceState.distance,
                    lastDistanceState.timestamp
                );
                if (locationDistanceText) {
                    locationDistanceText.textContent = formatDistance(lastDistanceState.distance);
                }
                if (locationRadiusText) {
                    locationRadiusText.textContent = formatDistance(schoolRadius);
                }
                if (locationAddressText) {
                    locationAddressText.textContent = schoolAddress
                        ? `${schoolName} - ${schoolAddress}`
                        : schoolName;
                }
                setLocationLock(false);
                setCameraStatus('Lokasi valid (lokasi terakhir)');
                setStatus(`${message} Menggunakan lokasi terakhir yang tervalidasi.`);
                updateStartButtonState();
                finishRetryLoading();
                return;
            }

            locationAllowed = false;
            locationVerified = false;
            setLocationLock(true, message);
            setCameraStatus('Akurasi sangat rendah');
            setStatus(message);
            updateStartButtonState();
            finishRetryLoading();
            return;
        }

        if (accuracy && accuracy > accuracyLimit) {
            locationReady = true;
            const accuracyText = Math.round(accuracy);
            const accuracyNote = getAccuracyNote(position);
            const message = `Akurasi GPS rendah (+/-${accuracyText} m). Menunggu sinyal lebih presisi...`;
            const withinRadius = distance <= schoolRadius;
            const accuracyBuffer = Math.min(accuracy, Math.max(50, schoolRadius * 1.5));
            const withinRadiusWithBuffer = distance <= (schoolRadius + accuracyBuffer);

            if (withinRadius && accuracy <= accuracySoftLimit) {
                locationAllowed = true;
                locationVerified = isWithinRadius(distance, accuracy);
                hasValidLocation = true;
                stableWithinCount = stableWithinThreshold;
                stableOutsideCount = 0;

                lastDistance = distance;
                storeLastDistance(distance, accuracy, true, latitude, longitude);
                if (locationDistanceText) {
                    locationDistanceText.textContent = formatDistance(distance);
                }
                if (locationRadiusText) {
                    locationRadiusText.textContent = formatDistance(schoolRadius);
                }
                if (locationAddressText) {
                    locationAddressText.textContent = schoolAddress
                        ? `${schoolName} - ${schoolAddress}`
                        : schoolName;
                }

                setLocationLock(false);
                setCameraStatus('Lokasi valid (akurasi rendah)');
                setStatus(`Akurasi GPS rendah (+/-${accuracyText} m), tetapi jarak sudah di dalam radius. ${accuracyNote}`);
                updateStartButtonState();
                finishRetryLoading();
                return;
            }

            if (!withinRadiusWithBuffer) {
                locationAllowed = false;
                locationVerified = false;
                hasValidLocation = true;
                stableWithinCount = 0;
                stableOutsideCount = stableOutsideThreshold;
                lastDistance = distance;
                storeLastDistance(distance, accuracy, false, latitude, longitude);
                if (locationDistanceText) {
                    locationDistanceText.textContent = formatDistance(distance);
                }
                if (locationRadiusText) {
                    locationRadiusText.textContent = formatDistance(schoolRadius);
                }
                if (locationAddressText) {
                    locationAddressText.textContent = schoolAddress
                        ? `${schoolName} - ${schoolAddress}`
                        : schoolName;
                }
                setLocationLock(true, `Di luar radius lokasi (akurasi rendah +/-${accuracyText} m).`);
                setCameraStatus('Di luar radius');
                setStatus(`Di luar radius lokasi. ${accuracyNote}`);
                notifyOutsideRadius(distance, accuracyText);
                updateStartButtonState();
                finishRetryLoading();
                return;
            }

            const lastDistanceState = loadLastDistance();
            if (isRecentDistance(lastDistanceState, true) && lastDistanceState.distance <= schoolRadius) {
                locationAllowed = true;
                locationVerified = isWithinRadius(lastDistanceState.distance, lastDistanceState.accuracy);
                hasValidLocation = true;
                stableWithinCount = stableWithinThreshold;
                stableOutsideCount = 0;

                lastDistance = lastDistanceState.distance;
                updateLocationSnapshot(
                    lastDistanceState.lat ?? latitude,
                    lastDistanceState.lng ?? longitude,
                    lastDistanceState.accuracy ?? accuracy,
                    lastDistanceState.distance,
                    lastDistanceState.timestamp
                );
                if (locationDistanceText) {
                    locationDistanceText.textContent = formatDistance(lastDistanceState.distance);
                }
                if (locationRadiusText) {
                    locationRadiusText.textContent = formatDistance(schoolRadius);
                }
                if (locationAddressText) {
                    locationAddressText.textContent = schoolAddress
                        ? `${schoolName} - ${schoolAddress}`
                        : schoolName;
                }

                setLocationLock(false);
                setCameraStatus('Lokasi valid (lokasi terakhir)');
                setStatus(`Akurasi GPS rendah, menggunakan lokasi terakhir yang sudah tervalidasi. ${accuracyNote}`);
                updateStartButtonState();
                finishRetryLoading();
                return;
            }

            if (hasValidLocation && locationAllowed) {
                locationVerified = isWithinRadius(lastDistance, accuracy);
                setCameraStatus('Akurasi rendah');
                setStatus(`${message} Menggunakan lokasi terakhir yang sudah tervalidasi.${accuracyNote}`);
                finishRetryLoading();
                return;
            }

            locationAllowed = false;
            locationVerified = false;
            if (locationDistanceText && Number.isFinite(distance)) {
                locationDistanceText.textContent = formatDistance(distance);
            }
            if (locationRadiusText) {
                locationRadiusText.textContent = formatDistance(schoolRadius);
            }
            if (locationAddressText) {
                locationAddressText.textContent = schoolAddress
                    ? `${schoolName} - ${schoolAddress}`
                    : schoolName;
            }

            setLocationLock(true, `${message}${accuracyNote}`);
            setCameraStatus('Akurasi rendah');
            setStatus(`${message}${accuracyNote}`);
            updateStartButtonState();
            finishRetryLoading();
            return;
        }

        if (Number.isFinite(lastDistance) && Math.abs(distance - lastDistance) > 2000) {
            const message = 'Lokasi berubah drastis. Menstabilkan ulang...';
            setLocationLock(true, message);
            setCameraStatus('Menstabilkan lokasi');
            setStatus(message);
            finishRetryLoading();
            return;
        }
        distanceSamples.push(distance);
        if (distanceSamples.length > locationSampleWindow) {
            distanceSamples.shift();
        }
        const smoothedDistance = getMedian(distanceSamples);
        lastDistance = smoothedDistance;
        updateLocationSnapshot(latitude, longitude, accuracy, smoothedDistance, position.timestamp);
        const isAccuracyGood = Number.isFinite(accuracy) ? accuracy <= accuracyLimit : false;
        storeLastDistance(smoothedDistance, accuracy, isAccuracyGood, latitude, longitude);
        locationReady = true;
        const withinRadius = smoothedDistance <= schoolRadius;
        if (withinRadius) {
            stableWithinCount += 1;
            stableOutsideCount = 0;
        } else {
            stableOutsideCount += 1;
            stableWithinCount = 0;
        }

        if (stableWithinCount >= stableWithinThreshold) {
            locationAllowed = true;
        } else if (stableOutsideCount >= stableOutsideThreshold) {
            locationAllowed = false;
        }
        locationVerified = locationAllowed && isWithinRadius(smoothedDistance, accuracy);
        hasValidLocation = true;

        if (locationDistanceText) {
            locationDistanceText.textContent = formatDistance(smoothedDistance);
        }
        if (locationRadiusText) {
            locationRadiusText.textContent = formatDistance(schoolRadius);
        }
        if (locationAddressText) {
            locationAddressText.textContent = schoolAddress
                ? `${schoolName} - ${schoolAddress}`
                : schoolName;
        }

        if (!locationAllowed && stableOutsideCount < stableOutsideThreshold) {
            setLocationLock(true, 'Menstabilkan lokasi...');
            setCameraStatus('Menstabilkan lokasi');
            setStatus('Menstabilkan lokasi sebelum verifikasi.');
            locationVerified = false;
            updateStartButtonState();
            finishRetryLoading();
            return;
        }

        if (locationAllowed) {
            setLocationLock(false);
            clearLocationState();
            setCameraStatus(`Lokasi valid (${formatDistance(smoothedDistance)})`);
            if (!stream) {
                setStatus('Lokasi valid. Anda bisa mengaktifkan kamera.');
            }
            locationVerified = isWithinRadius(smoothedDistance, accuracy);
        } else {
            setLocationLock(true, 'Di luar radius lokasi. Dekati titik absensi untuk membuka kamera.');
            storeLocationState(true, smoothedDistance);
            setCameraStatus('Di luar radius');
            setStatus('Lokasi di luar radius. Absensi terkunci sampai berada di area sekolah.');
            notifyOutsideRadius(smoothedDistance, null);
            if (stream) {
                stopCamera();
            }
            captureBtn.disabled = true;
            retryBtn.disabled = true;
            proceedBtn.disabled = true;
            previewWrap.classList.remove('show');
            locationVerified = false;
        }

        updateStartButtonState();
        finishRetryLoading();
    }

    function handleLocationError(error) {
        if (locationOverride) {
            locationAllowed = true;
            locationReady = true;
            locationVerified = false;
            setLocationLock(false);
            setCameraStatus(`Mode ${getModeLabel(absenceMode)} aktif`);
            if (!stream) {
                setStatus(`Mode ${getModeLabel(absenceMode)} aktif. Kamera tetap bisa digunakan tanpa validasi radius.`);
            }
            updateStartButtonState();
            finishRetryLoading();
            return;
        }

        if (hasValidLocation && locationAllowed) {
            setCameraStatus('Lokasi tidak stabil');
            setStatus('Lokasi sempat tidak terbaca. Memperbarui...');
            finishRetryLoading();
            return;
        }

        locationAllowed = false;
        locationReady = false;
        locationVerified = false;

        let message = 'Gagal mendapatkan lokasi. Pastikan GPS aktif.';
        if (error && error.code === 1) {
            message = 'Izin lokasi ditolak. Aktifkan GPS untuk melanjutkan.';
        } else if (error && error.code === 2) {
            message = 'Lokasi tidak tersedia. Coba lagi atau pindah ke area terbuka.';
        } else if (error && error.code === 3) {
            message = 'Permintaan lokasi timeout. Coba lagi.';
        }

        setLocationLock(true, message);
        storeLocationState(true, lastDistance);
        setCameraStatus('Lokasi gagal');
        setStatus(message);
        updateStartButtonState();
        finishRetryLoading();
    }

    function startLocationWatch() {
        if (!gpsEnabled) {
            locationAllowed = true;
            locationReady = true;
            updateStartButtonState();
            setScrollLock(false);
            return;
        }

        if (locationOverride) {
            locationAllowed = true;
            locationReady = true;
            setLocationLock(false);
            setCameraStatus(`Mode ${getModeLabel(absenceMode)} aktif`);
            if (!stream) {
                setStatus(`Mode ${getModeLabel(absenceMode)} aktif. Kamera dapat langsung digunakan.`);
            }
            updateStartButtonState();

            if (navigator.geolocation) {
                if (locationWatchId !== null) {
                    navigator.geolocation.clearWatch(locationWatchId);
                    locationWatchId = null;
                }
                const relaxedOptions = {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 5000
                };
                navigator.geolocation.getCurrentPosition(
                    handleLocationSuccess,
                    () => {},
                    relaxedOptions
                );
                locationWatchId = navigator.geolocation.watchPosition(
                    handleLocationSuccess,
                    () => {},
                    relaxedOptions
                );
            }

            finishRetryLoading();
            return;
        }


        if (!Number.isFinite(schoolLat) || !Number.isFinite(schoolLng) || !Number.isFinite(schoolRadius)) {
            setLocationLock(true, 'Lokasi sekolah belum dikonfigurasi.');
            setCameraStatus('Lokasi tidak tersedia');
            setStatus('Lokasi sekolah belum dikonfigurasi. Hubungi admin.');
            updateStartButtonState();
            finishRetryLoading();
            return;
        }

        if (!navigator.geolocation) {
            setLocationLock(true, 'Browser tidak mendukung geolocation.');
            setCameraStatus('GPS tidak didukung');
            setStatus('Browser tidak mendukung GPS. Gunakan perangkat lain.');
            updateStartButtonState();
            finishRetryLoading();
            return;
        }

        if (locationRadiusText) {
            locationRadiusText.textContent = formatDistance(schoolRadius);
        }
        if (locationAddressText) {
            locationAddressText.textContent = schoolAddress
                ? `${schoolName} - ${schoolAddress}`
                : schoolName;
        }

        setLocationLock(true, 'Meminta izin lokasi untuk melanjutkan...');
        setCameraStatus('Menunggu lokasi');
        setStatus('Sedang memeriksa lokasi Anda...');

        if (locationWatchId !== null) {
            navigator.geolocation.clearWatch(locationWatchId);
            locationWatchId = null;
        }

        const geoOptions = {
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 2000
        };

        navigator.geolocation.getCurrentPosition(
            handleLocationSuccess,
            handleLocationError,
            geoOptions
        );

        locationWatchId = navigator.geolocation.watchPosition(
            handleLocationSuccess,
            handleLocationError,
            geoOptions
        );
    }

    function stopLocationWatch() {
        if (locationWatchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(locationWatchId);
            locationWatchId = null;
        }
    }

    function descriptorDistanceToSimilarity(distance) {
        if (!Number.isFinite(distance)) return 0;
        const bestDistance = 0.24;
        const maxDistance = descriptorMaxDistance;
        const clamped = Math.max(bestDistance, Math.min(maxDistance, distance));
        const normalized = (clamped - bestDistance) / (maxDistance - bestDistance);
        return Math.max(0, Math.min(100, (1 - normalized) * 100));
    }

    function evaluateDescriptor(detection) {
        if (!descriptorModelReady || !referenceDescriptor || !detection || !detection.descriptor) {
            return null;
        }

        const distance = faceapi.euclideanDistance(referenceDescriptor, detection.descriptor);
        const similarity = descriptorDistanceToSimilarity(distance);
        return {
            distance,
            similarity,
            passed: distance <= descriptorDistanceThreshold,
            strong: distance <= descriptorStrongThreshold
        };
    }

    function resolveModelUrl(file) {
        const cleanBase = String(modelBase || '').replace(/\/+$/, '');
        const cleanFile = String(file || '').replace(/^\/+/, '');
        return `${cleanBase}/${cleanFile}`;
    }

    function warmReferenceAsset() {
        if (!referenceUrl || referencePrefetchStarted) {
            return;
        }
        referencePrefetchStarted = true;
        fetch(referenceUrl, { credentials: 'same-origin' }).catch(() => {});
    }

    function warmFaceAssetsInBackground() {
        if (modelWarmupStarted) {
            return;
        }
        modelWarmupStarted = true;

        const requests = faceModelFiles.map((file) =>
            fetch(resolveModelUrl(file), { credentials: 'same-origin' }).catch(() => null)
        );
        if (referenceUrl) {
            requests.push(fetch(referenceUrl, { credentials: 'same-origin' }).catch(() => null));
        }
        Promise.allSettled(requests).catch(() => {});
    }

    function warmFacePipeline() {
        const run = async () => {
            warmFaceAssetsInBackground();
            warmReferenceAsset();

            try {
                await loadModels({ silent: true });
                await loadReferenceDescriptor();
            } catch (error) {
                // Warmup should not block interaction flow.
            }
        };

        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(() => { run(); }, { timeout: 2000 });
            return;
        }

        setTimeout(run, 450);
    }

    async function loadModels(options = {}) {
        if (modelsReady) return;
        if (modelLoadPromise) return modelLoadPromise;

        const silent = options && options.silent === true;
        if (!silent) {
            setCameraStatus('Memuat model...');
        }

        modelLoadPromise = (async () => {
            try {
                try {
                    await faceapi.nets.ssdMobilenetv1.loadFromUri(modelBase);
                    detectorType = 'ssd';
                } catch (error) {
                    detectorType = 'tiny';
                    await faceapi.nets.tinyFaceDetector.loadFromUri(modelBase);
                }

                await Promise.all([
                    faceapi.nets.faceLandmark68Net.loadFromUri(modelBase),
                    faceapi.nets.faceRecognitionNet
                        .loadFromUri(modelBase)
                        .then(() => {
                            descriptorModelReady = true;
                        })
                        .catch(() => {
                            descriptorModelReady = false;
                        })
                ]);

                modelsReady = true;
                if (!silent) {
                    setCameraStatus('Model siap');
                }
            } catch (error) {
                modelLoadPromise = null;
                throw error;
            }
        })();

        return modelLoadPromise;
    }

    async function loadReferenceDescriptor() {
        if (!referenceUrl) {
            throw new Error('Foto referensi tidak tersedia');
        }
        if (referenceDescriptor) return referenceDescriptor;
        if (referenceDescriptorPromise) return referenceDescriptorPromise;

        referenceDescriptorPromise = (async () => {
            const refImg = await faceapi.fetchImage(referenceUrl);
            let detectionTask = faceapi
                .detectSingleFace(refImg, getDetectorOptions())
                .withFaceLandmarks();
            if (descriptorModelReady) {
                detectionTask = detectionTask.withFaceDescriptor();
            }

            const detection = await detectionTask;
            if (!detection) {
                throw new Error('Wajah pada foto referensi tidak terdeteksi');
            }

            if (descriptorModelReady) {
                if (!detection.descriptor) {
                    throw new Error('Model descriptor tidak siap. Muat ulang halaman.');
                }
                referenceDescriptor = detection.descriptor;
            } else {
                referenceDescriptor = null;
            }

            referenceReady = true;
            return detection;
        })();

        try {
            return await referenceDescriptorPromise;
        } catch (error) {
            referenceDescriptorPromise = null;
            throw error;
        }
    }

    async function startCamera(deviceId = null) {
        if (stream) {
            stopCamera();
        }

        const baseVideo = { width: { ideal: 640 }, height: { ideal: 480 } };
        const exactConstraints = deviceId
            ? { video: { ...baseVideo, deviceId: { exact: deviceId } }, audio: false }
            : { video: { ...baseVideo, facingMode: 'user' }, audio: false };

        try {
            stream = await navigator.mediaDevices.getUserMedia(exactConstraints);
        } catch (error) {
            if (deviceId) {
                try {
                    const idealConstraints = { video: { ...baseVideo, deviceId: { ideal: deviceId } }, audio: false };
                    stream = await navigator.mediaDevices.getUserMedia(idealConstraints);
                } catch (fallbackError) {
                    const fallbackConstraints = { video: { ...baseVideo, facingMode: 'user' }, audio: false };
                    stream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
                }
            } else {
                throw error;
            }
        }

        video.srcObject = stream;
        await video.play();

        const settings = stream.getVideoTracks()[0]?.getSettings();
        if (settings?.deviceId) {
            currentDeviceId = settings.deviceId;
            lastWorkingDeviceId = settings.deviceId;
        }
        await loadCameraDevices();
    }

    function stopCamera() {
        stopPoseMonitor();
        poseStarted = false;
        if (!stream) return;
        stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        refreshPoseButtons();
        updateCaptureButtonByPose();
    }

    function resetState() {
        setBadge('waiting', 'Menunggu');
        setStatus('Mulai kamera untuk memuat model dan mempersiapkan verifikasi.');
        setSimilarity(0);
        lastFaceDistance = null;
        lastDescriptorSimilarity = 0;
        lastServerMatchToken = '';
        matchPassed = false;
        attendanceDone = false;
        proceedBtn.disabled = true;
        captureBtn.disabled = true;
        retryBtn.disabled = true;
        previewWrap.classList.remove('show');
        previewImg.src = '';
        resetPoseValidation({ keepStatus: true });
        updateStartButtonState();
    }

    async function loadCameraDevices() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            cameraSelect.innerHTML = '<option value="">Kamera tidak tersedia</option>';
            cameraSelect.disabled = true;
            return;
        }
        const devices = await navigator.mediaDevices.enumerateDevices();
        const cameras = devices.filter(device => device.kind === 'videoinput');

        cameraSelect.innerHTML = '';
        if (cameras.length === 0) {
            cameraSelect.innerHTML = '<option value="">Kamera tidak ditemukan</option>';
            cameraSelect.disabled = true;
            return;
        }

        cameras.forEach((camera, index) => {
            const option = document.createElement('option');
            option.value = camera.deviceId;
            option.textContent = camera.label || `Kamera ${index + 1}`;
            cameraSelect.appendChild(option);
        });

        cameraSelect.disabled = false;

        if (currentDeviceId && cameras.some(cam => cam.deviceId === currentDeviceId)) {
            cameraSelect.value = currentDeviceId;
        } else if (cameraSelect.options.length) {
            cameraSelect.selectedIndex = 0;
        }
    }

    async function switchCamera(deviceId) {
        if (!deviceId) return;
        stopCamera();
        resetPoseValidation();
        setCameraStatus('Mengganti kamera...');
        try {
            await startCamera(deviceId);
            setCameraStatus('Kamera aktif');
            resetPoseValidation();
            setStatus('Kamera aktif. Klik konfirmasi pose untuk memulai pengambilan frame otomatis.');
        } catch (error) {
            setCameraStatus('Gagal');
            setStatus('Tidak dapat mengakses kamera yang dipilih.');
            if (lastWorkingDeviceId) {
                try {
                    await startCamera(lastWorkingDeviceId);
                    setCameraStatus('Kamera aktif');
                } catch (fallbackError) {
                    setCameraStatus('Error');
                }
            }
        }
    }

    function buildServerCaptureData(sourceCanvas) {
        if (!sourceCanvas || !sourceCanvas.width || !sourceCanvas.height) {
            return sourceCanvas ? sourceCanvas.toDataURL('image/jpeg', 0.85) : '';
        }

        const captureConfig = getCapturePipelineConfig();
        const maxSide = captureConfig.maxSide;
        const srcW = sourceCanvas.width;
        const srcH = sourceCanvas.height;
        const longestSide = Math.max(srcW, srcH);
        const scale = longestSide > maxSide ? (maxSide / longestSide) : 1;

        if (scale >= 1) {
            return sourceCanvas.toDataURL('image/jpeg', captureConfig.jpegQuality);
        }

        const resized = document.createElement('canvas');
        resized.width = Math.max(1, Math.round(srcW * scale));
        resized.height = Math.max(1, Math.round(srcH * scale));
        const resizedCtx = resized.getContext('2d');
        resizedCtx.drawImage(sourceCanvas, 0, 0, resized.width, resized.height);
        return resized.toDataURL('image/jpeg', captureConfig.jpegQuality);
    }

    async function requestServerMatch(imageData) {
        const payload = new URLSearchParams();
        payload.append('captured_image', imageData);

        const response = await fetch('../api/face_matching.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: payload
        });

        if (!response.ok) {
            throw new Error('Gagal memproses verifikasi di server.');
        }

        const data = await response.json();
        if (!data || !data.success) {
            throw new Error(data?.message || 'Verifikasi wajah gagal.');
        }

        return data;
    }

    function drawFaceLabel(box, label) {
        if (!box) return;
        const ctx = canvas.getContext('2d');
        ctx.lineWidth = 3;
        ctx.strokeStyle = '#ef4444';
        ctx.strokeRect(box.x, box.y, box.width, box.height);

        if (!label) return;
        const text = String(label);
        ctx.font = '16px Sora, sans-serif';
        const paddingX = 10;
        const paddingY = 6;
        const textWidth = ctx.measureText(text).width;
        const boxHeight = 24;
        const startX = box.x;
        const startY = Math.max(0, box.y - boxHeight);

        ctx.fillStyle = '#ef4444';
        ctx.fillRect(startX, startY, textWidth + paddingX * 2, boxHeight);
        ctx.fillStyle = '#fff';
        ctx.fillText(text, startX + paddingX, startY + boxHeight - paddingY);
    }

    const qualityCanvas = document.createElement('canvas');
    qualityCanvas.width = 160;
    qualityCanvas.height = 120;
    const qualityCtx = qualityCanvas.getContext('2d', { willReadFrequently: true });

    function analyzeCaptureQuality(sourceCanvas) {
        const w = qualityCanvas.width;
        const h = qualityCanvas.height;
        qualityCtx.drawImage(sourceCanvas, 0, 0, w, h);
        const img = qualityCtx.getImageData(0, 0, w, h).data;

        const total = w * h;
        const gray = new Float32Array(total);
        let sum = 0;
        let sumSq = 0;
        let idx = 0;
        for (let i = 0; i < img.length; i += 4) {
            const g = 0.299 * img[i] + 0.587 * img[i + 1] + 0.114 * img[i + 2];
            gray[idx++] = g;
            sum += g;
            sumSq += g * g;
        }

        const mean = sum / total;
        const variance = Math.max(0, sumSq / total - mean * mean);
        const std = Math.sqrt(variance);

        let lapSum = 0;
        let count = 0;
        for (let y = 1; y < h - 1; y++) {
            const row = y * w;
            for (let x = 1; x < w - 1; x++) {
                const center = gray[row + x];
                const lap = -4 * center
                    + gray[row + x - 1]
                    + gray[row + x + 1]
                    + gray[row - w + x]
                    + gray[row + w + x];
                lapSum += lap * lap;
                count++;
            }
        }
        const lapVar = count ? (lapSum / count) : 0;

        const lowLight = mean < 70;
        const lowContrast = std < 30;
        const blurry = lapVar < 120;
        const foggy = lowContrast && lapVar < 120 && mean > 90;

        const issues = [];
        if (foggy) issues.push('Kamera berembun/terhalang');
        if (blurry) issues.push('Gambar blur/kabur');
        if (lowLight) issues.push('Pencahayaan kurang');

        return {
            mean,
            std,
            lapVar,
            issues
        };
    }

    function buildQualityMessage(quality) {
        if (!quality || !quality.issues || !quality.issues.length) {
            return 'Wajah tidak terdeteksi. Coba atur posisi dan pencahayaan.';
        }
        return `Wajah tidak terdeteksi. ${quality.issues.join('. ')}.`;
    }

    async function performMatch(capturedData) {
        const canProcess = await waitForMemoryResponsive(8000);
        if (!canProcess) {
            setBadge('warning', 'Menahan');
            setStatus('RAM/CPU masih tinggi. Verifikasi ditunda sementara, coba lagi beberapa detik.');
            return false;
        }
        const memoryProfile = getMemoryProfile();
        if (memoryProfile === 'throttle') {
            await new Promise((resolve) => setTimeout(resolve, 220));
        }
        if (!modelsReady) {
            await loadModels();
        }
        if (!referenceReady) {
            try {
                await loadReferenceDescriptor();
            } catch (error) {
                setBadge('error', 'Gagal');
                setStatus(error.message || 'Foto referensi tidak dapat digunakan.');
                return false;
            }
        }

        let detectionTask = faceapi
            .detectSingleFace(canvas, getDetectorOptions())
            .withFaceLandmarks();
        if (descriptorModelReady) {
            detectionTask = detectionTask.withFaceDescriptor();
        }
        const detection = await detectionTask;

        if (!detection) {
            const quality = analyzeCaptureQuality(canvas);
            setBadge('error', 'Gagal');
            setStatus(buildQualityMessage(quality));
            setSimilarity(0);
            lastFaceDistance = null;
            lastDescriptorSimilarity = 0;
            lastServerMatchToken = '';
            return false;
        }

        const displaySize = { width: canvas.width, height: canvas.height };
        const resized = faceapi.resizeResults(detection, displaySize);
        faceapi.draw.drawFaceLandmarks(canvas, resized);
        drawFaceLabel(resized.detection.box, '');
        previewImg.src = canvas.toDataURL('image/jpeg', 0.9);

        let serverResult;
        try {
            const serverPayloadImage = buildServerCaptureData(canvas);
            serverResult = await requestServerMatch(serverPayloadImage || capturedData);
        } catch (error) {
            setBadge('error', 'Gagal');
            setStatus(error.message || 'Gagal memproses verifikasi.');
            setSimilarity(0);
            lastFaceDistance = null;
            lastDescriptorSimilarity = 0;
            lastServerMatchToken = '';
            notifyStudentEvent(
                'Error Verifikasi Wajah',
                error && error.message ? error.message : 'Terjadi kendala saat memproses verifikasi wajah.',
                '?page=face_recognition',
                'system_error_face',
                45000
            );
            return false;
        }
        lastServerMatchToken = serverResult?.match_token ? String(serverResult.match_token) : '';

        const thresholdValue = parseFloat(serverResult.threshold || threshold);
        const serverSimilarity = parseFloat(serverResult.similarity || 0);
        const localDescriptor = evaluateDescriptor(detection);
        const deepfacePassed = !!(serverResult.passed && serverSimilarity >= thresholdValue);
        let passed = deepfacePassed;

        if (localDescriptor) {
            lastFaceDistance = localDescriptor.distance;
            lastDescriptorSimilarity = localDescriptor.similarity;

            // Local descriptor is only a guardrail, not the main decision.
            // DeepFace server remains the primary source of truth.
            if (deepfacePassed && localDescriptor.distance >= descriptorHardFailDistance) {
                passed = false;
            }
        } else {
            lastFaceDistance = null;
            lastDescriptorSimilarity = 0;
        }

        setSimilarity(serverSimilarity);

        if (passed) {
            setBadge('success', 'Lolos');
            if (localDescriptor) {
                if (!localDescriptor.passed) {
                    setStatus('Verifikasi berhasil (DeepFace server). Anda bisa lanjut ke absensi.');
                } else {
                    setStatus('Verifikasi berhasil (DeepFace + descriptor). Anda bisa lanjut ke absensi.');
                }
            } else {
                setStatus('Verifikasi berhasil (DeepFace server). Anda bisa lanjut ke absensi.');
            }
            drawFaceLabel(resized.detection.box, faceLabel);
            notifyStudentEvent(
                'Verifikasi Wajah Berhasil',
                `DeepFace berhasil verifikasi wajah dengan skor ${serverSimilarity.toFixed(2)}%.`,
                '?page=face_recognition',
                'deepface_verified',
                20000
            );
            matchPassed = true;
            if (!studentScheduleId) {
                setStatus('Verifikasi berhasil, tetapi jadwal belum dipilih. Buka menu Jadwal lalu klik Absen.');
                proceedBtn.disabled = true;
            } else {
                proceedBtn.disabled = false;
            }
            return true;
        }

        setBadge('error', 'Gagal');
        const quality = analyzeCaptureQuality(canvas);
        const extraHint = quality.issues.length ? ` ${quality.issues.join('. ')}.` : '';
        if (deepfacePassed && localDescriptor && localDescriptor.distance >= descriptorHardFailDistance) {
            const current = localDescriptor.distance.toFixed(3);
            setStatus(`Validasi lokal menemukan jarak descriptor terlalu jauh (${current}). Silakan ulangi dengan posisi wajah lurus.${extraHint}`);
        } else if (localDescriptor && !localDescriptor.passed && serverSimilarity >= Math.max(45, thresholdValue - 30)) {
            const limit = descriptorDistanceThreshold.toFixed(2);
            const current = localDescriptor.distance.toFixed(3);
            setStatus(`Similarity server belum stabil. Jarak descriptor ${current} (batas panduan ${limit}). Silakan ulangi dengan pencahayaan lebih merata.${extraHint}`);
        } else {
            setStatus(`Similarity server di bawah batas validasi. Silakan ulangi pengambilan foto.${extraHint}`);
        }
        proceedBtn.disabled = true;
        matchPassed = false;
        lastServerMatchToken = '';
        lastDescriptorSimilarity = localDescriptor ? localDescriptor.similarity : 0;
        return false;
    }

    startBtn.addEventListener('click', async function() {
        if (!referenceUrl) {
            setBadge('error', 'Gagal');
            setStatus('Foto referensi belum tersedia.');
            return;
        }
        if (gpsEnabled && absenceMode === 'hadir' && !locationAllowed) {
            const msg = locationReady
                ? 'Anda berada di luar radius. Kamera tidak dapat diaktifkan.'
                : 'Menunggu lokasi. Aktifkan GPS untuk melanjutkan.';
            setLocationLock(true, msg);
            setStatus(msg);
            return;
        }
        if (isMemoryGuardActive()) {
            startBtn.disabled = true;
            setBadge('warning', 'Menahan');
            setStatus('Perangkat sedang sibuk. Menunggu RAM/CPU stabil sebelum menyalakan kamera...');
            const ready = await waitForMemoryResponsive(7000);
            if (!ready) {
                setBadge('warning', 'Tertunda');
                setStatus('Perangkat masih sibuk. Coba aktifkan kamera lagi beberapa saat.');
                updateStartButtonState();
                return;
            }
        }
        startBtn.disabled = true;
        setBadge('loading', 'Memuat');
        setStatus('Memuat model dan menyiapkan kamera...');

        try {
            await loadModels();
            await startCamera();
            retryBtn.disabled = false;
            setBadge('ready', 'Siap');
            setStatus('Kamera aktif. Konfirmasi siap untuk memulai validasi pose otomatis.');
            setCameraStatus('Kamera aktif');
            resetPoseValidation();
            startMemoryMonitor();

            try {
                await loadReferenceDescriptor();
                resetPoseValidation();
                setBadge('ready', 'Siap');
                setStatus('Kamera aktif. Klik konfirmasi pose lalu menoleh kanan dan kiri.');
                setPoseBadge('ready', 'Siap');
                setPoseInstruction('Klik <strong>Konfirmasi Siap & Mulai Otomatis</strong>, lalu menoleh kanan dan kiri. Sistem akan capture frame otomatis.');
                refreshPoseButtons();
                updateCaptureButtonByPose();
            } catch (refError) {
                referenceReady = false;
                updateCaptureButtonByPose();
                setBadge('error', 'Gagal');
                setStatus(refError.message || 'Foto referensi tidak dapat digunakan.');
            }
        } catch (error) {
            setBadge('error', 'Gagal');
            setStatus(error.message || 'Gagal memuat model wajah.');
            startBtn.disabled = false;
            setCameraStatus('Error');
        }
    });

    captureBtn.addEventListener('click', async function() {
        if (!stream) return;
        if (poseFlowEnabled && !poseCompleted) {
            setBadge('warning', 'Pose Diperlukan');
            setStatus('Selesaikan validasi pose otomatis terlebih dahulu.');
            return;
        }
        if (isMemoryGuardActive()) {
            setBadge('warning', 'Menahan');
            setStatus('Perangkat sedang sibuk. Menunggu memori stabil sebelum verifikasi...');
            const ready = await waitForMemoryResponsive(8000);
            if (!ready) {
                setBadge('warning', 'Tertunda');
                setStatus('RAM/CPU masih tinggi. Tunggu sebentar lalu coba lagi.');
                updateCaptureButtonByPose();
                return;
            }
        }
        captureBtn.disabled = true;
        setBadge('loading', 'Memeriksa');
        setStatus('Sedang mencocokkan wajah...');

        if (!video.videoWidth || !video.videoHeight) {
            setBadge('error', 'Gagal');
            setStatus('Kamera belum siap. Tunggu beberapa detik lalu coba lagi.');
            updateCaptureButtonByPose();
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.save();
        ctx.scale(-1, 1);
        ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
        ctx.restore();
        const captureConfig = getCapturePipelineConfig();
        const capturedData = canvas.toDataURL('image/jpeg', captureConfig.jpegQuality);
        previewImg.src = capturedData;
        previewWrap.classList.add('show');
        lastCapturedData = capturedData;

        const result = await performMatch(capturedData);
        updateCaptureButtonByPose();
        if (!result) {
            faceHint.innerHTML = '<i class="fas fa-info-circle"></i><span>Tips: rapikan posisi wajah, jangan terlalu dekat, dan hindari bayangan.</span>';
        }
    });

    retryBtn.addEventListener('click', function() {
        setBadge('ready', 'Siap');
        setStatus('Ulangi pengambilan foto untuk verifikasi.');
        setSimilarity(0);
        lastFaceDistance = null;
        lastDescriptorSimilarity = 0;
        lastServerMatchToken = '';
        matchPassed = false;
        proceedBtn.disabled = true;
        previewWrap.classList.remove('show');
        previewImg.src = '';
        faceHint.innerHTML = '<i class="fas fa-info-circle"></i><span>Pastikan wajah berada di tengah area panduan.</span>';
    });

    if (poseStartBtn) {
        poseStartBtn.addEventListener('click', function() {
            if (!stream || !referenceReady) {
                setStatus('Aktifkan kamera dan tunggu foto referensi siap terlebih dahulu.');
                return;
            }
            startPoseValidationFlow();
        });
    }

    if (poseResetBtn) {
        poseResetBtn.addEventListener('click', function() {
            resetPoseValidation();
            setStatus('Validasi pose direset. Klik konfirmasi siap untuk mulai lagi.');
            setPoseBadge('ready', 'Siap');
        });
    }

    function setAttendanceModalMessage(text, state = '') {
        if (!attendanceModalMessage) return;
        attendanceModalMessage.textContent = text;
        attendanceModalMessage.className = 'attendance-modal-note' + (state ? ` ${state}` : '');
    }

    function openAttendanceModal() {
        if (attendanceDone || attendanceSubmitting) return;
        if (!matchPassed) {
            setStatus('Verifikasi belum selesai. Silakan ambil dan cocokkan wajah.');
            return;
        }
        if (!studentScheduleId) {
            setStatus('Jadwal belum dipilih. Buka menu Jadwal lalu klik Absen.');
            return;
        }
        if (!lastCapturedData) {
            setStatus('Foto verifikasi belum tersedia. Silakan ambil ulang.');
            return;
        }
        if (!lastServerMatchToken) {
            setStatus('Sesi verifikasi wajah sudah tidak valid. Silakan Ambil & Cocokkan ulang.');
            return;
        }
        const hasGeo = !gpsEnabled || (Number.isFinite(currentLat) && Number.isFinite(currentLng));
        const distanceValue = Number.isFinite(currentDistance) ? currentDistance : lastDistance;
        const geoVerified = !gpsEnabled ? true : isWithinRadius(distanceValue, currentAccuracy);
        const requiresRadius = absenceMode === 'hadir';
        const requiresGeo = gpsEnabled && requiresRadius;
        if (requiresGeo && !hasGeo) {
            setStatus('Lokasi belum tersedia. Pastikan GPS aktif.');
            return;
        }
        if (requiresRadius && !geoVerified) {
            setStatus('Lokasi belum tervalidasi. Pastikan berada di dalam radius.');
            return;
        }

        if (attendanceFacePreview) {
            attendanceFacePreview.src = lastCapturedData;
        }
        setAttendanceModalMessage('Pastikan data sudah benar sebelum mengirim absensi.');
        if (attendanceSubmitBtn) {
            attendanceSubmitBtn.disabled = false;
            attendanceSubmitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Konfirmasi & Absen';
        }
        if (attendanceSimilarityText) {
            attendanceSimilarityText.textContent = `${lastSimilarity.toFixed(2)}%`;
        }
        if (attendanceStatusText) {
            attendanceStatusText.textContent = matchPassed ? 'Terverifikasi' : 'Gagal';
        }
        if (attendanceModeText) {
            attendanceModeText.textContent = getModeLabel(absenceMode);
        }
        if (attendanceMatchBadge) {
            attendanceMatchBadge.textContent = matchPassed ? 'Lolos' : 'Gagal';
            attendanceMatchBadge.className = 'match-badge ' + (matchPassed ? 'success' : 'error');
        }

        if (attendanceGeoName) {
            attendanceGeoName.textContent = 'Lokasi Siswa';
        }
        if (attendanceGeoAddress) {
            attendanceGeoAddress.textContent = 'Koordinat GPS siswa';
        }
        if (attendanceGeoLat) {
            attendanceGeoLat.textContent = formatCoordinate(currentLat);
        }
        if (attendanceGeoLng) {
            attendanceGeoLng.textContent = formatCoordinate(currentLng);
        }
        if (attendanceGeoDistance) {
            attendanceGeoDistance.textContent = Number.isFinite(currentDistance)
                ? formatDistance(currentDistance)
                : '-';
        }
        if (attendanceGeoAccuracy) {
            attendanceGeoAccuracy.textContent = formatAccuracy(currentAccuracy);
        }
        if (attendanceGeoTime) {
            attendanceGeoTime.textContent = `Update: ${formatDateTime(currentLocationTs)}`;
        }
        ensureAttendanceMap(currentLat, currentLng);
        if (attendanceGeoBadge) {
            if (!requiresRadius && !geoVerified) {
                attendanceGeoBadge.textContent = 'Berhalangan';
                attendanceGeoBadge.className = 'match-badge warning';
            } else {
                attendanceGeoBadge.textContent = geoVerified ? 'Tervalidasi' : 'Belum Valid';
                attendanceGeoBadge.className = 'match-badge ' + (geoVerified ? 'ready' : 'error');
            }
        }

        attendanceSubmitting = false;
        updateAttendanceInfo();
        if (attendanceSubmitBtn) {
            const canSubmit = matchPassed && studentScheduleId && lastCapturedData && lastServerMatchToken && (!requiresGeo || hasGeo) && (!requiresRadius || geoVerified);
            attendanceSubmitBtn.disabled = !canSubmit;
            if (!canSubmit) {
                if (!studentScheduleId) {
                    setAttendanceModalMessage('Jadwal belum dipilih. Kembali ke menu Jadwal.', 'danger');
                } else if (!lastCapturedData) {
                    setAttendanceModalMessage('Foto verifikasi belum tersedia.', 'danger');
                } else if (!lastServerMatchToken) {
                    setAttendanceModalMessage('Sesi verifikasi habis. Silakan Ambil & Cocokkan ulang.', 'danger');
                } else if (requiresGeo && !hasGeo) {
                    setAttendanceModalMessage('Lokasi belum tersedia. Pastikan GPS aktif.', 'danger');
                } else if (requiresRadius && !geoVerified) {
                    setAttendanceModalMessage('Lokasi belum tervalidasi. Pastikan berada di dalam radius.', 'danger');
                }
            }
        }

        if (attendanceModal) {
            document.body.classList.add('attendance-modal-open');
            suspendLocationLayerForModal();
            attendanceModal.show();
        } else if (attendanceModalEl && window.bootstrap) {
            attendanceModal = new bootstrap.Modal(attendanceModalEl, { backdrop: true, keyboard: true });
            document.body.classList.add('attendance-modal-open');
            suspendLocationLayerForModal();
            attendanceModal.show();
        }
    }

    async function submitAttendance() {
        if (attendanceSubmitting || attendanceDone) return;
        if (!studentScheduleId) {
            setAttendanceModalMessage('Jadwal belum dipilih. Kembali ke menu Jadwal.', 'danger');
            return;
        }
        if (!lastCapturedData) {
            setAttendanceModalMessage('Foto belum tersedia. Silakan ambil ulang.', 'danger');
            return;
        }
        if (!lastServerMatchToken) {
            setAttendanceModalMessage('Sesi verifikasi habis. Silakan Ambil & Cocokkan ulang.', 'danger');
            return;
        }
        const hasGeo = !gpsEnabled || (Number.isFinite(currentLat) && Number.isFinite(currentLng));
        const distanceValue = Number.isFinite(currentDistance) ? currentDistance : lastDistance;
        const geoVerified = !gpsEnabled ? true : isWithinRadius(distanceValue, currentAccuracy);
        const requiresRadius = absenceMode === 'hadir';
        const requiresGeo = gpsEnabled && requiresRadius;
        if (requiresGeo && !hasGeo) {
            setAttendanceModalMessage('Lokasi belum tersedia. Pastikan GPS aktif.', 'danger');
            return;
        }
        if (requiresRadius && !geoVerified) {
            setAttendanceModalMessage('Lokasi belum tervalidasi. Pastikan berada di dalam radius.', 'danger');
            return;
        }
        updateAttendanceInfo();

        attendanceSubmitting = true;
        if (attendanceSubmitBtn) {
            attendanceSubmitBtn.disabled = true;
            attendanceSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
        }

        const payload = new URLSearchParams();
        payload.append('student_schedule_id', studentScheduleId);
        payload.append('captured_image', lastCapturedData);
        payload.append('latitude', Number.isFinite(currentLat) ? currentLat : '');
        payload.append('longitude', Number.isFinite(currentLng) ? currentLng : '');
        payload.append('accuracy', Number.isFinite(currentAccuracy) ? currentAccuracy : '');
        payload.append('face_similarity', lastSimilarity.toFixed(2));
        payload.append('face_verified', matchPassed ? '1' : '0');
        payload.append('face_distance', Number.isFinite(lastFaceDistance) ? lastFaceDistance.toFixed(4) : '');
        if (lastServerMatchToken) {
            payload.append('face_match_token', lastServerMatchToken);
        }
        payload.append('present_id', String(getPresentId(absenceMode)));
        if (attendanceInfoText) {
            payload.append('information', attendanceInfoText);
        }
        const snapshotData = await captureAttendanceSnapshot();
        if (snapshotData) {
            payload.append('validation_snapshot', snapshotData);
        }

        try {
            const response = await fetch('../api/save_attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload
            });
            const rawText = await response.text();
            let data = null;
            try {
                data = rawText ? JSON.parse(rawText) : null;
            } catch (parseError) {
                const snippet = rawText ? rawText.substring(0, 200) : '';
                throw new Error(snippet ? `Respon tidak valid: ${snippet}` : 'Respon tidak valid.');
            }
            if (!response.ok || !data || !data.success) {
                const message = data?.message || 'Gagal menyimpan absensi.';
                setAttendanceModalMessage(message, 'danger');
                notifyStudentEvent(
                    'Absensi Gagal',
                    message,
                    '?page=face_recognition',
                    'attendance_error',
                    20000
                );
                attendanceSubmitting = false;
                if (attendanceSubmitBtn) {
                    attendanceSubmitBtn.disabled = false;
                    attendanceSubmitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Konfirmasi & Absen';
                }
                return;
            }

            attendanceDone = true;
            const statusLabel = (data.status || '').toUpperCase();
            setAttendanceModalMessage(`Absensi berhasil (${statusLabel || 'SUCCESS'}).`, 'success');
            setBadge('success', 'Selesai');
            setStatus('Absensi berhasil. Status jadwal diperbarui.');
            lastServerMatchToken = '';
            if (attendanceInfoStatus) {
                const baseLabel = getModeLabel(absenceMode);
                attendanceInfoStatus.textContent = statusLabel ? `${baseLabel} (${statusLabel})` : baseLabel;
            }
            {
                const baseLabel = getModeLabel(absenceMode);
                const title = statusLabel === 'OVERDUE' ? 'Absensi Terlambat Tercatat' : 'Absensi Berhasil';
                const body = statusLabel === 'OVERDUE'
                    ? `Absensi ${baseLabel} tercatat dengan status OVERDUE.`
                    : `Absensi ${baseLabel} berhasil tersimpan di sistem.`;
                notifyStudentEvent(title, body, '?page=riwayat', 'attendance_success', 15000);
            }
            proceedBtn.disabled = true;
            captureBtn.disabled = true;
            retryBtn.disabled = true;

            setTimeout(() => {
                if (attendanceModal) {
                    attendanceModal.hide();
                }
                window.location.href = '?page=jadwal';
            }, 1200);
        } catch (error) {
            const message = error?.message ? error.message : 'Gagal mengirim absensi. Coba lagi.';
            setAttendanceModalMessage(message, 'danger');
            notifyStudentEvent(
                'Error Sistem Absensi',
                message,
                '?page=face_recognition',
                'attendance_system_error',
                30000
            );
            attendanceSubmitting = false;
            if (attendanceSubmitBtn) {
                attendanceSubmitBtn.disabled = false;
                attendanceSubmitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Konfirmasi & Absen';
            }
        }
    }

    proceedBtn.addEventListener('click', function() {
        openAttendanceModal();
    });

    if (attendanceModalEl && window.bootstrap) {
        if (attendanceModalEl.parentElement !== document.body) {
            document.body.appendChild(attendanceModalEl);
        }
        attendanceModal = new bootstrap.Modal(attendanceModalEl, { backdrop: true, keyboard: true });
    }

    if (attendanceSubmitBtn) {
        attendanceSubmitBtn.addEventListener('click', function() {
            submitAttendance();
        });
    }
    function hideAttendanceModal() {
        if (attendanceModal) {
            attendanceModal.hide();
            return;
        }
        if (!attendanceModalEl) return;
        attendanceModalEl.classList.remove('show');
        attendanceModalEl.style.display = 'none';
        attendanceModalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }
    if (attendanceCancelBtn) {
        attendanceCancelBtn.addEventListener('click', hideAttendanceModal);
    }
    if (attendanceCloseBtn) {
        attendanceCloseBtn.addEventListener('click', hideAttendanceModal);
    }
    if (attendanceModalEl) {
        attendanceModalEl.addEventListener('hidden.bs.modal', function() {
            if (!attendanceDone && attendanceSubmitBtn) {
                attendanceSubmitBtn.disabled = false;
                attendanceSubmitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Konfirmasi & Absen';
            }
            attendanceSubmitting = false;
            restoreLocationLayerAfterModal();
            document.body.classList.remove('attendance-modal-open');
        });
        attendanceModalEl.addEventListener('shown.bs.modal', function() {
            const modalBody = attendanceModalEl.querySelector('.modal-body');
            if (modalBody) {
                modalBody.scrollTop = 0;
            }
            ensureAttendanceMap(currentLat, currentLng);
        });
    }

    if (excuseSickBtn) {
        excuseSickBtn.addEventListener('click', function() {
            setAbsenceMode('sakit');
        });
    }
    if (excuseIzinBtn) {
        excuseIzinBtn.addEventListener('click', function() {
            setAbsenceMode('izin');
        });
    }
    if (resetModeBtn) {
        resetModeBtn.addEventListener('click', function() {
            setAbsenceMode('hadir');
        });
    }

    if (referencePreview && referenceUrl) {
        const modalEl = document.getElementById('referenceModal');
        const modalImg = document.getElementById('referenceModalImg');
        const referenceUrlWithoutQuery = referenceUrl.split('?')[0];
        if (modalEl && window.bootstrap) {
            referenceModal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });
            let outsideClickHandler = null;

            if (modalImg) {
                modalImg.addEventListener('error', function() {
                    if (!modalImg.dataset.retried && referenceUrlWithoutQuery && referenceUrlWithoutQuery !== referenceUrl) {
                        modalImg.dataset.retried = '1';
                        modalImg.src = referenceUrlWithoutQuery;
                        return;
                    }
                    setStatus('Foto referensi gagal dimuat. Muat ulang halaman atau hubungi admin.');
                });
            }

            modalEl.addEventListener('shown.bs.modal', function() {
                outsideClickHandler = function(event) {
                    const dialog = modalEl.querySelector('.modal-dialog');
                    const isBackdrop = event.target.classList?.contains('modal-backdrop');
                    const clickedOutsideDialog = dialog && !dialog.contains(event.target);
                    if (isBackdrop || clickedOutsideDialog) {
                        referenceModal.hide();
                    }
                };
                document.addEventListener('mousedown', outsideClickHandler);
            });

            modalEl.addEventListener('hidden.bs.modal', function() {
                if (modalImg) {
                    modalImg.src = '';
                }
                if (outsideClickHandler) {
                    document.removeEventListener('mousedown', outsideClickHandler);
                    outsideClickHandler = null;
                }
            });
        }
        referencePreview.addEventListener('click', function() {
            if (modalImg) {
                delete modalImg.dataset.retried;
                modalImg.src = referenceUrl;
            }
            if (referenceModal) {
                referenceModal.show();
            } else {
                window.open(referenceUrl, '_blank');
            }
        });
    }

    if (cameraSelect) {
        cameraSelect.addEventListener('change', function() {
            if (this.value) {
                cameraSelect.disabled = true;
                switchCamera(this.value).finally(() => {
                    cameraSelect.disabled = false;
                });
            }
        });
    }

    if (refreshCameraBtn) {
        refreshCameraBtn.addEventListener('click', async function() {
            await loadCameraDevices();
            const selectedDevice = cameraSelect.value;
            if (selectedDevice) {
                setCameraStatus('Menyegarkan kamera...');
                await switchCamera(selectedDevice);
            }
        });
    }

    if (locationRetryBtn) {
        locationRetryBtn.addEventListener('click', function() {
            setRetryLoading(true);
            stableWithinCount = 0;
            stableOutsideCount = 0;
            distanceSamples.length = 0;
            if (Number.isFinite(lastDistance)) {
                distanceSamples.push(lastDistance, lastDistance);
            }
            stopLocationWatch();
            startLocationWatch();
        });
    }

    if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
        navigator.mediaDevices.addEventListener('devicechange', function() {
            loadCameraDevices();
        });
    }

    window.addEventListener('beforeunload', function() {
        stopCamera();
        stopMemoryMonitor();
        stopLocationWatch();
        setScrollLock(false);
    });

    resetState();
    const initialMode = normalizeMode(scheduleParams.get('mode'));
    setAbsenceMode(initialMode);
    if (!studentScheduleId) {
        setStatus('Silakan pilih jadwal terlebih dahulu dari menu Jadwal lalu klik Absen.');
        proceedBtn.disabled = true;
    }
    if (gpsEnabled) {
        const lastDistanceState = loadLastDistance();
        if (isRecentDistance(lastDistanceState, true)) {
            distanceSamples.push(lastDistanceState.distance, lastDistanceState.distance);
            lastDistance = lastDistanceState.distance;
        }
        const storedState = loadLocationState();
        if (storedState && storedState.locked) {
            if (locationRadiusText && Number.isFinite(storedState.radius)) {
                locationRadiusText.textContent = formatDistance(storedState.radius);
            }
            if (locationDistanceText && Number.isFinite(storedState.distance)) {
                locationDistanceText.textContent = formatDistance(storedState.distance);
            }
            const message = Number.isFinite(storedState.distance)
                ? 'Absensi terkunci. Di luar radius lokasi.'
                : 'Memeriksa lokasi Anda...';
            setLocationLock(true, message);
        } else {
            setLocationLock(true, 'Memeriksa lokasi Anda...');
        }
    }
    startLocationWatch();
    startMemoryMonitor();
    if (referenceUrl) {
        warmFacePipeline();
    }
    if (!referenceUrl) {
        setBadge('error', 'Gagal');
        setStatus('Foto referensi belum tersedia. Silakan hubungi admin.');
        setCameraStatus('Tidak tersedia');
    }
});
</script>
