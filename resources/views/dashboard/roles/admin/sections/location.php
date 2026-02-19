<?php
$success = $success ?? '';
$error = $error ?? '';

if (!function_exists('normalizeCoordinateValue')) {
    function normalizeCoordinateValue($value) {
        $value = trim((string) $value);
        return str_replace(',', '.', $value);
    }
}

if (!function_exists('getActiveLocationCount')) {
    function getActiveLocationCount($db, $excludeId = null) {
        $params = [];
        $sql = "SELECT COUNT(*) as total FROM school_location WHERE is_active = 'Y'";
        if (!empty($excludeId)) {
            $sql .= " AND location_id != ?";
            $params[] = $excludeId;
        }
        $stmt = $db->query($sql, $params);
        $row = $stmt ? $stmt->fetch() : null;
        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('getDefaultLocationId')) {
    function getDefaultLocationId($db) {
        $stmt = $db->query("SELECT default_location_id FROM site LIMIT 1");
        $row = $stmt ? $stmt->fetch() : null;
        return !empty($row['default_location_id']) ? (int) $row['default_location_id'] : 0;
    }
}

if (!function_exists('getLocationNameById')) {
    function getLocationNameById($db, $locationId) {
        $locationId = (int) $locationId;
        if ($locationId <= 0) {
            return 'Tidak ditentukan';
        }
        $stmt = $db->query("SELECT location_name FROM school_location WHERE location_id = ? LIMIT 1", [$locationId]);
        $row = $stmt ? $stmt->fetch() : null;
        $name = trim((string) ($row['location_name'] ?? ''));

        return $name !== '' ? $name : ('ID #' . $locationId);
    }
}

if (!function_exists('notifyDefaultLocationChanged')) {
    function notifyDefaultLocationChanged($db, $oldLocationId, $newLocationId, $actorLabel = 'admin') {
        $oldLocationId = (int) $oldLocationId;
        $newLocationId = (int) $newLocationId;
        if ($newLocationId <= 0 || $oldLocationId === $newLocationId) {
            return;
        }

        $oldName = getLocationNameById($db, $oldLocationId);
        $newName = getLocationNameById($db, $newLocationId);
        $title = 'Lokasi Default Absensi Diubah';
        $body = "Patokan GPS absensi berubah dari {$oldName} ke {$newName}. Pastikan lokasi Anda sesuai sebelum melakukan absensi.";

        $studentStmt = $db->query("SELECT id FROM student");
        $students = $studentStmt ? $studentStmt->fetchAll() : [];
        foreach ($students as $studentRow) {
            $studentId = (int) ($studentRow['id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }
            if (function_exists('pushNotifyStudent')) {
                pushNotifyStudent(
                    $studentId,
                    'default_location_changed',
                    $title,
                    $body,
                    '/dashboard/siswa.php?page=face_recognition'
                );
            }
        }

        if (function_exists('logActivity')) {
            $adminId = (int) ($_SESSION['user_id'] ?? 0);
            logActivity(
                $adminId,
                'admin',
                'default_location_changed',
                "Default lokasi berubah {$oldName} -> {$newName} oleh {$actorLabel}"
            );
        }
    }
}

if (!function_exists('locationRedirect')) {
    function locationRedirect($type, $message) {
        header("Location: admin.php?table=location&{$type}=" . urlencode($message));
        exit();
    }
}

// Handle location actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['location_id'])) {
        $location_id = isset($_POST['location_id']) ? (int) $_POST['location_id'] : 0;
        $location_name = trim($_POST['location_name'] ?? '');
        $latitude_raw = normalizeCoordinateValue($_POST['latitude'] ?? '');
        $longitude_raw = normalizeCoordinateValue($_POST['longitude'] ?? '');
        $radius_raw = trim($_POST['radius'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $is_active = $_POST['is_active'] ?? 'Y';
        $set_as_default = isset($_POST['set_as_default']) && $_POST['set_as_default'] === '1';

        $errors = [];

        if ($location_name === '') {
            $errors[] = 'Nama lokasi wajib diisi.';
        }

        $latitude_val = filter_var($latitude_raw, FILTER_VALIDATE_FLOAT);
        if ($latitude_val === false || $latitude_val < -90 || $latitude_val > 90) {
            $errors[] = 'Latitude tidak valid.';
        }

        $longitude_val = filter_var($longitude_raw, FILTER_VALIDATE_FLOAT);
        if ($longitude_val === false || $longitude_val < -180 || $longitude_val > 180) {
            $errors[] = 'Longitude tidak valid.';
        }

        $radius = filter_var($radius_raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 50, 'max_range' => 1000]
        ]);
        if ($radius === false) {
            $errors[] = 'Radius harus antara 50-1000 meter.';
        }

        $is_active = $is_active === 'N' ? 'N' : 'Y';
        if ($set_as_default) {
            $is_active = 'Y';
        }

        $defaultLocationId = getDefaultLocationId($db);
        if ($location_id > 0 && $is_active === 'N' && $defaultLocationId === $location_id) {
            $errors[] = 'Lokasi default harus aktif.';
        }

        if ($is_active === 'N') {
            $activeCount = getActiveLocationCount($db, $location_id > 0 ? $location_id : null);
            if ($activeCount <= 0) {
                $errors[] = 'Minimal harus ada satu lokasi aktif untuk validasi GPS.';
            }
        } elseif ($location_id === 0 && getActiveLocationCount($db) === 0) {
            $is_active = 'Y';
        }

        if (!empty($errors)) {
            locationRedirect('error', implode(' ', $errors));
        }

        $oldDefaultLocationId = getDefaultLocationId($db);
        $db->beginTransaction();

        if ($location_id === 0) {
            $stmt = $db->query(
                "INSERT INTO school_location (location_name, latitude, longitude, radius, address, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$location_name, $latitude_raw, $longitude_raw, $radius, $address, $is_active]
            );

            if (!$stmt) {
                $db->rollBack();
                locationRedirect('error', 'Gagal menambahkan lokasi.');
            }

            $location_id = (int) $db->lastInsertId();
            $successMessage = 'Lokasi berhasil ditambahkan!';
        } else {
            $stmt = $db->query(
                "UPDATE school_location SET location_name = ?, latitude = ?, longitude = ?, 
                 radius = ?, address = ?, is_active = ? WHERE location_id = ?",
                [$location_name, $latitude_raw, $longitude_raw, $radius, $address, $is_active, $location_id]
            );

            if (!$stmt) {
                $db->rollBack();
                locationRedirect('error', 'Gagal memperbarui lokasi.');
            }

            $successMessage = 'Lokasi berhasil diperbarui!';
        }

        if ($set_as_default) {
            $stmt = $db->query("UPDATE site SET default_location_id = ? WHERE site_id = 1", [$location_id]);
            if (!$stmt) {
                $db->rollBack();
                locationRedirect('error', 'Gagal memperbarui lokasi default.');
            }
            $successMessage .= ' Lokasi ini dijadikan patokan GPS.';
        }

        $db->commit();

        if ($set_as_default && $oldDefaultLocationId !== (int) $location_id) {
            notifyDefaultLocationChanged($db, $oldDefaultLocationId, (int) $location_id, 'admin/location_form');
        }

        locationRedirect('success', $successMessage);
    } elseif (isset($_POST['site_id'])) {
        $time_tolerance = $_POST['time_tolerance'] ?? '';
        $default_location_id = isset($_POST['default_location_id']) ? (int) $_POST['default_location_id'] : 0;
        $default_radius_raw = trim($_POST['default_radius'] ?? '');

        $errors = [];

        $time_tolerance = filter_var($time_tolerance, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 60]
        ]);
        if ($time_tolerance === false) {
            $errors[] = 'Toleransi waktu harus antara 0-60 menit.';
        }

        if ($default_location_id <= 0) {
            $errors[] = 'Lokasi default belum dipilih.';
        }

        $default_radius = filter_var($default_radius_raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 50, 'max_range' => 1000]
        ]);
        if ($default_radius === false) {
            $errors[] = 'Radius lokasi default harus antara 50-1000 meter.';
        }

        $defaultLocation = null;
        if ($default_location_id > 0) {
            $locStmt = $db->query(
                "SELECT location_id, is_active FROM school_location WHERE location_id = ? LIMIT 1",
                [$default_location_id]
            );
            $defaultLocation = $locStmt ? $locStmt->fetch() : null;
            if (!$defaultLocation) {
                $errors[] = 'Lokasi default tidak ditemukan.';
            }
        }

        if (!empty($errors)) {
            locationRedirect('error', implode(' ', $errors));
        }

        $oldDefaultLocationId = getDefaultLocationId($db);
        $db->beginTransaction();

        if ($defaultLocation && $defaultLocation['is_active'] !== 'Y') {
            $db->query("UPDATE school_location SET is_active = 'Y' WHERE location_id = ?", [$default_location_id]);
        }

        $stmt = $db->query(
            "UPDATE site SET time_tolerance = ?, enable_gps_validation = 'Y', enable_photo_validation = 'Y', 
             default_location_id = ? WHERE site_id = 1",
            [
                $time_tolerance,
                $default_location_id
            ]
        );

        if (!$stmt) {
            $db->rollBack();
            locationRedirect('error', 'Gagal menyimpan pengaturan.');
        }

        $radiusStmt = $db->query(
            "UPDATE school_location SET radius = ? WHERE location_id = ?",
            [$default_radius, $default_location_id]
        );
        if (!$radiusStmt) {
            $db->rollBack();
            locationRedirect('error', 'Gagal memperbarui radius lokasi default.');
        }

        $db->commit();

        if ($oldDefaultLocationId !== (int) $default_location_id) {
            notifyDefaultLocationChanged($db, $oldDefaultLocationId, (int) $default_location_id, 'admin/location_setting');
        }

        locationRedirect('success', 'Pengaturan sistem berhasil diperbarui!');
    }
}

// Handle GET actions (delete / set default)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (isset($canDeleteMaster) && !$canDeleteMaster) {
        locationRedirect('error', 'Operator tidak memiliki izin menghapus data master.');
    }
    $location_id = (int) $_GET['id'];
    if ($location_id <= 0) {
        locationRedirect('error', 'ID lokasi tidak valid.');
    }

    $locStmt = $db->query("SELECT location_id, is_active FROM school_location WHERE location_id = ? LIMIT 1", [$location_id]);
    $locationRow = $locStmt ? $locStmt->fetch() : null;

    if (!$locationRow) {
        locationRedirect('error', 'Lokasi tidak ditemukan.');
    }

    $defaultLocationId = getDefaultLocationId($db);
    if ($defaultLocationId === $location_id) {
        locationRedirect('error', 'Lokasi default tidak bisa dihapus. Ganti lokasi default terlebih dahulu.');
    }

    if ($locationRow['is_active'] === 'Y') {
        $activeCount = getActiveLocationCount($db, $location_id);
        if ($activeCount <= 0) {
            locationRedirect('error', 'Minimal harus ada satu lokasi aktif untuk validasi GPS.');
        }
    }

    $stmt = $db->query("DELETE FROM school_location WHERE location_id = ?", [$location_id]);
    if (!$stmt) {
        locationRedirect('error', 'Gagal menghapus lokasi.');
    }

    if (function_exists('resetAutoIncrementIfEmpty')) {
        resetAutoIncrementIfEmpty($db, 'school_location', 0);
    }

    locationRedirect('success', 'Lokasi berhasil dihapus!');
}

if (isset($_GET['action']) && $_GET['action'] === 'set_default' && isset($_GET['id'])) {
    $location_id = (int) $_GET['id'];
    if ($location_id <= 0) {
        locationRedirect('error', 'ID lokasi tidak valid.');
    }

    $locStmt = $db->query("SELECT location_id, is_active FROM school_location WHERE location_id = ? LIMIT 1", [$location_id]);
    $locationRow = $locStmt ? $locStmt->fetch() : null;

    if (!$locationRow) {
        locationRedirect('error', 'Lokasi tidak ditemukan.');
    }

    $oldDefaultLocationId = getDefaultLocationId($db);
    $db->beginTransaction();

    if ($locationRow['is_active'] !== 'Y') {
        $db->query("UPDATE school_location SET is_active = 'Y' WHERE location_id = ?", [$location_id]);
    }

    $stmt = $db->query("UPDATE site SET default_location_id = ? WHERE site_id = 1", [$location_id]);
    if (!$stmt) {
        $db->rollBack();
        locationRedirect('error', 'Gagal memperbarui lokasi default.');
    }

    $db->commit();

    if ($oldDefaultLocationId !== (int) $location_id) {
        notifyDefaultLocationChanged($db, $oldDefaultLocationId, (int) $location_id, 'admin/location_quick_action');
    }

    locationRedirect('success', 'Lokasi default berhasil diperbarui!');
}

// Get school locations
$sql = "SELECT * FROM school_location ORDER BY location_name";
$stmt = $db->query($sql);
$locations = $stmt ? $stmt->fetchAll() : [];

// Get site settings
$site_sql = "SELECT * FROM site LIMIT 1";
$site_stmt = $db->query($site_sql);
$site = $site_stmt ? $site_stmt->fetch() : null;

// Enforce GPS & face validation always active (server-side)
if ($site && ($site['enable_gps_validation'] !== 'Y' || $site['enable_photo_validation'] !== 'Y')) {
    $db->query("UPDATE site SET enable_gps_validation = 'Y', enable_photo_validation = 'Y' WHERE site_id = 1");
    $site['enable_gps_validation'] = 'Y';
    $site['enable_photo_validation'] = 'Y';
}

// Resolve default location (patokan GPS)
$defaultLocationId = (int) ($site['default_location_id'] ?? 0);
$defaultLocation = null;
foreach ($locations as $location) {
    if ((int) $location['location_id'] === $defaultLocationId) {
        $defaultLocation = $location;
        break;
    }
}

if (!$defaultLocation && !empty($locations)) {
    foreach ($locations as $location) {
        if ($location['is_active'] === 'Y') {
            $defaultLocation = $location;
            break;
        }
    }
    if (!$defaultLocation) {
        $defaultLocation = $locations[0];
    }

    $defaultLocationId = (int) $defaultLocation['location_id'];
    if ($defaultLocationId > 0) {
        $db->query("UPDATE site SET default_location_id = ? WHERE site_id = 1", [$defaultLocationId]);
        if ($site) {
            $site['default_location_id'] = $defaultLocationId;
        }
    }
}

if ($defaultLocation && $defaultLocation['is_active'] !== 'Y') {
    $db->query("UPDATE school_location SET is_active = 'Y' WHERE location_id = ?", [$defaultLocation['location_id']]);
    $defaultLocation['is_active'] = 'Y';
}

$mapLocation = $defaultLocation ?: (!empty($locations) ? $locations[0] : null);
?>

<div class="row">
    <div class="col-12">
        <div class="data-table-container mb-4">
            <h5><i class="fas fa-map"></i> Peta Lokasi Sekolah</h5>
            <div class="map-controls">
                <div class="map-type-group">
                    <button type="button" class="btn btn-sm btn-outline-primary map-type-btn" data-map-type="m">Normal</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary map-type-btn active" data-map-type="k">Satelit</button>
                </div>
            </div>
            <div class="map-frame">
                <div id="defaultLocationMap"
                     class="map-canvas"
                     data-lat="<?php echo $mapLocation['latitude'] ?? ''; ?>"
                     data-lng="<?php echo $mapLocation['longitude'] ?? ''; ?>"
                     data-zoom="18"
                     data-map-type="k"
                     aria-label="Peta Lokasi Sekolah"></div>
                <a id="mapOpenLink" class="map-open-link" href="#" target="_blank" rel="noopener">Buka di Google Maps</a>
            </div>
            <div class="map-meta">
                <span>Radius: <strong id="mapRadiusText"><?php echo isset($mapLocation['radius']) ? (int) $mapLocation['radius'] . ' m' : '-'; ?></strong></span>
                <span id="mapLocationName"><?php echo htmlspecialchars($mapLocation['location_name'] ?? 'Lokasi'); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="data-table-container">
            <h5><i class="fas fa-map-marker-alt"></i> Lokasi Sekolah</h5>
            
            <div class="list-group mb-4">
                <?php foreach($locations as $location): ?>
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1"><?php echo $location['location_name']; ?></h6>
                        <div class="d-flex gap-2">
                            <?php if (!empty($site['default_location_id']) && (int)$site['default_location_id'] === (int)$location['location_id']): ?>
                            <span class="badge badge-primary">Default GPS</span>
                            <?php endif; ?>
                            <span class="badge <?php echo $location['is_active'] == 'Y' ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $location['is_active'] == 'Y' ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                        </div>
                    </div>
                    <p class="mb-1"><?php echo $location['address']; ?></p>
                    <small class="text-muted">
                        Koordinat: <?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?> | 
                        Radius: <?php echo $location['radius']; ?> meter
                    </small>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-warning edit-location-btn"
                                data-id="<?php echo $location['location_id']; ?>"
                                data-name="<?php echo $location['location_name']; ?>"
                                data-lat="<?php echo $location['latitude']; ?>"
                                data-lng="<?php echo $location['longitude']; ?>"
                                data-radius="<?php echo $location['radius']; ?>"
                                data-address="<?php echo $location['address']; ?>"
                                data-active="<?php echo $location['is_active']; ?>"
                                data-default="<?php echo (!empty($site['default_location_id']) && (int)$site['default_location_id'] === (int)$location['location_id']) ? '1' : '0'; ?>">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <?php if (empty($site['default_location_id']) || (int)$site['default_location_id'] !== (int)$location['location_id']): ?>
                            <a href="?table=location&action=set_default&id=<?php echo $location['location_id']; ?>" 
                               class="btn btn-sm btn-outline-primary"
                               onclick="return AppDialog.inlineConfirm(this, 'Jadikan lokasi ini sebagai patokan GPS?')">
                                <i class="fas fa-location-dot"></i> Jadikan Default
                            </a>
                            <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                                <button class="btn btn-sm btn-danger" disabled title="Operator tidak dapat menghapus data master">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            <?php else: ?>
                                <a href="?table=location&action=delete&id=<?php echo $location['location_id']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return AppDialog.inlineConfirm(this, 'Hapus lokasi ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-sm btn-danger" disabled title="Lokasi default tidak dapat dihapus">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                <i class="fas fa-plus-circle"></i> Tambah Lokasi Baru
            </button>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="form-container">
            <h5><i class="fas fa-cog"></i> Pengaturan Validasi</h5>
            
            <form method="POST" action="?table=location">
                <input type="hidden" name="site_id" value="1">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Toleransi Waktu (menit)</label>
                            <input type="number" class="form-control" name="time_tolerance" 
                                   value="<?php echo $site['time_tolerance']; ?>" min="0" max="60">
                            <small class="text-muted">Waktu toleransi keterlambatan</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Lokasi Default (Patokan GPS)</label>
                            <select class="form-select" name="default_location_id" id="defaultLocationSelect" required>
                                <?php foreach($locations as $location): ?>
                                <option value="<?php echo $location['location_id']; ?>" 
                                    <?php echo $site['default_location_id'] == $location['location_id'] ? 'selected' : ''; ?>
                                    <?php echo $location['is_active'] != 'Y' ? 'disabled' : ''; ?>
                                    data-lat="<?php echo $location['latitude']; ?>"
                                    data-lng="<?php echo $location['longitude']; ?>"
                                    data-radius="<?php echo $location['radius']; ?>"
                                    data-name="<?php echo htmlspecialchars($location['location_name']); ?>">
                                    <?php echo $location['location_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Lokasi yang dijadikan patokan validasi GPS.</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Radius Lokasi Default (meter)</label>
                    <input type="number" class="form-control" name="default_radius" id="defaultRadiusInput"
                           value="<?php echo $defaultLocation['radius'] ?? 100; ?>" min="50" max="1000" required>
                    <small class="text-muted">Jarak maksimum untuk absensi valid dari titik pusat lokasi default.</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Validasi GPS</label>
                            <input type="hidden" name="enable_gps_validation" value="Y">
                            <select class="form-select" disabled>
                                <option selected>Aktif (Selalu)</option>
                            </select>
                            <small class="text-muted">Selalu aktif dan tidak dapat diubah.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Validasi Foto</label>
                            <input type="hidden" name="enable_photo_validation" value="Y">
                            <select class="form-select" disabled>
                                <option selected>Aktif (Selalu)</option>
                            </select>
                            <small class="text-muted">Validasi wajah selalu aktif.</small>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                </button>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Add/Edit Location Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Lokasi Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?table=location">
                <div class="modal-body">
                    <input type="hidden" name="location_id" id="locationId" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lokasi</label>
                        <input type="text" class="form-control" name="location_name" id="locationName" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" class="form-control" name="latitude" id="latitude" 
                                       pattern="-?\d+(\.\d+)?" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" class="form-control" name="longitude" id="longitude" 
                                       pattern="-?\d+(\.\d+)?" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Radius (meter)</label>
                        <input type="number" class="form-control" name="radius" id="radius" 
                               value="100" min="50" max="1000">
                        <small class="text-muted">Jarak maksimum untuk absensi valid</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" name="address" id="address" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="is_active" id="isActive">
                            <option value="Y">Aktif</option>
                            <option value="N">Nonaktif</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="set_as_default" id="setAsDefault" value="1">
                            <label class="form-check-label" for="setAsDefault">
                                Jadikan lokasi default (patokan GPS)
                            </label>
                        </div>
                        <small class="text-muted">Jika dicentang, lokasi ini akan menjadi patokan GPS.</small>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="getCurrentLocation()">
                            <i class="fas fa-location-arrow"></i> Gunakan Lokasi Saat Ini
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const NORMAL_TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const NORMAL_ATTRIBUTION = '';
const SATELLITE_TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const SATELLITE_ATTRIBUTION = '';

let defaultLocationMap = null;
let defaultLocationMarker = null;
let defaultLocationCircle = null;
let normalLayer = null;
let satelliteLayer = null;
let currentLocation = null;
let currentMapType = 'm';
let defaultZoom = 18;

function buildMapLink(lat, lng, zoom, mapType) {
    const safeType = mapType === 'k' ? 'k' : 'm';
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
        return `https://www.google.com/maps?hl=id&ll=${lat},${lng}&z=${zoom}&t=${safeType}&q=loc:${lat},${lng}`;
    }
    return `https://www.google.com/maps?q=Indonesia&z=5&t=${safeType}`;
}

function getFallbackCenter() {
    return { lat: -2.5489, lng: 118.0149 };
}

function readSelectedLocation() {
    const defaultLocationSelect = document.getElementById('defaultLocationSelect');
    const defaultRadiusInput = document.getElementById('defaultRadiusInput');
    const mapEl = document.getElementById('defaultLocationMap');
    const option = defaultLocationSelect
        ? defaultLocationSelect.options[defaultLocationSelect.selectedIndex]
        : null;

    const lat = parseFloat(option?.dataset.lat || mapEl?.dataset.lat || '');
    const lng = parseFloat(option?.dataset.lng || mapEl?.dataset.lng || '');
    const parsedRadius = parseInt(defaultRadiusInput?.value || option?.dataset.radius || '0', 10);
    const radius = Number.isFinite(parsedRadius) ? parsedRadius : 0;
    const name = option?.dataset.name || option?.textContent?.trim() || 'Lokasi';

    return { lat, lng, radius, name };
}

function updateMeta(location) {
    const mapRadiusText = document.getElementById('mapRadiusText');
    const mapLocationName = document.getElementById('mapLocationName');

    if (mapRadiusText) {
        mapRadiusText.textContent = Number.isFinite(location.radius) ? `${location.radius} m` : '-';
    }
    if (mapLocationName) {
        mapLocationName.textContent = location.name || 'Lokasi';
    }
}

function updateOpenLink(location) {
    const mapOpenLink = document.getElementById('mapOpenLink');
    if (!mapOpenLink) return;
    const zoom = defaultLocationMap ? defaultLocationMap.getZoom() : defaultZoom;
    mapOpenLink.href = buildMapLink(location.lat, location.lng, zoom, currentMapType);
}

function setBaseLayer(mapType) {
    currentMapType = mapType === 'k' ? 'k' : 'm';
    if (!defaultLocationMap) return;

    const nextLayer = currentMapType === 'k' ? satelliteLayer : normalLayer;
    if (defaultLocationMap.hasLayer(normalLayer)) {
        defaultLocationMap.removeLayer(normalLayer);
    }
    if (defaultLocationMap.hasLayer(satelliteLayer)) {
        defaultLocationMap.removeLayer(satelliteLayer);
    }
    nextLayer.addTo(defaultLocationMap);

    const mapEl = document.getElementById('defaultLocationMap');
    if (mapEl) {
        mapEl.dataset.mapType = currentMapType;
    }
}

function ensureMap(initialLocation) {
    if (defaultLocationMap || typeof L === 'undefined') return;
    const mapEl = document.getElementById('defaultLocationMap');
    if (!mapEl) return;

    const zoomAttr = mapEl.dataset.zoom || '18';
    const parsedZoom = parseInt(zoomAttr, 10);
    defaultZoom = Number.isFinite(parsedZoom) ? parsedZoom : 18;
    currentMapType = mapEl.dataset.mapType || currentMapType;

    normalLayer = L.tileLayer(NORMAL_TILE_URL, {
        attribution: NORMAL_ATTRIBUTION,
        maxZoom: 19
    });
    satelliteLayer = L.tileLayer(SATELLITE_TILE_URL, {
        attribution: SATELLITE_ATTRIBUTION,
        maxZoom: 19
    });

    const hasCoords = Number.isFinite(initialLocation.lat) && Number.isFinite(initialLocation.lng);
    const center = hasCoords ? { lat: initialLocation.lat, lng: initialLocation.lng } : getFallbackCenter();

    defaultLocationMap = L.map(mapEl, {
        zoomControl: true,
        attributionControl: true
    }).setView(center, defaultZoom);

    if (defaultLocationMap.attributionControl && defaultLocationMap.attributionControl.setPrefix) {
        defaultLocationMap.attributionControl.setPrefix(false);
    }

    setBaseLayer(currentMapType);

    defaultLocationMap.on('zoomend', function() {
        if (currentLocation) {
            updateOpenLink(currentLocation);
        }
    });
}

function applyLocation(location, options = {}) {
    const { recenter = false, resetZoom = false } = options;
    currentLocation = location;
    updateMeta(location);
    ensureMap(location);
    if (!defaultLocationMap) return;

    const hasCoords = Number.isFinite(location.lat) && Number.isFinite(location.lng);
    if (hasCoords) {
        const center = L.latLng(location.lat, location.lng);
        if (recenter && resetZoom) {
            defaultLocationMap.setView(center, defaultZoom);
        } else if (recenter) {
            defaultLocationMap.setView(center, defaultLocationMap.getZoom());
        } else if (resetZoom) {
            defaultLocationMap.setZoom(defaultZoom);
        }

        if (!defaultLocationMarker) {
            defaultLocationMarker = L.marker(center).addTo(defaultLocationMap);
        } else {
            defaultLocationMarker.setLatLng(center);
        }

        if (Number.isFinite(location.radius) && location.radius > 0) {
            if (!defaultLocationCircle) {
                defaultLocationCircle = L.circle(center, {
                    radius: location.radius,
                    color: '#ff0000',
                    weight: 4,
                    fillColor: '#ff0505',
                    fillOpacity: 0.25
                }).addTo(defaultLocationMap);
            } else {
                defaultLocationCircle.setLatLng(center);
                defaultLocationCircle.setRadius(location.radius);
                if (!defaultLocationMap.hasLayer(defaultLocationCircle)) {
                    defaultLocationCircle.addTo(defaultLocationMap);
                }
            }
        } else if (defaultLocationCircle) {
            defaultLocationMap.removeLayer(defaultLocationCircle);
            defaultLocationCircle = null;
        }
    } else {
        if (defaultLocationMarker) {
            defaultLocationMap.removeLayer(defaultLocationMarker);
            defaultLocationMarker = null;
        }
        if (defaultLocationCircle) {
            defaultLocationMap.removeLayer(defaultLocationCircle);
            defaultLocationCircle = null;
        }
    }

    updateOpenLink(location);
}

$(document).ready(function() {
    const defaultLocationSelect = document.getElementById('defaultLocationSelect');
    const defaultRadiusInput = document.getElementById('defaultRadiusInput');
    const mapEl = document.getElementById('defaultLocationMap');
    const mapTypeButtons = document.querySelectorAll('.map-type-btn');
    
    function syncMapFromSelection(recenter = true, resetZoom = true) {
        const location = readSelectedLocation();
        if (mapEl) {
            mapEl.dataset.lat = Number.isFinite(location.lat) ? String(location.lat) : '';
            mapEl.dataset.lng = Number.isFinite(location.lng) ? String(location.lng) : '';
        }
        applyLocation(location, { recenter, resetZoom });
    }

    if (defaultLocationSelect) {
        defaultLocationSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option && defaultRadiusInput) {
                defaultRadiusInput.value = option.dataset.radius || defaultRadiusInput.value || 100;
            }
            syncMapFromSelection(true, true);
        });
    }

    if (defaultRadiusInput) {
        defaultRadiusInput.addEventListener('input', function() {
            syncMapFromSelection(false, false);
        });
    }

    if (mapTypeButtons && mapTypeButtons.length) {
        const initialMapType = mapEl?.dataset.mapType || 'm';
        mapTypeButtons.forEach((btn) => {
            btn.classList.toggle('active', (btn.dataset.mapType || 'm') === initialMapType);
            btn.addEventListener('click', function() {
                mapTypeButtons.forEach((b) => b.classList.remove('active'));
                this.classList.add('active');
                setBaseLayer(this.dataset.mapType || 'm');
                if (currentLocation) {
                    updateOpenLink(currentLocation);
                }
            });
        });
    }

    syncMapFromSelection(true, true);
    const locationModal = $('#addLocationModal');

    // Edit location button
    $('.edit-location-btn').click(function() {
        const locationId = $(this).data('id');
        const locationName = $(this).data('name');
        const latitude = $(this).data('lat');
        const longitude = $(this).data('lng');
        const radius = $(this).data('radius');
        const address = $(this).data('address');
        const isActive = $(this).data('active');
        const isDefault = $(this).data('default') == 1;
        
        $('#locationId').val(locationId);
        $('#locationName').val(locationName);
        $('#latitude').val(latitude);
        $('#longitude').val(longitude);
        $('#radius').val(radius);
        $('#address').val(address);
        $('#isActive').val(isActive);
        $('#setAsDefault').prop('checked', isDefault);
        
        locationModal.data('mode', 'edit');
        $('.modal-title').text('Edit Lokasi');
        locationModal.modal('show');
    });
    
    // Set mode to add when clicking "Tambah Lokasi Baru"
    $('[data-bs-target="#addLocationModal"]').on('click', function() {
        locationModal.data('mode', 'add');
    });

    // Reset form when adding new
    locationModal.on('show.bs.modal', function() {
        if (locationModal.data('mode') === 'add') {
            $('#locationId').val(0);
            $('#locationName').val('');
            $('#latitude').val('');
            $('#longitude').val('');
            $('#radius').val(100);
            $('#address').val('');
            $('#isActive').val('Y');
            $('#setAsDefault').prop('checked', false);
            $('.modal-title').text('Tambah Lokasi Baru');
        }
    });
});

function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                $('#latitude').val(position.coords.latitude.toFixed(6));
                $('#longitude').val(position.coords.longitude.toFixed(6));
            },
            function(error) {
                alert('Gagal mendapatkan lokasi: ' + error.message);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        alert('Geolocation tidak didukung oleh browser Anda.');
    }
}
</script>
