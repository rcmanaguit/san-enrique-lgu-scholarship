<div class="card card-soft shadow-sm wizard-review">
    <div class="card-body p-4">
        <div class="wizard-step-header mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="h5 mb-0">Step 6: Review and Preview Before Submit</h2>
                <button type="button" class="btn btn-outline-primary btn-sm" id="openPrintablePreviewBtn">
                    <i class="fa-solid fa-print me-1"></i>Preview Printable Form
                </button>
            </div>
            <p class="small text-muted mb-0">Review your details carefully before final submission. You can still go back and edit any step.</p>
        </div>
        <?php if ($reviewIssues): ?>
            <div class="alert alert-warning">
                <strong>Complete these first:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($reviewIssues as $issue): ?>
                        <li>
                            <?= e((string) ($issue['text'] ?? 'Incomplete item')) ?>
                            <?php if (!empty($issue['url'])): ?>
                                <a href="<?= e((string) $issue['url']) ?>" class="ms-1">Fix</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-8">
                <div class="card card-soft">
                    <div class="card-body">
                        <h3 class="h6">Program Details <a class="small ms-2" href="apply.php?step=1">Edit</a></h3>
                        <div class="review-kv">
                            <div class="review-kv-row">
                                <span class="review-kv-label">Applicant Type</span>
                                <span class="review-kv-value"><?= e($applicantTypeLabel) ?></span>
                            </div>
                            <div class="review-kv-row">
                                <span class="review-kv-label">Semester / School Year</span>
                                <span class="review-kv-value"><?= e((string) ($step1['semester'] ?? '')) ?> / <?= e((string) ($step1['school_year'] ?? '')) ?></span>
                            </div>
                            <div class="review-kv-row">
                                <span class="review-kv-label">Email Address</span>
                                <span class="review-kv-value"><?= e((string) ($step1['email'] ?? '')) ?></span>
                            </div>
                            <div class="review-kv-row">
                                <span class="review-kv-label">School</span>
                                <span class="review-kv-value"><?= e((string) ($step1['school_name'] ?? '')) ?> (<?= e((string) ($step1['school_type'] ?? '')) ?>)</span>
                            </div>
                            <div class="review-kv-row">
                                <span class="review-kv-label">Course</span>
                                <span class="review-kv-value"><?= e((string) ($step1['course'] ?? '')) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card card-soft h-100">
                    <div class="card-body">
                        <h3 class="h6">2x2 Photo <a class="small ms-2" href="apply-photo.php">Edit</a></h3>
                        <div class="photo-frame">
                            <?php if (!empty($wizard['photo_path'])): ?>
                                <img src="<?= e((string) $wizard['photo_path']) ?>" alt="2x2 Photo">
                            <?php else: ?>
                                <span class="small text-muted">Missing photo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-soft mb-3">
            <div class="card-body">
                <h3 class="h6">Personal and Family Info <a class="small ms-2" href="apply.php?step=2">Edit Personal</a> <a class="small ms-2" href="apply.php?step=3">Edit Family</a></h3>
                <?php
                $motherReview = !empty($step3['mother_na']) ? 'N/A' : (!empty($step3['mother_deceased']) ? 'Deceased' : trim((string) ($step3['mother_name'] ?? '')));
                $fatherReview = !empty($step3['father_na']) ? 'N/A' : (!empty($step3['father_deceased']) ? 'Deceased' : trim((string) ($step3['father_name'] ?? '')));
                if ($motherReview === '') {
                    $motherReview = 'N/A';
                }
                if ($fatherReview === '') {
                    $fatherReview = 'N/A';
                }
                ?>
                <div class="review-text-list">
                    <p class="mb-1"><strong>Applicant:</strong> <?= e((string) ($step2['last_name'] ?? '')) ?>, <?= e((string) ($step2['first_name'] ?? '')) ?> <?= e(trim((string) (($step2['middle_name'] ?? '') . ' ' . ($step2['suffix'] ?? '')))) ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?= e((string) ($step2['email'] ?? ($user['email'] ?? ''))) ?></p>
                    <p class="mb-1"><strong>Contact:</strong> <?= e((string) ($step2['contact_number'] ?? '')) ?></p>
                    <p class="mb-1"><strong>Address:</strong> <?= e(trim((string) (($step2['address'] ?? '') . ', ' . ($step2['barangay'] ?? '') . ', ' . ($step2['town'] ?? san_enrique_town()) . ', ' . ($step2['province'] ?? san_enrique_province())), ', ')) ?></p>
                    <p class="mb-0"><strong>Parents:</strong> <?= e($motherReview) ?> / <?= e($fatherReview) ?></p>
                </div>
            </div>
        </div>

        <div class="card card-soft mb-4">
            <div class="card-body">
                <h3 class="h6">Requirements <a class="small ms-2" href="apply.php?step=4">Edit</a></h3>
                <ul class="mb-0 small requirements-list">
                    <?php foreach ($wizard['documents'] as $doc): ?>
                        <?php
                        $docPath = trim((string) ($doc['file_path'] ?? ''));
                        $isPreviewableDoc = $docPath !== '' && (
                            str_starts_with($docPath, 'uploads/documents/')
                            || str_starts_with($docPath, 'uploads/tmp/')
                            || str_starts_with($docPath, '/uploads/documents/')
                            || str_starts_with($docPath, '/uploads/tmp/')
                        );
                        ?>
                        <li>
                            <?= e((string) ($doc['requirement_name'] ?? 'Requirement')) ?> -
                            <span class="text-muted"><?= e((string) ($doc['original_name'] ?? basename((string) ($doc['file_path'] ?? '')))) ?></span>
                            <?php if ($isPreviewableDoc): ?>
                                <button
                                    type="button"
                                    class="btn btn-link btn-sm p-0 ms-2 align-baseline js-open-doc-preview"
                                    data-preview-title="<?= e((string) ($doc['requirement_name'] ?? 'Requirement')) ?>"
                                    data-preview-src="<?= e((string) ltrim($docPath, '/')) ?>"
                                >Preview</button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <form method="post" class="row g-3 wizard-review-submit">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="final_submit">
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="agreeTermsCheck" name="agree_terms" required>
                    <label class="form-check-label" for="agreeTermsCheck">
                        By submitting this application, I confirm that the information and uploaded documents are true and correct, and I consent to their processing for scholarship evaluation.
                    </label>
                </div>
            </div>
            <div class="col-12 wizard-actions">
                <a href="apply-photo.php" class="btn btn-outline-secondary wizard-btn-prev"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
                <button type="submit" class="btn btn-primary wizard-btn-next" <?= $reviewIssues ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-paper-plane me-1"></i>Submit Final Application
                </button>
            </div>
        </form>
        <p class="small text-muted mt-3 mb-0 review-note">After submitting, you can print/download the exact legal-size application form.</p>
    </div>
</div>

<div class="modal fade" id="reviewPreviewModal" tabindex="-1" aria-labelledby="reviewPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 m-0" id="reviewPreviewModalLabel">Preview</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe
                    id="reviewPreviewFrame"
                    src="about:blank"
                    title="Preview"
                    style="border:0;width:100%;height:100%;background:#fff;"
                ></iframe>
            </div>
        </div>
    </div>
</div>
