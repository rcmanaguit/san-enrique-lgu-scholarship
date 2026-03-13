<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/my_application_actions.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('login.php');
require_role(['applicant'], 'index.php');

$pageTitle = 'My Application Progress';
$user = current_user();
$periodScope = trim((string) ($_GET['period_scope'] ?? 'active'));
$allowedPeriodScopes = ['active', 'archived', 'all'];
$bodyClass = 'applicant-my-application-page';
if (!in_array($periodScope, $allowedPeriodScopes, true)) {
    $periodScope = 'active';
}

if (is_post()) {
    my_application_handle_post_request($conn, $user, $periodScope);
}
$pageData = my_application_load_page_data($conn, $user, $periodScope);
extract($pageData, EXTR_OVERWRITE);
$extraJs = ['assets/js/my-application-page.js'];

include __DIR__ . '/../../includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Application Tracker';
$pageHeaderTitle = '<i class="fa-solid fa-folder-open me-2 text-primary"></i>My Application';
$pageHeaderDescription = 'Check your current status, next step, and record history here.';
$pageHeaderPrimaryAction = $canCreateNewApplication
    ? '<a href="apply.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>New Application</a>'
    : ($openPeriod && $hasApplicationThisPeriod
        ? '<button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock me-1"></i>Already Applied This Period</button>'
        : '<button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-lock me-1"></i>Application Period Closed</button>');
$trackerSubmissionText = '';
if (!empty($openPeriod['end_date'])) {
    $trackerSubmissionText = ' Submission closes on <strong>' . e(date('M d, Y', strtotime((string) $openPeriod['end_date']))) . '</strong>.';
}
$pageHeaderSecondaryInfo = $openPeriod
    ? 'Current period: <strong>' . e(format_application_period($openPeriod)) . '</strong>. Submission: <strong>Open</strong>.' . $trackerSubmissionText
    : 'No open period right now. Your past records are still available in archived history.';
$pageHeaderActions = '';
include __DIR__ . '/../../includes/partials/page-shell-header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <?php
    $activeScopeUrl = 'my-application.php?period_scope=active';
    $archivedScopeUrl = 'my-application.php?period_scope=archived';
    $allScopeUrl = 'my-application.php?period_scope=all';
    ?>
    <div class="btn-group btn-group-sm" role="group" aria-label="Application scope">
        <a href="<?= e($activeScopeUrl) ?>" class="btn <?= $periodScope === 'active' ? 'btn-primary' : 'btn-outline-primary' ?>">Active Period</a>
        <a href="<?= e($archivedScopeUrl) ?>" class="btn <?= $periodScope === 'archived' ? 'btn-primary' : 'btn-outline-primary' ?>">Archived</a>
        <a href="<?= e($allScopeUrl) ?>" class="btn <?= $periodScope === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All Records</a>
    </div>
    <div class="small text-muted">Choose which records to view.</div>
</div>

<?php if (!db_ready()): ?>
    <div class="alert alert-warning">The system is not ready yet. Please contact the administrator.</div>
<?php elseif ($openPeriod && $hasApplicationThisPeriod): ?>
    <div class="alert alert-secondary small">
        You already submitted an application in <?= e((string) ($openPeriod['period_name'] ?? 'the current period')) ?>.
        A new application is allowed only in the next open period.
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/partials/my-application/latest-application.php'; ?>

<?php include __DIR__ . '/../../includes/partials/my-application/resubmissions.php'; ?>

<?php include __DIR__ . '/../../includes/partials/my-application/history.php'; ?>

<?php include __DIR__ . '/../../includes/partials/my-application/modals.php'; ?>

<script>
window.SE_MY_APPLICATION_PAGE = {
    applicationModalPayload: <?= json_encode($applicationModalPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

