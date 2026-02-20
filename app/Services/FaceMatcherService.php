<?php

namespace App\Services;

class FaceMatcherService
{
    private $threshold = 70; // Minimum similarity percentage
    private $tempDir = '';
    private $facesDir = '';
    private $attendanceDir = '';
    private $pythonBin = 'python';
    private $pythonScript = null;
    private $pythonEnabled = false;
    private $allowFallback = false;
    private $deepfaceModel = 'SFace';
    private $deepfaceDetector = 'opencv';
    private $deepfaceMetric = 'cosine';
    private $deepfaceEnforceDetection = true;
    private $deepfaceMaxReferences = 1;
    private $deepfaceUseBackup = true;
    private $deepfaceBackupModel = 'SFace';
    private $deepfaceBackupDetector = 'mtcnn';
    private $deepfaceBackupMaxReferences = 1;
    private $deepfaceDetectorFallbacks = false;
    private $pythonTimeoutSeconds = 60;
    
    public function __construct() {
        $uploadsBase = public_path('uploads');
        if ($uploadsBase === '' || $uploadsBase === null) {
            $uploadsBase = base_path('public/uploads');
        }
        $uploadsBase = rtrim((string) $uploadsBase, '/\\') . DIRECTORY_SEPARATOR;
        $this->tempDir = $uploadsBase . 'temp' . DIRECTORY_SEPARATOR;
        $this->facesDir = $uploadsBase . 'faces' . DIRECTORY_SEPARATOR;
        $this->attendanceDir = $uploadsBase . 'attendance' . DIRECTORY_SEPARATOR;

        // Create directories if not exist
        $this->createDirectories();

        if (defined('FACE_MATCH_THRESHOLD')) {
            $this->threshold = max(0, min(100, (float) FACE_MATCH_THRESHOLD));
        }
        if (defined('PYTHON_BIN') && PYTHON_BIN) {
            $this->pythonBin = PYTHON_BIN;
        }
        if (defined('FACE_MATCH_ALLOW_FALLBACK')) {
            $this->allowFallback = filter_var((string) FACE_MATCH_ALLOW_FALLBACK, FILTER_VALIDATE_BOOLEAN);
        }
        if (defined('FACE_MATCH_MODEL') && FACE_MATCH_MODEL) {
            $this->deepfaceModel = (string) FACE_MATCH_MODEL;
        }
        if (defined('FACE_MATCH_DETECTOR') && FACE_MATCH_DETECTOR) {
            $this->deepfaceDetector = (string) FACE_MATCH_DETECTOR;
        }
        if (defined('FACE_MATCH_DISTANCE_METRIC') && FACE_MATCH_DISTANCE_METRIC) {
            $this->deepfaceMetric = (string) FACE_MATCH_DISTANCE_METRIC;
        }
        if (defined('FACE_MATCH_ENFORCE_DETECTION')) {
            $this->deepfaceEnforceDetection = filter_var((string) FACE_MATCH_ENFORCE_DETECTION, FILTER_VALIDATE_BOOLEAN);
        }
        if (defined('FACE_MATCH_MAX_REFERENCES')) {
            $this->deepfaceMaxReferences = max(1, (int) FACE_MATCH_MAX_REFERENCES);
        }
        if (defined('FACE_MATCH_USE_BACKUP')) {
            $this->deepfaceUseBackup = filter_var((string) FACE_MATCH_USE_BACKUP, FILTER_VALIDATE_BOOLEAN);
        }
        if (defined('FACE_MATCH_BACKUP_MODEL') && FACE_MATCH_BACKUP_MODEL) {
            $this->deepfaceBackupModel = (string) FACE_MATCH_BACKUP_MODEL;
        }
        if (defined('FACE_MATCH_BACKUP_DETECTOR') && FACE_MATCH_BACKUP_DETECTOR) {
            $this->deepfaceBackupDetector = (string) FACE_MATCH_BACKUP_DETECTOR;
        }
        if (defined('FACE_MATCH_BACKUP_MAX_REFERENCES')) {
            $this->deepfaceBackupMaxReferences = max(1, (int) FACE_MATCH_BACKUP_MAX_REFERENCES);
        }
        if (defined('FACE_MATCH_DETECTOR_FALLBACKS')) {
            $this->deepfaceDetectorFallbacks = filter_var((string) FACE_MATCH_DETECTOR_FALLBACKS, FILTER_VALIDATE_BOOLEAN);
        }
        if (defined('FACE_MATCH_TIMEOUT_SECONDS')) {
            $this->pythonTimeoutSeconds = max(15, (int) FACE_MATCH_TIMEOUT_SECONDS);
        }

        $scriptPath = realpath(public_path('face/faces_conf/face_match.py'));
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
     * Dapatkan path foto referensi aktif (terbaru) berdasarkan NISN / filename database.
     */
    public function getReferencePath($nisn, $photoReference = null) {
        $candidates = $this->getReferenceCandidates($nisn, $photoReference);
        return !empty($candidates) ? $candidates[0] : null;
    }

    /**
     * Dapatkan daftar kandidat foto referensi siswa.
     */
    public function getReferenceCandidates($nisn, $photoReference = null) {
        $nisn = trim((string) $nisn);
        $files = [];

        $photoReference = trim((string) $photoReference);
        if ($photoReference !== '') {
            $normalizedReference = function_exists('normalize_face_reference_path')
                ? normalize_face_reference_path($photoReference)
                : ltrim(str_replace('\\', '/', $photoReference), '/');

            $directCandidates = [$photoReference];
            if ($normalizedReference !== '') {
                $normalizedForFilesystem = str_replace('/', DIRECTORY_SEPARATOR, ltrim($normalizedReference, '/'));
                $directCandidates[] = $normalizedReference;
                $directCandidates[] = $this->facesDir . $normalizedForFilesystem;
                $directCandidates[] = public_path('uploads/faces/' . ltrim($normalizedReference, '/'));
            }

            if (function_exists('resolve_face_reference_file_path')) {
                $resolvedReference = resolve_face_reference_file_path($photoReference);
                if (is_string($resolvedReference) && $resolvedReference !== '') {
                    $directCandidates[] = $resolvedReference;
                }
            }

            foreach ($directCandidates as $candidate) {
                if (is_file($candidate)) {
                    $files[] = $candidate;
                }
            }
        }

        if ($nisn !== '') {
            $exactPatterns = [
                $this->facesDir . $nisn . '.jpg',
                $this->facesDir . $nisn . '.jpeg',
                $this->facesDir . $nisn . '.png',
                $this->facesDir . $nisn . '.webp',
                $this->facesDir . $nisn . DIRECTORY_SEPARATOR . '*.jpg',
                $this->facesDir . $nisn . DIRECTORY_SEPARATOR . '*.jpeg',
                $this->facesDir . $nisn . DIRECTORY_SEPARATOR . '*.png',
                $this->facesDir . $nisn . DIRECTORY_SEPARATOR . '*.webp'
            ];
            foreach ($exactPatterns as $pattern) {
                $matches = glob($pattern) ?: [];
                foreach ($matches as $match) {
                    $files[] = $match;
                }
            }

            $broadPatterns = [
                $this->facesDir . $nisn . '-*',
                $this->facesDir . $nisn . '_*'
            ];
            foreach ($broadPatterns as $pattern) {
                $matches = glob($pattern) ?: [];
                foreach ($matches as $match) {
                    $files[] = $match;
                }
            }
        }

        $files = array_values(array_unique(array_filter($files, function($path) {
            if (!is_file($path)) {
                return false;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'], true);
        })));

        if (empty($files) && $nisn !== '') {
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->facesDir, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $fileInfo) {
                    if (!$fileInfo->isFile()) {
                        continue;
                    }
                    $filename = $fileInfo->getFilename();
                    if (stripos($filename, $nisn) === false) {
                        continue;
                    }
                    $ext = strtolower($fileInfo->getExtension());
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp'], true)) {
                        continue;
                    }
                    $files[] = $fileInfo->getPathname();
                }
                $files = array_values(array_unique($files));
            } catch (Exception $e) {
                // ignore recursive scan failures
            }
        }

        usort($files, function($a, $b) {
            $mtimeA = @filemtime($a) ?: 0;
            $mtimeB = @filemtime($b) ?: 0;
            if ($mtimeA === $mtimeB) {
                return strcmp($a, $b);
            }
            return ($mtimeB <=> $mtimeA);
        });

        return $files;
    }

    /**
     * Konversi absolute file path di folder public menjadi URL browser.
     * Contoh:
     *   C:\xampp\htdocs\presenova\public\uploads\faces\a.jpg -> /presenova/uploads/faces/a.jpg
     */
    public function toPublicUrl($filePath, $prefix = '..') {
        $filePath = trim((string) $filePath);
        if ($filePath === '') {
            return '';
        }

        // Sudah berupa URL/data URI atau path web relatif.
        if (preg_match('#^(https?:)?//#i', $filePath) || stripos($filePath, 'data:') === 0) {
            return $filePath;
        }

        $normalized = str_replace('\\', '/', $filePath);
        $isAbsoluteWindows = (bool) preg_match('#^[A-Za-z]:/#', $normalized);
        $isAbsoluteUnix = strpos($normalized, '/') === 0;

        if ($isAbsoluteWindows || $isAbsoluteUnix) {
            $publicRoot = realpath(public_path());
            if ($publicRoot !== false) {
                $normalizedPublicRoot = rtrim(str_replace('\\', '/', $publicRoot), '/');
                if (stripos($normalized, $normalizedPublicRoot . '/') === 0) {
                    $relative = ltrim(substr($normalized, strlen($normalizedPublicRoot)), '/');
                    return $this->buildPublicWebUrl($relative);
                }
            }

            $publicMarkerPos = stripos($normalized, '/public/');
            if ($publicMarkerPos !== false) {
                $relative = ltrim(substr($normalized, $publicMarkerPos + strlen('/public/')), '/');
                return $this->buildPublicWebUrl($relative);
            }
        }

        $relative = ltrim($normalized, '/');
        if ($relative !== '' && preg_match('#^(uploads|assets|face|scripts)/#i', $relative)) {
            return $this->buildPublicWebUrl($relative);
        }

        $encodedPath = $this->encodeUrlPath($relative !== '' ? $relative : $normalized);
        $prefix = rtrim((string) $prefix, '/');
        if ($prefix === '' || $prefix === '.') {
            return $encodedPath;
        }

        return $prefix . '/' . ltrim($encodedPath, '/');
    }

    private function buildPublicWebUrl(string $relativePath): string
    {
        $relativePath = ltrim($this->encodeUrlPath($relativePath), '/');
        if ($relativePath === '') {
            return '/';
        }

        $basePath = $this->resolveAppBasePath();
        if ($basePath === '') {
            return '/' . $relativePath;
        }

        return '/' . $basePath . '/' . $relativePath;
    }

    private function resolveAppBasePath(): string
    {
        $requestPath = '';
        try {
            $requestPath = trim((string) request()->getBasePath(), '/');
        } catch (\Throwable) {
            $requestPath = '';
        }

        if ($requestPath !== '') {
            return $requestPath;
        }

        $configuredPath = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        return $configuredPath;
    }

    private function encodeUrlPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return '';
        }

        $segments = explode('/', ltrim($path, '/'));
        $encoded = array_map(static function ($segment) {
            if ($segment === '') {
                return '';
            }
            return rawurlencode($segment);
        }, $segments);

        return implode('/', $encoded);
    }
    
    /**
     * Face matching utama berbasis DeepFace via script Python.
     */
    public function matchFaces($referencePath, $selfiePath, $options = []) {
        if (!file_exists($referencePath) || !file_exists($selfiePath)) {
            return ['success' => false, 'error' => 'File tidak ditemukan'];
        }

        $label = isset($options['label']) ? trim((string) $options['label']) : '';
        $annotate = !empty($options['annotate']);
        $allowFallback = array_key_exists('allow_fallback', $options)
            ? !empty($options['allow_fallback'])
            : $this->allowFallback;

        if ($this->pythonEnabled) {
            $pythonResult = $this->matchFacesWithPython($referencePath, $selfiePath, $label, [
                'annotate' => $annotate
            ]);
            if (!empty($pythonResult['success'])) {
                return $pythonResult;
            }

            if (!$allowFallback) {
                return [
                    'success' => false,
                    'error' => $pythonResult['error'] ?? 'Python matcher gagal dijalankan'
                ];
            }
        } elseif (!$allowFallback) {
            return [
                'success' => false,
                'error' => 'Python matcher tidak tersedia di face/faces_conf'
            ];
        }

        return $this->matchFacesFallback($referencePath, $selfiePath);
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
            . ' -u'
            . ' ' . escapeshellarg($this->pythonScript)
            . ' --reference ' . escapeshellarg($referencePath)
            . ' --candidate ' . escapeshellarg($selfiePath)
            . ' --threshold ' . escapeshellarg((string) $threshold)
            . ' --model ' . escapeshellarg($this->deepfaceModel)
            . ' --detector ' . escapeshellarg($this->deepfaceDetector)
            . ' --metric ' . escapeshellarg($this->deepfaceMetric)
            . ' --enforce-detection ' . escapeshellarg($this->deepfaceEnforceDetection ? 'true' : 'false')
            . ' --max-references ' . escapeshellarg((string) $this->deepfaceMaxReferences)
            . ' --use-backup ' . escapeshellarg($this->deepfaceUseBackup ? 'true' : 'false')
            . ' --backup-model ' . escapeshellarg($this->deepfaceBackupModel)
            . ' --backup-detector ' . escapeshellarg($this->deepfaceBackupDetector)
            . ' --backup-max-references ' . escapeshellarg((string) $this->deepfaceBackupMaxReferences)
            . ' --detector-fallbacks ' . escapeshellarg($this->deepfaceDetectorFallbacks ? 'true' : 'false')
            . ' --max-runtime-seconds ' . escapeshellarg((string) $this->pythonTimeoutSeconds);

        if ($label !== '') {
            $cmd .= ' --label ' . escapeshellarg($label);
        }
        if ($annotate && $outputPath) {
            $cmd .= ' --output ' . escapeshellarg($outputPath);
        }

        $run = $this->runPythonCommand($cmd, $this->pythonTimeoutSeconds + 5);
        if (!empty($run['timed_out'])) {
            return [
                'success' => false,
                'error' => 'Verifikasi wajah timeout di server. Coba ulangi dengan pencahayaan lebih baik.'
            ];
        }
        $raw = trim((string) ($run['output'] ?? ''));

        $data = $this->parsePythonJson($raw);
        if (!is_array($data)) {
            return [
                'success' => false,
                'error' => 'Output python tidak valid',
                'raw' => $this->truncateDebugText($raw)
            ];
        }

        if (empty($data['success'])) {
            return [
                'success' => false,
                'error' => $data['error'] ?? 'DeepFace matcher gagal'
            ];
        }

        $details = isset($data['details']) && is_array($data['details']) ? $data['details'] : [];
        $details['source'] = $details['source'] ?? 'python-deepface';
        $details['threshold'] = $threshold;
        $details['model'] = $this->deepfaceModel;
        $details['detector'] = $this->deepfaceDetector;
        $details['metric'] = $this->deepfaceMetric;
        $details['backup_enabled'] = $this->deepfaceUseBackup;
        $details['backup_model'] = $this->deepfaceBackupModel;
        $details['backup_detector'] = $this->deepfaceBackupDetector;

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

    private function runPythonCommand($command, $timeoutSeconds = 60) {
        $timeoutSeconds = max(5, (int) $timeoutSeconds);
        $command = trim((string) $command);

        if ($command === '') {
            return [
                'output' => '',
                'exit_code' => 1,
                'timed_out' => false
            ];
        }

        if (!function_exists('proc_open')) {
            $output = [];
            $exitCode = 0;
            exec($command . ' 2>&1', $output, $exitCode);
            return [
                'output' => trim(implode("\n", $output)),
                'exit_code' => $exitCode,
                'timed_out' => false
            ];
        }

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return [
                'output' => '',
                'exit_code' => 1,
                'timed_out' => false
            ];
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $started = microtime(true);

        while (true) {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }

            if ((microtime(true) - $started) >= $timeoutSeconds) {
                $timedOut = true;
                @proc_terminate($process);
                break;
            }

            usleep(100000);
        }

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);
        if ($timedOut && $exitCode === 0) {
            $exitCode = 124;
        }

        $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));

        return [
            'output' => $output,
            'exit_code' => $exitCode,
            'timed_out' => $timedOut
        ];
    }

    private function truncateDebugText($text, $maxLength = 600) {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength) . '...';
    }

    private function parsePythonJson($raw) {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $lines = preg_split('/\R/', $raw) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $firstBrace = strpos($raw, '{');
        $lastBrace = strrpos($raw, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $candidate = substr($raw, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function matchFacesFallback($referencePath, $selfiePath) {
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
