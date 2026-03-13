<?php
declare(strict_types=1);

if (!$resubmissionTargetsByAppId) {
    return;
}
?>
<?php foreach ($applications as $row): ?>
    <?php
    $rowId = (int) ($row['id'] ?? 0);
    $resubmissionDocs = $resubmissionTargetsByAppId[$rowId] ?? [];
    if (!$resubmissionDocs) {
        continue;
    }
    ?>
    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h6 mb-1">Document Resubmission Required</h2>
                    <div class="small text-muted">
                        <?= e((string) ($row['application_no'] ?? '-')) ?> |
                        <?= e((string) ($row['semester'] ?? '-')) ?> / <?= e((string) ($row['school_year'] ?? '-')) ?>
                    </div>
                </div>
                <span class="badge text-bg-warning">ACTION NEEDED</span>
            </div>
            <?php if (trim((string) ($row['review_notes'] ?? '')) !== ''): ?>
                <div class="alert alert-secondary small">
                    <strong>Review Notes:</strong> <?= e((string) ($row['review_notes'] ?? '')) ?>
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="resubmit_documents">
                <input type="hidden" name="application_id" value="<?= $rowId ?>">
                <?php foreach ($resubmissionDocs as $doc): ?>
                    <?php
                    $docId = (int) ($doc['id'] ?? 0);
                    $docRemark = trim((string) ($doc['remarks'] ?? ''));
                    $docPath = trim((string) ($doc['path'] ?? ''));
                    ?>
                    <div class="col-12">
                        <label class="form-label"><?= e((string) ($doc['name'] ?? 'Requirement')) ?></label>
                        <?php if ($docRemark !== ''): ?>
                            <div class="small text-muted mb-1">Issue: <?= e($docRemark) ?></div>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input
                                type="file"
                                name="resubmit_doc_<?= $docId ?>"
                                class="form-control"
                                accept=".pdf,.jpg,.jpeg,.png"
                            >
                            <?php if ($docPath !== ''): ?>
                                <a href="preview-document.php?file=<?= urlencode($docPath) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                                    View Current File
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-upload me-1"></i>Submit Replacement Documents
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
