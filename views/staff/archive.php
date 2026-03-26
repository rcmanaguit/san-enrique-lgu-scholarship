<?php require __DIR__ . '/../layouts/header.php'; ?>

<?php
$recordOnlyView = !empty($recordOnlyView);
$selectedSection = $selectedSection ?? 'profile';
$sectionBaseUrl = !empty($selectedProfileId) ? base_url('staff/master-record/' . (int) $selectedProfileId) : '';
$sectionLinks = [
    'profile' => 'Profile',
    'applications' => 'Applications',
    'documents' => 'Documents',
    'notes' => 'Notes',
    'timelines' => 'Timelines',
];
$activeFilters = [];
if ($search !== '') {
    $activeFilters[] = ['label' => 'Search', 'value' => $search];
}
if ($schoolYearFilter !== '') {
    $activeFilters[] = ['label' => 'School Year', 'value' => $schoolYearFilter];
}
if ($semesterFilter !== '') {
    $activeFilters[] = ['label' => 'Semester', 'value' => $semesterFilter];
}
if ($barangayFilter !== '') {
    $activeFilters[] = ['label' => 'Barangay', 'value' => $barangayFilter];
}
if ($schoolTypeFilter !== '') {
    $activeFilters[] = ['label' => 'School Type', 'value' => $schoolTypeFilter];
}
if ($applicationTypeFilter !== '') {
    $activeFilters[] = ['label' => 'Applicant Type', 'value' => $applicationTypeFilter];
}
if ($statusFilter !== '') {
    $activeFilters[] = ['label' => 'Latest Status', 'value' => str_replace('_', ' ', $statusFilter)];
}
if ($lifecycleFilter !== '') {
    $activeFilters[] = ['label' => 'Record Status', 'value' => $lifecycleFilter];
}

$calculateApplicantAge = static function ($value): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return 'N/A';
    }

    try {
        return (string) ((new DateTimeImmutable($normalized))->diff(new DateTimeImmutable('today'))->y);
    } catch (Throwable $exception) {
        return 'N/A';
    }
};
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Case Folder</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-address-card me-2"></i><?php echo $recordOnlyView ? 'Master Record' : 'Records'; ?></h1>
                </div>
            </section>

            <?php if (!$recordOnlyView): ?>
            <section class="app-surface mb-4">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Filters</h2>
                    </div>
                </div>
                <div class="app-surface-body">
                    <form method="GET" action="<?php echo htmlspecialchars(base_url('staff/archive')); ?>" class="row g-3 align-items-end" data-live-submit data-live-submit-delay="300">
                        <div class="col-lg-4 col-xl-3">
                            <label class="form-label fw-bold">Search</label>
                            <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Name, SELGU-APP-YYYY-00001, school, barangay, phone, or email">
                        </div>
                        <div class="col-md-3 col-lg-2 col-xl-1">
                            <label class="form-label fw-bold">School Year</label>
                            <select class="form-select" name="school_year">
                                <option value="">All school years</option>
                                <?php foreach ($schoolYearOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars((string) $option); ?>" <?php echo $schoolYearFilter === (string) $option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string) $option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2 col-xl-1">
                            <label class="form-label fw-bold">Semester</label>
                            <select class="form-select" name="semester">
                                <option value="">All semesters</option>
                                <?php foreach ($semesterOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $semesterFilter === $option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2 col-xl-2">
                            <label class="form-label fw-bold">Barangay</label>
                            <select class="form-select" name="barangay">
                                <option value="">All barangays</option>
                                <?php foreach ($barangayOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $barangayFilter === $option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2 col-xl-1">
                            <label class="form-label fw-bold">School Type</label>
                            <select class="form-select" name="school_type">
                                <option value="">All school types</option>
                                <?php foreach ($schoolTypeOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $schoolTypeFilter === $option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2 col-xl-1">
                            <label class="form-label fw-bold">Applicant Type</label>
                            <select class="form-select" name="application_type">
                                <option value="">All types</option>
                                <?php foreach ($applicationTypeOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $applicationTypeFilter === $option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2 col-xl-1">
                            <label class="form-label fw-bold">Latest Status</label>
                            <select class="form-select" name="status">
                                <option value="">All statuses</option>
                                <?php foreach ($statusOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $statusFilter === $option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $option)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2 col-xl-1">
                            <label class="form-label fw-bold">Record Status</label>
                            <select class="form-select" name="lifecycle">
                                <option value="">All records</option>
                                <?php foreach ($lifecycleOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $lifecycleFilter === $option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-lg-2 col-xl-1 d-grid">
                            <a href="<?php echo htmlspecialchars(base_url('staff/archive')); ?>" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
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
                                    <span class="app-active-filter-chip">
                                        <span><?php echo htmlspecialchars((string) $filterChip['label']); ?>: <?php echo htmlspecialchars((string) $filterChip['value']); ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="app-surface app-table-card mb-4">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Results</h2>
                    </div>
                    <span class="app-pill-badge"><i class="fa-solid fa-user-group"></i><?php echo count($searchResults); ?> Found</span>
                </div>
                <div class="p-0">
                    <?php if ($searchResults === []): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-state-icon"><i class="fa-solid fa-folder-open"></i></div>
                            <h3 class="app-empty-state-title">No scholars matched the current search</h3>
                            <p class="app-empty-state-copy">Try a broader keyword or remove one of the filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-md-none app-mobile-record-grid">
                            <?php foreach ($searchResults as $result): ?>
                                <?php $recordUrl = base_url('staff/master-record/' . (int) ($result['profile_id'] ?? 0)); ?>
                                <?php
                                $resultStatusClass = match ((string) ($result['latest_status'] ?? '')) {
                                    'Submitted', 'Initial_Review' => 'app-status-badge app-status-review',
                                    'Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue' => 'app-status-badge app-status-correction',
                                    'For_Interview' => 'app-status-badge app-status-interview',
                                    'Eligible_Awaiting_SOA', 'SOA_Under_Review' => 'app-status-badge app-status-soa',
                                    'Approved_Pending_Payroll' => 'app-status-badge app-status-payout',
                                    'Approved_Finished' => 'app-status-badge app-status-complete',
                                    'Not_Eligible', 'Forfeited' => 'app-status-badge app-status-closed',
                                    default => 'app-status-badge app-status-default',
                                };
                                $lifecycle = (string) ($result['latest_lifecycle'] ?? 'Current');
                                $lifecycleClass = $lifecycle === 'Archived'
                                    ? 'app-status-badge app-status-closed'
                                    : ($lifecycle === 'Completed' ? 'app-status-badge app-status-complete' : 'app-status-badge app-status-review');
                                ?>
                                <article
                                    class="app-mobile-record-card app-clickable-card"
                                    role="link"
                                    tabindex="0"
                                    aria-label="Open record for <?php echo htmlspecialchars((string) (($result['last_name'] ?? '') . ', ' . ($result['first_name'] ?? ''))); ?>"
                                    onclick="window.location.href='<?php echo htmlspecialchars($recordUrl, ENT_QUOTES); ?>'"
                                    onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href='<?php echo htmlspecialchars($recordUrl, ENT_QUOTES); ?>'; }">
                                    <div class="app-list-item-head">
                                        <div>
                                            <h3 class="app-list-item-title mb-1"><?php echo htmlspecialchars((string) (($result['last_name'] ?? '') . ', ' . ($result['first_name'] ?? ''))); ?></h3>
                                            <div class="app-list-item-meta"><?php echo htmlspecialchars((string) ($result['address_barangay'] ?? '')); ?></div>
                                            <div class="app-list-item-meta"><?php echo htmlspecialchars((string) ($result['school_name'] ?? '')); ?></div>
                                        </div>
                                    </div>
                                    <div class="app-mobile-record-section">
                                        <span class="app-mobile-record-label">Contact</span>
                                        <div class="app-mobile-record-value"><?php echo htmlspecialchars((string) ($result['phone_number'] ?? '')); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($result['email'] ?? '')); ?></div>
                                    </div>
                                    <div class="app-meta-chips">
                                        <span class="<?php echo htmlspecialchars($resultStatusClass); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', (string) ($result['latest_status'] ?? 'No Application'))); ?></span>
                                        <span class="<?php echo htmlspecialchars($lifecycleClass); ?>"><?php echo htmlspecialchars($lifecycle); ?></span>
                                        <span class="app-meta-chip"><?php echo (int) ($result['total_applications'] ?? 0); ?> application<?php echo (int) ($result['total_applications'] ?? 0) === 1 ? '' : 's'; ?></span>
                                    </div>
                                    <div class="app-mobile-record-section">
                                        <span class="app-mobile-record-label">Indexed Record</span>
                                        <div class="app-mobile-record-value">
                                            <?php if (!empty($result['latest_application_id'])): ?>
                                                <?php echo htmlspecialchars('SELGU-APP-' . date('Y', strtotime((string) ($result['latest_application_at'] ?? 'now'))) . '-' . str_pad((string) $result['latest_application_id'], 5, '0', STR_PAD_LEFT)); ?>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted"><?php echo htmlspecialchars(trim((string) (($result['latest_school_year'] ?? '') . ' | ' . ($result['latest_semester'] ?? '')))); ?></div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Scholar</th>
                                        <th>Contact</th>
                                        <th>School</th>
                                        <th>Indexed Record</th>
                                        <th>Latest Status</th>
                                        <th>Record Status</th>
                                        <th>Total Applications</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($searchResults as $result): ?>
                                        <?php $recordUrl = base_url('staff/master-record/' . (int) ($result['profile_id'] ?? 0)); ?>
                                        <?php $isSelectedRecord = (int) ($result['profile_id'] ?? 0) === (int) ($selectedProfileId ?? 0); ?>
                                        <?php
                                        $resultStatusClass = match ((string) ($result['latest_status'] ?? '')) {
                                            'Submitted', 'Initial_Review' => 'app-status-badge app-status-review',
                                            'Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue' => 'app-status-badge app-status-correction',
                                            'For_Interview' => 'app-status-badge app-status-interview',
                                            'Eligible_Awaiting_SOA', 'SOA_Under_Review' => 'app-status-badge app-status-soa',
                                            'Approved_Pending_Payroll' => 'app-status-badge app-status-payout',
                                            'Approved_Finished' => 'app-status-badge app-status-complete',
                                            'Not_Eligible', 'Forfeited' => 'app-status-badge app-status-closed',
                                            default => 'app-status-badge app-status-default',
                                        };
                                        ?>
                                        <tr
                                            class="<?php echo $isSelectedRecord ? 'app-board-row-selected ' : ''; ?>app-board-row-link"
                                            role="link"
                                            tabindex="0"
                                            aria-label="Open record for <?php echo htmlspecialchars((string) (($result['last_name'] ?? '') . ', ' . ($result['first_name'] ?? ''))); ?>"
                                            onclick="window.location.href='<?php echo htmlspecialchars($recordUrl, ENT_QUOTES); ?>'"
                                            onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href='<?php echo htmlspecialchars($recordUrl, ENT_QUOTES); ?>'; }">
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars((string) (($result['last_name'] ?? '') . ', ' . ($result['first_name'] ?? ''))); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($result['address_barangay'] ?? '')); ?></div>
                                            </td>
                                            <td class="small">
                                                <div><?php echo htmlspecialchars((string) ($result['phone_number'] ?? '')); ?></div>
                                                <div class="text-muted"><?php echo htmlspecialchars((string) ($result['email'] ?? '')); ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($result['school_name'] ?? '')); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($result['course'] ?? '')); ?></div>
                                            </td>
                                            <td class="small">
                                                <div class="fw-semibold">
                                                    <?php if (!empty($result['latest_application_id'])): ?>
                                                        <?php echo htmlspecialchars('SELGU-APP-' . date('Y', strtotime((string) ($result['latest_application_at'] ?? 'now'))) . '-' . str_pad((string) $result['latest_application_id'], 5, '0', STR_PAD_LEFT)); ?>
                                                    <?php else: ?>
                                                        --
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted">
                                                    <?php echo htmlspecialchars(trim((string) (($result['latest_school_year'] ?? '') . ' | ' . ($result['latest_semester'] ?? '')))); ?>
                                                </div>
                                                <div class="text-muted">
                                                    <?php echo htmlspecialchars((string) ($result['latest_application_type'] ?? '')); ?>
                                                    <?php if (!empty($result['latest_school_type'])): ?>
                                                        | <?php echo htmlspecialchars((string) $result['latest_school_type']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><span class="<?php echo htmlspecialchars($resultStatusClass); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', (string) ($result['latest_status'] ?? 'No Application'))); ?></span></td>
                                            <td>
                                                <?php
                                                $lifecycle = (string) ($result['latest_lifecycle'] ?? 'Current');
                                                $lifecycleClass = $lifecycle === 'Archived'
                                                    ? 'app-status-badge app-status-closed'
                                                    : ($lifecycle === 'Completed' ? 'app-status-badge app-status-complete' : 'app-status-badge app-status-review');
                                                ?>
                                                <span class="<?php echo htmlspecialchars($lifecycleClass); ?>"><?php echo htmlspecialchars($lifecycle); ?></span>
                                            </td>
                                            <td><?php echo (int) ($result['total_applications'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($selectedRecord): ?>
                <section class="app-surface mb-4 app-review-command-bar">
                    <div class="app-surface-body">
                        <div class="app-review-command-layout">
                            <div>
                                <div class="app-stat-kicker">Selected Record</div>
                                <div class="fw-bold text-dark mb-1"><?php echo htmlspecialchars((string) (($selectedRecord['last_name'] ?? '') . ', ' . ($selectedRecord['first_name'] ?? ''))); ?></div>
                                <div class="app-meta-chips">
                                    <span class="app-meta-chip"><?php echo (int) count($applicationHistory); ?> application<?php echo count($applicationHistory) === 1 ? '' : 's'; ?></span>
                                    <span class="app-meta-chip"><?php echo (int) count($documentHistory); ?> document<?php echo count($documentHistory) === 1 ? '' : 's'; ?></span>
                                    <span class="app-meta-chip"><?php echo (int) count($caseNotes); ?> note<?php echo count($caseNotes) === 1 ? '' : 's'; ?></span>
                                </div>
                            </div>
                            <ul class="nav nav-pills app-review-tabs app-record-section-nav">
                                <?php foreach ($sectionLinks as $sectionKey => $sectionLabel): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $recordOnlyView && $selectedSection === $sectionKey ? 'active' : ''; ?>"
                                           href="<?php echo htmlspecialchars($recordOnlyView ? ($sectionBaseUrl . '?section=' . urlencode($sectionKey)) : ('#record-' . $sectionKey)); ?>">
                                            <?php echo htmlspecialchars($sectionLabel); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </section>

                <div class="row g-4">
                    <?php if (!$recordOnlyView || $selectedSection === 'profile'): ?>
                    <div class="<?php echo $recordOnlyView ? 'col-12' : 'col-xl-4'; ?>">
                        <section class="app-surface mb-4" id="record-profile">
                            <div class="app-surface-header">
                                <div>
                                    <h2 class="app-surface-title">Scholar Profile</h2>
                                </div>
                            </div>
                            <div class="app-surface-body">
                                <div class="mb-3">
                                    <div class="fw-bold fs-5"><?php echo htmlspecialchars((string) (($selectedRecord['last_name'] ?? '') . ', ' . ($selectedRecord['first_name'] ?? ''))); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($selectedRecord['email'] ?? '')); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($selectedRecord['phone_number'] ?? '')); ?></div>
                                </div>
                                <dl class="app-definition-list">
                                    <div><dt>Application Type</dt><dd><?php echo htmlspecialchars((string) ($latestApplication['application_type'] ?? 'N/A')); ?></dd></div>
                                    <div><dt>Record Status</dt><dd><?php echo htmlspecialchars((string) ($latestApplication['record_lifecycle'] ?? 'N/A')); ?></dd></div>
                                    <div><dt>School</dt><dd><?php echo htmlspecialchars((string) ($selectedRecord['school_name'] ?? '')); ?></dd></div>
                                    <div><dt>Course</dt><dd><?php echo htmlspecialchars((string) ($selectedRecord['course'] ?? '')); ?></dd></div>
                                    <div><dt>School Type</dt><dd><?php echo htmlspecialchars((string) ($selectedRecord['school_type'] ?? '')); ?></dd></div>
                                    <div><dt>Address</dt><dd><?php echo htmlspecialchars((string) (($selectedRecord['address_line'] ?? '') . ', ' . ($selectedRecord['address_barangay'] ?? '') . ', San Enrique, Negros Occidental')); ?></dd></div>
                                    <div><dt>Birth Date</dt><dd><?php echo htmlspecialchars((string) ($selectedRecord['date_of_birth'] ?? '')); ?></dd></div>
                                    <div><dt>Applicant Age</dt><dd><?php echo htmlspecialchars($calculateApplicantAge($selectedRecord['date_of_birth'] ?? '')); ?></dd></div>
                                    <div><dt>Place of Birth</dt><dd><?php echo htmlspecialchars((string) ($selectedRecord['place_of_birth'] ?? '')); ?></dd></div>
                                    <div><dt>Mother</dt><dd><?php echo htmlspecialchars((string) ($selectedRecord['mother_name'] ?? '')); ?><?php echo !empty($selectedRecord['mother_age']) ? ' | Age: ' . htmlspecialchars((string) $selectedRecord['mother_age']) : ''; ?></dd></div>
                                    <div><dt>Father</dt><dd><?php echo htmlspecialchars((string) ($selectedRecord['father_name'] ?? '')); ?><?php echo !empty($selectedRecord['father_age']) ? ' | Age: ' . htmlspecialchars((string) $selectedRecord['father_age']) : ''; ?></dd></div>
                                    <div><dt>Account Verified</dt><dd><?php echo ((int) ($selectedRecord['is_verified'] ?? 0) === 1) ? 'Yes' : 'No'; ?></dd></div>
                                </dl>
                            </div>
                        </section>

                        <?php if (!$recordOnlyView): ?>
                        <section class="app-surface">
                            <div class="app-surface-header">
                                <div>
                                    <h2 class="app-surface-title">Latest Notifications</h2>
                                </div>
                            </div>
                            <div class="app-surface-body">
                                <?php if ($notificationHistory === []): ?>
                                    <div class="app-empty-state py-4">
                                        <div class="app-empty-state-icon"><i class="fa-regular fa-bell"></i></div>
                                        <h3 class="app-empty-state-title">No notifications yet</h3>
                                        <p class="app-empty-state-copy">This scholar has not received any system notifications.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="app-note-list">
                                        <?php foreach ($notificationHistory as $notification): ?>
                                            <div class="app-note-item">
                                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($notification['title'] ?? 'Notification')); ?></div>
                                                <div class="small text-muted mb-1"><?php echo htmlspecialchars((string) ($notification['message'] ?? '')); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($notification['created_at'] ?? '')); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!$recordOnlyView || $selectedSection !== 'profile'): ?>
                    <div class="<?php echo $recordOnlyView ? 'col-12' : 'col-xl-8'; ?>">
                        <?php if (!$recordOnlyView || $selectedSection === 'applications'): ?>
                        <section class="app-surface app-table-card mb-4" id="record-applications">
                            <div class="app-surface-header">
                                <div>
                                    <h2 class="app-surface-title">Application History</h2>
                                </div>
                            </div>
                            <div class="p-0">
                                <?php if ($applicationHistory === []): ?>
                                    <div class="app-empty-state">
                                        <div class="app-empty-state-icon"><i class="fa-solid fa-file-lines"></i></div>
                                        <h3 class="app-empty-state-title">No application history found</h3>
                                        <p class="app-empty-state-copy">This scholar does not have any saved application record yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="d-md-none app-mobile-record-grid">
                                        <?php foreach ($applicationHistory as $application): ?>
                                            <?php
                                            $recordLifecycle = (string) ($application['record_lifecycle'] ?? 'Current');
                                            $applicationRecordUrl = base_url('staff/verify-documents/' . (int) ($application['id'] ?? 0));
                                            $lifecycleClass = $recordLifecycle === 'Archived'
                                                ? 'app-status-badge app-status-closed'
                                                : ($recordLifecycle === 'Completed' ? 'app-status-badge app-status-complete' : 'app-status-badge app-status-review');
                                            $statusClass = match ((string) ($application['status'] ?? '')) {
                                                'Submitted', 'Initial_Review' => 'app-status-badge app-status-review',
                                                'Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue' => 'app-status-badge app-status-correction',
                                                'For_Interview' => 'app-status-badge app-status-interview',
                                                'Eligible_Awaiting_SOA', 'SOA_Under_Review' => 'app-status-badge app-status-soa',
                                                'Approved_Pending_Payroll' => 'app-status-badge app-status-payout',
                                                'Approved_Finished' => 'app-status-badge app-status-complete',
                                                'Not_Eligible', 'Forfeited' => 'app-status-badge app-status-closed',
                                                default => 'app-status-badge app-status-default',
                                            };
                                            $canArchive = in_array((string) ($application['status'] ?? ''), ['Approved_Finished', 'Not_Eligible', 'Forfeited'], true);
                                            $redirectQuery = '?' . http_build_query([
                                                'q' => $search,
                                                'status' => $statusFilter,
                                                'lifecycle' => $lifecycleFilter,
                                                'school_year' => $schoolYearFilter,
                                                'semester' => $semesterFilter,
                                                'school_type' => $schoolTypeFilter,
                                                'application_type' => $applicationTypeFilter,
                                                'barangay' => $barangayFilter,
                                                'profile_id' => (int) ($selectedProfileId ?? 0),
                                            ]);
                                            ?>
                                            <article class="app-mobile-record-card">
                                                <div class="app-list-item-head">
                                                    <div>
                                                        <h3 class="app-list-item-title mb-1"><?php echo htmlspecialchars('SELGU-APP-' . date('Y', strtotime((string) ($application['created_at'] ?? 'now'))) . '-' . str_pad((string) ($application['id'] ?? 0), 5, '0', STR_PAD_LEFT)); ?></h3>
                                                        <div class="app-list-item-meta"><?php echo htmlspecialchars((string) (($application['school_year'] ?? '') . ' | ' . ($application['semester'] ?? ''))); ?></div>
                                                        <div class="app-list-item-meta"><?php echo htmlspecialchars((string) ($application['application_type'] ?? '')); ?></div>
                                                    </div>
                                                    <a href="<?php echo htmlspecialchars($applicationRecordUrl); ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                                </div>
                                                <div class="app-meta-chips">
                                                    <span class="<?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', (string) ($application['status'] ?? ''))); ?></span>
                                                    <span class="<?php echo htmlspecialchars($lifecycleClass); ?>"><?php echo htmlspecialchars($recordLifecycle); ?></span>
                                                    <span class="app-meta-chip"><?php echo !empty($application['final_grant_amount']) ? 'PHP ' . number_format((float) $application['final_grant_amount'], 2) : 'No grant yet'; ?></span>
                                                </div>
                                                <div class="app-mobile-record-section">
                                                    <span class="app-mobile-record-label">Interview</span>
                                                    <div class="app-mobile-record-value"><?php echo !empty($application['interview_batch_name']) ? htmlspecialchars((string) ($application['interview_batch_name'] . ' | ' . $application['interview_schedule'])) : 'Not scheduled'; ?></div>
                                                </div>
                                                <div class="app-mobile-record-section">
                                                    <span class="app-mobile-record-label">Payout</span>
                                                    <div class="app-mobile-record-value"><?php echo !empty($application['payout_batch_name']) ? htmlspecialchars((string) ($application['payout_batch_name'] . ' | ' . $application['payout_schedule'])) : 'Not scheduled'; ?></div>
                                                </div>
                                                <div class="app-actions-row">
                                                    <?php if (!empty($application['interview_schedule'])): ?>
                                                        <a href="<?php echo htmlspecialchars(base_url('staff/print-notice/' . (int) $application['id'] . '/interview')); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Interview Notice</a>
                                                    <?php endif; ?>
                                                    <?php if (($application['status'] ?? '') === 'Not_Eligible'): ?>
                                                        <a href="<?php echo htmlspecialchars(base_url('staff/print-notice/' . (int) $application['id'] . '/disqualification')); ?>" target="_blank" class="btn btn-sm btn-outline-danger">Disqualification Notice</a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($application['payout_schedule'])): ?>
                                                        <a href="<?php echo htmlspecialchars(base_url('staff/print-notice/' . (int) $application['id'] . '/payout')); ?>" target="_blank" class="btn btn-sm btn-outline-success">Payout Notice</a>
                                                    <?php endif; ?>
                                                    <?php if ($recordLifecycle === 'Archived'): ?>
                                                            <form method="POST" action="<?php echo htmlspecialchars(base_url('staff/archive/toggle')); ?>" class="w-100">
                                                                <?php echo csrf_input(); ?>
                                                            <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                                                            <input type="hidden" name="archive_action" value="unarchive">
                                                            <input type="hidden" name="redirect_query" value="<?php echo htmlspecialchars($redirectQuery); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Restore</button>
                                                        </form>
                                                    <?php elseif ($canArchive): ?>
                                                            <form method="POST" action="<?php echo htmlspecialchars(base_url('staff/archive/toggle')); ?>" class="w-100">
                                                                <?php echo csrf_input(); ?>
                                                            <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                                                            <input type="hidden" name="archive_action" value="archive">
                                                            <input type="hidden" name="redirect_query" value="<?php echo htmlspecialchars($redirectQuery); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-dark w-100">Archive</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="table-responsive d-none d-md-block">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Application</th>
                                                    <th>Status</th>
                                                    <th>Record Status</th>
                                                    <th>Interview</th>
                                                    <th>Payout</th>
                                                    <th>Amount</th>
                                                    <th class="text-end">Archive</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($applicationHistory as $application): ?>
                                                    <?php
                                                    $recordLifecycle = (string) ($application['record_lifecycle'] ?? 'Current');
                                                    $applicationRecordUrl = base_url('staff/verify-documents/' . (int) ($application['id'] ?? 0));
                                                    $lifecycleClass = $recordLifecycle === 'Archived'
                                                        ? 'app-status-badge app-status-closed'
                                                        : ($recordLifecycle === 'Completed' ? 'app-status-badge app-status-complete' : 'app-status-badge app-status-review');
                                                    $statusClass = match ((string) ($application['status'] ?? '')) {
                                                        'Submitted', 'Initial_Review' => 'app-status-badge app-status-review',
                                                        'Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue' => 'app-status-badge app-status-correction',
                                                        'For_Interview' => 'app-status-badge app-status-interview',
                                                        'Eligible_Awaiting_SOA', 'SOA_Under_Review' => 'app-status-badge app-status-soa',
                                                        'Approved_Pending_Payroll' => 'app-status-badge app-status-payout',
                                                        'Approved_Finished' => 'app-status-badge app-status-complete',
                                                        'Not_Eligible', 'Forfeited' => 'app-status-badge app-status-closed',
                                                        default => 'app-status-badge app-status-default',
                                                    };
                                                    $canArchive = in_array((string) ($application['status'] ?? ''), ['Approved_Finished', 'Not_Eligible', 'Forfeited'], true);
                                                    $redirectQuery = '?' . http_build_query([
                                                        'q' => $search,
                                                        'status' => $statusFilter,
                                                        'lifecycle' => $lifecycleFilter,
                                                        'school_year' => $schoolYearFilter,
                                                        'semester' => $semesterFilter,
                                                        'school_type' => $schoolTypeFilter,
                                                        'application_type' => $applicationTypeFilter,
                                                        'barangay' => $barangayFilter,
                                                        'profile_id' => (int) ($selectedProfileId ?? 0),
                                                    ]);
                                                    ?>
                                                    <tr
                                                        class="app-board-row-link"
                                                        role="link"
                                                        tabindex="0"
                                                        aria-label="Open application record <?php echo htmlspecialchars('SELGU-APP-' . date('Y', strtotime((string) ($application['created_at'] ?? 'now'))) . '-' . str_pad((string) ($application['id'] ?? 0), 5, '0', STR_PAD_LEFT)); ?>"
                                                        onclick="if (!event.target.closest('a, button, input, select, textarea, form, label')) { window.location.href='<?php echo htmlspecialchars($applicationRecordUrl, ENT_QUOTES); ?>'; }"
                                                        onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, input, select, textarea, form, label')) { event.preventDefault(); window.location.href='<?php echo htmlspecialchars($applicationRecordUrl, ENT_QUOTES); ?>'; }">
                                                        <td>
                                                            <div class="fw-bold">App ID <?php echo (int) $application['id']; ?></div>
                                                            <div class="small text-muted">
                                                                <?php echo htmlspecialchars('Application No. SELGU-APP-' . date('Y', strtotime((string) ($application['created_at'] ?? 'now'))) . '-' . str_pad((string) ($application['id'] ?? 0), 5, '0', STR_PAD_LEFT)); ?>
                                                            </div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars((string) (($application['school_year'] ?? '') . ' | ' . ($application['semester'] ?? ''))); ?></div>
                                                            <div class="small text-muted">
                                                                <?php echo htmlspecialchars((string) ($application['application_type'] ?? '')); ?>
                                                                <?php if (!empty($selectedRecord['school_type'])): ?>
                                                                    | <?php echo htmlspecialchars((string) ($selectedRecord['school_type'] ?? '')); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="d-flex gap-2 flex-wrap mt-2">
                                                                <?php if (!empty($application['interview_schedule'])): ?>
                                                                    <a href="<?php echo htmlspecialchars(base_url('staff/print-notice/' . (int) $application['id'] . '/interview')); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Interview Notice</a>
                                                                <?php endif; ?>
                                                                <?php if (($application['status'] ?? '') === 'Not_Eligible'): ?>
                                                                    <a href="<?php echo htmlspecialchars(base_url('staff/print-notice/' . (int) $application['id'] . '/disqualification')); ?>" target="_blank" class="btn btn-sm btn-outline-danger">Disqualification Notice</a>
                                                                <?php endif; ?>
                                                                <?php if (!empty($application['payout_schedule'])): ?>
                                                                    <a href="<?php echo htmlspecialchars(base_url('staff/print-notice/' . (int) $application['id'] . '/payout')); ?>" target="_blank" class="btn btn-sm btn-outline-success">Payout Notice</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', (string) ($application['status'] ?? ''))); ?></span>
                                                            <div class="small text-muted mt-1"><?php echo htmlspecialchars((string) ($application['created_at'] ?? '')); ?></div>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo htmlspecialchars($lifecycleClass); ?>"><?php echo htmlspecialchars($recordLifecycle); ?></span>
                                                            <?php if (!empty($application['archived_at'])): ?>
                                                                <div class="small text-muted mt-1"><?php echo htmlspecialchars((string) $application['archived_at']); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="small text-muted">
                                                            <?php echo !empty($application['interview_batch_name']) ? htmlspecialchars((string) ($application['interview_batch_name'] . ' | ' . $application['interview_schedule'])) : 'Not scheduled'; ?>
                                                        </td>
                                                        <td class="small text-muted">
                                                            <?php echo !empty($application['payout_batch_name']) ? htmlspecialchars((string) ($application['payout_batch_name'] . ' | ' . $application['payout_schedule'])) : 'Not scheduled'; ?>
                                                        </td>
                                                        <td class="fw-semibold text-success"><?php echo !empty($application['final_grant_amount']) ? 'PHP ' . number_format((float) $application['final_grant_amount'], 2) : '--'; ?></td>
                                                        <td class="text-end">
                                                            <?php if ($recordLifecycle === 'Archived'): ?>
                                                                <form method="POST" action="<?php echo htmlspecialchars(base_url('staff/archive/toggle')); ?>" class="d-inline">
                                                                    <?php echo csrf_input(); ?>
                                                                    <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                                                                    <input type="hidden" name="archive_action" value="unarchive">
                                                                    <input type="hidden" name="redirect_query" value="<?php echo htmlspecialchars($redirectQuery); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Restore</button>
                                                                </form>
                                                            <?php elseif ($canArchive): ?>
                                                                <form method="POST" action="<?php echo htmlspecialchars(base_url('staff/archive/toggle')); ?>" class="d-inline">
                                                                    <?php echo csrf_input(); ?>
                                                                    <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                                                                    <input type="hidden" name="archive_action" value="archive">
                                                                    <input type="hidden" name="redirect_query" value="<?php echo htmlspecialchars($redirectQuery); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-dark">Archive</button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="small text-muted">Current record</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                        <?php endif; ?>

                        <?php if (!$recordOnlyView || $selectedSection === 'documents'): ?>
                        <section class="app-surface app-table-card mb-4" id="record-documents">
                            <div class="app-surface-header">
                                <div>
                                    <h2 class="app-surface-title">Document History</h2>
                                </div>
                            </div>
                            <div class="p-0">
                                <?php if ($documentHistory === []): ?>
                                    <div class="app-empty-state">
                                        <div class="app-empty-state-icon"><i class="fa-regular fa-folder"></i></div>
                                        <h3 class="app-empty-state-title">No documents found</h3>
                                        <p class="app-empty-state-copy">There are no uploaded documents linked to this scholar yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Document</th>
                                                    <th>Application Term</th>
                                                    <th>Status</th>
                                                    <th>File Size</th>
                                                    <th>Remarks</th>
                                                    <th>Updated</th>
                                                    <th class="text-end">Preview</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($documentHistory as $document): ?>
                                                    <?php
                                                    $documentStatusClass = match ((string) ($document['status'] ?? '')) {
                                                        'Verified' => 'app-status-badge app-status-complete',
                                                        'Rejected' => 'app-status-badge app-status-correction',
                                                        default => 'app-status-badge app-status-review',
                                                    };
                                                    ?>
                                                    <tr>
                                                        <td class="fw-semibold"><?php echo htmlspecialchars(document_type_label((string) ($document['document_type'] ?? ''))); ?></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) (($document['school_year'] ?? '') . ' | ' . ($document['semester'] ?? ''))); ?></td>
                                                        <td><span class="<?php echo htmlspecialchars($documentStatusClass); ?>"><?php echo htmlspecialchars((string) ($document['status'] ?? '')); ?></span></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) ($document['file_size_label'] ?? 'N/A')); ?></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) (($document['rejection_remarks'] ?? '') !== '' ? $document['rejection_remarks'] : '--')); ?></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) ($document['updated_at'] ?? '')); ?></td>
                                                        <td class="text-end">
                                                            <a href="<?php echo htmlspecialchars(base_url('staff/document-preview/' . (int) ($document['id'] ?? 0))); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                                Open File
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <?php if (!$recordOnlyView): ?>
                        <section class="app-surface app-table-card mb-4">
                            <div class="app-surface-header">
                                <div>
                                    <h2 class="app-surface-title">File History</h2>
                                </div>
                            </div>
                            <div class="p-0">
                                <?php if ($documentVersionHistory === []): ?>
                                    <div class="app-empty-state">
                                        <div class="app-empty-state-icon"><i class="fa-solid fa-code-branch"></i></div>
                                        <h3 class="app-empty-state-title">No document versions recorded yet</h3>
                                        <p class="app-empty-state-copy">Version history will appear here after the scholar uploads or resubmits requirements.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Document</th>
                                                    <th>Application Term</th>
                                                    <th>Version</th>
                                                    <th>Source</th>
                                                    <th>Status</th>
                                                    <th>File Size</th>
                                                    <th>Uploaded By</th>
                                                    <th>Remarks</th>
                                                    <th>Saved</th>
                                                    <th class="text-end">Preview</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($documentVersionHistory as $version): ?>
                                                    <?php
                                                    $versionStatusClass = match ((string) ($version['document_status'] ?? '')) {
                                                        'Verified' => 'app-status-badge app-status-complete',
                                                        'Rejected' => 'app-status-badge app-status-correction',
                                                        default => 'app-status-badge app-status-review',
                                                    };
                                                    ?>
                                                    <tr>
                                                        <td class="fw-semibold"><?php echo htmlspecialchars(document_type_label((string) ($version['document_type'] ?? ''))); ?></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) (($version['school_year'] ?? '') . ' | ' . ($version['semester'] ?? ''))); ?></td>
                                                        <td><span class="badge bg-primary-subtle text-primary-emphasis">v<?php echo (int) ($version['version_number'] ?? 0); ?></span></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) ($version['source_action'] ?? '')); ?></td>
                                                        <td><span class="<?php echo htmlspecialchars($versionStatusClass); ?>"><?php echo htmlspecialchars((string) ($version['document_status'] ?? '')); ?></span></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) ($version['file_size_label'] ?? 'N/A')); ?></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) ($version['uploader_role'] ?? 'Unknown')); ?></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) ($version['rejection_remarks'] ?? '')); ?></td>
                                                        <td class="small text-muted"><?php echo htmlspecialchars((string) ($version['created_at'] ?? '')); ?></td>
                                                        <td class="text-end">
                                                            <a href="<?php echo htmlspecialchars(base_url('staff/document-version-preview/' . (int) ($version['id'] ?? 0))); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                                Open File
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!$recordOnlyView || $selectedSection === 'notes'): ?>
                        <section class="app-surface" id="record-notes">
                            <div class="app-surface-header">
                                <div>
                                    <h2 class="app-surface-title">Staff Notes</h2>
                                </div>
                            </div>
                            <div class="app-surface-body">
                                <?php if ($caseNotes === []): ?>
                                    <div class="app-empty-state py-4">
                                        <div class="app-empty-state-icon"><i class="fa-regular fa-note-sticky"></i></div>
                                        <h3 class="app-empty-state-title">No staff notes recorded yet</h3>
                                        <p class="app-empty-state-copy">Use the verification pages to add staff notes for this scholar.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="app-note-list">
                                        <?php foreach ($caseNotes as $note): ?>
                                            <div class="app-note-item">
                                                <div class="app-note-meta"><?php echo htmlspecialchars((string) ($note['created_at'] ?? '')); ?> | <?php echo htmlspecialchars((string) ($note['author_role'] ?? 'System')); ?></div>
                                                <div class="small text-dark"><?php echo nl2br(htmlspecialchars((string) ($note['note_text'] ?? ''))); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                        <?php endif; ?>

                        <?php if (!$recordOnlyView || $selectedSection === 'timelines'): ?>
                        <section class="app-surface mt-4" id="record-timelines">
                            <div class="app-surface-header">
                                <div>
                                    <h2 class="app-surface-title">Application Timelines</h2>
                                </div>
                            </div>
                            <div class="app-surface-body">
                                <?php if ($applicationTimelines === []): ?>
                                    <div class="app-empty-state py-4">
                                        <div class="app-empty-state-icon"><i class="fa-solid fa-timeline"></i></div>
                                        <h3 class="app-empty-state-title">No updates yet</h3>
                                        <p class="app-empty-state-copy">Updates will appear here once this scholar has saved application activity.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="d-grid gap-4">
                                        <?php foreach ($applicationTimelines as $applicationId => $timelineGroup): ?>
                                            <?php
                                            $timelineApplication = $timelineGroup['application'] ?? [];
                                            $timelineCreatedAt = (string) ($timelineApplication['created_at'] ?? 'now');
                                            $timelineApplicationNumber = 'SELGU-APP-' . date('Y', strtotime($timelineCreatedAt)) . '-' . str_pad((string) $applicationId, 5, '0', STR_PAD_LEFT);
                                            ?>
                                            <section class="application-section-card">
                                                <div class="application-section-head">
                                                    <div>
                                                        <h6 class="application-section-title mb-1"><?php echo htmlspecialchars($timelineApplicationNumber); ?></h6>
                                                        <p class="application-section-copy mb-0">
                                                            <?php echo htmlspecialchars((string) trim((string) (($timelineApplication['school_year'] ?? '') . ' | ' . ($timelineApplication['semester'] ?? '')))); ?>
                                                            <?php if (!empty($timelineApplication['status'])): ?>
                                                                | <?php echo htmlspecialchars(str_replace('_', ' ', (string) ($timelineApplication['status'] ?? 'Unknown'))); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <?php if (($timelineGroup['entries'] ?? []) === []): ?>
                                                    <div class="app-empty-state py-4">
                                                        <div class="app-empty-state-icon"><i class="fa-solid fa-timeline"></i></div>
                                                        <h3 class="app-empty-state-title">No updates yet</h3>
                                                        <p class="app-empty-state-copy">This application has no saved activity yet.</p>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="app-case-timeline">
                                                        <?php foreach ($timelineGroup['entries'] as $timelineEntry): ?>
                                                            <article class="app-case-timeline-item">
                                                                <div class="app-case-timeline-rail">
                                                                    <span class="badge rounded-pill <?php echo htmlspecialchars((string) ($timelineEntry['badge_class'] ?? 'text-bg-secondary')); ?> app-case-timeline-icon">
                                                                        <i class="fa-solid <?php echo htmlspecialchars((string) ($timelineEntry['icon'] ?? 'fa-circle')); ?>"></i>
                                                                    </span>
                                                                </div>
                                                                <div class="app-case-timeline-body">
                                                                    <div class="app-case-timeline-head">
                                                                        <div>
                                                                            <h3 class="app-case-timeline-title"><?php echo htmlspecialchars((string) ($timelineEntry['title'] ?? 'Timeline Entry')); ?></h3>
                                                                            <p class="app-case-timeline-meta mb-0">
                                                                                <?php echo htmlspecialchars((string) ($timelineEntry['time'] ?? '')); ?>
                                                                                <?php if (!empty($timelineEntry['actor'])): ?>
                                                                                    | <?php echo htmlspecialchars((string) $timelineEntry['actor']); ?>
                                                                                <?php endif; ?>
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <?php if (!empty($timelineEntry['details'])): ?>
                                                                        <div class="small text-dark mt-2"><?php echo nl2br(htmlspecialchars((string) $timelineEntry['details'])); ?></div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($timelineEntry['meta']) && is_array($timelineEntry['meta'])): ?>
                                                                        <dl class="app-definition-list app-case-timeline-data mt-3">
                                                                            <?php foreach ($timelineEntry['meta'] as $metaLabel => $metaValue): ?>
                                                                                <?php if ((string) $metaValue === ''): ?>
                                                                                    <?php continue; ?>
                                                                                <?php endif; ?>
                                                                                <div>
                                                                                    <dt><?php echo htmlspecialchars((string) $metaLabel); ?></dt>
                                                                                    <dd><?php echo htmlspecialchars(is_scalar($metaValue) ? (string) $metaValue : json_encode($metaValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></dd>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </dl>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                        <?php endif; ?>

                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
