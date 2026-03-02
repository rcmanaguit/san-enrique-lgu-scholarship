<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordWriterFactory;
use PhpOffice\PhpWord\PhpWord;

function export_rows_to_format(
    string $format,
    string $title,
    array $columns,
    array $rows,
    string $filenameBase
): void {
    $format = strtolower(trim($format));
    if (!in_array($format, ['pdf', 'docx', 'xlsx'], true)) {
        $format = 'xlsx';
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if ($format === 'xlsx') {
        export_rows_to_xlsx($title, $columns, $rows, $filenameBase);
        return;
    }

    if ($format === 'docx') {
        export_rows_to_docx($title, $columns, $rows, $filenameBase);
        return;
    }

    export_rows_to_pdf($title, $columns, $rows, $filenameBase);
}

function export_rows_to_xlsx(string $title, array $columns, array $rows, string $filenameBase): void
{
    if (!class_exists(Spreadsheet::class)) {
        http_response_code(500);
        echo 'Composer dependency missing: phpoffice/phpspreadsheet';
        exit;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Export');

    $colIndex = 1;
    foreach ($columns as $label) {
        $cell = Coordinate::stringFromColumnIndex($colIndex) . '1';
        $sheet->setCellValue($cell, $label);
        $colIndex++;
    }

    $rowNum = 2;
    foreach ($rows as $row) {
        $colIndex = 1;
        foreach (array_keys($columns) as $key) {
            $cell = Coordinate::stringFromColumnIndex($colIndex) . (string) $rowNum;
            $sheet->setCellValue($cell, (string) ($row[$key] ?? ''));
            $colIndex++;
        }
        $rowNum++;
    }

    $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($columns)));
    $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
    foreach (range('A', $lastColumn) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function export_rows_to_docx(string $title, array $columns, array $rows, string $filenameBase): void
{
    if (!class_exists(PhpWord::class)) {
        http_response_code(500);
        echo 'Composer dependency missing: phpoffice/phpword';
        exit;
    }

    $phpWord = new PhpWord();
    $section = $phpWord->addSection(['orientation' => 'landscape']);
    $section->addText($title, ['bold' => true, 'size' => 16]);
    $section->addText('Generated on ' . date('F d, Y h:i A'), ['size' => 10], ['spaceAfter' => 200]);

    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '9DBFD8',
        'cellMargin' => 70,
    ]);

    $table->addRow();
    foreach ($columns as $label) {
        $table->addCell(1400)->addText((string) $label, ['bold' => true, 'size' => 9]);
    }

    foreach ($rows as $row) {
        $table->addRow();
        foreach (array_keys($columns) as $key) {
            $table->addCell(1400)->addText((string) ($row[$key] ?? ''), ['size' => 9]);
        }
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.docx"');
    header('Cache-Control: max-age=0');

    $writer = WordWriterFactory::createWriter($phpWord, 'Word2007');
    $writer->save('php://output');
    exit;
}

function export_rows_to_pdf(string $title, array $columns, array $rows, string $filenameBase): void
{
    if (!class_exists(Dompdf::class)) {
        http_response_code(500);
        echo 'Composer dependency missing: dompdf/dompdf';
        exit;
    }

    $headerCells = '';
    foreach ($columns as $label) {
        $headerCells .= '<th>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</th>';
    }

    $htmlRows = '';
    foreach ($rows as $row) {
        $htmlRows .= '<tr>';
        foreach (array_keys($columns) as $key) {
            $htmlRows .= '<td>' . htmlspecialchars((string) ($row[$key] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $htmlRows .= '</tr>';
    }

    $html = '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f3340; }
h1 { font-size: 16px; margin: 0 0 6px; }
p { margin: 0 0 12px; color: #4f6270; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #9dbfd8; padding: 4px; vertical-align: top; }
th { background: #eaf5ff; font-weight: bold; }
</style>
</head>
<body>
<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>
<p>Generated on ' . htmlspecialchars(date('F d, Y h:i A'), ENT_QUOTES, 'UTF-8') . '</p>
<table>
<thead><tr>' . $headerCells . '</tr></thead>
<tbody>' . $htmlRows . '</tbody>
</table>
</body>
</html>';

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
    header('Cache-Control: max-age=0');
    echo $dompdf->output();
    exit;
}
