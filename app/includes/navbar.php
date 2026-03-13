<?php
declare(strict_types=1);

$user = current_user();
$currentPath = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$currentPath = trim($currentPath, '/');
if ($currentPath === '') {
    $currentPath = 'index.php';
}

$incomingOnAdminPage = $GLOBALS['onAdminPage'] ?? null;
if ($incomingOnAdminPage === null) {
    $pathForRoleCheck = '/' . $currentPath;
    $onAdminPage = str_contains($pathForRoleCheck, '/shared/')
        || str_contains($pathForRoleCheck, '/admin-only/')
        || str_contains($pathForRoleCheck, '/staff-only/');
} else {
    $onAdminPage = (bool) $incomingOnAdminPage;
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
$logoAbsolutePath = __DIR__ . '/../../' . $logoRelativePath;
$hasBrandLogo = file_exists($logoAbsolutePath);
$facebookPageUrl = 'https://www.facebook.com/groups/438677743308925';
$enableDesktopSidebar = $user && in_array((string) ($user['role'] ?? ''), ['admin', 'staff', 'applicant'], true);
$unreadNotifications = 0;
$hasOpenApplicationPeriod = false;
if (!$user && db_ready()) {
    $hasOpenApplicationPeriod = current_open_application_period($conn) !== null;
}
if ($user && db_ready()) {
    $unreadNotifications = unread_notification_count($conn, (int) ($user['id'] ?? 0));
}
$unreadNotificationsLabel = $unreadNotifications > 99 ? '99+' : (string) $unreadNotifications;
$notificationsActive = $isActive('notifications.php');
$profileActive = $isActive(['profile-settings.php', 'account-security.php']);
$globalSearchActive = $isActive('shared/global-search.php');
$canUseGlobalSearch = $user && in_array((string) ($user['role'] ?? ''), ['admin', 'staff'], true);
$settingsNavActive = $isActive([
    'profile-settings.php',
    'account-security.php',
    'admin-only/application-periods.php',
    'admin-only/staff.php',
    'admin-only/logs.php',
]);
$isAdminUser = $user && (string) ($user['role'] ?? '') === 'admin';
$brandHomePath = 'index.php';
if ($user && in_array((string) ($user['role'] ?? ''), ['admin', 'staff'], true)) {
    $brandHomePath = 'shared/dashboard.php';
} elseif ($user && (string) ($user['role'] ?? '') === 'applicant') {
    $brandHomePath = 'dashboard.php';
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top"
    data-desktop-sidebar="<?= $enableDesktopSidebar ? '1' : '0' ?>">
    <div class="container">
        <div class="navbar-left-cluster">
            <button class="navbar-toggler me-2" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <?php if ($enableDesktopSidebar): ?>
                <button type="button" class="btn btn-outline-primary btn-sm nav-sidebar-toggle d-none d-lg-inline-flex"
                    data-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="true" title="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
            <?php endif; ?>
            <a class="navbar-brand fw-semibold" href="<?= e($link($brandHomePath)) ?>">
                <?php if ($hasBrandLogo): ?>
                    <img src="<?= e($link($logoRelativePath)) ?>" alt="Municipality of San Enrique Official Seal"
                        class="navbar-logo">
                <?php else: ?>
                    <span class="navbar-logo-fallback" aria-hidden="true"><i class="fa-solid fa-shield"></i></span>
                <?php endif; ?>
                <span class="navbar-brand-text">San Enrique LGU Scholarship</span>
            </a>
        </div>
        <?php if ($user): ?>
            <div class="navbar-top-actions ms-auto d-flex align-items-center gap-2">
                <?php if ($canUseGlobalSearch): ?>
                    <div class="navbar-global-search" data-navbar-global-search
                        data-search-endpoint="<?= e($link('shared/global-search-api.php')) ?>"
                        data-search-page="<?= e($link('shared/global-search.php')) ?>">
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm nav-top-icon d-none d-lg-inline-flex<?= $globalSearchActive ? ' active' : '' ?>"
                            title="Global Search"
                            aria-label="Global Search"
                            aria-expanded="false"
                            aria-controls="navbarGlobalSearchPanel"
                            data-nav-search-toggle
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <div class="nav-search-panel shadow-sm" id="navbarGlobalSearchPanel" data-nav-search-panel>
                            <div class="nav-search-head">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-search"></i></span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        maxlength="120"
                                        placeholder="Search records..."
                                        autocomplete="off"
                                        data-nav-search-input
                                    >
                                    <button type="button" class="btn btn-outline-secondary" data-nav-search-clear>Clear</button>
                                </div>
                                <a href="<?= e($link('shared/global-search.php')) ?>" class="small text-decoration-none"
                                    data-nav-search-open-full>Open full page</a>
                            </div>
                            <div class="nav-search-status small text-muted" data-nav-search-status>Type at least 2 characters.</div>
                            <div class="nav-search-results" data-nav-search-results></div>
                        </div>
                    </div>
                <?php endif; ?>
                <a
                    class="btn btn-outline-primary btn-sm nav-top-icon<?= $notificationsActive ? ' active' : '' ?>"
                    href="<?= e($link('notifications.php')) ?>"
                    title="Notifications"
                    aria-label="Notifications"
                    data-realtime-notification="1"
                >
                    <i class="fa-regular fa-bell"></i>
                    <span class="badge rounded-pill text-bg-danger nav-top-icon-badge<?= $unreadNotifications > 0 ? '' : ' d-none' ?>"
                        data-realtime-notification-badge
                        aria-live="polite"><?= e($unreadNotificationsLabel) ?></span>
                </a>
                <a
                    class="btn btn-outline-primary btn-sm nav-top-icon<?= $profileActive ? ' active' : '' ?>"
                    href="<?= e($link('profile-settings.php')) ?>"
                    title="Profile / Settings"
                    aria-label="Profile / Settings"
                >
                    <i class="fa-solid fa-user-gear"></i>
                </a>
                <a
                    class="btn btn-outline-danger btn-sm nav-top-icon d-none d-lg-inline-flex"
                    href="<?= e($link('logout.php')) ?>"
                    title="Logout"
                    aria-label="Logout"
                >
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        <?php endif; ?>
        <div class="collapse navbar-collapse app-nav-panel" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (!$user): ?>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'applicant'): ?>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('dashboard.php')) ?>" href="<?= e($link('dashboard.php')) ?>"><i
                                class="fa-solid fa-gauge me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('my-application.php')) ?>"
                            href="<?= e($link('my-application.php')) ?>"><i class="fa-solid fa-folder-open me-1"></i>My
                            Application & Status</a>
                    </li>
                <?php endif; ?>
                <?php if ($user && in_array($user['role'], ['admin', 'staff'], true)): ?>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/dashboard.php')) ?>"
                            href="<?= e($link('shared/dashboard.php')) ?>"><i
                                class="fa-solid fa-gauge-high me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/applications.php')) ?>"
                            href="<?= e($link('shared/applications.php')) ?>"><i
                                class="fa-solid fa-folder-tree me-1"></i>Application Queue</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass(['shared/scholars.php', 'shared/applicants-scholars.php', 'shared/masterlist.php'])) ?>"
                            href="<?= e($link('shared/scholars.php')) ?>"><i
                                class="fa-solid fa-users-between-lines me-1"></i>Applicants & Scholars</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/disbursements.php')) ?>"
                            href="<?= e($link('shared/disbursements.php')) ?>"><i
                                class="fa-solid fa-money-check-dollar me-1"></i>Payout Queue</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass(['shared/sms.php', 'admin-only/announcements.php'])) ?>" href="<?= e($link('shared/sms.php')) ?>"><i
                                class="fa-solid fa-comments me-1"></i>Communications</a>
                    </li>
                    <li class="nav-item">
                        <a class="<?= e($navLinkClass('shared/analytics.php')) ?>"
                            href="<?= e($link('shared/analytics.php')) ?>"><i
                                class="fa-solid fa-chart-pie me-1"></i>Analytics & Reports</a>
                    </li>
                    <li class="nav-item">
                        <a
                            class="nav-link settings-toggle<?= $settingsNavActive ? ' active' : '' ?>"
                            href="#settingsNavMenu"
                            data-bs-toggle="collapse"
                            role="button"
                            aria-expanded="<?= $settingsNavActive ? 'true' : 'false' ?>"
                            aria-controls="settingsNavMenu"
                        >
                            <i class="fa-solid fa-gear me-1"></i>Settings
                            <i class="fa-solid fa-chevron-down ms-auto small"></i>
                        </a>
                        <div class="collapse settings-submenu<?= $settingsNavActive ? ' show' : '' ?>" id="settingsNavMenu">
                            <div class="nav flex-column gap-1">
                                <a class="<?= e($navLinkClass(['profile-settings.php', 'account-security.php'])) ?>" href="<?= e($link('profile-settings.php')) ?>">
                                    <i class="fa-solid fa-user-gear me-1"></i>Profile & Security
                                </a>
                            <?php if ($isAdminUser): ?>
                                <a class="<?= e($navLinkClass('admin-only/application-periods.php')) ?>" href="<?= e($link('admin-only/application-periods.php')) ?>">
                                    <i class="fa-solid fa-calendar-days me-1"></i>Application Periods
                                </a>
                                <a class="<?= e($navLinkClass('admin-only/staff.php')) ?>" href="<?= e($link('admin-only/staff.php')) ?>">
                                    <i class="fa-solid fa-user-shield me-1"></i>Staff
                                </a>
                                <a class="<?= e($navLinkClass('admin-only/logs.php')) ?>" href="<?= e($link('admin-only/logs.php')) ?>">
                                    <i class="fa-solid fa-clipboard-list me-1"></i>Logs
                                </a>
                            <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>
                <?php if ($user): ?>
                    <?php if ($canUseGlobalSearch): ?>
                        <li class="nav-item d-lg-none">
                            <a class="<?= e($navLinkClass('shared/global-search.php')) ?>"
                                href="<?= e($link('shared/global-search.php')) ?>">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>Global Search
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item d-lg-none">
                        <a class="nav-link" href="<?= e($link('logout.php')) ?>">
                            <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2 app-nav-actions">
                <?php if (!$user): ?>
                    <?php if ($hasOpenApplicationPeriod): ?>
                        <a class="btn btn-primary btn-sm" href="<?= e($link('register.php')) ?>"><i
                                class="fa-solid fa-user-plus me-1"></i>Apply Now</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-sm" disabled>
                            <i class="fa-solid fa-lock me-1"></i>Applications Closed
                        </button>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e($link('login.php')) ?>"><i
                            class="fa-solid fa-right-to-bracket me-1"></i>Track Application</a>
                <?php else: ?>
                    <span class="small text-muted align-self-center d-none d-md-inline nav-user-label">
                        <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mobile-nav-backdrop d-lg-none" data-mobile-nav-backdrop></div>
    </div>
</nav>
