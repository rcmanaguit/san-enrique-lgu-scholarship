<?php

namespace App\Support;

class OfficeExporter
{
    public static function stream(string $title, array $columns, array $rows, string $format, array $meta = []): never
    {
        $timestamp = date('Ymd-His');

        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . self::slug($title) . '-' . $timestamp . '.xls"');
        } elseif ($format === 'word') {
            header('Content-Type: application/msword; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . self::slug($title) . '-' . $timestamp . '.doc"');
        } else {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . self::escape($title) . '</title>';
        echo '<style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #1b2d3d; margin: 24px; }
            h1 { font-size: 18px; margin: 0 0 8px; }
            .meta { margin-bottom: 16px; color: #4a5e73; }
            .meta div { margin-bottom: 4px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #9fb3c8; padding: 8px; text-align: left; vertical-align: top; }
            th { background: #dfeaf5; }
            .empty { padding: 24px; text-align: center; color: #4a5e73; border: 1px solid #9fb3c8; }
            @media print {
                body { margin: 12px; }
            }
        </style>';

        if ($format === 'print') {
            echo '<script>window.addEventListener("load", function () { window.print(); });</script>';
        }

        echo '</head><body>';
        echo '<h1>' . self::escape($title) . '</h1>';

        if ($meta !== []) {
            echo '<div class="meta">';
            foreach ($meta as $label => $value) {
                echo '<div><strong>' . self::escape((string) $label) . ':</strong> ' . self::escape((string) $value) . '</div>';
            }
            echo '</div>';
        }

        if ($rows === []) {
            echo '<div class="empty">No records available for this list.</div>';
            echo '</body></html>';
            exit;
        }

        echo '<table><thead><tr>';
        foreach ($columns as $label) {
            echo '<th>' . self::escape((string) $label) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach (array_keys($columns) as $key) {
                echo '<td>' . self::escape((string) ($row[$key] ?? '')) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></body></html>';
        exit;
    }

    private static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? 'export';
        return trim($value, '-') ?: 'export';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
