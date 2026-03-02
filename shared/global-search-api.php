<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

header('Content-Type: application/json; charset=utf-8');

if (!db_ready()) {
    echo json_encode([
        'ok' => false,
        'error' => 'The system is not ready yet.',
        'sections' => [],
        'total' => 0,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $query = mb_substr($query, 0, 120);
} else {
    $query = substr($query, 0, 120);
}

if ($query === '' || strlen($query) < 2) {
    echo json_encode([
        'ok' => true,
        'query' => $query,
        'sections' => [],
        'total' => 0,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$like = '%' . $query . '%';
$isAdmin = is_admin();
$sections = [];
$totalCount = 0;
$perSection = 8;
$hasDisbursementTime = table_column_exists($conn, 'disbursements', 'disbursement_time');

$pushSection = static function (string $key, string $label, array $items) use (&$sections, &$totalCount): void {
    if (!$items) {
        return;
    }
    $sections[] = [
        'key' => $key,
        'label' => $label,
        'count' => count($items),
        'items' => $items,
    ];
    $totalCount += count($items);
};

$searchApplications = static function (mysqli $conn, string $like, int $limit): array {
    $items = [];
    $stmt = $conn->prepare(
        "SELECT a.id, a.application_no, a.status, a.scholarship_type, a.school_name, a.school_year,
                u.first_name, u.last_name
         FROM applications a
         INNER JOIN users u ON u.id = a.user_id
         WHERE a.application_no LIKE ?
            OR u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?
            OR a.school_name LIKE ?
            OR a.scholarship_type LIKE ?
            OR u.phone LIKE ?
            OR u.email LIKE ?
         ORDER BY a.updated_at DESC, a.id DESC
         LIMIT ?"
    );
    if (!$stmt) {
        return $items;
    }

    $stmt->bind_param('ssssssssi', $like, $like, $like, $like, $like, $like, $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as $row) {
        $fullName = trim((string) ($row['last_name'] ?? '') . ', ' . (string) ($row['first_name'] ?? ''), ', ');
        $items[] = [
            'title' => (string) ($row['application_no'] ?? 'Application'),
            'subtitle' => $fullName !== '' ? $fullName : '-',
            'meta' => trim(implode(' | ', array_filter([
                (string) ($row['scholarship_type'] ?? ''),
                strtoupper((string) ($row['status'] ?? '')),
                (string) ($row['school_name'] ?? ''),
                (string) ($row['school_year'] ?? ''),
            ]))),
            'url' => '../print-application.php?id=' . (int) ($row['id'] ?? 0),
        ];
    }

    return $items;
};

$searchDisbursements = static function (mysqli $conn, string $like, int $limit) use ($hasDisbursementTime): array {
    $items = [];
    $timeSelectSql = $hasDisbursementTime ? ', d.disbursement_time' : ', NULL AS disbursement_time';
    $timeOrderSql = $hasDisbursementTime ? ", COALESCE(d.disbursement_time, '00:00:00') DESC" : '';
    $stmt = $conn->prepare(
        "SELECT d.id, d.reference_no, d.amount, d.disbursement_date{$timeSelectSql}, d.status,
                a.application_no, u.first_name, u.last_name
         FROM disbursements d
         INNER JOIN applications a ON a.id = d.application_id
         INNER JOIN users u ON u.id = a.user_id
         WHERE d.reference_no LIKE ?
            OR a.application_no LIKE ?
            OR u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?
         ORDER BY d.disbursement_date DESC{$timeOrderSql}, d.id DESC
         LIMIT ?"
    );
    if (!$stmt) {
        return $items;
    }

    $stmt->bind_param('sssssi', $like, $like, $like, $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as $row) {
        $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        $meta = 'PHP ' . number_format((float) ($row['amount'] ?? 0), 2);
        if (!empty($row['disbursement_date'])) {
            $scheduleLabel = date('M d, Y', strtotime((string) $row['disbursement_date']));
            $timeValue = trim((string) ($row['disbursement_time'] ?? ''));
            if ($hasDisbursementTime && $timeValue !== '' && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeValue) === 1) {
                $timeTs = strtotime($timeValue);
                if ($timeTs !== false) {
                    $scheduleLabel .= ' ' . date('h:i A', $timeTs);
                }
            }
            $meta .= ' | ' . $scheduleLabel;
        }
        $meta .= ' | ' . strtoupper((string) ($row['status'] ?? ''));
        $meta .= ' | Ref: ' . (string) ($row['reference_no'] ?? '-');

        $items[] = [
            'title' => (string) ($row['application_no'] ?? 'Disbursement'),
            'subtitle' => $fullName !== '' ? $fullName : '-',
            'meta' => $meta,
            'url' => 'disbursements.php',
        ];
    }

    return $items;
};

$searchQrScans = static function (mysqli $conn, string $like, int $limit): array {
    $items = [];
    if (!table_exists($conn, 'qr_scan_logs')) {
        return $items;
    }

    $stmt = $conn->prepare(
        "SELECT l.id, l.purpose, l.scan_status, l.scanned_qr_token, l.scanned_application_no, l.notes, l.created_at,
                a.application_no, a.first_name, a.last_name
         FROM qr_scan_logs l
         LEFT JOIN applications a ON a.id = l.application_id
         WHERE l.scanned_qr_token LIKE ?
            OR l.scanned_application_no LIKE ?
            OR l.notes LIKE ?
            OR l.purpose LIKE ?
            OR a.application_no LIKE ?
            OR a.first_name LIKE ?
            OR a.last_name LIKE ?
         ORDER BY l.created_at DESC, l.id DESC
         LIMIT ?"
    );
    if (!$stmt) {
        return $items;
    }

    $stmt->bind_param('sssssssi', $like, $like, $like, $like, $like, $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as $row) {
        $applicationNo = trim((string) ($row['application_no'] ?? ''));
        if ($applicationNo === '') {
            $applicationNo = trim((string) ($row['scanned_application_no'] ?? 'Unknown Application'));
        }
        $applicantName = trim((string) ($row['last_name'] ?? '') . ', ' . (string) ($row['first_name'] ?? ''), ', ');
        $meta = qr_scan_purpose_label((string) ($row['purpose'] ?? 'general_verification'))
            . ' | '
            . strtoupper((string) ($row['scan_status'] ?? 'invalid'));
        if (!empty($row['created_at'])) {
            $meta .= ' | ' . date('M d, Y h:i A', strtotime((string) $row['created_at']));
        }
        if (!empty($row['notes'])) {
            $meta .= ' | ' . excerpt((string) $row['notes'], 70);
        }

        $items[] = [
            'title' => $applicationNo !== '' ? $applicationNo : 'QR Scan',
            'subtitle' => $applicantName !== '' ? $applicantName : '-',
            'meta' => $meta,
            'url' => 'verify-qr.php',
        ];
    }

    return $items;
};

$searchAnnouncements = static function (mysqli $conn, string $like, int $limit, bool $isAdmin): array {
    $items = [];
    $whereActive = $isAdmin ? '' : ' AND a.is_active = 1 ';
    $sql = "SELECT a.id, a.title, a.content, a.is_active, a.created_at
            FROM announcements a
            WHERE (a.title LIKE ? OR a.content LIKE ?)
            {$whereActive}
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $items;
    }

    $stmt->bind_param('ssi', $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as $row) {
        $meta = ((int) ($row['is_active'] ?? 0) === 1 ? 'ACTIVE' : 'INACTIVE');
        if (!empty($row['created_at'])) {
            $meta .= ' | ' . date('M d, Y', strtotime((string) $row['created_at']));
        }
        $items[] = [
            'title' => (string) ($row['title'] ?? 'Announcement'),
            'subtitle' => excerpt((string) ($row['content'] ?? ''), 90),
            'meta' => $meta,
            'url' => $isAdmin ? '../admin-only/announcements.php' : '../announcements.php',
        ];
    }

    return $items;
};

$searchRequirements = static function (mysqli $conn, string $like, int $limit): array {
    $items = [];
    if (!table_exists($conn, 'requirement_templates')) {
        return $items;
    }

    $stmt = $conn->prepare(
        "SELECT id, requirement_name, description, is_active
         FROM requirement_templates
         WHERE requirement_name LIKE ? OR description LIKE ?
         ORDER BY sort_order ASC, id ASC
         LIMIT ?"
    );
    if (!$stmt) {
        return $items;
    }

    $stmt->bind_param('ssi', $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as $row) {
        $items[] = [
            'title' => (string) ($row['requirement_name'] ?? 'Requirement'),
            'subtitle' => excerpt((string) ($row['description'] ?? ''), 80),
            'meta' => ((int) ($row['is_active'] ?? 0) === 1 ? 'ACTIVE' : 'INACTIVE'),
            'url' => '../admin-only/requirements.php',
        ];
    }

    return $items;
};

$searchPeriods = static function (mysqli $conn, string $like, int $limit): array {
    $items = [];
    if (!table_exists($conn, 'application_periods')) {
        return $items;
    }

    $stmt = $conn->prepare(
        "SELECT id, period_name, start_date, end_date, is_open, notes
         FROM application_periods
         WHERE period_name LIKE ? OR notes LIKE ?
         ORDER BY id DESC
         LIMIT ?"
    );
    if (!$stmt) {
        return $items;
    }

    $stmt->bind_param('ssi', $like, $like, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($rows as $row) {
        $range = '-';
        if (!empty($row['start_date']) && !empty($row['end_date'])) {
            $range = date('M d, Y', strtotime((string) $row['start_date'])) . ' - ' . date('M d, Y', strtotime((string) $row['end_date']));
        } elseif (!empty($row['start_date'])) {
            $range = 'From ' . date('M d, Y', strtotime((string) $row['start_date']));
        } elseif (!empty($row['end_date'])) {
            $range = 'Until ' . date('M d, Y', strtotime((string) $row['end_date']));
        }

        $items[] = [
            'title' => (string) ($row['period_name'] ?? 'Application Period'),
            'subtitle' => $range,
            'meta' => ((int) ($row['is_open'] ?? 0) === 1 ? 'OPEN' : 'CLOSED'),
            'url' => '../admin-only/application-periods.php',
        ];
    }

    return $items;
};

$pushSection('applications', 'Applications', $searchApplications($conn, $like, $perSection));
$pushSection('disbursements', 'Disbursements', $searchDisbursements($conn, $like, $perSection));
$pushSection('qr_scans', 'QR Scans', $searchQrScans($conn, $like, $perSection));
$pushSection('announcements', 'Announcements', $searchAnnouncements($conn, $like, $perSection, $isAdmin));

if ($isAdmin) {
    $pushSection('requirements', 'Requirements', $searchRequirements($conn, $like, $perSection));
    $pushSection('application_periods', 'Application Periods', $searchPeriods($conn, $like, $perSection));
}

echo json_encode([
    'ok' => true,
    'query' => $query,
    'sections' => $sections,
    'total' => $totalCount,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
