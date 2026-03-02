<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$respond = static function (array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if (!is_post()) {
    $respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!is_logged_in() || !user_has_role(['applicant'])) {
    $respond(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    $respond(['ok' => false, 'message' => 'Invalid request token.'], 419);
}

if (!db_ready()) {
    $respond(['ok' => false, 'message' => 'The system is not ready yet.'], 503);
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    $respond(['ok' => false, 'message' => 'Invalid user.'], 401);
}

$step = (int) ($_POST['step'] ?? 0);
if (!in_array($step, [1, 2, 3], true)) {
    $respond(['ok' => false, 'message' => 'Invalid step.'], 422);
}

$openPeriod = current_open_application_period($conn);
if (!$openPeriod) {
    $respond(['ok' => false, 'message' => 'Application period is closed.'], 409);
}
if (applicant_has_application_in_period($conn, $userId, $openPeriod)) {
    $respond(['ok' => false, 'message' => 'You already submitted an application in this period.'], 409);
}

$wizard = wizard_state();

if ($step === 1) {
    $wizard['step1'] = [
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
}

if ($step === 2) {
    $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
    if ($contactNumber !== '' && is_valid_mobile_number($contactNumber)) {
        $contactNumber = normalize_mobile_number($contactNumber);
    }
    $birthDate = trim((string) ($_POST['birth_date'] ?? ''));
    $computedAge = calculate_age_from_birth_date($birthDate);
    $barangay = normalize_barangay((string) ($_POST['barangay'] ?? ''));

    $wizard['step2'] = [
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
        'contact_number' => $contactNumber,
    ];
}

if ($step === 3) {
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
}

wizard_save($wizard);
wizard_save_persistent_draft($conn, $userId, $wizard, $step);

$respond([
    'ok' => true,
    'saved_step' => $step,
    'saved_time' => date('h:i A'),
]);
