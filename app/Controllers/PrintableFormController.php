<?php

namespace App\Controllers;

use App\Config\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

class PrintableFormController
{
    public function __construct()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \base_url('login'));
            exit;
        }
    }

    public function preview()
    {
        $applicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$applicationId) {
            $this->redirectToDashboardWithError('Application ID is required to preview the printable form.');
        }

        $printable = $this->loadPrintableApplication($applicationId);
        if ($printable === null) {
            $this->redirectToDashboardWithError('Application not found.');
        }

        $routeBase = $this->currentRouteBase();
        $cacheBust = time();
        $pdfUrl = \base_url($routeBase . '/print-form/pdf?id=' . $applicationId . '&v=' . $cacheBust);
        $downloadUrl = $pdfUrl . '&download=1';
        $backUrl = \base_url($this->dashboardPath());

        require __DIR__ . '/../../views/printables/application_form_preview.php';
    }

    public function pdf()
    {
        $applicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$applicationId) {
            http_response_code(400);
            exit('Application ID is required.');
        }

        $printable = $this->loadPrintableApplication($applicationId);
        if ($printable === null) {
            http_response_code(404);
            exit('Application not found.');
        }

        extract($printable, EXTR_SKIP);

        ob_start();
        require __DIR__ . '/../../views/printables/application_form_pdf.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 612, 936]);
        $dompdf->render();

        $output = $dompdf->output();
        $fileName = preg_replace('/[^A-Za-z0-9\\-_]+/', '_', $applicationNumber) . '.pdf';
        $download = filter_input(INPUT_GET, 'download', FILTER_VALIDATE_INT) === 1;

        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($output));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
        echo $output;
        exit;
    }

    private function loadPrintableApplication(int $applicationId): ?array
    {
        if ($applicationId <= 0) {
            return null;
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT
                a.*,
                p.*,
                u.email,
                u.phone_number
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $applicationId]);
        $application = $stmt->fetch();

        if (!$application) {
            return null;
        }

        if ((string) ($_SESSION['role'] ?? '') === 'Student' && (int) ($application['user_id'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)) {
            http_response_code(403);
            exit('You are not allowed to view this printable form.');
        }

        $documentsStmt = $db->prepare("
            SELECT document_type, file_path, status, rejection_remarks, uploaded_at
            FROM documents
            WHERE application_id = :application_id
            ORDER BY id ASC
        ");
        $documentsStmt->execute(['application_id' => $applicationId]);
        $documents = $documentsStmt->fetchAll();

        $siblings = json_decode((string) ($application['siblings_json'] ?? '[]'), true);
        if (!is_array($siblings)) {
            $siblings = [];
        }

        $education = json_decode((string) ($application['education_json'] ?? '[]'), true);
        if (!is_array($education)) {
            $education = [];
        }

        $grants = json_decode((string) ($application['grants_json'] ?? '[]'), true);
        if (!is_array($grants)) {
            $grants = [];
        }

        $applicationNumber = 'SELGU-APP-' . date('Y', strtotime((string) ($application['created_at'] ?? 'now'))) . '-' . str_pad((string) $applicationId, 5, '0', STR_PAD_LEFT);
        $fullName = trim(
            trim((string) ($application['last_name'] ?? '')) . ', ' .
            trim((string) ($application['first_name'] ?? '')) .
            (!empty($application['middle_name']) ? ' ' . strtoupper(substr((string) $application['middle_name'], 0, 1)) . '.' : '') .
            (!empty($application['suffix']) ? ' ' . trim((string) ($application['suffix'] ?? '')) : '')
        );
        $fullAddress = $this->na($application['address_line'] ?? '') . ', Brgy. ' . $this->na($application['address_barangay'] ?? '') . ', San Enrique, Negros Occidental';
        $calculatedAge = $this->calculateAge($application['date_of_birth'] ?? '');
        $photoSrc = $this->toDataUri((string) ($application['id_picture_path'] ?? ''));
        $signatureSrc = $this->toTrimmedDataUri((string) ($application['e_signature_path'] ?? ''));
        $logoSrc = $this->toDataUri(dirname(__DIR__, 2) . '/public/assets/images/lgu-logo.png');
        $gradesDocument = $this->findDocument($documents, 'Grades');
        $residencyDocument = $this->findDocument($documents, 'Residency');
        $soaDocument = $this->findDocument($documents, 'SOA');

        return compact(
            'application',
            'documents',
            'siblings',
            'education',
            'grants',
            'applicationNumber',
            'fullName',
            'fullAddress',
            'calculatedAge',
            'photoSrc',
            'signatureSrc',
            'logoSrc',
            'gradesDocument',
            'residencyDocument',
            'soaDocument'
        );
    }

    private function currentRouteBase(): string
    {
        $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $basePath = \app_base_path();
        if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
            $requestPath = substr($requestPath, strlen($basePath));
        }

        if (str_starts_with($requestPath, '/admin/')) {
            return 'admin';
        }

        if (str_starts_with($requestPath, '/staff/')) {
            return 'staff';
        }

        return 'student';
    }

    private function dashboardPath(): string
    {
        return match ((string) ($_SESSION['role'] ?? '')) {
            'Admin' => 'admin/dashboard',
            'Staff' => 'staff/dashboard',
            default => 'student/dashboard',
        };
    }

    private function redirectToDashboardWithError(string $message): never
    {
        \redirect_with_flash($this->dashboardPath(), 'error', $message);
    }

    private function toDataUri(string $path): string
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '' || !is_file($normalizedPath)) {
            return '';
        }

        $mimeType = mime_content_type($normalizedPath) ?: 'application/octet-stream';
        $binary = file_get_contents($normalizedPath);
        if ($binary === false) {
            return '';
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
    }

    private function toTrimmedDataUri(string $path): string
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '' || !is_file($normalizedPath)) {
            return '';
        }

        $binary = file_get_contents($normalizedPath);
        if ($binary === false) {
            return '';
        }

        if (!function_exists('imagecreatefromstring')) {
            return $this->toDataUri($normalizedPath);
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return $this->toDataUri($normalizedPath);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                $isVisible = $alpha < 120 && !($red > 245 && $green > 245 && $blue > 245);
                if (!$isVisible) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            imagedestroy($image);
            return $this->toDataUri($normalizedPath);
        }

        $croppedWidth = max(1, $maxX - $minX + 1);
        $croppedHeight = max(1, $maxY - $minY + 1);
        $cropped = imagecreatetruecolor($croppedWidth, $croppedHeight);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
        imagefilledrectangle($cropped, 0, 0, $croppedWidth, $croppedHeight, $transparent);
        imagecopy($cropped, $image, 0, 0, $minX, $minY, $croppedWidth, $croppedHeight);

        ob_start();
        imagepng($cropped);
        $trimmedBinary = ob_get_clean();

        imagedestroy($cropped);
        imagedestroy($image);

        if (!is_string($trimmedBinary) || $trimmedBinary === '') {
            return $this->toDataUri($normalizedPath);
        }

        return 'data:image/png;base64,' . base64_encode($trimmedBinary);
    }

    private function na($value): string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? 'N/A' : $normalized;
    }

    private function formatDate(?string $value, string $fallback = 'N/A'): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return $fallback;
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('F d, Y');
        } catch (\Throwable $exception) {
            return $normalized;
        }
    }

    private function calculateAge(?string $dateOfBirth): string
    {
        $normalized = trim((string) $dateOfBirth);
        if ($normalized === '') {
            return '';
        }

        try {
            $birthDate = new \DateTimeImmutable($normalized);
            $today = new \DateTimeImmutable('today');
            return (string) $birthDate->diff($today)->y;
        } catch (\Throwable $exception) {
            return '';
        }
    }

    private function formatMoney($value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        return 'PHP ' . number_format((float) $value, 2);
    }

    private function findDocument(array $documents, string $type): ?array
    {
        foreach ($documents as $document) {
            if (($document['document_type'] ?? '') === $type) {
                return $document;
            }
        }

        return null;
    }

    private function documentStatusLabel(?array $document): string
    {
        if (!$document) {
            return 'Not Submitted';
        }

        $status = trim((string) ($document['status'] ?? ''));
        return $status === '' ? 'Submitted' : str_replace('_', ' ', $status);
    }
}
