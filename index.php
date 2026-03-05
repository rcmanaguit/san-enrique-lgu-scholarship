<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

$pageTitle = 'San Enrique LGU Scholarship Portal';
$bodyClass = 'public-landing';
$announcements = [];
$openPeriod = null;
$isPublicApplyOpen = false;

if (db_ready()) {
    $openPeriod = current_open_application_period($conn);
    $isPublicApplyOpen = $openPeriod !== null;

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

include __DIR__ . '/includes/header.php';
?>

<section class="hero p-4 p-md-5 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-12 col-md-7">
            <h1 class="fw-bold mb-3">San Enrique LGU Scholarship Records Management System</h1>
            <p class="text-muted mb-4">
                A mobile-friendly scholarship portal for the LGU of San Enrique, Negros Occidental.
                Submit applications online, track status, and get updates faster.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <?php if (!is_logged_in()): ?>
                    <?php if ($isPublicApplyOpen): ?>
                        <a href="register.php" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Create Applicant Account</a>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled><i class="fa-solid fa-lock me-1"></i>Application Period Closed</button>
                    <?php endif; ?>
                    <a href="login.php" class="btn btn-outline-primary"><i class="fa-solid fa-right-to-bracket me-1"></i>Login</a>
                <?php else: ?>
                    <a href="<?= user_has_role(['admin', 'staff']) ? 'shared/dashboard.php' : 'dashboard.php' ?>" class="btn btn-primary">
                        Open Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-12 col-md-5">
            <div class="card card-soft shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">What you can do here</h5>
                    <?php if ($isPublicApplyOpen): ?>
                        <p class="small text-success mb-2"><i class="fa-solid fa-circle-check me-1"></i><?= e(format_application_period($openPeriod)) ?></p>
                    <?php else: ?>
                        <p class="small text-warning mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>Application period is currently closed.</p>
                    <?php endif; ?>
                    <ul class="small mb-0 ps-3">
                        <li>View scholarship announcements</li>
                        <li>Submit digital application and documents</li>
                        <li>Track application and interview schedule</li>
                        <li>See disbursement updates</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 m-0">Latest Announcements</h2>
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
                    <article class="card card-soft h-100">
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

<?php include __DIR__ . '/includes/footer.php'; ?>
