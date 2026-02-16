# Presenova

Presenova adalah sistem presensi sekolah berbasis web dan PWA dengan validasi waktu, lokasi, dan wajah untuk membantu sekolah mencatat kehadiran secara lebih cepat dan akurat.

## Tujuan Sistem

- Mengurangi waktu absensi manual di kelas.
- Mengurangi gangguan pembelajaran karena proses presensi konvensional.
- Meminimalkan kesalahan pencatatan kehadiran siswa.

## Struktur Folder Proyek!

```text
presenova/
|- app/                    # Core Laravel (controller, service, middleware)
|- bootstrap/              # Bootstrap aplikasi Laravel
|- config/                 # Konfigurasi aplikasi
|- database/               # Migration, seeders, dan schema
|- public/                 # Entry point web + modul legacy
|  |- api/                 # Endpoint API legacy
|  |- assets/              # CSS, JS, gambar, dan file statis
|  |- dashboard/           # Dashboard role-based
|  |  |- roles/
|  |  |  |- admin/sections/
|  |  |  |- guru/sections/
|  |  |  |- siswa/sections/
|  |- includes/            # Config/helper legacy
|  |- uploads/             # File upload (foto profil/foto absensi)
|  |- index.php            # Homepage legacy
|  |- laravel.php          # Front controller Laravel
|- resources/              # View dan asset Laravel
|- routes/                 # Definisi route Laravel
|- scripts/                # Script otomasi/setup
|- storage/                # Log, cache, dan file runtime
|- tests/                  # Unit/feature test
|- vendor/                 # Dependency Composer
|- README.md               # Dokumentasi utama proyek
|- workflow.md             # Ringkasan alur kerja (pointer ke README)
|- framework.md            # Catatan arsitektur Laravel <-> legacy
|- concept.md              # Konsep dan kebutuhan sistem
```

## Alur Kerja Sistem

### 1. Akses Awal

- Pengguna membuka halaman awal yang berisi: Beranda, Fitur, Cara Kerja, PWA, dan Kontak.
- Pengguna memilih login atau pendaftaran sesuai role: Admin, Guru, atau Siswa.

### 2. Login dan Onboarding Siswa

- Siswa login menggunakan NISN dan password.
- Jika belum punya akun, siswa menghubungi admin (via email) untuk dibuatkan akun.
- Saat login pertama, siswa mengunggah pas foto resmi sebagai referensi verifikasi wajah.
- Sesi login disimpan menggunakan token (JWT), sehingga pengguna tidak perlu login ulang setiap saat (kecuali logout atau token kedaluwarsa).

### 3. Proses Absensi Harian

1. Admin mengatur jadwal pelajaran.
2. Sekitar 5 menit sebelum pelajaran dimulai, sistem mengirim push notification ke siswa di kelas terkait.
3. Siswa membuka halaman absensi dari notifikasi.
4. Sistem melakukan validasi:
   - Waktu absensi menggunakan waktu server.
   - Lokasi absensi menggunakan GPS dengan radius sekolah.
   - Verifikasi wajah (selfie dibandingkan dengan foto referensi).
5. Foto absensi disimpan ke cloud storage, sedangkan database menyimpan metadata dan URL file.

### 4. Dashboard Admin

- Ringkasan total guru, total siswa, total kelas, dan absensi hari ini.
- Manajemen siswa: filter per kelas, pencarian, dan tambah data siswa.
- Manajemen guru: daftar guru, pencarian, jadwal, dan tambah guru.
- Manajemen kelas dan jurusan: tambah, lihat, dan ubah data.
- Jadwal mengajar: detail per hari/kelas/guru/shift + tampilan mingguan.
- Data absensi: rekap harian dan statistik bulanan (hadir, sakit, izin, alpa).
- Pengaturan lokasi: toleransi waktu, radius absensi default, validasi GPS/foto, dan pengaturan terkait.
- Pengaturan sistem: manajemen user, backup database, dan status keamanan sistem.

### 5. Dashboard Siswa

- Statistik absensi 7 hari terakhir (hadir, sakit, izin, alpa, rasio kehadiran).
- Informasi jadwal absensi hari ini dan jadwal pelajaran esok hari.
- Fitur jadwal pelajaran lengkap per hari/shift/durasi/mapel/guru/status + aksi absensi foto.
- Fitur riwayat absensi dan pencarian data absensi.
- Fitur profil siswa (NISN, kode siswa, status, tanggal daftar, kontak, kelas, jurusan, statistik).
- Perubahan data inti (misalnya NISN, nama, kelas) dilakukan melalui administrator.

### 6. Dashboard Guru

- Ringkasan total siswa, jadwal hadir hari ini/bulan ini, dan total absensi siswa.
- Informasi jadwal mengajar hari ini dan jadwal esok hari.
- Statistik bulanan untuk monitoring kehadiran.

## Kompatibilitas dan Keamanan

- Akses lintas perangkat: Android, iOS, desktop browser modern.
- Kamera: MediaDevices API.
- Lokasi: Geolocation API.
- Notifikasi: Web Push API.
- PWA: dapat ditambahkan ke home screen.
- Audit log mencatat aktivitas penting (login, upload foto, absensi) dengan timestamp.

## Dokumen Pendukung

- `framework.md`: detail arsitektur Laravel dan bridge ke modul legacy.
- `concept.md`: latar belakang masalah dan konsep bisnis.
- `workflow.md`: ringkasan alur kerja; versi lengkapnya ada di `README.md`.
