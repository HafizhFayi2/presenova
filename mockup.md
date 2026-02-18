# Mockup Konsep dan Alur Kerja Presenova

Dokumen ini menjelaskan konsep website Presenova berdasarkan implementasi kode saat ini di repository (`public/`, `routes/`, `app/`, dan dashboard role-based). Tujuannya agar komunitas mudah memahami sistem dari sisi produk, operasional, dan teknis.

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

### 2.1 Hybrid Laravel + Legacy PHP

Aplikasi menggunakan pola hybrid:

- Laravel dipakai sebagai kernel aplikasi dan routing modern
- Modul legacy PHP tetap aktif di `public/`
- Request file fisik (misalnya `login.php`, `dashboard/siswa.php`) dieksekusi langsung
- Request non-file diarahkan ke front controller Laravel (`public/laravel.php`)

Dampak:

- Migrasi bertahap jadi mungkin tanpa memutus sistem lama
- Struktur masih campuran, sehingga maintainability butuh disiplin tinggi

### 2.2 Komponen Inti

- Frontend: PHP template, Bootstrap, jQuery, DataTables, Chart.js, Leaflet
- Backend: PHP (legacy + Laravel bridge)
- Face matching: Python (DeepFace) via `face_match.py` + fallback legacy matcher
- Database: MySQL
- PWA + Push: Service Worker + Web Push (VAPID)

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
6. API `save_attendance.php` memvalidasi ulang jadwal + GPS + token wajah
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

## 6. Model Data Inti

Tabel utama yang membentuk alur bisnis:

- `user`, `user_level`: akun admin/operator
- `teacher`, `teacher_schedule`: data guru dan jadwal mengajar
- `student`, `student_schedule`: data siswa dan jadwal siswa per tanggal
- `presence`, `present_status`: data absensi dan status (Hadir/Sakit/Izin/Alpa)
- `school_location`, `site`: konfigurasi validasi GPS, radius, toleransi waktu
- `push_tokens`, `push_notification_logs`: notifikasi PWA
- `activity_logs`: audit aktivitas sistem

## 7. Pro dan Kontra Sistem

## 7.1 Kelebihan (Pro)

- Multi-validasi (waktu + GPS + wajah) membuat absensi lebih kredibel
- Role-based dashboard jelas untuk admin/operator/guru/siswa
- Alur siswa kuat dengan onboarding wajah + pose capture
- Ada PWA, push notification, dan monitoring realtime
- Riwayat dan laporan cukup lengkap untuk kebutuhan sekolah
- Mekanisme alpa tidak hanya bergantung input manual

## 7.2 Kekurangan/Risiko (Con)

- Arsitektur hybrid (Laravel + legacy) masih menyebar logika bisnis di banyak file, maintenance lebih sulit
- Banyak file dashboard sangat besar (mix PHP/HTML/JS), sulit diuji unit dan sulit direview cepat
- Password default (`admin123`, `guru123`) dan pola reset default berisiko jika tidak dipaksa ganti
- Konfigurasi sensitif push (VAPID key) tersimpan di file konfigurasi repo, perlu hardening environment
- Ada endpoint legacy lama berdampingan dengan endpoint baru (potensi inkonsistensi)
- Beberapa referensi UI terlihat tidak sinkron dengan file aktual (indikasi technical debt)
- Belum terlihat guard modern seperti CSRF dan rate limit ketat di semua endpoint legacy
- Data biometrik dan geolokasi butuh kebijakan privasi/retensi yang sangat jelas

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

1. Wajibkan ganti password default saat login pertama (admin/guru/siswa).
2. Pindahkan seluruh secret (VAPID key, dsb.) ke environment, bukan file statis.
3. Pecah file dashboard besar menjadi service/controller modular agar mudah dites.
4. Standarkan endpoint absensi ke satu jalur utama dan deprecate endpoint lama.
5. Tambahkan audit trail lebih eksplisit untuk perubahan master data penting.
6. Susun dokumen kebijakan retensi data biometrik dan lokasi untuk transparansi komunitas.

---

Dokumen ini dapat dipakai sebagai bahan presentasi komunitas, onboarding tim teknis baru, dan acuan menyusun roadmap pengembangan Presenova berikutnya.
