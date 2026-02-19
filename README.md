# Presenova

Presenova adalah sistem presensi sekolah berbasis web + PWA dengan validasi waktu, lokasi, dan wajah.

## Status Arsitektur
Runtime aplikasi sudah Laravel-only:
- Front controller tunggal: `public/index.php`
- Endpoint dashboard, auth, API, dan AJAX seluruhnya ditangani controller Laravel
- URL lama berbentuk `.php` tetap tersedia sebagai kompatibilitas route

## Struktur Proyek
```text
presenova/
|- app/                    # Controller, service, middleware, support
|- bootstrap/              # Bootstrap aplikasi Laravel
|- config/                 # Konfigurasi Laravel
|- database/               # Migration/seeders
|- public/                 # Web root (assets, uploads, face, index.php)
|- resources/views/        # View dashboard, pages, utility
|- routes/                 # Route Laravel
|- scripts/                # Script otomasi (DeepFace, migrasi)
|- storage/                # Cache, logs, runtime files
|- tests/                  # Test suite
```

## Fitur Utama
- Login role admin/guru/siswa
- Remember login siswa via cookie JWT `attendance_token`
- Dashboard admin/guru/siswa
- CRUD master data (siswa, guru, kelas, jurusan, jadwal, lokasi)
- Absensi siswa (hadir/sakit/izin) + validasi lokasi
- Face recognition DeepFace untuk verifikasi absensi
- Export laporan Excel (`.xlsx` via PhpSpreadsheet)
- Push notification web (VAPID)

## Konfigurasi Environment
Minimal variabel yang harus diisi:
- Database: `DB_*`
- JWT remember: `JWT_REMEMBER_SECRET`
- Face matcher: `PYTHON_BIN`, `FACE_MATCH_*`
- Push: `PUSH_ENABLED`, `PUSH_VAPID_PUBLIC_KEY`, `PUSH_VAPID_PRIVATE_KEY`, `PUSH_VAPID_SUBJECT`

Lihat contoh lengkap di `.env.example`.

## Menjalankan Lokal
1. Jalankan Apache + MySQL (XAMPP).
2. Pastikan extension PHP yang dibutuhkan aktif (`pdo_mysql`, `openssl`, `mbstring`, `gd`).
3. Install dependency:
```bash
composer install
```
4. Generate key jika belum ada:
```bash
php artisan key:generate
```
5. Jalankan migrasi jika diperlukan:
```bash
php artisan migrate
```
6. Akses aplikasi:
- `http://localhost/presenova`

## Setup DeepFace
```bash
bash scripts/setup_deepface.sh --write-env
```

## Setup Cron Push (VPS Linux)
Jalankan installer cron (idempotent, aman di-run ulang):

```bash
bash scripts/install_push_cron_linux.sh /var/www/presenova
```

Catatan:
- Script akan memasang cron per 1 menit untuk menjalankan `public/cron/send_notifications.php`.
- Jika `flock` tersedia, eksekusi cron diberi lock agar tidak overlap.
- Log cron tersimpan di `storage/logs/push-cron.log`.

Verifikasi:

```bash
crontab -l | grep PRESENOVA_PUSH_CRON
tail -f /var/www/presenova/storage/logs/push-cron.log
```

### Pastikan Subscription Siswa Masuk ke `push_tokens`
1. Siswa login ke dashboard siswa (PWA aktif di halaman ini).
2. Klik tombol aktifkan notifikasi jika browser meminta izin.
3. Setelah izin `Allow`, subscription otomatis disimpan ke endpoint `api/save-subscription.php`.

Cek di MySQL:

```sql
SELECT id, student_id, is_active, created_at, updated_at
FROM push_tokens
ORDER BY id DESC
LIMIT 20;
```

## Verifikasi
- `php artisan route:list`
- Login admin/guru/siswa
- CRUD dashboard
- Face matching + save attendance
- Export Excel `.xlsx`
