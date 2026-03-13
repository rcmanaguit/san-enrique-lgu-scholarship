<div class="card card-soft shadow-sm">
    <div class="card-body p-4">
        <div class="wizard-step-header mb-3">
            <h2 class="h5 mb-2">Step 2: Personal Information</h2>
            <p class="small text-muted mb-0" id="autosaveStatus">Auto-save is enabled for this step.</p>
        </div>
        <form method="post" class="row g-3" id="applyStep2Form" data-autosave-step="2">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_step2">

            <div class="col-12 col-md-3">
                <label class="form-label">Last Name *</label>
                <input type="text" name="last_name" class="form-control" value="<?= e((string) ($step2['last_name'] ?? '')) ?>" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">First Name *</label>
                <input type="text" name="first_name" class="form-control" value="<?= e((string) ($step2['first_name'] ?? '')) ?>" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-control" value="<?= e((string) ($step2['middle_name'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Suffix</label>
                <input type="text" name="suffix" class="form-control" value="<?= e((string) ($step2['suffix'] ?? '')) ?>" maxlength="20" placeholder="e.g., Jr., Sr., III">
                <div class="form-text">Leave blank if not applicable.</div>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">Date of Birth *</label>
                <input type="date" name="birth_date" id="birthDateInput" class="form-control" value="<?= e((string) ($step2['birth_date'] ?? '')) ?>" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Age</label>
                <input type="number" name="age" id="ageInput" class="form-control" value="<?= e((string) ($step2['age'] ?? '')) ?>" readonly>
                <div class="form-text">Auto-calculated</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Civil Status *</label>
                <?php $selectedCivilStatus = (string) ($step2['civil_status'] ?? ''); ?>
                <select name="civil_status" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach (['Single', 'Married', 'Widowed', 'Separated'] as $civilStatusOption): ?>
                        <option value="<?= e($civilStatusOption) ?>" <?= $selectedCivilStatus === $civilStatusOption ? 'selected' : '' ?>><?= e($civilStatusOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Sex *</label>
                <?php $selectedSex = (string) ($step2['sex'] ?? ''); ?>
                <select name="sex" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach (['Male', 'Female'] as $sexOption): ?>
                        <option value="<?= e($sexOption) ?>" <?= $selectedSex === $sexOption ? 'selected' : '' ?>><?= e($sexOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" value="<?= e((string) ($step2['email'] ?? ($user['email'] ?? ''))) ?>" placeholder="you@example.com" required>
                <div class="form-text">This email will be saved to your account and used in application records.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Place of Birth *</label>
                <input type="text" name="birth_place" class="form-control" value="<?= e((string) ($step2['birth_place'] ?? '')) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Address (House No. / Street / Purok) *</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Address (House No. / Street / Purok)" required><?= e((string) ($step2['address'] ?? '')) ?></textarea>
                <div class="form-text">Enter your address details (House No. / Street / Purok).</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Barangay *</label>
                <select name="barangay" class="form-select" required>
                    <option value="">Select Barangay</option>
                    <?php foreach ($barangayOptions as $barangay): ?>
                        <option value="<?= e($barangay) ?>" <?= (string) ($step2['barangay'] ?? '') === $barangay ? 'selected' : '' ?>><?= e($barangay) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Town / Municipality</label>
                <input type="text" name="town" class="form-control" value="<?= e((string) ($step2['town'] ?? san_enrique_town())) ?>" readonly>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Province</label>
                <input type="text" name="province" class="form-control" value="<?= e((string) ($step2['province'] ?? san_enrique_province())) ?>" readonly>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Contact Number *</label>
                <input type="text" name="contact_number" class="form-control" value="<?= e((string) ($step2['contact_number'] ?? '')) ?>" readonly required>
                <div class="form-text">Auto-filled from your registered mobile number.</div>
            </div>

            <div class="col-12 wizard-actions">
                <a href="apply.php?step=1" class="btn btn-outline-secondary wizard-btn-prev"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
                <button type="submit" class="btn btn-primary wizard-btn-next"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</button>
            </div>
        </form>
    </div>
</div>
