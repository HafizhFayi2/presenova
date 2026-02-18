# Runbook Deploy Laravel-Only Presenova

## Tujuan
- Runtime produksi sepenuhnya berjalan lewat Laravel.
- URL `.php` yang sudah dipakai frontend tetap kompatibel via route Laravel.
- Tidak ada handler dinamis yang dieksekusi langsung dari file procedural lama.

## Pra-Deploy
Pastikan `.env` server berisi:
- `APP_KEY`
- `APP_URL`
- `DB_*`
- `JWT_REMEMBER_SECRET`
- `PYTHON_BIN` + `FACE_MATCH_*`
- `PUSH_ENABLED`, `PUSH_VAPID_PUBLIC_KEY`, `PUSH_VAPID_PRIVATE_KEY`, `PUSH_VAPID_SUBJECT`

## Backup
1. Backup database penuh (`mysqldump`).
2. Backup folder `public/uploads`.
3. Backup `.env`.

## Deploy Sequence
1. Upload source terbaru.
2. Install dependency:
   - `composer install --no-dev --optimize-autoloader`
3. Migrasi schema:
   - `php artisan migrate --force`
4. Refresh cache:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
5. Restart Apache/PHP-FPM.

## Post-Deploy Verification
1. `curl -I https://<host>/login.php` mengandung `X-Presenova-Stack: laravel-only`.
2. URL utama bisa diakses:
   - `/login.php`
   - `/dashboard/admin.php`
   - `/dashboard/guru.php`
   - `/dashboard/siswa.php`
   - `/api/face_matching.php`
   - `/api/save_attendance.php`
3. Remember login siswa via `attendance_token` tetap bekerja.
4. Force password siswa dan auto-rotate password guru tetap bekerja.
5. CRUD admin berjalan normal.
6. DeepFace verify + submit attendance berjalan normal.

## Rollback
1. Aktifkan maintenance mode.
2. Restore code dan `.env` dari backup.
3. Restore database jika perlu.
4. Restart service dan verifikasi login/dashboard.