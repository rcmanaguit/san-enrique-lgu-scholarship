<?php
declare(strict_types=1);

function upload_document(string $fieldName, string $targetDir): ?string
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed upload for field: ' . $fieldName);
    }

    $maxUploadMb = (int) (getenv('UPLOAD_MAX_FILE_MB') ?: 25);
    if ($maxUploadMb < 5) {
        $maxUploadMb = 5;
    }
    $maxSize = $maxUploadMb * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('File too large (max ' . $maxUploadMb . 'MB): ' . $fieldName);
    }

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Invalid file type for: ' . $fieldName);
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $newName = uniqid($fieldName . '_', true) . '.' . $ext;
    $fullPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
    if (!move_uploaded_file((string) $file['tmp_name'], $fullPath)) {
        throw new RuntimeException('Could not move uploaded file: ' . $fieldName);
    }

    return str_replace('\\', '/', $fullPath);
}

function upload_any_file(string $fieldName, string $targetDir, array $allowedExt = ['pdf', 'jpg', 'jpeg', 'png']): ?array
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed upload for field: ' . $fieldName);
    }

    $maxUploadMb = (int) (getenv('UPLOAD_MAX_FILE_MB') ?: 25);
    if ($maxUploadMb < 8) {
        $maxUploadMb = 8;
    }
    $maxSize = $maxUploadMb * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('File too large (max ' . $maxUploadMb . 'MB): ' . $fieldName);
    }

    $originalName = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Invalid file type for: ' . $fieldName);
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $newName = uniqid('tmp_', true) . '.' . $ext;
    $fullPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
    if (!move_uploaded_file((string) $file['tmp_name'], $fullPath)) {
        throw new RuntimeException('Could not store uploaded file: ' . $fieldName);
    }

    $mimeType = (string) ($file['type'] ?? '');
    return [
        'file_path' => str_replace('\\', '/', $fullPath),
        'original_name' => $originalName,
        'ext' => $ext,
        'mime' => $mimeType,
    ];
}

function save_base64_image(string $base64Image, string $targetDir): string
{
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
        throw new RuntimeException('Invalid image data format.');
    }

    $ext = strtolower($matches[1]);
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Unsupported image format.');
    }

    $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
    $decoded = base64_decode($imageData, true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid base64 image data.');
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = uniqid('photo_', true) . '.jpg';
    $fullPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
    file_put_contents($fullPath, $decoded);

    return str_replace('\\', '/', $fullPath);
}

function wizard_default_state(): array
{
    return [
        'step1' => [],
        'step2' => [],
        'step3' => [],
        'documents' => [],
        'step1_done' => false,
        'step2_done' => false,
        'step3_done' => false,
        'step4_done' => false,
        'photo_path' => null,
    ];
}

function wizard_step1_is_complete(array $state): bool
{
    $step1 = is_array($state['step1'] ?? null) ? $state['step1'] : [];
    $required = ['applicant_type', 'semester', 'school_year', 'school_name', 'school_type', 'course'];
    foreach ($required as $field) {
        if (trim((string) ($step1[$field] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

function wizard_step2_is_complete(array $state): bool
{
    $step2 = is_array($state['step2'] ?? null) ? $state['step2'] : [];
    if (
        trim((string) ($step2['last_name'] ?? '')) === ''
        || trim((string) ($step2['first_name'] ?? '')) === ''
        || trim((string) ($step2['email'] ?? '')) === ''
        || trim((string) ($step2['contact_number'] ?? '')) === ''
        || trim((string) ($step2['barangay'] ?? '')) === ''
    ) {
        return false;
    }

    return filter_var((string) ($step2['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false
        && is_valid_mobile_number((string) ($step2['contact_number'] ?? ''))
        && is_valid_barangay((string) ($step2['barangay'] ?? ''));
}

function wizard_has_progress(array $state): bool
{
    $state = array_merge(wizard_default_state(), $state);
    return !empty($state['step1'])
        || !empty($state['step2'])
        || !empty($state['step3'])
        || !empty($state['documents'])
        || !empty($state['photo_path'])
        || (bool) ($state['step1_done'] ?? false)
        || (bool) ($state['step2_done'] ?? false)
        || (bool) ($state['step3_done'] ?? false)
        || (bool) ($state['step4_done'] ?? false);
}

function wizard_resume_step(array $state): int
{
    $state = array_merge(wizard_default_state(), $state);

    if (!(bool) ($state['step1_done'] ?? false)) {
        return 1;
    }
    if (!(bool) ($state['step2_done'] ?? false)) {
        return 2;
    }
    if (!(bool) ($state['step3_done'] ?? false)) {
        return 3;
    }
    if (!(bool) ($state['step4_done'] ?? false)) {
        return 4;
    }
    if (trim((string) ($state['photo_path'] ?? '')) === '') {
        return 5;
    }
    return 6;
}

function wizard_state(): array
{
    if (!isset($_SESSION['application_wizard']) || !is_array($_SESSION['application_wizard'])) {
        $_SESSION['application_wizard'] = wizard_default_state();
    }

    $state = array_merge(wizard_default_state(), $_SESSION['application_wizard']);

    // Backward-compatible upgrade for drafts saved before step done flags existed.
    if (!(bool) ($state['step1_done'] ?? false) && wizard_step1_is_complete($state)) {
        $state['step1_done'] = true;
    }
    if (!(bool) ($state['step2_done'] ?? false) && wizard_step2_is_complete($state)) {
        $state['step2_done'] = true;
    }
    if (!(bool) ($state['step3_done'] ?? false) && !empty($state['step3'])) {
        $state['step3_done'] = true;
    }

    $_SESSION['application_wizard'] = $state;
    return $_SESSION['application_wizard'];
}

function wizard_save(array $state): void
{
    $_SESSION['application_wizard'] = array_merge(wizard_default_state(), $state);
}

function wizard_load_persistent_draft(mysqli $conn, int $userId): ?array
{
    if ($userId <= 0 || !table_exists($conn, 'application_wizard_drafts')) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT wizard_json, current_step
         FROM application_wizard_drafts
         WHERE user_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $decoded = json_decode((string) ($row['wizard_json'] ?? ''), true);
    if (!is_array($decoded)) {
        return null;
    }

    $state = array_merge(wizard_default_state(), $decoded);
    if (!(bool) ($state['step1_done'] ?? false) && wizard_step1_is_complete($state)) {
        $state['step1_done'] = true;
    }
    if (!(bool) ($state['step2_done'] ?? false) && wizard_step2_is_complete($state)) {
        $state['step2_done'] = true;
    }
    if (!(bool) ($state['step3_done'] ?? false) && !empty($state['step3'])) {
        $state['step3_done'] = true;
    }

    $currentStep = (int) ($row['current_step'] ?? 0);
    $currentStep = max(1, min(6, $currentStep));

    return [
        'state' => $state,
        'current_step' => $currentStep,
    ];
}

function wizard_save_persistent_draft(mysqli $conn, int $userId, array $state, ?int $currentStep = null): void
{
    if ($userId <= 0 || !table_exists($conn, 'application_wizard_drafts')) {
        return;
    }

    $normalized = array_merge(wizard_default_state(), $state);
    if (!(bool) ($normalized['step1_done'] ?? false) && wizard_step1_is_complete($normalized)) {
        $normalized['step1_done'] = true;
    }
    if (!(bool) ($normalized['step2_done'] ?? false) && wizard_step2_is_complete($normalized)) {
        $normalized['step2_done'] = true;
    }

    if (!wizard_has_progress($normalized)) {
        $stmtDelete = $conn->prepare("DELETE FROM application_wizard_drafts WHERE user_id = ? LIMIT 1");
        if ($stmtDelete) {
            $stmtDelete->bind_param('i', $userId);
            $stmtDelete->execute();
            $stmtDelete->close();
        }
        return;
    }

    $stepToStore = $currentStep ?? wizard_resume_step($normalized);
    $stepToStore = max(1, min(6, $stepToStore));
    $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO application_wizard_drafts (user_id, wizard_json, current_step)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            wizard_json = VALUES(wizard_json),
            current_step = VALUES(current_step),
            updated_at = CURRENT_TIMESTAMP"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isi', $userId, $payload, $stepToStore);
    $stmt->execute();
    $stmt->close();
}

function wizard_clear_persistent_draft(mysqli $conn, int $userId): void
{
    if ($userId <= 0 || !table_exists($conn, 'application_wizard_drafts')) {
        return;
    }
    $stmt = $conn->prepare("DELETE FROM application_wizard_drafts WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function wizard_clear(): void
{
    $state = wizard_state();
    foreach ($state['documents'] as $doc) {
        $path = (string) ($doc['file_path'] ?? '');
        if ($path !== '' && str_contains($path, 'uploads/tmp/') && file_exists(__DIR__ . '/../../' . $path)) {
            @unlink(__DIR__ . '/../../' . $path);
        }
    }

    $photoPath = (string) ($state['photo_path'] ?? '');
    if ($photoPath !== '' && str_contains($photoPath, 'uploads/tmp/') && file_exists(__DIR__ . '/../../' . $photoPath)) {
        @unlink(__DIR__ . '/../../' . $photoPath);
    }

    unset($_SESSION['application_wizard']);
}

