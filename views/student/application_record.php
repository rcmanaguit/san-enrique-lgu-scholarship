<?php
require __DIR__ . '/../layouts/header.php';

$na = static function ($value): string {
    $normalized = trim((string) $value);
    return $normalized === '' ? 'N/A' : $normalized;
};

$formatDateTime = static function ($value, string $fallback = 'N/A'): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return $fallback;
    }

    try {
        return (new DateTimeImmutable($normalized))->format('M d, Y h:i A');
    } catch (Throwable $exception) {
        return $normalized;
    }
};

$formatDate = static function ($value, string $fallback = 'N/A'): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return $fallback;
    }

    try {
        return (new DateTimeImmutable($normalized))->format('M d, Y');
    } catch (Throwable $exception) {
        return $normalized;
    }
};

$calculateApplicantAge = static function ($value): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return 'N/A';
    }

    try {
        $birthDate = new DateTimeImmutable($normalized);
        $today = new DateTimeImmutable('today');
        return (string) $birthDate->diff($today)->y;
    } catch (Throwable $exception) {
        return 'N/A';
    }
};

$applicationNumber = 'SELGU-APP-' . date('Y', strtotime((string) ($application['created_at'] ?? 'now'))) . '-' . str_pad((string) ($application['id'] ?? 0), 5, '0', STR_PAD_LEFT);
$applicantAge = $calculateApplicantAge($application['date_of_birth'] ?? '');
$statusLabel = str_replace('_', ' ', (string) ($application['status'] ?? 'Pending'));
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Student Record</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-folder-open me-2"></i>Submitted Application Record</h1>
                    <p class="app-page-subtitle">Review the submitted information, uploaded files, and timeline for this application.</p>
                </div>
                <div class="app-page-header-actions d-flex gap-2 flex-wrap">
                    <a href="<?php echo htmlspecialchars(base_url('student/history')); ?>" class="btn btn-outline-secondary">Back to My Applications</a>
                    <a href="<?php echo htmlspecialchars(base_url('student/print-form?id=' . (int) ($application['id'] ?? 0))); ?>" target="_blank" class="btn btn-primary">Preview Printable Form</a>
                </div>
            </section>

            <section class="app-surface mb-4">
                <div class="app-surface-body">
                    <div class="app-review-summary-top">
                        <div>
                            <div class="app-page-eyebrow mb-2">Application Record</div>
                            <h2 class="app-review-summary-title"><?php echo htmlspecialchars((string) (($application['last_name'] ?? '') . ', ' . ($application['first_name'] ?? ''))); ?></h2>
                            <div class="app-review-summary-meta">
                                <span><?php echo htmlspecialchars($applicationNumber); ?></span>
                                <span><?php echo htmlspecialchars($na($application['school_year'] ?? '') . ' | ' . $na($application['semester'] ?? '')); ?></span>
                                <span><?php echo htmlspecialchars($na($application['application_type'] ?? '')); ?></span>
                            </div>
                        </div>
                        <div class="app-review-summary-status">
                            <span class="app-pill-badge"><?php echo htmlspecialchars($statusLabel); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-surface">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Workspace</h2>
                        <p class="app-surface-copy">Use the tabs to inspect the saved application details.</p>
                    </div>
                </div>
                <div class="app-surface-body">
                    <ul class="nav nav-pills app-review-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#student-record-info" type="button">Info</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#student-record-documents" type="button">Files / Documents</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#student-record-timeline" type="button">Timeline</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="student-record-info">
                            <div class="row g-4">
                                <div class="col-xl-8">
                                    <section class="app-surface mb-4">
                                        <div class="app-surface-header">
                                            <div>
                                                <h2 class="app-surface-title">Application Summary</h2>
                                                <p class="app-surface-copy">Core details for this submitted scholarship record.</p>
                                            </div>
                                        </div>
                                        <div class="app-surface-body">
                                            <div class="row g-3">
                                                <div class="col-md-6"><div class="small text-muted">Application No.</div><div class="fw-bold"><?php echo htmlspecialchars($applicationNumber); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Application ID</div><div class="fw-bold"><?php echo (int) ($application['id'] ?? 0); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">School Year</div><div class="fw-bold"><?php echo htmlspecialchars($na($application['school_year'] ?? '')); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Semester</div><div class="fw-bold"><?php echo htmlspecialchars($na($application['semester'] ?? '')); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Application Type</div><div class="fw-bold"><?php echo htmlspecialchars($na($application['application_type'] ?? '')); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Current Status</div><div class="fw-bold"><?php echo htmlspecialchars($statusLabel); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">School Name</div><div class="fw-bold"><?php echo htmlspecialchars($na($application['school_name'] ?? '')); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Course / Program</div><div class="fw-bold"><?php echo htmlspecialchars($na($application['course'] ?? '')); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Year Level</div><div class="fw-bold"><?php echo htmlspecialchars($na($application['year_level'] ?? '')); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Birth Date</div><div class="fw-bold"><?php echo htmlspecialchars($formatDate($application['date_of_birth'] ?? '')); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Applicant Age</div><div class="fw-bold"><?php echo htmlspecialchars($applicantAge); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Place of Birth</div><div class="fw-bold"><?php echo htmlspecialchars($na($application['place_of_birth'] ?? '')); ?></div></div>
                                                <div class="col-md-6"><div class="small text-muted">Date Submitted</div><div class="fw-bold"><?php echo htmlspecialchars($formatDateTime($application['created_at'] ?? '')); ?></div></div>
                                            </div>
                                        </div>
                                    </section>

                                    <div class="row g-4">
                                        <div class="col-lg-6">
                                            <section class="app-surface h-100">
                                                <div class="app-surface-header">
                                                    <div>
                                                        <h2 class="app-surface-title">Family and Siblings</h2>
                                                        <p class="app-surface-copy">Submitted household details tied to this application.</p>
                                                    </div>
                                                </div>
                                                <div class="app-surface-body">
                                                    <div class="mb-3">
                                                        <div class="small text-muted">Mother</div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($na($application['mother_name'] ?? '')); ?></div>
                                                        <div class="small text-muted">Age: <?php echo htmlspecialchars($na($application['mother_age'] ?? '')); ?></div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="small text-muted">Father</div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($na($application['father_name'] ?? '')); ?></div>
                                                        <div class="small text-muted">Age: <?php echo htmlspecialchars($na($application['father_age'] ?? '')); ?></div>
                                                    </div>
                                                    <div class="small text-muted mb-2">Siblings</div>
                                                    <?php if ($siblings === []): ?>
                                                        <p class="small text-muted mb-0">No sibling details were submitted for this application.</p>
                                                    <?php else: ?>
                                                        <div class="d-grid gap-2">
                                                            <?php foreach ($siblings as $sibling): ?>
                                                                <div class="border rounded-3 p-3 bg-light">
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($na($sibling['name'] ?? '')); ?></div>
                                                                    <div class="small text-muted">
                                                                        Age: <?php echo htmlspecialchars($na($sibling['age'] ?? '')); ?> |
                                                                        Education: <?php echo htmlspecialchars($na($sibling['education'] ?? '')); ?> |
                                                                        Occupation: <?php echo htmlspecialchars($na($sibling['occupation'] ?? '')); ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </section>
                                        </div>

                                        <div class="col-lg-6">
                                            <section class="app-surface h-100">
                                                <div class="app-surface-header">
                                                    <div>
                                                        <h2 class="app-surface-title">Education and Grants</h2>
                                                        <p class="app-surface-copy">Academic entries and previous scholarship records submitted on the form.</p>
                                                    </div>
                                                </div>
                                                <div class="app-surface-body">
                                                    <div class="small text-muted mb-2">Educational Background</div>
                                                    <?php if ($education === []): ?>
                                                        <p class="small text-muted">No educational background entries were saved for this record.</p>
                                                    <?php else: ?>
                                                        <div class="d-grid gap-2 mb-3">
                                                            <?php foreach ($education as $row): ?>
                                                                <div class="border rounded-3 p-3 bg-light">
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($na($row['level'] ?? '')); ?></div>
                                                                    <div class="small text-muted">
                                                                        <?php echo htmlspecialchars($na($row['school'] ?? '')); ?>
                                                                        <?php if (!empty($row['year'])): ?>
                                                                            | <?php echo htmlspecialchars((string) $row['year']); ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="small text-muted mb-2">Scholarship Grants Availed</div>
                                                    <?php if ($grants === []): ?>
                                                        <p class="small text-muted mb-0">No previous grant entries were submitted for this application.</p>
                                                    <?php else: ?>
                                                        <div class="d-grid gap-2">
                                                            <?php foreach ($grants as $grant): ?>
                                                                <div class="border rounded-3 p-3 bg-light">
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($na($grant['program'] ?? '')); ?></div>
                                                                    <div class="small text-muted"><?php echo htmlspecialchars($na($grant['period'] ?? '')); ?></div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </section>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <section class="app-surface mb-4">
                                        <div class="app-surface-header">
                                            <div>
                                                <h2 class="app-surface-title">Applicant Photo</h2>
                                                <p class="app-surface-copy">Submitted 2x2 picture for this application.</p>
                                            </div>
                                        </div>
                                        <div class="app-surface-body text-center">
                                            <?php if ($photoSrc !== ''): ?>
                                                <img src="<?php echo htmlspecialchars($photoSrc); ?>" alt="Applicant 2x2 photo" class="img-fluid rounded-3 border" style="max-height: 280px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="app-empty-state py-4">
                                                    <div class="app-empty-state-icon"><i class="fa-regular fa-image"></i></div>
                                                    <h3 class="app-empty-state-title">No photo saved</h3>
                                                    <p class="app-empty-state-copy">This application record does not have a stored 2x2 photo.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </section>

                                    <section class="app-surface">
                                        <div class="app-surface-header">
                                            <div>
                                                <h2 class="app-surface-title">Digital Signature</h2>
                                                <p class="app-surface-copy">Submitted e-signature attached to this application.</p>
                                            </div>
                                        </div>
                                        <div class="app-surface-body text-center">
                                            <?php if ($signatureSrc !== ''): ?>
                                                <img src="<?php echo htmlspecialchars($signatureSrc); ?>" alt="Applicant signature" class="img-fluid rounded-3 border bg-white" style="max-height: 180px; object-fit: contain;">
                                            <?php else: ?>
                                                <div class="app-empty-state py-4">
                                                    <div class="app-empty-state-icon"><i class="fa-solid fa-signature"></i></div>
                                                    <h3 class="app-empty-state-title">No signature saved</h3>
                                                    <p class="app-empty-state-copy">This application record does not have a stored e-signature.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="student-record-documents">
                            <section class="app-surface">
                                <div class="app-surface-header">
                                    <div>
                                        <h2 class="app-surface-title">Submitted Documents</h2>
                                        <p class="app-surface-copy">Each document can be opened in a separate page. File size is shown for reference.</p>
                                    </div>
                                </div>
                                <div class="p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="ps-4 py-3">Document</th>
                                                    <th class="py-3">Status</th>
                                                    <th class="py-3">File Size</th>
                                                    <th class="py-3">Submitted</th>
                                                    <th class="py-3">Remarks</th>
                                                    <th class="py-3 text-end pe-4">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($documents === []): ?>
                                                    <tr>
                                                        <td colspan="6" class="p-0">
                                                            <div class="app-empty-state">
                                                                <div class="app-empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                                                                <h3 class="app-empty-state-title">No uploaded documents found</h3>
                                                                <p class="app-empty-state-copy">This application record does not have any stored document files yet.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($documents as $document): ?>
                                                        <tr>
                                                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars((string) ($document['document_type'] ?? 'Document')); ?></td>
                                                            <td><span class="<?php echo htmlspecialchars((string) (($document['status_badge']['class'] ?? 'app-status-badge app-status-default'))); ?>"><?php echo htmlspecialchars((string) (($document['status_badge']['label'] ?? ($document['status'] ?? 'Pending')))); ?></span></td>
                                                            <td><?php echo htmlspecialchars((string) ($document['file_size_label'] ?? 'N/A')); ?></td>
                                                            <td><?php echo htmlspecialchars($formatDateTime($document['uploaded_at'] ?? '')); ?></td>
                                                            <td class="small text-muted"><?php echo htmlspecialchars($na($document['rejection_remarks'] ?? '')); ?></td>
                                                            <td class="text-end pe-4">
                                                                <a href="<?php echo htmlspecialchars(base_url('student/application/' . (int) ($application['id'] ?? 0) . '/document/' . (int) ($document['id'] ?? 0))); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">Open In New Page</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="tab-pane fade" id="student-record-timeline">
                            <section class="app-surface">
                                <div class="app-surface-header">
                                    <div>
                                        <h2 class="app-surface-title">Application Timeline</h2>
                                        <p class="app-surface-copy">Chronological history of this submitted application.</p>
                                    </div>
                                </div>
                                <div class="app-surface-body">
                                    <?php if (($applicationTimeline ?? []) === []): ?>
                                        <div class="app-empty-state py-4">
                                            <div class="app-empty-state-icon"><i class="fa-solid fa-timeline"></i></div>
                                            <h3 class="app-empty-state-title">No timeline entries yet</h3>
                                            <p class="app-empty-state-copy">Workflow activity for this application will appear here once events are recorded.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="app-timeline">
                                            <?php foreach ($applicationTimeline as $entry): ?>
                                                <article class="app-timeline-item">
                                                    <div class="app-timeline-marker <?php echo htmlspecialchars((string) ($entry['badge_class'] ?? 'text-bg-secondary')); ?>">
                                                        <i class="fa-solid <?php echo htmlspecialchars((string) ($entry['icon'] ?? 'fa-circle')); ?>"></i>
                                                    </div>
                                                    <div class="app-timeline-content">
                                                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                                                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($entry['title'] ?? 'Activity')); ?></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($formatDateTime((string) ($entry['time'] ?? ''), '-')); ?></div>
                                                        </div>
                                                        <?php if (!empty($entry['details'])): ?>
                                                            <div class="small mt-2"><?php echo htmlspecialchars((string) $entry['details']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
