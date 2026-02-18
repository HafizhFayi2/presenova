<div class="attendance-form-container">
    <div class="alert alert-info">
        <h5><i class="fas fa-book"></i> {{ $subject }}</h5>
        <p class="mb-0"><i class="fas fa-chalkboard-teacher"></i> {{ $teacher }}</p>
    </div>
    
    @if (!$hasReference)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Perhatian:</strong> Foto referensi Anda belum tersedia di sistem.
            Silakan hubungi administrator untuk upload foto referensi terlebih dahulu.
        </div>
    @endif
    
    <!-- GPS Status -->
    <div id="gpsStatus" class="mb-3">
        <h6><i class="fas fa-map-marker-alt"></i> Step 1: Verifikasi Lokasi</h6>
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Mendapatkan lokasi GPS Anda...</p>
        </div>
    </div>
    
    <!-- Camera Section -->
    <div id="cameraSection" style="display: none;">
        <h6><i class="fas fa-camera"></i> Step 2: Ambil Foto Selfie</h6>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Pastikan wajah terlihat jelas dengan pencahayaan cukup
        </div>

        <div class="mb-2">
            <label class="form-label small">Pilih Kamera</label>
            <div class="d-flex gap-2">
                <select id="attendanceCameraSelect" class="form-select form-select-sm" disabled></select>
                <button type="button" class="btn btn-outline-primary btn-sm" id="attendanceRefreshCameraBtn" title="Refresh kamera">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        
        <div class="text-center mb-3">
            <div class="position-relative d-inline-block">
                <video id="attendanceVideo" autoplay playsinline style="width: 100%; max-width: 400px; border-radius: 8px;"></video>
                <canvas id="attendanceOverlay" style="position: absolute; left: 0; top: 0; width: 100%; height: 100%;"></canvas>
            </div>
            <canvas id="attendanceCanvas" style="display: none;"></canvas>
            <div id="liveFaceStatus" class="small text-muted mt-2">Memuat model wajah...</div>
        </div>
        
        <div class="text-center">
            <button id="captureAttendanceBtn" class="btn btn-primary" data-has-reference="{{ $hasReference ? '1' : '0' }}" @if (!$hasReference) disabled @endif>
                <i class="fas fa-camera"></i> Ambil Foto
            </button>
        </div>
        
        <div id="previewSection" style="display: none;" class="mt-3">
            <h6><i class="fas fa-image"></i> Preview Foto</h6>
            <div class="text-center">
                <img id="previewImage" class="img-fluid rounded" style="max-width: 300px;">
            </div>
        </div>
    </div>

    <!-- Face Match Section -->
    <div id="faceMatchSection" style="display: none;">
        <h6><i class="fas fa-user-check"></i> Step 3: Verifikasi Wajah</h6>
        <div class="face-match-card">
            <div id="faceMatchStatus" class="alert alert-secondary mb-2">
                Menunggu verifikasi wajah...
            </div>
            <div class="progress mb-2">
                <div id="faceMatchProgress" class="progress-bar bg-info" role="progressbar" style="width: 0%"></div>
            </div>
            <div class="text-muted small">
                Similarity: <strong id="faceSimilarityText">0%</strong>
            </div>
            <div class="text-center mt-3">
                <button id="faceRetakeBtn" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-redo"></i> Ambil Foto Ulang
                </button>
            </div>
        </div>
    </div>
    
    <!-- Hidden Inputs -->
    <input type="hidden" id="capturedImageData">
    <input type="hidden" id="capturedLatitude">
    <input type="hidden" id="capturedLongitude">
    <input type="hidden" id="capturedAccuracy">
    <input type="hidden" id="faceReferenceUrl" value="{{ $referenceUrl }}">
    <input type="hidden" id="faceSimilarityValue" value="0">
    <input type="hidden" id="faceDistanceValue" value="0">
    <input type="hidden" id="faceVerifiedValue" value="0">
    <input type="hidden" id="studentScheduleId" value="{{ $studentScheduleId }}">
</div>

<div class="modal fade" id="attendanceResultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content attendance-result-modal">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>Konfirmasi Absensi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="result-grid">
                    <div class="result-face">
                        <div class="result-face-title">Hasil Wajah</div>
                        <img id="finalFaceImage" class="result-face-image" alt="Hasil Wajah">
                        <div class="result-face-meta">
                            <span>Similarity</span>
                            <strong id="finalSimilarityText">0%</strong>
                        </div>
                    </div>
                    <div class="result-geo">
                        <div class="geo-card">
                            <div class="geo-card-title">
                                <i class="fas fa-map-marker-alt me-1"></i> Detail Geolokasi
                            </div>
                            <div class="geo-row">
                                <span>Lokasi</span>
                                <strong id="finalSchoolName">-</strong>
                            </div>
                            <div class="geo-row">
                                <span>Alamat</span>
                                <strong id="finalSchoolAddress">-</strong>
                            </div>
                            <div class="geo-row">
                                <span>Koordinat</span>
                                <strong id="finalCoordinates">-</strong>
                            </div>
                            <div class="geo-row">
                                <span>Jarak</span>
                                <strong id="finalDistance">-</strong>
                            </div>
                            <div class="geo-row">
                                <span>Radius</span>
                                <strong id="finalRadius">-</strong>
                            </div>
                            <div class="geo-row">
                                <span>Akurasi GPS</span>
                                <strong id="finalAccuracy">-</strong>
                            </div>
                            <div class="geo-row">
                                <span>Waktu</span>
                                <strong id="finalTimestamp">-</strong>
                            </div>
                        </div>
                        <div class="geo-attendance">
                            <h6 class="mb-2"><i class="fas fa-check-circle"></i> Konfirmasi Absensi</h6>
                            <div class="mb-3">
                                <label class="form-label">Status Kehadiran</label>
                                <select id="attendanceStatus" class="form-select">
                                    <option value="1">Hadir</option>
                                    <option value="2">Sakit</option>
                                    <option value="3">Izin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Keterangan (Opsional)</label>
                                <textarea id="attendanceInfo" class="form-control" rows="2" placeholder="Contoh: Sakit kepala, Izin ke dokter, dll."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer attendance-result-footer">
                <button id="retakePhotoBtn" class="btn btn-outline-secondary">
                    <i class="fas fa-redo"></i> Ambil Foto Ulang
                </button>
                <button id="submitAttendanceBtn" class="btn btn-success">
                    <i class="fas fa-check"></i> Simpan Absensi
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../face/faces_logics/face-api.min.js"></script>
<script>
$(document).ready(function() {
    let attendanceStream = null;
    let capturedData = '';
    let currentLocation = null;
    let locationInfo = null;
    let locationAccuracy = null;
    let locationTimestamp = null;
    let attendanceResultModal = null;
    let modelsReady = false;
    let modelLoading = null;
    let detectorType = 'ssd';
    let referenceDescriptor = null;
    let liveDetectInterval = null;
    let liveDetectBusy = false;
    const similarityThreshold = {{ (int) $similarityThreshold }};
    const distanceThreshold = 0.55;
    const minLiveScore = 0.5;
    const minFaceAreaRatio = 0.08;
    const hasReference = String($('#captureAttendanceBtn').data('has-reference')) === '1';
    const studentLabel = @json($studentLabel);
    const MODEL_URL = '../face/faces_logics/models';
    let currentDeviceId = null;
    let lastWorkingDeviceId = null;
    const attendanceContentEl = document.getElementById('attendanceContent');
    const resultModalEl = attendanceContentEl
        ? attendanceContentEl.querySelector('#attendanceResultModal')
        : document.getElementById('attendanceResultModal');
    if (resultModalEl && document.body) {
        const existingModal = document.body.querySelector('#attendanceResultModal');
        if (existingModal && existingModal !== resultModalEl) {
            existingModal.remove();
        }
        if (resultModalEl.parentElement !== document.body) {
            document.body.appendChild(resultModalEl);
        }
    }
    if (resultModalEl && window.bootstrap) {
        attendanceResultModal = bootstrap.Modal.getOrCreateInstance(resultModalEl, {
            backdrop: 'static',
            keyboard: false
        });
    }
    
    // Step 1: Get GPS Location
    getGPSLocation();
    
    function getGPSLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    currentLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };
                    locationAccuracy = Number.isFinite(position.coords.accuracy) ? position.coords.accuracy : null;
                    locationTimestamp = position.timestamp ? new Date(position.timestamp) : new Date();
                    
                    $('#capturedLatitude').val(currentLocation.latitude);
                    $('#capturedLongitude').val(currentLocation.longitude);
                    if (Number.isFinite(currentLocation.accuracy)) {
                        $('#capturedAccuracy').val(currentLocation.accuracy);
                    }
                    
                    // Check location validity
                    checkLocationValidity(currentLocation);
                },
                function(error) {
                    $('#gpsStatus').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Gagal mendapatkan lokasi GPS. Pastikan GPS aktif dan izinkan akses lokasi.
                            <button onclick="getGPSLocation()" class="btn btn-sm btn-warning mt-2">
                                <i class="fas fa-redo"></i> Coba Lagi
                            </button>
                        </div>
                    `);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        } else {
            $('#gpsStatus').html(`
                <div class="alert alert-danger">
                    Browser tidak mendukung geolocation.
                </div>
            `);
        }
    }
    
    function checkLocationValidity(location) {
        $.ajax({
            url: '../api/check_location.php',
            method: 'POST',
            data: location,
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    locationInfo = {
                        distance: response.data?.distance ?? null,
                        radius: response.data?.radius_limit ?? null,
                        withinRadius: !!response.data?.within_radius,
                        school: response.data?.school_location ?? null
                    };
                }
                if (response.success && response.data.within_radius) {
                    $('#gpsStatus').html(`
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Lokasi valid! Anda berada dalam radius sekolah.
                            <br><small>Jarak: ${response.data.distance.toFixed(1)} meter dari sekolah</small>
                        </div>
                    `);
                    
                    // Show camera section after 1 second
                    setTimeout(() => {
                        $('#gpsStatus').slideUp();
                        $('#cameraSection').slideDown();
                        startCamera();
                    }, 1000);
                    
                } else {
                    $('#gpsStatus').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i>
                            Anda berada di luar radius sekolah!
                            <br><small>Jarak: ${response.data?.distance?.toFixed(1) || 0} meter (Maks: ${response.data?.radius_limit || 100} meter)</small>
                            <br><small>Silakan datang ke sekolah untuk melakukan absensi.</small>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#gpsStatus').html(`
                    <div class="alert alert-danger">
                        Gagal memvalidasi lokasi. Silakan refresh halaman.
                    </div>
                `);
            }
        });
    }

    function formatNumber(value, digits = 6) {
        if (!Number.isFinite(value)) return '-';
        return Number(value).toFixed(digits);
    }

    function formatMeters(value) {
        if (!Number.isFinite(value)) return '-';
        return `${Number(value).toFixed(1)} m`;
    }

    function formatDateTime(value) {
        const dateObj = value instanceof Date ? value : new Date();
        return dateObj.toLocaleString('id-ID');
    }

    function updateResultModal() {
        const faceSrc = $('#previewImage').attr('src') || capturedData || '';
        $('#finalFaceImage').attr('src', faceSrc);
        const similarityText = $('#faceSimilarityText').text() || `${$('#faceSimilarityValue').val() || 0}%`;
        $('#finalSimilarityText').text(similarityText);

        const school = locationInfo?.school || {};
        $('#finalSchoolName').text(school.location_name || '-');
        $('#finalSchoolAddress').text(school.address || '-');

        if (currentLocation) {
            const coords = `${formatNumber(currentLocation.latitude, 6)}, ${formatNumber(currentLocation.longitude, 6)}`;
            $('#finalCoordinates').text(coords);
        } else {
            $('#finalCoordinates').text('-');
        }

        $('#finalDistance').text(formatMeters(locationInfo?.distance));
        $('#finalRadius').text(locationInfo && Number.isFinite(locationInfo.radius) ? `${locationInfo.radius} m` : '-');
        $('#finalAccuracy').text(formatMeters(locationAccuracy));
        $('#finalTimestamp').text(formatDateTime(locationTimestamp || new Date()));
    }

    function showResultModal() {
        updateResultModal();
        if (attendanceResultModal) {
            attendanceResultModal.show();
            return;
        }
        if (resultModalEl && window.bootstrap) {
            attendanceResultModal = bootstrap.Modal.getOrCreateInstance(resultModalEl, {
                backdrop: 'static',
                keyboard: false
            });
            attendanceResultModal.show();
            return;
        }
        if (window.jQuery) {
            $('#attendanceResultModal').modal('show');
        }
    }

    function updateScheduleRowAfterSubmit(scheduleId, statusLabel) {
        if (!scheduleId) return;
        const row = document.querySelector(`.schedule-row[data-schedule-id="${scheduleId}"]`);
        if (!row) return;

        const normalizedStatus = String(statusLabel || 'SUCCESS').toUpperCase();
        const isLate = normalizedStatus === 'OVERDUE' || normalizedStatus === 'TERLAMBAT';
        row.dataset.attended = '1';
        row.dataset.attendanceLate = isLate ? '1' : '0';

        const statusEl = row.querySelector('.schedule-status');
        if (statusEl) {
            statusEl.classList.remove(
                'status-muted',
                'status-countdown',
                'status-active',
                'status-overdue',
                'status-closed',
                'status-success'
            );
            statusEl.classList.add(isLate ? 'status-overdue' : 'status-success');
            statusEl.innerHTML = `<i class="fas fa-${isLate ? 'exclamation-triangle' : 'check-circle'} me-1"></i>${normalizedStatus}`;
        }

        const btn = row.querySelector('.schedule-action-btn');
        if (btn) {
            btn.disabled = true;
            btn.classList.remove('btn-success', 'btn-warning');
            btn.classList.add('btn-secondary');
        }

        const actionCell = row.querySelector('.schedule-action');
        if (actionCell) {
            actionCell.innerHTML = `
                <span class="badge bg-success">
                    <i class="fas fa-check"></i> Done
                </span>
            `;
        }
    }
    
    function startCamera(deviceId = null) {
        if (attendanceStream) {
            attendanceStream.getTracks().forEach(track => track.stop());
        }
        stopLiveFaceDetection();
        setCaptureEnabled(false);

        const baseVideo = { width: { ideal: 640 }, height: { ideal: 480 } };
        const exactConstraints = deviceId
            ? { video: { ...baseVideo, deviceId: { exact: deviceId } }, audio: false }
            : { video: { ...baseVideo, facingMode: 'user' }, audio: false };

        navigator.mediaDevices.getUserMedia(exactConstraints)
            .then(function(stream) {
                attendanceStream = stream;
                const video = document.getElementById('attendanceVideo');
                video.srcObject = stream;
                video.onloadedmetadata = async () => {
                    await video.play();
                    updateLiveStatus('muted', 'Memuat model wajah...');
                    try {
                        await loadFaceModels();
                        updateLiveStatus('muted', 'Arahkan wajah ke kamera...');
                        startLiveFaceDetection();
                    } catch (error) {
                        updateLiveStatus('error', 'Model wajah gagal dimuat. Silakan refresh.');
                    }
                };

                const settings = stream.getVideoTracks()[0]?.getSettings();
                if (settings?.deviceId) {
                    currentDeviceId = settings.deviceId;
                    lastWorkingDeviceId = settings.deviceId;
                }

                loadCameraDevices();
            })
            .catch(function(err) {
                if (deviceId) {
                    const idealConstraints = { video: { ...baseVideo, deviceId: { ideal: deviceId } }, audio: false };
                    return navigator.mediaDevices.getUserMedia(idealConstraints)
                        .then(function(stream) {
                            attendanceStream = stream;
                            const video = document.getElementById('attendanceVideo');
                            video.srcObject = stream;
                            video.onloadedmetadata = async () => {
                                await video.play();
                                updateLiveStatus('muted', 'Memuat model wajah...');
                                try {
                                    await loadFaceModels();
                                    updateLiveStatus('muted', 'Arahkan wajah ke kamera...');
                                    startLiveFaceDetection();
                                } catch (error) {
                                    updateLiveStatus('error', 'Model wajah gagal dimuat. Silakan refresh.');
                                }
                            };
                            const settings = stream.getVideoTracks()[0]?.getSettings();
                            if (settings?.deviceId) {
                                currentDeviceId = settings.deviceId;
                                lastWorkingDeviceId = settings.deviceId;
                            }
                            loadCameraDevices();
                        });
                }
                throw err;
            })
            .catch(function(err) {
                $('#cameraSection').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Tidak dapat mengakses kamera: ${err.message}
                    </div>
                `);
            });
    }

    async function loadCameraDevices() {
        const select = $('#attendanceCameraSelect');
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            select.html('<option value="">Kamera tidak tersedia</option>');
            select.prop('disabled', true);
            return;
        }

        const devices = await navigator.mediaDevices.enumerateDevices();
        const cameras = devices.filter(device => device.kind === 'videoinput');
        select.empty();

        if (!cameras.length) {
            select.html('<option value="">Kamera tidak ditemukan</option>');
            select.prop('disabled', true);
            return;
        }

        cameras.forEach((camera, index) => {
            const label = camera.label || `Kamera ${index + 1}`;
            select.append(`<option value="${camera.deviceId}">${label}</option>`);
        });

        select.prop('disabled', false);
        if (currentDeviceId && cameras.some(cam => cam.deviceId === currentDeviceId)) {
            select.val(currentDeviceId);
        } else if (select.find('option').length) {
            select.prop('selectedIndex', 0);
        }
    }

    function switchCamera(deviceId) {
        if (!deviceId) return;
        if (attendanceStream) {
            attendanceStream.getTracks().forEach(track => track.stop());
        }
        startCamera(deviceId);
        if (lastWorkingDeviceId && lastWorkingDeviceId !== deviceId) {
            setTimeout(() => {
                if (!attendanceStream && lastWorkingDeviceId) {
                    startCamera(lastWorkingDeviceId);
                }
            }, 800);
        }
    }

    async function loadFaceModels() {
        if (modelsReady) return;
        if (modelLoading) return modelLoading;

        modelLoading = (async () => {
            try {
                await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
                detectorType = 'ssd';
            } catch (error) {
                detectorType = 'tiny';
                await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            }

            // Gunakan landmark_68 + face recognition (model shards ada di face/faces_logics/models)
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
            modelsReady = true;
        })();

        return modelLoading;
    }

    function getDetectorOptions(mode = 'match') {
        if (detectorType === 'ssd') {
            return new faceapi.SsdMobilenetv1Options({
                minConfidence: mode === 'live' ? minLiveScore : 0.6
            });
        }

        return new faceapi.TinyFaceDetectorOptions({
            inputSize: 320,
            scoreThreshold: mode === 'live' ? minLiveScore : 0.6
        });
    }

    function updateLiveStatus(type, message) {
        const status = $('#liveFaceStatus');
        status.removeClass('text-muted text-success text-warning text-danger');

        if (type === 'ok') {
            status.addClass('text-success');
        } else if (type === 'warn') {
            status.addClass('text-warning');
        } else if (type === 'error') {
            status.addClass('text-danger');
        } else {
            status.addClass('text-muted');
        }

        status.text(message);
    }

    function setCaptureEnabled(enabled) {
        const btn = $('#captureAttendanceBtn');
        if (!hasReference) {
            btn.prop('disabled', true);
            return;
        }
        btn.prop('disabled', !enabled);
    }

    function startLiveFaceDetection() {
        const video = document.getElementById('attendanceVideo');
        const overlay = document.getElementById('attendanceOverlay');

        if (!video || !overlay) return;
        if (!modelsReady) return;

        stopLiveFaceDetection();

        liveDetectInterval = setInterval(async () => {
            if (!attendanceStream || video.readyState < 2 || !modelsReady) return;
            if (liveDetectBusy) return;

            liveDetectBusy = true;
            try {
                const displaySize = { width: video.videoWidth, height: video.videoHeight };
                overlay.width = displaySize.width;
                overlay.height = displaySize.height;

                const detection = await faceapi
                    .detectSingleFace(video, getDetectorOptions('live'))
                    .withFaceLandmarks();

                const ctx = overlay.getContext('2d');
                ctx.clearRect(0, 0, overlay.width, overlay.height);

                if (detection) {
                    const resized = faceapi.resizeResults(detection, displaySize);
                    const box = resized.detection.box;
                    const drawBox = new faceapi.draw.DrawBox(box, {
                        label: studentLabel || 'Siswa'
                    });
                    drawBox.draw(overlay);
                    faceapi.draw.drawFaceLandmarks(overlay, resized);

                    const score = detection.detection.score ?? 0;
                    const areaRatio = (box.width * box.height) / (displaySize.width * displaySize.height);
                    const ok = score >= minLiveScore && areaRatio >= minFaceAreaRatio;

                    if (ok) {
                        updateLiveStatus('ok', 'Wajah terdeteksi. Tekan tombol Ambil Foto.');
                        setCaptureEnabled(true);
                    } else {
                        updateLiveStatus('warn', 'Wajah terdeteksi, tapi terlalu kecil/kurang jelas. Dekatkan wajah.');
                        setCaptureEnabled(false);
                    }
                } else {
                    updateLiveStatus('warn', 'Wajah belum terdeteksi. Pastikan wajah menghadap kamera.');
                    setCaptureEnabled(false);
                }
            } catch (error) {
                updateLiveStatus('error', 'Gagal mendeteksi wajah. Coba lagi.');
                setCaptureEnabled(false);
            } finally {
                liveDetectBusy = false;
            }
        }, 220);
    }

    function stopLiveFaceDetection() {
        if (liveDetectInterval) {
            clearInterval(liveDetectInterval);
            liveDetectInterval = null;
        }
        liveDetectBusy = false;

        const overlay = document.getElementById('attendanceOverlay');
        if (overlay) {
            const ctx = overlay.getContext('2d');
            ctx.clearRect(0, 0, overlay.width, overlay.height);
        }
    }

    async function detectBestFaceDescriptor(imageSource) {
        const detections = await faceapi
            .detectAllFaces(imageSource, getDetectorOptions('match'))
            .withFaceLandmarks()
            .withFaceDescriptors();

        if (!detections || detections.length === 0) {
            return null;
        }

        detections.sort((a, b) => {
            const areaA = a.detection.box.width * a.detection.box.height;
            const areaB = b.detection.box.width * b.detection.box.height;
            return areaB - areaA;
        });

        return detections[0];
    }

    async function loadReferenceDescriptor() {
        const referenceUrl = $('#faceReferenceUrl').val();
        if (!referenceUrl) {
            throw new Error('Foto referensi tidak tersedia');
        }
        if (referenceDescriptor) return referenceDescriptor;
        const img = await faceapi.fetchImage(referenceUrl);
        const detection = await detectBestFaceDescriptor(img);
        if (!detection) {
            throw new Error('Wajah pada foto referensi tidak terdeteksi');
        }
        referenceDescriptor = detection.descriptor;
        return referenceDescriptor;
    }

    function distanceToSimilarity(distance) {
        const bestDistance = 0.25; // sangat mirip
        const thresholdDistance = distanceThreshold; // batas lulus
        const maxDistance = 0.85; // sangat berbeda

        if (distance <= bestDistance) return 100;
        if (distance >= maxDistance) return 0;

        if (distance <= thresholdDistance) {
            const t = (distance - bestDistance) / (thresholdDistance - bestDistance);
            return Math.max(0, Math.min(100, 100 - (t * 25)));
        }

        const t = (distance - thresholdDistance) / (maxDistance - thresholdDistance);
        return Math.max(0, Math.min(100, 75 - (t * 75)));
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

    function drawMatchLabel(box) {
        const canvas = document.getElementById('attendanceCanvas');
        if (!canvas || !box) return;
        const ctx = canvas.getContext('2d');
        ctx.lineWidth = 3;
        ctx.strokeStyle = '#ef4444';
        ctx.strokeRect(box.x, box.y, box.width, box.height);

        if (!studentLabel) return;
        const text = String(studentLabel);
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

    function resetFaceMatchState() {
        $('#faceSimilarityValue').val('0');
        $('#faceDistanceValue').val('0');
        $('#faceVerifiedValue').val('0');
        $('#faceSimilarityText').text('0%');
        $('#faceMatchProgress').css('width', '0%');
        $('#faceMatchStatus').removeClass('alert-success alert-danger').addClass('alert-secondary')
            .html('Menunggu verifikasi wajah...');
    }
    
    // Capture Photo
    $('#captureAttendanceBtn').click(function() {
        const video = document.getElementById('attendanceVideo');
        const canvas = document.getElementById('attendanceCanvas');
        const context = canvas.getContext('2d');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        capturedData = canvas.toDataURL('image/jpeg', 0.8);
        $('#capturedImageData').val(capturedData);
        
        // Show preview
        $('#previewImage').attr('src', capturedData);
        $('#previewSection').show();
        
        // Stop camera
        if (attendanceStream) {
            attendanceStream.getTracks().forEach(track => track.stop());
        }
        stopLiveFaceDetection();
        setCaptureEnabled(false);
        
        // Start face matching
        $('#cameraSection').slideUp();
        $('#faceMatchSection').slideDown();
        resetFaceMatchState();

        $('#faceMatchStatus')
            .removeClass('alert-secondary alert-success alert-danger')
            .addClass('alert-info')
            .html('<i class="fas fa-spinner fa-spin me-1"></i> Memverifikasi wajah...');

        (async function runFaceMatch() {
            try {
                await loadFaceModels();

                const capturedImage = new Image();
                capturedImage.src = capturedData;
                await new Promise((resolve, reject) => {
                    capturedImage.onload = resolve;
                    capturedImage.onerror = () => reject(new Error('Gagal memuat foto selfie'));
                });

                const detection = await detectBestFaceDescriptor(capturedImage);

                if (!detection) {
                    throw new Error('Wajah tidak terdeteksi. Coba ulangi dengan pencahayaan lebih baik.');
                }

                const resized = faceapi.resizeResults(detection, {
                    width: canvas.width,
                    height: canvas.height
                });
                faceapi.draw.drawFaceLandmarks(canvas, resized);
                drawMatchLabel(resized.detection.box);
                $('#previewImage').attr('src', canvas.toDataURL('image/jpeg', 0.85));

                const serverResult = await requestServerMatch(capturedData);
                const serverSimilarity = parseFloat(serverResult.similarity || 0);
                const thresholdValue = serverResult.threshold || similarityThreshold;
                const localDistance = faceapi.euclideanDistance(referenceDescriptor, detection.descriptor);
                const localSimilarity = distanceToSimilarity(localDistance);
                const localPassed = Number.isFinite(localDistance) && localDistance <= distanceThreshold;
                const serverNearThreshold = serverSimilarity >= Math.max(45, thresholdValue - 30);
                const finalPassed = localPassed && serverNearThreshold;
                const similarityDisplay = Math.max(0, Math.min(100, (serverSimilarity * 0.58) + (localSimilarity * 0.42)));

                $('#faceSimilarityValue').val(similarityDisplay.toFixed(2));
                $('#faceDistanceValue').val(Number.isFinite(localDistance) ? localDistance.toFixed(4) : '');
                $('#faceSimilarityText').text(similarityDisplay.toFixed(2) + '%');
                $('#faceMatchProgress').css('width', similarityDisplay.toFixed(2) + '%');

                if (finalPassed) {
                    $('#faceVerifiedValue').val('1');
                    $('#faceMatchStatus')
                        .removeClass('alert-info alert-secondary alert-danger')
                        .addClass('alert-success')
                        .html('<i class="fas fa-check-circle me-1"></i> Verifikasi wajah berhasil (descriptor + server).');

                    setTimeout(() => {
                        showResultModal();
                    }, 400);
                } else {
                    $('#faceVerifiedValue').val('0');
                    const localDistanceText = Number.isFinite(localDistance) ? localDistance.toFixed(3) : '-';
                    $('#faceMatchStatus')
                        .removeClass('alert-info alert-secondary alert-success')
                        .addClass('alert-danger')
                        .html(`<i class="fas fa-times-circle me-1"></i> Verifikasi gagal. Descriptor: ${localDistanceText} (batas ${distanceThreshold}).`);
                }
            } catch (error) {
                $('#faceMatchStatus')
                    .removeClass('alert-info alert-secondary alert-success')
                    .addClass('alert-danger')
                    .html(`<i class="fas fa-exclamation-triangle me-1"></i> ${error.message}`);
            }
        })();
    });
    
    // Retake Photo
    $('#retakePhotoBtn').click(function() {
        if (attendanceResultModal) {
            attendanceResultModal.hide();
        } else if (window.jQuery) {
            $('#attendanceResultModal').modal('hide');
        }
        $('#faceMatchSection').slideUp();
        $('#previewSection').hide();
        $('#cameraSection').slideDown();
        resetFaceMatchState();
        const selectedDevice = $('#attendanceCameraSelect').val() || null;
        startCamera(selectedDevice);
    });

    $('#faceRetakeBtn').click(function() {
        if (attendanceResultModal) {
            attendanceResultModal.hide();
        } else if (window.jQuery) {
            $('#attendanceResultModal').modal('hide');
        }
        $('#faceMatchSection').slideUp();
        $('#previewSection').hide();
        $('#cameraSection').slideDown();
        resetFaceMatchState();
        const selectedDevice = $('#attendanceCameraSelect').val() || null;
        startCamera(selectedDevice);
    });

    $('#attendanceCameraSelect').on('change', function() {
        const selectedDevice = $(this).val();
        if (selectedDevice) {
            $('#attendanceCameraSelect').prop('disabled', true);
            switchCamera(selectedDevice);
            setTimeout(() => $('#attendanceCameraSelect').prop('disabled', false), 800);
        }
    });

    $('#attendanceRefreshCameraBtn').on('click', function() {
        loadCameraDevices();
    });

    if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
        navigator.mediaDevices.addEventListener('devicechange', function() {
            loadCameraDevices();
        });
    }
    
    // Submit Attendance
    $('#submitAttendanceBtn').click(function() {
        if (!capturedData) {
            alert('Silakan ambil foto terlebih dahulu');
            return;
        }

        if ($('#faceVerifiedValue').val() !== '1') {
            alert('Verifikasi wajah belum berhasil. Silakan ulangi proses face matching.');
            return;
        }
        
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
        
        const data = {
            student_schedule_id: $('#studentScheduleId').val(),
            captured_image: capturedData,
            latitude: $('#capturedLatitude').val(),
            longitude: $('#capturedLongitude').val(),
            accuracy: $('#capturedAccuracy').val(),
            present_id: $('#attendanceStatus').val(),
            information: $('#attendanceInfo').val(),
            face_similarity: $('#faceSimilarityValue').val(),
            face_distance: $('#faceDistanceValue').val(),
            face_verified: $('#faceVerifiedValue').val()
        };
        
        $.ajax({
            url: '../api/save_attendance.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const statusLabel = response.data.status || 'SUCCESS';
                    let validationHtml = '';
                    if (response.data.validation_path) {
                        const imgSrc = '../' + response.data.validation_path;
                        validationHtml = `
                            <div class="text-center mt-3">
                                <img src="${imgSrc}" class="img-fluid rounded" style="max-width: 360px;">
                                <div class="small text-muted mt-2">Tersimpan di ${response.data.attendance_path}</div>
                            </div>
                        `;
                    }

                    $('#attendanceContent').html(`
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Absensi berhasil (${statusLabel}). Similarity: ${response.data.similarity}%
                        </div>
                        ${validationHtml}
                    `);
                    if (attendanceResultModal) {
                        attendanceResultModal.hide();
                    } else if (window.jQuery) {
                        $('#attendanceResultModal').modal('hide');
                    }
                    updateScheduleRowAfterSubmit(data.student_schedule_id, statusLabel);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alert('Absensi gagal: ' + response.message);
                    btn.prop('disabled', false).html('<i class="fas fa-check"></i> Simpan Absensi');
                }
            },
            error: function(xhr, status, error) {
                alert('Terjadi kesalahan jaringan');
                btn.prop('disabled', false).html('<i class="fas fa-check"></i> Simpan Absensi');
            }
        });
    });
});
</script>

<style>
.attendance-result-modal {
    border-radius: 20px;
    border: 1px solid var(--border, rgba(148, 163, 184, 0.2));
    background: var(--card-color, #ffffff);
    box-shadow: 0 24px 50px rgba(15, 23, 42, 0.2);
}

.attendance-result-modal .modal-header,
.attendance-result-modal .modal-footer {
    border-color: var(--border, rgba(148, 163, 184, 0.2));
}

.result-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr);
    gap: 16px;
}

.result-face {
    background: rgba(53, 121, 246, 0.08);
    border-radius: 16px;
    padding: 16px;
    border: 1px solid rgba(53, 121, 246, 0.18);
    display: flex;
    flex-direction: column;
    gap: 12px;
    text-align: center;
}

.result-face-title {
    font-weight: 600;
    color: var(--text-color, #1f2937);
}

.result-face-image {
    width: 100%;
    border-radius: 14px;
    object-fit: cover;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.2);
}

.result-face-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(148, 163, 184, 0.2);
    font-size: 0.9rem;
}

.geo-card {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 16px;
    padding: 16px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
    display: grid;
    gap: 8px;
}

.geo-card-title {
    font-weight: 600;
    color: var(--text-color, #1f2937);
    margin-bottom: 4px;
}

.geo-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 0.85rem;
}

.geo-row span {
    color: var(--text-secondary-color, #52607a);
}

.geo-row strong {
    font-weight: 600;
    color: var(--text-color, #1f2937);
    text-align: right;
}

.geo-attendance {
    margin-top: 16px;
}

.attendance-result-footer {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

@media (max-width: 768px) {
    .result-grid {
        grid-template-columns: 1fr;
    }

    .attendance-result-footer {
        flex-direction: column-reverse;
    }
}
</style>

