# Mockup Konsep dan Alur Kerja Presenova

Dokumen ini menjelaskan konsep website Presenova berdasarkan implementasi kode saat ini di repository (`public/`, `routes/`, `app/`, dan dashboard role-based). Tujuannya agar komunitas mudah memahami sistem dari sisi produk, operasional, dan teknis.

Status dokumen ini diperbarui mengikuti progres cutover sampai fase 10 (18 Februari 2026).

## 1. Ringkasan Konsep

Presenova adalah sistem presensi sekolah berbasis web + PWA dengan tiga validasi utama:

- Validasi jadwal (harus sesuai waktu mapel)
- Validasi lokasi GPS (berdasarkan radius lokasi sekolah default)
- Validasi wajah (face matching antara selfie dan foto referensi siswa)

Fokus produk:

- Mempercepat proses absensi
- Menurunkan titip absen
- Menyediakan rekap data kehadiran yang siap dipantau (admin/guru/siswa)

## 2. Arsitektur Sistem

### 2.1 Laravel-First (Cutover Bertahap)

Arsitektur saat ini sudah **Laravel-only runtime**:

- Semua request dinamis masuk ke front controller Laravel (`public/index.php`)
- URL `.php` lama dipertahankan lewat route Laravel
- Runtime halaman dijalankan dari controller Laravel + view di `resources/views`
- Semua URL lama dipetakan eksplisit via route/controller Laravel (tanpa catch-all script map)

Dampak:

- Stabilitas routing meningkat tanpa memutus URL lama
- Risiko endpoint liar menurun karena allowlist
- Refactor kode procedural ke service/controller masih berlangsung, namun jalur runtime aktif sudah sepenuhnya lewat Laravel

Definisi status framework saat ini:

- Full Laravel untuk **runtime request handling**: Ya.
- Full Laravel untuk **native refactor seluruh kode dashboard**: Ya (runtime aktif).
- UI/flow lama tetap dijaga lewat view PHP/Blade di Laravel agar tidak terjadi regresi operasional.

### 2.2 Komponen Inti

- Frontend: PHP template, Bootstrap, jQuery, DataTables, Chart.js, Leaflet
- Backend: Laravel controller/service terpusat
- Face matching: Python (DeepFace) via `face_match.py` + fallback matcher berbasis PHP
- Database: MySQL
- PWA + Push: Service Worker + Web Push (VAPID)

### 2.3 Status Cutover Teknis (Fase 10)

Sudah native Laravel:

- Home, login, logout
- JWT/session remember bridge
- CRUD admin utama (`add/edit student`, `add class/jurusan`, `reset/reveal`, statistik)
- Endpoint AJAX form dashboard:
- `dashboard/ajax/config.php`
- `dashboard/ajax/get_data.php`
- `dashboard/ajax/get_form.php`
- `dashboard/ajax/get_schedule_form.php`
- `dashboard/ajax/load_attendance_form.php`
- API umum (`check_location`, `get-public-key`, `get_schedule`, `get_attendance_details`, `sync_schedule`)
- API face yang sudah dipindah: `face_matching`, `register_face`, `save_pose_frames`
- API absensi terstandar: `save_attendance.php` sebagai endpoint utama Laravel
- Wrapper lama juga sudah diarahkan ke service yang sama:
- `api/submit_attendance.php`
- `dashboard/ajax/save_attendance.php`
- Endpoint print jadwal sudah native Laravel controller:
- `dashboard/roles/admin/print/jadwal_print.php`
- `dashboard/roles/guru/print/jadwal_print.php`
- `dashboard/roles/siswa/print/jadwal_print.php`
- `404.php` sudah native Laravel view
- Route utilitas lama sudah masuk controller Laravel:
- `call.php`
- `register.php`
- `forgot-password.php`
- `reset_password.php`
- Header verifikasi stack `X-Presenova-Stack: laravel-only`

Catatan:
- Dashboard role (`admin/guru/siswa`) tetap mempertahankan UI/flow lama, tetapi sekarang dieksekusi melalui controller Laravel + `resources/views`.
- Tidak ada lagi class/route runtime aktif yang mengeksekusi file procedural lama.

### 2.4 Verifikasi Komunikasi Database dan Sinkronisasi (18 Februari 2026)

Hasil health check relasi data inti:

- `student_count=3`
- `teacher_count=3`
- `class_count=3`
- `teacher_schedule_count=7`
- `student_schedule_count=566`
- `presence_count=3`
- `site_rows=1`
- `default_location_exists=1`

Hasil cek orphan/ketidaksinkronan:

- `orphan_student_class=0`
- `orphan_student_jurusan=0`
- `orphan_ts_teacher=0`
- `orphan_ts_class=0`
- `orphan_ss_student=0`
- `orphan_ss_ts=0`
- `orphan_presence_ss=0`
- `orphan_presence_student=0`

Kesimpulan operasional:

- Komunikasi database antar modul inti (master, jadwal, absensi, lokasi) sinkron.
- Tidak ditemukan putus relasi data pada tabel inti saat pemeriksaan.

## 3. Aktor dan Hak Akses

Catatan penting: pada implementasi, role formal adalah `admin`, `guru`, `siswa`. "Operator" adalah `admin` dengan `level = 2`.

### 3.1 Matriks Role

| Role | Deskripsi | Hak utama |
|---|---|---|
| Administrator (level 1) | Pengelola penuh sistem | Kelola master data, user sistem, lokasi default, jadwal, laporan, pengaturan |
| Operator (level 2) | Pengelola operasional terbatas | Hampir semua menu admin, tetapi dibatasi untuk aksi sensitif tertentu |
| Guru | Monitoring dan rekap absensi kelas yang diajar | Lihat jadwal, rekap absensi, laporan statistik, profil |
| Siswa | Eksekusi absensi dan lihat data pribadi | Verifikasi wajah, absen, lihat jadwal, riwayat, profil |

### 3.2 Batasan Khusus Operator (level 2)

Operator tidak bisa:

- Menghapus data master tertentu (`class/jurusan/location`)
- Menghapus guru
- Mengelola user sistem (tambah/edit/hapus/toggle aktif)
- Melihat kode siswa asli (hanya admin level 1 yang bisa membuka dengan verifikasi password admin)

Operator masih bisa:

- Kelola jadwal
- Kelola data siswa (termasuk hapus siswa jika diizinkan alur)
- Monitoring absensi
- Ubah sebagian pengaturan lokasi dan validasi

Detail fungsi operator dalam operasi harian:

- Menjalankan CRUD siswa harian (input siswa baru, edit data, reset kredensial siswa ke default kebijakan).
- Menangani validasi jadwal (cek konflik guru-jam, koreksi kelas/hari, pemeliharaan kalender mengajar).
- Menjadi first-line support untuk kendala absensi siswa (lokasi, status jadwal, keterlambatan, status sakit/izin).
- Memantau dashboard attendance tanpa akses ke pengaturan user sistem inti.

## 4. Alur Pengguna End-to-End

## 4.1 Masuk dari Halaman Publik

Landing page (`index.php`) memiliki section:

- Beranda
- Fitur
- Cara Kerja
- PWA
- Kontak

Pengguna lanjut ke `login.php` dan memilih role:

- Siswa
- Guru
- Admin

## 4.2 Login dan Session

Sistem login memiliki:

- Session + timeout aktivitas
- Remember token berbasis JWT cookie
- Redirect dashboard sesuai role
- Untuk siswa: cek apakah referensi wajah valid

Mekanisme session/JWT remember:

- Cookie remember lama tetap kompatibel (`attendance_token`, `remember_token`).
- Claim token kompatibel (`user_id`, `role`, `exp`) agar auto-login siswa tetap berjalan saat session habis.
- Jika JWT valid dan session kosong, middleware membangun ulang session role user otomatis.
- Jika JWT invalid/expired, sistem fallback ke login biasa.

Kontrol password default:

- Siswa: bila terdeteksi password default (kode siswa/NISN/pola lama), dipaksa ganti password sebelum akses penuh dashboard.
- Guru: bila masih `guru123`, sistem auto-rotate ke format acak `P@ssw0rdTC###` dan tampilkan notifikasi perubahan.

Jika siswa belum punya referensi wajah/pose capture, diarahkan ke `register.php`.

## 4.3 Onboarding Siswa (Register Wajah)

Onboarding siswa terdiri dari 2 tahap:

1. Pose capture dataset:
- 5 frame kepala menoleh kanan
- 5 frame kepala menoleh kiri
- 1 frame kepala depan

2. Foto referensi depan:
- Diambil dari kamera
- Disimpan ke `uploads/faces/...`
- Dicatat ke kolom `student.photo_reference`

Jika dua tahap lengkap, siswa bisa masuk dashboard.

## 4.4 Sinkronisasi Jadwal

Sistem menyiapkan `student_schedule` dari `teacher_schedule` secara otomatis (rolling sampai 6 bulan) agar setiap siswa punya instance jadwal konkret per tanggal.

## 4.5 Push Notification

PWA dan push token tersimpan di `push_tokens`.
Cron notifikasi (`cron/send_notifications.php`) mengirim event seperti:

- 2 menit sebelum mulai
- Saat jadwal dimulai
- Sisa 10 menit
- Masuk waktu toleransi
- Overdue dimulai
- Ringkasan jadwal esok hari (default pukul 18:00)

## 4.6 Proses Absensi Wajah

Urutan absensi siswa (mode utama hadir):

1. Siswa pilih jadwal (`student_schedule_id`)
2. Sistem cek GPS dan radius lokasi sekolah default
3. Kamera aktif, selfie diambil
4. API `face_matching.php` memverifikasi wajah dan mengeluarkan `match_token` (berlaku terbatas)
5. Siswa konfirmasi di modal absensi
6. API `save_attendance.php` (native Laravel) memvalidasi ulang jadwal + GPS + token wajah
7. Data `presence` disimpan, status jadwal diubah `COMPLETED`
8. Bukti foto absensi disimpan ke folder attendance (dengan kartu validasi berisi metadata)
9. Push hasil absensi dikirim ke siswa

Mode `sakit/izin`:

- Tetap wajib verifikasi wajah
- Radius GPS tidak dijadikan syarat utama seperti mode hadir
- `present_id` disimpan sesuai mode

## 5. Detail Fitur per Section

## 5.1 Section Admin/Operator

Menu utama admin:

- `dashboard`: ringkasan total siswa/guru/kelas/absensi hari ini + aktivitas terbaru
- `student`: CRUD siswa, filter kelas/jurusan, reset password ke kode siswa, status wajah
- `teacher`: CRUD guru, filter tipe, reset password guru (`guru123`)
- `class`: CRUD kelas dan jurusan, proteksi hapus jika masih ada relasi siswa/jadwal
- `schedule`: CRUD jadwal mengajar, konfigurasi jam harian, kalender mingguan, cetak jadwal
- `attendance`: rekap absensi lintas tanggal, chart statistik, export, detail lokasi/foto
- `location`: pengaturan titik sekolah, radius, lokasi default, validasi GPS/foto
- `system`: manajemen user sistem (admin only), log aktivitas, monitoring resource server

Catatan penting operasional:

- Status alpa dihitung juga dari jadwal yang sudah lewat tetapi belum ada record presence
- Hapus data sensitif beberapa tabel menggunakan validasi dan transaksi
- Ada backup data siswa saat delete siswa (termasuk jejak file)

Kontrol akses admin vs operator:

- Aksi sensitif tingkat tinggi (manajemen user sistem, delete master tertentu, reveal kode siswa) dibatasi ke admin level 1.
- Operator level 2 tetap produktif untuk operasi sekolah harian tanpa membuka akses ke area risiko tertinggi.

## 5.2 Section Guru

Menu guru:

- `dashboard`: ringkasan jadwal hari ini, statistik kelas yang diajar
- `jadwal`: daftar jadwal mengajar + filter + cetak
- `absensi`: rekap absensi per tanggal/kelas/status, export (Excel/PDF/Print), chart
- `laporan`: statistik periode (harian/mingguan/bulanan), performa per kelas
- `profil`: identitas guru, ringkasan mengajar, ganti password

Fokus guru di sistem ini adalah monitoring dan pelaporan, bukan pengaturan master data.

## 5.3 Section Siswa

Menu siswa:

- `dashboard`: ringkasan kehadiran, jadwal hari ini/esok, status realtime (menunggu/countdown/active/overdue/alpa)
- `jadwal`: jadwal pelajaran mingguan + tombol absen per jadwal
- `face_recognition`: alur inti verifikasi wajah + lokasi + konfirmasi absensi
- `riwayat`: riwayat absensi, pending hari ini, daftar alpa, filter status/tanggal/pencarian
- `profil`: info identitas, statistik, edit kontak dasar (email/telepon), keamanan akun

Security siswa yang aktif:

- Face verification dua lapis: validasi client + validasi server (`face_matching` + token verifikasi).
- Validasi GPS radius untuk mode hadir, dengan kebijakan berbeda untuk mode sakit/izin.
- Validasi jadwal/waktu dan toleransi keterlambatan berbasis konfigurasi site.
- Paksaan update password default untuk menutup risiko kredensial standar.
- Sinkronisasi session/JWT agar pengalaman login tetap mulus tanpa menurunkan kontrol akses.

## 6. Model Data Inti

Tabel utama yang membentuk alur bisnis:

- `user`, `user_level`: akun admin/operator
- `teacher`, `teacher_schedule`: data guru dan jadwal mengajar
- `student`, `student_schedule`: data siswa dan jadwal siswa per tanggal
- `presence`, `present_status`: data absensi dan status (Hadir/Sakit/Izin/Alpa)
- `school_location`, `site`: konfigurasi validasi GPS, radius, toleransi waktu
- `push_tokens`, `push_notification_logs`: notifikasi PWA
- `activity_logs`: audit aktivitas sistem

Aturan sinkronisasi data:

- `teacher_schedule` menjadi sumber jadwal mengajar per guru/kelas/hari.
- `student_schedule` disintesis dari `teacher_schedule` untuk setiap siswa agar absensi berjalan per-instance tanggal.
- `presence` wajib terikat ke `student_schedule` agar status hadir/sakit/izin/alpa dapat dihitung konsisten lintas role.
- `site` dan `school_location` menjadi sumber tunggal validasi radius GPS.

## 7. Pro dan Kontra Sistem (Setelah Fase 10)

## 7.1 Kelebihan (Pro)

- Multi-validasi (waktu + GPS + wajah) membuat absensi lebih kredibel
- Role-based dashboard jelas untuk admin/operator/guru/siswa
- Alur siswa kuat dengan onboarding wajah + pose capture
- Ada PWA, push notification, dan monitoring realtime
- Riwayat dan laporan cukup lengkap untuk kebutuhan sekolah
- Mekanisme alpa tidak hanya bergantung input manual
- Cutover Laravel sudah berjalan nyata tanpa memutus URL lama
- Catch-all lama sudah dihapus, diganti allowlist route
- Secret push/JWT sudah dipusatkan ke environment (`.env`)
- Endpoint absensi sudah distandarkan ke satu service/controller Laravel

## 7.2 Kekurangan/Risiko (Con)

- Runtime tetap mempertahankan sebagian kode procedural kompatibilitas demi parity UI/flow
- Banyak file dashboard besar (mix PHP/HTML/JS), sulit diuji unit dan sulit direview cepat
- Password default (`admin123`, `guru123`) dan pola reset default berisiko jika tidak dipaksa ganti
- Sebagian runtime internal masih mempertahankan format procedural lama
- Guard modern (policy/rate-limit/test coverage) belum merata untuk semua alur
- Data biometrik dan geolokasi butuh kebijakan privasi/retensi yang sangat jelas

Status final saat ini:

- Runtime aktif sudah Laravel-only (`public/index.php` -> Laravel front controller).
- Route `.php` lama tetap tersedia, tetapi diproses oleh controller Laravel.
- Folder view/helper runtime lama sudah dihapus dari runtime aktif.
- Endpoint AJAX yang sebelumnya kosong (`change_password`, `optimize_database`, `save_security`) sudah dipetakan ke controller Laravel.
- Cron push notification sudah bootstrap Laravel langsung.

## 8. Dampak untuk Komunitas (Bahasa Non-Teknis)

Untuk komunitas sekolah, Presenova bisa dipahami sebagai:

- Sistem absensi digital yang menuntut kehadiran nyata (bukan titip absen)
- Sistem yang memberi transparansi ke tiga pihak sekaligus:
- Siswa tahu status absensinya
- Guru mudah memantau kelas
- Admin/operator mudah mengelola data dan evaluasi

Titik kritis adopsi komunitas:

- Edukasi penggunaan kamera dan GPS di ponsel siswa
- Edukasi perbedaan peran admin vs operator vs guru
- SOP saat siswa sakit/izin agar proses tetap konsisten
- Kebijakan privasi data wajah dan lokasi

## 9. Rekomendasi Penguatan Bertahap

1. Lanjutkan refactor bertahap file dashboard besar ke service/controller murni agar testability meningkat.
2. Pertahankan parity UI/flow sambil menurunkan kode procedural di runtime internal.
3. Pecah dashboard besar menjadi service/controller modular agar mudah dites.
4. Standarkan endpoint absensi ke satu jalur utama dan nonaktifkan wrapper lama sementara.
5. Perluas audit trail terstruktur untuk seluruh perubahan master data penting.
6. Tegakkan forced change password default secara konsisten untuk semua role.
7. Susun dan publikasikan kebijakan retensi data biometrik/lokasi untuk transparansi komunitas.

---

Dokumen ini dapat dipakai sebagai bahan presentasi komunitas, onboarding tim teknis baru, dan acuan menyusun roadmap pengembangan Presenova berikutnya.
