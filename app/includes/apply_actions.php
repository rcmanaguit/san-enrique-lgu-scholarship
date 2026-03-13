<?php
declare(strict_types=1);

function apply_project_root(): string
{
    return dirname(__DIR__, 2);
}

function apply_persist_wizard_state(mysqli $conn, int $userId, array $state, int $currentStep): void
{
    wizard_save($state);
    wizard_save_persistent_draft($conn, $userId, $state, $currentStep);
}

function apply_is_deferred_requirement_for_initial_application(array $req): bool
{
    $name = strtolower(trim((string) ($req['requirement_name'] ?? '')));
    if ($name === '') {
        return false;
    }

    return str_contains($name, 'soa') || str_contains($name, 'student copy') || str_contains($name, 'statement of account');
}

function apply_is_photo_handled_in_step_five(array $req): bool
{
    $name = strtolower(trim((string) ($req['requirement_name'] ?? '')));
    if ($name === '') {
        return false;
    }

    return str_contains($name, '2x2') && (str_contains($name, 'picture') || str_contains($name, 'photo'));
}

function apply_get_requirements(mysqli $conn, array $state): array
{
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
}

function apply_get_step_four_requirements(mysqli $conn, array $state): array
{
    $requirements = apply_get_requirements($conn, $state);

    return array_values(array_filter($requirements, static function (array $req): bool {
        return !apply_is_photo_handled_in_step_five($req)
            && !apply_is_deferred_requirement_for_initial_application($req);
    }));
}

function apply_get_later_requirements(mysqli $conn, array $state): array
{
    $requirements = apply_get_requirements($conn, $state);

    return array_values(array_filter($requirements, static function (array $req): bool {
        return apply_is_deferred_requirement_for_initial_application($req);
    }));
}

function apply_detect_applicant_type(mysqli $conn, array $user, array $openPeriod): string
{
    $detectedApplicantType = 'new';
    $hasPreviousApplication = false;
    $userId = (int) ($user['id'] ?? 0);
    $openPeriodId = (int) ($openPeriod['id'] ?? 0);

    if ($userId > 0 && table_exists($conn, 'applications')) {
        if ($openPeriodId > 0 && table_column_exists($conn, 'applications', 'application_period_id')) {
            $stmt = $conn->prepare(
                "SELECT id
                 FROM applications
                 WHERE user_id = ?
                   AND (application_period_id <> ? OR application_period_id IS NULL)
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('ii', $userId, $openPeriodId);
                $stmt->execute();
                $result = $stmt->get_result();
                $hasPreviousApplication = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare(
                "SELECT id
                 FROM applications
                 WHERE user_id = ?
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $hasPreviousApplication = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
                $stmt->close();
            }
        }
    }

    if ($hasPreviousApplication) {
        $detectedApplicantType = 'renew';
    }

    return $detectedApplicantType;
}

function apply_load_latest_previous_application(mysqli $conn, int $userId, array $openPeriod): ?array
{
    if ($userId <= 0 || !table_exists($conn, 'applications')) {
        return null;
    }

    $openPeriodId = (int) ($openPeriod['id'] ?? 0);
    $hasApplicationPeriodColumn = table_column_exists($conn, 'applications', 'application_period_id');

    if ($hasApplicationPeriodColumn && $openPeriodId > 0) {
        $stmt = $conn->prepare(
            "SELECT a.*, u.email
             FROM applications a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.user_id = ?
               AND (a.application_period_id <> ? OR a.application_period_id IS NULL)
             ORDER BY COALESCE(a.submitted_at, a.created_at) DESC, a.id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ii', $userId, $openPeriodId);
    } else {
        $stmt = $conn->prepare(
            "SELECT a.*, u.email
             FROM applications a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.user_id = ?
             ORDER BY COALESCE(a.submitted_at, a.created_at) DESC, a.id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: null) : null;
    $stmt->close();

    return $row;
}

function apply_prefill_wizard_from_previous_application(array $wizard, array $previousApplication, array $openPeriod): array
{
    $motherName = trim((string) ($previousApplication['mother_name'] ?? ''));
    $fatherName = trim((string) ($previousApplication['father_name'] ?? ''));
    $siblings = json_array((string) ($previousApplication['siblings_json'] ?? ''));
    $education = json_array((string) ($previousApplication['educational_background_json'] ?? ''));
    $grants = json_array((string) ($previousApplication['grants_availed_json'] ?? ''));
    $contactNumber = trim((string) ($previousApplication['contact_number'] ?? ''));
    if ($contactNumber !== '' && is_valid_mobile_number($contactNumber)) {
        $contactNumber = normalize_mobile_number($contactNumber);
    }

    $wizard['step1'] = array_merge($wizard['step1'] ?? [], [
        'applicant_type' => 'renew',
        'semester' => trim((string) ($openPeriod['semester'] ?? '')),
        'school_year' => trim((string) ($openPeriod['academic_year'] ?? '')),
        'school_name' => normalize_school_name((string) ($previousApplication['school_name'] ?? '')),
        'school_type' => trim((string) ($previousApplication['school_type'] ?? '')),
        'course' => normalize_course_name((string) ($previousApplication['course'] ?? '')),
    ]);

    $wizard['step2'] = array_merge($wizard['step2'] ?? [], [
        'last_name' => trim((string) ($previousApplication['last_name'] ?? '')),
        'first_name' => trim((string) ($previousApplication['first_name'] ?? '')),
        'middle_name' => trim((string) ($previousApplication['middle_name'] ?? '')),
        'email' => strtolower(trim((string) ($previousApplication['email'] ?? ''))),
        'suffix' => trim((string) ($previousApplication['suffix'] ?? '')),
        'age' => trim((string) ($previousApplication['age'] ?? '')),
        'civil_status' => trim((string) ($previousApplication['civil_status'] ?? '')),
        'sex' => trim((string) ($previousApplication['sex'] ?? '')),
        'birth_date' => trim((string) ($previousApplication['birth_date'] ?? '')),
        'birth_place' => trim((string) ($previousApplication['birth_place'] ?? '')),
        'barangay' => normalize_barangay((string) ($previousApplication['barangay'] ?? '')),
        'town' => trim((string) ($previousApplication['town'] ?? san_enrique_town())) ?: san_enrique_town(),
        'province' => trim((string) ($previousApplication['province'] ?? san_enrique_province())) ?: san_enrique_province(),
        'address' => trim((string) ($previousApplication['address'] ?? '')),
        'contact_number' => $contactNumber,
    ]);

    $wizard['step3'] = array_merge($wizard['step3'] ?? [], [
        'mother_na' => $motherName === '' ? 1 : 0,
        'mother_deceased' => strcasecmp($motherName, 'Deceased') === 0 ? 1 : 0,
        'mother_name' => strcasecmp($motherName, 'Deceased') === 0 ? '' : $motherName,
        'mother_age' => trim((string) ($previousApplication['mother_age'] ?? '')),
        'mother_occupation' => trim((string) ($previousApplication['mother_occupation'] ?? '')),
        'mother_monthly_income' => trim((string) ($previousApplication['mother_monthly_income'] ?? '')),
        'father_na' => $fatherName === '' ? 1 : 0,
        'father_deceased' => strcasecmp($fatherName, 'Deceased') === 0 ? 1 : 0,
        'father_name' => strcasecmp($fatherName, 'Deceased') === 0 ? '' : $fatherName,
        'father_age' => trim((string) ($previousApplication['father_age'] ?? '')),
        'father_occupation' => trim((string) ($previousApplication['father_occupation'] ?? '')),
        'father_monthly_income' => trim((string) ($previousApplication['father_monthly_income'] ?? '')),
        'honors_na' => 0,
        'siblings_na' => $siblings === [] ? 1 : 0,
        'siblings' => is_array($siblings) ? $siblings : [],
        'education' => is_array($education) ? $education : [],
        'grants_na' => $grants === [] ? 1 : 0,
        'grants' => is_array($grants) ? $grants : [],
    ]);

    $wizard['prefilled_from_previous'] = true;
    $wizard['prefill_application_id'] = (int) ($previousApplication['id'] ?? 0);

    return $wizard;
}

function apply_bootstrap_page_state(mysqli $conn, array $user, array $openPeriod): array
{
    $detectedApplicantType = apply_detect_applicant_type($conn, $user, $openPeriod);

    if (applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod)) {
        set_flash('warning', 'You already submitted an application for the current open period. Only one application is allowed per period.');
        redirect('my-application.php');
    }

    $wizard = wizard_state();
    $persistentDraft = db_ready() ? wizard_load_persistent_draft($conn, (int) ($user['id'] ?? 0)) : null;
    if (is_array($persistentDraft['state'] ?? null)) {
        $wizard = array_merge($wizard, (array) $persistentDraft['state']);
        wizard_save($wizard);
    } elseif (
        $detectedApplicantType === 'renew'
        && !wizard_has_progress($wizard)
    ) {
        $previousApplication = apply_load_latest_previous_application($conn, (int) ($user['id'] ?? 0), $openPeriod);
        if ($previousApplication) {
            $wizard = apply_prefill_wizard_from_previous_application($wizard, $previousApplication, $openPeriod);
            wizard_save($wizard);
        }
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

    return [
        'detected_applicant_type' => $detectedApplicantType,
        'wizard' => $wizard,
        'persistent_draft' => $persistentDraft,
        'step' => $step,
    ];
}

function apply_prepare_view_data(mysqli $conn, array $user, array $openPeriod, array $wizard, string $detectedApplicantType): array
{
    $step1 = $wizard['step1'];
    $step2 = $wizard['step2'];
    $step3 = $wizard['step3'];
    $prefilledFromPrevious = !empty($wizard['prefilled_from_previous']);

    $step1['applicant_type'] = trim((string) ($step1['applicant_type'] ?? '')) !== ''
        ? trim((string) $step1['applicant_type'])
        : $detectedApplicantType;
    $applicantTypeLabel = ($step1['applicant_type'] ?? $detectedApplicantType) === 'renew'
        ? 'Renewing Applicant'
        : 'New Applicant';
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

    $requirements = apply_get_step_four_requirements($conn, $wizard);
    $laterRequirements = apply_get_later_requirements($conn, $wizard);
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

    return [
        'step1' => $step1,
        'step2' => $step2,
        'step3' => $step3,
        'applicantTypeLabel' => $applicantTypeLabel,
        'schoolNameOptions' => $schoolNameOptions,
        'selectedSchoolName' => $selectedSchoolName,
        'otherSchoolName' => $otherSchoolName,
        'isOtherSchoolSelected' => $isOtherSchoolSelected,
        'courseOptions' => $courseOptions,
        'selectedCourse' => $selectedCourse,
        'otherCourse' => $otherCourse,
        'isOtherCourseSelected' => $isOtherCourseSelected,
        'requirements' => $requirements,
        'laterRequirements' => $laterRequirements,
        'barangayOptions' => $barangayOptions,
        'reviewIssues' => $reviewIssues,
        'prefilledFromPrevious' => $prefilledFromPrevious,
        'stepLabels' => [1 => 'Program', 2 => 'Personal', 3 => 'Family', 4 => 'Requirements', 5 => '2x2 Photo', 6 => 'Review'],
    ];
}

function apply_handle_post_request(
    mysqli $conn,
    array &$user,
    array $openPeriod,
    string $detectedApplicantType,
    int $step,
    callable $persistWizard,
    callable $getStepFourRequirements
): void {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
        apply_handle_upload_size_overflow($step);
    }

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('apply.php?step=' . $step);
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $wizard = wizard_state();

    if ($action === 'save_step1') {
        apply_handle_save_step1($wizard, $openPeriod, $detectedApplicantType, $persistWizard);
        return;
    }

    if ($action === 'save_step2') {
        apply_handle_save_step2($conn, $user, $wizard, $persistWizard);
        return;
    }

    if ($action === 'save_step3') {
        apply_handle_save_step3($wizard, $persistWizard);
        return;
    }

    if ($action === 'save_step4') {
        apply_handle_save_step4($wizard, $persistWizard, $getStepFourRequirements);
        return;
    }

    if ($action === 'final_submit') {
        apply_handle_final_submit($conn, $user, $wizard, $getStepFourRequirements);
    }
}

function apply_handle_upload_size_overflow(int $step): void
{
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

function apply_handle_save_step1(array $wizard, array $openPeriod, string $detectedApplicantType, callable $persistWizard): void
{
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
        'applicant_type' => trim((string) ($_POST['applicant_type'] ?? '')) ?: $detectedApplicantType,
        'semester' => $periodSemester !== '' ? $periodSemester : trim((string) ($_POST['semester'] ?? '')),
        'school_year' => $periodSchoolYear !== '' ? $periodSchoolYear : trim((string) ($_POST['school_year'] ?? '')),
        'school_name' => normalize_school_name($resolvedSchoolName),
        'school_type' => trim((string) ($_POST['school_type'] ?? '')),
        'course' => normalize_course_name($resolvedCourse),
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

function apply_handle_save_step2(mysqli $conn, array &$user, array $wizard, callable $persistWizard): void
{
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
        'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
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

    if (
        $data['last_name'] === ''
        || $data['first_name'] === ''
        || $data['email'] === ''
        || $data['birth_date'] === ''
        || $data['civil_status'] === ''
        || $data['sex'] === ''
        || $data['birth_place'] === ''
        || $data['address'] === ''
        || $data['contact_number'] === ''
        || $data['barangay'] === ''
    ) {
        set_flash('danger', 'Please complete all required fields in Step 2.');
        redirect('apply.php?step=2');
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please enter a valid email address.');
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
    $currentUserId = (int) ($user['id'] ?? 0);
    $stmtEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    if (!$stmtEmail) {
        set_flash('danger', 'Unable to validate email right now. Please try again.');
        redirect('apply.php?step=2');
    }
    $stmtEmail->bind_param('si', $data['email'], $currentUserId);
    $stmtEmail->execute();
    $emailExists = $stmtEmail->get_result()->fetch_assoc();
    $stmtEmail->close();
    if ($emailExists) {
        set_flash('danger', 'Email is already assigned to another account.');
        redirect('apply.php?step=2');
    }
    $stmtUserEmail = $conn->prepare("UPDATE users SET email = ? WHERE id = ? LIMIT 1");
    if (!$stmtUserEmail) {
        set_flash('danger', 'Unable to save your email right now. Please try again.');
        redirect('apply.php?step=2');
    }
    $stmtUserEmail->bind_param('si', $data['email'], $currentUserId);
    $stmtUserEmail->execute();
    $stmtUserEmail->close();
    $_SESSION['user']['email'] = $data['email'];
    $user['email'] = $data['email'];

    $wizard['step2'] = $data;
    $wizard['step2_done'] = true;
    $persistWizard($wizard, 3);
    redirect('apply.php?step=3');
}

function apply_handle_save_step3(array $wizard, callable $persistWizard): void
{
    $motherNa = isset($_POST['mother_na']) ? 1 : 0;
    $motherDeceased = isset($_POST['mother_deceased']) ? 1 : 0;
    $fatherNa = isset($_POST['father_na']) ? 1 : 0;
    $fatherDeceased = isset($_POST['father_deceased']) ? 1 : 0;
    $siblingsNa = isset($_POST['siblings_na']) ? 1 : 0;
    $grantsNa = isset($_POST['grants_na']) ? 1 : 0;

    if ($motherNa && $motherDeceased) {
        set_flash('danger', 'Please select only one mother option: Not Applicable or Deceased.');
        redirect('apply.php?step=3');
    }
    if ($fatherNa && $fatherDeceased) {
        set_flash('danger', 'Please select only one father option: Not Applicable or Deceased.');
        redirect('apply.php?step=3');
    }

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

    if (
        !$motherNa
        && !$motherDeceased
        && (
            trim((string) ($_POST['mother_name'] ?? '')) === ''
            || trim((string) ($_POST['mother_age'] ?? '')) === ''
            || trim((string) ($_POST['mother_occupation'] ?? '')) === ''
            || trim((string) ($_POST['mother_monthly_income'] ?? '')) === ''
        )
    ) {
        set_flash('danger', 'Please complete all required mother information, or mark it as Not Applicable or Deceased.');
        redirect('apply.php?step=3');
    }
    if (
        !$fatherNa
        && !$fatherDeceased
        && (
            trim((string) ($_POST['father_name'] ?? '')) === ''
            || trim((string) ($_POST['father_age'] ?? '')) === ''
            || trim((string) ($_POST['father_occupation'] ?? '')) === ''
            || trim((string) ($_POST['father_monthly_income'] ?? '')) === ''
        )
    ) {
        set_flash('danger', 'Please complete all required father information, or mark it as Not Applicable or Deceased.');
        redirect('apply.php?step=3');
    }
    if (!$siblingsNa && count($siblings) === 0) {
        set_flash('danger', 'Please add at least one sibling entry or mark siblings as Not Applicable.');
        redirect('apply.php?step=3');
    }
    if (!$grantsNa && count($grants) === 0) {
        set_flash('danger', 'Please add at least one scholarship grant entry or mark it as Not Applicable.');
        redirect('apply.php?step=3');
    }
    if (
        trim((string) ($_POST['education'][0]['school'] ?? '')) === ''
        || trim((string) ($_POST['education'][0]['year'] ?? '')) === ''
        || trim((string) ($_POST['education'][1]['school'] ?? '')) === ''
        || trim((string) ($_POST['education'][1]['year'] ?? '')) === ''
        || trim((string) ($_POST['education'][2]['school'] ?? '')) === ''
        || trim((string) ($_POST['education'][2]['course'] ?? '')) === ''
        || trim((string) ($_POST['education'][2]['year'] ?? '')) === ''
    ) {
        set_flash('danger', 'Please complete all required educational background fields in Step 3.');
        redirect('apply.php?step=3');
    }

    $wizard['step3'] = [
        'mother_na' => $motherNa,
        'mother_deceased' => $motherDeceased,
        'mother_name' => $motherNa ? '' : ($motherDeceased ? 'Deceased' : trim((string) ($_POST['mother_name'] ?? ''))),
        'mother_age' => ($motherNa || $motherDeceased) ? '' : trim((string) ($_POST['mother_age'] ?? '')),
        'mother_occupation' => ($motherNa || $motherDeceased) ? '' : trim((string) ($_POST['mother_occupation'] ?? '')),
        'mother_monthly_income' => ($motherNa || $motherDeceased) ? '' : trim((string) ($_POST['mother_monthly_income'] ?? '')),
        'father_na' => $fatherNa,
        'father_deceased' => $fatherDeceased,
        'father_name' => $fatherNa ? '' : ($fatherDeceased ? 'Deceased' : trim((string) ($_POST['father_name'] ?? ''))),
        'father_age' => ($fatherNa || $fatherDeceased) ? '' : trim((string) ($_POST['father_age'] ?? '')),
        'father_occupation' => ($fatherNa || $fatherDeceased) ? '' : trim((string) ($_POST['father_occupation'] ?? '')),
        'father_monthly_income' => ($fatherNa || $fatherDeceased) ? '' : trim((string) ($_POST['father_monthly_income'] ?? '')),
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

function apply_handle_save_step4(array $wizard, callable $persistWizard, callable $getStepFourRequirements): void
{
    $projectRoot = apply_project_root();
    $requirements = $getStepFourRequirements($wizard);
    $errors = [];
    foreach ($requirements as $req) {
        $reqId = (string) $req['id'];
        $field = 'req_' . $reqId;
        $existing = $wizard['documents'][$reqId] ?? null;
        $uploaded = upload_any_file($field, __DIR__ . '/../../uploads/tmp');
        if ($uploaded) {
            if ($existing && !empty($existing['file_path'])) {
                $oldAbsolute = $projectRoot . '/' . ltrim((string) $existing['file_path'], '/');
                if (file_exists($oldAbsolute)) {
                    @unlink($oldAbsolute);
                }
            }

            $filePath = (string) $uploaded['file_path'];
                $fileExt = (string) $uploaded['ext'];
                $originalName = (string) $uploaded['original_name'];
                $relative = str_replace(str_replace('\\', '/', $projectRoot) . '/', '', str_replace('\\', '/', $filePath));
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

function apply_handle_final_submit(mysqli $conn, array $user, array $wizard, callable $getStepFourRequirements): void
{
    $projectRoot = apply_project_root();
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
            application_no, user_id, " . $periodColumnSql . "applicant_type, semester, school_year,
            school_name, school_type, course, last_name, first_name, middle_name, suffix,
            age, civil_status, sex, birth_date, birth_place, barangay, town, province, address, contact_number,
            mother_name, mother_age, mother_occupation, mother_monthly_income,
            father_name, father_age, father_occupation, father_monthly_income,
            siblings_json, educational_background_json, grants_availed_json, photo_path, status, submitted_at
        ) VALUES (
            " . $esc($applicationNo) . ",
            " . (int) $user['id'] . ",
            " . $periodValueSql . "
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

        $finalPhoto = move_temp_file_to_final((string) $wizard['photo_path'], $projectRoot . '/uploads/photos', 'app_' . $applicationId . '_photo');
        if ($finalPhoto) {
            $stmtPhoto = $conn->prepare("UPDATE applications SET photo_path = ? WHERE id = ?");
            $stmtPhoto->bind_param('si', $finalPhoto, $applicationId);
            $stmtPhoto->execute();
            $stmtPhoto->close();
        }

        foreach ($wizard['documents'] as $doc) {
            $finalDoc = move_temp_file_to_final((string) ($doc['file_path'] ?? ''), $projectRoot . '/uploads/documents', 'app_' . $applicationId . '_doc');
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

        $smsMessage = 'San Enrique LGU Scholarship: Application ' . $applicationNo . ' has been submitted and is currently under review. Please wait for further updates.';
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
            'Your application ' . $applicationNo . ' has been submitted and is currently under review. Please wait for further updates.',
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
