<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/apply_actions.php';

require_login('login.php');
require_role(['applicant'], 'index.php');

if (!db_ready()) {
    set_flash('warning', 'The system setup is not complete yet. Please contact the administrator.');
    redirect('dashboard.php');
}

$hasPeriodTable = table_exists($conn, 'application_periods');
if (!$hasPeriodTable) {
    set_flash('warning', 'Application period settings are not ready yet. Please contact the administrator.');
    redirect('dashboard.php');
}

$openPeriod = current_open_application_period($conn);
if (!$openPeriod) {
    set_flash('warning', 'Applications are currently closed. You cannot submit an application without an open application period.');
    redirect('dashboard.php');
}

$pageTitle = 'Scholarship Application Wizard';
$realtimeConfig = [
    'enabled' => false,
];
$user = current_user();
$pageState = apply_bootstrap_page_state($conn, $user, $openPeriod);
$detectedApplicantType = (string) ($pageState['detected_applicant_type'] ?? 'new');
$wizard = (array) ($pageState['wizard'] ?? wizard_state());
$step = (int) ($pageState['step'] ?? 1);

$persistWizard = static function (array $state, int $currentStep) use ($conn, $user): void {
    apply_persist_wizard_state($conn, (int) ($user['id'] ?? 0), $state, $currentStep);
};

$getStepFourRequirements = static function (array $state) use ($conn): array {
    return apply_get_step_four_requirements($conn, $state);
};

if (is_post()) {
    apply_handle_post_request(
        $conn,
        $user,
        $openPeriod,
        $detectedApplicantType,
        $step,
        $persistWizard,
        $getStepFourRequirements
    );
}

$wizard = wizard_state();
$viewData = apply_prepare_view_data($conn, $user, $openPeriod, $wizard, $detectedApplicantType);
extract($viewData, EXTR_OVERWRITE);

$extraJs = ['assets/js/apply-wizard.js', 'assets/js/apply-validation.js', 'assets/js/apply-page.js', 'assets/js/capture-utils.js', 'assets/js/capture-ui.js'];
$bodyClass = 'apply-page';

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-file-pen me-2 text-primary"></i>Scholarship Application Wizard</h1>
    <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
        <a href="apply.php?reset=1" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reset Draft</a>
    </div>
</div>

<div class="card card-soft mb-3 wizard-step-summary">
    <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-primary">Step <?= (int) $step ?> of 6</span>
            <span class="small text-muted"><?= e((string) ($stepLabels[$step] ?? 'Application Wizard')) ?></span>
        </div>
        <?php if ($openPeriod): ?>
            <span class="small text-muted">
                <i class="fa-regular fa-calendar-check me-1"></i><?= e(format_application_period($openPeriod)) ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="card card-soft mb-3">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 wizard-progress">
            <?php foreach ($stepLabels as $stepNo => $label): ?>
                <?php
                $class = 'wizard-step-pill';
                if ($stepNo < $step) {
                    $class .= ' done';
                } elseif ($stepNo === $step) {
                    $class .= ' active';
                }
                ?>
                <div class="d-flex align-items-center wizard-progress-item">
                    <div class="<?= e($class) ?>"><?= $stepNo ?></div>
                    <div class="wizard-step-label ms-2 d-none d-sm-block"><?= e($label) ?></div>
                    <?php if ($stepNo < 6): ?>
                        <div class="wizard-divider ms-2"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($step === 1): ?>
    <?php include __DIR__ . '/../../includes/partials/apply/step-one.php'; ?>
<?php endif; ?>

<?php if ($step === 2): ?>
    <?php include __DIR__ . '/../../includes/partials/apply/step-two.php'; ?>
<?php endif; ?>

<?php if ($step === 3): ?>
    <?php
    $siblingsPrefill = $step3['siblings'] ?? [];
    $educationPrefill = $step3['education'] ?? [];
    $grantsPrefill = $step3['grants'] ?? [];
    $motherNa = !empty($step3['mother_na']);
    $motherDeceased = !empty($step3['mother_deceased']);
    $fatherNa = !empty($step3['father_na']);
    $fatherDeceased = !empty($step3['father_deceased']);
    $siblingsNa = !empty($step3['siblings_na']);
    $grantsNa = !empty($step3['grants_na']);

    $educationByLevel = [];
    foreach ($educationPrefill as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = strtolower(trim((string) ($row['level'] ?? '')));
        if ($key !== '') {
            $educationByLevel[$key] = $row;
        }
    }
    $eduElementary = $educationByLevel['elementary'] ?? ['level' => 'Elementary', 'school' => '', 'year' => '', 'honors' => '', 'course' => ''];
    $eduHighSchool = $educationByLevel['high school'] ?? ['level' => 'High School', 'school' => '', 'year' => '', 'honors' => '', 'course' => ''];
    $eduCollege = $educationByLevel['college'] ?? ['level' => 'College', 'school' => '', 'year' => '', 'honors' => '', 'course' => ''];
    $collegeYearLevelValue = trim((string) ($eduCollege['year'] ?? ''));
    $collegeYearLevelOptions = ['1', '2', '3', '4'];
    if (!in_array($collegeYearLevelValue, $collegeYearLevelOptions, true)) {
        $collegeYearLevelValue = '';
    }
    $collegeDefaultSchool = trim((string) ($step1['school_name'] ?? ''));
    $collegeDefaultCourse = trim((string) ($step1['course'] ?? ''));
    if (trim((string) ($eduCollege['school'] ?? '')) === '' && $collegeDefaultSchool !== '') {
        $eduCollege['school'] = $collegeDefaultSchool;
    }
    if (trim((string) ($eduCollege['course'] ?? '')) === '' && $collegeDefaultCourse !== '') {
        $eduCollege['course'] = $collegeDefaultCourse;
    }
    ?>
    <?php include __DIR__ . '/../../includes/partials/apply/step-three.php'; ?>
<?php endif; ?>

<?php if ($step === 4): ?>
    <?php include __DIR__ . '/../../includes/partials/apply/step-four.php'; ?>
<?php endif; ?>

<?php if ($step === 6): ?>
    <?php include __DIR__ . '/../../includes/partials/apply/step-six.php'; ?>
<?php endif; ?>

<script>
window.SE_APPLY_PAGE_CONFIG = <?= json_encode([
    'step' => (int) $step,
    'enableAutosave' => in_array($step, [1, 2, 3], true),
    'hasReviewPreview' => $step === 6,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>


