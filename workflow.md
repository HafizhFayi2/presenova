Cara Kerja Sistem

Website ini didesain sebagai _Progressive Web App (PWA)_ yang bisa diakses lewat browser apapun (Chrome, Safari, Firefox) dan bisa di-install seperti aplikasi di HP siswa. Jadi siswa nggak perlu download dari Play Store, cukup buka link sekali, lalu "Add to Home Screen".

> Proses Awal:
> login admin ialah :
> username: admin
> password admin123

Admin sekolah membuat akun siswa pakai NIS/NISN lewat dashboard admin. Siswa mengisi username dan password menggunakan nisn yang dibuat admin. Saat login pertama kali, siswa _wajib_ upload pas foto resmi (tampak depan, latar polos). Foto ini di-compress dan disimpan di _cloud storage_ pada google drive agar nggak membebani database. Di database cuma nyimpen link ke foto tersebut.
Setelah registrasi foto selesai, browser otomatis _menyimpan session login_ pakai token JWT yang disimpan di cookie/localStorage. Jadi siswa nggak perlu login ulang setiap buka website, kecuali logout manual atau token expired (misal 30 hari).

> Proses Absensi Harian:

Jadwal mata pelajaran sudah diinput oleh admin (misal: Matematika, Senin 07.00-08.30, Kelas X RPL 1). Sistem punya _background scheduler_ (pakai cron job atau task scheduler) yang berjalan di codingan.
5 menit sebelum mapel dimulai, system website itu mengirim _push notification_ ke semua siswa yang terdaftar di kelas tersebut. Push notification ini pakai teknologi _Web Push API_ yang didukung browser modern. Siswa cukup izinkan notifikasi saat pertama kali buka website.

Siswa klik notifikasi → langsung masuk halaman absensi. Di sini sistem melakukan 3 pengecekan bersamaan:

1. _Validasi Waktu_: Sistem cek apakah sekarang masih dalam rentang waktu absensi (misal 10 menit sebelum s/d 15 menit setelah mapel dimulai). Pakai waktu server, bukan waktu device siswa (biar nggak bisa diubah-ubah).
2. _Validasi Lokasi_: Sistem minta akses GPS lewat browser (Geolocation API). Koordinat siswa dibandingkan dengan koordinat sekolah yang sudah disetting admin. Pakai perhitungan jarak _Haversine formula_ dengan toleransi radius tertentu (misal 100-200 meter). Ini cukup akurat untuk memastikan siswa ada di area sekolah.
3. _Face Recognition_: Kamera browser aktif otomatis (pakai MediaDevices API). Siswa selfie sekali klik. Foto selfie langsung diproses di _server_ pakai library face recognition (misal face-api.js di frontend untuk deteksi wajah, lalu kirim ke backend untuk matching pakai Python + face_recognition library atau TensorFlow). Sistem melakukan perbandingan _1:1_ antara foto selfie dengan pas foto siswa yang sudah tersimpan. Kalau similarity score di atas threshold tertentu (misal 70-80%), dianggap cocok.

Jika ketiga validasi lolos, data absensi tersimpan ke database dengan struktur seperti ini:

```
ID Absensi | NIS | Nama | ID Mapel | Nama Mapel | Kelas | Waktu Absen | Koordinat | URL Foto Selfie | Status | Face Match Score
```

Foto selfie juga di-upload ke cloud storage, di database cuma nyimpen URL-nya. Ini penting biar database nggak bengkak dan loading tetap cepat meskipun ribuan siswa absen setiap hari.

_Dashboard Guru & Admin:_
Guru login ke dashboard dan bisa filter absensi berdasarkan mapel, kelas, tanggal. Setiap record absensi bisa diklik untuk melihat detail lengkap: foto referensi (pas foto) vs foto presensi (selfie), lokasi di map, jam exact, dan skor kemiripan wajah.
Guru bisa export data absensi ke Excel (pakai library seperti ExcelJS atau SheetJS) untuk administrasi atau rekapitulasi bulanan. File Excel berisi semua data kecuali foto (foto tetap bisa diakses lewat link kalau perlu verifikasi manual).

> Kompatibilitas Device & Browser

Website ini didesain **responsive** pakai framework seperti React/Vue + Tailwind CSS. Bisa diakses dari:

- Android (Chrome, Firefox, Edge)
- iOS (Safari, Chrome)
- Desktop/Laptop (semua browser modern)

Untuk fitur-fitur kritis:

- _Kamera:_ Semua browser modern sudah support MediaDevices API
- _GPS:_ Geolocation API sudah standard di semua browser
- _Push Notification:_ Support di Chrome/Edge/Firefox (iOS Safari masih terbatas, tapi bisa pakai fallback dengan reminder manual)
- _PWA Install:_ Bisa di-install sebagai "aplikasi" di HP tanpa perlu App Store

_Session Management:_ Pakai JWT token yang disimpan di _httpOnly cookie_ (lebih aman dari localStorage). Token punya masa aktif 30 hari, jadi siswa nggak perlu login ulang kecuali:

- Logout manual
- Token expired
- Clear browser data

Setiap kali siswa buka website, sistem otomatis cek token. Kalau valid, langsung masuk ke dashboard. Kalau expired, diminta login lagi (tapi data history tetap ada di server).

> Keamanan & Anti-Kecurangan

- _GPS Spoofing Prevention:_ Sistem bisa tambahkan validasi tambahan seperti cek IP address atau WiFi SSID sekolah (optional)
- _Face Spoofing Prevention:_ Bisa tambahkan liveness detection sederhana (misal: minta siswa kedip mata atau gerakkan kepala)
- _Waktu Server:_ Semua validasi waktu pakai server time, bukan device time yang bisa dimanipulasi
- _Rate Limiting:_ Batasi berapa kali siswa bisa coba absen dalam periode tertentu (misal max 3x per mapel)
- _Audit Log:_ Semua aktivitas tercatat (login, upload foto, absen) dengan timestamp dan IP address

_ANALISIS MASALAH_

- Proses presensi atau absensi yang dilakukan secara manual di kelas sering memakan waktu belajar. Waktu pembelajaran menjadi semakin berkurang, terutama ketika terdapat siswa yang tidak hadir tanpa keterangan, karena hal tersebut sering memicu pembahasan tambahan di kelas yang tidak berkaitan langsung dengan materi pelajaran.
- Kegiatan guru BK yang harus berkeliling ke setiap kelas untuk melakukan absensi dapat mengganggu konsentrasi siswa, baik saat kegiatan belajar, praktik, maupun saat jam istirahat. Selain itu, metode ini juga kurang efisien karena membutuhkan waktu dan tenaga lebih bagi guru BK untuk mendatangi banyak kelas.
- Sistem absensi manual memiliki risiko terjadinya kesalahan pencatatan, seperti siswa yang sebenarnya tidak hadir tetapi tercatat hadir, sehingga data kehadiran menjadi kurang akurat dan tidak mencerminkan kondisi sebenarnya
