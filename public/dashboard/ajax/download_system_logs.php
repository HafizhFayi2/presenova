<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

if (ob_get_length()) {
    ob_clean();
}

$db = new Database();
$timestamp = date('Ymd_His');
$exportedBy = trim((string)($_SESSION['fullname'] ?? $_SESSION['username'] ?? ''));
if ($exportedBy === '') {
    $exportedBy = 'Administrator';
}

$sql = "SELECT l.user_type, l.action, l.details, l.created_at,
               COALESCE(u.fullname, t.teacher_name, s.student_name, '-') AS actor_name
        FROM activity_logs l
        LEFT JOIN user u ON l.user_type = 'admin' AND l.user_id = u.user_id
        LEFT JOIN teacher t ON l.user_type = 'guru' AND l.user_id = t.id
        LEFT JOIN student s ON (l.user_type = 'student' OR l.user_type = 'siswa') AND l.user_id = s.id
        ORDER BY l.created_at DESC";
$stmt = $db->query($sql);
$logs = $stmt ? $stmt->fetchAll() : [];

$resolveLogColor = static function ($userType, $action): string {
    $userType = strtolower(trim((string) $userType));
    $action = strtolower(trim((string) $action));

    if ($action !== '') {
        if (stripos($action, 'failed') !== false || stripos($action, 'blocked') !== false) {
            return 'Merah';
        }
        if (stripos($action, 'login') !== false) {
            return 'Hijau';
        }
        if (stripos($action, 'attendance') !== false) {
            return 'Oranye';
        }
        if (stripos($action, 'logout') !== false) {
            return 'Abu-abu';
        }
    }

    switch ($userType) {
        case 'admin':
            return 'Biru';
        case 'guru':
            return 'Hijau';
        case 'student':
        case 'siswa':
            return 'Oranye';
        default:
            return 'Abu-abu';
    }
};

$colorPalette = [
    'Merah' => ['fill' => 'FEE2E2', 'font' => '991B1B'],
    'Hijau' => ['fill' => 'DCFCE7', 'font' => '166534'],
    'Biru' => ['fill' => 'DBEAFE', 'font' => '1D4ED8'],
    'Oranye' => ['fill' => 'FFEDD5', 'font' => '9A3412'],
    'Abu-abu' => ['fill' => 'E2E8F0', 'font' => '334155'],
];

$rowPalette = [
    'Merah' => ['fill' => 'F8D7DA', 'font' => '7A0C0C'],
    'Hijau' => ['fill' => 'D1FAE5', 'font' => '0B5D1E'],
    'Biru' => ['fill' => 'DBEAFE', 'font' => '1B4F8A'],
    'Oranye' => ['fill' => 'FFE4C7', 'font' => '8A3B00'],
    'Abu-abu' => ['fill' => 'E2E8F0', 'font' => '1F2937'],
];

$autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    // Fallback CSV with proper extension/mime to avoid Excel extension warning.
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="log_system_' . $timestamp . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Nama', 'Tipe User', 'Aktivitas', 'Detail', 'Waktu']);
    $no = 1;
    foreach ($logs as $log) {
        fputcsv($output, [
            $no++,
            $log['actor_name'] ?? '-',
            $log['user_type'] ?? '',
            $log['action'] ?? '',
            $log['details'] ?? '',
            $log['created_at'] ?? '',
        ]);
    }
    fclose($output);
    exit();
}

require_once $autoloadPath;

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Log Sistem');

$spreadsheet->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(10);
$sheet->mergeCells('A1:F1');
$sheet->setCellValue('A1', 'LOG SISTEM PRESENOVA');
$sheet->setCellValue('A2', 'Diekspor pada: ' . date('d/m/Y H:i:s') . ' oleh ' . $exportedBy);
$sheet->mergeCells('A2:F2');

$headers = ['No', 'Nama', 'Tipe User', 'Aktivitas', 'Detail', 'Waktu'];
foreach ($headers as $index => $headerText) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue($col . '4', $headerText);
}

$sheet->getStyle('A1:F1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1D4ED8'],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ],
]);
$sheet->getStyle('A2:F2')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'DBEAFE'],
    ],
]);
$sheet->getStyle('A4:F4')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '123E68'],
    ],
    'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
]);

$row = 5;
$no = 1;
foreach ($logs as $log) {
    $colorName = $resolveLogColor($log['user_type'] ?? '', $log['action'] ?? '');
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, (string)($log['actor_name'] ?? '-'));
    $sheet->setCellValue('C' . $row, (string)($log['user_type'] ?? '-'));
    $sheet->setCellValue('D' . $row, (string)($log['action'] ?? '-'));
    $sheet->setCellValue('E' . $row, (string)($log['details'] ?? '-'));
    $sheet->setCellValue('F' . $row, (string)($log['created_at'] ?? '-'));
    if (isset($rowPalette[$colorName])) {
        $palette = $rowPalette[$colorName];
        $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray([
            'font' => ['color' => ['rgb' => $palette['font']]],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => $palette['fill']],
            ],
        ]);
        $sheet->getStyle('B' . $row)->getFont()->setBold(true);
        $sheet->getStyle('D' . $row)->getFont()->setBold(true);
        $sheet->getStyle('F' . $row)->getFont()->setBold(true);
    }
    $row++;
}

$lastDataRow = max(4, $row - 1);
$sheet->getStyle('A4:F' . $lastDataRow)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1'],
        ],
    ],
    'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
]);

if ($lastDataRow >= 5) {
    $sheet->getStyle('A5:F' . $lastDataRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A5:A' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C5:C' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F5:F' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
}

if ($lastDataRow >= 5) {
    $sheet->getStyle('E5:E' . $lastDataRow)->getAlignment()->setWrapText(true);
}

$columnWidths = [
    'A' => 6,
    'B' => 22,
    'C' => 14,
    'D' => 20,
    'E' => 38,
    'F' => 22,
];
foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}
$sheet->freezePane('A5');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="log_system_' . $timestamp . '.xlsx"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
exit();
