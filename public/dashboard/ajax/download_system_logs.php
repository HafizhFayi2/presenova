<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

if (ob_get_length()) {
    ob_clean();
}

$db = new Database();
$timestamp = date('Ymd_His');

$sql = "SELECT l.user_type, l.action, l.details, l.ip_address, l.user_agent, l.created_at,
               COALESCE(u.fullname, t.teacher_name, s.student_name, '-') AS actor_name
        FROM activity_logs l
        LEFT JOIN user u ON l.user_type = 'admin' AND l.user_id = u.user_id
        LEFT JOIN teacher t ON l.user_type = 'guru' AND l.user_id = t.id
        LEFT JOIN student s ON (l.user_type = 'student' OR l.user_type = 'siswa') AND l.user_id = s.id
        ORDER BY l.created_at DESC";
$stmt = $db->query($sql);
$logs = $stmt ? $stmt->fetchAll() : [];

$autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    // Fallback CSV with proper extension/mime to avoid Excel extension warning.
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="log_system_' . $timestamp . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Nama', 'Tipe User', 'Aktivitas', 'Detail', 'IP', 'Browser', 'Waktu']);
    $no = 1;
    foreach ($logs as $log) {
        fputcsv($output, [
            $no++,
            $log['actor_name'] ?? '-',
            $log['user_type'] ?? '',
            $log['action'] ?? '',
            $log['details'] ?? '',
            $log['ip_address'] ?? '',
            $log['user_agent'] ?? '',
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
$sheet->mergeCells('A1:H1');
$sheet->setCellValue('A1', 'LOG SISTEM PRESENOVA');
$sheet->setCellValue('A2', 'Diekspor pada: ' . date('d/m/Y H:i:s'));
$sheet->mergeCells('A2:H2');

$headers = ['No', 'Nama', 'Tipe User', 'Aktivitas', 'Detail', 'IP', 'Browser', 'Waktu'];
foreach ($headers as $index => $headerText) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue($col . '4', $headerText);
}

$sheet->getStyle('A1:H1')->applyFromArray([
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
$sheet->getStyle('A2:H2')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'DBEAFE'],
    ],
]);
$sheet->getStyle('A4:H4')->applyFromArray([
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
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, (string)($log['actor_name'] ?? '-'));
    $sheet->setCellValue('C' . $row, (string)($log['user_type'] ?? '-'));
    $sheet->setCellValue('D' . $row, (string)($log['action'] ?? '-'));
    $sheet->setCellValue('E' . $row, (string)($log['details'] ?? '-'));
    $sheet->setCellValue('F' . $row, (string)($log['ip_address'] ?? '-'));
    $sheet->setCellValue('G' . $row, (string)($log['user_agent'] ?? '-'));
    $sheet->setCellValue('H' . $row, (string)($log['created_at'] ?? '-'));
    $row++;
}

$lastDataRow = max(4, $row - 1);
$sheet->getStyle('A4:H' . $lastDataRow)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1'],
        ],
    ],
    'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP],
]);

if ($lastDataRow >= 5) {
    $sheet->getStyle('E5:E' . $lastDataRow)->getAlignment()->setWrapText(true);
    $sheet->getStyle('G5:G' . $lastDataRow)->getAlignment()->setWrapText(true);
}

$columnWidths = [
    'A' => 6,
    'B' => 22,
    'C' => 14,
    'D' => 20,
    'E' => 34,
    'F' => 16,
    'G' => 42,
    'H' => 22,
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
