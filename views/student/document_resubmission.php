<?php
require __DIR__ . '/../layouts/header.php';

$documentMeta = [
    'Grades' => [
        'label' => 'Previous Grades / Report Card',
        'input_name' => 'grades_file',
        'icon' => 'fa-file-lines',
    ],
    'Residency' => [
        'label' => 'Certificate of Barangay Residency',
        'input_name' => 'residency_file',
        'icon' => 'fa-house-flag',
    ],
];
?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">
            <div class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Document Resubmission</span>
                    <h1 class="app-page-title">Upload the corrected application documents</h1>
                    <p class="app-page-subtitle">Only the rejected requirements need to be replaced. Once you submit them again, your application returns to the staff review queue.</p>
                </div>
                <div class="app-page-header-actions">
                    <a href="<?php echo htmlspecialchars(base_url('student/dashboard')); ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="app-surface mb-4">
                <div class="app-surface-header">
                    <div>
                        <h5 class="app-surface-title"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Action Required</h5>
                        <p class="app-surface-copy">Review the staff remarks below, upload the corrected files, and submit once all rejected requirements are replaced.</p>
                    </div>
                </div>
                <div class="app-surface-body">
                    <form action="<?php echo htmlspecialchars(base_url('student/submit-application')); ?>" method="POST" enctype="multipart/form-data" novalidate>
                        <div class="row g-4">
                            <?php foreach ($documentMeta as $documentType => $meta): ?>
                                <?php $document = $resubmissionDocuments[$documentType] ?? null; ?>
                                <?php if (!$document): ?>
                                    <?php continue; ?>
                                <?php endif; ?>

                                <?php $isRejected = ((string) ($document['status'] ?? '') === 'Rejected'); ?>
                                <div class="col-lg-6">
                                    <div class="application-section-card h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                            <div>
                                                <h6 class="fw-bold mb-1">
                                                    <i class="fa-solid <?php echo htmlspecialchars($meta['icon']); ?> text-primary me-2"></i>
                                                    <?php echo htmlspecialchars($meta['label']); ?>
                                                </h6>
                                                <p class="small text-muted mb-0">
                                                    <?php if ($isRejected): ?>
                                                        Staff marked this document for correction. Upload a new clear copy in JPG, PNG, or PDF format.
                                                    <?php else: ?>
                                                        This document is already accepted and does not need to be uploaded again.
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <span class="badge <?php echo $isRejected ? 'text-bg-danger' : 'text-bg-success'; ?>">
                                                <?php echo htmlspecialchars((string) ($document['status'] ?? 'Pending')); ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($document['rejection_remarks'])): ?>
                                            <div class="alert alert-danger small mb-3">
                                                <strong>Staff Remarks:</strong>
                                                <?php echo nl2br(htmlspecialchars((string) $document['rejection_remarks'])); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($document['updated_at'])): ?>
                                            <p class="small text-muted mb-3">Last review update: <?php echo htmlspecialchars((string) $document['updated_at']); ?></p>
                                        <?php endif; ?>

                                        <?php if ($isRejected): ?>
                                            <label class="form-label fw-bold" for="<?php echo htmlspecialchars($meta['input_name']); ?>">Upload corrected file *</label>
                                            <input
                                                type="file"
                                                class="form-control"
                                                id="<?php echo htmlspecialchars($meta['input_name']); ?>"
                                                name="<?php echo htmlspecialchars($meta['input_name']); ?>"
                                                accept=".jpg,.jpeg,.png,.pdf"
                                                required
                                                data-field-label="<?php echo htmlspecialchars($meta['label']); ?>">
                                            <div class="form-text">Please upload not more than 2MB.</div>
                                        <?php else: ?>
                                            <div class="app-pill-badge">
                                                <i class="fa-solid fa-circle-check"></i> No action needed
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="app-actions-row mt-4">
                            <a href="<?php echo htmlspecialchars(base_url('student/dashboard')); ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-upload me-1"></i>Submit Corrected Documents
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
