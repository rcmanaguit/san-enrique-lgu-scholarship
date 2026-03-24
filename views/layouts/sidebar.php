<?php
$userRole = $_SESSION['role'] ?? 'Student';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$relativeCurrentPath = trim((string) $currentPath, '/');
$basePath = trim((string) app_base_path(), '/');
if ($basePath !== '' && str_starts_with($relativeCurrentPath, $basePath)) {
    $relativeCurrentPath = trim(substr($relativeCurrentPath, strlen($basePath)), '/');
}

function sidebar_link_is_active(array $patterns, string $currentPath): bool
{
    foreach ($patterns as $pattern) {
        $normalized = trim($pattern, '/');
        if ($normalized === '') {
            continue;
        }

        if ($currentPath === $normalized || str_starts_with($currentPath, $normalized . '/')) {
            return true;
        }
    }

    return false;
}

function sidebar_nav_groups(string $userRole): array
{
    $groups = [];

    if ($userRole === 'Student') {
        $groups[] = [
            'label' => 'Menu',
            'links' => [
                [
                    'label' => 'Dashboard',
                    'icon' => 'fa-solid fa-timeline',
                    'path' => 'student/dashboard',
                    'patterns' => ['student/dashboard'],
                ],
                [
                    'label' => 'My Applications',
                    'icon' => 'fa-solid fa-clock-rotate-left',
                    'path' => 'student/history',
                    'patterns' => ['student/history', 'student/application'],
                ],
            ],
        ];
    }

    if (in_array($userRole, ['Staff', 'Admin'], true)) {
        $groups[] = [
            'label' => 'Menu',
            'links' => [
                [
                    'label' => 'Dashboard',
                    'icon' => $userRole === 'Admin' ? 'fa-solid fa-chart-pie' : 'fa-solid fa-gauge-high',
                    'path' => $userRole === 'Admin' ? 'admin/dashboard' : 'staff/dashboard',
                    'patterns' => [$userRole === 'Admin' ? 'admin/dashboard' : 'staff/dashboard'],
                ],
                [
                    'label' => 'Applications',
                    'icon' => 'fa-solid fa-folder-open',
                    'path' => 'staff/applications',
                    'patterns' => ['staff/applications', 'staff/verify-documents'],
                ],
                [
                    'label' => $userRole === 'Admin' ? 'Interview Schedules' : 'Interviews',
                    'icon' => 'fa-solid fa-users-viewfinder',
                    'path' => 'admin/batch-interview',
                    'patterns' => ['admin/batch-interview'],
                ],
            ],
        ];

        $groups[] = [
            'label' => 'Records',
            'links' => [
                [
                    'label' => 'Master Record',
                    'icon' => 'fa-solid fa-id-card-clip',
                    'path' => 'staff/archive',
                    'patterns' => ['staff/archive'],
                ],
            ],
        ];
    }

    if ($userRole === 'Admin') {
        $groups[0]['links'][] = [
            'label' => 'Reports',
            'icon' => 'fa-solid fa-table-list',
            'path' => 'admin/reports',
            'patterns' => ['admin/reports'],
        ];
        $groups[0]['links'][] = [
            'label' => 'Payouts',
            'icon' => 'fa-solid fa-money-bill-wave',
            'path' => 'admin/final-approval',
            'patterns' => ['admin/final-approval'],
        ];
        $groups[0]['links'][] = [
            'label' => 'Application Period',
            'icon' => 'fa-solid fa-gears',
            'path' => 'admin/settings',
            'patterns' => ['admin/settings'],
        ];
        $groups[] = [
            'label' => 'More',
            'links' => [
                [
                    'label' => 'Exceptions',
                    'icon' => 'fa-solid fa-triangle-exclamation',
                    'path' => 'admin/exceptions',
                    'patterns' => ['admin/exceptions'],
                ],
                [
                    'label' => 'Announcements',
                    'icon' => 'fa-solid fa-bullhorn',
                    'path' => 'admin/announcements',
                    'patterns' => ['admin/announcements'],
                ],
                [
                    'label' => 'Users',
                    'icon' => 'fa-solid fa-users-gear',
                    'path' => 'admin/users',
                    'patterns' => ['admin/users'],
                ],
                [
                    'label' => 'Audit Logs',
                    'icon' => 'fa-solid fa-clipboard-list',
                    'path' => 'admin/audit-logs',
                    'patterns' => ['admin/audit-logs'],
                ],
                [
                    'label' => 'Recovery',
                    'icon' => 'fa-solid fa-user-shield',
                    'path' => 'admin/account-recovery',
                    'patterns' => ['admin/account-recovery'],
                ],
            ],
        ];
    }

    return $groups;
}

$sidebarGroups = sidebar_nav_groups($userRole);
$accountSettingsActive = sidebar_link_is_active(['account/settings'], $relativeCurrentPath);
?>

<aside class="col-md-3 col-lg-2 d-md-block bg-white shadow-sm app-sidebar" id="sidebarMenu" aria-hidden="true">
    <div class="position-sticky">
        <div class="app-sidebar-head px-3">
            <div class="app-sidebar-user-label">
                <span class="app-sidebar-user-name"><?php echo htmlspecialchars($userRole); ?> Portal</span>
                <span class="app-sidebar-user-copy">Navigation</span>
            </div>
        </div>

        <nav class="app-sidebar-nav px-2" aria-label="Sidebar">
            <?php foreach ($sidebarGroups as $group): ?>
                <section class="app-sidebar-group">
                    <h2 class="sidebar-heading app-sidebar-heading px-3">
                        <span><?php echo htmlspecialchars($group['label']); ?></span>
                    </h2>
                    <ul class="nav flex-column mb-0">
                        <?php foreach ($group['links'] as $link): ?>
                            <?php $isActive = sidebar_link_is_active($link['patterns'], $relativeCurrentPath); ?>
                            <li class="nav-item">
                                <a
                                    class="nav-link app-sidebar-link <?php echo $isActive ? 'is-active' : ''; ?>"
                                    href="<?php echo htmlspecialchars(base_url($link['path'])); ?>"
                                    title="<?php echo htmlspecialchars($link['label']); ?>"
                                    aria-current="<?php echo $isActive ? 'page' : 'false'; ?>"
                                >
                                    <span class="app-sidebar-link-icon">
                                        <i class="<?php echo htmlspecialchars($link['icon']); ?>"></i>
                                    </span>
                                    <span class="app-sidebar-link-label"><?php echo htmlspecialchars($link['label']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </nav>

        <div class="app-sidebar-footer px-2 pb-3">
            <a
                class="nav-link app-sidebar-link <?php echo $accountSettingsActive ? 'is-active' : ''; ?>"
                href="<?php echo htmlspecialchars(base_url('account/settings')); ?>"
                title="Account Settings"
                aria-current="<?php echo $accountSettingsActive ? 'page' : 'false'; ?>"
            >
                <span class="app-sidebar-link-icon">
                    <i class="fa-solid fa-user-gear"></i>
                </span>
                <span class="app-sidebar-link-label">Account Settings</span>
            </a>

            <a href="<?php echo htmlspecialchars(base_url('logout')); ?>" class="btn btn-outline-danger w-100 fw-bold app-sidebar-logout" title="Logout">
                <span class="app-sidebar-link-icon">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </span>
                <span class="app-sidebar-link-label">Logout</span>
            </a>
        </div>
    </div>
</aside>
