<?php
class AttendanceHelper {
    /**
     * Cek apakah terlambat berdasarkan waktu masuk dan shift
     */
    public static function isLate($time_in, $shift_name = null) {
        $current_time = date('H:i:s');
        
        // Jika shift Full Day, tidak ada konsep terlambat
        if ($shift_name === 'Full Day') {
            return false;
        }
        
        // Batas toleransi keterlambatan (30 menit)
        $toleransi_menit = 30;
        $batas_toleransi = date('H:i:s', strtotime($time_in) + ($toleransi_menit * 60));
        
        return $current_time > $batas_toleransi;
    }
    
    /**
     * Hitung menit keterlambatan
     */
    public static function calculateLateMinutes($time_in) {
        $current_time = time();
        $time_in_unix = strtotime($time_in);
        $toleransi = 30 * 60; // 30 menit dalam detik
        
        $terlambat_detik = $current_time - ($time_in_unix + $toleransi);
        
        if ($terlambat_detik <= 0) {
            return 0;
        }
        
        return ceil($terlambat_detik / 60); // Konversi ke menit
    }
    
    /**
     * Validasi apakah masih dalam waktu absen yang diizinkan
     */
    public static function isWithinAttendanceTime($time_in, $time_out, $shift_name) {
        $current_time = date('H:i:s');
        
        // Untuk Full Day, selalu dalam waktu
        if ($shift_name === 'Full Day') {
            return $current_time >= '06:00:00' && $current_time <= '23:00:00';
        }
        
        // Untuk shift reguler, cek dalam rentang waktu
        return $current_time >= $time_in && $current_time <= $time_out;
    }
}
?>