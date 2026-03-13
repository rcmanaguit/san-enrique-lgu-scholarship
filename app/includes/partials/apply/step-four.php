<div class="card card-soft shadow-sm">
    <div class="card-body p-4">
        <div class="wizard-step-header mb-3">
            <h2 class="h5 mb-2">Step 4: Requirements Upload</h2>
            <p class="small text-muted mb-0">Upload only the documents required now for initial review. Additional documents may be requested later in the process.</p>
        </div>
        <?php if ($laterRequirements): ?>
            <div class="alert alert-info small">
                <strong>Later / If Requested:</strong>
                <?php foreach ($laterRequirements as $index => $req): ?>
                    <?= $index > 0 ? ' | ' : '' ?><?= e((string) ($req['requirement_name'] ?? 'Requirement')) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <h3 class="h6 mb-3">Required Now</h3>
        <form method="post" enctype="multipart/form-data" class="row g-3" id="applyStep4Form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_step4">

            <?php foreach ($requirements as $req): ?>
                <?php
                $reqId = (string) $req['id'];
                $field = 'req_' . $reqId;
                $existing = $wizard['documents'][$reqId] ?? null;
                ?>
                <div class="col-12">
                    <div class="requirement-item">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h3 class="h6 mb-1">
                                    <?= e((string) $req['requirement_name']) ?>
                                    <?php if ((int) ($req['is_required'] ?? 1) === 1): ?>
                                        <span class="badge text-bg-danger ms-1">Required</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary ms-1">Optional</span>
                                    <?php endif; ?>
                                </h3>
                                <?php if (!empty($req['description'])): ?>
                                    <p class="small text-muted mb-0"><?= e((string) $req['description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ($existing): ?>
                                <span class="badge text-bg-success"><i class="fa-solid fa-check me-1"></i>Uploaded</span>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="<?= e($field) ?>" class="form-control mt-2" accept=".pdf,.jpg,.jpeg,.png" <?= ((int) ($req['is_required'] ?? 1) === 1 && !$existing) ? 'required' : '' ?>>
                        <div class="small text-muted mt-1">Upload PDF/image file.</div>
                        <?php if ($existing): ?>
                            <div class="small mt-2 text-muted">Current: <?= e((string) ($existing['original_name'] ?? basename((string) $existing['file_path']))) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="col-12 wizard-actions">
                <a href="apply.php?step=3" class="btn btn-outline-secondary wizard-btn-prev" id="step4PrevBtn"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
                <button type="submit" class="btn btn-primary wizard-btn-next" id="step4NextBtn">
                    <span class="step4-next-label"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</span>
                    <span class="step4-loading-label d-none"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Uploading files...</span>
                </button>
            </div>
            <div class="col-12">
                <p class="small text-muted mb-0 d-none" id="step4UploadingNote">Please wait. Do not close or refresh this page while uploading.</p>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('applyStep4Form');
        const nextBtn = document.getElementById('step4NextBtn');
        const prevBtn = document.getElementById('step4PrevBtn');
        const uploadingNote = document.getElementById('step4UploadingNote');
        if (!form || !nextBtn) {
            return;
        }

        let isSubmitting = false;
        form.querySelectorAll('input[type="file"]').forEach(function (field) {
            field.addEventListener('change', function () {
                if (typeof field.reportValidity === 'function') {
                    field.reportValidity();
                }
            });
        });

        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }
            if (isSubmitting) {
                return;
            }
            isSubmitting = true;
            nextBtn.disabled = true;

            const nextLabel = nextBtn.querySelector('.step4-next-label');
            const loadingLabel = nextBtn.querySelector('.step4-loading-label');
            if (nextLabel) {
                nextLabel.classList.add('d-none');
            }
            if (loadingLabel) {
                loadingLabel.classList.remove('d-none');
            }
            if (uploadingNote) {
                uploadingNote.classList.remove('d-none');
            }
            if (prevBtn) {
                prevBtn.classList.add('disabled');
                prevBtn.setAttribute('aria-disabled', 'true');
            }
        });
    });
</script>
