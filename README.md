# Presenova - Dokumentasi Gabungan

Dokumen ini merupakan penggabungan seluruh file `*.md` utama proyek ke satu file `README.md`.

## Daftar Sumber
- `README.md`
- `mockup.md`
- `framework.md`
- `workflow.md`
- `docs/cutover-runbook-laravel-only.md`
- `docs/kebijakan-retensi-data-biometrik-lokasi.md`
- `concept.md`

---

## Sumber: `README.md`

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

## Konfigurasi Apache (Windows + Linux)
Konfigurasi `DocumentRoot` wajib ke folder `public`, bukan ke `resources/views/pages`.

Jika salah (mis. ke `resources/views/pages`), route Laravel tidak aktif dan tombol bisa loop / URL duplikat.

### Linux VPS (`/var/www/presenova`)
Contoh VirtualHost:

```apache
<VirtualHost *:80>
    ServerName presenova.my.id
    ServerAdmin admin@presenova.my.id
    DocumentRoot /var/www/presenova/public

    <Directory /var/www/presenova/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/presenova-error.log
    CustomLog ${APACHE_LOG_DIR}/presenova-access.log combined
</VirtualHost>
```

Template siap pakai juga tersedia di: `scripts/apache-vhost-linux.conf`.

### Windows XAMPP (`C:/xampp/htdocs/presenova`)
Jika memakai host `localhost/presenova`, pastikan root `.htaccess` aktif (`AllowOverride All`) atau gunakan VirtualHost:

```apache
<VirtualHost *:80>
    ServerName presenova.local
    DocumentRoot "C:/xampp/htdocs/presenova/public"

    <Directory "C:/xampp/htdocs/presenova/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Template siap pakai juga tersedia di: `scripts/apache-vhost-windows.conf`.

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

---

## Sumber: `mockup.md`

# Presenova - Dokumen Sistem, Fungsi, dan Alur Kerja (Update 20 Februari 2026)

Dokumen ini menggantikan versi lama dan merangkum kondisi sistem Presenova yang aktif saat ini.

## 1. Ringkasan Platform

Presenova adalah sistem presensi sekolah berbasis web + PWA untuk 3 role utama:
- Siswa
- Guru
- Admin/Operator

Fokus utama sistem:
- Validasi kehadiran berbasis jadwal
- Verifikasi lokasi (GPS radius sekolah)
- Verifikasi wajah (client + DeepFace server)
- Rekap kehadiran dan pelaporan
- Notifikasi terjadwal dan notifikasi event penting

## 2. Status Arsitektur Saat Ini

### 2.1 Status runtime
- Runtime aplikasi sudah Laravel-only.
- Seluruh request dinamis diproses oleh `public/index.php` (Laravel front controller).
- URL lama `.php` tetap aktif sebagai compatibility route (mis. `login.php`, `dashboard/siswa.php`, `api/save_attendance.php`), namun handler tetap controller Laravel.

### 2.2 Komponen inti
- Routing: `routes/web.php`
- Controller utama:
  - `app/Http/Controllers/HomeController.php`
  - `app/Http/Controllers/Auth/LoginController.php`
  - `app/Http/Controllers/Dashboard/DashboardPageController.php`
  - `app/Http/Controllers/Dashboard/Ajax/DashboardAjaxController.php`
  - `app/Http/Controllers/Api/ApiController.php`
  - `app/Http/Controllers/Dashboard/Print/SchedulePrintController.php`
- Middleware kompatibilitas:
  - `app/Http/Middleware/RememberTokenBridge.php`
  - `app/Http/Middleware/NativePhpSessionBridge.php`
  - `app/Http/Middleware/CanonicalPublicPathRedirect.php`
- Header verifikasi stack:
  - `X-Presenova-Stack: laravel-only`

### 2.3 Landing flow
- Root `/` saat ini menampilkan halaman Get Started.
- Tombol `Get Started` mengarah ke `login.php`.
- Tombol `More To Know About Us` mengarah ke `index.php` (landing produk utama).

## 3. Fungsi Website per Role

### 3.1 Admin/Operator
Fungsi utama:
- CRUD data master:
  - siswa, guru, kelas, jurusan, jadwal, lokasi sekolah, user sistem
- Manajemen keamanan dasar:
  - reset password siswa (ke NISN)
  - reset password guru (ke `guru123`)
- Monitoring:
  - statistik absensi
  - data sistem, log, dan utilitas maintenance
- Rekap absensi:
  - status hadir, sakit, izin, terlambat, alpa, menunggu
- Export:
  - Excel `.xlsx` (PhpSpreadsheet)
  - print/download PDF

### 3.2 Guru
Fungsi utama:
- Akses dashboard guru
- Lihat jadwal mengajar
- Lihat data absensi terkait kelas/jadwal
- Update profil dan password
- Print jadwal guru

Keamanan guru:
- Jika password default `guru123` terdeteksi, sistem auto-rotate password ke format `P@ssw0rdTC###`.
- Password baru ditampilkan di popup (copy + download PNG) untuk disimpan guru.

### 3.3 Siswa
Fungsi utama:
- Dashboard siswa (jadwal, verifikasi wajah, riwayat absensi, profil)
- Absensi mode:
  - hadir
  - sakit
  - izin
- Verifikasi lokasi + wajah sebelum submit absensi hadir
- Riwayat absensi dan status keterlambatan/alpa
- Monitoring RAM client saat proses face verification

Keamanan siswa:
- Login menggunakan NISN.
- Default password akun siswa saat dibuat admin: NISN siswa.
- Jika terdeteksi masih default (termasuk pola lama seperti `siswa123`), siswa dipaksa ganti password sebelum akses dashboard normal.

## 4. Cara Kerja Login dan Session

### 4.1 Login siswa
1. Siswa login memakai NISN + password.
2. Jika valid, session siswa dibuat.
3. Jika `remember` aktif, token cookie tetap disimpan untuk auto-login (`attendance_token` / `remember_token`).
4. Jika foto referensi/pose belum lengkap, diarahkan ke halaman registrasi wajah.
5. Jika password default, diarahkan ke halaman wajib ganti password.

### 4.2 Login guru
1. Guru login memakai `teacher_code` atau `teacher_username`.
2. Jika valid, session guru dibuat.
3. Saat dashboard terbuka, sistem mengecek default password.
4. Jika default, sistem langsung rotate password dan menampilkan popup informasi.

### 4.3 Login admin/operator
1. Admin login via `user` level admin/operator.
2. Session role admin dibuat.
3. Akses penuh ke dashboard manajemen sesuai level.

## 5. Alur Absensi Siswa End-to-End

1. Siswa membuka menu Verifikasi Wajah.
2. Sistem mengambil jadwal aktif berdasarkan waktu server + jadwal siswa.
3. Sistem validasi lokasi:
   - baca geolocation browser,
   - hitung jarak ke titik sekolah,
   - cek radius aktif.
4. Sistem validasi wajah:
   - client-side check (face-api.js) untuk kualitas/deteksi awal,
   - server-side check via endpoint `api/face_matching.php` (DeepFace).
5. Jika lolos verifikasi, siswa submit absensi ke `api/save_attendance.php`.
6. Status absensi tersimpan ke database dan muncul di riwayat.
7. Jika jadwal tutup tanpa absensi final, status akan masuk kategori alpa.

## 6. Notifikasi Sistem (PWA Push)

### 6.1 Notifikasi event langsung
- DeepFace verifikasi berhasil (menampilkan skor kemiripan).
- Lokasi siswa di luar radius.
- Absensi berhasil disimpan (hadir/sakit/izin).
- Perubahan password siswa/guru.
- Reset password via lupa password.
- Event error sistem tertentu.

### 6.2 Notifikasi terjadwal via cron (`public/cron/send_notifications.php`)
- Pengingat H-5 menit jadwal mulai.
- Pengingat H-2 menit jadwal mulai.
- Notifikasi saat jadwal mulai.
- Notifikasi sisa 10 menit sebelum jadwal selesai.
- Notifikasi masuk waktu toleransi.
- Notifikasi overdue saat melewati batas toleransi.
- Pengingat tiap 10 menit jika belum absensi dalam rentang jadwal aktif.
- Notifikasi alpa saat jadwal ditutup tanpa absensi.
- Ringkasan jadwal esok hari (sekitar pukul 18:00 WIB).
- Rekap reset mingguan hari Sabtu (sekitar pukul 15:00 WIB).

## 7. Monitoring Performa (Face Verification)

Pada panel siswa, monitoring RAM memakai JS Heap observer dengan budget 4GB:
- Profil cepat dipacu sejak heap rendah (0-10MB baseline).
- Throttling aktif saat penggunaan mendekati/melewati 85% budget.
- Tujuan: verifikasi wajah tetap responsif dan mencegah browser hang.

## 8. Keamanan, Audit, dan Retensi Data

### 8.1 Secret dan konfigurasi
Semua secret disimpan di `.env`, termasuk:
- `JWT_REMEMBER_SECRET`
- `PASSWORD_SALT`
- `PUSH_VAPID_PUBLIC_KEY`
- `PUSH_VAPID_PRIVATE_KEY`
- `PUSH_VAPID_SUBJECT`
- `PUSH_ENABLED`
- `PUSH_TTL_SECONDS`
- `PYTHON_BIN` dan `FACE_MATCH_*`

### 8.2 Audit trail
- Aktivitas sensitif (reset password, perubahan data penting, dan event keamanan) dicatat.
- Struktur audit mendukung before/after metadata untuk perubahan master data.

### 8.3 Kebijakan retensi
- Biometrik: 180 hari
- Lokasi: 365 hari
- Dokumen kebijakan: `docs/kebijakan-retensi-data-biometrik-lokasi.md`

## 9. Alur Data Antar Modul

1. Admin menyiapkan master data (guru/siswa/kelas/jadwal/lokasi).
2. Siswa login dan sinkron jadwal berdasarkan kelas.
3. Siswa verifikasi lokasi + wajah.
4. Absensi disimpan ke tabel presence/student_schedule.
5. Dashboard admin/guru membaca data absensi untuk rekap dan monitoring.
6. Modul export/print mengambil data final yang sama (single source database).
7. Push service mengirim notifikasi berdasarkan event dan cron scheduler.

## 10. Deploy dan Path Lintas Windows/Linux

Agar alur tidak looping ke halaman awal:
- Apache/Nginx harus mengarah ke folder `public`, bukan `resources/views/pages`.
- Linux vhost:
  - `DocumentRoot /var/www/presenova/public`
- Windows XAMPP:
  - `DocumentRoot C:/xampp/htdocs/presenova/public` (atau gunakan rewrite root project yang aktif)

Dokumen dan template:
- `README.md`
- `scripts/apache-vhost-linux.conf`
- `scripts/apache-vhost-windows.conf`

## 11. Pro dan Cons Terkini

### Pro
- Runtime tunggal Laravel, lebih stabil dan mudah dipelihara.
- URL legacy tetap kompatibel.
- Alur pengguna lama tetap sama, UI tetap konsisten.
- Integrasi DeepFace, export `.xlsx`, print PDF, dan push PWA sudah aktif.

### Cons
- Beberapa file view dashboard masih besar dan procedural style (belum komponen Blade penuh).
- Beberapa endpoint kompatibilitas masih dikecualikan dari CSRF untuk menjaga backward compatibility.
- Optimasi query/performa dashboard besar masih perlu iterasi lanjutan.

---

Dokumen ini menjadi acuan operasional terbaru untuk tim pengembang, operator sekolah, dan proses deploy server Presenova.

---

## Sumber: `framework.md`

# Presenova Framework Notes

## Status Stack
Presenova sekarang berjalan 100% melalui kernel Laravel.

- Semua request dinamis masuk ke `public/index.php`.
- URL lama berbentuk `.php` tetap tersedia, tetapi dipetakan ke route/controller Laravel.
- File handler PHP procedural lama sudah tidak dipakai sebagai runtime.

## Struktur Runtime
- `app/Http/Controllers`: handler halaman dashboard, auth, utility, API.
- `app/Services`: business logic (absensi, face matching, sinkron jadwal, dll).
- `app/Http/Middleware`:
  - `RememberTokenBridge`: restore session dari cookie remember JWT (`attendance_token`).
  - `NativePhpSessionBridge`: sinkronisasi session key agar flow lama tetap kompatibel di sisi frontend.
  - `PresenovaStackHeader`: header verifikasi `X-Presenova-Stack: laravel-only`.
- `resources/views`: UI dashboard/admin/guru/siswa + section.
- `routes/web.php`: pemetaan endpoint utama, termasuk URL `.php` yang masih dipakai frontend.

## Routing dan Kompatibilitas URL
Rute kompatibel yang dipertahankan:
- Auth: `/login.php`, `/logout.php`, `/dashboard/login.php`, `/dashboard/logout.php`
- Dashboard: `/dashboard/admin.php`, `/dashboard/guru.php`, `/dashboard/siswa.php`
- API: `/api/face_matching.php`, `/api/save_attendance.php`, dll.
- AJAX dashboard: `/dashboard/ajax/*.php`

Semua rute di atas diproses Laravel, bukan dieksekusi sebagai file PHP di webroot.

## Konfigurasi `.env`
Konfigurasi utama sudah dipusatkan di environment:
- App/runtime: `SITE_NAME`, `SITE_URL`, `ATTENDANCE_RADIUS`, `PASSWORD_SALT`, `JWT_EXPIRE_DAYS`
- Remember token: `JWT_REMEMBER_SECRET`
- Face recognition: `PYTHON_BIN`, `FACE_MATCH_*`
- Push notification: `PUSH_ENABLED`, `PUSH_VAPID_PUBLIC_KEY`, `PUSH_VAPID_PRIVATE_KEY`, `PUSH_VAPID_SUBJECT`

## DeepFace
DeepFace dijalankan melalui script Python:
- `public/face/faces_conf/face_match.py`

Setup otomatis:
```bash
bash scripts/setup_deepface.sh --write-env
```

## Verifikasi Cepat
1. `php artisan route:list` harus sukses.
2. `curl -I http://localhost/presenova/login.php` mengandung `X-Presenova-Stack: laravel-only`.
3. Login admin/guru/siswa berjalan normal.
4. CRUD dashboard (siswa, guru, kelas, jurusan, jadwal) berjalan normal.
5. Face matching dan save attendance berjalan normal.

---

## Sumber: `workflow.md`

# Workflow Presenova (Ringkasan)

Dokumen workflow telah digabung ke `README.md` agar dokumentasi tidak terpecah.

Silakan gunakan `README.md` sebagai referensi utama untuk:

- Struktur folder proyek.
- Alur kerja sistem dari halaman awal sampai absensi.
- Fitur dashboard per role (Admin, Guru, Siswa).
- Kompatibilitas perangkat dan ringkasan keamanan.

Dokumen terkait:

- `README.md`
- `framework.md`
- `concept.md`

---

## Sumber: `docs/cutover-runbook-laravel-only.md`

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

---

## Sumber: `docs/kebijakan-retensi-data-biometrik-lokasi.md`

# Kebijakan Retensi Data Biometrik dan Lokasi Presenova

Dokumen ini menetapkan kebijakan retensi data untuk transparansi komunitas sekolah.

## 1. Ruang Lingkup Data

- Data biometrik:
  - foto referensi wajah siswa,
  - foto selfie verifikasi absensi,
  - metadata verifikasi wajah.
- Data lokasi:
  - koordinat lokasi absensi (`latitude`, `longitude`),
  - jarak dari titik sekolah,
  - metadata validasi lokasi.

## 2. Durasi Retensi

- Biometrik: **180 hari** sejak data dibuat.
- Lokasi: **365 hari** sejak data dibuat.

## 3. Prinsip Akses

- Data hanya boleh diakses oleh pihak berwenang sesuai role sistem.
- Akses data sensitif wajib memiliki tujuan operasional yang jelas.
- Aktivitas perubahan data penting dicatat pada audit trail.

## 4. Penghapusan Data

- Setelah melewati durasi retensi, data dapat dijadwalkan untuk dihapus secara aman.
- Penghapusan dilakukan tanpa menghapus metadata audit yang diperlukan untuk kepatuhan internal.

## 5. Transparansi Komunitas

- Kebijakan ini menjadi acuan resmi untuk tim teknis, admin sekolah, dan komunitas pengguna.
- Perubahan kebijakan retensi wajib didokumentasikan dan diumumkan secara internal.

## 6. Catatan Implementasi Saat Ini

- Pada fase ini, kebijakan disediakan dalam bentuk dokumen resmi.
- Otomasi purge/cleanup data belum diaktifkan pada dokumen ini.

---

## Sumber: `concept.md`

# File Tree: presenova

**Generated:** 2/13/2026, 11:35:58 AM
**Root Path:** `c:\xampp\htdocs\presenova`

```
â”œâ”€â”€ ðŸ“ api
â”‚   â”œâ”€â”€ ðŸ˜ check_location.php
â”‚   â”œâ”€â”€ ðŸ˜ face_matching.php
â”‚   â”œâ”€â”€ ðŸ˜ get-public-key.php
â”‚   â”œâ”€â”€ ðŸ˜ get_attendance_details.php
â”‚   â”œâ”€â”€ ðŸ˜ get_schedule.php
â”‚   â”œâ”€â”€ ðŸ˜ register_face.php
â”‚   â”œâ”€â”€ ðŸ˜ remove-subscription.php
â”‚   â”œâ”€â”€ ðŸ˜ save-subscription.php
â”‚   â”œâ”€â”€ ðŸ˜ save_attendance.php
â”‚   â”œâ”€â”€ ðŸ˜ submit_attendance.php
â”‚   â””â”€â”€ ðŸ˜ sync_schedule.php
â”œâ”€â”€ ðŸ“ assets
â”‚   â”œâ”€â”€ ðŸ“ css
â”‚   â”‚   â”œâ”€â”€ ðŸŽ¨ admin.css
â”‚   â”‚   â”œâ”€â”€ ðŸŽ¨ pwa.css
â”‚   â”‚   â””â”€â”€ ðŸŽ¨ style.css
â”‚   â”œâ”€â”€ ðŸ“ images
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-192x192-white background.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-192x192_404.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-192x192_login.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-192x192_student.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-192x192_teach.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-512x512-white background.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-512x512_404.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-512x512_login.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-512x512_student.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-chrome-512x512_teach.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-presenova-192x192-black background.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ android-presenova-512x512-black background.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ apple-presenova-icon-black background.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ apple-touch-icon-white background.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ apple-touch-icon_404.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ apple-touch-icon_login.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ apple-touch-icon_student.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ apple-touch-icon_teach.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-16x16-black background.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-16x16-white background.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-16x16_404.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-16x16_login.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-16x16_student.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-16x16_teach.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-32x32_404.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-32x32_admin.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-32x32_login.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-32x32_student.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ favicon-32x32_teach.png
â”‚   â”‚   â”œâ”€â”€ ðŸ“„ favicon-black background.ico
â”‚   â”‚   â”œâ”€â”€ ðŸ“„ favicon-white background.ico
â”‚   â”‚   â”œâ”€â”€ ðŸ“„ favicon_404.ico
â”‚   â”‚   â”œâ”€â”€ ðŸ“„ favicon_login.ico
â”‚   â”‚   â”œâ”€â”€ ðŸ“„ favicon_student.ico
â”‚   â”‚   â”œâ”€â”€ ðŸ“„ favicon_teach.ico
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ logo-192.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ logo-512.png
â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ presenova.png
â”‚   â”‚   â””â”€â”€ ðŸ“„ site.webmanifest
â”‚   â””â”€â”€ ðŸ“ js
â”‚       â”œâ”€â”€ ðŸ“„ app.js
â”‚       â”œâ”€â”€ ðŸ“„ camera.js
â”‚       â”œâ”€â”€ ðŸ“„ location.js
â”‚       â”œâ”€â”€ ðŸ“„ main.js
â”‚       â””â”€â”€ ðŸ“„ pwa.js
â”œâ”€â”€ ðŸ“ config
â”‚   â”œâ”€â”€ ðŸ“ push_nontification
â”‚   â”‚   â”œâ”€â”€ ðŸ“ server
â”‚   â”‚   â”‚   â”œâ”€â”€ ðŸ“„ app.js
â”‚   â”‚   â”‚   â””â”€â”€ âš™ï¸ package.json
â”‚   â”‚   â”œâ”€â”€ ðŸ“ README.md
â”‚   â”‚   â”œâ”€â”€ ðŸ“„ script.js
â”‚   â”‚   â””â”€â”€ ðŸ“„ sw.js
â”‚   â”œâ”€â”€ ðŸ˜ database_queries.php
â”‚   â””â”€â”€ ðŸ˜ push.php
â”œâ”€â”€ ðŸ“ cron
â”‚   â””â”€â”€ ðŸ˜ send_notifications.php
â”œâ”€â”€ ðŸ“ dashboard
â”‚   â”œâ”€â”€ ðŸ“ ajax
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ add_class.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ add_jurusan.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ add_student.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ check_schedule.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ config.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ download_system_logs.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ edit_student.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ get_attendance_details.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ get_attendance_stats.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ get_data.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ get_form.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ get_schedule_form.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ get_system_stats.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ load_attendance_form.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ reset_password.php
â”‚   â”‚   â””â”€â”€ ðŸ˜ save_attendance.php
â”‚   â”œâ”€â”€ ðŸ“ sections
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ attendance.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ class.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ dashboard.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ dashboard_siswa.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ face_recognition.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ guru_absensi.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ guru_dashboard.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ guru_laporan.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ guru_profil.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ jadwal.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ location.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ profil.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ profil_siswa.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ riwayat.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ schedule.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ student.php
â”‚   â”‚   â”œâ”€â”€ ðŸ˜ system.php
â”‚   â”‚   â””â”€â”€ ðŸ˜ teacher.php
â”‚   â”œâ”€â”€ ðŸ˜ admin.php
â”‚   â”œâ”€â”€ ðŸ˜ guru.php
â”‚   â””â”€â”€ ðŸ˜ siswa.php
â”œâ”€â”€ ðŸ“ face
â”‚   â”œâ”€â”€ ðŸ“ faces_conf
â”‚   â”‚   â”œâ”€â”€ ðŸ“ data
â”‚   â”‚   â”‚   â”œâ”€â”€ ðŸ“„ faces.pkl
â”‚   â”‚   â”‚   â”œâ”€â”€ âš™ï¸ haarcascade_frontalface_default.xml
â”‚   â”‚   â”‚   â””â”€â”€ ðŸ“„ names.pkl
â”‚   â”‚   â”œâ”€â”€ ðŸ add_faces.py
â”‚   â”‚   â”œâ”€â”€ ðŸ app.py
â”‚   â”‚   â”œâ”€â”€ ðŸ face_match.py
â”‚   â”‚   â””â”€â”€ ðŸ test.py
â”‚   â””â”€â”€ ðŸ“ faces_logics
â”‚       â”œâ”€â”€ ðŸ“ models
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ age_gender_model-shard1
â”‚       â”‚   â”œâ”€â”€ âš™ï¸ age_gender_model-weights_manifest.json
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ face_expression_model-shard1
â”‚       â”‚   â”œâ”€â”€ âš™ï¸ face_expression_model-weights_manifest.json
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ face_landmark_68_model-shard1
â”‚       â”‚   â”œâ”€â”€ âš™ï¸ face_landmark_68_model-weights_manifest.json
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ face_landmark_68_tiny_model-shard1
â”‚       â”‚   â”œâ”€â”€ âš™ï¸ face_landmark_68_tiny_model-weights_manifest.json
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ face_recognition_model-shard1
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ face_recognition_model-shard2
â”‚       â”‚   â”œâ”€â”€ âš™ï¸ face_recognition_model-weights_manifest.json
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ mtcnn_model-shard1
â”‚       â”‚   â”œâ”€â”€ âš™ï¸ mtcnn_model-weights_manifest.json
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ ssd_mobilenetv1_model-shard1
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ ssd_mobilenetv1_model-shard2
â”‚       â”‚   â”œâ”€â”€ âš™ï¸ ssd_mobilenetv1_model-weights_manifest.json
â”‚       â”‚   â”œâ”€â”€ ðŸ“„ tiny_face_detector_model-shard1
â”‚       â”‚   â””â”€â”€ âš™ï¸ tiny_face_detector_model-weights_manifest.json
â”‚       â””â”€â”€ ðŸ“„ script.js
â”œâ”€â”€ ðŸ“ helpers
â”‚   â”œâ”€â”€ ðŸ˜ attendance_helper.php
â”‚   â””â”€â”€ ðŸ˜ jp_time_helper.php
â”œâ”€â”€ ðŸ“ includes
â”‚   â”œâ”€â”€ ðŸ˜ auth.php
â”‚   â”œâ”€â”€ ðŸ˜ config.php
â”‚   â”œâ”€â”€ ðŸ˜ database.php
â”‚   â”œâ”€â”€ ðŸ˜ database_helper.php
â”‚   â”œâ”€â”€ ðŸ˜ face_matcher.php
â”‚   â”œâ”€â”€ ðŸ˜ face_recognition.php
â”‚   â”œâ”€â”€ ðŸ˜ functions.php
â”‚   â”œâ”€â”€ ðŸ˜ global_error_handler.php
â”‚   â””â”€â”€ ðŸ˜ push_service.php
â”œâ”€â”€ ðŸ“ scripts
â”‚   â””â”€â”€ ðŸ“ webpush
â”‚       â”œâ”€â”€ âš™ï¸ package-lock.json
â”‚       â”œâ”€â”€ âš™ï¸ package.json
â”‚       â””â”€â”€ ðŸ“„ send.js
â”œâ”€â”€ ðŸ“ uploads
â”‚   â”œâ”€â”€ ðŸ“ attendance
â”‚   â”‚   â”œâ”€â”€ ðŸ“ 2026-02-10
â”‚   â”‚   â”‚   â”œâ”€â”€ ðŸ–¼ï¸ ATT_10_20260210_163809.jpg
â”‚   â”‚   â”‚   â””â”€â”€ ðŸ–¼ï¸ VAL_10_20260210_163809.jpg
â”‚   â”‚   â””â”€â”€ ðŸ“ 2026-02-11
â”‚   â”‚       â””â”€â”€ ðŸ–¼ï¸ hapis_10_20260211_101402.jpg
â”‚   â””â”€â”€ ðŸ“ faces
â”‚       â”œâ”€â”€ ðŸ–¼ï¸ 123456-HAPIS_1770691597.jpg
â”‚       â””â”€â”€ ðŸ–¼ï¸ 1234567-FF_1770688071.jpg
â”œâ”€â”€ âš™ï¸ .htaccess
â”œâ”€â”€ ðŸ˜ 404.php
â”œâ”€â”€ ðŸ˜ call.php
â”œâ”€â”€ ðŸ˜ debug_login.php
â”œâ”€â”€ ðŸ˜ fix_database.php
â”œâ”€â”€ ðŸ˜ hash_generator.php
â”œâ”€â”€ ðŸ˜ index.php
â”œâ”€â”€ ðŸ˜ login.php
â”œâ”€â”€ ðŸ˜ logout.php
â”œâ”€â”€ âš™ï¸ manifest.json
â”œâ”€â”€ ðŸ“„ presenova.sql
â”œâ”€â”€ ðŸ˜ register.php
â”œâ”€â”€ ðŸ˜ reset_password.php
â”œâ”€â”€ ðŸ“„ service-worker.js
â”œâ”€â”€ ðŸ˜ test_login.php
â”œâ”€â”€ ðŸ˜ test_sesion.php
â””â”€â”€ ðŸ˜ verify_salt.php
```

---

---

