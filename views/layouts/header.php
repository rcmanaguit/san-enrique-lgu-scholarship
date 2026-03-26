<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>San Enrique LGU Scholarship System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css')); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
    (function () {
        try {
            var savedTheme = localStorage.getItem('app-theme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = savedTheme || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.style.colorScheme = 'light';
        }
    })();
    </script>
    <script>
    (function () {
        function ensureCsrfField(form) {
            if (!form || String(form.method || '').toUpperCase() !== 'POST') {
                return;
            }

            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            var token = tokenMeta ? tokenMeta.getAttribute('content') || '' : '';
            if (!token) {
                return;
            }

            var existingField = form.querySelector('input[name="_csrf"]');
            if (existingField) {
                existingField.value = token;
                return;
            }

            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = '_csrf';
            hidden.value = token;
            form.appendChild(hidden);
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form').forEach(ensureCsrfField);
        });

        document.addEventListener('submit', function (event) {
            ensureCsrfField(event.target);
        }, true);
    })();
    </script>
</head>

<?php $layoutUserRole = $_SESSION['role'] ?? null; ?>
<?php $layoutHideAuthenticatedNavbar = (bool) ($hideAuthenticatedNavbar ?? false); ?>
<body class="bg-light<?php echo $layoutUserRole !== null && !$layoutHideAuthenticatedNavbar ? ' app-shell-layout' : ''; ?>">
<?php $layoutBasePath = trim((string) app_base_path(), '/'); ?>
<?php $layoutCurrentPath = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'); ?>
<?php if ($layoutBasePath !== '' && str_starts_with($layoutCurrentPath, $layoutBasePath)) {
    $layoutCurrentPath = trim(substr($layoutCurrentPath, strlen($layoutBasePath)), '/');
} ?>
<?php
$layoutDashboardPath = 'login';
if ($layoutUserRole === 'Student') {
    $layoutDashboardPath = 'student/dashboard';
} elseif ($layoutUserRole === 'Admin') {
    $layoutDashboardPath = 'admin/dashboard';
} elseif ($layoutUserRole === 'Staff') {
    $layoutDashboardPath = 'staff/dashboard';
}
$layoutPortalLabel = $layoutUserRole !== null ? ($layoutUserRole . ' Portal') : '';
$layoutUserName = trim((string) (($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')));
$layoutSearchValue = (string) ($_GET['q'] ?? '');
$layoutFirstName = trim((string) ($_SESSION['first_name'] ?? ''));
$layoutCurrentUserId = (int) ($_SESSION['user_id'] ?? 0);
$layoutNotifications = [];
$layoutUnreadNotificationCount = 0;
if ($layoutCurrentUserId > 0) {
    $layoutNotifications = \App\Models\Notification::latestForUser($layoutCurrentUserId, 8);
    $layoutUnreadNotificationCount = \App\Models\Notification::unreadCountForUser($layoutCurrentUserId);
}
?>
<?php if ($layoutUserRole !== null && !$layoutHideAuthenticatedNavbar): ?>
    <nav class="navbar navbar-expand-lg app-navbar sticky-top">
        <div class="container-fluid">
            <div class="app-navbar-left">
                <button class="btn btn-outline-primary app-navbar-sidebar-toggle d-md-none me-2" type="button"
                    data-sidebar-mobile-toggle aria-controls="sidebarMenu"
                    aria-expanded="false" aria-label="Open navigation menu" title="Open navigation menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <button
                    class="btn btn-outline-primary app-navbar-sidebar-toggle d-none d-md-inline-flex me-2"
                    type="button"
                    data-sidebar-desktop-toggle
                    aria-controls="sidebarMenu"
                    aria-expanded="true"
                    aria-label="Collapse sidebar"
                    title="Collapse sidebar"
                >
                    <i class="fa-solid fa-bars-staggered"></i>
                </button>
                <a class="navbar-brand app-navbar-brand" href="<?php echo htmlspecialchars(base_url($layoutDashboardPath)); ?>">
                    <img src="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>" alt="LGU Logo" class="app-navbar-logo">
                    <span class="app-navbar-brand-copy">
                        <span class="app-navbar-brand-text">San Enrique LGU Scholarship</span>
                    </span>
                </a>
            </div>
            <div class="app-navbar-actions ms-auto">
                    <?php if (in_array($layoutUserRole, ['Staff', 'Admin'], true)): ?>
                        <div class="app-navbar-search-panel-wrap" data-navbar-search>
                            <button
                                type="button"
                                class="btn btn-outline-primary app-navbar-icon"
                                data-navbar-search-toggle
                                aria-expanded="false"
                                aria-controls="appNavbarSearchPanel"
                                title="Search"
                                aria-label="Search"
                            >
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                            <div class="app-navbar-search-panel shadow-sm" id="appNavbarSearchPanel" data-navbar-search-panel>
                                <form action="<?php echo htmlspecialchars(base_url('search')); ?>" method="GET" role="search" data-search-suggestions-form data-suggestions-url="<?php echo htmlspecialchars(base_url('search/suggestions')); ?>">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="fa-solid fa-search"></i></span>
                                        <input
                                            type="search"
                                            name="q"
                                            class="form-control"
                                            maxlength="120"
                                            placeholder="Search applications, scholars, school..."
                                            value="<?php echo htmlspecialchars($layoutSearchValue); ?>"
                                        >
                                        <button type="button" class="btn btn-outline-secondary" data-navbar-search-clear>Clear</button>
                                    </div>
                                    <div class="app-navbar-search-panel-actions">
                                        <a href="<?php echo htmlspecialchars(base_url('search')); ?>" class="small text-decoration-none">Open full page</a>
                                        <span class="small text-muted">Suggestions appear while you type</span>
                                    </div>
                                </form>
                                <div class="app-navbar-search-suggestions" data-navbar-search-suggestions>
                                    <div class="app-navbar-search-suggestions-empty">Start typing to see suggestions.</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <span class="app-navbar-user-chip d-none d-xl-inline-flex" title="<?php echo htmlspecialchars($layoutUserName !== '' ? $layoutUserName : $layoutUserRole); ?>">
                        <span class="app-navbar-user-chip-name"><?php echo htmlspecialchars($layoutFirstName !== '' ? $layoutFirstName : $layoutUserRole); ?></span>
                        <span class="app-navbar-user-chip-role"><?php echo htmlspecialchars(ucfirst(strtolower((string) $layoutUserRole))); ?></span>
                    </span>
                    <button
                        type="button"
                        class="btn btn-outline-primary app-navbar-icon app-theme-toggle"
                        data-theme-toggle
                        aria-label="Toggle dark mode"
                        title="Toggle dark mode"
                    >
                        <i class="fa-regular fa-moon"></i>
                    </button>
                    <div
                        class="dropdown"
                        data-notification-widget
                        data-notification-feed-url="<?php echo htmlspecialchars(base_url('notifications/feed')); ?>"
                        data-notification-mark-read-url="<?php echo htmlspecialchars(base_url('notifications/mark-read')); ?>"
                        data-notification-mark-all-read-url="<?php echo htmlspecialchars(base_url('notifications/mark-all-read')); ?>"
                    >
                        <button class="btn btn-outline-primary app-navbar-icon app-navbar-bell" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                            <i class="fa-regular fa-bell"></i>
                            <span class="app-navbar-bell-badge<?php echo $layoutUnreadNotificationCount > 0 ? '' : ' d-none'; ?>" data-notification-badge><?php echo htmlspecialchars($layoutUnreadNotificationCount > 99 ? '99+' : (string) $layoutUnreadNotificationCount); ?></span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown shadow-sm">
                            <div class="notification-dropdown-header d-flex justify-content-between align-items-center">
                                <strong>Notifications</strong>
                                <form action="<?php echo htmlspecialchars(base_url('notifications/mark-all-read')); ?>" method="POST" data-notification-mark-all-form>
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($layoutCurrentPath); ?>">
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Read All</button>
                                </form>
                            </div>

                            <div class="notification-dropdown-body" data-notification-list>
                                <?php if ($layoutNotifications === []): ?>
                                    <div class="notification-empty-state" data-notification-empty>
                                        No notifications yet.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($layoutNotifications as $layoutNotification): ?>
                                        <?php $layoutNotificationLink = trim((string) ($layoutNotification['link_path'] ?? '')); ?>
                                        <a class="notification-item text-decoration-none <?php echo ((int) ($layoutNotification['is_read'] ?? 0) === 0) ? 'notification-item-unread' : ''; ?>"
                                            data-notification-id="<?php echo (int) ($layoutNotification['id'] ?? 0); ?>"
                                            href="<?php echo htmlspecialchars($layoutNotificationLink !== '' ? base_url($layoutNotificationLink) : '#'); ?>">
                                            <div class="notification-item-title"><?php echo htmlspecialchars((string) ($layoutNotification['title'] ?? 'Notification')); ?></div>
                                            <div class="notification-item-message"><?php echo htmlspecialchars((string) ($layoutNotification['message'] ?? '')); ?></div>
                                            <div class="notification-item-time"><?php echo htmlspecialchars((string) ($layoutNotification['created_at'] ?? '')); ?></div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary app-navbar-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="app-navbar-user-avatar"><i class="fa-solid fa-user"></i></span>
                            <i class="fa-solid fa-chevron-down small"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end app-navbar-user-menu shadow-sm">
                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars(base_url($layoutDashboardPath)); ?>"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
                            <?php if ($layoutUserRole === 'Student'): ?>
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars(base_url('student/apply')); ?>"><i class="fa-solid fa-file-signature me-2"></i>Apply</a></li>
                            <?php elseif ($layoutUserRole === 'Admin'): ?>
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars(base_url('admin/batch-interview')); ?>"><i class="fa-solid fa-users-viewfinder me-2"></i>Interview Schedules</a></li>
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars(base_url('admin/reports')); ?>"><i class="fa-solid fa-table-list me-2"></i>Reports</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars(base_url('admin/batch-interview')); ?>"><i class="fa-solid fa-users-viewfinder me-2"></i>Interviews</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars(base_url('account/settings')); ?>"><i class="fa-solid fa-user-gear me-2"></i>Account Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars(base_url('logout')); ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </div>
            </div>
        </div>
    </nav>
    <div class="app-navbar-backdrop d-md-none" data-app-sidebar-backdrop></div>
<?php endif; ?>
