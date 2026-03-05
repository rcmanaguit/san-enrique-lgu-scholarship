<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordWriterFactory;
use PhpOffice\PhpWord\PhpWord;

function export_official_header_lines(): array
{
    return [
        'REPUBLIC OF THE PHILIPPINES',
        'Province of Negros Occidental',
        'MUNICIPALITY OF SAN ENRIQUE',
        'OFFICE OF THE MAYOR',
        'LGU SCHOLARSHIP PROGRAM',
    ];
}

function export_official_logo_path(): string
{
    $logoPath = dirname(__DIR__) . '/assets/images/branding/lgu-logo.png';
    return is_file($logoPath) ? $logoPath : '';
}

function export_official_header_html(string $title): string
{
    $logoPath = export_official_logo_path();
    $logoHtml = '';
    if ($logoPath !== '') {
        $mime = function_exists('mime_content_type') ? (string) mime_content_type($logoPath) : 'image/png';
        $raw = @file_get_contents($logoPath);
        if ($raw !== false && $raw !== '') {
            $base64 = base64_encode($raw);
            $logoHtml = '<img src="data:' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . ';base64,' . $base64 . '" alt="LGU Logo" class="gov-logo">';
        }
    }

    $lines = export_official_header_lines();
    $textHtml = '';
    foreach ($lines as $index => $line) {
        $class = 'gov-line-' . ($index + 1);
        $textHtml .= '<div class="' . $class . '">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    return '<div class="gov-header">'
        . $logoHtml
        . '<div class="gov-header-text">' . $textHtml . '</div>'
        . '</div>'
        . '<div class="report-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
}

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
    $section = $phpWord->addSection([
        'orientation' => 'portrait',
        'paperSize' => 'A4',
    ]);

    $headerTable = $section->addTable([
        'borderSize' => 0,
        'cellMargin' => 40,
    ]);
    $headerTable->addRow();
    $logoCell = $headerTable->addCell(1200);
    $logoPath = export_official_logo_path();
    if ($logoPath !== '') {
        $logoCell->addImage($logoPath, ['width' => 46, 'height' => 46]);
    }
    $textCell = $headerTable->addCell(8500);
    $lines = export_official_header_lines();
    foreach ($lines as $index => $line) {
        $isMajor = $index === 2 || $index === 4;
        $textCell->addText($line, ['bold' => $isMajor, 'size' => $isMajor ? 11 : 9], ['alignment' => 'left', 'spaceAfter' => 0]);
    }

    $section->addText($title, ['bold' => true, 'size' => 12], ['spaceBefore' => 120, 'spaceAfter' => 80]);
    $section->addText('Generated on ' . date('F d, Y h:i A'), ['size' => 10], ['spaceAfter' => 200]);

    $columnCount = max(1, count($columns));
    $cellWidth = max(1200, (int) floor(9500 / $columnCount));
    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '9DBFD8',
        'cellMargin' => 70,
    ]);

    $table->addRow();
    foreach ($columns as $label) {
        $table->addCell($cellWidth)->addText((string) $label, ['bold' => true, 'size' => 9]);
    }

    foreach ($rows as $row) {
        $table->addRow();
        foreach (array_keys($columns) as $key) {
            $table->addCell($cellWidth)->addText((string) ($row[$key] ?? ''), ['size' => 9]);
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

    $headerHtml = export_official_header_html($title);
    $html = '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { size: A4 portrait; margin: 18mm 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f3340; }
.gov-header { display: table; width: 100%; margin-bottom: 6px; }
.gov-logo { display: table-cell; width: 48px; height: 48px; object-fit: contain; vertical-align: top; }
.gov-header-text { display: table-cell; vertical-align: top; padding-left: 8px; }
.gov-line-1, .gov-line-2, .gov-line-4 { font-size: 9px; line-height: 1.2; }
.gov-line-3 { font-size: 12px; font-weight: bold; line-height: 1.15; }
.gov-line-5 { font-size: 11px; font-weight: bold; line-height: 1.2; margin-top: 2px; }
.report-title { font-size: 13px; font-weight: bold; margin: 6px 0 2px; }
p { margin: 0 0 10px; color: #4f6270; font-size: 9px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #9dbfd8; padding: 4px; vertical-align: top; }
th { background: #eaf5ff; font-weight: bold; }
</style>
</head>
<body>
'.$headerHtml.'
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
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
    header('Cache-Control: max-age=0');
    echo $dompdf->output();
    exit;
}
