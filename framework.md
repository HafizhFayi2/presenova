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