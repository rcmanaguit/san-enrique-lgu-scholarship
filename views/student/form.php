<?php
require __DIR__ . '/../layouts/header.php';

$formData = is_array($prefillData ?? null) ? $prefillData : [];
$fieldValue = static function (string $key, string $default = '') use ($formData): string {
    $value = $formData[$key] ?? $default;
    return is_string($value) ? $value : $default;
};
$isSelected = static function (string $key, string $value) use ($fieldValue): string {
    return $fieldValue($key, '') === $value ? 'selected' : '';
};
$isChecked = static function (string $key) use ($formData): string {
    return !empty($formData[$key]) ? 'checked' : '';
};
$siblingsRows = is_array($formData['siblings'] ?? null) ? array_values($formData['siblings']) : [['name' => '', 'age' => '', 'education' => '', 'occupation' => '', 'income' => '']];
$educationRows = is_array($formData['education'] ?? null) ? array_values($formData['education']) : [];
$educationRows = array_pad($educationRows, 3, ['level' => '', 'school' => '', 'course' => '', 'year' => '', 'honors' => '']);
$grantsRows = is_array($formData['grants'] ?? null) ? array_values($formData['grants']) : [['program' => '', 'period' => '']];
$isRenewalForm = ($detectedApplicationType ?? 'New') === 'Renewal';
$selectedSchoolName = $selectedSchoolName ?? '';
$otherSchoolName = $otherSchoolName ?? '';
$selectedCourse = $selectedCourse ?? '';
$otherCourse = $otherCourse ?? '';
$schoolNameOptions = is_array($schoolNameOptions ?? null) ? $schoolNameOptions : [];
$courseOptions = is_array($courseOptions ?? null) ? $courseOptions : [];
?>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">

        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">

            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom">
                <h1 class="h3 fw-bold" style="color: var(--lgu-primary);">Scholarship Application Form</h1>
            </div>

            <div class="row justify-content-center">
                <div class="col-12 application-form-shell">
                        <div class="card application-form-card mb-5">
                        <div class="card-body">
                            <div class="application-wizard-top">
                            <?php if ($isRenewalForm): ?>
                                <div class="alert alert-info border-0 shadow-sm mb-4">
                                    <strong>Renewal application detected.</strong> Your latest approved scholarship record has been prefilled below. Review the information, update anything that changed, and upload the current requirements for this term.
                                </div>
                            <?php endif; ?>
                                <div class="application-wizard-intro">
                                    <div>
                                        <span class="application-wizard-kicker">Online Application</span>
                                        <h2 class="application-wizard-title">Complete the official scholarship application step by step</h2>
                                        <p class="application-wizard-lead">This online form follows the LGU scholarship application record. Fill in each stage carefully, review your details before submitting, and keep your uploads ready for a smoother application process.</p>
                                    </div>
                                    <div class="application-wizard-meta">
                                        <div class="application-wizard-meta-label">Application Type</div>
                                        <div class="application-wizard-meta-value"><?php echo htmlspecialchars((string) ($detectedApplicationType ?? 'New')); ?></div>
                                        <div class="small text-muted mb-0">Detected from your previous approved scholarship history.</div>
                                    </div>
                                </div>
                                <div class="application-stage-tracker">
                                    <div class="application-stage-pill is-active" data-stage-pill="0">
                                        <span class="application-stage-number">1</span>
                                        <span class="application-stage-title">Personal</span>
                                        <span class="application-stage-copy">Basic identity, contact, and address details.</span>
                                    </div>
                                    <div class="application-stage-pill" data-stage-pill="1">
                                        <span class="application-stage-number">2</span>
                                        <span class="application-stage-title">Family</span>
                                        <span class="application-stage-copy">Parent details and siblings information.</span>
                                    </div>
                                    <div class="application-stage-pill" data-stage-pill="2">
                                        <span class="application-stage-number">3</span>
                                        <span class="application-stage-title">Education</span>
                                        <span class="application-stage-copy">School background and previous grants.</span>
                                    </div>
                                    <div class="application-stage-pill" data-stage-pill="3">
                                        <span class="application-stage-number">4</span>
                                        <span class="application-stage-title">Uploads</span>
                                        <span class="application-stage-copy">Requirements, 2x2 photo, and signature.</span>
                                    </div>
                                    <div class="application-stage-pill" data-stage-pill="4">
                                        <span class="application-stage-number">5</span>
                                        <span class="application-stage-title">Review</span>
                                        <span class="application-stage-copy">Final checking, privacy consent, and certification.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="application-progress-wrap">
                                <div class="progress">
                                    <div id="form-progress"
                                        class="progress-bar progress-bar-striped progress-bar-animated bg-success fw-bold"
                                        role="progressbar" style="width: 20%; font-size: 0.95rem;">Step 1 of 5</div>
                                </div>
                            </div>

                            <form id="student-application-form"
                                action="<?php echo htmlspecialchars(base_url('student/submit-application')); ?>"
                                method="POST"
                                data-draft-user-id="<?php echo (int) ($currentUser['id'] ?? 0); ?>"
                                data-draft-school-year="<?php echo htmlspecialchars((string) ($currentSchoolYear ?? '')); ?>"
                                data-draft-semester="<?php echo htmlspecialchars((string) ($currentSemester ?? '')); ?>"
                                novalidate
                                enctype="multipart/form-data">
                                <?php echo csrf_input(); ?>
                                <div class="application-form-body">

                                <div class="form-step active" id="step-1">
                                    <div class="application-step-header">
                                        <div>
                                            <span class="application-step-kicker">Stage 1</span>
                                            <h4 class="application-step-title"><i class="fa-solid fa-user me-2"></i>Personal Information</h4>
                                            <p class="application-step-lead">Enter your personal and contact details exactly as they should appear in your scholarship record and printable application form.</p>
                                        </div>
                                        <div class="application-step-badge">
                                            <i class="fa-solid fa-circle-info"></i>
                                            Complete all required applicant details
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Last Name *</label>
                                            <input type="text" class="form-control" name="last_name" required
                                                data-live-filter="letters"
                                                value="<?php echo htmlspecialchars($fieldValue('last_name')); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">First Name *</label>
                                            <input type="text" class="form-control" name="first_name" required
                                                data-live-filter="letters"
                                                value="<?php echo htmlspecialchars($fieldValue('first_name')); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label fw-bold">Middle Name</label>
                                            <input type="text" class="form-control" name="middle_name"
                                                data-live-filter="letters"
                                                value="<?php echo htmlspecialchars($fieldValue('middle_name')); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label fw-bold">Suffix</label>
                                            <select class="form-select" name="suffix">
                                                <option value="">None</option>
                                                <option value="Jr." <?php echo $isSelected('suffix', 'Jr.'); ?>>Jr.</option>
                                                <option value="Sr." <?php echo $isSelected('suffix', 'Sr.'); ?>>Sr.</option>
                                                <option value="II" <?php echo $isSelected('suffix', 'II'); ?>>II</option>
                                                <option value="III" <?php echo $isSelected('suffix', 'III'); ?>>III</option>
                                                <option value="IV" <?php echo $isSelected('suffix', 'IV'); ?>>IV</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Email Address *</label>
                                            <input type="email" class="form-control" name="email"
                                                value="<?php echo htmlspecialchars($fieldValue('email', (string) ($currentUser['email'] ?? ''))); ?>"
                                                placeholder="you@example.com" required data-live-filter="email"
                                                data-field-label="Email Address">
                                            <div class="form-text">This becomes your account recovery email.</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Application Type</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars((string) ($detectedApplicationType ?? 'New')); ?>" readonly>
                                            <div class="form-text">Automatically detected from your previous approved scholarship records.</div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Date of Birth *</label>
                                            <input type="date" class="form-control" name="date_of_birth" required
                                                value="<?php echo htmlspecialchars($fieldValue('date_of_birth')); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Age</label>
                                            <input type="text" class="form-control" id="calculated_age" value="" readonly>
                                            <div class="form-text">Automatically calculated from your birth date.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Place of Birth</label>
                                            <input type="text" class="form-control" name="place_of_birth"
                                                placeholder="City / Municipality, Province"
                                                value="<?php echo htmlspecialchars($fieldValue('place_of_birth')); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Sex *</label>
                                            <select class="form-select" name="sex" required>
                                                <option value="">Select...</option>
                                                <option value="Male" <?php echo $isSelected('sex', 'Male'); ?>>Male</option>
                                                <option value="Female" <?php echo $isSelected('sex', 'Female'); ?>>Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Civil Status *</label>
                                            <select class="form-select" name="civil_status" required>
                                                <option value="">Select...</option>
                                                <option value="Single" <?php echo $isSelected('civil_status', 'Single'); ?>>Single</option>
                                                <option value="Married" <?php echo $isSelected('civil_status', 'Married'); ?>>Married</option>
                                                <option value="Widowed" <?php echo $isSelected('civil_status', 'Widowed'); ?>>Widowed</option>
                                                <option value="Separated" <?php echo $isSelected('civil_status', 'Separated'); ?>>Separated</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">House No./Street/Purok *</label>
                                            <input type="text" class="form-control" name="address_line"
                                                placeholder="e.g., Purok Riverside, Prk. Santol" required
                                                value="<?php echo htmlspecialchars($fieldValue('address_line')); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Barangay (San Enrique) *</label>
                                            <select class="form-select" name="address_barangay" required>
                                                <option value="">Select Barangay...</option>
                                                <option value="Bagonawa" <?php echo $isSelected('address_barangay', 'Bagonawa'); ?>>Bagonawa</option>
                                                <option value="Baliwagan" <?php echo $isSelected('address_barangay', 'Baliwagan'); ?>>Baliwagan</option>
                                                <option value="Batuan" <?php echo $isSelected('address_barangay', 'Batuan'); ?>>Batuan</option>
                                                <option value="Guintorilan" <?php echo $isSelected('address_barangay', 'Guintorilan'); ?>>Guintorilan</option>
                                                <option value="Nayon" <?php echo $isSelected('address_barangay', 'Nayon'); ?>>Nayon</option>
                                                <option value="Poblacion" <?php echo $isSelected('address_barangay', 'Poblacion'); ?>>Poblacion</option>
                                                <option value="Sibucao" <?php echo $isSelected('address_barangay', 'Sibucao'); ?>>Sibucao</option>
                                                <option value="Tabao Baybay" <?php echo $isSelected('address_barangay', 'Tabao Baybay'); ?>>Tabao Baybay</option>
                                                <option value="Tabao Rizal" <?php echo $isSelected('address_barangay', 'Tabao Rizal'); ?>>Tabao Rizal</option>
                                                <option value="Tibsoc" <?php echo $isSelected('address_barangay', 'Tibsoc'); ?>>Tibsoc</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Municipality *</label>
                                            <input type="text" class="form-control" name="address_municipality"
                                                value="San Enrique" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Province *</label>
                                            <input type="text" class="form-control" name="address_province"
                                                value="Negros Occidental" readonly>
                                        </div>
                                    </div>

                                    <div class="application-step-actions is-end">
                                        <button type="button" class="btn btn-primary btn-next px-4 fw-bold">Next <i
                                                class="fa-solid fa-arrow-right ms-1"></i></button>
                                    </div>
                                </div>

                                <div class="form-step d-none" id="step-2">
                                    <div class="application-step-header">
                                        <div>
                                            <span class="application-step-kicker">Stage 2</span>
                                            <h4 class="application-step-title"><i class="fa-solid fa-users me-2"></i>Family Background</h4>
                                            <p class="application-step-lead">Provide your parent details first, then add sibling information only when applicable. This section follows the physical application form.</p>
                                        </div>
                                        <div class="application-step-badge">
                                            <i class="fa-solid fa-house-user"></i>
                                            Parent and household details
                                        </div>
                                    </div>

                                    <div class="application-section-card">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="mb-0 text-dark">Mother's Details</h5>
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="mother_not_applicable"
                                                        name="mother_not_applicable" value="1" <?php echo $isChecked('mother_not_applicable'); ?>>
                                                    <label class="form-check-label fw-bold"
                                                        for="mother_not_applicable">Not Applicable</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="mother_is_deceased"
                                                        name="mother_is_deceased" value="1" <?php echo $isChecked('mother_is_deceased'); ?>>
                                                    <label class="form-check-label text-danger fw-bold"
                                                        for="mother_is_deceased">Mark as Deceased</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" class="form-control parent-field-mother" name="mother_name"
                                                    placeholder="e.g., Maria Santos Dela Cruz"
                                                    data-live-filter="letters"
                                                    value="<?php echo htmlspecialchars($fieldValue('mother_name')); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Age</label>
                                                <input type="number" class="form-control parent-field-mother"
                                                    name="mother_age" min="0" max="120" step="1" inputmode="numeric"
                                                    value="<?php echo htmlspecialchars($fieldValue('mother_age')); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Occupation</label>
                                                <input type="text" class="form-control parent-field-mother"
                                                    name="mother_occupation"
                                                    value="<?php echo htmlspecialchars($fieldValue('mother_occupation')); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Monthly Income (₱)</label>
                                                <input type="number" step="0.01"
                                                    class="form-control parent-field-mother"
                                                    name="mother_monthly_income" min="0" inputmode="decimal"
                                                    data-live-filter="decimal"
                                                    value="<?php echo htmlspecialchars($fieldValue('mother_monthly_income')); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="application-section-card">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="mb-0 text-dark">Father's Details</h5>
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="father_not_applicable"
                                                        name="father_not_applicable" value="1" <?php echo $isChecked('father_not_applicable'); ?>>
                                                    <label class="form-check-label fw-bold"
                                                        for="father_not_applicable">Not Applicable</label>
                                                </div>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="father_is_deceased"
                                                        name="father_is_deceased" value="1" <?php echo $isChecked('father_is_deceased'); ?>>
                                                    <label class="form-check-label text-danger fw-bold"
                                                        for="father_is_deceased">Mark as Deceased</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" class="form-control parent-field-father" name="father_name"
                                                    placeholder="e.g., Juan Reyes Dela Cruz"
                                                    data-live-filter="letters"
                                                    value="<?php echo htmlspecialchars($fieldValue('father_name')); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Age</label>
                                                <input type="number" class="form-control parent-field-father"
                                                    name="father_age" min="0" max="120" step="1" inputmode="numeric"
                                                    value="<?php echo htmlspecialchars($fieldValue('father_age')); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Occupation</label>
                                                <input type="text" class="form-control parent-field-father"
                                                    name="father_occupation"
                                                    value="<?php echo htmlspecialchars($fieldValue('father_occupation')); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Monthly Income (₱)</label>
                                                <input type="number" step="0.01"
                                                    class="form-control parent-field-father"
                                                    name="father_monthly_income" min="0" inputmode="decimal"
                                                    data-live-filter="decimal"
                                                    value="<?php echo htmlspecialchars($fieldValue('father_monthly_income')); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="application-section-card">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                            <div>
                                                <h5 class="mb-1 text-dark">Siblings</h5>
                                                <p class="small text-muted mb-0">Add sibling details as shown in the physical application form.</p>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="siblings_na"
                                                        name="siblings_na" value="1" <?php echo $isChecked('siblings_na'); ?>>
                                                    <label class="form-check-label fw-semibold" for="siblings_na">No siblings / Not applicable</label>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="add-sibling-row-btn">
                                                    <i class="fa-solid fa-plus me-1"></i>Add Sibling
                                                </button>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th style="width: 90px;">Age</th>
                                                        <th>Highest Educational Attainment</th>
                                                        <th>Occupation</th>
                                                        <th style="width: 150px;">Monthly Income</th>
                                                        <th style="width: 64px;" class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="siblings-table-body">
                                                    <?php foreach ($siblingsRows as $index => $siblingRow): ?>
                                                    <tr class="sibling-row">
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm sibling-required"
                                                                name="siblings[<?php echo (int) $index; ?>][name]" placeholder="Full name" required
                                                                data-live-filter="letters"
                                                                value="<?php echo htmlspecialchars((string) ($siblingRow['name'] ?? '')); ?>">
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm sibling-required"
                                                                name="siblings[<?php echo (int) $index; ?>][age]" min="0" max="120" step="1" inputmode="numeric" required
                                                                value="<?php echo htmlspecialchars((string) ($siblingRow['age'] ?? '')); ?>">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm sibling-required"
                                                                name="siblings[<?php echo (int) $index; ?>][education]" placeholder="e.g., College" required
                                                                value="<?php echo htmlspecialchars((string) ($siblingRow['education'] ?? '')); ?>">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm sibling-required"
                                                                name="siblings[<?php echo (int) $index; ?>][occupation]" placeholder="e.g., Student" required
                                                                data-live-filter="letters"
                                                                value="<?php echo htmlspecialchars((string) ($siblingRow['occupation'] ?? '')); ?>">
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" class="form-control form-control-sm sibling-required"
                                                                name="siblings[<?php echo (int) $index; ?>][income]" min="0" inputmode="decimal" required
                                                                data-live-filter="decimal"
                                                                value="<?php echo htmlspecialchars((string) ($siblingRow['income'] ?? '')); ?>">
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-sibling-row-btn"
                                                                aria-label="Remove sibling row">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="application-step-actions">
                                        <button type="button" class="btn btn-secondary btn-prev px-4 fw-bold"><i
                                                class="fa-solid fa-arrow-left me-1"></i> Previous</button>
                                        <button type="button" class="btn btn-primary btn-next px-4 fw-bold">Next <i
                                                class="fa-solid fa-arrow-right ms-1"></i></button>
                                    </div>
                                </div>

                                <div class="form-step d-none" id="step-3">
                                    <div class="application-step-header">
                                        <div>
                                            <span class="application-step-kicker">Stage 3</span>
                                            <h4 class="application-step-title"><i class="fa-solid fa-graduation-cap me-2"></i>Education and Scholarship History</h4>
                                            <p class="application-step-lead">Confirm your current school details, complete your educational background, and list previous scholarships only when applicable.</p>
                                        </div>
                                        <div class="application-step-badge">
                                            <i class="fa-solid fa-building-columns"></i>
                                            Academic record and grant history
                                        </div>
                                    </div>

                                    <div class="application-section-card">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">School Type *</label>
                                            <select class="form-select" name="school_type" required>
                                                <option value="">Select Type...</option>
                                                <option value="Public" <?php echo $isSelected('school_type', 'Public'); ?>>Public / State University (SUC)</option>
                                                <option value="Private" <?php echo $isSelected('school_type', 'Private'); ?>>Private Institution</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label fw-bold">Name of College / University *</label>
                                            <select class="form-select" name="school_name" id="school_name" required>
                                                <option value="">Select School</option>
                                                <?php foreach ($schoolNameOptions as $schoolOption): ?>
                                                    <option value="<?php echo htmlspecialchars((string) $schoolOption); ?>" <?php echo $selectedSchoolName === $schoolOption ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars((string) $schoolOption); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="__other__" <?php echo $selectedSchoolName === '__other__' ? 'selected' : ''; ?>>Others</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 <?php echo $selectedSchoolName === '__other__' ? '' : 'd-none'; ?>" id="school_name_other_wrapper">
                                            <label class="form-label fw-bold">Other School Name *</label>
                                            <input type="text" class="form-control" name="school_name_other" id="school_name_other"
                                                placeholder="Type if not listed" value="<?php echo htmlspecialchars((string) $otherSchoolName); ?>"
                                                data-field-label="Other School Name"
                                                <?php echo $selectedSchoolName === '__other__' ? 'required' : ''; ?>>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label fw-bold">Course / Degree Program *</label>
                                            <select class="form-select" name="course" id="course" required>
                                                <option value="">Select Course</option>
                                                <?php foreach ($courseOptions as $courseOption): ?>
                                                    <option value="<?php echo htmlspecialchars((string) $courseOption); ?>" <?php echo $selectedCourse === $courseOption ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars((string) $courseOption); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="__other__" <?php echo $selectedCourse === '__other__' ? 'selected' : ''; ?>>Others</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 <?php echo $selectedCourse === '__other__' ? '' : 'd-none'; ?>" id="course_other_wrapper">
                                            <label class="form-label fw-bold">Other Course *</label>
                                            <input type="text" class="form-control" name="course_other" id="course_other"
                                                placeholder="Type if not listed" value="<?php echo htmlspecialchars((string) $otherCourse); ?>"
                                                data-field-label="Other Course"
                                                <?php echo $selectedCourse === '__other__' ? 'required' : ''; ?>>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Year Level *</label>
                                            <select class="form-select" name="year_level" id="year_level" required>
                                                <option value="">Select...</option>
                                                <option value="1st Year" <?php echo $isSelected('year_level', '1st Year'); ?>>1st Year</option>
                                                <option value="2nd Year" <?php echo $isSelected('year_level', '2nd Year'); ?>>2nd Year</option>
                                                <option value="3rd Year" <?php echo $isSelected('year_level', '3rd Year'); ?>>3rd Year</option>
                                                <option value="4th Year" <?php echo $isSelected('year_level', '4th Year'); ?>>4th Year</option>
                                                <option value="5th Year" <?php echo $isSelected('year_level', '5th Year'); ?>>5th Year</option>
                                            </select>
                                        </div>
                                    </div>
                                    </div>

                                    <div class="application-section-card">
                                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                            <div>
                                                <h5 class="mb-1 text-dark">Educational Background</h5>
                                                <p class="small text-muted mb-0">Fill in the same school history shown in the physical application form.</p>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-lg-4">
                                                <div class="border rounded p-3 h-100 bg-light-subtle">
                                                    <h6 class="fw-bold mb-3">Elementary</h6>
                                                    <input type="hidden" name="education[0][level]" value="Elementary">
                                                    <div class="mb-3">
                                                        <label class="form-label">School Name *</label>
                                                        <input type="text" class="form-control form-control-sm" name="education[0][school]" required
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[0]['school'] ?? '')); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Year Graduated *</label>
                                                        <input type="number" class="form-control form-control-sm" name="education[0][year]"
                                                            min="1900" max="2100" step="1" inputmode="numeric" required
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[0]['year'] ?? '')); ?>">
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Honors / Awards</label>
                                                        <input type="text" class="form-control form-control-sm" name="education[0][honors]"
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[0]['honors'] ?? '')); ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="border rounded p-3 h-100 bg-light-subtle">
                                                    <h6 class="fw-bold mb-3">High School</h6>
                                                    <input type="hidden" name="education[1][level]" value="High School">
                                                    <div class="mb-3">
                                                        <label class="form-label">School Name *</label>
                                                        <input type="text" class="form-control form-control-sm" name="education[1][school]" required
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[1]['school'] ?? '')); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Year Graduated *</label>
                                                        <input type="number" class="form-control form-control-sm" name="education[1][year]"
                                                            min="1900" max="2100" step="1" inputmode="numeric" required
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[1]['year'] ?? '')); ?>">
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Honors / Awards</label>
                                                        <input type="text" class="form-control form-control-sm" name="education[1][honors]"
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[1]['honors'] ?? '')); ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-4">
                                                <div class="border rounded p-3 h-100 bg-light-subtle">
                                                    <h6 class="fw-bold mb-3">College</h6>
                                                    <input type="hidden" name="education[2][level]" value="College">
                                                    <div class="mb-3">
                                                        <label class="form-label">School Name *</label>
                                                        <input type="text" class="form-control form-control-sm" name="education[2][school]" id="education_college_school" required
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[2]['school'] ?? '')); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Course *</label>
                                                        <input type="text" class="form-control form-control-sm" name="education[2][course]" id="education_college_course" required
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[2]['course'] ?? '')); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Year Level *</label>
                                                        <select class="form-select form-select-sm" name="education[2][year]" id="education_college_year" required>
                                                            <option value="">Select...</option>
                                                            <option value="1" <?php echo (($educationRows[2]['year'] ?? '') === '1') ? 'selected' : ''; ?>>1st Year</option>
                                                            <option value="2" <?php echo (($educationRows[2]['year'] ?? '') === '2') ? 'selected' : ''; ?>>2nd Year</option>
                                                            <option value="3" <?php echo (($educationRows[2]['year'] ?? '') === '3') ? 'selected' : ''; ?>>3rd Year</option>
                                                            <option value="4" <?php echo (($educationRows[2]['year'] ?? '') === '4') ? 'selected' : ''; ?>>4th Year</option>
                                                            <option value="5" <?php echo (($educationRows[2]['year'] ?? '') === '5') ? 'selected' : ''; ?>>5th Year</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="form-label">Honors / Awards</label>
                                                        <input type="text" class="form-control form-control-sm" name="education[2][honors]"
                                                            value="<?php echo htmlspecialchars((string) ($educationRows[2]['honors'] ?? '')); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="application-section-card">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                            <div>
                                                <h5 class="mb-1 text-dark">Previous Scholarships / Grants Availed</h5>
                                                <p class="small text-muted mb-0">Include this only if you previously received another scholarship.</p>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="grants_na"
                                                        name="grants_na" value="1" <?php echo $isChecked('grants_na'); ?>>
                                                    <label class="form-check-label fw-semibold" for="grants_na">Not applicable</label>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="add-grant-row-btn">
                                                    <i class="fa-solid fa-plus me-1"></i>Add Row
                                                </button>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Scholarship Program</th>
                                                        <th>Year / Period</th>
                                                        <th style="width: 64px;" class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="grants-table-body">
                                                    <?php foreach ($grantsRows as $index => $grantRow): ?>
                                                    <tr class="grant-row">
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm grant-required"
                                                                name="grants[<?php echo (int) $index; ?>][program]" placeholder="Program name" required
                                                                value="<?php echo htmlspecialchars((string) ($grantRow['program'] ?? '')); ?>">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm grant-required"
                                                                name="grants[<?php echo (int) $index; ?>][period]" placeholder="e.g., 2024-2025" required
                                                                value="<?php echo htmlspecialchars((string) ($grantRow['period'] ?? '')); ?>">
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-grant-row-btn"
                                                                aria-label="Remove grant row">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="application-step-actions">
                                        <button type="button" class="btn btn-secondary btn-prev px-4 fw-bold"><i
                                                class="fa-solid fa-arrow-left me-1"></i> Previous</button>
                                        <button type="button" class="btn btn-primary btn-next px-4 fw-bold">Next <i
                                                class="fa-solid fa-arrow-right ms-1"></i></button>
                                    </div>
                                </div>

                                <div class="form-step d-none" id="step-4">
                                    <div class="application-step-header">
                                        <div>
                                            <span class="application-step-kicker">Stage 4</span>
                                            <h4 class="application-step-title"><i class="fa-solid fa-file-circle-check me-2"></i>Attachments, Photo, and Signature</h4>
                                            <p class="application-step-lead">Complete the final requirements for this term. Your uploads and signature here will be used in review and on the printable application form.</p>
                                        </div>
                                        <div class="application-step-badge">
                                            <i class="fa-solid fa-paperclip"></i>
                                            Prepare final submission requirements
                                        </div>
                                    </div>

                                    <div class="alert alert-light border shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                                        <div>
                                            <div class="fw-bold text-dark">Final submission step</div>
                                            <div class="small text-muted">Complete the three sections below before submitting your scholarship application.</div>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <span class="badge rounded-pill text-bg-secondary" id="documents-status-badge">Documents Pending</span>
                                            <span class="badge rounded-pill text-bg-secondary" id="photo-status-badge">2x2 Photo Pending</span>
                                            <span class="badge rounded-pill text-bg-secondary" id="signature-status-badge">E-Signature Pending</span>
                                        </div>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-12">
                                            <div class="application-section-card">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                                    <div>
                                                        <h6 class="fw-bold mb-1"><i
                                                                class="fa-solid fa-upload text-primary me-2"></i>A. Document Uploads</h6>
                                                        <p class="small text-muted mb-0">Allowed types: JPG, PNG, PDF. Maximum file size: 2MB.</p>
                                                    </div>
                                                    <span class="badge rounded-pill text-bg-secondary" id="documents-card-badge">Pending</span>
                                                </div>

                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold">Previous Grades / Report Card *</label>
                                                        <input type="file" class="form-control" name="grades_file" id="grades_file"
                                                            accept=".jpg,.jpeg,.png,.pdf" required>
                                                        <div class="form-text">Allowed types: JPG, PNG, PDF. Maximum file size: 2MB.</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold">Certificate of Barangay Residency *</label>
                                                        <input type="file" class="form-control" name="residency_file" id="residency_file"
                                                            accept=".jpg,.jpeg,.png,.pdf" required>
                                                        <div class="form-text">Allowed types: JPG, PNG, PDF. Maximum file size: 2MB.</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-5">
                                            <div class="application-section-card text-center h-100">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 text-start">
                                                    <div>
                                                        <h6 class="fw-bold mb-1"><i
                                                                class="fa-solid fa-camera text-primary me-2"></i>B. 2x2 ID Picture</h6>
                                                        <p class="small text-muted mb-0">Capture your 2x2 photo, open the larger camera view on mobile, or upload a recent square photo with a plain background.</p>
                                                    </div>
                                                    <span class="badge rounded-pill text-bg-secondary" id="photo-card-badge">Pending</span>
                                                </div>

                                                <div class="bg-dark mx-auto d-flex justify-content-center align-items-center rounded mb-3 overflow-hidden"
                                                    style="width: 200px; height: 200px;">
                                                    <video id="camera-stream" autoplay playsinline
                                                        style="display: none; width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                                                    <canvas id="camera-canvas"
                                                        style="display: none; width: 100%; height: 100%; object-fit: cover;"></canvas>
                                                    <i class="fa-solid fa-user text-secondary" id="camera-placeholder"
                                                        style="font-size: 5rem;"></i>
                                                </div>

                                                <div class="d-flex justify-content-center flex-wrap gap-2">
                                                    <button type="button" id="start-camera-btn"
                                                        class="btn btn-sm btn-outline-primary fw-bold"><i
                                                            class="fa-solid fa-video me-1"></i> Open Camera</button>
                                                    <button type="button" id="open-camera-modal-btn"
                                                        class="btn btn-sm btn-outline-secondary fw-bold"><i
                                                            class="fa-solid fa-expand me-1"></i>Large View</button>
                                                    <label for="id_picture_upload" class="btn btn-sm btn-outline-secondary fw-bold mb-0">
                                                        <i class="fa-solid fa-upload me-1"></i> Upload Photo
                                                    </label>
                                                    <input type="file" class="d-none" id="id_picture_upload" name="id_picture_upload"
                                                        accept=".png,.jpg,.jpeg">
                                                    <button type="button" id="snap-btn"
                                                        class="btn btn-sm btn-success fw-bold" style="display: none;"><i
                                                            class="fa-solid fa-camera me-1"></i> Take Photo</button>
                                                    <button type="button" id="retake-btn"
                                                        class="btn btn-sm btn-warning fw-bold" style="display: none;"><i
                                                            class="fa-solid fa-rotate-right me-1"></i> Retake</button>
                                                </div>
                                                <div class="small text-muted mt-2">Allowed types: JPG, PNG. Maximum file size: 2MB.</div>
                                                <div class="small text-muted mt-2" id="photo-source-note">Use a clear recent photo with your face centered, shoulders visible, and a plain background.</div>

                                                <input type="hidden" id="id_picture_base64" name="id_picture_base64"
                                                    required>
                                            </div>
                                        </div>

                                        <div class="col-lg-7">
                                            <div class="application-section-card h-100">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                                    <div>
                                                        <h6 class="fw-bold mb-1"><i
                                                                class="fa-solid fa-pen-nib text-primary me-2"></i>C. E-Signature</h6>
                                                        <p class="small text-muted mb-0">Draw your signature below, open the full-screen pad for a larger signing area, or upload a clear image of your signature.</p>
                                                    </div>
                                                    <span class="badge rounded-pill text-bg-secondary" id="signature-card-badge">Pending</span>
                                                </div>

                                                <div class="border rounded bg-light"
                                                    style="position: relative; height: 220px;">
                                                    <canvas id="signature-canvas"
                                                        style="width: 100%; height: 100%; cursor: crosshair;"></canvas>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <button type="button" id="open-signature-modal-btn"
                                                            class="btn btn-sm btn-outline-primary"><i
                                                                class="fa-solid fa-up-right-and-down-left-from-center me-1"></i>Fullscreen</button>
                                                        <label for="signature_upload" class="btn btn-sm btn-outline-secondary mb-0">
                                                            <i class="fa-solid fa-upload me-1"></i>Upload Signature Image
                                                        </label>
                                                        <input type="file" class="d-none" id="signature_upload" name="signature_upload"
                                                            accept=".png,.jpg,.jpeg">
                                                    </div>
                                                    <button type="button" id="clear-signature-btn"
                                                        class="btn btn-sm btn-outline-danger"><i
                                                            class="fa-solid fa-eraser me-1"></i> Clear
                                                        Signature</button>
                                                </div>
                                                <div class="small text-muted mt-2">Allowed types: JPG, PNG. Maximum file size: 2MB.</div>
                                                <div class="small text-muted mt-2" id="signature-source-note">Draw directly in the box, use the full-screen pad, or upload a PNG/JPG image of your signature.</div>

                                                <input type="hidden" id="e_signature_base64" name="e_signature_base64"
                                                    required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="application-step-actions">
                                        <button type="button" class="btn btn-secondary btn-prev px-4 fw-bold"><i
                                                class="fa-solid fa-arrow-left me-1"></i> Previous</button>
                                        <button type="button" class="btn btn-primary btn-next px-4 fw-bold">Review <i
                                                class="fa-solid fa-arrow-right ms-1"></i></button>
                                    </div>
                                </div>

                                <div class="form-step d-none" id="step-5">
                                    <div class="application-step-header">
                                        <div>
                                            <span class="application-step-kicker">Stage 5</span>
                                            <h4 class="application-step-title"><i class="fa-solid fa-clipboard-check me-2"></i>Review, Privacy, and Certification</h4>
                                            <p class="application-step-lead">Check each section one last time before final submission. You can still return to any earlier stage if something needs to be corrected.</p>
                                        </div>
                                        <div class="application-step-badge">
                                            <i class="fa-solid fa-shield-heart"></i>
                                            Final confirmation before submission
                                        </div>
                                    </div>

                                    <div class="alert alert-light border shadow-sm mb-4">
                                        <div class="fw-bold text-dark">Review your application before submitting.</div>
                                        <div class="small text-muted">Please confirm that the details below are correct and complete. You can still go back to make changes before final submission.</div>
                                    </div>

                                    <div class="application-review-actions">
                                        <button type="button" class="btn btn-outline-secondary btn-sm application-edit-step" data-go-step="0">Edit Personal</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm application-edit-step" data-go-step="1">Edit Family</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm application-edit-step" data-go-step="2">Edit Education</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm application-edit-step" data-go-step="3">Edit Uploads</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" disabled title="Printable PDF preview becomes available after submission.">
                                            <i class="fa-solid fa-print me-1"></i>Printable PDF Available After Submission
                                        </button>
                                    </div>

                                    <div class="application-review-grid">
                                        <div class="application-review-card">
                                                <h6 class="fw-bold mb-3 text-dark">Personal Information</h6>
                                                <div class="small application-review-summary" id="review-personal-summary"></div>
                                        </div>
                                        <div class="application-review-card">
                                                <h6 class="fw-bold mb-3 text-dark">Family and Address</h6>
                                                <div class="small application-review-summary" id="review-family-summary"></div>
                                        </div>
                                        <div class="application-review-card">
                                                <h6 class="fw-bold mb-3 text-dark">Education and Grants</h6>
                                                <div class="small application-review-summary" id="review-education-summary"></div>
                                        </div>
                                        <div class="application-review-card">
                                                <h6 class="fw-bold mb-3 text-dark">Attachments and Final Checks</h6>
                                                <div class="small application-review-summary" id="review-attachments-summary"></div>
                                        </div>
                                    </div>

                                    <div class="application-section-card">
                                        <h6 class="fw-bold mb-3 text-dark">Data Privacy Notice</h6>
                                        <p class="small text-muted mb-2">
                                            In accordance with Republic Act No. 10173, or the Data Privacy Act of 2012, the Municipality of San Enrique will collect, process, store, and use your personal information, supporting documents, 2x2 photo, and electronic signature solely for scholarship application evaluation, verification, records management, and related LGU scholarship services.
                                        </p>
                                        <p class="small text-muted mb-3">
                                            Your information will only be accessed by authorized LGU personnel and will be retained only for lawful government and scholarship administration purposes, subject to applicable records retention and auditing requirements.
                                        </p>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" value="1" id="privacy_consent" name="privacy_consent" required>
                                            <label class="form-check-label" for="privacy_consent">
                                                I have read and understood the Data Privacy Notice, and I consent to the collection, processing, storage, and use of my personal data for LGU scholarship application and records management purposes.
                                            </label>
                                        </div>
                                    </div>

                                    <div class="application-section-card">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="certify_application" required>
                                            <label class="form-check-label" for="certify_application">
                                                I certify that all information provided in this application and the attached documents are true, complete, and accurate to the best of my knowledge.
                                            </label>
                                        </div>
                                    </div>

                                    <div class="application-step-actions">
                                        <button type="button" class="btn btn-secondary btn-prev px-4 fw-bold"><i
                                                class="fa-solid fa-arrow-left me-1"></i> Previous</button>
                                        <button type="submit" class="btn btn-success btn-lg px-5 fw-bold shadow"><i
                                                class="fa-solid fa-paper-plane me-2"></i> Submit Application</button>
                                    </div>
                                </div>

                                </div>
                            </form>
                            <div class="alert alert-light border shadow-sm mt-4 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-3" id="application-draft-banner">
                                <div>
                                    <div class="fw-bold text-dark">Draft autosave</div>
                                    <div class="small text-muted" id="application-draft-status">Your form draft is saved automatically in this browser while you fill it out.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-application-draft-btn">
                                    <i class="fa-solid fa-trash-can me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="camera-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">2x2 Photo Capture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p class="small text-muted mb-3">Use the larger preview below to capture a clearer 2x2 photo. Face the camera directly, keep good lighting, and use a plain background.</p>
                <div class="bg-dark mx-auto d-flex justify-content-center align-items-center rounded overflow-hidden"
                    style="width: min(90vw, 420px); aspect-ratio: 1 / 1;">
                    <video id="camera-modal-stream" autoplay playsinline
                        style="display: none; width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                    <canvas id="camera-modal-canvas"
                        style="display: none; width: 100%; height: 100%; object-fit: cover;"></canvas>
                    <i class="fa-solid fa-user text-secondary" id="camera-modal-placeholder"
                        style="font-size: 6rem;"></i>
                </div>
            </div>
            <div class="modal-footer justify-content-between flex-wrap gap-2">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" id="start-camera-modal-btn">
                        <i class="fa-solid fa-video me-1"></i>Open Camera
                    </button>
                    <button type="button" class="btn btn-success" id="snap-modal-btn" style="display: none;">
                        <i class="fa-solid fa-camera me-1"></i>Take Photo
                    </button>
                    <button type="button" class="btn btn-warning" id="retake-modal-btn" style="display: none;">
                        <i class="fa-solid fa-rotate-right me-1"></i>Retake
                    </button>
                </div>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="signature-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Full-Screen Signature Pad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">Sign using the larger pad below. When you are done, save the signature to use it in your application.</p>
                <div class="border rounded bg-light" style="position: relative; height: min(70vh, 460px);">
                    <canvas id="signature-modal-canvas" style="width: 100%; height: 100%; cursor: crosshair;"></canvas>
                </div>
            </div>
            <div class="modal-footer justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-outline-danger" id="clear-signature-modal-btn">
                    <i class="fa-solid fa-eraser me-1"></i>Clear
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-signature-modal-btn">
                        <i class="fa-solid fa-check me-1"></i>Use This Signature
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        // ---------------------------------------------------------
        // 1. MULTI-STEP FORM LOGIC
        // ---------------------------------------------------------
        const steps = document.querySelectorAll('.form-step');
        const nextBtns = document.querySelectorAll('.btn-next');
        const prevBtns = document.querySelectorAll('.btn-prev');
        const progressBar = document.getElementById('form-progress');
        const stagePills = document.querySelectorAll('[data-stage-pill]');
        const editStepButtons = document.querySelectorAll('.application-edit-step');
        const applicationForm = document.getElementById('student-application-form');
        const clearDraftButton = document.getElementById('clear-application-draft-btn');
        const draftStatusElement = document.getElementById('application-draft-status');
        let currentStep = 0;
        const draftStorageKey = applicationForm
            ? `student-application-draft:${applicationForm.dataset.draftUserId || '0'}:${applicationForm.dataset.draftSchoolYear || 'na'}:${applicationForm.dataset.draftSemester || 'na'}`
            : '';
        let saveDraftTimeout = null;
        let isRestoringDraft = false;

        function updateDraftStatus(message, tone = 'muted') {
            if (!draftStatusElement) {
                return;
            }

            draftStatusElement.textContent = message;
            draftStatusElement.classList.remove('text-muted', 'text-success', 'text-danger');
            if (tone === 'success') {
                draftStatusElement.classList.add('text-success');
            } else if (tone === 'danger') {
                draftStatusElement.classList.add('text-danger');
            } else {
                draftStatusElement.classList.add('text-muted');
            }
        }

        function formatDraftTimestamp(timestamp) {
            if (!timestamp) {
                return '';
            }

            const savedDate = new Date(timestamp);
            if (Number.isNaN(savedDate.getTime())) {
                return '';
            }

            return savedDate.toLocaleString([], {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
        }

        function saveDraft() {
            if (!applicationForm || !draftStorageKey || isRestoringDraft) {
                return;
            }

            const values = {};
            applicationForm.querySelectorAll('input, select, textarea').forEach((field) => {
                if (!(field instanceof HTMLElement) || !field.name || field.type === 'file') {
                    return;
                }

                if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                    values[field.name] = field.checked ? (field.value || '1') : '';
                    return;
                }

                values[field.name] = field.value ?? '';
            });

            const payload = {
                savedAt: new Date().toISOString(),
                currentStep,
                values,
            };

            try {
                window.localStorage.setItem(draftStorageKey, JSON.stringify(payload));
                const formattedTimestamp = formatDraftTimestamp(payload.savedAt);
                updateDraftStatus(
                    formattedTimestamp
                        ? `Draft saved automatically on ${formattedTimestamp}. File uploads still need to be selected again.`
                        : 'Draft saved automatically in this browser. File uploads still need to be selected again.',
                    'success'
                );
            } catch (error) {
                updateDraftStatus('Draft autosave is unavailable in this browser right now.', 'danger');
            }
        }

        function scheduleDraftSave() {
            if (saveDraftTimeout) {
                window.clearTimeout(saveDraftTimeout);
            }

            saveDraftTimeout = window.setTimeout(saveDraft, 250);
        }

        function clearDraft({ keepStatus = false } = {}) {
            if (!draftStorageKey) {
                return;
            }

            try {
                window.localStorage.removeItem(draftStorageKey);
                if (!keepStatus) {
                    updateDraftStatus('Draft cleared. Your form will continue from the values currently shown on the page.', 'muted');
                }
            } catch (error) {
                updateDraftStatus('Unable to clear the saved draft in this browser.', 'danger');
            }
        }

        function getDraftRowCount(values, groupName) {
            const pattern = new RegExp(`^${groupName}\\[(\\d+)\\]\\[`);
            let highestIndex = -1;

            Object.keys(values).forEach((key) => {
                const match = key.match(pattern);
                if (match) {
                    highestIndex = Math.max(highestIndex, parseInt(match[1], 10));
                }
            });

            return highestIndex + 1;
        }

        function updateForm() {
            steps.forEach((step, index) => {
                step.classList.toggle('d-none', index !== currentStep);
                step.classList.toggle('active', index === currentStep);
            });

            document.dispatchEvent(new CustomEvent('application-form-step-change', {
                detail: { currentStep }
            }));

            stagePills.forEach((pill, index) => {
                pill.classList.toggle('is-active', index === currentStep);
                pill.classList.toggle('is-complete', index < currentStep);
            });

            // Update Progress Bar
            let progressPercent = ((currentStep + 1) / steps.length) * 100;
            progressBar.style.width = progressPercent + '%';
            progressBar.innerText = `Step ${currentStep + 1} of ${steps.length}`;

            if (currentStep === steps.length - 1) {
                updateReviewSummary();
            }

            scheduleDraftSave();
        }

        nextBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Basic HTML5 Validation Check before moving to next step
                const currentInputs = steps[currentStep].querySelectorAll('input, select, textarea');
                const isValid = window.AppLiveValidation
                    ? window.AppLiveValidation.validateFields(currentInputs, { touch: true, focusFirst: true })
                    : Array.from(currentInputs).every((input) => input.checkValidity());

                if (isValid && currentStep < steps.length - 1) {
                    currentStep++;
                    updateForm();
                }
            });
        });

        prevBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                if (currentStep > 0) {
                    currentStep--;
                    updateForm();
                }
            });
        });

        editStepButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetStep = parseInt(button.getAttribute('data-go-step') || '', 10);
                if (!Number.isNaN(targetStep) && targetStep >= 0 && targetStep < steps.length) {
                    currentStep = targetStep;
                    updateForm();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });

        // ---------------------------------------------------------
        // 2. PARENT STATE TOGGLES (Not Applicable / Deceased)
        // ---------------------------------------------------------
        function handleParentState(options) {
            const deceasedCheckbox = document.getElementById(options.deceasedId);
            const notApplicableCheckbox = document.getElementById(options.notApplicableId);
            const inputs = document.querySelectorAll('.' + options.inputClass);

            if (!deceasedCheckbox || !notApplicableCheckbox) {
                return;
            }

            function applyState(source) {
                if (source === 'deceased' && deceasedCheckbox.checked) {
                    notApplicableCheckbox.checked = false;
                }

                if (source === 'na' && notApplicableCheckbox.checked) {
                    deceasedCheckbox.checked = false;
                }

                const isLocked = deceasedCheckbox.checked || notApplicableCheckbox.checked;
                const textValue = deceasedCheckbox.checked ? 'N/A' : (notApplicableCheckbox.checked ? 'N/A' : '');

                inputs.forEach(input => {
                    if (isLocked) {
                        if (input.type === 'number') {
                            input.value = input.name.includes('income') ? '0.00' : '';
                        } else {
                            input.value = textValue;
                        }
                        input.setAttribute('readonly', 'readonly');
                        input.classList.add('bg-light', 'text-muted');
                    } else {
                        input.removeAttribute('readonly');
                        input.classList.remove('bg-light', 'text-muted');
                    }
                });
            }

            deceasedCheckbox.addEventListener('change', () => applyState('deceased'));
            notApplicableCheckbox.addEventListener('change', () => applyState('na'));
            applyState();
        }

        handleParentState({
            deceasedId: 'mother_is_deceased',
            notApplicableId: 'mother_not_applicable',
            inputClass: 'parent-field-mother',
        });
        handleParentState({
            deceasedId: 'father_is_deceased',
            notApplicableId: 'father_not_applicable',
            inputClass: 'parent-field-father',
        });

        // ---------------------------------------------------------
        // 3. SIBLINGS REPEATER
        // ---------------------------------------------------------
        const siblingsTableBody = document.getElementById('siblings-table-body');
        const addSiblingRowBtn = document.getElementById('add-sibling-row-btn');
        const siblingsNaCheckbox = document.getElementById('siblings_na');
        let siblingIndex = siblingsTableBody ? siblingsTableBody.querySelectorAll('.sibling-row').length : 0;

        function buildSiblingRow(index) {
            const row = document.createElement('tr');
            row.className = 'sibling-row';
            row.innerHTML = `
                <td><input type="text" class="form-control form-control-sm sibling-required" name="siblings[${index}][name]" placeholder="Full name" required data-live-filter="letters"></td>
                <td><input type="number" class="form-control form-control-sm sibling-required" name="siblings[${index}][age]" min="0" max="120" step="1" inputmode="numeric" required></td>
                <td><input type="text" class="form-control form-control-sm sibling-required" name="siblings[${index}][education]" placeholder="e.g., College" required></td>
                <td><input type="text" class="form-control form-control-sm sibling-required" name="siblings[${index}][occupation]" placeholder="e.g., Student" required data-live-filter="letters"></td>
                <td><input type="number" step="0.01" class="form-control form-control-sm sibling-required" name="siblings[${index}][income]" min="0" inputmode="decimal" required data-live-filter="decimal"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-sibling-row-btn" aria-label="Remove sibling row"><i class="fa-solid fa-trash-can"></i></button></td>
            `;
            return row;
        }

        function refreshSiblingFieldsState() {
            if (!siblingsTableBody || !siblingsNaCheckbox) {
                return;
            }

            const isDisabled = siblingsNaCheckbox.checked;
            siblingsTableBody.querySelectorAll('input').forEach(input => {
                input.disabled = isDisabled;
                input.required = !isDisabled && input.classList.contains('sibling-required');
                if (isDisabled) {
                    input.value = '';
                }
            });

            if (addSiblingRowBtn) {
                addSiblingRowBtn.disabled = isDisabled;
            }
        }

        if (addSiblingRowBtn && siblingsTableBody) {
            addSiblingRowBtn.addEventListener('click', () => {
                if (siblingsNaCheckbox && siblingsNaCheckbox.checked) {
                    return;
                }
                siblingsTableBody.appendChild(buildSiblingRow(siblingIndex));
                siblingIndex++;
                scheduleDraftSave();
            });

            siblingsTableBody.addEventListener('click', (event) => {
                const removeButton = event.target.closest('.remove-sibling-row-btn');
                if (!removeButton) {
                    return;
                }

                const rows = siblingsTableBody.querySelectorAll('.sibling-row');
                if (rows.length === 1) {
                    rows[0].querySelectorAll('input').forEach(input => {
                        input.value = '';
                    });
                    scheduleDraftSave();
                    return;
                }

                removeButton.closest('.sibling-row')?.remove();
                scheduleDraftSave();
            });
        }

        if (siblingsNaCheckbox) {
            siblingsNaCheckbox.addEventListener('change', () => {
                refreshSiblingFieldsState();
                scheduleDraftSave();
            });
            refreshSiblingFieldsState();
        }

        // ---------------------------------------------------------
        // 4. GRANTS REPEATER
        // ---------------------------------------------------------
        const grantsTableBody = document.getElementById('grants-table-body');
        const addGrantRowBtn = document.getElementById('add-grant-row-btn');
        const grantsNaCheckbox = document.getElementById('grants_na');
        let grantIndex = grantsTableBody ? grantsTableBody.querySelectorAll('.grant-row').length : 0;

        function buildGrantRow(index) {
            const row = document.createElement('tr');
            row.className = 'grant-row';
            row.innerHTML = `
                <td><input type="text" class="form-control form-control-sm grant-required" name="grants[${index}][program]" placeholder="Program name" required></td>
                <td><input type="text" class="form-control form-control-sm grant-required" name="grants[${index}][period]" placeholder="e.g., 2024-2025" required></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-grant-row-btn" aria-label="Remove grant row"><i class="fa-solid fa-trash-can"></i></button></td>
            `;
            return row;
        }

        function refreshGrantFieldsState() {
            if (!grantsTableBody || !grantsNaCheckbox) {
                return;
            }

            const isDisabled = grantsNaCheckbox.checked;
            grantsTableBody.querySelectorAll('input').forEach(input => {
                input.disabled = isDisabled;
                input.required = !isDisabled && input.classList.contains('grant-required');
                if (isDisabled) {
                    input.value = '';
                }
            });

            if (addGrantRowBtn) {
                addGrantRowBtn.disabled = isDisabled;
            }
        }

        if (addGrantRowBtn && grantsTableBody) {
            addGrantRowBtn.addEventListener('click', () => {
                if (grantsNaCheckbox && grantsNaCheckbox.checked) {
                    return;
                }
                grantsTableBody.appendChild(buildGrantRow(grantIndex));
                grantIndex++;
                scheduleDraftSave();
            });

            grantsTableBody.addEventListener('click', (event) => {
                const removeButton = event.target.closest('.remove-grant-row-btn');
                if (!removeButton) {
                    return;
                }

                const rows = grantsTableBody.querySelectorAll('.grant-row');
                if (rows.length === 1) {
                    rows[0].querySelectorAll('input').forEach(input => {
                        input.value = '';
                    });
                    scheduleDraftSave();
                    return;
                }

                removeButton.closest('.grant-row')?.remove();
                scheduleDraftSave();
            });
        }

        if (grantsNaCheckbox) {
            grantsNaCheckbox.addEventListener('change', () => {
                refreshGrantFieldsState();
                scheduleDraftSave();
            });
            refreshGrantFieldsState();
        }

        // ---------------------------------------------------------
        // 5. EDUCATION FIELD SYNC
        // ---------------------------------------------------------
        const schoolNameInput = document.getElementById('school_name');
        const schoolNameOtherInput = document.getElementById('school_name_other');
        const schoolNameOtherWrapper = document.getElementById('school_name_other_wrapper');
        const courseInput = document.getElementById('course');
        const courseOtherInput = document.getElementById('course_other');
        const courseOtherWrapper = document.getElementById('course_other_wrapper');
        const yearLevelInput = document.getElementById('year_level');
        const educationCollegeSchool = document.getElementById('education_college_school');
        const educationCollegeCourse = document.getElementById('education_college_course');
        const educationCollegeYear = document.getElementById('education_college_year');

        function getResolvedAcademicValue(selectElement, otherElement) {
            if (!selectElement) {
                return '';
            }

            if (selectElement.value === '__other__') {
                return otherElement?.value ?? '';
            }

            return selectElement.value ?? '';
        }

        function toggleOtherAcademicField(selectElement, wrapperElement, otherElement) {
            if (!selectElement || !wrapperElement || !otherElement) {
                return;
            }

            const isOther = selectElement.value === '__other__';
            wrapperElement.classList.toggle('d-none', !isOther);
            otherElement.required = isOther;

            if (!isOther) {
                otherElement.value = '';
            }
        }

        function mapYearLevel(value) {
            switch (value) {
                case '1st Year':
                    return '1';
                case '2nd Year':
                    return '2';
                case '3rd Year':
                    return '3';
                case '4th Year':
                    return '4';
                case '5th Year':
                    return '5';
                default:
                    return '';
            }
        }

        function syncEducationFields() {
            const resolvedSchoolName = getResolvedAcademicValue(schoolNameInput, schoolNameOtherInput);
            const resolvedCourse = getResolvedAcademicValue(courseInput, courseOtherInput);

            if (resolvedSchoolName !== '' && educationCollegeSchool && educationCollegeSchool.value.trim() === '') {
                educationCollegeSchool.value = resolvedSchoolName;
            }
            if (resolvedCourse !== '' && educationCollegeCourse && educationCollegeCourse.value.trim() === '') {
                educationCollegeCourse.value = resolvedCourse;
            }
            if (yearLevelInput && educationCollegeYear && educationCollegeYear.value === '') {
                educationCollegeYear.value = mapYearLevel(yearLevelInput.value);
            }
        }

        schoolNameInput?.addEventListener('change', () => {
            toggleOtherAcademicField(schoolNameInput, schoolNameOtherWrapper, schoolNameOtherInput);
            window.AppLiveValidation?.validateField(schoolNameInput, { touched: true });
            window.AppLiveValidation?.validateField(schoolNameOtherInput, { force: true });
            syncEducationFields();
        });
        schoolNameOtherInput?.addEventListener('input', () => {
            syncEducationFields();
            window.AppLiveValidation?.validateField(schoolNameOtherInput, { touched: true });
        });
        courseInput?.addEventListener('change', () => {
            toggleOtherAcademicField(courseInput, courseOtherWrapper, courseOtherInput);
            window.AppLiveValidation?.validateField(courseInput, { touched: true });
            window.AppLiveValidation?.validateField(courseOtherInput, { force: true });
            syncEducationFields();
        });
        courseOtherInput?.addEventListener('input', () => {
            syncEducationFields();
            window.AppLiveValidation?.validateField(courseOtherInput, { touched: true });
        });
        yearLevelInput?.addEventListener('change', syncEducationFields);
        toggleOtherAcademicField(schoolNameInput, schoolNameOtherWrapper, schoolNameOtherInput);
        toggleOtherAcademicField(courseInput, courseOtherWrapper, courseOtherInput);
        syncEducationFields();

        // ---------------------------------------------------------
        // 6. FINAL STEP COMPLETION BADGES
        // ---------------------------------------------------------
        const gradesFileInput = document.getElementById('grades_file');
        const residencyFileInput = document.getElementById('residency_file');
        const idPictureInput = document.getElementById('id_picture_base64');
        const idPictureUploadInput = document.getElementById('id_picture_upload');
        const signatureInput = document.getElementById('e_signature_base64');
        const signatureUploadInput = document.getElementById('signature_upload');
        const reviewPersonalSummary = document.getElementById('review-personal-summary');
        const reviewFamilySummary = document.getElementById('review-family-summary');
        const reviewEducationSummary = document.getElementById('review-education-summary');
        const reviewAttachmentsSummary = document.getElementById('review-attachments-summary');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function readFieldValue(selector) {
            const element = document.querySelector(selector);
            if (!element) {
                return '';
            }

            if (element.tagName === 'SELECT') {
                return element.options[element.selectedIndex]?.text ?? '';
            }

            return element.value ?? '';
        }

        function calculateAgeFromBirthDate(dateValue) {
            if (!dateValue) {
                return '';
            }

            const birthDate = new Date(dateValue + 'T00:00:00');
            if (Number.isNaN(birthDate.getTime())) {
                return '';
            }

            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDifference = today.getMonth() - birthDate.getMonth();

            if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            if (age < 0 || age > 130) {
                return '';
            }

            return String(age);
        }

        const dateOfBirthInput = document.querySelector('input[name="date_of_birth"]');
        const calculatedAgeInput = document.getElementById('calculated_age');

        function updateCalculatedAge() {
            if (!calculatedAgeInput) {
                return;
            }

            calculatedAgeInput.value = calculateAgeFromBirthDate(dateOfBirthInput?.value ?? '');
        }

        dateOfBirthInput?.addEventListener('input', updateCalculatedAge);
        dateOfBirthInput?.addEventListener('change', updateCalculatedAge);
        updateCalculatedAge();

        function formatReviewLine(label, value) {
            const displayValue = String(value).trim() === '' ? 'Not provided' : String(value).trim();
            return `<div class="mb-1"><strong>${escapeHtml(label)}:</strong> ${escapeHtml(displayValue)}</div>`;
        }

        function updateReviewSummary() {
            const siblingsCount = siblingsNaCheckbox?.checked
                ? 'Not applicable'
                : String(document.querySelectorAll('#siblings-table-body .sibling-row').length);
            const grantsCount = grantsNaCheckbox?.checked
                ? 'Not applicable'
                : String(document.querySelectorAll('#grants-table-body .grant-row').length);
            const documentsComplete = Boolean(gradesFileInput?.files?.length) && Boolean(residencyFileInput?.files?.length);
            const photoComplete = Boolean(idPictureInput?.value.trim()) || Boolean(idPictureUploadInput?.files?.length);
            const signatureComplete = Boolean(signatureInput?.value.trim()) || Boolean(signatureUploadInput?.files?.length);

            if (reviewPersonalSummary) {
                reviewPersonalSummary.innerHTML = [
                    formatReviewLine('Applicant Type', '<?php echo htmlspecialchars((string) ($detectedApplicationType ?? 'New')); ?>'),
                    formatReviewLine('Full Name', `${readFieldValue('input[name="last_name"]')}, ${readFieldValue('input[name="first_name"]')} ${readFieldValue('input[name="middle_name"]')} ${readFieldValue('select[name="suffix"]')}`),
                    formatReviewLine('Email Address', readFieldValue('input[name="email"]')),
                    formatReviewLine('Date of Birth', readFieldValue('input[name="date_of_birth"]')),
                    formatReviewLine('Age', calculateAgeFromBirthDate(readFieldValue('input[name="date_of_birth"]'))),
                    formatReviewLine('Place of Birth', readFieldValue('input[name="place_of_birth"]')),
                    formatReviewLine('Sex', readFieldValue('select[name="sex"]')),
                    formatReviewLine('Civil Status', readFieldValue('select[name="civil_status"]')),
                ].join('');
            }

            if (reviewFamilySummary) {
                reviewFamilySummary.innerHTML = [
                    formatReviewLine('Address', `${readFieldValue('input[name="address_line"]')}, Brgy. ${readFieldValue('select[name="address_barangay"]')}, San Enrique, Negros Occidental`),
                    formatReviewLine('Mother', readFieldValue('input[name="mother_name"]') || (document.getElementById('mother_is_deceased')?.checked ? 'Deceased' : (document.getElementById('mother_not_applicable')?.checked ? 'Not Applicable' : 'Not provided'))),
                    formatReviewLine('Mother Age', readFieldValue('input[name="mother_age"]') || (document.getElementById('mother_is_deceased')?.checked ? 'Deceased' : (document.getElementById('mother_not_applicable')?.checked ? 'Not Applicable' : 'Not provided'))),
                    formatReviewLine('Father', readFieldValue('input[name="father_name"]') || (document.getElementById('father_is_deceased')?.checked ? 'Deceased' : (document.getElementById('father_not_applicable')?.checked ? 'Not Applicable' : 'Not provided'))),
                    formatReviewLine('Father Age', readFieldValue('input[name="father_age"]') || (document.getElementById('father_is_deceased')?.checked ? 'Deceased' : (document.getElementById('father_not_applicable')?.checked ? 'Not Applicable' : 'Not provided'))),
                    formatReviewLine('Sibling Entries', siblingsCount),
                ].join('');
            }

            if (reviewEducationSummary) {
                reviewEducationSummary.innerHTML = [
                    formatReviewLine('School Type', readFieldValue('select[name="school_type"]')),
                    formatReviewLine('College / University', getResolvedAcademicValue(schoolNameInput, schoolNameOtherInput)),
                    formatReviewLine('Course', getResolvedAcademicValue(courseInput, courseOtherInput)),
                    formatReviewLine('Year Level', readFieldValue('select[name="year_level"]')),
                    formatReviewLine('Previous Grants', grantsCount),
                ].join('');
            }

            if (reviewAttachmentsSummary) {
                reviewAttachmentsSummary.innerHTML = [
                    formatReviewLine('Grades File', gradesFileInput?.files?.[0]?.name ?? (documentsComplete ? 'Attached' : 'Pending')),
                    formatReviewLine('Barangay Residency', residencyFileInput?.files?.[0]?.name ?? (documentsComplete ? 'Attached' : 'Pending')),
                    formatReviewLine('2x2 Photo', idPictureUploadInput?.files?.[0]?.name ?? (photoComplete ? 'Captured' : 'Pending')),
                    formatReviewLine('E-Signature', signatureUploadInput?.files?.[0]?.name ?? (signatureComplete ? 'Completed' : 'Pending')),
                ].join('');
            }
        }

        function setStatusBadge(elementId, isComplete, completeLabel, pendingLabel) {
            const badge = document.getElementById(elementId);
            if (!badge) {
                return;
            }

            badge.textContent = isComplete ? completeLabel : pendingLabel;
            badge.classList.remove('text-bg-secondary', 'text-bg-success');
            badge.classList.add(isComplete ? 'text-bg-success' : 'text-bg-secondary');
        }

        function updateFinalStepStatuses() {
            const documentsComplete = Boolean(gradesFileInput?.files?.length) && Boolean(residencyFileInput?.files?.length);
            const photoComplete = Boolean(idPictureInput?.value.trim()) || Boolean(idPictureUploadInput?.files?.length);
            const signatureComplete = Boolean(signatureInput?.value.trim()) || Boolean(signatureUploadInput?.files?.length);

            setStatusBadge('documents-status-badge', documentsComplete, 'Documents Complete', 'Documents Pending');
            setStatusBadge('photo-status-badge', photoComplete, '2x2 Photo Complete', '2x2 Photo Pending');
            setStatusBadge('signature-status-badge', signatureComplete, 'E-Signature Complete', 'E-Signature Pending');

            setStatusBadge('documents-card-badge', documentsComplete, 'Complete', 'Pending');
            setStatusBadge('photo-card-badge', photoComplete, 'Complete', 'Pending');
            setStatusBadge('signature-card-badge', signatureComplete, 'Complete', 'Pending');
        }

        gradesFileInput?.addEventListener('change', updateFinalStepStatuses);
        residencyFileInput?.addEventListener('change', updateFinalStepStatuses);
        idPictureInput?.addEventListener('change', updateFinalStepStatuses);
        idPictureUploadInput?.addEventListener('change', updateFinalStepStatuses);
        signatureInput?.addEventListener('change', updateFinalStepStatuses);
        signatureUploadInput?.addEventListener('change', updateFinalStepStatuses);
        updateFinalStepStatuses();
        updateReviewSummary();
        window.setInterval(updateFinalStepStatuses, 500);
        window.setInterval(updateReviewSummary, 500);

        function restoreDraft() {
            if (!applicationForm || !draftStorageKey) {
                return;
            }

            let savedPayload = null;
            try {
                savedPayload = JSON.parse(window.localStorage.getItem(draftStorageKey) || 'null');
            } catch (error) {
                savedPayload = null;
            }

            if (!savedPayload || typeof savedPayload !== 'object' || typeof savedPayload.values !== 'object') {
                updateDraftStatus('Your form draft is saved automatically in this browser while you fill it out.', 'muted');
                return;
            }

            isRestoringDraft = true;
            const draftValues = savedPayload.values || {};

            const siblingRowCount = Math.max(getDraftRowCount(draftValues, 'siblings'), siblingsTableBody?.querySelectorAll('.sibling-row').length || 0);
            if (siblingsTableBody) {
                while (siblingsTableBody.querySelectorAll('.sibling-row').length < siblingRowCount) {
                    siblingsTableBody.appendChild(buildSiblingRow(siblingIndex));
                    siblingIndex++;
                }
            }

            const grantRowCount = Math.max(getDraftRowCount(draftValues, 'grants'), grantsTableBody?.querySelectorAll('.grant-row').length || 0);
            if (grantsTableBody) {
                while (grantsTableBody.querySelectorAll('.grant-row').length < grantRowCount) {
                    grantsTableBody.appendChild(buildGrantRow(grantIndex));
                    grantIndex++;
                }
            }

            Object.entries(draftValues).forEach(([name, value]) => {
                const escapedName = CSS.escape(name);
                const fields = applicationForm.querySelectorAll(`[name="${escapedName}"]`);
                if (!fields.length) {
                    return;
                }

                fields.forEach((field) => {
                    if (!(field instanceof HTMLElement) || field.hasAttribute('readonly') || field instanceof HTMLInputElement && field.type === 'file') {
                        return;
                    }

                    if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                        field.checked = value !== '';
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        return;
                    }

                    field.value = String(value ?? '');
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            const restoredStep = parseInt(String(savedPayload.currentStep ?? 0), 10);
            if (!Number.isNaN(restoredStep) && restoredStep >= 0 && restoredStep < steps.length) {
                currentStep = restoredStep;
            }

            refreshSiblingFieldsState();
            refreshGrantFieldsState();
            toggleOtherAcademicField(schoolNameInput, schoolNameOtherWrapper, schoolNameOtherInput);
            toggleOtherAcademicField(courseInput, courseOtherWrapper, courseOtherInput);
            syncEducationFields();
            updateFinalStepStatuses();
            updateReviewSummary();
            updateForm();
            isRestoringDraft = false;

            const formattedTimestamp = formatDraftTimestamp(savedPayload.savedAt ?? '');
            updateDraftStatus(
                formattedTimestamp
                    ? `Draft restored from ${formattedTimestamp}. File uploads still need to be selected again.`
                    : 'Draft restored. File uploads still need to be selected again.',
                'success'
            );
        }

        applicationForm?.addEventListener('input', scheduleDraftSave);
        applicationForm?.addEventListener('change', scheduleDraftSave);
        applicationForm?.addEventListener('submit', () => {
            updateDraftStatus('Submitting your application...', 'muted');
        });
        clearDraftButton?.addEventListener('click', () => {
            clearDraft();
        });

        restoreDraft();

    });
</script>

<script src="<?php echo htmlspecialchars(asset_url('js/camera.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(asset_url('js/signature.js')); ?>"></script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
