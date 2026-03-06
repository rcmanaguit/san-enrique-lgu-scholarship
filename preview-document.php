<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login('login.php');

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

$rawFile = trim((string) ($_GET['file'] ?? ''));
if ($rawFile === '') {
    http_response_code(400);
    echo 'Missing file.';
    exit;
}

$relativeFile = ltrim(str_replace('\\', '/', $rawFile), '/');
$baseUploads = realpath(__DIR__ . '/uploads');
$absoluteFile = realpath(__DIR__ . '/' . $relativeFile);

if ($baseUploads === false || $absoluteFile === false || !is_file($absoluteFile)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$normalizedBase = str_replace('\\', '/', $baseUploads);
$normalizedFile = str_replace('\\', '/', $absoluteFile);
$allowedDocuments = str_replace('\\', '/', realpath(__DIR__ . '/uploads/documents') ?: '');
$allowedTmp = str_replace('\\', '/', realpath(__DIR__ . '/uploads/tmp') ?: '');

$isAllowed = str_starts_with($normalizedFile, $normalizedBase . '/')
    && (
        ($allowedDocuments !== '' && str_starts_with($normalizedFile, $allowedDocuments . '/'))
        || ($allowedTmp !== '' && str_starts_with($normalizedFile, $allowedTmp . '/'))
    );
if (!$isAllowed) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$relativeFile = ltrim(str_replace('\\', '/', $relativeFile), '/');
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$canAccessFile = false;

$normalizePath = static function (string $path): string {
    return ltrim(str_replace('\\', '/', trim($path)), '/');
};

$matchesPath = static function (string $candidate, string $target) use ($normalizePath): bool {
    $normalizedCandidate = $normalizePath($candidate);
    $normalizedTarget = $normalizePath($target);
    return $normalizedCandidate !== '' && $normalizedCandidate === $normalizedTarget;
};

$applicantOwnsPersistedFile = static function (mysqli $conn, int $ownerId, string $targetFile) use ($matchesPath): bool {
    if ($ownerId <= 0 || !db_ready()) {
        return false;
    }

    if (!table_exists($conn, 'applications') || !table_exists($conn, 'application_documents')) {
        return false;
    }

    $candidateA = $targetFile;
    $candidateB = '/' . $targetFile;

    $stmtDoc = $conn->prepare(
        "SELECT d.file_path
         FROM application_documents d
         INNER JOIN applications a ON a.id = d.application_id
         WHERE a.user_id = ?
           AND (d.file_path = ? OR d.file_path = ?)
         LIMIT 1"
    );
    if ($stmtDoc) {
        $stmtDoc->bind_param('iss', $ownerId, $candidateA, $candidateB);
        $stmtDoc->execute();
        $resultDoc = $stmtDoc->get_result();
        $rowDoc = $resultDoc instanceof mysqli_result ? ($resultDoc->fetch_assoc() ?: null) : null;
        $stmtDoc->close();
        if (is_array($rowDoc) && $matchesPath((string) ($rowDoc['file_path'] ?? ''), $targetFile)) {
            return true;
        }
    }

    $stmtPhoto = $conn->prepare(
        "SELECT photo_path
         FROM applications
         WHERE user_id = ?
           AND (photo_path = ? OR photo_path = ?)
         LIMIT 1"
    );
    if ($stmtPhoto) {
        $stmtPhoto->bind_param('iss', $ownerId, $candidateA, $candidateB);
        $stmtPhoto->execute();
        $resultPhoto = $stmtPhoto->get_result();
        $rowPhoto = $resultPhoto instanceof mysqli_result ? ($resultPhoto->fetch_assoc() ?: null) : null;
        $stmtPhoto->close();
        if (is_array($rowPhoto) && $matchesPath((string) ($rowPhoto['photo_path'] ?? ''), $targetFile)) {
            return true;
        }
    }

    return false;
};

$applicantOwnsWizardFile = static function (int $ownerId, string $targetFile, ?mysqli $conn) use ($matchesPath): bool {
    if ($ownerId <= 0) {
        return false;
    }

    $state = wizard_state();
    $candidatePaths = [];

    $photoPath = trim((string) ($state['photo_path'] ?? ''));
    if ($photoPath !== '') {
        $candidatePaths[] = $photoPath;
    }

    $documents = $state['documents'] ?? [];
    if (is_array($documents)) {
        foreach ($documents as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $path = trim((string) ($doc['file_path'] ?? ''));
            if ($path !== '') {
                $candidatePaths[] = $path;
            }
        }
    }

    if ($conn instanceof mysqli && db_ready()) {
        $persistedDraft = wizard_load_persistent_draft($conn, $ownerId);
        if (is_array($persistedDraft) && is_array($persistedDraft['state'] ?? null)) {
            $persistedState = (array) $persistedDraft['state'];
            $persistedPhotoPath = trim((string) ($persistedState['photo_path'] ?? ''));
            if ($persistedPhotoPath !== '') {
                $candidatePaths[] = $persistedPhotoPath;
            }
            $persistedDocs = $persistedState['documents'] ?? [];
            if (is_array($persistedDocs)) {
                foreach ($persistedDocs as $doc) {
                    if (!is_array($doc)) {
                        continue;
                    }
                    $path = trim((string) ($doc['file_path'] ?? ''));
                    if ($path !== '') {
                        $candidatePaths[] = $path;
                    }
                }
            }
        }
    }

    foreach ($candidatePaths as $candidatePath) {
        if ($matchesPath((string) $candidatePath, $targetFile)) {
            return true;
        }
    }

    return false;
};

if (is_admin() || is_staff()) {
    $canAccessFile = true;
} elseif (user_has_role(['applicant']) && $userId > 0) {
    $canAccessFile = $conn instanceof mysqli
        ? $applicantOwnsPersistedFile($conn, $userId, $relativeFile)
        : false;
    if (!$canAccessFile && str_starts_with($relativeFile, 'uploads/tmp/')) {
        $canAccessFile = $applicantOwnsWizardFile($userId, $relativeFile, $conn instanceof mysqli ? $conn : null);
    }
}

if (!$canAccessFile) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$fileExt = strtolower(pathinfo($absoluteFile, PATHINFO_EXTENSION));
$isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
$isPdf = $fileExt === 'pdf';
$safeSrc = e($relativeFile);
$safeName = e((string) basename($absoluteFile));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Preview</title>
    <style>
        html, body {
            margin: 0;
            width: 100%;
            height: 100%;
            background: #0b1520;
            color: #e7eef5;
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        .viewer {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #0b1520;
        }
        .viewer img {
            max-width: 100vw;
            max-height: 100vh;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .viewer iframe {
            width: 100vw;
            height: 100vh;
            border: 0;
            background: #111;
        }
        .fallback {
            text-align: center;
            padding: 1rem;
        }
        .fallback a {
            color: #9fd7ff;
            text-decoration: underline;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="viewer">
        <?php if ($isImage): ?>
            <img src="<?= $safeSrc ?>" alt="<?= $safeName ?>">
        <?php elseif ($isPdf): ?>
            <iframe src="<?= $safeSrc ?>" title="<?= $safeName ?>"></iframe>
        <?php else: ?>
            <div class="fallback">
                <p>Preview is not available for this file type.</p>
                <p><a href="<?= $safeSrc ?>" target="_blank" rel="noopener">Open file: <?= $safeName ?></a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
