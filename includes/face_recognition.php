<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/face_matcher.php';

class FaceRecognition {
    public static function registerFace($imageData) {
        if (empty($imageData)) {
            return null;
        }

        $clean = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);
        $clean = str_replace(' ', '+', $clean);
        $binary = base64_decode($clean);
        if ($binary === false) {
            return null;
        }

        return hash('sha256', $binary);
    }

    public static function matchFace($referencePath, $imageData, $label = '') {
        if (empty($referencePath) || empty($imageData)) {
            return [
                'match' => false,
                'score' => 0,
                'message' => 'Data tidak lengkap'
            ];
        }

        $matcher = new FaceMatcher();
        $selfieResult = $matcher->saveSelfie('match', $imageData);
        if (empty($selfieResult['success'])) {
            return [
                'match' => false,
                'score' => 0,
                'message' => 'Gagal menyimpan foto sementara'
            ];
        }

        $matchResult = $matcher->matchFaces($referencePath, $selfieResult['path'], [
            'label' => $label
        ]);

        if (!empty($selfieResult['path']) && file_exists($selfieResult['path'])) {
            unlink($selfieResult['path']);
        }

        if (empty($matchResult['success'])) {
            return [
                'match' => false,
                'score' => 0,
                'message' => $matchResult['error'] ?? 'Gagal memproses wajah'
            ];
        }

        return [
            'match' => !empty($matchResult['passed']),
            'score' => $matchResult['similarity'] ?? 0,
            'message' => !empty($matchResult['passed'])
                ? 'Wajah terverifikasi'
                : 'Similarity di bawah threshold',
            'details' => $matchResult['details'] ?? []
        ];
    }
}
