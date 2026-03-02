<?php
declare(strict_types=1);

$user = current_user();
$currentPath = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$currentPath = trim($currentPath, '/');
if ($currentPath === '') {
    $currentPath = 'index.php';
}

$normalizePath = static function (string $path): string {
    return trim(str_replace('\\', '/', $path), '/');
};

$isActive = static function ($targets) use ($currentPath, $normalizePath): bool {
    foreach ((array) $targets as $target) {
        $candidate = $normalizePath((string) $target);
        if ($candidate === '') {
            continue;
        }

        if ($currentPath === $candidate || str_ends_with($currentPath, '/' . $candidate)) {
            return true;
        }

        if (!str_contains($candidate, '/') && basename($currentPath) === $candidate) {
            return true;
        }
    }

    return false;
};

$navLinkClass = static function ($targets) use ($isActive): string {
    return 'nav-link' . ($isActive($targets) ? ' active' : '');
};

$link = static function (string $path) use ($onAdminPage): string {
    $path = ltrim($path, '/');
    if ($onAdminPage) {
        return '../' . $path;
    }
    return $path;
};

$logoRelativePath = 'assets/images/branding/lgu-logo.png';
$logoAbsolutePath = __DIR__ . '/../' . $logoRelativePath;
$hasBrandLogo = file_exists($logoAbsolutePath);
$enableDesktopSidebar = $user && in_array((string) ($user['role'] ?? ''), ['admin', 'staff', 'applicant'], true);
$unreadNotifications = 0;
$hasOpenApplicationPeriod = false;
if (db_ready()) {
    $hasOpenApplicationPeriod = current_open_application_period($conn) !== null;
}
if ($user && db_ready()) {
    $unreadNotifications = unread_notification_count($conn, (int) ($user['id'] ?? 0));
}
$unreadNotificationsLabel = $unreadNotifications > 99 ? '99+' : (string) $unreadNotifications;
$brandHomePath = 'index.php';
if ($user && in_array((string) ($user['role'] ?? ''), ['admin', 'staff'], true)) {
    $brandHomePath = 'shared/dashboard.php';
} elseif ($user && (string) ($user['role'] ?? '') === 'applicant') {
    $brandHomePath = 'dashboard.php';
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top" data-desktop-sidebar="<?= $enableDesktopSidebar ? '1' : '0' ?>">
    <div class="container">
        <div class="navbar-left-cluster">
            <?php if ($enableDesktopSidebar): ?>
                <button
                    type="button"
                    class="btn btn-outline-primary btn-sm nav-sidebar-toggle d-none d-lg-inline-flex"
                    data-sidebar-toggle
                    aria-label="Toggle sidebar"
                    aria-expanded="true"
                    title="Toggle sidebar"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>
            <?php endif; ?>
            <a class="navbar-brand fw-semibold" href="<?= e($link($brandHomePath)) ?>">
                <?php if ($hasBrandLogo): ?>
                    <img src="<?= e($link($logoRelativePath)) ?>" alt="Municipality of San Enrique Official Seal" class="navbar-logo">
                <?php else: ?>
                    <span class="navbar-logo-fallback" aria-hidden="true"><i class="fa-solid fa-shield"></i></span>
                <?php endif; ?>
                <span class="navbar-brand-text">San Enrique LGU Scholarship</span>
            </a>
        </div>
        <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse app-nav-panel" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (!$user || $user['role'] === 'applicant'): ?>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass(['announcements.php', 'shared/announcements.php'])) ?>" href="<?= e($link('announcements.php')) ?>"><i class="fa-regular fa-bell me-1"></i>Announcements</a>
                    </li>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'applicant'): ?>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('dashboard.php')) ?>" href="<?= e($link('dashboard.php')) ?>"><i class="fa-solid fa-gauge me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <?php if ($hasOpenApplicationPeriod): ?>
                            <a class="<?= e($navLinkClass('apply.php')) ?>" href="<?= e($link('apply.php')) ?>"><i class="fa-solid fa-file-pen me-1"></i>Apply</a>
                        <?php else: ?>
                            <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true" title="Application Period Closed">
                                <i class="fa-solid fa-file-pen me-1"></i>Apply (Closed)
                            </a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('my-application.php')) ?>" href="<?= e($link('my-application.php')) ?>"><i class="fa-solid fa-folder-open me-1"></i>My Application</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass(['profile-settings.php', 'account-security.php'])) ?>" href="<?= e($link('profile-settings.php')) ?>"><i class="fa-solid fa-user-gear me-1"></i>Profile / Settings</a>
                    </li>
                <?php endif; ?>
                <?php if ($user && in_array($user['role'], ['admin', 'staff'], true)): ?>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/dashboard.php')) ?>" href="<?= e($link('shared/dashboard.php')) ?>"><i class="fa-solid fa-gauge-high me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/applications.php')) ?>" href="<?= e($link('shared/applications.php')) ?>"><i class="fa-solid fa-folder-tree me-1"></i>Applications</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/masterlist.php')) ?>" href="<?= e($link('shared/masterlist.php')) ?>"><i class="fa-solid fa-table-list me-1"></i>Masterlist</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/interviews.php')) ?>" href="<?= e($link('shared/interviews.php')) ?>"><i class="fa-solid fa-calendar-check me-1"></i>Interviews</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/disbursements.php')) ?>" href="<?= e($link('shared/disbursements.php')) ?>"><i class="fa-solid fa-money-check-dollar me-1"></i>Disbursements</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/verify-qr.php')) ?>" href="<?= e($link('shared/verify-qr.php')) ?>"><i class="fa-solid fa-qrcode me-1"></i>QR Scanner</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/scholars.php')) ?>" href="<?= e($link('shared/scholars.php')) ?>"><i class="fa-solid fa-users me-1"></i>Scholars</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/global-search.php')) ?>" href="<?= e($link('shared/global-search.php')) ?>"><i class="fa-solid fa-magnifying-glass me-1"></i>Global Search</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/analytics.php')) ?>" href="<?= e($link('shared/analytics.php')) ?>"><i class="fa-solid fa-chart-pie me-1"></i>Analytics</a>
                    </li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="<?= e($navLinkClass('admin-only/announcements.php')) ?>" href="<?= e($link('admin-only/announcements.php')) ?>"><i class="fa-regular fa-newspaper me-1"></i>Manage Announcements</a>
                        </li>
                        <li class="nav-item">
                            <a class="<?= e($navLinkClass('admin-only/requirements.php')) ?>" href="<?= e($link('admin-only/requirements.php')) ?>"><i class="fa-solid fa-list-check me-1"></i>Requirements</a>
                        </li>
                        <li class="nav-item">
                            <a class="<?= e($navLinkClass('admin-only/application-periods.php')) ?>" href="<?= e($link('admin-only/application-periods.php')) ?>"><i class="fa-solid fa-calendar-days me-1"></i>Application Periods</a>
                        </li>
                        <li class="nav-item">
                            <a class="<?= e($navLinkClass('admin-only/reports.php')) ?>" href="<?= e($link('admin-only/reports.php')) ?>"><i class="fa-solid fa-chart-line me-1"></i>Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="<?= e($navLinkClass('admin-only/logs.php')) ?>" href="<?= e($link('admin-only/logs.php')) ?>"><i class="fa-solid fa-clipboard-list me-1"></i>Logs</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($user): ?>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('notifications.php')) ?>" href="<?= e($link('notifications.php')) ?>">
                            <i class="fa-regular fa-bell me-1"></i>Notifications
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="badge rounded-pill text-bg-danger nav-notification-badge ms-1"><?= e($unreadNotificationsLabel) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php if (in_array((string) ($user['role'] ?? ''), ['admin', 'staff'], true)): ?>
                        <li class="nav-item">
                            <a class="<?= e($navLinkClass(['profile-settings.php', 'account-security.php'])) ?>" href="<?= e($link('profile-settings.php')) ?>"><i class="fa-solid fa-user-gear me-1"></i>Profile / Settings</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2 app-nav-actions">
                <?php if ($user && in_array($user['role'], ['admin', 'staff'], true)): ?>
                    <form class="d-none d-xl-flex me-1" role="search" method="get" action="<?= e($link('shared/global-search.php')) ?>">
                        <div class="input-group input-group-sm nav-global-search">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-search"></i></span>
                            <input
                                type="text"
                                class="form-control"
                                name="q"
                                placeholder="Global search"
                                minlength="2"
                                maxlength="120"
                                aria-label="Global search"
                            >
                        </div>
                    </form>
                <?php endif; ?>
                <?php if (!$user): ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e($link('login.php')) ?>"><i class="fa-solid fa-right-to-bracket me-1"></i>Login</a>
                    <?php if ($hasOpenApplicationPeriod): ?>
                        <a class="btn btn-primary btn-sm" href="<?= e($link('register.php')) ?>"><i class="fa-solid fa-user-plus me-1"></i>Register</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-sm" disabled>
                            <i class="fa-solid fa-lock me-1"></i>Application Period Closed
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="small text-muted align-self-center d-none d-md-inline nav-user-label">
                        <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                    </span>
                    <a class="btn btn-outline-danger btn-sm" href="<?= e($link('logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
