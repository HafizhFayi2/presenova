# Presenova (Laravel + Legacy Bridge)

Proyek ini sudah dikonversi ke struktur **Laravel** tanpa mengubah konsep utama aplikasi Presenova yang sebelumnya berbasis PHP native.

## Struktur

- `app/`, `bootstrap/`, `config/`, `routes/`, `storage/`, `vendor/`: kernel Laravel.
- `public/`: berisi seluruh aplikasi legacy (file `*.php`, `api/`, `dashboard/`, `includes/`, `assets/`, `uploads/`, dll).
- `public/laravel.php`: front controller Laravel.
- `public/index.php`: homepage legacy lama (tetap dipertahankan).

## Cara Kerja Routing

- Request ke file legacy yang memang ada (`/login.php`, `/dashboard/siswa.php`, dll) tetap dijalankan langsung.
- Request non-file diteruskan ke Laravel (`public/laravel.php`) melalui `public/.htaccess`.
- Root proyek (`/presenova`) diarahkan ke folder `public/` lewat `.htaccess` di root.

## Konfigurasi

Konfigurasi sekarang dipusatkan di `.env` Laravel.

Nilai penting default:

- `APP_URL=http://localhost/presenova`
- `APP_TIMEZONE=Asia/Jakarta`
- `DB_CONNECTION=mysql`
- `DB_DATABASE=presenova`
- `DB_USERNAME=root`
- `DB_PASSWORD=`

File `public/includes/config.php` sudah diubah agar membaca `.env` Laravel terlebih dahulu (fallback ke nilai default lama bila env belum ada).

## Menjalankan

1. Pastikan Apache + MySQL XAMPP aktif.
2. Pastikan `mod_rewrite` aktif.
3. Import database `public/presenova.sql` ke MySQL (jika belum ada).
4. Akses aplikasi di `http://localhost/presenova`.

## Catatan

- Endpoint verifikasi kernel Laravel tersedia di: `http://localhost/presenova/laravel-health`.
- Konsep bisnis dan alur halaman legacy tidak diubah; migrasi ini fokus ke fondasi Laravel + sentralisasi konfigurasi.

## Refactor CSS Dashboard

CSS inline pada dashboard sudah dipindahkan ke stylesheet eksternal agar struktur lebih rapi:

- `public/assets/css/legacy-dashboard/admin.css`
- `public/assets/css/legacy-dashboard/guru.css`
- `public/assets/css/legacy-dashboard/siswa.css`
- `public/assets/css/legacy-dashboard/sections/*.css`

File PHP dashboard sekarang memuat stylesheet via tag `<link ... data-inline-style="extracted">`.

## Struktur Dashboard Role-Based

Struktur dashboard sekarang dipisah per role agar lebih terorganisir:

- `public/dashboard/roles/admin/sections/*`
- `public/dashboard/roles/guru/sections/*`
- `public/dashboard/roles/siswa/sections/*`

Entry legacy tetap sama (`public/dashboard/admin.php`, `public/dashboard/guru.php`, `public/dashboard/siswa.php`) dan sekarang membaca section dari folder role-based tersebut.
Folder `public/dashboard/sections` dipertahankan sebagai compatibility wrapper agar alur lama tidak berubah.

## Bridge Blade Dashboard

Agar modul dashboard mulai mengikuti struktur Laravel tanpa mengubah tampilan/alur lama:

- Route Laravel:
  - `/dashboard/admin`
  - `/dashboard/guru`
  - `/dashboard/siswa`
  - (otomatis juga tersedia dengan prefix path dari `APP_URL`, contoh: `/presenova/dashboard/admin`)
- Controller: `app/Http/Controllers/LegacyDashboardController.php`
- Renderer bridge: `app/Support/LegacyPhpRenderer.php`
- Blade wrapper:
  - `resources/views/dashboard/admin.blade.php`
  - `resources/views/dashboard/guru.blade.php`
  - `resources/views/dashboard/siswa.blade.php`

Ketiga route tetap merender file legacy asli (`public/dashboard/*.php`) sehingga output UI dan flow tetap sama.
