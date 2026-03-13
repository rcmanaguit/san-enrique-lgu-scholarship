<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

$pageTitle = 'Scholarship Announcements';
$announcements = [];

if (db_ready()) {
    $result = $conn->query("SELECT id, title, content, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC");
    if ($result instanceof mysqli_result) {
        $announcements = $result->fetch_all(MYSQLI_ASSOC);
    }
}

include __DIR__ . '/../includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Public Updates';
$pageHeaderTitle = 'Announcements';
$pageHeaderDescription = 'Read scholarship opening dates, reminders, and office advisories here.';
$pageHeaderActions = '<a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Home</a>';
include __DIR__ . '/../includes/partials/page-shell-header.php';
?>

<?php if (!db_ready()): ?>
    <div class="alert alert-warning">The system is not ready yet. Please contact the administrator.</div>
<?php elseif (!$announcements): ?>
    <div class="card card-soft">
        <div class="card-body text-muted">No announcements available.</div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($announcements as $row): ?>
            <div class="col-12">
                <article class="card card-soft shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-3 align-items-start flex-wrap">
                            <h2 class="h5 mb-2"><?= e($row['title']) ?></h2>
                            <span class="badge text-bg-light"><?= date('M d, Y', strtotime((string) $row['created_at'])) ?></span>
                        </div>
                        <p class="mb-0"><?= nl2br(e((string) $row['content'])) ?></p>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
