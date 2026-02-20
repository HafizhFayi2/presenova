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
