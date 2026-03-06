<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login('login.php');
require_role(['applicant'], 'index.php');

if (!db_ready()) {
    set_flash('warning', 'The system setup is not complete yet. Please contact the administrator.');
    redirect('dashboard.php');
}

$hasPeriodTable = table_exists($conn, 'application_periods');
if (!$hasPeriodTable) {
    set_flash('warning', 'Application period settings are not ready yet. Please contact the administrator.');
    redirect('dashboard.php');
}

$openPeriod = current_open_application_period($conn);
if (!$openPeriod) {
    set_flash('warning', 'Applications are currently closed. You cannot submit an application without an open application period.');
    redirect('dashboard.php');
}

$pageTitle = 'Scholarship Application Wizard';
$user = current_user();
$alreadyAppliedThisPeriod = applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod);
if ($alreadyAppliedThisPeriod) {
    set_flash('warning', 'You already submitted an application for the current open period. Only one application is allowed per period.');
    redirect('my-application.php');
}

$wizard = wizard_state();
$persistentDraft = db_ready() ? wizard_load_persistent_draft($conn, (int) ($user['id'] ?? 0)) : null;
if (is_array($persistentDraft['state'] ?? null)) {
    $wizard = array_merge($wizard, (array) $persistentDraft['state']);
    wizard_save($wizard);
}

$resumeStep = wizard_resume_step($wizard);
$savedCurrentStep = (int) ($persistentDraft['current_step'] ?? 0);
if ($savedCurrentStep >= 1 && $savedCurrentStep <= 6) {
    $resumeStep = $savedCurrentStep;
}

$step = isset($_GET['step']) ? (int) $_GET['step'] : $resumeStep;
$step = max(1, min(6, $step));
if ($step === 5) {
    redirect('apply-photo.php');
}

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    wizard_clear();
    wizard_clear_persistent_draft($conn, (int) ($user['id'] ?? 0));
    set_flash('success', 'Application draft reset.');
    redirect('apply.php?step=1');
}

if ($step >= 2 && !(bool) ($wizard['step1_done'] ?? false)) {
    redirect('apply.php?step=1');
}
if ($step >= 3 && !(bool) ($wizard['step2_done'] ?? false)) {
    redirect('apply.php?step=2');
}
if ($step >= 4 && !(bool) ($wizard['step3_done'] ?? false)) {
    redirect('apply.php?step=3');
}
if ($step >= 5 && empty($wizard['step4_done'])) {
    redirect('apply.php?step=4');
}
if ($step >= 6 && empty($wizard['photo_path'])) {
    redirect('apply-photo.php');
}
wizard_save_persistent_draft($conn, (int) ($user['id'] ?? 0), $wizard, $step);

$persistWizard = static function (array $state, int $currentStep) use ($conn, $user): void {
    wizard_save($state);
    wizard_save_persistent_draft($conn, (int) ($user['id'] ?? 0), $state, $currentStep);
};

$isDeferredRequirementForInitialApplication = static function (array $req): bool {
    $name = strtolower(trim((string) ($req['requirement_name'] ?? '')));
    if ($name === '') {
        return false;
    }

    return str_contains($name, 'soa') || str_contains($name, 'student copy') || str_contains($name, 'statement of account');
};

$isPhotoHandledInStepFive = static function (array $req): bool {
    $name = strtolower(trim((string) ($req['requirement_name'] ?? '')));
    if ($name === '') {
        return false;
    }

    return str_contains($name, '2x2') && (str_contains($name, 'picture') || str_contains($name, 'photo'));
};

$getRequirements = static function (array $state) use ($conn): array {
    $step1 = $state['step1'] ?? [];
    $requirements = active_requirements(
        $conn,
        (string) ($step1['applicant_type'] ?? ''),
        (string) ($step1['school_type'] ?? '')
    );

    if (!$requirements) {
        $requirements = [
            ['id' => -1001, 'requirement_name' => 'Report Card / Previous Semester (Photocopy)', 'description' => '', 'is_required' => 1],
            ['id' => -1002, 'requirement_name' => '1 pc 2x2 Picture', 'description' => '', 'is_required' => 1],
            ['id' => -1003, 'requirement_name' => 'Barangay Residency', 'description' => '', 'is_required' => 1],
            ['id' => -1004, 'requirement_name' => 'Original Student Copy / Statement of Account (SOA)', 'description' => '', 'is_required' => 0],
        ];
    }

    return $requirements;
};

$getStepFourRequirements = static function (array $state) use ($getRequirements, $isPhotoHandledInStepFive, $isDeferredRequirementForInitialApplication): array {
    $requirements = $getRequirements($state);
    return array_values(array_filter($requirements, static function (array $req) use ($isPhotoHandledInStepFive, $isDeferredRequirementForInitialApplication): bool {
        return !$isPhotoHandledInStepFive($req) && !$isDeferredRequirementForInitialApplication($req);
    }));
};

if (is_post()) {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
        $toBytes = static function (string $value): int {
            $raw = trim($value);
            if ($raw === '') {
                return 0;
            }
            $unit = strtolower(substr($raw, -1));
            $number = (float) $raw;
            if ($unit === 'g') {
                return (int) ($number * 1024 * 1024 * 1024);
            }
            if ($unit === 'm') {
                return (int) ($number * 1024 * 1024);
            }
            if ($unit === 'k') {
                return (int) ($number * 1024);
            }
            return (int) $number;
        };
        $formatBytes = static function (int $bytes): string {
            if ($bytes >= 1024 * 1024 * 1024) {
                return rtrim(rtrim(number_format($bytes / (1024 * 1024 * 1024), 2, '.', ''), '0'), '.') . ' GB';
            }
            if ($bytes >= 1024 * 1024) {
                return rtrim(rtrim(number_format($bytes / (1024 * 1024), 2, '.', ''), '0'), '.') . ' MB';
            }
            if ($bytes >= 1024) {
                return rtrim(rtrim(number_format($bytes / 1024, 2, '.', ''), '0'), '.') . ' KB';
            }
            return $bytes . ' bytes';
        };

        $postMax = $toBytes((string) ini_get('post_max_size'));
        $uploadMax = $toBytes((string) ini_get('upload_max_filesize'));
        $limit = $postMax > 0 && $uploadMax > 0 ? min($postMax, $uploadMax) : max($postMax, $uploadMax);
        $limitText = $limit > 0 ? $formatBytes($limit) : 'server limit';

        set_flash('danger', 'Upload failed because the file is too large. Maximum allowed size is ' . $limitText . '.');
        redirect('apply.php?step=' . $step);
    }

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('apply.php?step=' . $step);
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $wizard = wizard_state();

    if ($action === 'save_step1') {
        $semesterOptions = ['First Semester', 'Second Semester'];
        $periodSemester = trim((string) ($openPeriod['semester'] ?? ''));
        $periodSchoolYear = trim((string) ($openPeriod['academic_year'] ?? ''));
        $schoolNameSelection = trim((string) ($_POST['school_name'] ?? ''));
        $schoolNameOther = trim((string) ($_POST['school_name_other'] ?? ''));
        $courseSelection = trim((string) ($_POST['course'] ?? ''));
        $courseOther = trim((string) ($_POST['course_other'] ?? ''));
        $resolvedSchoolName = $schoolNameSelection;
        if ($schoolNameSelection === '__other__') {
            $validatedSchoolOther = validate_typed_academic_text($schoolNameOther, 'School name');
            if (!($validatedSchoolOther['ok'] ?? false)) {
                set_flash('danger', (string) ($validatedSchoolOther['error'] ?? 'Invalid school name.'));
                redirect('apply.php?step=1');
            }
            $resolvedSchoolName = (string) ($validatedSchoolOther['value'] ?? '');
        }

        $resolvedCourse = $courseSelection;
        if ($courseSelection === '__other__') {
            $validatedCourseOther = validate_typed_academic_text($courseOther, 'Course');
            if (!($validatedCourseOther['ok'] ?? false)) {
                set_flash('danger', (string) ($validatedCourseOther['error'] ?? 'Invalid course.'));
                redirect('apply.php?step=1');
            }
            $resolvedCourse = (string) ($validatedCourseOther['value'] ?? '');
        }
        $data = [
            'applicant_type' => trim((string) ($_POST['applicant_type'] ?? '')),
            'semester' => $periodSemester !== '' ? $periodSemester : trim((string) ($_POST['semester'] ?? '')),
            'school_year' => $periodSchoolYear !== '' ? $periodSchoolYear : trim((string) ($_POST['school_year'] ?? '')),
            'school_name' => normalize_school_name($resolvedSchoolName),
            'school_type' => trim((string) ($_POST['school_type'] ?? '')),
            'course' => normalize_course_name($resolvedCourse),
            // Kept for database compatibility; no longer shown on online form.
            'scholarship_type' => '',
            'year_level' => '',
            'gwa' => '',
            'family_income' => '',
            'reason' => '',
        ];

        $required = ['applicant_type', 'semester', 'school_year', 'school_name', 'school_type', 'course'];
        foreach ($required as $field) {
            if ($data[$field] === '') {
                set_flash('danger', 'Please complete all required fields in Step 1.');
                redirect('apply.php?step=1');
            }
        }
        if ($schoolNameSelection !== '__other__' && !is_valid_negros_occidental_school_name($data['school_name'])) {
            set_flash('danger', 'Please select a school from the list or choose Other and type your school name.');
            redirect('apply.php?step=1');
        }
        if ($courseSelection !== '__other__' && $courseSelection !== '' && !is_valid_scholarship_course($data['course'])) {
            set_flash('danger', 'Please select a course from the list or choose Other and type your course.');
            redirect('apply.php?step=1');
        }
        if (!in_array($data['semester'], $semesterOptions, true)) {
            set_flash('danger', 'Semester must be First Semester or Second Semester only.');
            redirect('apply.php?step=1');
        }

        $wizard['step1'] = $data;
        $wizard['step1_done'] = true;
        $persistWizard($wizard, 2);
        redirect('apply.php?step=2');
    }

    if ($action === 'save_step2') {
        $birthDate = trim((string) ($_POST['birth_date'] ?? ''));
        $computedAge = calculate_age_from_birth_date($birthDate);
        $barangay = normalize_barangay((string) ($_POST['barangay'] ?? ''));
        $civilStatus = trim((string) ($_POST['civil_status'] ?? ''));
        $sex = trim((string) ($_POST['sex'] ?? ''));
        $suffix = trim((string) ($_POST['suffix'] ?? ''));
        $allowedCivilStatuses = ['', 'Single', 'Married', 'Widowed', 'Separated'];
        $allowedSexes = ['', 'Male', 'Female'];

        $data = [
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'middle_name' => trim((string) ($_POST['middle_name'] ?? '')),
            'suffix' => $suffix,
            'age' => $computedAge === null ? '' : (string) $computedAge,
            'civil_status' => $civilStatus,
            'sex' => $sex,
            'birth_date' => $birthDate,
            'birth_place' => trim((string) ($_POST['birth_place'] ?? '')),
            'barangay' => $barangay,
            'town' => san_enrique_town(),
            'province' => san_enrique_province(),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'contact_number' => trim((string) ($user['phone'] ?? '')),
        ];

        if ($data['last_name'] === '' || $data['first_name'] === '' || $data['contact_number'] === '' || $data['barangay'] === '') {
            set_flash('danger', 'Last name, first name, contact number, and barangay are required.');
            redirect('apply.php?step=2');
        }
        if (!in_array($data['civil_status'], $allowedCivilStatuses, true)) {
            set_flash('danger', 'Please select a valid civil status.');
            redirect('apply.php?step=2');
        }
        if (!in_array($data['sex'], $allowedSexes, true)) {
            set_flash('danger', 'Please select a valid sex.');
            redirect('apply.php?step=2');
        }
        if ($data['suffix'] !== '' && (!preg_match("/^[A-Za-z0-9 .'-]{1,20}$/", $data['suffix']) || strlen($data['suffix']) > 20)) {
            set_flash('danger', 'Suffix must be up to 20 characters and can only contain letters, numbers, spaces, apostrophes, dots, and hyphens.');
            redirect('apply.php?step=2');
        }

        if ($data['birth_date'] !== '' && $computedAge === null) {
            set_flash('danger', 'Please enter a valid birthdate (cannot be in the future).');
            redirect('apply.php?step=2');
        }

        if (!is_valid_mobile_number($data['contact_number'])) {
            set_flash('danger', 'Please provide a valid mobile number (09XXXXXXXXX).');
            redirect('apply.php?step=2');
        }
        $data['contact_number'] = normalize_mobile_number($data['contact_number']);

        $wizard['step2'] = $data;
        $wizard['step2_done'] = true;
        $persistWizard($wizard, 3);
        redirect('apply.php?step=3');
    }

    if ($action === 'save_step3') {
        $motherNa = isset($_POST['mother_na']) ? 1 : 0;
        $fatherNa = isset($_POST['father_na']) ? 1 : 0;
        $siblingsNa = isset($_POST['siblings_na']) ? 1 : 0;
        $grantsNa = isset($_POST['grants_na']) ? 1 : 0;

        $siblingsRaw = $_POST['siblings'] ?? [];
        $educationRaw = $_POST['education'] ?? [];
        $grantsRaw = $_POST['grants'] ?? [];

        $siblings = [];
        if (!$siblingsNa) {
            foreach ($siblingsRaw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $entry = [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'age' => trim((string) ($row['age'] ?? '')),
                    'education' => trim((string) ($row['education'] ?? '')),
                    'occupation' => trim((string) ($row['occupation'] ?? '')),
                    'income' => trim((string) ($row['income'] ?? '')),
                ];
                if (implode('', $entry) !== '') {
                    $siblings[] = $entry;
                }
            }
        }

        $education = [];
        foreach ($educationRaw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $educationYear = trim((string) ($row['year'] ?? ''));
            if ($educationYear !== '') {
                $educationYear = preg_replace('/\D+/', '', $educationYear) ?? '';
                if ($educationYear !== '' && strlen($educationYear) > 4) {
                    $educationYear = substr($educationYear, 0, 4);
                }
            }
            $entry = [
                'level' => trim((string) ($row['level'] ?? '')),
                'school' => trim((string) ($row['school'] ?? '')),
                'course' => trim((string) ($row['course'] ?? '')),
                'year' => $educationYear,
                'honors' => trim((string) ($row['honors'] ?? '')),
            ];
            if (implode('', $entry) !== '') {
                $education[] = $entry;
            }
        }

        $grants = [];
        if (!$grantsNa) {
            foreach ($grantsRaw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $entry = [
                    'program' => trim((string) ($row['program'] ?? '')),
                    'period' => trim((string) ($row['period'] ?? '')),
                ];
                if (implode('', $entry) !== '') {
                    $grants[] = $entry;
                }
            }
        }

        $wizard['step3'] = [
            'mother_na' => $motherNa,
            'mother_name' => $motherNa ? '' : trim((string) ($_POST['mother_name'] ?? '')),
            'mother_age' => $motherNa ? '' : trim((string) ($_POST['mother_age'] ?? '')),
            'mother_occupation' => $motherNa ? '' : trim((string) ($_POST['mother_occupation'] ?? '')),
            'mother_monthly_income' => $motherNa ? '' : trim((string) ($_POST['mother_monthly_income'] ?? '')),
            'father_na' => $fatherNa,
            'father_name' => $fatherNa ? '' : trim((string) ($_POST['father_name'] ?? '')),
            'father_age' => $fatherNa ? '' : trim((string) ($_POST['father_age'] ?? '')),
            'father_occupation' => $fatherNa ? '' : trim((string) ($_POST['father_occupation'] ?? '')),
            'father_monthly_income' => $fatherNa ? '' : trim((string) ($_POST['father_monthly_income'] ?? '')),
            'siblings_na' => $siblingsNa,
            'siblings' => $siblings,
            'education' => $education,
            'grants_na' => $grantsNa,
            'grants' => $grants,
        ];
        $wizard['step3_done'] = true;
        $persistWizard($wizard, 4);
        redirect('apply.php?step=4');
    }

    if ($action === 'save_step4') {
        $requirements = $getStepFourRequirements($wizard);
        $errors = [];
        foreach ($requirements as $req) {
            $reqId = (string) $req['id'];
            $field = 'req_' . $reqId;
            $existing = $wizard['documents'][$reqId] ?? null;
            $uploaded = upload_any_file($field, __DIR__ . '/uploads/tmp');
            if ($uploaded) {
                if ($existing && !empty($existing['file_path'])) {
                    $oldAbsolute = __DIR__ . '/' . ltrim((string) $existing['file_path'], '/');
                    if (file_exists($oldAbsolute)) {
                        @unlink($oldAbsolute);
                    }
                }

                $filePath = (string) $uploaded['file_path'];
                $fileExt = (string) $uploaded['ext'];
                $originalName = (string) $uploaded['original_name'];
                $relative = str_replace(str_replace('\\', '/', __DIR__) . '/', '', str_replace('\\', '/', $filePath));
                $wizard['documents'][$reqId] = [
                    'requirement_template_id' => (int) $req['id'],
                    'requirement_name' => (string) $req['requirement_name'],
                    'file_path' => $relative,
                    'file_ext' => $fileExt,
                    'original_name' => $originalName,
                ];
            }

            $required = (int) ($req['is_required'] ?? 1) === 1;
            if ($required && empty($wizard['documents'][$reqId])) {
                $errors[] = $req['requirement_name'] . ' is required.';
            }
        }

        if ($errors) {
            foreach ($errors as $error) {
                set_flash('danger', $error);
            }
            redirect('apply.php?step=4');
        }

        $wizard['step4_done'] = true;
        $persistWizard($wizard, 5);
        redirect('apply-photo.php');
    }

    if ($action === 'final_submit') {
        $currentOpenPeriod = current_open_application_period($conn);
        if (!$currentOpenPeriod) {
            set_flash('danger', 'Application period is closed. Submission cancelled.');
            redirect('dashboard.php');
        }
        if (applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $currentOpenPeriod)) {
            set_flash('warning', 'You already submitted an application for the current open period.');
            redirect('my-application.php');
        }

        $requirements = $getStepFourRequirements($wizard);
        $missing = [];
        foreach ($requirements as $req) {
            $required = (int) ($req['is_required'] ?? 1) === 1;
            $reqId = (string) $req['id'];
            if ($required && empty($wizard['documents'][$reqId])) {
                $missing[] = $req['requirement_name'];
            }
        }

        if (!(bool) ($wizard['step1_done'] ?? false)) {
            set_flash('danger', 'Please complete Step 1 before final submission.');
            redirect('apply.php?step=1');
        }
        if (!(bool) ($wizard['step2_done'] ?? false)) {
            set_flash('danger', 'Please complete Step 2 before final submission.');
            redirect('apply.php?step=2');
        }
        if (!(bool) ($wizard['step3_done'] ?? false)) {
            set_flash('danger', 'Please complete Step 3 before final submission.');
            redirect('apply.php?step=3');
        }
        if ($missing) {
            set_flash('danger', 'Please upload all required documents before final submission.');
            redirect('apply.php?step=4');
        }
        if (empty($wizard['photo_path'])) {
            set_flash('danger', 'Please complete your 2x2 photo before final submission.');
            redirect('apply-photo.php');
        }

        $agreedToTerms = isset($_POST['agree_terms']) && (string) $_POST['agree_terms'] === '1';
        if (!$agreedToTerms) {
            set_flash('danger', 'Please confirm the submission declaration before submitting your application.');
            redirect('apply.php?step=6');
        }

        $step1 = $wizard['step1'];
        $step1['school_name'] = normalize_school_name((string) ($step1['school_name'] ?? ''));
        $step2 = $wizard['step2'];
        $step3 = $wizard['step3'];
        $step2['contact_number'] = normalize_mobile_number((string) ($step2['contact_number'] ?? ''));
        $step2['barangay'] = normalize_barangay((string) ($step2['barangay'] ?? ''));
        $step2['town'] = san_enrique_town();
        $step2['province'] = san_enrique_province();
        $step2['age'] = (string) (calculate_age_from_birth_date((string) ($step2['birth_date'] ?? '')) ?? '');
        $step2['suffix'] = trim((string) ($step2['suffix'] ?? ''));

        if (!is_valid_mobile_number((string) ($step2['contact_number'] ?? ''))) {
            set_flash('danger', 'Please provide a valid contact mobile number.');
            redirect('apply.php?step=2');
        }
        if (trim((string) ($step2['barangay'] ?? '')) === '') {
            set_flash('danger', 'Please select a valid barangay.');
            redirect('apply.php?step=2');
        }
        if (trim((string) ($step2['birth_date'] ?? '')) !== '' && trim((string) ($step2['age'] ?? '')) === '') {
            set_flash('danger', 'Birthdate is invalid. Please review your personal information.');
            redirect('apply.php?step=2');
        }
        if ($step2['suffix'] !== '' && (!preg_match("/^[A-Za-z0-9 .'-]{1,20}$/", $step2['suffix']) || strlen($step2['suffix']) > 20)) {
            set_flash('danger', 'Suffix must be up to 20 characters and can only contain letters, numbers, spaces, apostrophes, dots, and hyphens.');
            redirect('apply.php?step=2');
        }

        if (mobile_number_exists($conn, (string) $step2['contact_number'], (int) ($user['id'] ?? 0))) {
            set_flash('danger', 'Contact mobile number is already assigned to another account.');
            redirect('apply.php?step=2');
        }

        $conn->begin_transaction();
        try {
            $applicationNo = generate_application_no($conn);
            $qrToken = generate_qr_token();
            $submittedAt = date('Y-m-d H:i:s');

            $esc = static fn(string $value): string => "'" . $conn->real_escape_string($value) . "'";
            $nullable = static fn(?string $value): string => trim((string) $value) === '' ? 'NULL' : "'" . $conn->real_escape_string((string) $value) . "'";
            $hasPeriodColumn = table_column_exists($conn, 'applications', 'application_period_id');
            $openPeriodId = (int) ($currentOpenPeriod['id'] ?? 0);
            $periodColumnSql = $hasPeriodColumn ? 'application_period_id, ' : '';
            $periodValueSql = $hasPeriodColumn ? ($openPeriodId > 0 ? $openPeriodId : 'NULL') . ', ' : '';

            $siblingsJson = array_json($step3['siblings'] ?? []);
            $educationJson = array_json($step3['education'] ?? []);
            $grantsJson = array_json($step3['grants'] ?? []);

            $insertSql = "INSERT INTO applications (
                application_no, user_id, " . $periodColumnSql . "qr_token, applicant_type, semester, school_year,
                school_name, school_type, course, last_name, first_name, middle_name, suffix,
                age, civil_status, sex, birth_date, birth_place, barangay, town, province, address, contact_number,
                mother_name, mother_age, mother_occupation, mother_monthly_income,
                father_name, father_age, father_occupation, father_monthly_income,
                siblings_json, educational_background_json, grants_availed_json, photo_path, status, submitted_at
            ) VALUES (
                " . $esc($applicationNo) . ",
                " . (int) $user['id'] . ",
                " . $periodValueSql . "
                " . $esc($qrToken) . ",
                " . $esc((string) $step1['applicant_type']) . ",
                " . $esc((string) $step1['semester']) . ",
                " . $esc((string) $step1['school_year']) . ",
                " . $esc((string) $step1['school_name']) . ",
                " . $esc((string) $step1['school_type']) . ",
                " . $nullable($step1['course'] ?? '') . ",
                " . $esc((string) $step2['last_name']) . ",
                " . $esc((string) $step2['first_name']) . ",
                " . $nullable($step2['middle_name'] ?? '') . ",
                " . $nullable($step2['suffix'] ?? '') . ",
                " . $nullable($step2['age'] ?? '') . ",
                " . $nullable($step2['civil_status'] ?? '') . ",
                " . $nullable($step2['sex'] ?? '') . ",
                " . $nullable($step2['birth_date'] ?? '') . ",
                " . $nullable($step2['birth_place'] ?? '') . ",
                " . $nullable($step2['barangay'] ?? '') . ",
                " . $esc((string) ($step2['town'] ?? san_enrique_town())) . ",
                " . $esc((string) ($step2['province'] ?? san_enrique_province())) . ",
                " . $nullable($step2['address'] ?? '') . ",
                " . $esc((string) $step2['contact_number']) . ",
                " . $nullable($step3['mother_name'] ?? '') . ",
                " . $nullable($step3['mother_age'] ?? '') . ",
                " . $nullable($step3['mother_occupation'] ?? '') . ",
                " . $nullable($step3['mother_monthly_income'] ?? '') . ",
                " . $nullable($step3['father_name'] ?? '') . ",
                " . $nullable($step3['father_age'] ?? '') . ",
                " . $nullable($step3['father_occupation'] ?? '') . ",
                " . $nullable($step3['father_monthly_income'] ?? '') . ",
                " . $esc($siblingsJson) . ",
                " . $esc($educationJson) . ",
                " . $esc($grantsJson) . ",
                NULL,
                'under_review',
                " . $esc($submittedAt) . "
            )";

            if (!$conn->query($insertSql)) {
                throw new RuntimeException('Failed to create application: ' . $conn->error);
            }
            $applicationId = (int) $conn->insert_id;

            $finalPhoto = move_temp_file_to_final((string) $wizard['photo_path'], __DIR__ . '/uploads/photos', 'app_' . $applicationId . '_photo');
            if ($finalPhoto) {
                $stmtPhoto = $conn->prepare("UPDATE applications SET photo_path = ? WHERE id = ?");
                $stmtPhoto->bind_param('si', $finalPhoto, $applicationId);
                $stmtPhoto->execute();
                $stmtPhoto->close();
            }

            foreach ($wizard['documents'] as $doc) {
                $finalDoc = move_temp_file_to_final((string) ($doc['file_path'] ?? ''), __DIR__ . '/uploads/documents', 'app_' . $applicationId . '_doc');
                if (!$finalDoc) {
                    continue;
                }

                $templateId = (int) ($doc['requirement_template_id'] ?? 0);
                $requirementName = (string) ($doc['requirement_name'] ?? 'Requirement');
                $fileExt = (string) ($doc['file_ext'] ?? '');
                $documentType = 'requirement';
                $templateSql = $templateId > 0 ? (string) $templateId : 'NULL';
                $docSql = "INSERT INTO application_documents
                    (application_id, requirement_template_id, requirement_name, document_type, file_path, file_ext)
                    VALUES (
                        " . (int) $applicationId . ",
                        " . $templateSql . ",
                        '" . $conn->real_escape_string($requirementName) . "',
                        '" . $conn->real_escape_string($documentType) . "',
                        '" . $conn->real_escape_string($finalDoc) . "',
                        '" . $conn->real_escape_string($fileExt) . "'
                    )";
                if (!$conn->query($docSql)) {
                    throw new RuntimeException('Failed to store document record: ' . $conn->error);
                }
            }

            $stmtUser = $conn->prepare(
                "UPDATE users
                 SET first_name = ?, middle_name = ?, suffix = ?, last_name = ?, phone = ?, school_name = ?, school_type = ?, course = ?, barangay = ?, town = ?, province = ?, address = ?
                 WHERE id = ?"
            );
            $stmtUser->bind_param(
                'ssssssssssssi',
                $step2['first_name'],
                $step2['middle_name'],
                $step2['suffix'],
                $step2['last_name'],
                $step2['contact_number'],
                $step1['school_name'],
                $step1['school_type'],
                $step1['course'],
                $step2['barangay'],
                $step2['town'],
                $step2['province'],
                $step2['address'],
                $user['id']
            );
            $stmtUser->execute();
            $stmtUser->close();

            $conn->commit();

            $smsMessage = 'San Enrique LGU Scholarship: Application ' . $applicationNo . ' was submitted successfully and is now under review.';
            sms_send((string) $step2['contact_number'], $smsMessage, (int) $user['id'], 'status_update');
            audit_log(
                $conn,
                'application_submitted',
                (int) ($user['id'] ?? 0),
                (string) ($user['role'] ?? 'applicant'),
                'application',
                (string) $applicationId,
                'Application submitted successfully.',
                [
                    'application_no' => $applicationNo,
                    'school_year' => (string) ($step1['school_year'] ?? ''),
                ]
            );
            create_notification(
                $conn,
                (int) ($user['id'] ?? 0),
                'Application Submitted',
                'Your application ' . $applicationNo . ' was submitted successfully and is now under review.',
                'application',
                'my-application.php',
                (int) ($user['id'] ?? 0)
            );
            create_notifications_for_roles(
                $conn,
                ['admin', 'staff'],
                'New Application Submitted',
                'Application ' . $applicationNo . ' was submitted by ' . trim((string) ($step2['first_name'] ?? '') . ' ' . (string) ($step2['last_name'] ?? '')) . '.',
                'application',
                'shared/applications.php',
                (int) ($user['id'] ?? 0)
            );

            wizard_clear();
            wizard_clear_persistent_draft($conn, (int) ($user['id'] ?? 0));
            set_flash('success', 'Application submitted. Application No: ' . $applicationNo);
            redirect('my-application.php');
        } catch (Throwable $e) {
            $conn->rollback();
            audit_log(
                $conn,
                'application_submit_failed',
                (int) ($user['id'] ?? 0),
                (string) ($user['role'] ?? 'applicant'),
                'application',
                null,
                'Application submission failed.',
                ['error' => $e->getMessage()]
            );
            set_flash('danger', 'Submission failed: ' . $e->getMessage());
            redirect('apply.php?step=6');
        }
    }
}

$wizard = wizard_state();
$step1 = $wizard['step1'];
$step2 = $wizard['step2'];
$step3 = $wizard['step3'];
$step1['semester'] = trim((string) ($openPeriod['semester'] ?? ($step1['semester'] ?? '')));
$step1['school_year'] = trim((string) ($openPeriod['academic_year'] ?? ($step1['school_year'] ?? '')));
$schoolNameOptions = negros_occidental_colleges_universities();
$currentSchoolName = trim((string) ($step1['school_name'] ?? ''));
$selectedSchoolName = '';
if ($currentSchoolName !== '') {
    $selectedSchoolName = is_valid_negros_occidental_school_name($currentSchoolName) ? $currentSchoolName : '__other__';
}
$otherSchoolName = $selectedSchoolName === '__other__' ? (string) ($step1['school_name'] ?? '') : '';
$isOtherSchoolSelected = $selectedSchoolName === '__other__';
$courseOptions = scholarship_course_options();
$currentCourse = trim((string) ($step1['course'] ?? ''));
$selectedCourse = '';
if ($currentCourse !== '') {
    $selectedCourse = is_valid_scholarship_course($currentCourse) ? $currentCourse : '__other__';
}
$otherCourse = $selectedCourse === '__other__' ? (string) ($step1['course'] ?? '') : '';
$isOtherCourseSelected = $selectedCourse === '__other__';
$requirements = $getStepFourRequirements($wizard);
$barangayOptions = san_enrique_barangays();
$reviewIssues = [];

if (trim((string) ($step2['birth_date'] ?? '')) !== '' && trim((string) ($step2['age'] ?? '')) === '') {
    $step2['age'] = (string) (calculate_age_from_birth_date((string) ($step2['birth_date'] ?? '')) ?? '');
}
$step2['last_name'] = trim((string) ($step2['last_name'] ?? '')) !== '' ? (string) $step2['last_name'] : (string) ($user['last_name'] ?? '');
$step2['first_name'] = trim((string) ($step2['first_name'] ?? '')) !== '' ? (string) $step2['first_name'] : (string) ($user['first_name'] ?? '');
$step2['middle_name'] = trim((string) ($step2['middle_name'] ?? '')) !== '' ? (string) $step2['middle_name'] : (string) ($user['middle_name'] ?? '');
$step2['suffix'] = trim((string) ($step2['suffix'] ?? '')) !== '' ? (string) $step2['suffix'] : (string) ($user['suffix'] ?? '');
$step2['contact_number'] = trim((string) ($step2['contact_number'] ?? '')) !== '' ? (string) $step2['contact_number'] : (string) ($user['phone'] ?? '');
$step2['town'] = trim((string) ($step2['town'] ?? '')) !== '' ? (string) $step2['town'] : san_enrique_town();
$step2['province'] = trim((string) ($step2['province'] ?? '')) !== '' ? (string) $step2['province'] : san_enrique_province();
$step2['barangay'] = normalize_barangay((string) ($step2['barangay'] ?? ''));

if (!(bool) ($wizard['step1_done'] ?? false)) {
    $reviewIssues[] = ['text' => 'Step 1 (Program) is incomplete.', 'url' => 'apply.php?step=1'];
}
if (!(bool) ($wizard['step2_done'] ?? false)) {
    $reviewIssues[] = ['text' => 'Step 2 (Personal) is incomplete.', 'url' => 'apply.php?step=2'];
}
if (!(bool) ($wizard['step3_done'] ?? false)) {
    $reviewIssues[] = ['text' => 'Step 3 (Family) is incomplete.', 'url' => 'apply.php?step=3'];
}
foreach ($requirements as $req) {
    $reqId = (string) ($req['id'] ?? '');
    $required = (int) ($req['is_required'] ?? 1) === 1;
    if ($required && empty($wizard['documents'][$reqId])) {
        $reviewIssues[] = ['text' => 'Missing required document: ' . (string) ($req['requirement_name'] ?? 'Requirement'), 'url' => 'apply.php?step=4'];
    }
}
if (empty($wizard['photo_path'])) {
    $reviewIssues[] = ['text' => '2x2 photo is missing.', 'url' => 'apply-photo.php'];
}

$stepLabels = [1 => 'Program', 2 => 'Personal', 3 => 'Family', 4 => 'Requirements', 5 => '2x2 Photo', 6 => 'Review'];

$extraJs = ['assets/js/apply-wizard.js', 'assets/js/capture-utils.js', 'assets/js/capture-ui.js'];
$bodyClass = 'apply-page';

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-file-pen me-2 text-primary"></i>Scholarship Application Wizard</h1>
    <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
        <a href="apply.php?reset=1" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reset Draft</a>
    </div>
</div>

<div class="card card-soft mb-3 wizard-step-summary">
    <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-primary">Step <?= (int) $step ?> of 6</span>
            <span class="small text-muted"><?= e((string) ($stepLabels[$step] ?? 'Application Wizard')) ?></span>
        </div>
        <?php if ($openPeriod): ?>
            <span class="small text-muted">
                <i class="fa-regular fa-calendar-check me-1"></i><?= e(format_application_period($openPeriod)) ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="card card-soft mb-3">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 wizard-progress">
            <?php foreach ($stepLabels as $stepNo => $label): ?>
                <?php
                $class = 'wizard-step-pill';
                if ($stepNo < $step) {
                    $class .= ' done';
                } elseif ($stepNo === $step) {
                    $class .= ' active';
                }
                ?>
                <div class="d-flex align-items-center wizard-progress-item">
                    <div class="<?= e($class) ?>"><?= $stepNo ?></div>
                    <div class="wizard-step-label ms-2 d-none d-sm-block"><?= e($label) ?></div>
                    <?php if ($stepNo < 6): ?>
                        <div class="wizard-divider ms-2"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($step === 1): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Step 1: Program Information</h2>
            <p class="small text-muted mb-3" id="autosaveStatus">Auto-save is enabled for this step.</p>
            <form method="post" class="row g-3" id="applyStep1Form" data-autosave-step="1">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_step1">

                <div class="col-12 col-md-4">
                    <label class="form-label">Applicant Type *</label>
                    <select name="applicant_type" class="form-select" required>
                        <option value="">Select</option>
                        <option value="new" <?= ($step1['applicant_type'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="renew" <?= ($step1['applicant_type'] ?? '') === 'renew' ? 'selected' : '' ?>>Re-New</option>
                    </select>
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
<?php endif; ?>

<?php if ($step === 2): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Step 2: Personal Information</h2>
            <p class="small text-muted mb-3" id="autosaveStatus">Auto-save is enabled for this step.</p>
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
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="birth_date" id="birthDateInput" class="form-control" value="<?= e((string) ($step2['birth_date'] ?? '')) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Age</label>
                    <input type="number" name="age" id="ageInput" class="form-control" value="<?= e((string) ($step2['age'] ?? '')) ?>" readonly>
                    <div class="form-text">Auto-calculated</div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Civil Status</label>
                    <?php $selectedCivilStatus = (string) ($step2['civil_status'] ?? ''); ?>
                    <select name="civil_status" class="form-select">
                        <option value="">Select</option>
                        <?php foreach (['Single', 'Married', 'Widowed', 'Separated'] as $civilStatusOption): ?>
                            <option value="<?= e($civilStatusOption) ?>" <?= $selectedCivilStatus === $civilStatusOption ? 'selected' : '' ?>><?= e($civilStatusOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Sex</label>
                    <?php $selectedSex = (string) ($step2['sex'] ?? ''); ?>
                    <select name="sex" class="form-select">
                        <option value="">Select</option>
                        <?php foreach (['Male', 'Female'] as $sexOption): ?>
                            <option value="<?= e($sexOption) ?>" <?= $selectedSex === $sexOption ? 'selected' : '' ?>><?= e($sexOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Place of Birth</label>
                    <input type="text" name="birth_place" class="form-control" value="<?= e((string) ($step2['birth_place'] ?? '')) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Address (House No. / Street / Purok)</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Address (House No. / Street / Purok)"><?= e((string) ($step2['address'] ?? '')) ?></textarea>
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
<?php endif; ?>

<?php if ($step === 3): ?>
    <?php
    $siblingsPrefill = $step3['siblings'] ?? [];
    $educationPrefill = $step3['education'] ?? [];
    $grantsPrefill = $step3['grants'] ?? [];
    $motherNa = !empty($step3['mother_na']);
    $fatherNa = !empty($step3['father_na']);
    $siblingsNa = !empty($step3['siblings_na']);
    $grantsNa = !empty($step3['grants_na']);

    $educationByLevel = [];
    foreach ($educationPrefill as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = strtolower(trim((string) ($row['level'] ?? '')));
        if ($key !== '') {
            $educationByLevel[$key] = $row;
        }
    }
    $eduElementary = $educationByLevel['elementary'] ?? ['level' => 'Elementary', 'school' => '', 'year' => '', 'honors' => '', 'course' => ''];
    $eduHighSchool = $educationByLevel['high school'] ?? ['level' => 'High School', 'school' => '', 'year' => '', 'honors' => '', 'course' => ''];
    $eduCollege = $educationByLevel['college'] ?? ['level' => 'College', 'school' => '', 'year' => '', 'honors' => '', 'course' => ''];
    $collegeYearLevelValue = trim((string) ($eduCollege['year'] ?? ''));
    $collegeYearLevelOptions = ['1', '2', '3', '4'];
    if (!in_array($collegeYearLevelValue, $collegeYearLevelOptions, true)) {
        $collegeYearLevelValue = '';
    }
    $collegeDefaultSchool = trim((string) ($step1['school_name'] ?? ''));
    $collegeDefaultCourse = trim((string) ($step1['course'] ?? ''));
    if (trim((string) ($eduCollege['school'] ?? '')) === '' && $collegeDefaultSchool !== '') {
        $eduCollege['school'] = $collegeDefaultSchool;
    }
    if (trim((string) ($eduCollege['course'] ?? '')) === '' && $collegeDefaultCourse !== '') {
        $eduCollege['course'] = $collegeDefaultCourse;
    }
    ?>
    <p class="small text-muted mb-3" id="autosaveStatus">Auto-save is enabled for this step.</p>
    <form method="post" id="applyStep3Form" data-autosave-step="3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_step3">

        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Step 3: Family Background</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <h3 class="h6">Mother</h3>
                        <div class="form-check mb-2">
                            <input class="form-check-input js-na-toggle" type="checkbox" id="motherNaToggle" name="mother_na" value="1" data-target="#motherFields" <?= $motherNa ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="motherNaToggle">Not Applicable</label>
                        </div>
                        <div class="row g-2" id="motherFields">
                            <div class="col-12"><input type="text" class="form-control" name="mother_name" placeholder="Mother's Name" value="<?= e((string) ($step3['mother_name'] ?? '')) ?>"></div>
                            <div class="col-4"><input type="number" class="form-control" name="mother_age" placeholder="Age" value="<?= e((string) ($step3['mother_age'] ?? '')) ?>"></div>
                            <div class="col-8"><input type="text" class="form-control" name="mother_occupation" placeholder="Occupation" value="<?= e((string) ($step3['mother_occupation'] ?? '')) ?>"></div>
                            <div class="col-12"><input type="number" step="0.01" class="form-control" name="mother_monthly_income" placeholder="Monthly Income" value="<?= e((string) ($step3['mother_monthly_income'] ?? '')) ?>"></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <h3 class="h6">Father</h3>
                        <div class="form-check mb-2">
                            <input class="form-check-input js-na-toggle" type="checkbox" id="fatherNaToggle" name="father_na" value="1" data-target="#fatherFields" <?= $fatherNa ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="fatherNaToggle">Not Applicable</label>
                        </div>
                        <div class="row g-2" id="fatherFields">
                            <div class="col-12"><input type="text" class="form-control" name="father_name" placeholder="Father's Name" value="<?= e((string) ($step3['father_name'] ?? '')) ?>"></div>
                            <div class="col-4"><input type="number" class="form-control" name="father_age" placeholder="Age" value="<?= e((string) ($step3['father_age'] ?? '')) ?>"></div>
                            <div class="col-8"><input type="text" class="form-control" name="father_occupation" placeholder="Occupation" value="<?= e((string) ($step3['father_occupation'] ?? '')) ?>"></div>
                            <div class="col-12"><input type="number" step="0.01" class="form-control" name="father_monthly_income" placeholder="Monthly Income" value="<?= e((string) ($step3['father_monthly_income'] ?? '')) ?>"></div>
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
                                    <td data-label="Name"><div class="wizard-inline-field-label">Name</div><input type="text" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][name]" value="<?= e((string) ($row['name'] ?? '')) ?>" placeholder="Full name"></td>
                                    <td data-label="Age"><div class="wizard-inline-field-label">Age</div><input type="number" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][age]" value="<?= e((string) ($row['age'] ?? '')) ?>" min="0"></td>
                                    <td data-label="Highest Educational Attainment"><div class="wizard-inline-field-label">Highest Educational Attainment</div><input type="text" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][education]" value="<?= e((string) ($row['education'] ?? '')) ?>" placeholder="e.g., College"></td>
                                    <td data-label="Occupation"><div class="wizard-inline-field-label">Occupation</div><input type="text" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][occupation]" value="<?= e((string) ($row['occupation'] ?? '')) ?>" placeholder="e.g., Student"></td>
                                    <td data-label="Monthly Income"><div class="wizard-inline-field-label">Monthly Income</div><input type="number" step="0.01" class="form-control form-control-sm" name="siblings[<?= (int) $i ?>][income]" value="<?= e((string) ($row['income'] ?? '')) ?>" min="0"></td>
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
                                    <input type="text" class="form-control form-control-sm" name="education[0][school]" value="<?= e((string) ($eduElementary['school'] ?? '')) ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Year Graduated</label>
                                    <input type="number" class="form-control form-control-sm" name="education[0][year]" value="<?= e((string) ($eduElementary['year'] ?? '')) ?>" min="1900" max="2100" step="1" inputmode="numeric" placeholder="YYYY">
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
                                    <input type="text" class="form-control form-control-sm" name="education[1][school]" value="<?= e((string) ($eduHighSchool['school'] ?? '')) ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Year Graduated</label>
                                    <input type="number" class="form-control form-control-sm" name="education[1][year]" value="<?= e((string) ($eduHighSchool['year'] ?? '')) ?>" min="1900" max="2100" step="1" inputmode="numeric" placeholder="YYYY">
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
                                    <input type="text" class="form-control form-control-sm" name="education[2][school]" value="<?= e((string) ($eduCollege['school'] ?? '')) ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Course</label>
                                    <input type="text" class="form-control form-control-sm" name="education[2][course]" value="<?= e((string) ($eduCollege['course'] ?? '')) ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label mb-1">Year Level</label>
                                    <select class="form-select form-select-sm" name="education[2][year]">
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
                                    <td data-label="Scholarship Program"><div class="wizard-inline-field-label">Scholarship Program</div><input type="text" class="form-control form-control-sm" name="grants[<?= (int) $i ?>][program]" value="<?= e((string) ($row['program'] ?? '')) ?>" placeholder="Program name"></td>
                                    <td data-label="Year/Period"><div class="wizard-inline-field-label">Year/Period</div><input type="text" class="form-control form-control-sm" name="grants[<?= (int) $i ?>][period]" value="<?= e((string) ($row['period'] ?? '')) ?>" placeholder="e.g., 2024-2025"></td>
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
            </div>
        </div>

        <div class="wizard-actions mb-3">
            <a href="apply.php?step=2" class="btn btn-outline-secondary wizard-btn-prev"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
            <button type="submit" class="btn btn-primary wizard-btn-next"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</button>
        </div>
    </form>
<?php endif; ?>

<?php if ($step === 4): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Step 4: Dynamic Requirements Upload</h2>
            <p class="small text-muted">Upload the required documents listed below. Please make sure the uploaded files are clear and readable.</p>
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
                            <input type="file" name="<?= e($field) ?>" class="form-control mt-2" accept=".pdf,.jpg,.jpeg,.png">
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
            form.addEventListener('submit', function () {
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

<?php endif; ?>

<?php if ($step === 6): ?>
    <div class="card card-soft shadow-sm wizard-review">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="h5 mb-0">Step 6: Review and Preview Before Submit</h2>
                <button type="button" class="btn btn-outline-primary btn-sm" id="openPrintablePreviewBtn">
                    <i class="fa-solid fa-print me-1"></i>Preview Printable Form
                </button>
            </div>
            <p class="small text-muted mb-3">Review your details carefully before final submission. You can still go back and edit any step.</p>
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
                                    <span class="review-kv-value"><?= e((string) ($step1['applicant_type'] ?? '')) ?></span>
                                </div>
                                <div class="review-kv-row">
                                    <span class="review-kv-label">Semester / School Year</span>
                                    <span class="review-kv-value"><?= e((string) ($step1['semester'] ?? '')) ?> / <?= e((string) ($step1['school_year'] ?? '')) ?></span>
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
                    $motherReview = !empty($step3['mother_na']) ? 'N/A' : trim((string) ($step3['mother_name'] ?? ''));
                    $fatherReview = !empty($step3['father_na']) ? 'N/A' : trim((string) ($step3['father_name'] ?? ''));
                    if ($motherReview === '') {
                        $motherReview = 'N/A';
                    }
                    if ($fatherReview === '') {
                        $fatherReview = 'N/A';
                    }
                    ?>
                    <div class="review-text-list">
                        <p class="mb-1"><strong>Applicant:</strong> <?= e((string) ($step2['last_name'] ?? '')) ?>, <?= e((string) ($step2['first_name'] ?? '')) ?> <?= e(trim((string) (($step2['middle_name'] ?? '') . ' ' . ($step2['suffix'] ?? '')))) ?></p>
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
            <p class="small text-muted mt-3 mb-0 review-note">After submitting, you can print/download the exact legal-size application form with QR code.</p>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('reviewPreviewModal');
            const titleEl = document.getElementById('reviewPreviewModalLabel');
            const frameEl = document.getElementById('reviewPreviewFrame');
            const printableBtn = document.getElementById('openPrintablePreviewBtn');
            if (!modalEl || !titleEl || !frameEl || typeof bootstrap === 'undefined') {
                return;
            }
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            const openPreview = function (title, src) {
                titleEl.textContent = title;
                frameEl.src = src;
                modal.show();
            };

            if (printableBtn) {
                printableBtn.addEventListener('click', function () {
                    openPreview('Printable Form Preview', 'print-application.php?draft=1&embed=1');
                });
            }

            document.querySelectorAll('.js-open-doc-preview').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const src = btn.getAttribute('data-preview-src') || '';
                    const title = btn.getAttribute('data-preview-title') || 'Document Preview';
                    if (!src) {
                        return;
                    }
                    openPreview(title, 'preview-document.php?file=' + encodeURIComponent(src));
                });
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                frameEl.src = 'about:blank';
            });
        });
    </script>
<?php endif; ?>

<?php if (in_array($step, [1, 2, 3], true)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.SE_APPLY_WIZARD) {
                return;
            }

            if (<?= (int) $step ?> === 1) {
                const schoolSelect = document.getElementById('applySchoolNameSelect');
                const otherWrapper = document.getElementById('applyOtherSchoolWrapper');
                const otherInput = document.getElementById('applyOtherSchoolInput');
                const courseSelect = document.getElementById('applyCourseSelect');
                const otherCourseWrapper = document.getElementById('applyOtherCourseWrapper');
                const otherCourseInput = document.getElementById('applyOtherCourseInput');
                if (schoolSelect && otherWrapper && otherInput) {
                    const syncOtherSchoolVisibility = function () {
                        const showOther = schoolSelect.value === '__other__';
                        otherWrapper.classList.toggle('d-none', !showOther);
                        otherInput.required = showOther;
                        if (!showOther) {
                            otherInput.value = '';
                        }
                    };
                    schoolSelect.addEventListener('change', syncOtherSchoolVisibility);
                    syncOtherSchoolVisibility();
                }
                if (courseSelect && otherCourseWrapper && otherCourseInput) {
                    const syncOtherCourseVisibility = function () {
                        const showOther = courseSelect.value === '__other__';
                        otherCourseWrapper.classList.toggle('d-none', !showOther);
                        otherCourseInput.required = showOther;
                        if (!showOther) {
                            otherCourseInput.value = '';
                        }
                    };
                    courseSelect.addEventListener('change', syncOtherCourseVisibility);
                    syncOtherCourseVisibility();
                }
            }

            if (<?= (int) $step ?> === 2 && typeof window.SE_APPLY_WIZARD.initBirthdateAgeSync === 'function') {
                window.SE_APPLY_WIZARD.initBirthdateAgeSync({
                    birthDateInputId: 'birthDateInput',
                    ageInputId: 'ageInput'
                });
            }

            if (<?= (int) $step ?> === 3 && typeof window.SE_APPLY_WIZARD.initNaToggles === 'function') {
                window.SE_APPLY_WIZARD.initNaToggles({
                    toggleSelector: '.js-na-toggle[data-target]',
                    honorsToggleId: 'honorsNaToggle',
                    honorsInputSelector: '.js-honors-input'
                });

                const siblingsBody = document.getElementById('siblingsTableBody');
                const addSiblingBtn = document.getElementById('addSiblingRowBtn');
                const siblingsNaToggle = document.getElementById('siblingsNaToggle');
                const grantsBody = document.getElementById('grantsTableBody');
                const addGrantsBtn = document.getElementById('addGrantsRowBtn');
                const grantsNaToggle = document.getElementById('grantsNaToggle');
                if (siblingsBody && addSiblingBtn) {
                    const maxRows = 10;
                    const syncAddButtonState = function () {
                        addSiblingBtn.disabled = !!(siblingsNaToggle && siblingsNaToggle.checked);
                    };
                    const getNextIndex = function () {
                        const current = Number(siblingsBody.getAttribute('data-next-index') || '0');
                        siblingsBody.setAttribute('data-next-index', String(current + 1));
                        return current;
                    };
                    const buildRow = function (index) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td data-label="Name"><div class="wizard-inline-field-label">Name</div><input type="text" class="form-control form-control-sm" name="siblings[${index}][name]" placeholder="Full name"></td>
                            <td data-label="Age"><div class="wizard-inline-field-label">Age</div><input type="number" class="form-control form-control-sm" name="siblings[${index}][age]" min="0"></td>
                            <td data-label="Highest Educational Attainment"><div class="wizard-inline-field-label">Highest Educational Attainment</div><input type="text" class="form-control form-control-sm" name="siblings[${index}][education]" placeholder="e.g., College"></td>
                            <td data-label="Occupation"><div class="wizard-inline-field-label">Occupation</div><input type="text" class="form-control form-control-sm" name="siblings[${index}][occupation]" placeholder="e.g., Student"></td>
                            <td data-label="Monthly Income"><div class="wizard-inline-field-label">Monthly Income</div><input type="number" step="0.01" class="form-control form-control-sm" name="siblings[${index}][income]" min="0"></td>
                            <td data-label="Action" class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger js-remove-sibling-row" aria-label="Remove sibling row">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </td>
                        `;
                        return tr;
                    };
                    const removeRow = function (button) {
                        const row = button.closest('tr');
                        if (!row) {
                            return;
                        }
                        if (siblingsBody.querySelectorAll('tr').length <= 1) {
                            row.querySelectorAll('input').forEach(function (input) {
                                input.value = '';
                            });
                            return;
                        }
                        row.remove();
                    };

                    addSiblingBtn.addEventListener('click', function () {
                        const currentRows = siblingsBody.querySelectorAll('tr').length;
                        if (currentRows >= maxRows) {
                            return;
                        }
                        siblingsBody.appendChild(buildRow(getNextIndex()));
                    });

                    siblingsBody.addEventListener('click', function (event) {
                        const target = event.target;
                        if (!(target instanceof HTMLElement)) {
                            return;
                        }
                        const removeBtn = target.closest('.js-remove-sibling-row');
                        if (!removeBtn) {
                            return;
                        }
                        removeRow(removeBtn);
                    });

                    if (siblingsNaToggle) {
                        siblingsNaToggle.addEventListener('change', syncAddButtonState);
                    }
                    syncAddButtonState();
                }

                const initDynamicRows = function (config) {
                    if (!config.body || !config.addBtn) {
                        return;
                    }
                    const maxRows = config.maxRows || 10;
                    const getNextIndex = function () {
                        const current = Number(config.body.getAttribute('data-next-index') || '0');
                        config.body.setAttribute('data-next-index', String(current + 1));
                        return current;
                    };
                    const buildRow = function (index) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = config.rowHtml(index);
                        return tr;
                    };
                    const syncState = function () {
                        const disabledByToggle = !!(config.naToggle && config.naToggle.checked);
                        config.addBtn.disabled = disabledByToggle;
                    };
                    config.addBtn.addEventListener('click', function () {
                        if (config.addBtn.disabled) {
                            return;
                        }
                        const currentRows = config.body.querySelectorAll('tr').length;
                        if (currentRows >= maxRows) {
                            return;
                        }
                        config.body.appendChild(buildRow(getNextIndex()));
                    });
                    config.body.addEventListener('click', function (event) {
                        const target = event.target;
                        if (!(target instanceof HTMLElement)) {
                            return;
                        }
                        const removeBtn = target.closest(config.removeSelector);
                        if (!removeBtn) {
                            return;
                        }
                        const row = removeBtn.closest('tr');
                        if (!row) {
                            return;
                        }
                        if (config.body.querySelectorAll('tr').length <= 1) {
                            row.querySelectorAll('input').forEach(function (input) {
                                input.value = '';
                            });
                            return;
                        }
                        row.remove();
                    });
                    if (config.naToggle) {
                        config.naToggle.addEventListener('change', syncState);
                    }
                    syncState();
                };

                initDynamicRows({
                    body: grantsBody,
                    addBtn: addGrantsBtn,
                    naToggle: grantsNaToggle,
                    removeSelector: '.js-remove-grants-row',
                    maxRows: 8,
                    rowHtml: function (index) {
                        return `
                            <td data-label="Scholarship Program"><div class="wizard-inline-field-label">Scholarship Program</div><input type="text" class="form-control form-control-sm" name="grants[${index}][program]" placeholder="Program name"></td>
                            <td data-label="Year/Period"><div class="wizard-inline-field-label">Year/Period</div><input type="text" class="form-control form-control-sm" name="grants[${index}][period]" placeholder="e.g., 2024-2025"></td>
                            <td data-label="Action" class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger js-remove-grants-row" aria-label="Remove grants row">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </td>
                        `;
                    }
                });
            }

            if (typeof window.SE_APPLY_WIZARD.initAutosave === 'function') {
                window.SE_APPLY_WIZARD.initAutosave({
                    formSelector: 'form[data-autosave-step="<?= (int) $step ?>"]',
                    statusId: 'autosaveStatus',
                    step: <?= (int) $step ?>,
                    endpoint: 'apply-autosave.php',
                    intervalMs: 10000,
                    debounceMs: 1200
                });
            }
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

