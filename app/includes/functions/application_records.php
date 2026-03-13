<?php
declare(strict_types=1);

function array_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function json_array(?string $json): array
{
    if (!$json) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function table_exists(mysqli $conn, string $tableName): bool
{
    static $cache = [];

    $tableName = trim($tableName);
    if ($tableName === '') {
        return false;
    }

    $cacheKey = strtolower($tableName);
    if (array_key_exists($cacheKey, $cache)) {
        return (bool) $cache[$cacheKey];
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        $cache[$cacheKey] = false;
        return false;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    $exists = (int) ($row['total'] ?? 0) > 0;
    $cache[$cacheKey] = $exists;
    return $exists;
}

function generate_application_no(mysqli $conn): string
{
    $year = date('Y');
    $prefix = 'SE-' . $year . '-';
    $sequenceTable = 'application_no_sequences';
    if (!table_exists($conn, $sequenceTable)) {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS application_no_sequences (
                sequence_year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
                last_number INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (table_exists($conn, $sequenceTable)) {
        $yearInt = (int) $year;
        $stmtSeq = $conn->prepare(
            "INSERT INTO application_no_sequences (sequence_year, last_number)
             VALUES (?, 1)
             ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)"
        );
        if ($stmtSeq) {
            $stmtSeq->bind_param('i', $yearInt);
            $stmtSeq->execute();
            $stmtSeq->close();

            $nextNumber = (int) ($conn->insert_id ?? 0);
            if ($nextNumber <= 0) {
                $stmtCurrent = $conn->prepare(
                    "SELECT last_number
                     FROM application_no_sequences
                     WHERE sequence_year = ?
                     LIMIT 1"
                );
                if ($stmtCurrent) {
                    $stmtCurrent->bind_param('i', $yearInt);
                    $stmtCurrent->execute();
                    $row = $stmtCurrent->get_result()->fetch_assoc();
                    $stmtCurrent->close();
                    $nextNumber = (int) ($row['last_number'] ?? 0);
                }
            }

            if ($nextNumber > 0) {
                return $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM applications WHERE application_no LIKE CONCAT(?, '%')");
    if (!$stmt) {
        return $prefix . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $prefix . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
}

function active_requirements(mysqli $conn, ?string $applicantType, ?string $schoolType): array
{
    $applicantType = $applicantType ?: '';
    $schoolType = $schoolType ?: '';

    $periodRequirementRows = current_period_requirements($conn, $applicantType, $schoolType);
    if ($periodRequirementRows) {
        return $periodRequirementRows;
    }

    $sql = "SELECT id, requirement_name, description, applicant_type, school_type, is_required, sort_order
            FROM requirement_templates
            WHERE is_active = 1
              AND (? = '' OR applicant_type IS NULL OR applicant_type = ?)
              AND (? = '' OR school_type IS NULL OR school_type = ?)
            ORDER BY sort_order ASC, id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $applicantType, $applicantType, $schoolType, $schoolType);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function ensure_application_period_requirements_table(mysqli $conn): bool
{
    static $ensured = false;

    if ($ensured) {
        return table_exists($conn, 'application_period_requirements');
    }

    $ensured = true;
    if (table_exists($conn, 'application_period_requirements')) {
        return true;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS application_period_requirements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_period_id INT UNSIGNED NOT NULL,
            requirement_template_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_application_period_requirement (application_period_id, requirement_template_id),
            CONSTRAINT fk_application_period_requirements_period
                FOREIGN KEY (application_period_id) REFERENCES application_periods(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_application_period_requirements_template
                FOREIGN KEY (requirement_template_id) REFERENCES requirement_templates(id)
                ON DELETE CASCADE
        )"
    );

    return table_exists($conn, 'application_period_requirements');
}

function save_application_period_requirements(mysqli $conn, int $periodId, array $requirementTemplateIds): void
{
    if ($periodId <= 0 || !ensure_application_period_requirements_table($conn)) {
        return;
    }

    $cleanIds = array_values(array_unique(array_filter(array_map(
        static fn($value): int => (int) $value,
        $requirementTemplateIds
    ), static fn(int $value): bool => $value > 0)));

    $conn->query("DELETE FROM application_period_requirements WHERE application_period_id = " . $periodId);
    if (!$cleanIds) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO application_period_requirements (application_period_id, requirement_template_id)
         VALUES (?, ?)"
    );

    foreach ($cleanIds as $templateId) {
        $stmt->bind_param('ii', $periodId, $templateId);
        $stmt->execute();
    }

    $stmt->close();
}

function current_period_requirements(mysqli $conn, ?string $applicantType, ?string $schoolType): array
{
    if (!ensure_application_period_requirements_table($conn)) {
        return [];
    }

    $openPeriod = current_open_application_period($conn);
    $periodId = (int) ($openPeriod['id'] ?? 0);
    if ($periodId <= 0) {
        return [];
    }

    $applicantType = $applicantType ?: '';
    $schoolType = $schoolType ?: '';

    $sql = "SELECT rt.id, rt.requirement_name, rt.description, rt.applicant_type, rt.school_type, rt.is_required, rt.sort_order
            FROM application_period_requirements apr
            INNER JOIN requirement_templates rt ON rt.id = apr.requirement_template_id
            WHERE apr.application_period_id = ?
              AND rt.is_active = 1
              AND (? = '' OR rt.applicant_type IS NULL OR rt.applicant_type = ?)
              AND (? = '' OR rt.school_type IS NULL OR rt.school_type = ?)
            ORDER BY rt.sort_order ASC, rt.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issss', $periodId, $applicantType, $applicantType, $schoolType, $schoolType);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function application_period_timeline_for_user(mysqli $conn, int $userId): array
{
    if ($userId <= 0 || !table_exists($conn, 'application_periods') || !table_exists($conn, 'applications')) {
        return [];
    }

    $periodRows = [];
    $result = $conn->query(
        "SELECT id, academic_year, semester, period_name, start_date, end_date
         FROM application_periods
         ORDER BY
            CASE WHEN start_date IS NULL THEN 1 ELSE 0 END ASC,
            start_date ASC,
            id ASC"
    );
    if ($result instanceof mysqli_result) {
        $periodRows = $result->fetch_all(MYSQLI_ASSOC);
    }
    if ($periodRows === []) {
        return [];
    }

    $applicationRows = [];
    $stmt = $conn->prepare(
        "SELECT id, application_no, application_period_id, semester, school_year, status, submitted_at, created_at
         FROM applications
         WHERE user_id = ?
         ORDER BY COALESCE(submitted_at, created_at) ASC, id ASC"
    );
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $applicationRows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $applicationsByPeriodId = [];
    $applicationsByKey = [];
    foreach ($applicationRows as $applicationRow) {
        $periodId = (int) ($applicationRow['application_period_id'] ?? 0);
        if ($periodId > 0) {
            $applicationsByPeriodId[$periodId] = $applicationRow;
        }

        $semester = trim((string) ($applicationRow['semester'] ?? ''));
        $schoolYear = trim((string) ($applicationRow['school_year'] ?? ''));
        if ($semester !== '' && $schoolYear !== '') {
            $applicationsByKey[$semester . '|' . $schoolYear] = $applicationRow;
        }
    }

    $timeline = [];
    foreach ($periodRows as $periodRow) {
        $periodId = (int) ($periodRow['id'] ?? 0);
        $semester = trim((string) ($periodRow['semester'] ?? ''));
        $academicYear = trim((string) ($periodRow['academic_year'] ?? ''));
        $match = $applicationsByPeriodId[$periodId] ?? null;
        if (!$match && $semester !== '' && $academicYear !== '') {
            $match = $applicationsByKey[$semester . '|' . $academicYear] ?? null;
        }

        $hasApplication = is_array($match);
        $status = trim((string) ($match['status'] ?? ''));
        $isReleased = $status === 'released';

        $timeline[] = [
            'period_label' => format_application_period($periodRow),
            'application_id' => (int) ($match['id'] ?? 0),
            'application_no' => trim((string) ($match['application_no'] ?? '')),
            'status' => $status,
            'label' => $hasApplication ? 'Applied / Released' : 'No application',
            'badge_class' => $hasApplication
                ? ($isReleased ? 'text-bg-success' : 'text-bg-primary')
                : 'text-bg-light',
            'has_application' => $hasApplication,
            'is_released' => $isReleased,
        ];
    }

    return $timeline;
}

function move_temp_file_to_final(string $relativePath, string $targetDir, string $newPrefix): ?string
{
    $relativePath = trim($relativePath);
    if ($relativePath === '') {
        return null;
    }

    $source = __DIR__ . '/../../' . ltrim($relativePath, '/');
    if (!file_exists($source)) {
        return null;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $newName = $newPrefix . '_' . uniqid('', true) . '.' . $ext;
    $destination = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
    rename($source, $destination);

    $baseProject = str_replace('\\', '/', realpath(__DIR__ . '/../../') ?: '');
    $normalized = str_replace('\\', '/', $destination);
    if ($baseProject !== '' && str_starts_with($normalized, $baseProject . '/')) {
        return substr($normalized, strlen($baseProject) + 1);
    }
    return str_replace('\\', '/', $destination);
}

