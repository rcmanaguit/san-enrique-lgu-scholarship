<form method="post" id="applyStep3Form" data-autosave-step="3">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_step3">

    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="wizard-step-header mb-3">
                <h2 class="h5 mb-2">Step 3: Family Background</h2>
                <p class="small text-muted mb-0" id="autosaveStatus">Auto-save is enabled for this step.</p>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <h3 class="h6">Mother</h3>
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input js-parent-status-toggle js-na-toggle" type="checkbox" id="motherNaToggle" name="mother_na" value="1" data-target="#motherFields" data-group="mother" <?= $motherNa ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="motherNaToggle">Not Applicable</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input js-parent-status-toggle js-na-toggle" type="checkbox" id="motherDeceasedToggle" name="mother_deceased" value="1" data-target="#motherFields" data-group="mother" <?= $motherDeceased ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="motherDeceasedToggle">Deceased</label>
                        </div>
                    </div>
                    <div class="row g-2" id="motherFields">
                        <div class="col-12"><input type="text" class="form-control" name="mother_name" placeholder="Mother's Name" value="<?= e($motherDeceased ? '' : (string) ($step3['mother_name'] ?? '')) ?>" <?= ($motherNa || $motherDeceased) ? '' : 'required' ?>></div>
                        <div class="col-4"><input type="number" class="form-control" name="mother_age" placeholder="Age" value="<?= e((string) ($step3['mother_age'] ?? '')) ?>" min="0" <?= ($motherNa || $motherDeceased) ? '' : 'required' ?>></div>
                        <div class="col-8"><input type="text" class="form-control" name="mother_occupation" placeholder="Occupation" value="<?= e((string) ($step3['mother_occupation'] ?? '')) ?>" <?= ($motherNa || $motherDeceased) ? '' : 'required' ?>></div>
                        <div class="col-12"><input type="number" step="0.01" class="form-control" name="mother_monthly_income" placeholder="Monthly Income" value="<?= e((string) ($step3['mother_monthly_income'] ?? '')) ?>" min="0" <?= ($motherNa || $motherDeceased) ? '' : 'required' ?>></div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <h3 class="h6">Father</h3>
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input js-parent-status-toggle js-na-toggle" type="checkbox" id="fatherNaToggle" name="father_na" value="1" data-target="#fatherFields" data-group="father" <?= $fatherNa ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="fatherNaToggle">Not Applicable</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input js-parent-status-toggle js-na-toggle" type="checkbox" id="fatherDeceasedToggle" name="father_deceased" value="1" data-target="#fatherFields" data-group="father" <?= $fatherDeceased ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="fatherDeceasedToggle">Deceased</label>
                        </div>
                    </div>
                    <div class="row g-2" id="fatherFields">
                        <div class="col-12"><input type="text" class="form-control" name="father_name" placeholder="Father's Name" value="<?= e($fatherDeceased ? '' : (string) ($step3['father_name'] ?? '')) ?>" <?= ($fatherNa || $fatherDeceased) ? '' : 'required' ?>></div>
                        <div class="col-4"><input type="number" class="form-control" name="father_age" placeholder="Age" value="<?= e((string) ($step3['father_age'] ?? '')) ?>" min="0" <?= ($fatherNa || $fatherDeceased) ? '' : 'required' ?>></div>
                        <div class="col-8"><input type="text" class="form-control" name="father_occupation" placeholder="Occupation" value="<?= e((string) ($step3['father_occupation'] ?? '')) ?>" <?= ($fatherNa || $fatherDeceased) ? '' : 'required' ?>></div>
                        <div class="col-12"><input type="number" step="0.01" class="form-control" name="father_monthly_income" placeholder="Monthly Income" value="<?= e((string) ($step3['father_monthly_income'] ?? '')) ?>" min="0" <?= ($fatherNa || $fatherDeceased) ? '' : 'required' ?>></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body p-4">
            <?php $siblingsRows = !empty($siblingsPrefill) ? array_values($siblingsPrefill) : [['name' => '', 'age' => '', 'education' => '', 'occupation' => '', 'income' => '']]; ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h3 class="h6 mb-0">Members of the Family (Siblings)</h3>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addSiblingRowBtn">
                    <i class="fa-solid fa-plus me-1"></i>Add Sibling
                </button>
            </div>
            <div class="form-check mb-1">
                <input class="form-check-input js-na-toggle" type="checkbox" id="siblingsNaToggle" name="siblings_na" value="1" data-target="#siblingsFields" <?= $siblingsNa ? 'checked' : '' ?>>
                <label class="form-check-label small" for="siblingsNaToggle">Not Applicable</label>
            </div>
            <div class="small text-muted mb-2">Add only siblings with relevant information. Leave this section as Not Applicable if none.</div>
            <div class="table-responsive" id="siblingsFields">
                <table class="table table-sm align-middle wizard-stack-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Highest Educational Attainment</th>
                            <th>Occupation</th>
                            <th>Monthly Income</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="siblingsTableBody" data-next-index="<?= (int) count($siblingsRows) ?>">
                        <?php foreach ($siblingsRows as $i => $row): ?>
                            <tr>
                                <td data-label="Name"><div class="wizard-inline-field-label">Name</div><input type="text" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][name]" value="<?= e((string) ($row['name'] ?? '')) ?>" placeholder="Full name" <?= $siblingsNa ? '' : 'required' ?>></td>
                                <td data-label="Age"><div class="wizard-inline-field-label">Age</div><input type="number" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][age]" value="<?= e((string) ($row['age'] ?? '')) ?>" min="0" <?= $siblingsNa ? '' : 'required' ?>></td>
                                <td data-label="Highest Educational Attainment"><div class="wizard-inline-field-label">Highest Educational Attainment</div><input type="text" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][education]" value="<?= e((string) ($row['education'] ?? '')) ?>" placeholder="e.g., College" <?= $siblingsNa ? '' : 'required' ?>></td>
                                <td data-label="Occupation"><div class="wizard-inline-field-label">Occupation</div><input type="text" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][occupation]" value="<?= e((string) ($row['occupation'] ?? '')) ?>" placeholder="e.g., Student" <?= $siblingsNa ? '' : 'required' ?>></td>
                                <td data-label="Monthly Income"><div class="wizard-inline-field-label">Monthly Income</div><input type="number" step="0.01" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][income]" value="<?= e((string) ($row['income'] ?? '')) ?>" min="0" <?= $siblingsNa ? '' : 'required' ?>></td>
                                <td data-label="Action" class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-sibling-row" aria-label="Remove sibling row">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-soft shadow-sm mb-3">
        <div class="card-body p-4">
            <?php $grantsRows = !empty($grantsPrefill) ? array_values($grantsPrefill) : [['program' => '', 'period' => '']]; ?>
            <h3 class="h6 mb-3">Educational Background</h3>
            <div class="row g-3">
                <div class="col-12 col-lg-4">
                    <div class="card card-soft h-100">
                        <div class="card-body p-3">
                            <h4 class="h6 mb-3">Elementary</h4>
                            <input type="hidden" name="education[0][level]" value="Elementary">
                            <div class="mb-2">
                                <label class="form-label mb-1">School Name</label>
                                <input type="text" class="form-control form-control-sm" name="education[0][school]" value="<?= e((string) ($eduElementary['school'] ?? '')) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Year Graduated</label>
                                <input type="number" class="form-control form-control-sm" name="education[0][year]" value="<?= e((string) ($eduElementary['year'] ?? '')) ?>" min="1900" max="2100" step="1" inputmode="numeric" placeholder="YYYY" required>
                            </div>
                            <div>
                                <label class="form-label mb-1">Honors/Awards</label>
                                <input type="text" class="form-control form-control-sm" name="education[0][honors]" value="<?= e((string) ($eduElementary['honors'] ?? '')) ?>">
                                <div class="form-text">Leave blank if none.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="card card-soft h-100">
                        <div class="card-body p-3">
                            <h4 class="h6 mb-3">High School</h4>
                            <input type="hidden" name="education[1][level]" value="High School">
                            <div class="mb-2">
                                <label class="form-label mb-1">School Name</label>
                                <input type="text" class="form-control form-control-sm" name="education[1][school]" value="<?= e((string) ($eduHighSchool['school'] ?? '')) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Year Graduated</label>
                                <input type="number" class="form-control form-control-sm" name="education[1][year]" value="<?= e((string) ($eduHighSchool['year'] ?? '')) ?>" min="1900" max="2100" step="1" inputmode="numeric" placeholder="YYYY" required>
                            </div>
                            <div>
                                <label class="form-label mb-1">Honors/Awards</label>
                                <input type="text" class="form-control form-control-sm" name="education[1][honors]" value="<?= e((string) ($eduHighSchool['honors'] ?? '')) ?>">
                                <div class="form-text">Leave blank if none.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="card card-soft h-100">
                        <div class="card-body p-3">
                            <h4 class="h6 mb-3">College</h4>
                            <input type="hidden" name="education[2][level]" value="College">
                            <div class="mb-2">
                                <label class="form-label mb-1">School Name (College)</label>
                                <input type="text" class="form-control form-control-sm" name="education[2][school]" value="<?= e((string) ($eduCollege['school'] ?? '')) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Course</label>
                                <input type="text" class="form-control form-control-sm" name="education[2][course]" value="<?= e((string) ($eduCollege['course'] ?? '')) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Year Level</label>
                                <select class="form-select form-select-sm" name="education[2][year]" required>
                                    <option value="">Select Year Level</option>
                                    <option value="1" <?= $collegeYearLevelValue === '1' ? 'selected' : '' ?>>1st Year</option>
                                    <option value="2" <?= $collegeYearLevelValue === '2' ? 'selected' : '' ?>>2nd Year</option>
                                    <option value="3" <?= $collegeYearLevelValue === '3' ? 'selected' : '' ?>>3rd Year</option>
                                    <option value="4" <?= $collegeYearLevelValue === '4' ? 'selected' : '' ?>>4th Year</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label mb-1">Honors/Awards</label>
                                <input type="text" class="form-control form-control-sm" name="education[2][honors]" value="<?= e((string) ($eduCollege['honors'] ?? '')) ?>">
                                <div class="form-text">Leave blank if none.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-2">
                <h3 class="h6 mb-0">Scholarship Grants Availed</h3>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addGrantsRowBtn">
                    <i class="fa-solid fa-plus me-1"></i>Add Row
                </button>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input js-na-toggle" type="checkbox" id="grantsNaToggle" name="grants_na" value="1" data-target="#grantsFields" <?= $grantsNa ? 'checked' : '' ?>>
                <label class="form-check-label small" for="grantsNaToggle">Not Applicable</label>
            </div>
            <div class="table-responsive" id="grantsFields">
                <table class="table table-sm align-middle wizard-stack-table">
                    <thead>
                        <tr>
                            <th>Scholarship Program</th>
                            <th>Year/Period</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="grantsTableBody" data-next-index="<?= (int) count($grantsRows) ?>">
                        <?php foreach ($grantsRows as $i => $row): ?>
                            <tr>
                                <td data-label="Scholarship Program"><div class="wizard-inline-field-label">Scholarship Program</div><input type="text" class="form-control form-control-sm" name="grants[<?= (int) $i ?>][program]" value="<?= e((string) ($row['program'] ?? '')) ?>" placeholder="Program name" <?= $grantsNa ? '' : 'required' ?>></td>
                                <td data-label="Year/Period"><div class="wizard-inline-field-label">Year/Period</div><input type="text" class="form-control form-control-sm" name="grants[<?= (int) $i ?>][period]" value="<?= e((string) ($row['period'] ?? '')) ?>" placeholder="e.g., 2024-2025" <?= $grantsNa ? '' : 'required' ?>></td>
                                <td data-label="Action" class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-remove-grants-row" aria-label="Remove grants row">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="wizard-actions mt-4">
                <a href="apply.php?step=2" class="btn btn-outline-secondary wizard-btn-prev"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
                <button type="submit" class="btn btn-primary wizard-btn-next"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</button>
            </div>
        </div>
    </div>
</form>
