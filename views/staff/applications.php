<?php require __DIR__ . '/../layouts/header.php'; ?>

<?php
$periodLabel = is_array($currentPeriod ?? null) && !empty($currentPeriod['school_year']) && !empty($currentPeriod['semester'])
    ? trim((string) $currentPeriod['school_year'] . ' | ' . (string) $currentPeriod['semester'])
    : 'No current application period configured';
$isCurrentPeriodOpen = is_array($currentPeriod ?? null) && (int) ($currentPeriod['is_open'] ?? 0) === 1;
$visibleQueues = ['under_review', 'needs_resubmission', 'for_interview', 'for_soa', 'ready_for_payout'];
$currentQueueConfig = $queueMap[$queueFilter] ?? ['label' => 'Applications', 'description' => 'Filtered application records'];
$activeFilters = [];

if ($search !== '') {
    $activeFilters[] = [
        'label' => 'Search',
        'value' => $search,
        'url' => base_url('staff/applications') . '?' . http_build_query(array_filter([
            'queue' => $queueFilter,
            'barangay' => $barangayFilter,
            'school_type' => $schoolTypeFilter,
            'application_type' => $applicationTypeFilter,
            'sort' => $sortFilter,
            'selected_id' => $selectedApplicationId ?? null,
        ])),
    ];
}

if ($barangayFilter !== '') {
    $activeFilters[] = [
        'label' => 'Barangay',
        'value' => $barangayFilter,
        'url' => base_url('staff/applications') . '?' . http_build_query(array_filter([
            'queue' => $queueFilter,
            'q' => $search,
            'school_type' => $schoolTypeFilter,
            'application_type' => $applicationTypeFilter,
            'sort' => $sortFilter,
            'selected_id' => $selectedApplicationId ?? null,
        ])),
    ];
}

if ($schoolTypeFilter !== '') {
    $activeFilters[] = [
        'label' => 'School Type',
        'value' => $schoolTypeFilter,
        'url' => base_url('staff/applications') . '?' . http_build_query(array_filter([
            'queue' => $queueFilter,
            'q' => $search,
            'barangay' => $barangayFilter,
            'application_type' => $applicationTypeFilter,
            'sort' => $sortFilter,
            'selected_id' => $selectedApplicationId ?? null,
        ])),
    ];
}

if ($applicationTypeFilter !== '') {
    $activeFilters[] = [
        'label' => 'Applicant Type',
        'value' => $applicationTypeFilter,
        'url' => base_url('staff/applications') . '?' . http_build_query(array_filter([
            'queue' => $queueFilter,
            'q' => $search,
            'barangay' => $barangayFilter,
            'school_type' => $schoolTypeFilter,
            'sort' => $sortFilter,
            'selected_id' => $selectedApplicationId ?? null,
        ])),
    ];
}
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <h1 class="app-page-title"><i class="fa-solid fa-table-list me-2"></i>Applications</h1>
                </div>
                <div class="app-page-header-actions">
                    <span class="app-pill-badge">
                        <i class="fa-solid fa-calendar-day"></i><?php echo htmlspecialchars($periodLabel); ?>
                    </span>
                    <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                        <a href="<?php echo htmlspecialchars(base_url('admin/settings')); ?>" class="btn btn-outline-primary">
                            <i class="fa-solid fa-gears me-2"></i>Period
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-body">
                    <div class="app-board-queue-grid">
                        <?php foreach ($visibleQueues as $queueKey): ?>
                            <?php if (!isset($queueMap[$queueKey])) { continue; } ?>
                            <?php $queueConfig = $queueMap[$queueKey]; ?>
                            <?php $isActiveQueue = $queueFilter === $queueKey; ?>
                            <?php
                            $accent = match ($queueKey) {
                                'under_review' => '#0d6efd',
                                'needs_resubmission' => '#dc3545',
                                'for_interview' => '#c58c00',
                                'for_soa' => '#0f87a3',
                                'ready_for_payout' => '#198754',
                                default => '#4f6b86',
                            };
                            ?>
                            <a class="app-stat-card app-queue-chip-card <?php echo $isActiveQueue ? 'is-active' : ''; ?>"
                                style="--stat-accent: <?php echo htmlspecialchars($accent); ?>;"
                                aria-current="<?php echo $isActiveQueue ? 'page' : 'false'; ?>"
                                aria-label="<?php echo htmlspecialchars((string) (($queueConfig['label'] ?? 'Applications') . ': ' . (int) ($queueCounts[$queueKey] ?? 0) . ' application' . ((int) ($queueCounts[$queueKey] ?? 0) === 1 ? '' : 's'))); ?>"
                                href="<?php echo htmlspecialchars(base_url('staff/applications') . '?' . http_build_query(array_filter([
                                    'queue' => $queueKey,
                                    'q' => $search,
                                    'barangay' => $barangayFilter,
                                    'school_type' => $schoolTypeFilter,
                                    'application_type' => $applicationTypeFilter,
                                    'sort' => $sortFilter,
                                ]))); ?>">
                                <div class="app-stat-card-body">
                                    <div>
                                        <div class="app-queue-chip-label"><?php echo htmlspecialchars((string) ($queueConfig['label'] ?? 'Applications')); ?></div>
                                        <div class="app-queue-chip-copy"><?php echo htmlspecialchars((string) ($queueConfig['description'] ?? '')); ?></div>
                                    </div>
                                    <span class="app-stat-icon app-queue-chip-count"><?php echo (int) ($queueCounts[$queueKey] ?? 0); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Filter List</h2>
                    </div>
                </div>
                <div class="app-surface-body">
                    <form method="GET" action="<?php echo htmlspecialchars(base_url('staff/applications')); ?>" class="row g-3 align-items-end" data-live-board-filters>
                        <input type="hidden" name="queue" value="<?php echo htmlspecialchars($queueFilter); ?>">
                        <input type="hidden" name="selected_id" value="<?php echo (int) ($selectedApplicationId ?? 0); ?>">
                        <div class="col-lg-3">
                            <label class="form-label fw-bold">Search</label>
                            <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="SELGU-APP-YYYY-00001, scholar name, school, or barangay">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Barangay</label>
                            <select class="form-select" name="barangay">
                                <option value="">All barangays</option>
                                <?php foreach ($barangayOptions as $barangayOption): ?>
                                    <option value="<?php echo htmlspecialchars($barangayOption); ?>" <?php echo $barangayFilter === $barangayOption ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($barangayOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">School Type</label>
                            <select class="form-select" name="school_type">
                                <option value="">All school types</option>
                                <?php foreach ($schoolTypeOptions as $schoolTypeOption): ?>
                                    <option value="<?php echo htmlspecialchars($schoolTypeOption); ?>" <?php echo $schoolTypeFilter === $schoolTypeOption ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($schoolTypeOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Applicant Type</label>
                            <select class="form-select" name="application_type">
                                <option value="">All types</option>
                                <?php foreach ($applicationTypeOptions as $applicationTypeOption): ?>
                                    <option value="<?php echo htmlspecialchars($applicationTypeOption); ?>" <?php echo $applicationTypeFilter === $applicationTypeOption ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($applicationTypeOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Sort</label>
                            <select class="form-select" name="sort">
                                <?php foreach ($sortChoices as $sortKey => $sortLabel): ?>
                                    <option value="<?php echo htmlspecialchars($sortKey); ?>" <?php echo $sortFilter === $sortKey ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sortLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <a href="<?php echo htmlspecialchars(base_url('staff/applications') . '?queue=' . urlencode($queueFilter)); ?>" class="btn btn-outline-secondary w-100">Clear</a>
                        </div>
                    </form>
                    <div class="app-filter-feedback mt-3" data-filter-feedback aria-live="polite"></div>
                </div>
            </section>

            <?php if ($activeFilters !== []): ?>
                <section class="app-surface mb-4">
                    <div class="app-surface-body py-3">
                        <div class="app-active-filter-row">
                            <div>
                                <h2 class="app-surface-title mb-1">Active Filters</h2>
                            </div>
                            <div class="app-active-filter-chips">
                                <?php foreach ($activeFilters as $filterChip): ?>
                                    <a href="<?php echo htmlspecialchars((string) $filterChip['url']); ?>" class="app-active-filter-chip">
                                        <span><?php echo htmlspecialchars((string) $filterChip['label']); ?>: <?php echo htmlspecialchars((string) $filterChip['value']); ?></span>
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="app-surface app-table-card">
                <div class="app-surface-header app-results-header">
                    <div>
                        <h2 class="app-surface-title"><?php echo htmlspecialchars((string) ($currentQueueConfig['label'] ?? 'Applications')); ?></h2>
                        <p class="app-surface-copy mb-0">
                            <?php echo (int) count($applications); ?> application<?php echo count($applications) === 1 ? '' : 's'; ?> match this view.
                            <?php if (!empty($currentQueueConfig['description'])): ?>
                                <?php echo htmlspecialchars((string) $currentQueueConfig['description']); ?>.
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="app-pill-badge"><i class="fa-solid fa-folder-tree"></i><?php echo count($applications); ?></span>
                </div>
                <div class="p-0">
                    <?php if ($applications === []): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                            <h3 class="app-empty-state-title"><?php echo htmlspecialchars((string) ($currentQueueConfig['empty_title'] ?? 'No applications in this queue')); ?></h3>
                            <p class="app-empty-state-copy"><?php echo htmlspecialchars((string) ($currentQueueConfig['empty_copy'] ?? 'Try a different queue or remove one of the filters to widen the search.')); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="d-md-none p-3">
                            <div class="app-list-stack">
                                <?php foreach ($applications as $application): ?>
                                    <?php $applicationRecordUrl = base_url('staff/verify-documents/' . (int) ($application['id'] ?? 0)); ?>
                                    <article
                                        class="app-list-item app-clickable-card"
                                        role="link"
                                        tabindex="0"
                                        aria-label="Open application <?php echo htmlspecialchars((string) ($application['application_number'] ?? '')); ?>"
                                        onclick="window.location.href='<?php echo htmlspecialchars($applicationRecordUrl, ENT_QUOTES); ?>'"
                                        onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href='<?php echo htmlspecialchars($applicationRecordUrl, ENT_QUOTES); ?>'; }">
                                        <div class="app-list-item-head">
                                            <div class="flex-grow-1">
                                                <h3 class="app-list-item-title mb-1"><?php echo htmlspecialchars((string) (($application['last_name'] ?? '') . ', ' . ($application['first_name'] ?? ''))); ?></h3>
                                                <p class="app-list-item-meta mb-0"><?php echo htmlspecialchars((string) ($application['application_number'] ?? '')); ?></p>
                                                <p class="app-list-item-meta mb-0"><?php echo htmlspecialchars(trim((string) (($application['school_year'] ?? '') . ' | ' . ($application['semester'] ?? '')))); ?></p>
                                            </div>
                                            <span class="<?php echo htmlspecialchars((string) (($application['status_badge']['class'] ?? 'app-status-badge app-status-default'))); ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', (string) ($application['status'] ?? ''))); ?>
                                            </span>
                                        </div>
                                        <div class="app-list-item-meta mt-2">
                                            <div><strong>Barangay:</strong> <?php echo htmlspecialchars((string) ($application['address_barangay'] ?? '')); ?></div>
                                            <div><strong>School:</strong> <?php echo htmlspecialchars((string) ($application['school_name'] ?? '')); ?></div>
                                            <?php if (!empty($application['school_type'])): ?>
                                                <div><strong>Type:</strong> <?php echo htmlspecialchars((string) $application['school_type']); ?></div>
                                            <?php endif; ?>
                                            <div><strong>Next:</strong> <?php echo htmlspecialchars((string) (($application['next_action']['title'] ?? 'Review this application'))); ?></div>
                                            <div class="mt-2 d-flex flex-wrap gap-2">
                                                <span class="<?php echo htmlspecialchars((string) (($application['urgency_label']['class'] ?? 'app-urgency-chip app-urgency-neutral'))); ?>">
                                                    <?php echo htmlspecialchars((string) (($application['urgency_label']['label'] ?? 'Needs review'))); ?>
                                                </span>
                                                <span class="app-meta-chip"><?php echo htmlspecialchars((string) ($application['updated_time_human'] ?? '')); ?></span>
                                            </div>
                                        </div>
                                        <div class="app-workflow-stepper compact mt-3">
                                            <?php foreach (($application['workflow_steps'] ?? []) as $step): ?>
                                                <?php $stepState = (string) ($step['state'] ?? 'upcoming'); ?>
                                                <div class="app-workflow-step app-workflow-step-<?php echo htmlspecialchars($stepState); ?>">
                                                    <span class="app-workflow-dot"></span>
                                                    <span class="app-workflow-text"><?php echo htmlspecialchars((string) ($step['short_label'] ?? $step['label'] ?? '')); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="d-none d-md-block p-3">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Application</th>
                                            <th>Full Name</th>
                                            <th>Urgency</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $application): ?>
                                            <?php $applicationRecordUrl = base_url('staff/verify-documents/' . (int) ($application['id'] ?? 0)); ?>
                                            <tr
                                                class="app-board-row-link"
                                                role="link"
                                                tabindex="0"
                                                aria-label="Open application <?php echo htmlspecialchars((string) ($application['application_number'] ?? '')); ?>"
                                                onclick="window.location.href='<?php echo htmlspecialchars($applicationRecordUrl, ENT_QUOTES); ?>'"
                                                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href='<?php echo htmlspecialchars($applicationRecordUrl, ENT_QUOTES); ?>'; }">
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars((string) ($application['application_number'] ?? '')); ?></div>
                                                    <div class="small text-muted">
                                                        <?php echo htmlspecialchars(trim((string) (($application['school_year'] ?? '') . ' | ' . ($application['semester'] ?? '')))); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars((string) (($application['last_name'] ?? '') . ', ' . ($application['first_name'] ?? ''))); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($application['school_name'] ?? '')); ?></div>
                                                </td>
                                                <td>
                                                    <span class="<?php echo htmlspecialchars((string) (($application['urgency_label']['class'] ?? 'app-urgency-chip app-urgency-neutral'))); ?>">
                                                        <?php echo htmlspecialchars((string) (($application['urgency_label']['label'] ?? 'Needs review'))); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-live-board-filters]');
    if (!form) {
        return;
    }

    let submitTimer = null;
    const feedback = document.querySelector('[data-filter-feedback]');
    const scheduleSubmit = function () {
        window.clearTimeout(submitTimer);
        submitTimer = window.setTimeout(function () {
            if (feedback) {
                feedback.textContent = 'Filtering applications...';
            }
            form.requestSubmit();
        }, 250);
    };

    form.querySelectorAll('select').forEach(function (field) {
        field.addEventListener('change', scheduleSubmit);
    });

    const searchInput = form.querySelector('input[name="q"]');
    if (searchInput) {
        searchInput.addEventListener('input', scheduleSubmit);
    }
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
