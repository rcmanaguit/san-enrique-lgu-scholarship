<?php require __DIR__ . '/../layouts/header.php'; ?>

<?php
$statusLabel = str_replace('_', ' ', (string) ($applicant['status'] ?? ''));
$periodLabel = trim((string) (($applicant['school_year'] ?? '') . ' | ' . ($applicant['semester'] ?? '')));
$summaryApplication = $applicant;
$summaryApplicationNumber = $applicationNumber ?? '';
$summaryNextAction = $nextAction ?? ['title' => 'Review this application', 'detail' => ''];
$summaryWorkflowSteps = $workflowSteps ?? [];
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <section class="app-page-header">
                <div>
                    <h1 class="app-page-title"><?php echo htmlspecialchars((string) (($applicant['last_name'] ?? '') . ', ' . ($applicant['first_name'] ?? ''))); ?></h1>
                </div>
                <div class="app-page-header-actions">
                    <a href="<?php echo htmlspecialchars(base_url('staff/applications')); ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-2"></i>Back to Applications
                    </a>
                    <?php if (!empty($applicant['student_id'])): ?>
                        <a href="<?php echo htmlspecialchars(base_url('staff/master-record/' . (int) ($applicant['student_id'] ?? 0))); ?>" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-address-card me-2"></i>Master Record
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars(base_url('staff/print-form?id=' . (int) ($applicationId ?? 0))); ?>" class="btn btn-outline-primary">
                        <i class="fa-solid fa-file-pdf me-2"></i>Print Form
                    </a>
                    <span class="app-pill-badge"><?php echo htmlspecialchars($statusLabel); ?></span>
                </div>
            </section>

            <?php require __DIR__ . '/../partials/application_summary_card.php'; ?>

            <section class="app-surface mb-4 app-review-command-bar">
                <div class="app-surface-body">
                    <div class="app-review-command-layout">
                        <div>
                            <div class="app-stat-kicker">Focus</div>
                            <div class="fw-bold text-dark mb-1"><?php echo htmlspecialchars((string) ($reviewSummary['next_rule'] ?? 'Complete the remaining review items on this application.')); ?></div>
                            <div class="app-meta-chips">
                                <span class="app-urgency-chip app-urgency-medium"><?php echo (int) ($reviewSummary['pending_count'] ?? 0); ?> pending</span>
                                <span class="app-urgency-chip app-urgency-high"><?php echo (int) ($reviewSummary['rejected_count'] ?? 0); ?> returned</span>
                                <span class="app-urgency-chip app-urgency-low"><?php echo (int) ($reviewSummary['verified_count'] ?? 0); ?> verified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-surface">
                <div class="app-surface-header">
                    <div><h2 class="app-surface-title">Workspace</h2></div>
                </div>
                <div class="app-surface-body">
                    <ul class="nav nav-pills app-review-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#review-documents" type="button">Documents</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#review-timeline" type="button">Timeline</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#review-versions" type="button">History</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#review-notes" type="button">Notes</button></li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="review-documents">
                            <div class="row g-4">
                                <div class="col-lg-5">
                                    <section class="app-surface h-100">
                                        <div class="app-surface-header">
                                            <div>
                                                <h3 class="app-surface-title"><i class="fa-solid fa-file-shield text-warning me-2"></i>Documents</h3>
                                                <p class="app-surface-copy d-lg-none mb-0">On mobile, use “Open In New Page” for a larger document view.</p>
                                            </div>
                                        </div>
                                        <div class="p-0">
                                            <?php if ($documents === []): ?>
                                                <div class="app-empty-state">
                                                    <div class="app-empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                                                    <h3 class="app-empty-state-title">No uploaded documents</h3>
                                                    <p class="app-empty-state-copy">This application does not have any saved document uploads yet.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="app-list-stack">
                                                    <?php foreach ($documents as $doc): ?>
                                                        <?php
                                                        $uploadedAt = strtotime((string) ($doc['uploaded_at'] ?? ''));
                                                        $updatedAt = strtotime((string) ($doc['updated_at'] ?? ''));
                                                        $looksResubmitted = ($doc['status'] ?? '') === 'Pending' && $uploadedAt && $updatedAt && $updatedAt > $uploadedAt;
                                                        $documentPreviewUrl = base_url('staff/document-preview/' . (int) ($doc['id'] ?? 0));
                                                        ?>
                                                        <article class="app-list-item app-review-doc-card <?php echo !empty($doc['is_pending_review']) ? 'is-pending' : (!empty($doc['is_rejected']) ? 'is-returned' : 'is-verified'); ?>">
                                                            <div class="app-list-item-head mb-2">
                                                                <button class="btn btn-link text-decoration-none fw-bold p-0 text-start view-doc-btn" data-document-id="<?php echo (int) ($doc['id'] ?? 0); ?>" data-preview-url="<?php echo htmlspecialchars($documentPreviewUrl); ?>" data-file-extension="<?php echo htmlspecialchars(strtolower(pathinfo((string) ($doc['file_path'] ?? ''), PATHINFO_EXTENSION))); ?>" data-doctype="<?php echo htmlspecialchars(document_type_label((string) $doc['document_type'])); ?>">
                                                                    <i class="fa-solid fa-file-lines text-primary me-1"></i><?php echo htmlspecialchars(document_type_label((string) $doc['document_type'])); ?>
                                                                </button>
                                                                <span class="<?php echo htmlspecialchars((string) (($doc['status_badge']['class'] ?? 'app-status-badge app-status-default'))); ?>">
                                                                    <?php echo htmlspecialchars((string) (($doc['status_badge']['label'] ?? ($doc['status'] ?? 'Pending')))); ?>
                                                                </span>
                                                            </div>
                                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                                <span class="<?php echo htmlspecialchars((string) (($doc['review_priority']['class'] ?? 'app-urgency-chip app-urgency-neutral'))); ?>">
                                                                    <?php echo htmlspecialchars((string) (($doc['review_priority']['label'] ?? 'Needs review'))); ?>
                                                                </span>
                                                                <span class="app-meta-chip"><?php echo htmlspecialchars((string) ($doc['relative_updated_at'] ?? '')); ?></span>
                                                            </div>
                                                            <p class="small text-muted mb-2"><?php echo htmlspecialchars((string) ($doc['review_hint'] ?? '')); ?></p>
                                                            <?php if ($looksResubmitted): ?>
                                                                <div class="mb-3 p-2 bg-primary bg-opacity-10 border border-primary-subtle rounded small text-primary-emphasis"><strong>Resubmitted file:</strong> The applicant uploaded a corrected copy on <?php echo htmlspecialchars((string) ($doc['updated_at'] ?? '')); ?>.</div>
                                                            <?php elseif (!empty($doc['updated_at'])): ?>
                                                                <p class="small text-muted mb-3">Last updated: <?php echo htmlspecialchars((string) $doc['updated_at']); ?></p>
                                                            <?php endif; ?>
                                                            <?php if (($doc['status'] ?? '') !== 'Verified'): ?>
                                                                <div class="d-flex gap-2 mt-3 flex-wrap">
                                                                    <form action="<?php echo htmlspecialchars(base_url('staff/update-document-status')); ?>" method="POST" class="flex-grow-1">
                                                                        <?php echo csrf_input(); ?>
                                                                        <input type="hidden" name="document_id" value="<?php echo (int) $doc['id']; ?>">
                                                                        <input type="hidden" name="application_id" value="<?php echo (int) $applicationId; ?>">
                                                                        <input type="hidden" name="status" value="Verified">
                                                                        <button type="button" class="btn btn-sm btn-success w-100 confirm-action" data-action="verify this document"><i class="fa-solid fa-check me-1"></i>Verify</button>
                                                                    </form>
                                                                    <form action="<?php echo htmlspecialchars(base_url('staff/update-document-status')); ?>" method="POST" class="flex-grow-1">
                                                                        <?php echo csrf_input(); ?>
                                                                        <input type="hidden" name="document_id" value="<?php echo (int) $doc['id']; ?>">
                                                                        <input type="hidden" name="application_id" value="<?php echo (int) $applicationId; ?>">
                                                                        <input type="hidden" name="status" value="Rejected">
                                                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 btn-reject-doc"><i class="fa-solid fa-xmark me-1"></i>Reject</button>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (($doc['status'] ?? '') === 'Rejected' && !empty($doc['rejection_remarks'])): ?>
                                                                <div class="mt-3 p-2 bg-danger bg-opacity-10 border border-danger rounded small"><strong class="text-danger">Reason:</strong> <?php echo htmlspecialchars((string) $doc['rejection_remarks']); ?></div>
                                                            <?php endif; ?>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </section>
                                </div>

                                <div class="col-lg-7 app-mobile-hide">
                                    <section class="app-surface h-100">
                                        <div class="app-surface-header">
                                            <div><h3 class="app-surface-title"><i class="fa-solid fa-eye me-2"></i>Document Preview</h3></div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span id="preview-title" class="app-pill-badge">Select a document to view</span>
                                                <a id="preview-open-new-page" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary d-none">
                                                    <i class="fa-solid fa-up-right-from-square me-1"></i>Open
                                                </a>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-center align-items-center bg-secondary bg-opacity-10 rounded" style="min-height: 600px;">
                                            <div id="preview-placeholder" class="app-empty-state">
                                                <div class="app-empty-state-icon"><i class="fa-solid fa-file-magnifying-glass"></i></div>
                                                <h3 class="app-empty-state-title">No document selected</h3>
                                                <p class="app-empty-state-copy">Click a document on the left to preview it here.</p>
                                            </div>
                                            <iframe id="document-viewer-frame" class="d-none w-100 h-100 border-0 rounded" style="min-height: 600px;"></iframe>
                                            <img id="document-viewer-img" class="d-none img-fluid" style="max-height: 800px; object-fit: contain;">
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="review-timeline">
                            <section class="app-surface">
                                <div class="app-surface-header">
                                    <div><h3 class="app-surface-title">Application Timeline</h3></div>
                                </div>
                                <div class="app-surface-body">
                                    <?php if ($applicationTimeline === []): ?>
                                        <div class="app-empty-state py-4">
                                            <div class="app-empty-state-icon"><i class="fa-solid fa-timeline"></i></div>
                                            <h3 class="app-empty-state-title">No timeline entries yet</h3>
                                            <p class="app-empty-state-copy">Workflow activity for this application will appear here once events are recorded.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="app-timeline">
                                            <?php foreach ($applicationTimeline as $entry): ?>
                                                <article class="app-timeline-item">
                                                    <div class="app-timeline-marker <?php echo htmlspecialchars((string) ($entry['badge_class'] ?? 'text-bg-secondary')); ?>"><i class="fa-solid <?php echo htmlspecialchars((string) ($entry['icon'] ?? 'fa-circle')); ?>"></i></div>
                                                    <div class="app-timeline-content">
                                                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                                                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($entry['title'] ?? 'Activity')); ?></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars((string) ($entry['time'] ?? '')); ?></div>
                                                        </div>
                                                        <div class="small text-muted mb-1"><?php echo htmlspecialchars((string) ($entry['actor'] ?? 'System')); ?></div>
                                                        <?php if (!empty($entry['details'])): ?><div class="small mb-2"><?php echo htmlspecialchars((string) $entry['details']); ?></div><?php endif; ?>
                                                        <?php if (!empty($entry['meta']) && is_array($entry['meta'])): ?>
                                                            <div class="app-meta-chips">
                                                                <?php foreach ($entry['meta'] as $metaLabel => $metaValue): ?>
                                                                    <?php if (trim((string) $metaValue) === '') { continue; } ?>
                                                                    <span class="app-meta-chip"><?php echo htmlspecialchars((string) $metaLabel . ': ' . (string) $metaValue); ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>

                        <div class="tab-pane fade" id="review-versions">
                            <section class="app-surface">
                                <div class="app-surface-header">
                                    <div><h3 class="app-surface-title">File History</h3></div>
                                </div>
                                <div class="app-surface-body">
                                    <?php if ($documentVersionHistory === []): ?>
                                        <div class="app-empty-state py-4">
                                            <div class="app-empty-state-icon"><i class="fa-solid fa-code-branch"></i></div>
                                            <h3 class="app-empty-state-title">No document versions yet</h3>
                                            <p class="app-empty-state-copy">Document version snapshots will appear here once files are uploaded or replaced.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead><tr><th>Document</th><th>Version</th><th>Source</th><th>Status</th><th>Uploaded By</th><th>Remarks</th><th>Created At</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($documentVersionHistory as $versionRows): ?>
                                                        <?php foreach ($versionRows as $versionRow): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars(document_type_label((string) ($versionRow['document_type'] ?? 'Document'))); ?></td>
                                                                <td>v<?php echo (int) ($versionRow['version_number'] ?? 0); ?></td>
                                                                <td><?php echo htmlspecialchars((string) ($versionRow['source_action'] ?? '')); ?></td>
                                                                <td><?php echo htmlspecialchars((string) ($versionRow['document_status'] ?? 'Pending')); ?></td>
                                                                <td><?php echo htmlspecialchars((string) ($versionRow['uploader_role'] ?? 'Unknown')); ?></td>
                                                                <td class="small text-muted"><?php echo htmlspecialchars((string) ($versionRow['rejection_remarks'] ?? '')); ?></td>
                                                                <td class="small text-muted"><?php echo htmlspecialchars((string) ($versionRow['created_at'] ?? '')); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>

                        <div class="tab-pane fade" id="review-notes">
                            <section class="app-surface">
                                <div class="app-surface-header">
                                    <div><h3 class="app-surface-title"><i class="fa-solid fa-note-sticky text-primary me-2"></i>Staff Notes</h3></div>
                                </div>
                                <div class="app-surface-body">
                                    <form action="<?php echo htmlspecialchars(base_url('staff/add-case-note')); ?>" method="POST" class="mb-3">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="application_id" value="<?php echo (int) $applicationId; ?>">
                                        <label class="form-label fw-bold">Add Staff Note</label>
                                        <textarea class="form-control mb-2" name="note_text" rows="3" maxlength="3000" required></textarea>
                                        <button type="submit" class="btn btn-sm btn-primary">Save Note</button>
                                    </form>
                                    <?php if ($caseNotes === []): ?>
                                        <div class="app-empty-state py-4">
                                            <div class="app-empty-state-icon"><i class="fa-regular fa-note-sticky"></i></div>
                                            <h3 class="app-empty-state-title">No staff notes yet</h3>
                                            <p class="app-empty-state-copy">Add notes here to keep reminders and reviewer context with the application.</p>
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
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const viewButtons = document.querySelectorAll('.view-doc-btn');
    const placeholder = document.getElementById('preview-placeholder');
    const iframeViewer = document.getElementById('document-viewer-frame');
    const imgViewer = document.getElementById('document-viewer-img');
    const previewTitle = document.getElementById('preview-title');
    const previewOpenNewPage = document.getElementById('preview-open-new-page');

    const openPreview = function (button) {
        if (!button) {
            return;
        }

        viewButtons.forEach(function (candidate) {
            candidate.classList.remove('is-active');
        });
        button.classList.add('is-active');

        const previewUrl = button.getAttribute('data-preview-url') || '';
        const docType = button.getAttribute('data-doctype');
        const ext = (button.getAttribute('data-file-extension') || '').toLowerCase();
        previewTitle.innerText = 'Viewing: ' + docType;
        if (previewOpenNewPage) {
            previewOpenNewPage.href = previewUrl;
            previewOpenNewPage.classList.remove('d-none');
        }
        placeholder.classList.add('d-none');
        iframeViewer.classList.add('d-none');
        imgViewer.classList.add('d-none');
        if (ext === 'pdf') {
            iframeViewer.src = previewUrl;
            iframeViewer.classList.remove('d-none');
        } else {
            imgViewer.src = previewUrl;
            imgViewer.classList.remove('d-none');
        }
    };

    viewButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            openPreview(this);
        });
    });

    if (viewButtons.length > 0) {
        openPreview(viewButtons[0]);
    }
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
