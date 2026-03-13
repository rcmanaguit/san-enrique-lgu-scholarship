<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

$pageTitle = 'San Enrique LGU Scholarship Portal';
$bodyClass = 'public-landing';
$announcements = [];
$openPeriod = null;
$isPublicApplyOpen = false;
$openPeriodRequirements = [];

if (db_ready()) {
    $openPeriod = current_open_application_period($conn);
    $isPublicApplyOpen = $openPeriod !== null;
    if ($isPublicApplyOpen) {
        $openPeriodRequirements = current_period_requirements($conn, null, null);
    }

    $sql = "SELECT id, title, content, created_at
            FROM announcements
            WHERE is_active = 1
            ORDER BY created_at DESC
            LIMIT 3";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $announcements = $result->fetch_all(MYSQLI_ASSOC);
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="public-hero-section mb-4">
    <div class="public-hero-card">
        <div class="public-hero-copy">
            <p class="public-kicker mb-2">Scholarship Application Portal</p>
            <h1 class="public-hero-title mb-3">San Enrique LGU Scholarship</h1>
            <p class="public-hero-text mb-4">
                Apply during the current submission period, upload your requirements, and track your application in one place.
            </p>
            <div class="d-flex flex-wrap gap-2 mb-3 public-hero-actions">
                <?php if (!is_logged_in()): ?>
                    <?php if ($isPublicApplyOpen): ?>
                        <a href="register.php" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Apply Now</a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled><i class="fa-solid fa-lock me-1"></i>Applications Closed</button>
                    <?php endif; ?>
                    <a href="login.php" class="btn btn-outline-primary"><i class="fa-solid fa-right-to-bracket me-1"></i>Login to Track Application</a>
                <?php else: ?>
                    <a href="<?= user_has_role(['admin', 'staff']) ? 'shared/dashboard.php' : 'dashboard.php' ?>" class="btn btn-primary">
                        <?= user_has_role(['admin', 'staff']) ? 'Open Dashboard' : 'Open Dashboard' ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="public-period-pill">
                <?php if ($isPublicApplyOpen): ?>
                    <i class="fa-solid fa-circle-check me-1 text-success"></i>Current period: <?= e(format_application_period($openPeriod)) ?>. Apply until <?= e(!empty($openPeriod['end_date']) ? date('M d, Y', strtotime((string) $openPeriod['end_date'])) : '-') ?>
                <?php else: ?>
                    <i class="fa-solid fa-circle-exclamation me-1 text-warning"></i>Applications are currently closed
                <?php endif; ?>
            </div>
            <?php if ($isPublicApplyOpen && $openPeriodRequirements): ?>
                <div class="public-action-list public-requirements-list mt-3">
                    <p class="small text-muted mb-1">Requirements for this period</p>
                    <div class="small public-requirements-inline">
                        <?php foreach ($openPeriodRequirements as $index => $requirement): ?>
                            <?= $index > 0 ? '<span class="text-muted"> • </span>' : '' ?>
                            <span>
                                <?= e((string) ($requirement['requirement_name'] ?? 'Requirement')) ?>
                                <?php if (trim((string) ($requirement['description'] ?? '')) !== ''): ?>
                                    <span class="text-muted">(<?= e((string) ($requirement['description'] ?? '')) ?>)</span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="h4 m-0">Latest Announcements</h2>
            <p class="small text-muted mb-0">Read the newest public updates from the LGU scholarship office.</p>
        </div>
        <a href="announcements.php" class="small">View all</a>
    </div>

    <?php if (!db_ready()): ?>
        <div class="alert alert-warning">
            The system setup is not complete yet. Please contact the administrator.
        </div>
    <?php elseif (!$announcements): ?>
        <div class="card card-soft">
            <div class="card-body text-muted">No announcements yet.</div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($announcements as $item): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <article class="card card-soft h-100 public-announcement-card">
                        <div class="card-body">
                            <h3 class="h6"><?= e($item['title']) ?></h3>
                            <p class="text-muted small mb-2">
                                <?= date('F d, Y', strtotime((string) $item['created_at'])) ?>
                            </p>
                            <p class="small mb-0"><?= e(excerpt((string) $item['content'], 160)) ?></p>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
