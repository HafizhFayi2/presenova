<?php
/** @var array<string, mixed> $attendance */
/** @var string $photoPath */
?>
<div class="row">
    <div class="col-md-6">
        <h6>Detail Siswa</h6>
        <table class="table table-sm">
            <tr>
                <th>NISN:</th>
                <td><?= htmlspecialchars((string) ($attendance['student_nisn'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th>Nama:</th>
                <td><?= htmlspecialchars((string) ($attendance['student_name'] ?? '-')) ?></td>
            </tr>
            <tr>
                <th>Kelas:</th>
                <td><?= htmlspecialchars((string) ($attendance['class_name'] ?? '-')) ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6>Detail Absensi</h6>
        <table class="table table-sm">
            <tr>
                <th>Tanggal:</th>
                <td><?= !empty($attendance['presence_date']) ? date('d/m/Y', strtotime((string) $attendance['presence_date'])) : '-' ?></td>
            </tr>
            <tr>
                <th>Jam:</th>
                <td><?= !empty($attendance['time_in']) ? date('H:i:s', strtotime((string) $attendance['time_in'])) : '-' ?></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <?php if ((int) ($attendance['present_id'] ?? 0) === 1): ?>
                        <span class="badge <?= strtoupper((string) ($attendance['is_late'] ?? 'N')) === 'Y' ? 'bg-warning' : 'bg-success' ?>">
                            <?= strtoupper((string) ($attendance['is_late'] ?? 'N')) === 'Y' ? 'Terlambat' : htmlspecialchars((string) ($attendance['present_name'] ?? 'Hadir')) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-info"><?= htmlspecialchars((string) ($attendance['present_name'] ?? '-')) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (strtoupper((string) ($attendance['is_late'] ?? 'N')) === 'Y'): ?>
            <tr>
                <th>Keterlambatan:</th>
                <td class="text-warning"><?= (int) ($attendance['late_time'] ?? 0) ?> menit</td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($attendance['subject'])): ?>
            <tr>
                <th>Mata Pelajaran:</th>
                <td><?= htmlspecialchars((string) $attendance['subject']) ?></td>
            </tr>
            <tr>
                <th>Guru:</th>
                <td><?= htmlspecialchars((string) ($attendance['teacher_name'] ?? '-')) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if (!empty($attendance['latitude_in']) && !empty($attendance['longitude_in'])): ?>
<div class="mt-3">
    <h6>Lokasi Absensi</h6>
    <p class="mb-1">Koordinat: <?= htmlspecialchars((string) $attendance['latitude_in']) ?>, <?= htmlspecialchars((string) $attendance['longitude_in']) ?></p>
    <?php if (!empty($attendance['distance_in'])): ?>
        <p class="mb-0">Jarak dari sekolah: <?= (int) $attendance['distance_in'] ?> meter</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($attendance['information'])): ?>
<div class="mt-3">
    <h6>Keterangan</h6>
    <p><?= htmlspecialchars((string) $attendance['information']) ?></p>
</div>
<?php endif; ?>

<?php if ($photoPath !== ''): ?>
<div class="mt-3">
    <h6>Foto Absensi</h6>
    <img src="<?= htmlspecialchars($photoPath) ?>" class="img-fluid rounded" style="max-width: 320px;">
</div>
<?php endif; ?>
