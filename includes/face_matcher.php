<?php
// face_matcher.php
class FaceMatcher {
    private $threshold = 70; // Minimum similarity percentage
    private $tempDir = '../uploads/temp/';
    private $facesDir = '../uploads/faces/';
    private $attendanceDir = '../uploads/attendance/';
    private $pythonBin = 'python';
    private $pythonScript = null;
    private $pythonEnabled = false;
    
    public function __construct() {
        // Create directories if not exist
        $this->createDirectories();

        if (defined('FACE_MATCH_THRESHOLD')) {
            $this->threshold = max(0, min(100, (float) FACE_MATCH_THRESHOLD));
        }
        if (defined('PYTHON_BIN') && PYTHON_BIN) {
            $this->pythonBin = PYTHON_BIN;
        }

        $scriptPath = realpath(__DIR__ . '/../face/faces_conf/face_match.py');
        if ($scriptPath && file_exists($scriptPath)) {
            $this->pythonScript = $scriptPath;
            $this->pythonEnabled = true;
        }
    }
    
    private function createDirectories() {
        $dirs = [$this->tempDir, $this->facesDir, $this->attendanceDir];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }
    
    /**
     * Simpan foto selfie untuk matching
     */
    public function saveSelfie($studentId, $base64Image) {
        $filename = "capture_{$studentId}_" . time() . ".jpg";
        $filepath = $this->tempDir . $filename;
        
        // Decode base64 image
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
        
        if (file_put_contents($filepath, $imageData)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $filepath
            ];
        }
        
        return ['success' => false, 'error' => 'Gagal menyimpan foto'];
    }
    
    /**
     * Dapatkan path foto referensi berdasarkan NISN
     */
    public function getReferencePath($nisn) {
        // Cari file berdasarkan pattern NISN
        $patterns = [
            $this->facesDir . $nisn . ".jpg",
            $this->facesDir . $nisn . ".jpeg",
            $this->facesDir . $nisn . ".png"
        ];
        
        foreach ($patterns as $pattern) {
            if (file_exists($pattern)) {
                return $pattern;
            }
        }
        
        // Jika tidak ditemukan, cari berdasarkan file yang mengandung NISN
        $files = glob($this->facesDir . "*{$nisn}*");
        if (!empty($files)) {
            return $files[0];
        }
        
        return null;
    }
    
    /**
     * Face matching (Python LBPH + fallback ke histogram)
     */
    public function matchFaces($referencePath, $selfiePath, $options = []) {
        if (!file_exists($referencePath) || !file_exists($selfiePath)) {
            return ['success' => false, 'error' => 'File tidak ditemukan'];
        }

        $label = isset($options['label']) ? trim((string) $options['label']) : '';
        $annotate = !empty($options['annotate']);
        $allowLegacy = !empty($options['allow_legacy']);

        if ($this->pythonEnabled) {
            $pythonResult = $this->matchFacesWithPython($referencePath, $selfiePath, $label, [
                'annotate' => $annotate
            ]);
            if (!empty($pythonResult['success'])) {
                return $pythonResult;
            }

            if (!$allowLegacy) {
                return [
                    'success' => false,
                    'error' => $pythonResult['error'] ?? 'Python matcher gagal dijalankan'
                ];
            }
        } elseif (!$allowLegacy) {
            return [
                'success' => false,
                'error' => 'Python matcher tidak tersedia di face/faces_conf'
            ];
        }

        return $this->matchFacesLegacy($referencePath, $selfiePath);
    }

    private function matchFacesWithPython($referencePath, $selfiePath, $label = '', $options = []) {
        if (!$this->pythonEnabled || !$this->pythonScript) {
            return ['success' => false, 'error' => 'Python matcher tidak tersedia'];
        }

        $threshold = $this->threshold;
        if (isset($options['threshold'])) {
            $threshold = max(0, min(100, (float) $options['threshold']));
        }
        $annotate = !empty($options['annotate']);
        $outputPath = '';
        if ($annotate) {
            $tempBase = realpath($this->tempDir) ?: $this->tempDir;
            $outputPath = rtrim($tempBase, '/\\') . DIRECTORY_SEPARATOR . 'match_' . time() . '_' . mt_rand(1000, 9999) . '.jpg';
        }

        $referencePath = realpath($referencePath) ?: $referencePath;
        $selfiePath = realpath($selfiePath) ?: $selfiePath;

        $cmd = escapeshellarg($this->pythonBin)
            . ' ' . escapeshellarg($this->pythonScript)
            . ' --reference ' . escapeshellarg($referencePath)
            . ' --candidate ' . escapeshellarg($selfiePath)
            . ' --threshold ' . escapeshellarg((string) $threshold);

        if ($label !== '') {
            $cmd .= ' --label ' . escapeshellarg($label);
        }
        if ($annotate && $outputPath) {
            $cmd .= ' --output ' . escapeshellarg($outputPath);
        }

        $cmd .= ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        $raw = trim(implode("\n", $output));

        $data = json_decode($raw, true);
        if (!is_array($data) && $raw !== '') {
            $lines = array_values(array_filter(explode("\n", $raw)));
            $lastLine = $lines ? $lines[count($lines) - 1] : '';
            $data = json_decode($lastLine, true);
        }
        if (!is_array($data)) {
            return [
                'success' => false,
                'error' => 'Output python tidak valid',
                'raw' => $raw
            ];
        }

        if (empty($data['success'])) {
            return [
                'success' => false,
                'error' => $data['error'] ?? 'Python matcher gagal'
            ];
        }

        $details = isset($data['details']) && is_array($data['details']) ? $data['details'] : [];
        $details['source'] = 'python-lbph';
        $details['threshold'] = $threshold;

        $result = [
            'success' => true,
            'similarity' => isset($data['similarity']) ? (float) $data['similarity'] : 0,
            'passed' => !empty($data['passed']),
            'details' => $details
        ];

        if (!empty($data['annotated_path']) && file_exists($data['annotated_path'])) {
            $result['annotated_path'] = $data['annotated_path'];
        }

        return $result;
    }

    private function matchFacesLegacy($referencePath, $selfiePath) {
        // Load images
        $refImg = $this->loadImage($referencePath);
        $selfieImg = $this->loadImage($selfiePath);
        
        if (!$refImg || !$selfieImg) {
            return ['success' => false, 'error' => 'Gagal memuat gambar'];
        }
        
        try {
            // Method 1: Histogram Comparison
            $histogramSimilarity = $this->compareHistograms($refImg, $selfieImg);
            
            // Method 2: Average Color Comparison
            $colorSimilarity = $this->compareAverageColor($refImg, $selfieImg);
            
            // Method 3: Edge Detection Comparison
            $edgeSimilarity = $this->compareEdges($refImg, $selfieImg);
            
            // Weighted average
            $totalSimilarity = ($histogramSimilarity * 0.5) + 
                             ($colorSimilarity * 0.3) + 
                             ($edgeSimilarity * 0.2);
            
            // Cleanup
            imagedestroy($refImg);
            imagedestroy($selfieImg);
            
            return [
                'success' => true,
                'similarity' => round($totalSimilarity, 2),
                'passed' => $totalSimilarity >= $this->threshold,
                'details' => [
                    'source' => 'histogram',
                    'histogram' => $histogramSimilarity,
                    'color' => $colorSimilarity,
                    'edges' => $edgeSimilarity
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Load image dengan error handling
     */
    private function loadImage($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($path);
            case 'png':
                return imagecreatefrompng($path);
            case 'gif':
                return imagecreatefromgif($path);
            default:
                return false;
        }
    }
    
    /**
     * Bandingkan histogram gambar
     */
    private function compareHistograms($img1, $img2) {
        // Resize ke ukuran yang sama untuk perbandingan
        $width = 64;
        $height = 64;
        
        $resized1 = imagecreatetruecolor($width, $height);
        $resized2 = imagecreatetruecolor($width, $height);
        
        imagecopyresampled($resized1, $img1, 0, 0, 0, 0, $width, $height, imagesx($img1), imagesy($img1));
        imagecopyresampled($resized2, $img2, 0, 0, 0, 0, $width, $height, imagesx($img2), imagesy($img2));
        
        // Konversi ke grayscale dan hitung histogram
        $hist1 = $this->calculateHistogram($resized1);
        $hist2 = $this->calculateHistogram($resized2);
        
        // Hitung similarity dengan correlation
        $similarity = $this->correlationCoefficient($hist1, $hist2);
        
        // Convert to percentage (0-100)
        $percentage = (($similarity + 1) / 2) * 100;
        
        imagedestroy($resized1);
        imagedestroy($resized2);
        
        return max(0, min(100, $percentage));
    }
    
    /**
     * Hitung histogram untuk gambar grayscale
     */
    private function calculateHistogram($image) {
        $histogram = array_fill(0, 256, 0);
        $width = imagesx($image);
        $height = imagesy($image);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray = round(($r + $g + $b) / 3);
                $histogram[$gray]++;
            }
        }
        
        // Normalize
        $total = $width * $height;
        foreach ($histogram as &$value) {
            $value /= $total;
        }
        
        return $histogram;
    }
    
    /**
     * Hitung koefisien korelasi
     */
    private function correlationCoefficient($arr1, $arr2) {
        $n = count($arr1);
        $sum1 = array_sum($arr1);
        $sum2 = array_sum($arr2);
        
        $sum1Sq = 0;
        $sum2Sq = 0;
        $pSum = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum1Sq += pow($arr1[$i], 2);
            $sum2Sq += pow($arr2[$i], 2);
            $pSum += $arr1[$i] * $arr2[$i];
        }
        
        $num = $pSum - ($sum1 * $sum2 / $n);
        $den = sqrt(($sum1Sq - pow($sum1, 2) / $n) * ($sum2Sq - pow($sum2, 2) / $n));
        
        if ($den == 0) return 0;
        
        return $num / $den;
    }
    
    /**
     * Bandingkan warna rata-rata
     */
    private function compareAverageColor($img1, $img2) {
        $color1 = $this->getAverageColor($img1);
        $color2 = $this->getAverageColor($img2);
        
        // Hitung perbedaan Euclidean
        $diff = sqrt(
            pow($color1['r'] - $color2['r'], 2) +
            pow($color1['g'] - $color2['g'], 2) +
            pow($color1['b'] - $color2['b'], 2)
        );
        
        // Convert to similarity percentage (max difference ~442)
        $similarity = max(0, 100 - ($diff / 4.42));
        
        return $similarity;
    }
    
    /**
     * Dapatkan warna rata-rata gambar
     */
    private function getAverageColor($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        $total = $width * $height;
        
        $r = $g = $b = 0;
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r += ($rgb >> 16) & 0xFF;
                $g += ($rgb >> 8) & 0xFF;
                $b += $rgb & 0xFF;
            }
        }
        
        return [
            'r' => round($r / $total),
            'g' => round($g / $total),
            'b' => round($b / $total)
        ];
    }
    
    /**
     * Bandingkan edge detection sederhana
     */
    private function compareEdges($img1, $img2) {
        // Konversi ke grayscale
        $gray1 = $this->convertToGray($img1);
        $gray2 = $this->convertToGray($img2);
        
        // Deteksi edge sederhana (Sobel-like)
        $edges1 = $this->detectEdges($gray1);
        $edges2 = $this->detectEdges($gray2);
        
        // Hitung similarity
        $similar = 0;
        $total = 0;
        
        for ($x = 1; $x < count($edges1) - 1; $x++) {
            for ($y = 1; $y < count($edges1[0]) - 1; $y++) {
                if (abs($edges1[$x][$y] - $edges2[$x][$y]) < 10) {
                    $similar++;
                }
                $total++;
            }
        }
        
        imagedestroy($gray1);
        imagedestroy($gray2);
        
        return $total > 0 ? ($similar / $total) * 100 : 0;
    }
    
    /**
     * Konversi gambar ke grayscale
     */
    private function convertToGray($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        $gray = imagecreatetruecolor($width, $height);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $grayValue = round(($r + $g + $b) / 3);
                $color = imagecolorallocate($gray, $grayValue, $grayValue, $grayValue);
                imagesetpixel($gray, $x, $y, $color);
            }
        }
        
        return $gray;
    }
    
    /**
     * Deteksi edge sederhana
     */
    private function detectEdges($grayImage) {
        $width = imagesx($grayImage);
        $height = imagesy($grayImage);
        $edges = [];
        
        for ($x = 1; $x < $width - 1; $x++) {
            $edges[$x] = [];
            for ($y = 1; $y < $height - 1; $y++) {
                // Simple gradient calculation
                $gx = (
                    $this->getGrayPixel($grayImage, $x+1, $y-1) + 
                    2 * $this->getGrayPixel($grayImage, $x+1, $y) + 
                    $this->getGrayPixel($grayImage, $x+1, $y+1) -
                    $this->getGrayPixel($grayImage, $x-1, $y-1) - 
                    2 * $this->getGrayPixel($grayImage, $x-1, $y) - 
                    $this->getGrayPixel($grayImage, $x-1, $y+1)
                );
                
                $gy = (
                    $this->getGrayPixel($grayImage, $x-1, $y+1) + 
                    2 * $this->getGrayPixel($grayImage, $x, $y+1) + 
                    $this->getGrayPixel($grayImage, $x+1, $y+1) -
                    $this->getGrayPixel($grayImage, $x-1, $y-1) - 
                    2 * $this->getGrayPixel($grayImage, $x, $y-1) - 
                    $this->getGrayPixel($grayImage, $x+1, $y-1)
                );
                
                $edges[$x][$y] = sqrt(pow($gx, 2) + pow($gy, 2));
            }
        }
        
        return $edges;
    }
    
    /**
     * Ambil nilai pixel grayscale
     */
    private function getGrayPixel($image, $x, $y) {
        $rgb = imagecolorat($image, $x, $y);
        return $rgb & 0xFF; // Grayscale, semua channel sama
    }
    
    /**
     * Simpan hasil matching ke log
     */
    public function saveMatchResult($studentId, $similarity, $passed, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'student_id' => $studentId,
            'similarity' => $similarity,
            'passed' => $passed,
            'details' => $details
        ];
        
        $logFile = $this->tempDir . "match_log_{$studentId}_" . date('Ymd') . ".json";
        $logs = [];
        
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true);
        }
        
        $logs[] = $logData;
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
        
        return $logData;
    }
}
