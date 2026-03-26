<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">
        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom">
                <h1 class="h3 fw-bold" style="color: var(--lgu-primary);">Statement of Account Submission</h1>
            </div>

            <div class="row justify-content-center">
                <div class="col-xl-7">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="alert alert-info border-0">
                                <div class="fw-bold mb-1">You passed the interview.</div>
                                <div class="small mb-0">Upload your current Statement of Account so the LGU can continue your scholarship evaluation.</div>
                            </div>

                            <div class="mb-4">
                                <div class="small text-muted mb-1">Application Period</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) (($currentSchoolYear ?? '') . ' | ' . ($currentSemester ?? ''))); ?></div>
                            </div>

                            <div class="mb-4">
                                <div class="small text-muted mb-1">SOA Deadline Policy</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($soaDeadlinePolicyLabel ?? 'Manual fixed date')); ?></div>
                            </div>

                            <div class="mb-4">
                                <div class="small text-muted mb-1">SOA Deadline</div>
                                <div class="fw-semibold <?php echo (!empty($soaDeadline) && !$soaDeadlineOpen) ? 'text-danger' : 'text-dark'; ?>">
                                    <?php echo !empty($soaDeadline) ? htmlspecialchars((string) $soaDeadline) : 'Not configured'; ?>
                                </div>
                                <?php if (!empty($soaDeadline)): ?>
                                    <div class="small <?php echo $soaDeadlineOpen ? 'text-muted' : 'text-danger fw-bold'; ?>">
                                        <?php echo $soaDeadlineOpen ? 'SOA submission is currently open.' : 'SOA submission deadline has passed.'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form action="<?php echo htmlspecialchars(base_url('student/submit-application')); ?>" method="POST" enctype="multipart/form-data">
                                <?php echo csrf_input(); ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Statement of Account *</label>
                                    <input type="file" class="form-control" name="soa_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <div class="form-text">Allowed types: JPG, PNG, PDF. Maximum file size: 2MB.</div>
                                </div>

                                <div class="d-flex justify-content-between gap-2 mt-4">
                                    <a href="<?php echo htmlspecialchars(base_url('student/dashboard')); ?>" class="btn btn-outline-secondary px-4">Back</a>
                                    <button type="submit" class="btn btn-primary px-4 fw-bold" <?php echo (!empty($soaDeadline) && !$soaDeadlineOpen) ? 'disabled' : ''; ?>>
                                        Submit SOA
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
