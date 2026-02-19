# Presenova - Status Sistem Terbaru (Update 18 Februari 2026)

Dokumen ini menggantikan versi lama dan hanya memuat kondisi sistem terbaru yang aktif di repository saat ini.

## 1. Status Framework Saat Ini

### Kesimpulan singkat
- Runtime aplikasi sudah **Laravel-only**.
- Seluruh request dinamis masuk lewat `public/index.php` dan route/controller Laravel.
- URL lama berbentuk `.php` tetap aktif sebagai **compatibility route** (mis. `login.php`, `dashboard/siswa.php`), tetapi eksekusinya tetap oleh Laravel.

### Arti status ini
- Bukan lagi hybrid Laravel + folder runtime legacy terpisah.
- Kode tampilan masih mempertahankan gaya UI lama (PHP-style di `resources/views`) agar flow tidak berubah, tetapi tetap dijalankan dari stack Laravel.

## 2. Ringkasan Arsitektur Aktif

- Routing utama: `routes/web.php`
- Controller utama:
  - `app/Http/Controllers/Auth/LoginController.php`
  - `app/Http/Controllers/Dashboard/DashboardPageController.php`
  - `app/Http/Controllers/Dashboard/Ajax/DashboardAjaxController.php`
  - `app/Http/Controllers/Api/ApiController.php`
  - `app/Http/Controllers/Dashboard/Print/SchedulePrintController.php`
- Middleware kompatibilitas session/JWT:
  - `app/Http/Middleware/RememberTokenBridge.php`
  - `app/Http/Middleware/NativePhpSessionBridge.php`
- Header stack verifikasi: `X-Presenova-Stack: laravel-only`

## 3. Pro & Cons Terbaru

### Pro
- Runtime tunggal Laravel memudahkan deployment dan debugging.
- URL lama tetap bisa dipakai tanpa memutus kebiasaan user.
- Auto-login session/JWT siswa tetap jalan (`attendance_token`, `remember_token`).
- Secret push/JWT sudah pakai environment (`.env`), bukan hardcoded.
- Endpoint absensi sudah distandarkan ke jalur utama service Laravel.
- Print jadwal, export, dan API utama sudah satu jalur backend Laravel.

### Cons (teknis, bukan blocker operasional)
- Sebagian view dashboard masih file besar dan PHP-style procedural; belum sepenuhnya idiomatik Blade component/service kecil.
- Beberapa route kompatibilitas masih dikecualikan dari CSRF untuk menjaga backward compatibility endpoint lama.
- Performa UI di beberapa aksi berat masih sangat bergantung pada script client-side dan query lama; perlu optimasi bertahap.

## 4. Cara Login Siswa (Aktif Saat Ini)

### Kredensial
- Role: `Siswa`
- Username/Login ID: **NISN**
- Password default saat akun dibuat admin: **NISN siswa** (hash SHA-256 + `PASSWORD_SALT`)

### Flow login
1. Siswa pilih tab `Siswa` di `login.php`.
2. Input NISN + password.
3. Jika benar, session siswa dibuat.
4. Jika remember diaktifkan, token JWT disimpan di cookie `attendance_token`.
5. Jika foto referensi/pose belum lengkap, siswa diarahkan ke `register.php`.
6. Jika password masih default (terdeteksi salah satu: NISN / student_code / `siswa123`), siswa dipaksa ke halaman `change_password` sebelum akses dashboard normal.

## 5. Cara Login Guru (Aktif Saat Ini)

### Kredensial
- Role: `Guru`
- Username/Login ID: `teacher_code` atau `teacher_username`
- Password default dari admin/reset: `guru123`

### Flow login
1. Guru pilih tab `Guru` di `login.php`.
2. Input kode/username guru + password.
3. Jika valid, masuk dashboard guru.
4. Saat dashboard guru pertama kali dibuka, sistem cek apakah hash password masih default `guru123`.
5. Jika masih default:
   - sistem auto-generate password baru format `P@ssw0rdTC###`,
   - password langsung diupdate ke database,
   - muncul popup yang menampilkan password baru + tombol **Copy** + **Download PNG**.

## 6. Konfigurasi Environment Penting

Gunakan `.env` untuk secret/runtime berikut:
- `JWT_REMEMBER_SECRET`
- `PASSWORD_SALT`
- `PUSH_ENABLED`
- `PUSH_VAPID_PUBLIC_KEY`
- `PUSH_VAPID_PRIVATE_KEY`
- `PUSH_VAPID_SUBJECT`
- `PYTHON_BIN` dan variabel `FACE_MATCH_*`

## 7. Catatan Operasional

- UI/flow tetap dipertahankan agar tidak mengganggu pengguna lama.
- CRUD utama admin/guru/siswa tetap pada jalur controller Laravel.
- Untuk hardening lanjutan: modularisasi file view besar dan pengetatan CSRF compatibility route bisa dilanjutkan fase berikutnya.
