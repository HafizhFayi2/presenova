<?php
$forceLogout = false;

// Ensure user active column exists
$hasUserActiveColumn = false;
try {
    $colStmt = $db->query("SHOW COLUMNS FROM user LIKE 'is_active'");
    $hasUserActiveColumn = $colStmt && $colStmt->fetch();
    if (!$hasUserActiveColumn) {
        $db->query("ALTER TABLE user ADD is_active ENUM('Y','N') NOT NULL DEFAULT 'Y'");
        $hasUserActiveColumn = true;
    }
} catch (Exception $e) {
    $hasUserActiveColumn = false;
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['user_id'])) {
        $user_id = $_POST['user_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $fullname = $_POST['fullname'];
        $level = $_POST['level'];
        
        if ($user_id == 0) {
            // Add new user
            $password = hash('sha256', 'admin123' . PASSWORD_SALT);
            if ($hasUserActiveColumn) {
                $sql = "INSERT INTO user (username, email, password, fullname, level, is_active) 
                        VALUES (?, ?, ?, ?, ?, 'Y')";
                $stmt = $db->query($sql, [$username, $email, $password, $fullname, $level]);
            } else {
                $sql = "INSERT INTO user (username, email, password, fullname, level) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->query($sql, [$username, $email, $password, $fullname, $level]);
            }
            $success = "User berhasil ditambahkan! Password default: admin123";
        } else {
            // Update user
            $sql = "UPDATE user SET username = ?, email = ?, fullname = ?, level = ? 
                    WHERE user_id = ?";
            $stmt = $db->query($sql, [$username, $email, $fullname, $level, $user_id]);
            $success = "User berhasil diperbarui!";
        }
    }
}

// Handle password reset (custom)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_user_id'])) {
    if (isset($canDeleteMaster) && !$canDeleteMaster) {
        $error = "Operator tidak memiliki izin mereset password user.";
        header("Location: admin.php?table=system&error=" . urlencode($error));
        exit();
    }

    $user_id = (int) ($_POST['reset_user_id'] ?? 0);
    $new_plain = trim((string) ($_POST['new_password'] ?? ''));

    if ($user_id <= 0) {
        $error = "User tidak valid.";
        header("Location: admin.php?table=system&error=" . urlencode($error));
        exit();
    }
    if ($new_plain === '' || strlen($new_plain) < 6) {
        $error = "Password minimal 6 karakter.";
        header("Location: admin.php?table=system&error=" . urlencode($error));
        exit();
    }
    if (strtolower($new_plain) === 'admin123') {
        $error = "Password tidak boleh menggunakan default admin123.";
        header("Location: admin.php?table=system&error=" . urlencode($error));
        exit();
    }

    $new_password = hash('sha256', $new_plain . PASSWORD_SALT);
    $sql = "UPDATE user SET password = ? WHERE user_id = ?";
    $db->query($sql, [$new_password, $user_id]);
    $success = "Password user berhasil diperbarui.";
    header("Location: admin.php?table=system&success=" . urlencode($success));
    exit();
}

// Handle delete
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    
    if (isset($canDeleteMaster) && !$canDeleteMaster) {
        $error = "Operator tidak memiliki izin menghapus data master.";
        header("Location: admin.php?table=system&error=" . urlencode($error));
        exit();
    }

    if ($user_id != 1) { // Prevent deleting main admin
        $sql = "DELETE FROM user WHERE user_id = ?";
        $db->query($sql, [$user_id]);
        if (function_exists('resetAutoIncrementIfEmpty')) {
            resetAutoIncrementIfEmpty($db, 'user', 0);
        }
        $success = "User berhasil dihapus!";
    } else {
        $error = "Tidak dapat menghapus admin utama!";
    }
}

// Toggle active status
if (isset($_GET['toggle_user'])) {
    $user_id = (int) $_GET['toggle_user'];
    $currentStatus = ($_GET['status'] ?? 'Y') === 'Y' ? 'Y' : 'N';
    $newStatus = $currentStatus === 'Y' ? 'N' : 'Y';

    if (isset($canDeleteMaster) && !$canDeleteMaster) {
        $error = "Operator tidak memiliki izin menonaktifkan user.";
        header("Location: admin.php?table=system&error=" . urlencode($error));
        exit();
    }

    if ($hasUserActiveColumn) {
        $db->query("UPDATE user SET is_active = ? WHERE user_id = ?", [$newStatus, $user_id]);
        $success = $newStatus === 'Y' ? 'User diaktifkan.' : 'User dinonaktifkan.';

        if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $user_id && $newStatus === 'N') {
            $forceLogout = true;
        }
    }
}

if ($forceLogout) {
    header("Location: ../logout.php?disabled=1");
    exit();
}

// Get user levels
$levels = $db->query("SELECT * FROM user_level")->fetchAll();

// Get system users (after actions)
$sql = "SELECT u.*, ul.level_name FROM user u 
        JOIN user_level ul ON u.level = ul.level_id 
        ORDER BY u.level, u.username";
$stmt = $db->query($sql);
$users = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <div class="table-container">
            <h5><i class="bi bi-person-plus"></i> Tambah User Baru</h5>
            <form method="POST" action="?table=system">
                <input type="hidden" name="user_id" value="0">
                
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" required 
                           pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="30">
                    <small class="text-muted">Huruf, angka, dan underscore saja</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" name="fullname" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Level User</label>
                    <select class="form-select" name="level" required>
                        <?php foreach($levels as $level): ?>
                        <option value="<?php echo $level['level_id']; ?>"><?php echo $level['level_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-save"></i> Tambah User
                </button>
                
                <div class="mt-3 alert alert-info">
                    <small><i class="bi bi-info-circle"></i> Password default: <strong>admin123</strong></small>
                </div>
            </form>
        </div>
        
        <div class="table-container mt-4">
            <h5><i class="bi bi-clipboard-data"></i> Log Sistem</h5>
            <div class="d-grid gap-2">
                <a href="ajax/download_system_logs.php" class="btn btn-success" data-no-loading="1">
                    <i class="bi bi-download"></i> Download Log Sistem
                </a>
            </div>

            <div class="mt-3">
                <h6><i class="bi bi-clock-history"></i> Login Terakhir</h6>
                <div style="max-height: 200px; overflow-y: auto; font-size: 0.8rem;">
                    <?php
                    // Get recent logs
                    $log_sql = "SELECT l.*,
                                       COALESCE(u.fullname, t.teacher_name, s.student_name, l.user_type) AS actor_name
                                FROM activity_logs l
                                LEFT JOIN user u ON l.user_type = 'admin' AND l.user_id = u.user_id
                                LEFT JOIN teacher t ON l.user_type = 'guru' AND l.user_id = t.id
                                LEFT JOIN student s ON (l.user_type = 'student' OR l.user_type = 'siswa') AND l.user_id = s.id
                                ORDER BY l.created_at DESC
                                LIMIT 10";
                    $log_stmt = $db->query($log_sql);
                    $logs = $log_stmt->fetchAll();
                    
                    foreach($logs as $log):
                    ?>
                    <div class="border-bottom py-1">
                        <small>
                            <strong><?php echo htmlspecialchars($log['actor_name'] ?? $log['user_type']); ?></strong>
                            <span class="text-muted">(<?php echo $log['user_type']; ?>)</span>
                            <?php echo $log['action']; ?><br>
                            <span class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="bi bi-people"></i> Daftar User Sistem</h5>
                <div class="input-group" style="width: 300px;">
                    <input type="text" class="form-control" id="searchUser" placeholder="Cari user...">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                </div>
            </div>
            
            <table class="table table-hover table-admin-users">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <?php $isActive = ($user['is_active'] ?? 'Y') === 'Y'; ?>
                    <tr>
                        <td><?php echo $user['username']; ?></td>
                        <td><?php echo $user['fullname']; ?></td>
                        <td><?php echo $user['email']; ?></td>
                        <td>
                            <span class="badge <?php echo $user['level'] == 1 ? 'bg-danger' : 'bg-primary'; ?>">
                                <?php echo $user['level_name']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $isActive ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $isActive ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if($user['last_login']): ?>
                            <small><?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?></small>
                            <?php else: ?>
                            <small class="text-muted">Belum pernah</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-user-btn"
                                    data-id="<?php echo $user['user_id']; ?>"
                                    data-username="<?php echo $user['username']; ?>"
                                    data-email="<?php echo $user['email']; ?>"
                                    data-fullname="<?php echo $user['fullname']; ?>"
                                    data-level="<?php echo $user['level']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                                <button class="btn btn-sm btn-info" disabled title="Operator tidak dapat mereset password user">
                                    <i class="bi bi-key"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-info reset-user-btn"
                                        data-id="<?php echo $user['user_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($user['fullname']); ?>">
                                    <i class="bi bi-key"></i>
                                </button>
                            <?php endif; ?>
                            <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                                <button class="btn btn-sm <?php echo $isActive ? 'btn-success' : 'btn-secondary'; ?>" disabled title="Operator tidak memiliki izin menonaktifkan user">
                                    <i class="bi bi-power"></i>
                                </button>
                            <?php else: ?>
                                <a href="?table=system&toggle_user=<?php echo $user['user_id']; ?>&status=<?php echo $isActive ? 'Y' : 'N'; ?>" 
                                   class="btn btn-sm <?php echo $isActive ? 'btn-success' : 'btn-secondary'; ?>" 
                                   onclick="return confirm('<?php echo $isActive ? 'Nonaktifkan' : 'Aktifkan'; ?> user ini?')"
                                   title="<?php echo $isActive ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                    <i class="bi bi-power"></i>
                                </a>
                            <?php endif; ?>
                            <?php if($user['user_id'] != 1): ?>
                                <?php if (isset($canDeleteMaster) && !$canDeleteMaster): ?>
                                    <button class="btn btn-sm btn-danger" disabled title="Operator tidak dapat menghapus data master">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="?table=system&delete_user=<?php echo $user['user_id']; ?>" 
                                       class="btn btn-sm btn-danger" onclick="return confirm('Hapus user ini?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                            <button class="btn btn-sm btn-danger" disabled title="Tidak dapat dihapus">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-container mt-4">
            <h5><i class="bi bi-activity"></i> Monitoring Sistem Realtime</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>Disk</h6>
                            <div id="diskList" class="small"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>RAM</h6>
                            <div class="d-flex justify-content-between">
                                <span>Terpakai</span>
                                <strong id="ramUsed">-</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Total</span>
                                <strong id="ramTotal">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>CPU</h6>
                            <div class="d-flex justify-content-between">
                                <span>Load</span>
                                <strong id="cpuLoad">-</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Uptime</span>
                                <strong id="serverUptime">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?table=system">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" id="editUsername" required 
                               pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="30">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editEmail">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="fullname" id="editFullname" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Level User</label>
                        <select class="form-select" name="level" id="editLevel" required>
                            <?php foreach($levels as $level): ?>
                            <option value="<?php echo $level['level_id']; ?>"><?php echo $level['level_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetUserPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?table=system" autocomplete="off">
                <div class="modal-body">
                    <input type="hidden" name="reset_user_id" id="resetUserId">
                    <div class="mb-2 text-muted small">
                        User: <strong id="resetUserName">-</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" class="form-control" name="new_password" id="resetUserPassword" minlength="6" required>
                        <div class="form-text">Minimal 6 karakter.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Search functionality
    $('#searchUser').on('keyup', function() {
        const search = $(this).val().toLowerCase();
        $('.table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(search) > -1);
        });
    });
    
    // Edit user button
    $('.edit-user-btn').click(function() {
        const userId = $(this).data('id');
        const username = $(this).data('username');
        const email = $(this).data('email');
        const fullname = $(this).data('fullname');
        const level = $(this).data('level');
        
        $('#editUserId').val(userId);
        $('#editUsername').val(username);
        $('#editEmail').val(email);
        $('#editFullname').val(fullname);
        $('#editLevel').val(level);
        
        $('#editUserModal').modal('show');
    });

    // Reset password modal
    $(document).on('click', '.reset-user-btn', function() {
        const userId = $(this).data('id');
        const userName = $(this).data('name');
        $('#resetUserId').val(userId);
        $('#resetUserName').text(userName || '-');
        $('#resetUserPassword').val('');
        $('#resetUserPasswordModal').modal('show');
    });

    function formatBytes(bytes) {
        if (bytes === null || bytes === undefined) return '-';
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        if (bytes === 0) return '0 B';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + sizes[i];
    }

    function renderDisks(disks) {
        if (!Array.isArray(disks) || disks.length === 0) {
            $('#diskList').html('<div>-</div>');
            return;
        }
        let html = '';
        disks.forEach(function(disk) {
            const used = formatBytes(disk.used);
            const total = formatBytes(disk.total);
            html += `<div class="d-flex justify-content-between">
                        <span>${disk.name}</span>
                        <strong>${used} / ${total}</strong>
                     </div>`;
        });
        $('#diskList').html(html);
    }

    function refreshSystemStats() {
        $.ajax({
            url: 'ajax/get_system_stats.php',
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success && resp.data) {
                    const disks = resp.data.disks || [];
                    const ramUsed = resp.data.ram_used;
                    const ramTotal = resp.data.ram_total;
                    const cpuLoad = resp.data.cpu_load;
                    const uptime = resp.data.uptime;

                    renderDisks(disks);
                    $('#ramUsed').text(formatBytes(ramUsed));
                    $('#ramTotal').text(formatBytes(ramTotal));
                    $('#cpuLoad').text(cpuLoad !== null ? cpuLoad : '-');
                    $('#serverUptime').text(uptime || '-');
                }
            }
        });
    }

    refreshSystemStats();
    setInterval(refreshSystemStats, 10000);
});

function optimizeDatabase() {
    if (confirm('Optimasi database untuk performa lebih baik?')) {
        $.ajax({
            url: 'ajax/optimize_database.php',
            method: 'POST',
            success: function(response) {
                alert('Database berhasil dioptimasi!');
            }
        });
    }
}

function saveSecuritySettings() {
    const settings = {
        forceSSL: $('#forceSSL').is(':checked'),
        rateLimit: $('#rateLimit').is(':checked'),
        auditLog: $('#auditLog').is(':checked')
    };
    
    $.ajax({
        url: 'ajax/save_security.php',
        method: 'POST',
        data: settings,
        success: function(response) {
            alert('Pengaturan keamanan berhasil disimpan!');
        }
    });
}
</script>
