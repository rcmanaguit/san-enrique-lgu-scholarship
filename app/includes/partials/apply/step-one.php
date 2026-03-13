<div class="card card-soft shadow-sm">
    <div class="card-body p-4">
        <div class="wizard-step-header mb-3">
            <h2 class="h5 mb-2">Step 1: Program Information</h2>
            <p class="small text-muted mb-0" id="autosaveStatus">Auto-save is enabled for this step.</p>
        </div>
        <?php if (!empty($prefilledFromPrevious)): ?>
            <div class="alert alert-info small">
                Your latest previous application information was loaded automatically. Review and update any fields that changed before submitting this renewal.
            </div>
        <?php endif; ?>
        <form method="post" class="row g-3" id="applyStep1Form" data-autosave-step="1">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_step1">
            <input type="hidden" name="applicant_type" value="<?= e((string) ($step1['applicant_type'] ?? $detectedApplicantType)) ?>">

            <div class="col-12 col-md-4">
                <label class="form-label">Applicant Type *</label>
                <input
                    type="text"
                    class="form-control"
                    value="<?= e($applicantTypeLabel) ?>"
                    readonly
                >
                <div class="form-text">Auto-detected from your previous application history.</div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Semester *</label>
                <input type="text" name="semester" class="form-control" value="<?= e((string) ($step1['semester'] ?? '')) ?>" readonly required>
                <div class="form-text">Auto-filled from active application period.</div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">School Year *</label>
                <input type="text" name="school_year" class="form-control" value="<?= e((string) ($step1['school_year'] ?? '')) ?>" readonly required>
                <div class="form-text">Auto-filled from active application period.</div>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label">School Name *</label>
                <select name="school_name" id="applySchoolNameSelect" class="form-select" required>
                    <option value="">Select School</option>
                    <?php foreach ($schoolNameOptions as $schoolOption): ?>
                        <option value="<?= e($schoolOption) ?>" <?= $selectedSchoolName === $schoolOption ? 'selected' : '' ?>>
                            <?= e($schoolOption) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__other__" <?= $selectedSchoolName === '__other__' ? 'selected' : '' ?>>Other (Type School Name)</option>
                </select>
            </div>
            <div class="col-12 col-md-4<?= $isOtherSchoolSelected ? '' : ' d-none' ?>" id="applyOtherSchoolWrapper">
                <label class="form-label">Other School Name</label>
                <input type="text" name="school_name_other" id="applyOtherSchoolInput" class="form-control" value="<?= e($otherSchoolName) ?>" placeholder="Type if not listed" <?= $isOtherSchoolSelected ? 'required' : '' ?>>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">School Type *</label>
                <select name="school_type" class="form-select" required>
                    <option value="">Select</option>
                    <option value="public" <?= ($step1['school_type'] ?? '') === 'public' ? 'selected' : '' ?>>Public</option>
                    <option value="private" <?= ($step1['school_type'] ?? '') === 'private' ? 'selected' : '' ?>>Private</option>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Course *</label>
                <select name="course" id="applyCourseSelect" class="form-select" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courseOptions as $courseOption): ?>
                        <option value="<?= e($courseOption) ?>" <?= $selectedCourse === $courseOption ? 'selected' : '' ?>>
                            <?= e($courseOption) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__other__" <?= $selectedCourse === '__other__' ? 'selected' : '' ?>>Other (Type Course)</option>
                </select>
            </div>
            <div class="col-12 col-md-6<?= $isOtherCourseSelected ? '' : ' d-none' ?>" id="applyOtherCourseWrapper">
                <label class="form-label">Other Course</label>
                <input type="text" name="course_other" id="applyOtherCourseInput" class="form-control" value="<?= e($otherCourse) ?>" placeholder="Type if not listed" <?= $isOtherCourseSelected ? 'required' : '' ?>>
            </div>

            <div class="col-12 wizard-actions wizard-actions-end">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</button>
            </div>
        </form>
    </div>
</div>
