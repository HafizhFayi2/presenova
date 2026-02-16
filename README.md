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

## Setup DeepFace (Face Recognition)

Sistem face recognition server-side sekarang memakai **DeepFace** pada script:

- `public/face/faces_conf/face_match.py`

Rekomendasi untuk Linux VPS production: jalankan script setup otomatis.

```bash
# dari root project
bash scripts/setup_deepface.sh --write-env

# jika butuh install dependency OS (Debian/Ubuntu)
bash scripts/setup_deepface.sh --install-system-deps --write-env
```

Script ini bisa dijalankan dari folder mana pun:

```bash
bash /var/www/presenova/scripts/setup_deepface.sh --write-env
```

Manual setup (jika tidak pakai script): gunakan virtual environment khusus project.

```bash
python3 -m venv public/face/.venv
public/face/.venv/bin/python -m pip install -r public/face/faces_conf/requirements.txt
```

Jika tidak memakai venv, instalasi library Python mengikuti panduan resmi DeepFace:

```bash
pip install deepface
# jika muncul error keras3 pada retinaface:
pip install tf-keras
```

Atau lewat source code DeepFace:

```bash
git clone https://github.com/serengil/deepface.git
cd deepface
pip install -e .
```

Alternatif praktis untuk project ini:

```bash
pip install -r public/face/faces_conf/requirements.txt
```

Konfigurasi DeepFace dapat diatur dari `.env`:

- `LEGACY_PYTHON_BIN=/var/www/presenova/public/face/.venv/bin/python` (Linux VPS)
- `LEGACY_PYTHON_BIN=public/face/.venv/Scripts/python.exe` (Windows/XAMPP)
- `LEGACY_FACE_MATCH_THRESHOLD=89`
- `LEGACY_FACE_MATCH_MODEL=SFace`
- `LEGACY_FACE_MATCH_DETECTOR=opencv`
- `LEGACY_FACE_MATCH_DISTANCE_METRIC=cosine`
- `LEGACY_FACE_MATCH_ENFORCE_DETECTION=true`
- `LEGACY_FACE_MATCH_MAX_REFERENCES=1`
- `LEGACY_FACE_MATCH_USE_BACKUP=true`
- `LEGACY_FACE_MATCH_BACKUP_MODEL=SFace`
- `LEGACY_FACE_MATCH_BACKUP_DETECTOR=mtcnn`
- `LEGACY_FACE_MATCH_BACKUP_MAX_REFERENCES=1`
- `LEGACY_FACE_MATCH_DETECTOR_FALLBACKS=false`
- `LEGACY_FACE_MATCH_TIMEOUT_SECONDS=60`
- `LEGACY_FACE_MATCH_ALLOW_LEGACY=false`

Catatan:

- Foto referensi siswa tetap disimpan di `public/uploads/faces/`.
- Matcher DeepFace otomatis membaca referensi dari `photo_reference` siswa (jika ada), fallback ke pola NISN, lalu mengevaluasi beberapa foto referensi saat verifikasi absensi.
- `public/includes/config.php` otomatis memakai `public/face/.venv/Scripts/python.exe` jika file itu tersedia dan `LEGACY_PYTHON_BIN` tidak diisi.
- Saat run pertama, DeepFace akan mengunduh bobot model ke `~/.deepface/weights` sehingga proses awal bisa lebih lama.
- Runtime Python dibatasi oleh `LEGACY_FACE_MATCH_TIMEOUT_SECONDS` agar request tidak mentok di `Maximum execution time`.

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
