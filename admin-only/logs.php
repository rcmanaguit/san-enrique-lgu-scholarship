<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_admin('../index.php');

$pageTitle = 'System Logs';

$defaultFromDate = date('Y-m-d', strtotime('-30 days'));
$defaultToDate = date('Y-m-d');
$fromDate = trim((string) ($_GET['from_date'] ?? $defaultFromDate));
$toDate = trim((string) ($_GET['to_date'] ?? $defaultToDate));
$logType = trim((string) ($_GET['log_type'] ?? 'all'));
$auditAction = trim((string) ($_GET['audit_action'] ?? ''));
$auditEntity = trim((string) ($_GET['audit_entity'] ?? ''));
$smsStatus = trim((string) ($_GET['sms_status'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 120);

$isValidDate = static function (string $value): bool {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
};
if (!$isValidDate($fromDate)) {
    $fromDate = $defaultFromDate;
}
if (!$isValidDate($toDate)) {
    $toDate = $defaultToDate;
}
if ($fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$allowedLogTypes = ['all', 'audit', 'sms'];
if (!in_array($logType, $allowedLogTypes, true)) {
    $logType = 'all';
}

$allowedSmsStatuses = ['', 'queued', 'success', 'failed'];
if (!in_array($smsStatus, $allowedSmsStatuses, true)) {
    $smsStatus = '';
}

if ($limit < 30) {
    $limit = 30;
}
if ($limit > 500) {
    $limit = 500;
}

$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime = $toDate . ' 23:59:59';

$hasAuditLogs = db_ready() && table_exists($conn, 'audit_logs');
$hasSmsLogs = db_ready() && table_exists($conn, 'sms_logs');

$summary = [
    'audit' => 0,
    'sms' => 0,
];

$auditLogs = [];
$smsLogs = [];
$auditActionOptions = [];
$auditEntityOptions = [];

if (db_ready()) {
    $esc = static fn(string $value): string => "'" . $conn->real_escape_string($value) . "'";

    if ($hasAuditLogs) {
        $stmtCount = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM audit_logs
             WHERE created_at BETWEEN ? AND ?"
        );
        if ($stmtCount) {
            $stmtCount->bind_param('ss', $fromDateTime, $toDateTime);
            $stmtCount->execute();
            $summary['audit'] = (int) (($stmtCount->get_result()->fetch_assoc()['total'] ?? 0));
            $stmtCount->close();
        }

        $stmtActions = $conn->prepare(
            "SELECT DISTINCT action
             FROM audit_logs
             WHERE created_at BETWEEN ? AND ?
               AND action IS NOT NULL
               AND action <> ''
             ORDER BY action ASC
             LIMIT 300"
        );
        if ($stmtActions) {
            $stmtActions->bind_param('ss', $fromDateTime, $toDateTime);
            $stmtActions->execute();
            $resultActions = $stmtActions->get_result();
            if ($resultActions instanceof mysqli_result) {
                while ($rowAction = $resultActions->fetch_assoc()) {
                    $actionValue = trim((string) ($rowAction['action'] ?? ''));
                    if ($actionValue !== '') {
                        $auditActionOptions[] = $actionValue;
                    }
                }
            }
            $stmtActions->close();
        }

        $stmtEntities = $conn->prepare(
            "SELECT DISTINCT entity_type
             FROM audit_logs
             WHERE created_at BETWEEN ? AND ?
               AND entity_type IS NOT NULL
               AND entity_type <> ''
             ORDER BY entity_type ASC
             LIMIT 300"
        );
        if ($stmtEntities) {
            $stmtEntities->bind_param('ss', $fromDateTime, $toDateTime);
            $stmtEntities->execute();
            $resultEntities = $stmtEntities->get_result();
            if ($resultEntities instanceof mysqli_result) {
                while ($rowEntity = $resultEntities->fetch_assoc()) {
                    $entityValue = trim((string) ($rowEntity['entity_type'] ?? ''));
                    if ($entityValue !== '') {
                        $auditEntityOptions[] = $entityValue;
                    }
                }
            }
            $stmtEntities->close();
        }

        $auditActionOptions = array_values(array_unique($auditActionOptions));
        $auditEntityOptions = array_values(array_unique($auditEntityOptions));
        sort($auditActionOptions);
        sort($auditEntityOptions);

        if ($auditAction !== '' && !in_array($auditAction, $auditActionOptions, true)) {
            $auditAction = '';
        }
        if ($auditEntity !== '' && !in_array($auditEntity, $auditEntityOptions, true)) {
            $auditEntity = '';
        }
    }

    if ($hasSmsLogs) {
        $stmtCount = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM sms_logs
             WHERE created_at BETWEEN ? AND ?"
        );
        if ($stmtCount) {
            $stmtCount->bind_param('ss', $fromDateTime, $toDateTime);
            $stmtCount->execute();
            $summary['sms'] = (int) (($stmtCount->get_result()->fetch_assoc()['total'] ?? 0));
            $stmtCount->close();
        }
    }

    if ($hasAuditLogs && in_array($logType, ['all', 'audit'], true)) {
        $where = "l.created_at BETWEEN " . $esc($fromDateTime) . " AND " . $esc($toDateTime);
        if ($auditAction !== '') {
            $where .= " AND l.action = " . $esc($auditAction);
        }
        if ($auditEntity !== '') {
            $where .= " AND l.entity_type = " . $esc($auditEntity);
        }
        $sql = "SELECT l.id, l.action, l.user_role, l.entity_type, l.entity_id, l.description, l.metadata_json,
                       l.ip_address, l.created_at, u.first_name, u.last_name
                FROM audit_logs l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE {$where}
                ORDER BY l.id DESC
                LIMIT " . (int) $limit;
        $result = $conn->query($sql);
        if ($result instanceof mysqli_result) {
            $auditLogs = $result->fetch_all(MYSQLI_ASSOC);
        }
    }

    if ($hasSmsLogs && in_array($logType, ['all', 'sms'], true)) {
        $where = "s.created_at BETWEEN " . $esc($fromDateTime) . " AND " . $esc($toDateTime);
        if ($smsStatus !== '') {
            $where .= " AND s.delivery_status = " . $esc($smsStatus);
        }
        $sql = "SELECT s.id, s.phone, s.message, s.sms_type, s.delivery_status, s.provider_response, s.created_at,
                       u.first_name, u.last_name
                FROM sms_logs s
                LEFT JOIN users u ON u.id = s.user_id
                WHERE {$where}
                ORDER BY s.id DESC
                LIMIT " . (int) $limit;
        $result = $conn->query($sql);
        if ($result instanceof mysqli_result) {
            $smsLogs = $result->fetch_all(MYSQLI_ASSOC);
        }
    }

}

$commonQuery = http_build_query([
    'from_date' => $fromDate,
    'to_date' => $toDate,
]);

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>System Logs</h1>
    <div class="d-flex gap-2">
        <a href="../shared/analytics.php?<?= e($commonQuery) ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-chart-pie me-1"></i>Analytics & Reports</a>
    </div>
</div>

<form method="get" class="card card-soft shadow-sm mb-3" data-live-filter-form data-live-filter-debounce="200">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm">From</label>
            <input type="date" class="form-control form-control-sm" name="from_date" value="<?= e($fromDate) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm">To</label>
            <input type="date" class="form-control form-control-sm" name="to_date" value="<?= e($toDate) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm">Log Type</label>
            <select name="log_type" class="form-select form-select-sm">
                <option value="all" <?= $logType === 'all' ? 'selected' : '' ?>>All</option>
                <option value="audit" <?= $logType === 'audit' ? 'selected' : '' ?>>Audit</option>
                <option value="sms" <?= $logType === 'sms' ? 'selected' : '' ?>>SMS</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm">What Happened</label>
            <select name="audit_action" class="form-select form-select-sm">
                <option value="">All Events</option>
                <?php foreach ($auditActionOptions as $actionOption): ?>
                    <option value="<?= e($actionOption) ?>" <?= $auditAction === $actionOption ? 'selected' : '' ?>>
                        <?= e(audit_action_label($actionOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm">Affected Record</label>
            <select name="audit_entity" class="form-select form-select-sm">
                <option value="">All Entities</option>
                <?php foreach ($auditEntityOptions as $entityOption): ?>
                    <option value="<?= e($entityOption) ?>" <?= $auditEntity === $entityOption ? 'selected' : '' ?>>
                        <?= e(audit_entity_label($entityOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm">SMS Result</label>
            <select name="sms_status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="queued" <?= $smsStatus === 'queued' ? 'selected' : '' ?>>Queued</option>
                <option value="success" <?= $smsStatus === 'success' ? 'selected' : '' ?>>Success</option>
                <option value="failed" <?= $smsStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label form-label-sm">Limit</label>
            <select name="limit" class="form-select form-select-sm">
                <?php foreach ([60, 120, 240, 500] as $limitOption): ?>
                    <option value="<?= $limitOption ?>" <?= $limit === $limitOption ? 'selected' : '' ?>><?= $limitOption ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4 d-flex gap-2 align-items-center">
            <span class="small text-muted">Live filter enabled</span>
            <a href="logs.php" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
    </div>
</form>

<?php if (!db_ready()): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body text-muted">The system is not ready yet. Please contact the administrator.</div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-4 col-md-4">
            <div class="card card-soft metric-card"><div class="card-body"><p class="small text-muted mb-1">Audit Logs</p><h3><?= number_format($summary['audit']) ?></h3></div></div>
        </div>
        <div class="col-4 col-md-4">
            <div class="card card-soft metric-card"><div class="card-body"><p class="small text-muted mb-1">SMS Logs</p><h3><?= number_format($summary['sms']) ?></h3></div></div>
        </div>
    </div>

    <?php if (in_array($logType, ['all', 'audit'], true)): ?>
        <div data-live-table class="card card-soft shadow-sm mb-3">
            <div class="card-body border-bottom table-controls">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="h6 m-0">Audit Logs</h2>
                    <div class="btn-group btn-group-sm">
                        <a href="export-reports.php?dataset=audit_logs&format=pdf&<?= e($commonQuery) ?>" class="btn btn-outline-primary">PDF</a>
                        <a href="export-reports.php?dataset=audit_logs&format=docx&<?= e($commonQuery) ?>" class="btn btn-outline-primary">DOCX</a>
                        <a href="export-reports.php?dataset=audit_logs&format=xlsx&<?= e($commonQuery) ?>" class="btn btn-outline-primary">XLSX</a>
                    </div>
                </div>
                <div class="row g-2 align-items-end mt-1">
                    <div class="col-12 col-md-5">
                        <label class="form-label form-label-sm">Live Search</label>
                        <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search event, user, affected record, notes">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm">Event Filter</label>
                        <select data-table-filter class="form-select form-select-sm">
                            <option value="">All Events</option>
                            <?php foreach ($auditActionOptions as $actionOption): ?>
                                <option value="<?= e($actionOption) ?>"><?= e(audit_action_label($actionOption)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm">Rows</label>
                        <select data-table-per-page class="form-select form-select-sm">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 text-md-end">
                        <span class="page-legend" data-table-summary></span>
                    </div>
                </div>
                <div class="small text-muted mt-2">
                    <strong>What Happened</strong> = system event. <strong>Affected Record</strong> = data type and ID touched by that event.
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>What Happened</th>
                            <th>Affected Record</th>
                            <th>Description</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $row): ?>
                            <?php
                            $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                            $meta = trim((string) ($row['metadata_json'] ?? ''));
                            $description = trim((string) ($row['description'] ?? ''));
                            if ($description === '') {
                                $description = $meta !== '' ? excerpt($meta, 110) : '-';
                            }
                            $actionCode = trim((string) ($row['action'] ?? ''));
                            $actionLabel = audit_action_label($actionCode);
                            $entityType = trim((string) ($row['entity_type'] ?? ''));
                            $entityId = trim((string) ($row['entity_id'] ?? ''));
                            $entityLabel = audit_entity_label($entityType);
                            $search = strtolower(implode(' ', [
                                (string) ($row['action'] ?? ''),
                                $actionLabel,
                                $fullName,
                                (string) ($row['entity_type'] ?? ''),
                                (string) ($row['entity_id'] ?? ''),
                                $description,
                            ]));
                            ?>
                            <tr data-search="<?= e($search) ?>" data-filter="<?= e((string) ($row['action'] ?? '')) ?>">
                                <td><?= date('M d, Y h:i A', strtotime((string) $row['created_at'])) ?></td>
                                <td>
                                    <?= e($fullName !== '' ? $fullName : '-') ?>
                                    <div class="small text-muted"><?= e((string) ($row['user_role'] ?? '-')) ?></div>
                                </td>
                                <td>
                                    <?= e($actionLabel) ?>
                                    <div class="small text-muted"><code><?= e($actionCode !== '' ? $actionCode : '-') ?></code></div>
                                </td>
                                <td>
                                    <?= e($entityLabel) ?>
                                    <div class="small text-muted"><?= e($entityId !== '' ? ('ID: ' . $entityId) : '-') ?></div>
                                </td>
                                <td><?= e($description) ?></td>
                                <td><?= e((string) ($row['ip_address'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top d-flex justify-content-end">
                <div class="d-flex gap-2" data-table-pager></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (in_array($logType, ['all', 'sms'], true)): ?>
        <div data-live-table class="card card-soft shadow-sm mb-3">
            <div class="card-body border-bottom table-controls">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="h6 m-0">SMS Logs</h2>
                    <div class="btn-group btn-group-sm">
                        <a href="export-reports.php?dataset=sms_logs&format=pdf&<?= e($commonQuery) ?>" class="btn btn-outline-primary">PDF</a>
                        <a href="export-reports.php?dataset=sms_logs&format=docx&<?= e($commonQuery) ?>" class="btn btn-outline-primary">DOCX</a>
                        <a href="export-reports.php?dataset=sms_logs&format=xlsx&<?= e($commonQuery) ?>" class="btn btn-outline-primary">XLSX</a>
                    </div>
                </div>
                <div class="row g-2 align-items-end mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label form-label-sm">Live Search</label>
                        <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search phone, message, sender">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm">Status Filter</label>
                        <select data-table-filter class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="queued">Queued</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm">Rows</label>
                        <select data-table-per-page class="form-select form-select-sm">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 text-md-end">
                        <span class="page-legend" data-table-summary></span>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($smsLogs as $row): ?>
                            <?php
                            $sender = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                            $search = strtolower(implode(' ', [
                                (string) ($row['phone'] ?? ''),
                                (string) ($row['message'] ?? ''),
                                (string) ($row['sms_type'] ?? ''),
                                (string) ($row['delivery_status'] ?? ''),
                                $sender,
                            ]));
                            ?>
                            <tr data-search="<?= e($search) ?>" data-filter="<?= e((string) ($row['delivery_status'] ?? '')) ?>">
                                <td><?= date('M d, Y h:i A', strtotime((string) $row['created_at'])) ?></td>
                                <td>
                                    <?= e((string) ($row['phone'] ?? '-')) ?>
                                    <div class="small text-muted"><?= e($sender !== '' ? $sender : '-') ?></div>
                                </td>
                                <td><?= e(excerpt((string) ($row['message'] ?? ''), 120)) ?></td>
                                <td><?= e(strtoupper((string) ($row['sms_type'] ?? '-'))) ?></td>
                                <td><span class="badge <?= status_badge_class((string) ($row['delivery_status'] ?? 'queued')) ?>"><?= e(strtoupper((string) ($row['delivery_status'] ?? 'QUEUED'))) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top d-flex justify-content-end">
                <div class="d-flex gap-2" data-table-pager></div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
