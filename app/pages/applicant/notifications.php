<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('login.php');

$pageTitle = 'Notifications';
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$filter = trim((string) ($_GET['filter'] ?? 'all'));
if (!in_array($filter, ['all', 'unread'], true)) {
    $filter = 'all';
}
$bodyClass = 'applicant-notifications-page';

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('notifications.php?filter=' . urlencode($filter));
    }

    if (!db_ready()) {
        set_flash('warning', 'The system is not ready yet. Please contact the administrator.');
        redirect('notifications.php?filter=' . urlencode($filter));
    }

    if (!notifications_table_ready($conn)) {
        set_flash('warning', 'Notifications are not available yet. Please contact the administrator.');
        redirect('notifications.php?filter=' . urlencode($filter));
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'mark_all_read') {
        $affected = mark_all_notifications_read($conn, $userId);
        if ($affected > 0) {
            set_flash('success', 'Marked ' . $affected . ' notification(s) as read.');
        } else {
            set_flash('info', 'No unread notifications to mark.');
        }
        redirect('notifications.php?filter=' . urlencode($filter));
    }

    if ($action === 'mark_read') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        if ($notificationId > 0 && mark_notification_read($conn, $notificationId, $userId)) {
            set_flash('success', 'Notification marked as read.');
        } else {
            set_flash('info', 'Notification is already read or unavailable.');
        }
        redirect('notifications.php?filter=' . urlencode($filter));
    }
}

$hasNotificationFeature = db_ready() && notifications_table_ready($conn);
$unreadCount = 0;
$notifications = [];

if ($hasNotificationFeature) {
    $unreadCount = unread_notification_count($conn, $userId);
    $notifications = list_notifications($conn, $userId, 120, $filter === 'unread');
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="card card-soft applicant-hero mb-3">
    <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <p class="text-muted small mb-1">Updates Center</p>
            <h1 class="h4 m-0"><i class="fa-regular fa-bell me-2 text-primary"></i>Notifications</h1>
            <p class="small text-muted mb-0 mt-1">View alerts for application progress and important announcements.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap applicant-hero-actions">
        <a href="notifications.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
            All
        </a>
        <a href="notifications.php?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Unread<?= $unreadCount > 0 ? ' (' . (int) $unreadCount . ')' : '' ?>
        </a>
        <a href="<?= user_has_role(['admin', 'staff']) ? 'shared/dashboard.php' : 'dashboard.php' ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
        </a>
        </div>
    </div>
</div>

<?php if (!db_ready()): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body">
            <p class="mb-0 text-muted">The system is not ready yet. Please contact the administrator.</p>
        </div>
    </div>
<?php elseif (!$hasNotificationFeature): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body">
            <p class="mb-1">Notifications are not available yet.</p>
            <p class="small text-muted mb-0">Please contact the administrator to finish setup.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card card-soft shadow-sm mb-3 applicant-notification-summary">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-muted">
                Unread: <strong><?= (int) $unreadCount ?></strong>
            </div>
            <form method="post" class="m-0" data-crud-record="All Notifications">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-check-double me-1"></i>Mark All as Read
                </button>
            </form>
        </div>
    </div>

    <?php if (!$notifications): ?>
        <div class="card card-soft shadow-sm">
            <div class="card-body text-muted">
                No <?= $filter === 'unread' ? 'unread ' : '' ?>notifications.
            </div>
        </div>
    <?php else: ?>
        <div class="d-grid gap-3">
            <?php foreach ($notifications as $item): ?>
                <?php
                $isRead = (int) ($item['is_read'] ?? 0) === 1;
                $type = (string) ($item['notification_type'] ?? 'system');
                $relatedUrl = trim((string) ($item['related_url'] ?? ''));
                $hasSafeUrl = $relatedUrl !== ''
                    && preg_match('/^[a-zA-Z0-9_\\-\\/\\.\\?=&%#]+$/', $relatedUrl) === 1
                    && !str_starts_with(strtolower($relatedUrl), 'http://')
                    && !str_starts_with(strtolower($relatedUrl), 'https://')
                    && !str_starts_with($relatedUrl, '//');
                ?>
                <article class="card card-soft shadow-sm applicant-notification-item<?= $isRead ? ' is-read' : ' is-unread' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div>
                                <h2 class="h6 mb-1">
                                    <?= e((string) ($item['title'] ?? 'Notification')) ?>
                                    <?php if (!$isRead): ?>
                                        <span class="badge text-bg-primary ms-1">NEW</span>
                                    <?php endif; ?>
                                </h2>
                                <div class="small text-muted">
                                    <?= date('M d, Y h:i A', strtotime((string) ($item['created_at'] ?? 'now'))) ?>
                                </div>
                            </div>
                            <span class="badge <?= notification_type_badge_class($type) ?>">
                                <?= e(strtoupper(str_replace('_', ' ', $type))) ?>
                            </span>
                        </div>

                        <p class="mb-2"><?= nl2br(e((string) ($item['message'] ?? ''))) ?></p>

                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($hasSafeUrl): ?>
                                <a href="<?= e($relatedUrl) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-up-right-from-square me-1"></i>Open
                                </a>
                            <?php endif; ?>
                            <?php if (!$isRead): ?>
                                <form method="post" class="m-0" data-crud-record="<?= e((string) ($item['title'] ?? 'Notification')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= (int) ($item['id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="fa-solid fa-check me-1"></i>Mark as Read
                                    </button>
                                </form>
                            <?php elseif (!empty($item['read_at'])): ?>
                                <span class="small text-muted align-self-center">
                                    Read on <?= date('M d, Y h:i A', strtotime((string) $item['read_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

