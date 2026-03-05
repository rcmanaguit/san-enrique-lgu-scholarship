<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Masterlist';
$rows = [];

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$schoolTypeFilter = trim((string) ($_GET['school_type'] ?? ''));
$schoolYearFilter = trim((string) ($_GET['school_year'] ?? ''));
$barangayFilter = trim((string) ($_GET['barangay'] ?? ''));

$allowedStatus = application_status_options();
$allowedBarangays = san_enrique_barangays();
$hasBarangayColumn = table_column_exists($conn, 'applications', 'barangay');
$hasTownColumn = table_column_exists($conn, 'applications', 'town');
$hasProvinceColumn = table_column_exists($conn, 'applications', 'province');
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}
if (!in_array($schoolTypeFilter, ['public', 'private'], true)) {
    $schoolTypeFilter = '';
}
if (!in_array($barangayFilter, $allowedBarangays, true)) {
    $barangayFilter = '';
}
if (!$hasBarangayColumn) {
    $barangayFilter = '';
}

$whereClause = '';
$barangaySelect = $hasBarangayColumn ? 'a.barangay' : "'' AS barangay";
$townSelect = $hasTownColumn ? 'a.town' : "'" . $conn->real_escape_string(san_enrique_town()) . "' AS town";
$provinceSelect = $hasProvinceColumn ? 'a.province' : "'" . $conn->real_escape_string(san_enrique_province()) . "' AS province";

$sql = "SELECT
            a.id,
            a.application_no,
            a.applicant_type,
            a.school_name,
            a.school_type,
            a.semester,
            a.school_year,
            {$barangaySelect},
            {$townSelect},
            {$provinceSelect},
            a.status,
            a.soa_submission_deadline,
            a.soa_submitted_at,
            a.submitted_at,
            u.first_name,
            u.last_name,
            u.phone,
            u.email,
            COALESCE(SUM(d.amount), 0) AS total_disbursed
        FROM applications a
        INNER JOIN users u ON u.id = a.user_id
        LEFT JOIN disbursements d ON d.application_id = a.id
        {$whereClause}
        GROUP BY a.id
        ORDER BY a.id DESC";

$result = $conn->query($sql);
if ($result instanceof mysqli_result) {
    $rows = $result->fetch_all(MYSQLI_ASSOC);
}

$exportQuery = http_build_query([
    'status' => $statusFilter,
    'school_type' => $schoolTypeFilter,
    'school_year' => $schoolYearFilter,
    'barangay' => $barangayFilter,
]);

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-table-list me-2 text-primary"></i>San Enrique LGU Scholarship Masterlist</h1>
    <div class="d-flex gap-2">
        <a
            href="export-masterlist.php?<?= e($exportQuery) ?>&format=pdf"
            class="btn btn-outline-primary btn-sm"
            data-masterlist-export-link
            data-export-format="pdf"
        >
            <i class="fa-solid fa-file-pdf me-1"></i>Export PDF
        </a>
        <a
            href="export-masterlist.php?<?= e($exportQuery) ?>&format=docx"
            class="btn btn-outline-primary btn-sm"
            data-masterlist-export-link
            data-export-format="docx"
        >
            <i class="fa-solid fa-file-word me-1"></i>Export DOCX
        </a>
        <a
            href="export-masterlist.php?<?= e($exportQuery) ?>&format=xlsx"
            class="btn btn-primary btn-sm"
            data-masterlist-export-link
            data-export-format="xlsx"
        >
            <i class="fa-solid fa-file-excel me-1"></i>Export XLSX
        </a>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card card-soft"><div class="card-body text-muted">No records found for selected filters.</div></div>
<?php else: ?>
    <div data-live-table class="card card-soft shadow-sm">
        <div class="card-body border-bottom table-controls">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label form-label-sm">Live Search</label>
                    <input type="text" data-table-search class="form-control form-control-sm" placeholder="Search application no, name, school, applicant type, barangay">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Status</label>
                    <select class="form-select form-select-sm" data-table-filter data-filter-key="status">
                        <option value="">All</option>
                        <?php foreach ($allowedStatus as $status): ?>
                            <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                                <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">School Type</label>
                    <select class="form-select form-select-sm" data-table-filter data-filter-key="school-type">
                        <option value="">All</option>
                        <option value="public" <?= $schoolTypeFilter === 'public' ? 'selected' : '' ?>>Public</option>
                        <option value="private" <?= $schoolTypeFilter === 'private' ? 'selected' : '' ?>>Private</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">School Year</label>
                    <input type="text" class="form-control form-control-sm" data-table-filter data-filter-key="school-year" data-filter-mode="contains" value="<?= e($schoolYearFilter) ?>" placeholder="2026-2027">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm">Barangay</label>
                    <select class="form-select form-select-sm" data-table-filter data-filter-key="barangay" <?= $hasBarangayColumn ? '' : 'disabled' ?>>
                        <option value="">All</option>
                        <?php if ($hasBarangayColumn): ?>
                            <?php foreach ($allowedBarangays as $barangay): ?>
                                <option value="<?= e($barangay) ?>" <?= $barangayFilter === $barangay ? 'selected' : '' ?>><?= e($barangay) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">Unavailable in current setup</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label form-label-sm">Rows</label>
                    <select data-table-per-page class="form-select form-select-sm">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div class="col-6 col-md-1 d-grid">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="masterlistLiveReset">Reset</button>
                </div>
                <div class="col-12 col-md-12 text-md-end">
                    <span class="page-legend" data-table-summary></span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Applicant Type</th>
                        <th>School</th>
                        <th>Status</th>
                        <th>Total Disbursed</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $searchText = strtolower(implode(' ', [
                            $row['application_no'],
                            $row['first_name'],
                            $row['last_name'],
                            $row['school_name'],
                            $row['applicant_type'],
                            $row['school_year'],
                            $row['barangay'],
                            $row['town'],
                            $row['province'],
                            $row['status'],
                        ]));
                        ?>
                        <tr
                            data-search="<?= e($searchText) ?>"
                            data-filter="<?= e((string) $row['status']) ?>"
                            data-status="<?= e((string) $row['status']) ?>"
                            data-school-type="<?= e((string) $row['school_type']) ?>"
                            data-school-year="<?= e((string) $row['school_year']) ?>"
                            data-barangay="<?= e((string) ($row['barangay'] ?? '')) ?>"
                        >
                            <td>
                                <strong><?= e((string) $row['application_no']) ?></strong>
                                <div class="small text-muted">#<?= (int) $row['id'] ?> | <?= e((string) $row['semester']) ?> / <?= e((string) $row['school_year']) ?></div>
                            </td>
                            <td>
                                <?= e((string) $row['last_name']) ?>, <?= e((string) $row['first_name']) ?>
                                <div class="small text-muted"><?= e((string) $row['phone']) ?> | <?= e((string) $row['email']) ?></div>
                                <div class="small text-muted"><?= e((string) ($row['barangay'] ?? '')) ?>, <?= e((string) ($row['town'] ?? san_enrique_town())) ?>, <?= e((string) ($row['province'] ?? san_enrique_province())) ?></div>
                            </td>
                            <td>
                                <?= e(strtoupper((string) $row['applicant_type'])) ?>
                            </td>
                            <td>
                                <?= e((string) $row['school_name']) ?>
                                <div class="small text-muted"><?= e(strtoupper((string) $row['school_type'])) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= status_badge_class((string) $row['status']) ?>"><?= e(strtoupper((string) $row['status'])) ?></span>
                                <?php if ((string) $row['status'] === 'for_soa_submission' && !empty($row['soa_submission_deadline'])): ?>
                                    <div class="small text-muted">SOA deadline: <?= date('M d, Y', strtotime((string) $row['soa_submission_deadline'])) ?></div>
                                <?php endif; ?>
                                <?php if ((string) $row['status'] === 'soa_submitted' && !empty($row['soa_submitted_at'])): ?>
                                    <div class="small text-muted">SOA received: <?= date('M d, Y', strtotime((string) $row['soa_submitted_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>PHP <?= number_format((float) $row['total_disbursed'], 2) ?></td>
                            <td class="text-end">
                                <a href="../print-application.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                            </td>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const resetBtn = document.getElementById('masterlistLiveReset');
    const exportLinks = Array.from(document.querySelectorAll('[data-masterlist-export-link]'));
    const statusFilter = document.querySelector('[data-live-table] [data-table-filter][data-filter-key="status"]');
    const schoolTypeFilter = document.querySelector('[data-live-table] [data-table-filter][data-filter-key="school-type"]');
    const schoolYearFilter = document.querySelector('[data-live-table] [data-table-filter][data-filter-key="school-year"]');
    const barangayFilter = document.querySelector('[data-live-table] [data-table-filter][data-filter-key="barangay"]');

    function updateExportLinks() {
        if (!exportLinks.length) {
            return;
        }
        const status = statusFilter instanceof HTMLSelectElement ? String(statusFilter.value || '').trim() : '';
        const schoolType = schoolTypeFilter instanceof HTMLSelectElement ? String(schoolTypeFilter.value || '').trim() : '';
        const schoolYear = schoolYearFilter instanceof HTMLInputElement ? String(schoolYearFilter.value || '').trim() : '';
        const barangay = barangayFilter instanceof HTMLSelectElement ? String(barangayFilter.value || '').trim() : '';

        exportLinks.forEach(function (link) {
            const format = String(link.getAttribute('data-export-format') || '').trim().toLowerCase();
            const params = new URLSearchParams();
            if (status !== '') {
                params.set('status', status);
            }
            if (schoolType !== '') {
                params.set('school_type', schoolType);
            }
            if (schoolYear !== '') {
                params.set('school_year', schoolYear);
            }
            if (barangay !== '') {
                params.set('barangay', barangay);
            }
            if (format !== '') {
                params.set('format', format);
            }
            link.setAttribute('href', 'export-masterlist.php?' + params.toString());
        });
    }

    [statusFilter, schoolTypeFilter, schoolYearFilter, barangayFilter].forEach(function (control) {
        if (!(control instanceof HTMLElement)) {
            return;
        }
        const eventName = control instanceof HTMLInputElement ? 'input' : 'change';
        control.addEventListener(eventName, updateExportLinks);
    });

    if (!resetBtn) {
        updateExportLinks();
        return;
    }

    resetBtn.addEventListener('click', function () {
        const filterControls = Array.from(document.querySelectorAll('[data-live-table] [data-table-filter]'));
        filterControls.forEach(function (control) {
            if (!(control instanceof HTMLElement)) {
                return;
            }
            if (control instanceof HTMLSelectElement) {
                control.value = '';
                control.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
            if (control instanceof HTMLInputElement) {
                control.value = '';
                control.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        updateExportLinks();
    });

    updateExportLinks();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
