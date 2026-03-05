<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Applicants & Scholars List';
$rows = [];
$payoutScholarStatuses = ['waitlisted'];
$disbursedScholarStatuses = ['disbursed'];
$applicantStatuses = array_values(array_filter(application_status_options(), static function (string $status) use ($payoutScholarStatuses, $disbursedScholarStatuses): bool {
    return !in_array($status, $payoutScholarStatuses, true) && !in_array($status, $disbursedScholarStatuses, true);
}));
$allowedBarangays = san_enrique_barangays();
$hasBarangayColumn = table_column_exists($conn, 'applications', 'barangay');
$periodOptions = [];

$typeFilter = trim((string) ($_GET['type'] ?? ''));
$barangayFilter = trim((string) ($_GET['barangay'] ?? ''));
$schoolTypeFilter = trim((string) ($_GET['school_type'] ?? ''));
$periodFilter = trim((string) ($_GET['period'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$groupBySchool = !isset($_GET['group_school']) || trim((string) ($_GET['group_school'] ?? '1')) === '1';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 25);
$perPageOptions = [25, 50, 100];
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 25;
}

if ($typeFilter === 'scholar') {
    $typeFilter = 'disbursed';
}
if (!in_array($typeFilter, ['', 'applicant', 'payout', 'disbursed'], true)) {
    $typeFilter = '';
}
if (!in_array($schoolTypeFilter, ['', 'public', 'private'], true)) {
    $schoolTypeFilter = '';
}
if (!in_array($barangayFilter, $allowedBarangays, true)) {
    $barangayFilter = '';
}

if (db_ready()) {
    $barangaySelect = $hasBarangayColumn ? 'a.barangay' : "'' AS barangay";
    $conditions = [];
    if (!in_array($statusFilter, $applicantStatuses, true)) {
        $statusFilter = '';
    }

    if ($typeFilter === 'disbursed') {
        $safeStatuses = array_map(static fn(string $s): string => "'" . $conn->real_escape_string($s) . "'", $disbursedScholarStatuses);
        $conditions[] = 'a.status IN (' . implode(', ', $safeStatuses) . ')';
    } elseif ($typeFilter === 'payout') {
        $safeStatuses = array_map(static fn(string $s): string => "'" . $conn->real_escape_string($s) . "'", $payoutScholarStatuses);
        $conditions[] = 'a.status IN (' . implode(', ', $safeStatuses) . ')';
    } elseif ($typeFilter === 'applicant') {
        $excludedStatuses = array_values(array_unique(array_merge($payoutScholarStatuses, $disbursedScholarStatuses)));
        $safeStatuses = array_map(static fn(string $s): string => "'" . $conn->real_escape_string($s) . "'", $excludedStatuses);
        $conditions[] = 'a.status NOT IN (' . implode(', ', $safeStatuses) . ')';
        if ($statusFilter !== '') {
            $conditions[] = "a.status = '" . $conn->real_escape_string($statusFilter) . "'";
        }
    }
    if ($schoolTypeFilter !== '') {
        $conditions[] = "a.school_type = '" . $conn->real_escape_string($schoolTypeFilter) . "'";
    }
    if ($hasBarangayColumn && $barangayFilter !== '') {
        $conditions[] = "a.barangay = '" . $conn->real_escape_string($barangayFilter) . "'";
    }

    $periodSql = "SELECT DISTINCT semester, school_year
                  FROM applications
                  WHERE TRIM(COALESCE(semester, '')) <> '' AND TRIM(COALESCE(school_year, '')) <> ''
                  ORDER BY school_year DESC, FIELD(semester, 'First Semester', 'Second Semester')";
    $periodResult = $conn->query($periodSql);
    if ($periodResult instanceof mysqli_result) {
        while ($periodRow = $periodResult->fetch_assoc()) {
            $sem = trim((string) ($periodRow['semester'] ?? ''));
            $sy = trim((string) ($periodRow['school_year'] ?? ''));
            if ($sem === '' || $sy === '') {
                continue;
            }
            $value = $sem . '|' . $sy;
            $periodOptions[] = [
                'value' => $value,
                'label' => $sem . ' / ' . $sy,
                'semester' => $sem,
                'school_year' => $sy,
            ];
        }
    }

    if ($periodFilter !== '') {
        $validPeriodMap = [];
        foreach ($periodOptions as $opt) {
            $validPeriodMap[(string) ($opt['value'] ?? '')] = $opt;
        }
        if (!isset($validPeriodMap[$periodFilter])) {
            $periodFilter = '';
        } else {
            $selected = $validPeriodMap[$periodFilter];
            $conditions[] = "a.semester = '" . $conn->real_escape_string((string) ($selected['semester'] ?? '')) . "'";
            $conditions[] = "a.school_year = '" . $conn->real_escape_string((string) ($selected['school_year'] ?? '')) . "'";
        }
    }

    $whereClause = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $orderSql = $groupBySchool
        ? "a.school_name ASC, u.last_name ASC, u.first_name ASC, a.id ASC"
        : "u.last_name ASC, u.first_name ASC, a.school_name ASC, a.id ASC";

    $sql = "SELECT
                a.id AS application_id,
                a.application_no,
                a.applicant_type,
                a.school_year,
                a.semester,
                a.school_name,
                a.school_type,
                a.status,
                {$barangaySelect},
                a.updated_at,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.course
            FROM applications a
            INNER JOIN users u ON u.id = a.user_id
            {$whereClause}
            ORDER BY {$orderSql}";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$totalRows = count($rows);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$rowsPage = array_slice($rows, $offset, $perPage);
$startRow = $totalRows > 0 ? $offset + 1 : 0;
$endRow = min($offset + count($rowsPage), $totalRows);

$baseQueryParams = [
    'type' => $typeFilter,
    'barangay' => $barangayFilter,
    'school_type' => $schoolTypeFilter,
    'period' => $periodFilter,
    'status' => $statusFilter,
    'group_school' => $groupBySchool ? '1' : '0',
    'per_page' => (string) $perPage,
];
$basePageQuery = http_build_query(array_filter($baseQueryParams, static fn($v): bool => $v !== ''));
if ($basePageQuery !== '') {
    $basePageQuery .= '&';
}
$exportQueryParams = [
    'type' => $typeFilter,
    'barangay' => $barangayFilter,
    'school_type' => $schoolTypeFilter,
    'period' => $periodFilter,
    'status' => $statusFilter,
    'group_school' => $groupBySchool ? '1' : '0',
];
$exportQuery = http_build_query(array_filter($exportQueryParams, static fn($v): bool => $v !== ''));

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Generated Name List</h1>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="small text-muted">Filter first, then use this as your printable roll list.</span>
        <div class="btn-group btn-group-sm">
            <a href="export-scholars.php?<?= e($exportQuery) ?>&format=pdf" class="btn btn-outline-primary">PDF</a>
            <a href="export-scholars.php?<?= e($exportQuery) ?>&format=docx" class="btn btn-outline-primary">DOCX</a>
            <a href="export-scholars.php?<?= e($exportQuery) ?>&format=xlsx" class="btn btn-outline-primary">XLSX</a>
        </div>
    </div>
</div>

<div class="card card-soft shadow-sm" data-live-table>
    <div class="card-body border-bottom">
        <form method="get" class="row g-2 align-items-end" data-live-filter-form data-live-filter-debounce="250">
            <div class="col-12 col-md-3">
                <label class="form-label form-label-sm">Type</label>
                <input type="hidden" name="type" id="typeFilterInput" value="<?= e($typeFilter) ?>">
                <div class="d-flex flex-wrap gap-2" role="tablist" aria-label="Type tabs">
                    <button type="button" class="btn btn-outline-primary<?= $typeFilter === '' ? ' active' : '' ?>" data-type-tab="" aria-pressed="<?= $typeFilter === '' ? 'true' : 'false' ?>">All</button>
                    <button type="button" class="btn btn-outline-primary<?= $typeFilter === 'applicant' ? ' active' : '' ?>" data-type-tab="applicant" aria-pressed="<?= $typeFilter === 'applicant' ? 'true' : 'false' ?>">Applicants</button>
                    <button type="button" class="btn btn-outline-primary<?= $typeFilter === 'payout' ? ' active' : '' ?>" data-type-tab="payout" aria-pressed="<?= $typeFilter === 'payout' ? 'true' : 'false' ?>">Scholars for Payout</button>
                    <button type="button" class="btn btn-outline-primary<?= $typeFilter === 'disbursed' ? ' active' : '' ?>" data-type-tab="disbursed" aria-pressed="<?= $typeFilter === 'disbursed' ? 'true' : 'false' ?>">Disbursed Scholars</button>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm">Barangay</label>
                <select name="barangay" class="form-select form-select-sm" data-live-submit="immediate" <?= $hasBarangayColumn ? '' : 'disabled' ?>>
                    <option value="" <?= $barangayFilter === '' ? 'selected' : '' ?>>All</option>
                    <?php if ($hasBarangayColumn): ?>
                        <?php foreach ($allowedBarangays as $barangay): ?>
                            <option value="<?= e($barangay) ?>" <?= $barangayFilter === $barangay ? 'selected' : '' ?>><?= e($barangay) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">Unavailable</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label form-label-sm">School Type</label>
                <select name="school_type" class="form-select form-select-sm" data-live-submit="immediate">
                    <option value="" <?= $schoolTypeFilter === '' ? 'selected' : '' ?>>All</option>
                    <option value="public" <?= $schoolTypeFilter === 'public' ? 'selected' : '' ?>>Public</option>
                    <option value="private" <?= $schoolTypeFilter === 'private' ? 'selected' : '' ?>>Private</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm">Scholarship Period</label>
                <select name="period" class="form-select form-select-sm" data-live-submit="immediate">
                    <option value="" <?= $periodFilter === '' ? 'selected' : '' ?>>All</option>
                    <?php foreach ($periodOptions as $option): ?>
                        <option value="<?= e((string) ($option['value'] ?? '')) ?>" <?= $periodFilter === (string) ($option['value'] ?? '') ? 'selected' : '' ?>>
                            <?= e((string) ($option['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2" id="applicantStatusFilterWrap">
                <label class="form-label form-label-sm">Applicants Status</label>
                <select name="status" id="applicantStatusFilter" class="form-select form-select-sm" data-live-submit="immediate">
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All</option>
                    <?php foreach ($applicantStatuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm d-block">List Format</label>
                <input type="hidden" name="group_school" value="0">
                <div class="form-check mt-1">
                    <input type="checkbox" class="form-check-input" id="groupBySchoolCheck" name="group_school" value="1" <?= $groupBySchool ? 'checked' : '' ?> data-live-submit="immediate">
                    <label class="form-check-label small" for="groupBySchoolCheck">Group by school</label>
                </div>
            </div>
            <div class="col-6 col-md-1 d-grid">
                <a href="scholars.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body border-bottom table-controls">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm">Rows</label>
                <select class="form-select form-select-sm" onchange="window.location.href='scholars.php?<?= e($basePageQuery) ?>page=1&per_page=' + this.value;">
                    <?php foreach ($perPageOptions as $option): ?>
                        <option value="<?= (int) $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= (int) $option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-10 text-md-end">
                <span class="page-legend">Showing <?= (int) $startRow ?>-<?= (int) $endRow ?> of <?= number_format($totalRows) ?></span>
            </div>
        </div>
    </div>

    <?php if (!$rowsPage): ?>
        <div class="card-body text-muted">No records match the selected filters.</div>
    <?php else: ?>
        <div class="card-body border-bottom py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted">Generated names: <strong><?= number_format($totalRows) ?></strong></span>
            <span class="small text-muted">Grouped by school</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 90px;">No.</th>
                        <th>Name</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $currentSchool = null;
                $counter = $offset;
                foreach ($rowsPage as $row):
                ?>
                    <?php
                    $schoolName = trim((string) ($row['school_name'] ?? ''));
                    if ($schoolName === '') {
                        $schoolName = 'Unspecified School';
                    }
                    ?>
                    <?php if ($groupBySchool && $currentSchool !== $schoolName): ?>
                        <?php $currentSchool = $schoolName; ?>
                        <tr class="table-info">
                            <td colspan="2" class="fw-semibold text-uppercase"><?= e($currentSchool) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php $counter += 1; ?>
                    <tr>
                        <td><?= (int) $counter ?></td>
                        <td><?= e(strtoupper(trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="card-body border-top d-flex justify-content-end">
            <div class="d-flex gap-2" data-table-pager>
                <?php if ($page > 1): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="scholars.php?<?= e($basePageQuery) ?>page=<?= (int) ($page - 1) ?>">Previous</a>
                <?php endif; ?>
                <span class="btn btn-sm btn-light border disabled">Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="scholars.php?<?= e($basePageQuery) ?>page=<?= (int) ($page + 1) ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[data-live-filter-form]');
    const typeInput = document.getElementById('typeFilterInput');
    const typeTabs = Array.from(document.querySelectorAll('[data-type-tab]'));
    const applicantStatusWrap = document.getElementById('applicantStatusFilterWrap');
    const applicantStatusSelect = document.getElementById('applicantStatusFilter');
    if (!(form instanceof HTMLFormElement) || !(typeInput instanceof HTMLInputElement) || !typeTabs.length) {
        return;
    }

    function syncApplicantStatusVisibility() {
        const currentType = String(typeInput.value || '');
        const show = currentType === 'applicant';
        if (applicantStatusWrap) {
            applicantStatusWrap.classList.toggle('d-none', !show);
        }
        if (!show && applicantStatusSelect instanceof HTMLSelectElement && applicantStatusSelect.value !== '') {
            applicantStatusSelect.value = '';
        }
    }

    typeTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const value = String(tab.getAttribute('data-type-tab') || '');
            typeInput.value = value;
            typeTabs.forEach(function (btn) {
                const active = String(btn.getAttribute('data-type-tab') || '') === value;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            syncApplicantStatusVisibility();
            form.requestSubmit();
        });
    });

    syncApplicantStatusVisibility();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
