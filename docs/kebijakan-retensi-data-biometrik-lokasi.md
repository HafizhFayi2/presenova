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
