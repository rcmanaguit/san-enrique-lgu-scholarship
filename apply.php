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
    redirect('apply.php?step=5');
}
wizard_save_persistent_draft($conn, (int) ($user['id'] ?? 0), $wizard, $step);

$persistWizard = static function (array $state, int $currentStep) use ($conn, $user): void {
    wizard_save($state);
    wizard_save_persistent_draft($conn, (int) ($user['id'] ?? 0), $state, $currentStep);
};

$getRequirements = static function (array $state) use ($conn): array {
    $step1 = $state['step1'] ?? [];
    $requirements = active_requirements(
        $conn,
        (string) ($step1['scholarship_type'] ?? ''),
        (string) ($step1['applicant_type'] ?? ''),
        (string) ($step1['school_type'] ?? '')
    );

    if (!$requirements) {
        $requirements = [
            ['id' => -1001, 'requirement_name' => 'Report Card / Previous Semester (Photocopy)', 'description' => '', 'is_required' => 1],
            ['id' => -1002, 'requirement_name' => '1 pc 2x2 Picture', 'description' => '', 'is_required' => 1],
            ['id' => -1003, 'requirement_name' => 'Barangay Residency', 'description' => '', 'is_required' => 1],
            ['id' => -1004, 'requirement_name' => 'Original Student Copy / Statement of Account (SOA)', 'description' => '', 'is_required' => 1],
        ];
    }

    return $requirements;
};

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('apply.php?step=' . $step);
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $wizard = wizard_state();

    if ($action === 'save_step1') {
        $semesterOptions = ['First Semester', 'Second Semester'];
        $data = [
            'scholarship_type' => trim((string) ($_POST['scholarship_type'] ?? '')),
            'applicant_type' => trim((string) ($_POST['applicant_type'] ?? '')),
            'semester' => trim((string) ($_POST['semester'] ?? '')),
            'school_year' => trim((string) ($_POST['school_year'] ?? '')),
            'school_name' => trim((string) ($_POST['school_name'] ?? '')),
            'school_type' => trim((string) ($_POST['school_type'] ?? '')),
            'course' => trim((string) ($_POST['course'] ?? '')),
            'year_level' => trim((string) ($_POST['year_level'] ?? '')),
            'gwa' => trim((string) ($_POST['gwa'] ?? '')),
            'family_income' => trim((string) ($_POST['family_income'] ?? '')),
            'reason' => trim((string) ($_POST['reason'] ?? '')),
        ];

        $required = ['scholarship_type', 'applicant_type', 'semester', 'school_year', 'school_name', 'school_type'];
        foreach ($required as $field) {
            if ($data[$field] === '') {
                set_flash('danger', 'Please complete all required fields in Step 1.');
                redirect('apply.php?step=1');
            }
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

        $data = [
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'middle_name' => trim((string) ($_POST['middle_name'] ?? '')),
            'age' => $computedAge === null ? '' : (string) $computedAge,
            'civil_status' => trim((string) ($_POST['civil_status'] ?? '')),
            'sex' => trim((string) ($_POST['sex'] ?? '')),
            'birth_date' => $birthDate,
            'birth_place' => trim((string) ($_POST['birth_place'] ?? '')),
            'barangay' => $barangay,
            'town' => san_enrique_town(),
            'province' => san_enrique_province(),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'contact_number' => trim((string) ($_POST['contact_number'] ?? '')),
        ];

        if ($data['last_name'] === '' || $data['first_name'] === '' || $data['contact_number'] === '' || $data['barangay'] === '') {
            set_flash('danger', 'Last name, first name, contact number, and barangay are required.');
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
            $entry = [
                'level' => trim((string) ($row['level'] ?? '')),
                'school' => trim((string) ($row['school'] ?? '')),
                'year' => trim((string) ($row['year'] ?? '')),
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
        $requirements = $getRequirements($wizard);
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

                $relative = str_replace(str_replace('\\', '/', __DIR__) . '/', '', str_replace('\\', '/', (string) $uploaded['file_path']));
                $wizard['documents'][$reqId] = [
                    'requirement_template_id' => (int) $req['id'],
                    'requirement_name' => (string) $req['requirement_name'],
                    'file_path' => $relative,
                    'file_ext' => (string) $uploaded['ext'],
                    'original_name' => (string) $uploaded['original_name'],
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
        redirect('apply.php?step=5');
    }

    if ($action === 'save_step5') {
        $photoBase64 = trim((string) ($_POST['photo_base64'] ?? ''));
        $existingPhoto = (string) ($wizard['photo_path'] ?? '');

        try {
            if ($photoBase64 !== '') {
                $stored = save_base64_image($photoBase64, __DIR__ . '/uploads/tmp');
                $relative = str_replace(str_replace('\\', '/', __DIR__) . '/', '', str_replace('\\', '/', $stored));

                if ($existingPhoto !== '') {
                    $oldAbsolute = __DIR__ . '/' . ltrim($existingPhoto, '/');
                    if (file_exists($oldAbsolute)) {
                        @unlink($oldAbsolute);
                    }
                }
                $wizard['photo_path'] = $relative;
            } else {
                $uploaded = upload_any_file('photo_upload', __DIR__ . '/uploads/tmp', ['jpg', 'jpeg', 'png', 'webp']);
                if ($uploaded) {
                    $relative = str_replace(str_replace('\\', '/', __DIR__) . '/', '', str_replace('\\', '/', (string) $uploaded['file_path']));
                    $wizard['photo_path'] = $relative;
                }
            }

            if (empty($wizard['photo_path'])) {
                set_flash('danger', 'Please upload/capture and crop your 2x2 photo.');
                redirect('apply.php?step=5');
            }
        } catch (Throwable $e) {
            set_flash('danger', 'Photo upload failed: ' . $e->getMessage());
            redirect('apply.php?step=5');
        }

        $persistWizard($wizard, 6);
        redirect('apply.php?step=6');
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

        $requirements = $getRequirements($wizard);
        $missing = [];
        foreach ($requirements as $req) {
            $required = (int) ($req['is_required'] ?? 1) === 1;
            $reqId = (string) $req['id'];
            if ($required && empty($wizard['documents'][$reqId])) {
                $missing[] = $req['requirement_name'];
            }
        }

        if (
            !(bool) ($wizard['step1_done'] ?? false)
            || !(bool) ($wizard['step2_done'] ?? false)
            || !(bool) ($wizard['step3_done'] ?? false)
            || empty($wizard['photo_path'])
            || $missing
        ) {
            set_flash('danger', 'Please complete all required steps before final submission.');
            redirect('apply.php?step=6');
        }

        $accountPassword = (string) ($_POST['account_password'] ?? '');
        $confirmAccountPassword = (string) ($_POST['confirm_account_password'] ?? '');
        if ($accountPassword === '' || $confirmAccountPassword === '') {
            set_flash('danger', 'Please enter and confirm your account password before submitting.');
            redirect('apply.php?step=6');
        }
        if ($accountPassword !== $confirmAccountPassword) {
            set_flash('danger', 'Account password and confirm password do not match.');
            redirect('apply.php?step=6');
        }

        $stmtPassword = $conn->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmtPassword->bind_param('i', $user['id']);
        $stmtPassword->execute();
        $storedPasswordHash = (string) (($stmtPassword->get_result()->fetch_assoc()['password_hash'] ?? ''));
        $stmtPassword->close();

        if (!password_verify($accountPassword, $storedPasswordHash)) {
            set_flash('danger', 'Account password is incorrect.');
            redirect('apply.php?step=6');
        }

        $step1 = $wizard['step1'];
        $step2 = $wizard['step2'];
        $step3 = $wizard['step3'];
        $step2['contact_number'] = normalize_mobile_number((string) ($step2['contact_number'] ?? ''));
        $step2['barangay'] = normalize_barangay((string) ($step2['barangay'] ?? ''));
        $step2['town'] = san_enrique_town();
        $step2['province'] = san_enrique_province();
        $step2['age'] = (string) (calculate_age_from_birth_date((string) ($step2['birth_date'] ?? '')) ?? '');

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
                application_no, user_id, " . $periodColumnSql . "qr_token, scholarship_type, applicant_type, semester, school_year,
                school_name, school_type, course, year_level, last_name, first_name, middle_name,
                age, civil_status, sex, birth_date, birth_place, barangay, town, province, address, contact_number,
                mother_name, mother_age, mother_occupation, mother_monthly_income,
                father_name, father_age, father_occupation, father_monthly_income,
                siblings_json, educational_background_json, grants_availed_json, gwa, family_income,
                reason, photo_path, status, submitted_at
            ) VALUES (
                " . $esc($applicationNo) . ",
                " . (int) $user['id'] . ",
                " . $periodValueSql . "
                " . $esc($qrToken) . ",
                " . $esc((string) $step1['scholarship_type']) . ",
                " . $esc((string) $step1['applicant_type']) . ",
                " . $esc((string) $step1['semester']) . ",
                " . $esc((string) $step1['school_year']) . ",
                " . $esc((string) $step1['school_name']) . ",
                " . $esc((string) $step1['school_type']) . ",
                " . $nullable($step1['course'] ?? '') . ",
                " . $nullable($step1['year_level'] ?? '') . ",
                " . $esc((string) $step2['last_name']) . ",
                " . $esc((string) $step2['first_name']) . ",
                " . $nullable($step2['middle_name'] ?? '') . ",
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
                " . $nullable($step1['gwa'] ?? '') . ",
                " . $nullable($step1['family_income'] ?? '') . ",
                " . $nullable($step1['reason'] ?? '') . ",
                NULL,
                'submitted',
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
                 SET first_name = ?, middle_name = ?, last_name = ?, phone = ?, school_name = ?, school_type = ?, course = ?, year_level = ?, barangay = ?, town = ?, province = ?, address = ?
                 WHERE id = ?"
            );
            $stmtUser->bind_param(
                'ssssssssssssi',
                $step2['first_name'],
                $step2['middle_name'],
                $step2['last_name'],
                $step2['contact_number'],
                $step1['school_name'],
                $step1['school_type'],
                $step1['course'],
                $step1['year_level'],
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
                    'scholarship_type' => (string) ($step1['scholarship_type'] ?? ''),
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
$requirements = $getRequirements($wizard);
$barangayOptions = san_enrique_barangays();

if (trim((string) ($step2['birth_date'] ?? '')) !== '' && trim((string) ($step2['age'] ?? '')) === '') {
    $step2['age'] = (string) (calculate_age_from_birth_date((string) ($step2['birth_date'] ?? '')) ?? '');
}
$step2['town'] = trim((string) ($step2['town'] ?? '')) !== '' ? (string) $step2['town'] : san_enrique_town();
$step2['province'] = trim((string) ($step2['province'] ?? '')) !== '' ? (string) $step2['province'] : san_enrique_province();
$step2['barangay'] = normalize_barangay((string) ($step2['barangay'] ?? ''));

$stepLabels = [1 => 'Program', 2 => 'Personal', 3 => 'Family', 4 => 'Requirements', 5 => '2x2 Photo', 6 => 'Review'];

if ($step === 5) {
    $extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css'];
}

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
            <h2 class="h5 mb-3">Step 1: Scholarship Program Information</h2>
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
                    <label class="form-label">Scholarship Type *</label>
                    <select name="scholarship_type" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach (['Academic Scholarship', 'Financial Assistance', 'Special Grant'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= ($step1['scholarship_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Semester *</label>
                    <select name="semester" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach (['First Semester', 'Second Semester'] as $sem): ?>
                            <option value="<?= e($sem) ?>" <?= ($step1['semester'] ?? '') === $sem ? 'selected' : '' ?>><?= e($sem) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">School Year *</label>
                    <input type="text" name="school_year" class="form-control" placeholder="2026-2027" value="<?= e((string) ($step1['school_year'] ?? '')) ?>" required>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label">School Name *</label>
                    <input type="text" name="school_name" class="form-control" value="<?= e((string) ($step1['school_name'] ?? '')) ?>" required>
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
                    <label class="form-label">Course</label>
                    <input type="text" name="course" class="form-control" value="<?= e((string) ($step1['course'] ?? '')) ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Year Level</label>
                    <input type="text" name="year_level" class="form-control" value="<?= e((string) ($step1['year_level'] ?? '')) ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">GWA</label>
                    <input type="number" step="0.01" name="gwa" class="form-control" value="<?= e((string) ($step1['gwa'] ?? '')) ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Estimated Family Income (Monthly)</label>
                    <input type="number" step="0.01" name="family_income" class="form-control" value="<?= e((string) ($step1['family_income'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Reason for Application</label>
                    <textarea name="reason" rows="3" class="form-control"><?= e((string) ($step1['reason'] ?? '')) ?></textarea>
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

                <div class="col-12 col-md-4">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="last_name" class="form-control" value="<?= e((string) ($step2['last_name'] ?? '')) ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="first_name" class="form-control" value="<?= e((string) ($step2['first_name'] ?? '')) ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" value="<?= e((string) ($step2['middle_name'] ?? '')) ?>">
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
                    <input type="text" name="civil_status" class="form-control" value="<?= e((string) ($step2['civil_status'] ?? '')) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Sex</label>
                    <input type="text" name="sex" class="form-control" value="<?= e((string) ($step2['sex'] ?? '')) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Place of Birth</label>
                    <input type="text" name="birth_place" class="form-control" value="<?= e((string) ($step2['birth_place'] ?? '')) ?>">
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
                    <input type="text" name="contact_number" class="form-control" placeholder="09XXXXXXXXX" value="<?= e((string) ($step2['contact_number'] ?? '')) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">House No. / Street / Purok</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="House No. / Street / Purok"><?= e((string) ($step2['address'] ?? '')) ?></textarea>
                    <div class="form-text">Enter House No. / Street / Purok only.</div>
                </div>

                <div class="col-12 wizard-actions">
                    <a href="apply.php?step=1" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</button>
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

    $defaultEducationRows = [
        ['level' => 'Elementary', 'school' => '', 'year' => '', 'honors' => ''],
        ['level' => 'High School', 'school' => '', 'year' => '', 'honors' => ''],
        ['level' => 'College', 'school' => '', 'year' => '', 'honors' => ''],
        ['level' => 'Course', 'school' => '', 'year' => '', 'honors' => ''],
    ];
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
                <h3 class="h6">Members of the Family (Siblings)</h3>
                <div class="form-check mb-2">
                    <input class="form-check-input js-na-toggle" type="checkbox" id="siblingsNaToggle" name="siblings_na" value="1" data-target="#siblingsFields" <?= $siblingsNa ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="siblingsNaToggle">Not Applicable</label>
                </div>
                <div class="table-responsive" id="siblingsFields">
                    <table class="table table-sm align-middle wizard-stack-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Highest Educational Attainment</th>
                                <th>Occupation</th>
                                <th>Monthly Income</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < 5; $i++): ?>
                                <?php $row = $siblingsPrefill[$i] ?? ['name' => '', 'age' => '', 'education' => '', 'occupation' => '', 'income' => '']; ?>
                                <tr>
                                    <td data-label="Name"><input type="text" class="form-control form-control-sm" name="siblings[<?= $i ?>][name]" value="<?= e((string) ($row['name'] ?? '')) ?>"></td>
                                    <td data-label="Age"><input type="number" class="form-control form-control-sm" name="siblings[<?= $i ?>][age]" value="<?= e((string) ($row['age'] ?? '')) ?>"></td>
                                    <td data-label="Highest Educational Attainment"><input type="text" class="form-control form-control-sm" name="siblings[<?= $i ?>][education]" value="<?= e((string) ($row['education'] ?? '')) ?>"></td>
                                    <td data-label="Occupation"><input type="text" class="form-control form-control-sm" name="siblings[<?= $i ?>][occupation]" value="<?= e((string) ($row['occupation'] ?? '')) ?>"></td>
                                    <td data-label="Monthly Income"><input type="number" step="0.01" class="form-control form-control-sm" name="siblings[<?= $i ?>][income]" value="<?= e((string) ($row['income'] ?? '')) ?>"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-soft shadow-sm mb-3">
            <div class="card-body p-4">
                <h3 class="h6">Educational Background</h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle wizard-stack-table">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>School</th>
                                <th>Year</th>
                                <th>Honors/Awards</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < 4; $i++): ?>
                                <?php $row = $educationPrefill[$i] ?? $defaultEducationRows[$i]; ?>
                                <tr>
                                    <td data-label="Level"><input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][level]" value="<?= e((string) ($row['level'] ?? '')) ?>"></td>
                                    <td data-label="School"><input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][school]" value="<?= e((string) ($row['school'] ?? '')) ?>"></td>
                                    <td data-label="Year"><input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][year]" value="<?= e((string) ($row['year'] ?? '')) ?>"></td>
                                    <td data-label="Honors/Awards"><input type="text" class="form-control form-control-sm" name="education[<?= $i ?>][honors]" value="<?= e((string) ($row['honors'] ?? '')) ?>"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="h6 mt-4">Scholarship Grants Availed</h3>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < 3; $i++): ?>
                                <?php $row = $grantsPrefill[$i] ?? ['program' => '', 'period' => '']; ?>
                                <tr>
                                    <td data-label="Scholarship Program"><input type="text" class="form-control form-control-sm" name="grants[<?= $i ?>][program]" value="<?= e((string) ($row['program'] ?? '')) ?>"></td>
                                    <td data-label="Year/Period"><input type="text" class="form-control form-control-sm" name="grants[<?= $i ?>][period]" value="<?= e((string) ($row['period'] ?? '')) ?>"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="wizard-actions mb-3">
            <a href="apply.php?step=2" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</button>
        </div>
    </form>
<?php endif; ?>

<?php if ($step === 4): ?>
    <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Step 4: Dynamic Requirements Upload</h2>
            <p class="small text-muted">Requirements are based on selected scholarship type, applicant type, and school type.</p>
            <form method="post" enctype="multipart/form-data" class="row g-3">
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
                            <?php if ($existing): ?>
                                <div class="small mt-2 text-muted">Current: <?= e((string) ($existing['original_name'] ?? basename((string) $existing['file_path']))) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="col-12 wizard-actions">
                    <a href="apply.php?step=3" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($step === 5): ?>
    <?php $existingPhotoPath = (string) ($wizard['photo_path'] ?? ''); ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Step 5: 2x2 Photo (Upload or Capture)</h2>
            <p class="small text-muted">Use upload or live camera, then apply a square crop to match the 2x2 print requirement.</p>

            <form method="post" enctype="multipart/form-data" id="step5Form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_step5">
                <input type="hidden" name="photo_base64" id="photoBase64">

                <div class="photo-capture-shell">
                    <div class="photo-step-hints">
                        <span class="photo-step-chip"><i class="fa-solid fa-images me-1"></i>Select Source</span>
                        <span class="photo-step-chip"><i class="fa-solid fa-crop-simple me-1"></i>Crop 1:1</span>
                        <span class="photo-step-chip"><i class="fa-solid fa-circle-check me-1"></i>Apply</span>
                    </div>
                    <div class="photo-mode-switch mb-3" role="group" aria-label="Photo source">
                        <button type="button" class="btn btn-outline-primary active" data-photo-mode="upload" aria-pressed="true">
                            <i class="fa-solid fa-upload me-1"></i>Upload
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-photo-mode="camera" aria-pressed="false">
                            <i class="fa-solid fa-camera me-1"></i>Camera
                        </button>
                    </div>
                    <div class="photo-status photo-status-info mb-3" id="photoStatusWrap">
                        <span class="photo-status-icon" id="photoStatusIcon"><i class="fa-solid fa-circle-info"></i></span>
                        <p class="small mb-0" id="photoStatus">Choose Upload or Camera to begin.</p>
                    </div>
                </div>

                <div class="row g-3 align-items-start">
                    <div class="col-12 col-lg-7">
                        <div id="uploadPanel" class="photo-panel">
                            <label class="form-label">Choose Image</label>
                            <div class="photo-upload-drop">
                                <input type="file" name="photo_upload" id="photoInput" class="form-control" accept="image/*" capture="user">
                            </div>
                            <div class="photo-file-name mt-2" id="photoFileName">No file selected yet.</div>
                            <div class="small text-muted mt-1">Tip: On mobile phones this can open the camera directly.</div>
                        </div>

                        <div id="cameraPanel" class="photo-panel d-none">
                            <label class="form-label">Camera Capture</label>
                            <div class="camera-stage">
                                <video id="cameraVideo" class="d-none" autoplay playsinline muted></video>
                                <canvas id="cameraCanvas" class="d-none"></canvas>
                                <div id="cameraPlaceholder" class="camera-placeholder">
                                    <i class="fa-solid fa-camera-retro me-1"></i>Camera preview appears here after permission
                                </div>
                                <div id="cameraGuides" class="camera-guides d-none" aria-hidden="true"></div>
                            </div>
                            <div class="camera-control-grid mt-2">
                                <button type="button" id="startCameraBtn" class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-video me-1"></i>Start
                                </button>
                                <button type="button" id="captureBtn" class="btn btn-primary btn-sm" disabled>
                                    <i class="fa-solid fa-camera me-1"></i>Capture
                                </button>
                                <button type="button" id="retakeBtn" class="btn btn-outline-warning btn-sm" disabled>
                                    <i class="fa-solid fa-rotate-right me-1"></i>Retake
                                </button>
                                <button type="button" id="stopCameraBtn" class="btn btn-outline-secondary btn-sm" disabled>
                                    <i class="fa-solid fa-video-slash me-1"></i>Stop
                                </button>
                                <button type="button" id="toggleFullscreenBtn" class="btn btn-outline-dark btn-sm">
                                    <i class="fa-solid fa-expand me-1"></i>Full Screen
                                </button>
                            </div>
                            <div class="small text-muted mt-2">Best result: face the camera in good lighting, keep your head centered, and use Full Screen on phones.</div>
                        </div>

                        <div class="photo-source-shell mt-3 d-none" id="photoSourceShell">
                            <div class="photo-source-header mb-2">
                                <h3 class="h6 mb-0">Crop Photo</h3>
                                <span class="badge text-bg-light border"><i class="fa-solid fa-square me-1"></i>1:1 Ratio</span>
                            </div>
                            <img id="photoSource" src="" alt="Source" class="img-fluid d-none photo-source">
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" id="cropBtn" class="btn btn-outline-primary d-none wizard-mobile-full">
                                <i class="fa-solid fa-crop-simple me-1"></i>Use Cropped Photo
                            </button>
                            <button type="button" id="clearSourceBtn" class="btn btn-outline-danger d-none wizard-mobile-full">
                                <i class="fa-solid fa-trash-can me-1"></i>Clear Selection
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="photo-preview-card">
                            <h3 class="h6">2x2 Preview</h3>
                            <div class="photo-frame mb-2" id="photoPreviewFrame">
                                <?php if ($existingPhotoPath): ?>
                                    <img src="<?= e($existingPhotoPath) ?>" alt="2x2 Photo" id="existingPhoto">
                                <?php else: ?>
                                    <span class="text-muted small">No photo yet</span>
                                <?php endif; ?>
                            </div>
                            <p class="photo-preview-meta mb-0">Print target: exactly 2 x 2 inches (5.08 x 5.08 cm).</p>
                        </div>
                    </div>
                </div>

                <div class="wizard-actions mt-4">
                    <a href="apply.php?step=4" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('step5Form');
            const input = document.getElementById('photoInput');
            const source = document.getElementById('photoSource');
            const previewFrame = document.getElementById('photoPreviewFrame');
            const cropBtn = document.getElementById('cropBtn');
            const clearSourceBtn = document.getElementById('clearSourceBtn');
            const photoBase64 = document.getElementById('photoBase64');
            const photoStatusWrap = document.getElementById('photoStatusWrap');
            const photoStatusIcon = document.getElementById('photoStatusIcon');
            const photoStatus = document.getElementById('photoStatus');
            const photoFileName = document.getElementById('photoFileName');
            const uploadPanel = document.getElementById('uploadPanel');
            const cameraPanel = document.getElementById('cameraPanel');
            const photoSourceShell = document.getElementById('photoSourceShell');
            const modeButtons = document.querySelectorAll('[data-photo-mode]');
            const cameraVideo = document.getElementById('cameraVideo');
            const cameraCanvas = document.getElementById('cameraCanvas');
            const cameraPlaceholder = document.getElementById('cameraPlaceholder');
            const cameraGuides = document.getElementById('cameraGuides');
            const startCameraBtn = document.getElementById('startCameraBtn');
            const captureBtn = document.getElementById('captureBtn');
            const retakeBtn = document.getElementById('retakeBtn');
            const stopCameraBtn = document.getElementById('stopCameraBtn');
            const toggleFullscreenBtn = document.getElementById('toggleFullscreenBtn');

            let cropper = null;
            let stream = null;
            let fallbackFullscreenActive = false;
            let currentMode = 'upload';
            const initialPreview = previewFrame.innerHTML;
            const hasInitialPhoto = previewFrame.querySelector('img') !== null;

            function setStatus(message, tone) {
                const safeTone = ['info', 'success', 'error'].includes(tone) ? tone : 'info';
                const iconMap = {
                    info: 'fa-circle-info',
                    success: 'fa-circle-check',
                    error: 'fa-circle-exclamation'
                };
                if (photoStatusWrap) {
                    photoStatusWrap.classList.remove('photo-status-info', 'photo-status-success', 'photo-status-error');
                    photoStatusWrap.classList.add('photo-status-' + safeTone);
                }
                if (photoStatusIcon) {
                    photoStatusIcon.innerHTML = '<i class="fa-solid ' + iconMap[safeTone] + '"></i>';
                }
                if (!photoStatus) {
                    return;
                }
                photoStatus.textContent = message;
            }

            function showElement(element) {
                if (element) {
                    element.classList.remove('d-none');
                }
            }

            function hideElement(element) {
                if (element) {
                    element.classList.add('d-none');
                }
            }

            function updateFileName(file) {
                if (!photoFileName) {
                    return;
                }
                if (file) {
                    photoFileName.textContent = 'Selected: ' + file.name;
                    return;
                }
                photoFileName.textContent = 'No file selected yet.';
            }

            function getNativeFullscreenElement() {
                return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
            }

            function requestNativeFullscreen(element) {
                if (!element) {
                    return Promise.reject(new Error('Missing fullscreen target element.'));
                }
                if (element.requestFullscreen) {
                    return element.requestFullscreen();
                }
                if (element.webkitRequestFullscreen) {
                    element.webkitRequestFullscreen();
                    return Promise.resolve();
                }
                if (element.msRequestFullscreen) {
                    element.msRequestFullscreen();
                    return Promise.resolve();
                }
                return Promise.reject(new Error('Fullscreen API is not supported.'));
            }

            function exitNativeFullscreen() {
                if (document.exitFullscreen) {
                    return document.exitFullscreen();
                }
                if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                    return Promise.resolve();
                }
                if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                    return Promise.resolve();
                }
                return Promise.resolve();
            }

            function setFallbackFullscreen(active) {
                fallbackFullscreenActive = !!active;
                if (cameraPanel) {
                    cameraPanel.classList.toggle('photo-panel-overlay', fallbackFullscreenActive);
                }
                document.body.classList.toggle('photo-camera-overlay-open', fallbackFullscreenActive);
            }

            function isCameraFullscreenActive() {
                return !!getNativeFullscreenElement() || fallbackFullscreenActive;
            }

            function syncFullscreenButton() {
                if (!toggleFullscreenBtn) {
                    return;
                }
                if (currentMode !== 'camera') {
                    toggleFullscreenBtn.innerHTML = '<i class="fa-solid fa-expand me-1"></i>Full Screen';
                    toggleFullscreenBtn.classList.remove('btn-warning');
                    toggleFullscreenBtn.classList.add('btn-outline-dark');
                    return;
                }

                if (isCameraFullscreenActive()) {
                    toggleFullscreenBtn.innerHTML = '<i class="fa-solid fa-compress me-1"></i>Exit Full Screen';
                    toggleFullscreenBtn.classList.remove('btn-outline-dark');
                    toggleFullscreenBtn.classList.add('btn-warning');
                    return;
                }

                toggleFullscreenBtn.innerHTML = '<i class="fa-solid fa-expand me-1"></i>Full Screen';
                toggleFullscreenBtn.classList.remove('btn-warning');
                toggleFullscreenBtn.classList.add('btn-outline-dark');
            }

            async function enterCameraFullscreen() {
                if (!cameraPanel) {
                    return;
                }

                if (currentMode !== 'camera') {
                    setMode('camera', true);
                }

                setStatus('Opening full-screen camera...', 'info');
                setFallbackFullscreen(false);

                try {
                    await requestNativeFullscreen(cameraPanel);
                } catch (error) {
                    // Fallback for browsers/devices where element fullscreen is restricted.
                    setFallbackFullscreen(true);
                }

                syncFullscreenButton();
                setStatus('Full-screen camera enabled.', 'success');
            }

            async function exitCameraFullscreen(silent) {
                const wasActive = isCameraFullscreenActive();

                if (fallbackFullscreenActive) {
                    setFallbackFullscreen(false);
                }

                if (getNativeFullscreenElement()) {
                    try {
                        await exitNativeFullscreen();
                    } catch (error) {
                        // Continue cleanup even when fullscreen exit fails.
                    }
                }

                syncFullscreenButton();
                if (!silent && wasActive) {
                    setStatus('Exited full-screen camera.', 'info');
                }
            }

            function onNativeFullscreenChange() {
                if (!getNativeFullscreenElement()) {
                    setFallbackFullscreen(false);
                }
                syncFullscreenButton();
            }

            function cleanupCropper() {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                source.removeAttribute('src');
                hideElement(source);
                hideElement(photoSourceShell);
                hideElement(cropBtn);
                hideElement(clearSourceBtn);
            }

            function stopCamera(silent) {
                if (stream) {
                    stream.getTracks().forEach(function (track) {
                        track.stop();
                    });
                    stream = null;
                }
                if (cameraVideo) {
                    cameraVideo.pause();
                    cameraVideo.srcObject = null;
                }
                showElement(cameraPlaceholder);
                hideElement(cameraVideo);
                hideElement(cameraCanvas);
                hideElement(cameraGuides);
                if (captureBtn) {
                    captureBtn.disabled = true;
                }
                if (stopCameraBtn) {
                    stopCameraBtn.disabled = true;
                }
                if (startCameraBtn) {
                    startCameraBtn.disabled = false;
                }
                if (!silent && currentMode === 'camera') {
                    setStatus('Camera stopped. You can start it again anytime.', 'info');
                }
            }

            async function startCamera() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    setStatus('Camera is not supported on this device/browser.', 'error');
                    return;
                }

                if (startCameraBtn) {
                    startCameraBtn.disabled = true;
                }
                stopCamera(true);
                setStatus('Requesting camera permission...', 'info');
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'user',
                            width: { ideal: 1080 },
                            height: { ideal: 1080 }
                        },
                        audio: false
                    });
                    cameraVideo.srcObject = stream;
                    await cameraVideo.play();
                    showElement(cameraVideo);
                    hideElement(cameraPlaceholder);
                    showElement(cameraGuides);
                    if (captureBtn) {
                        captureBtn.disabled = false;
                    }
                    if (stopCameraBtn) {
                        stopCameraBtn.disabled = false;
                    }
                    if (retakeBtn) {
                        retakeBtn.disabled = true;
                    }
                    setStatus('Camera ready. Keep your face centered and tap Capture.', 'success');
                } catch (error) {
                    setStatus('Unable to access camera. Allow permission or switch to Upload.', 'error');
                } finally {
                    if (startCameraBtn) {
                        startCameraBtn.disabled = false;
                    }
                }
            }

            function buildCropper(dataUrl) {
                cleanupCropper();
                source.src = dataUrl;
                showElement(photoSourceShell);
                showElement(source);
                showElement(cropBtn);
                showElement(clearSourceBtn);
                cropper = new Cropper(source, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 1,
                    responsive: true,
                    dragMode: 'move',
                    background: false
                });
                setStatus('Adjust the square crop and tap "Use Cropped Photo".', 'info');
            }

            function setMode(mode, keepStatus) {
                const safeMode = mode === 'camera' ? 'camera' : 'upload';
                currentMode = safeMode;
                modeButtons.forEach(function (btn) {
                    const btnMode = btn.getAttribute('data-photo-mode');
                    const isActive = btnMode === safeMode;
                    btn.classList.toggle('active', isActive);
                    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
                if (safeMode === 'camera') {
                    hideElement(uploadPanel);
                    showElement(cameraPanel);
                    startCamera();
                    syncFullscreenButton();
                } else {
                    exitCameraFullscreen(true);
                    showElement(uploadPanel);
                    hideElement(cameraPanel);
                    stopCamera(true);
                    if (retakeBtn) {
                        retakeBtn.disabled = true;
                    }
                    syncFullscreenButton();
                    if (!keepStatus) {
                        setStatus('Upload a clear photo file, then crop and apply it.', 'info');
                    }
                }
            }

            function restoreInitialPreview() {
                previewFrame.innerHTML = initialPreview;
            }

            modeButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const mode = btn.getAttribute('data-photo-mode') || 'upload';
                    setMode(mode);
                });
            });

            input.addEventListener('change', function (event) {
                const file = event.target.files && event.target.files[0];
                updateFileName(file || null);
                if (!file) {
                    return;
                }
                photoBase64.value = '';

                const reader = new FileReader();
                reader.onload = function (e) {
                    buildCropper(e.target.result);
                };
                reader.readAsDataURL(file);
            });

            if (startCameraBtn) {
                startCameraBtn.addEventListener('click', function () {
                    if (currentMode !== 'camera') {
                        setMode('camera');
                        return;
                    }
                    startCamera();
                });
            }

            if (stopCameraBtn) {
                stopCameraBtn.addEventListener('click', function () {
                    stopCamera(false);
                });
            }

            if (toggleFullscreenBtn) {
                toggleFullscreenBtn.addEventListener('click', async function () {
                    if (currentMode !== 'camera') {
                        setMode('camera', true);
                    }
                    if (isCameraFullscreenActive()) {
                        await exitCameraFullscreen(false);
                        return;
                    }
                    await enterCameraFullscreen();
                });
            }

            if (captureBtn) {
                captureBtn.addEventListener('click', function () {
                    if (!stream || !cameraVideo.videoWidth || !cameraVideo.videoHeight) {
                        setStatus('Camera feed is not ready yet.', 'error');
                        return;
                    }

                    const width = cameraVideo.videoWidth;
                    const height = cameraVideo.videoHeight;
                    cameraCanvas.width = width;
                    cameraCanvas.height = height;
                    const ctx = cameraCanvas.getContext('2d');
                    if (!ctx) {
                        setStatus('Failed to process camera image. Try again.', 'error');
                        return;
                    }
                    ctx.drawImage(cameraVideo, 0, 0, width, height);
                    const data = cameraCanvas.toDataURL('image/jpeg', 0.95);
                    buildCropper(data);
                    stopCamera(true);
                    if (retakeBtn) {
                        retakeBtn.disabled = false;
                    }
                    setStatus('Captured. Fine-tune the crop and apply.', 'success');
                });
            }

            if (retakeBtn) {
                retakeBtn.addEventListener('click', function () {
                    cleanupCropper();
                    photoBase64.value = '';
                    setMode('camera', true);
                    setStatus('Ready for retake. Keep your face inside the guide.', 'info');
                });
            }

            cropBtn.addEventListener('click', function () {
                if (!cropper) {
                    setStatus('Please select or capture a photo first.', 'error');
                    return;
                }
                const canvas = cropper.getCroppedCanvas({ width: 512, height: 512 });
                if (!canvas) {
                    setStatus('Unable to crop this image. Please try another photo.', 'error');
                    return;
                }
                const data = canvas.toDataURL('image/jpeg', 0.92);
                photoBase64.value = data;
                previewFrame.innerHTML = '<img src="' + data + '" alt="2x2 Preview">';
                setStatus('2x2 photo applied successfully. You can continue to the next step.', 'success');
            });

            if (clearSourceBtn) {
                clearSourceBtn.addEventListener('click', function () {
                    cleanupCropper();
                    photoBase64.value = '';
                    if (input) {
                        input.value = '';
                    }
                    updateFileName(null);
                    restoreInitialPreview();
                    if (currentMode === 'camera') {
                        startCamera();
                        setStatus('Selection cleared. Capture a new photo when ready.', 'info');
                        return;
                    }
                    setStatus('Selection cleared. Upload or capture a new photo.', 'info');
                });
            }

            if (form) {
                form.addEventListener('submit', function (event) {
                    const hasAppliedCrop = photoBase64.value.trim() !== '';
                    const hasUpload = !!(input && input.files && input.files.length > 0);
                    const hasPreviewImage = previewFrame.querySelector('img') !== null;
                    const hasPendingSource = !source.classList.contains('d-none');

                    if (hasPendingSource && !hasAppliedCrop) {
                        event.preventDefault();
                        setStatus('Tap "Use Cropped Photo" before continuing.', 'error');
                        return;
                    }

                    if (!hasAppliedCrop && !hasUpload && !hasPreviewImage) {
                        event.preventDefault();
                        setStatus('Please upload or capture a photo first.', 'error');
                    }
                });
            }

            window.addEventListener('beforeunload', function () {
                exitCameraFullscreen(true);
                stopCamera(true);
            });

            document.addEventListener('fullscreenchange', onNativeFullscreenChange);
            document.addEventListener('webkitfullscreenchange', onNativeFullscreenChange);
            document.addEventListener('msfullscreenchange', onNativeFullscreenChange);
            document.addEventListener('MSFullscreenChange', onNativeFullscreenChange);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && fallbackFullscreenActive) {
                    exitCameraFullscreen(true);
                }
            });

            updateFileName(null);
            setMode('upload', true);
            if (hasInitialPhoto) {
                setStatus('Existing 2x2 photo detected. You can keep it or replace it.', 'info');
            } else {
                setStatus('Choose Upload or Camera to begin your 2x2 photo.', 'info');
            }
        });
    </script>
<?php endif; ?>

<?php if ($step === 6): ?>
    <div class="card card-soft shadow-sm wizard-review">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Step 6: Review and Preview Before Submit</h2>
            <p class="small text-muted mb-3">Review your details carefully before final submission. You can still go back and edit any step.</p>

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
                                    <span class="review-kv-label">Scholarship</span>
                                    <span class="review-kv-value"><?= e((string) ($step1['scholarship_type'] ?? '')) ?></span>
                                </div>
                                <div class="review-kv-row">
                                    <span class="review-kv-label">Semester / School Year</span>
                                    <span class="review-kv-value"><?= e((string) ($step1['semester'] ?? '')) ?> / <?= e((string) ($step1['school_year'] ?? '')) ?></span>
                                </div>
                                <div class="review-kv-row">
                                    <span class="review-kv-label">School</span>
                                    <span class="review-kv-value"><?= e((string) ($step1['school_name'] ?? '')) ?> (<?= e((string) ($step1['school_type'] ?? '')) ?>)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="card card-soft h-100">
                        <div class="card-body">
                            <h3 class="h6">2x2 Photo <a class="small ms-2" href="apply.php?step=5">Edit</a></h3>
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
                        <p class="mb-1"><strong>Applicant:</strong> <?= e((string) ($step2['last_name'] ?? '')) ?>, <?= e((string) ($step2['first_name'] ?? '')) ?> <?= e((string) ($step2['middle_name'] ?? '')) ?></p>
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
                            <li><?= e((string) ($doc['requirement_name'] ?? 'Requirement')) ?> - <span class="text-muted"><?= e((string) ($doc['original_name'] ?? basename((string) ($doc['file_path'] ?? '')))) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <form method="post" class="row g-3 wizard-review-submit">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="final_submit">
                <div class="col-12 col-md-6">
                    <label class="form-label">Account Password *</label>
                    <div class="input-group">
                        <input type="password" name="account_password" id="applyAccountPassword" class="form-control" required>
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#applyAccountPassword" aria-label="Show password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Confirm Password *</label>
                    <div class="input-group">
                        <input type="password" name="confirm_account_password" id="applyConfirmAccountPassword" class="form-control" required>
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle data-target="#applyConfirmAccountPassword" aria-label="Show password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12 wizard-actions">
                    <a href="apply.php?step=5" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Previous</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane me-1"></i>Submit Final Application
                    </button>
                </div>
            </form>
            <p class="small text-muted mt-3 mb-0 review-note">After submitting, you can print/download the exact legal-size application form with QR code.</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($step === 2): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const birthDateInput = document.getElementById('birthDateInput');
            const ageInput = document.getElementById('ageInput');
            if (!birthDateInput || !ageInput) {
                return;
            }

            function calculateAge(value) {
                if (!value) {
                    return '';
                }
                const birthDate = new Date(value + 'T00:00:00');
                if (Number.isNaN(birthDate.getTime())) {
                    return '';
                }

                const today = new Date();
                const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                if (birthDate > todayDate) {
                    return '';
                }

                let age = todayDate.getFullYear() - birthDate.getFullYear();
                const monthDiff = todayDate.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && todayDate.getDate() < birthDate.getDate())) {
                    age -= 1;
                }

                return age >= 0 ? String(age) : '';
            }

            function syncAge() {
                ageInput.value = calculateAge(birthDateInput.value);
            }

            birthDateInput.addEventListener('change', syncAge);
            birthDateInput.addEventListener('input', syncAge);
            syncAge();
        });
    </script>
<?php endif; ?>

<?php if ($step === 3): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggles = document.querySelectorAll('.js-na-toggle[data-target]');
            if (!toggles.length) {
                return;
            }

            function applyNaState(toggle) {
                const targetSelector = toggle.getAttribute('data-target') || '';
                if (!targetSelector) {
                    return;
                }
                const target = document.querySelector(targetSelector);
                if (!target) {
                    return;
                }

                const disabled = !!toggle.checked;
                const fields = target.querySelectorAll('input, select, textarea');
                fields.forEach(function (field) {
                    if (!(field instanceof HTMLElement)) {
                        return;
                    }
                    if (disabled) {
                        if (field instanceof HTMLInputElement) {
                            if (field.type === 'checkbox' || field.type === 'radio') {
                                field.checked = false;
                            } else {
                                field.value = '';
                            }
                        } else if (field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                            field.value = '';
                        }
                        field.setAttribute('disabled', 'disabled');
                    } else {
                        field.removeAttribute('disabled');
                    }
                });

                target.style.opacity = disabled ? '0.65' : '1';
            }

            toggles.forEach(function (toggle) {
                if (!(toggle instanceof HTMLInputElement)) {
                    return;
                }
                toggle.addEventListener('change', function () {
                    applyNaState(toggle);
                });
                applyNaState(toggle);
            });
        });
    </script>
<?php endif; ?>

<?php if (in_array($step, [1, 2, 3], true)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form[data-autosave-step="<?= (int) $step ?>"]');
            const statusEl = document.getElementById('autosaveStatus');
            if (!form) {
                return;
            }

            const step = parseInt(form.getAttribute('data-autosave-step') || '0', 10);
            const csrfInput = form.querySelector('input[name="csrf_token"]');
            if (!csrfInput || !step) {
                return;
            }

            const endpoint = 'apply-autosave.php';
            let lastSnapshot = '';
            let timer = null;
            let saving = false;
            let queued = false;

            function setStatus(text, className) {
                if (!statusEl) {
                    return;
                }
                statusEl.className = 'small mb-3 ' + className;
                statusEl.textContent = text;
            }

            function buildSnapshot() {
                const formData = new FormData(form);
                formData.delete('csrf_token');
                formData.delete('action');

                const params = new URLSearchParams();
                for (const entry of formData.entries()) {
                    const key = entry[0];
                    const value = entry[1];
                    if (value instanceof File) {
                        continue;
                    }
                    params.append(key, String(value));
                }
                return params.toString();
            }

            function buildPayload() {
                const formData = new FormData(form);
                formData.set('csrf_token', csrfInput.value);
                formData.set('action', 'autosave_step');
                formData.set('step', String(step));
                return formData;
            }

            async function saveDraft(force) {
                const snapshot = buildSnapshot();
                if (!force && snapshot === lastSnapshot) {
                    return;
                }
                if (saving) {
                    queued = true;
                    return;
                }

                saving = true;
                setStatus('Auto-saving draft...', 'text-muted');

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: buildPayload(),
                    });
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        setStatus('Auto-save failed. Keep this page open and try again.', 'text-danger');
                    } else {
                        lastSnapshot = snapshot;
                        setStatus('Draft saved at ' + (data.saved_time || 'just now') + '.', 'text-success');
                    }
                } catch (error) {
                    setStatus('Auto-save failed. Check your connection.', 'text-danger');
                } finally {
                    saving = false;
                    if (queued) {
                        queued = false;
                        saveDraft(false);
                    }
                }
            }

            function scheduleSave() {
                if (timer) {
                    window.clearTimeout(timer);
                }
                timer = window.setTimeout(function () {
                    saveDraft(false);
                }, 1200);
            }

            lastSnapshot = buildSnapshot();
            setStatus('Auto-save is enabled for this step.', 'text-muted');

            form.addEventListener('input', scheduleSave);
            form.addEventListener('change', scheduleSave);
            form.addEventListener('submit', function () {
                if (timer) {
                    window.clearTimeout(timer);
                }
            });

            window.setInterval(function () {
                saveDraft(false);
            }, 10000);

            window.addEventListener('beforeunload', function () {
                const snapshot = buildSnapshot();
                if (snapshot === lastSnapshot) {
                    return;
                }
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(endpoint, buildPayload());
                }
            });
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

