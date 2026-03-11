<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function user_has_role(array $roles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return in_array($user['role'], $roles, true);
}

function is_admin(): bool
{
    return user_has_role(['admin']);
}

function is_staff(): bool
{
    return user_has_role(['staff']);
}

function require_login(string $redirectTo = 'login.php'): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please login first.');
        redirect($redirectTo);
    }
}

function require_role(array $roles, string $redirectTo): void
{
    if (!user_has_role($roles)) {
        set_flash('danger', 'You are not allowed to access that page.');
        redirect($redirectTo);
    }
}

function require_admin(string $redirectTo): void
{
    require_role(['admin'], $redirectTo);
}

function humanize_flash_message(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return 'Please try again.';
    }

    $exactMap = [
        'Invalid request token.' => 'Your session expired. Please try again.',
        'Invalid request. Please try again.' => 'Your session expired. Please try again.',
        'Database is not connected yet.' => 'The system is not ready yet. Please try again shortly.',
        'Database is not connected yet. Import the SQL setup first.' => 'The system setup is not complete yet. Please contact the administrator.',
        'Application period setup is missing.' => 'Application period settings are not ready yet. Please contact the administrator.',
        'Application periods table is missing.' => 'Application period settings are not ready yet. Please contact the administrator.',
        'Notifications table is not available yet.' => 'Notifications are not available yet. Please contact the administrator.',
        'Invalid OTP payload. Please request a new code.' => 'The verification code request is no longer valid. Please request a new code.',
        'Invalid OTP session. Please request a new code.' => 'Your verification code has expired. Please request a new code.',
    ];

    if (isset($exactMap[$message])) {
        return $exactMap[$message];
    }

    if (preg_match('/^Submission failed:/i', $message) === 1) {
        if (user_has_role(['admin', 'staff'])) {
            return $message;
        }
        return 'We could not submit your application right now. Please try again.';
    }

    if (preg_match('/^Photo upload failed:/i', $message) === 1) {
        return 'We could not upload your photo. Please try another clear photo.';
    }

    if (preg_match('/\b(TextBee|Textbee|SMS provider)\b.*\bDev\b.*\b(OTP|Verification Code)\b/i', $message) === 1) {
        if (preg_match('/(\d{4,8})\s*$/', $message, $matches) === 1) {
            return 'SMS is not available right now. Use this verification code: ' . $matches[1];
        }
        return 'SMS is not available right now. Please try again shortly.';
    }

    return $message;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = humanize_flash_message($message);
}

function get_flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function old(string $key, string $default = ''): string
{
    if (!isset($_POST[$key])) {
        return $default;
    }

    return trim((string) $_POST[$key]);
}

function normalize_mobile_number(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '';
    }

    if (function_exists('normalize_phone_number')) {
        return normalize_phone_number($phone);
    }

    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        return '63' . substr($digits, 1);
    }
    if (str_starts_with($digits, '63') && strlen($digits) === 12) {
        return $digits;
    }
    return $digits;
}

function mobile_number_variants(string $phone): array
{
    $normalized = normalize_mobile_number($phone);
    if ($normalized === '') {
        return [];
    }

    $variants = [$normalized];
    if (str_starts_with($normalized, '63') && strlen($normalized) === 12) {
        $variants[] = '0' . substr($normalized, 2);
    } elseif (str_starts_with($normalized, '0') && strlen($normalized) === 11) {
        $variants[] = '63' . substr($normalized, 1);
    }

    $variants[] = trim($phone);
    $variants = array_values(array_unique(array_filter($variants, static fn($v) => trim((string) $v) !== '')));
    return array_slice($variants, 0, 3);
}

function is_valid_mobile_number(string $phone): bool
{
    $normalized = normalize_mobile_number($phone);
    return preg_match('/^63\d{10}$/', $normalized) === 1;
}

function mask_mobile_number(string $phone): string
{
    $normalized = normalize_mobile_number($phone);
    if ($normalized === '') {
        return '';
    }
    if (strlen($normalized) < 7) {
        return $normalized;
    }
    return substr($normalized, 0, 4) . str_repeat('*', 5) . substr($normalized, -3);
}

function find_user_by_mobile(mysqli $conn, string $phone): ?array
{
    $variants = mobile_number_variants($phone);
    if (!$variants) {
        return null;
    }

    $v1 = $variants[0] ?? '';
    $v2 = $variants[1] ?? $v1;
    $v3 = $variants[2] ?? $v1;
    $stmt = $conn->prepare(
        "SELECT id, role, first_name, middle_name, last_name, email, phone, password_hash, status
         FROM users
         WHERE phone IN (?, ?, ?)
         ORDER BY id ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('sss', $v1, $v2, $v3);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? ($result->fetch_assoc() ?: null) : null;
    $stmt->close();
    return $user;
}

function mobile_number_exists(mysqli $conn, string $phone, int $excludeUserId = 0): bool
{
    $variants = mobile_number_variants($phone);
    if (!$variants) {
        return false;
    }

    $v1 = $variants[0] ?? '';
    $v2 = $variants[1] ?? $v1;
    $v3 = $variants[2] ?? $v1;

    if ($excludeUserId > 0) {
        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE phone IN (?, ?, ?)
               AND id <> ?
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssi', $v1, $v2, $v3, $excludeUserId);
    } else {
        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE phone IN (?, ?, ?)
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sss', $v1, $v2, $v3);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
    $stmt->close();
    return $exists;
}

function is_duplicate_key_error(mysqli $conn): bool
{
    return (int) ($conn->errno ?? 0) === 1062;
}

function current_open_application_period(mysqli $conn): ?array
{
    if (!table_exists($conn, 'application_periods')) {
        return null;
    }

    $hasAcademicYear = table_column_exists($conn, 'application_periods', 'academic_year');
    $hasSemester = table_column_exists($conn, 'application_periods', 'semester');

    $academicYearSelect = $hasAcademicYear ? ', academic_year' : '';
    $semesterSelect = $hasSemester ? ', semester' : '';
    $today = date('Y-m-d');
    $stmt = $conn->prepare(
        "SELECT id, period_name, start_date, end_date, is_open, notes" . $academicYearSelect . $semesterSelect . "
         FROM application_periods
         WHERE is_open = 1
           AND (start_date IS NULL OR start_date <= ?)
           AND (end_date IS NULL OR end_date >= ?)
         ORDER BY id DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ss', $today, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? ($result->fetch_assoc() ?: null) : null;
    $stmt->close();
    return $row;
}

function is_application_period_open(mysqli $conn): bool
{
    return current_open_application_period($conn) !== null;
}

function format_application_period(?array $period): string
{
    if (!$period) {
        return 'No open application period';
    }

    $academicYear = trim((string) ($period['academic_year'] ?? ''));
    $semester = trim((string) ($period['semester'] ?? ''));
    $name = $semester !== '' && $academicYear !== ''
        ? ($semester . ' ' . $academicYear)
        : trim((string) ($period['period_name'] ?? 'Application Period'));
    $start = trim((string) ($period['start_date'] ?? ''));
    $end = trim((string) ($period['end_date'] ?? ''));

    if ($start !== '' && $end !== '') {
        return $name . ' (' . date('M d, Y', strtotime($start)) . ' - ' . date('M d, Y', strtotime($end)) . ')';
    }
    if ($start !== '') {
        return $name . ' (from ' . date('M d, Y', strtotime($start)) . ')';
    }
    if ($end !== '') {
        return $name . ' (until ' . date('M d, Y', strtotime($end)) . ')';
    }

    return $name;
}

function normalize_period_scope(string $scope, string $default = 'active'): string
{
    $scope = strtolower(trim($scope));
    $allowed = ['active', 'archived', 'all'];
    if (in_array($scope, $allowed, true)) {
        return $scope;
    }
    return in_array($default, $allowed, true) ? $default : 'active';
}

function application_is_in_active_period(
    array $application,
    ?array $activePeriod,
    bool $hasApplicationPeriodColumn = true
): bool {
    if (!$activePeriod) {
        return false;
    }

    $activePeriodId = (int) ($activePeriod['id'] ?? 0);
    if ($hasApplicationPeriodColumn && $activePeriodId > 0) {
        $applicationPeriodId = (int) ($application['application_period_id'] ?? 0);
        return $applicationPeriodId > 0 && $applicationPeriodId === $activePeriodId;
    }

    $activeSemester = trim((string) ($activePeriod['semester'] ?? ''));
    $activeSchoolYear = trim((string) ($activePeriod['academic_year'] ?? ''));
    if ($activeSemester !== '' && $activeSchoolYear !== '') {
        return trim((string) ($application['semester'] ?? '')) === $activeSemester
            && trim((string) ($application['school_year'] ?? '')) === $activeSchoolYear;
    }

    return false;
}

function application_is_archived_for_active_period(
    array $application,
    ?array $activePeriod,
    bool $hasApplicationPeriodColumn = true
): bool {
    if (!$activePeriod) {
        return true;
    }

    return !application_is_in_active_period($application, $activePeriod, $hasApplicationPeriodColumn);
}

function application_matches_period_scope(
    array $application,
    string $scope,
    ?array $activePeriod,
    bool $hasApplicationPeriodColumn = true
): bool {
    $scope = normalize_period_scope($scope, 'active');
    if ($scope === 'all') {
        return true;
    }
    if ($scope === 'archived') {
        return application_is_archived_for_active_period($application, $activePeriod, $hasApplicationPeriodColumn);
    }
    return application_is_in_active_period($application, $activePeriod, $hasApplicationPeriodColumn);
}

function table_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    static $cache = [];

    $tableName = trim($tableName);
    $columnName = trim($columnName);
    if ($tableName === '' || $columnName === '') {
        return false;
    }

    $cacheKey = strtolower($tableName . '.' . $columnName);
    if (array_key_exists($cacheKey, $cache)) {
        return (bool) $cache[$cacheKey];
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        $cache[$cacheKey] = false;
        return false;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    $exists = (int) ($row['total'] ?? 0) > 0;
    $cache[$cacheKey] = $exists;
    return $exists;
}

function applicant_has_application_in_period(mysqli $conn, int $userId, ?array $period = null): bool
{
    if ($userId <= 0 || !table_exists($conn, 'applications')) {
        return false;
    }

    if ($period === null) {
        $period = current_open_application_period($conn);
    }
    if (!$period) {
        return false;
    }

    $periodId = (int) ($period['id'] ?? 0);
    if ($periodId > 0 && table_column_exists($conn, 'applications', 'application_period_id')) {
        $stmt = $conn->prepare(
            "SELECT id
             FROM applications
             WHERE user_id = ?
               AND application_period_id = ?
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('ii', $userId, $periodId);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
            $stmt->close();
            if ($exists) {
                return true;
            }
        }
    }

    $startDate = trim((string) ($period['start_date'] ?? ''));
    $endDate = trim((string) ($period['end_date'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) !== 1) {
        $startDate = '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) !== 1) {
        $endDate = '';
    }

    $stmt = $conn->prepare(
        "SELECT id
         FROM applications
         WHERE user_id = ?
           AND (? = '' OR DATE(COALESCE(submitted_at, created_at)) >= ?)
           AND (? = '' OR DATE(COALESCE(submitted_at, created_at)) <= ?)
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('issss', $userId, $startDate, $startDate, $endDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && (bool) $result->fetch_assoc();
    $stmt->close();

    return $exists;
}

function otp_session_key(string $flow): string
{
    $flow = strtolower(trim($flow));
    $flow = preg_replace('/[^a-z0-9_]+/', '_', $flow) ?? 'generic';
    $flow = trim($flow, '_');
    if ($flow === '') {
        $flow = 'generic';
    }
    return 'otp_' . $flow;
}

function generate_otp_code(int $length = 6): string
{
    $length = max(4, min(8, $length));
    $max = (10 ** $length) - 1;
    return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
}

function otp_start(string $flow, array $payload = [], int $ttlSeconds = 300, int $maxAttempts = 5): string
{
    $code = generate_otp_code();
    $_SESSION[otp_session_key($flow)] = [
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => time() + max(60, $ttlSeconds),
        'attempts' => 0,
        'max_attempts' => max(1, $maxAttempts),
        'payload' => $payload,
        'created_at' => time(),
    ];
    return $code;
}

function otp_state(string $flow): ?array
{
    $key = otp_session_key($flow);
    $state = $_SESSION[$key] ?? null;
    if (!is_array($state)) {
        return null;
    }

    if ((int) ($state['expires_at'] ?? 0) < time()) {
        unset($_SESSION[$key]);
        return null;
    }

    return $state;
}

function otp_seconds_left(string $flow): int
{
    $state = otp_state($flow);
    if (!$state) {
        return 0;
    }
    return max(0, (int) ($state['expires_at'] ?? 0) - time());
}

function otp_clear(string $flow): void
{
    unset($_SESSION[otp_session_key($flow)]);
}

function otp_verify(string $flow, string $code): array
{
    $key = otp_session_key($flow);
    $state = otp_state($flow);
    if (!$state) {
        return ['ok' => false, 'error' => 'Verification code (OTP) is missing or expired. Please request a new code.'];
    }

    $cleanCode = preg_replace('/\D+/', '', trim($code)) ?? '';
    if ($cleanCode === '') {
        return ['ok' => false, 'error' => 'Please enter the verification code (OTP).'];
    }

    $attempts = (int) ($state['attempts'] ?? 0) + 1;
    $maxAttempts = (int) ($state['max_attempts'] ?? 5);
    $state['attempts'] = $attempts;
    $_SESSION[$key] = $state;

    if (!password_verify($cleanCode, (string) ($state['code_hash'] ?? ''))) {
        $remaining = max(0, $maxAttempts - $attempts);
        if ($remaining <= 0) {
            unset($_SESSION[$key]);
            return ['ok' => false, 'error' => 'Verification failed too many times. Please request a new code.'];
        }
        return ['ok' => false, 'error' => 'Incorrect verification code. Attempts left: ' . $remaining . '.'];
    }

    $payload = is_array($state['payload'] ?? null) ? $state['payload'] : [];
    unset($_SESSION[$key]);
    return ['ok' => true, 'payload' => $payload];
}

function san_enrique_barangays(): array
{
    return [
        'Bagonawa',
        'Baliwagan',
        'Batuan',
        'Guintorilan',
        'Nayon',
        'Poblacion',
        'Sibucao',
        'Tabao Baybay',
        'Tabao Rizal',
        'Tibsoc',
    ];
}

function san_enrique_town(): string
{
    return 'San Enrique';
}

function san_enrique_province(): string
{
    return 'Negros Occidental';
}

function normalize_barangay(string $barangay): string
{
    $barangay = trim($barangay);
    if ($barangay === '') {
        return '';
    }

    foreach (san_enrique_barangays() as $allowed) {
        if (strcasecmp($barangay, $allowed) === 0) {
            return $allowed;
        }
    }

    return '';
}

function negros_occidental_colleges_universities(): array
{
    return [
        'Central Philippines State University',
        'Carlos Hilado Memorial State University',
        'University of St. La Salle',
        'University of Negros Occidental - Recoletos',
        'STI West Negros University',
        'Colegio San Agustin - Bacolod',
        'La Consolacion College Bacolod',
        'Riverside College, Inc.',
        'Bacolod City College',
        'Bago City College',
        'John B. Lacson Colleges Foundation - Bacolod',
        'VMA Global College and Training Centers',
        'AMA Computer College - Bacolod',
        'Asian College of Aeronautics - Bacolod',
        'La Carlota City College',
        'I-TECH College',
        'Technological University of the Philippines - Visayas',
        'Philippine Normal University - Visayas',
    ];
}

function school_name_aliases(): array
{
    return [
        'Central Philippines State University' => [
            'cpsu',
            'central philippines state university',
            'central philippine state university',
            'cpsu main',
            'cpsu kabankalan',
            'cpsu san carlos',
            'cpsu sipalay',
            'cpsu hinigaran',
            'cpsu ilog',
            'cpsu candoni',
            'cpsu cauayan',
            'cpsu moises padilla',
            'cpsu valladolid',
            'central philippines state university main campus',
            'central philippines state university kabankalan',
            'central philippines state university san carlos',
            'central philippines state university sipalay',
            'central philippines state university hinigaran',
            'central philippines state university ilog',
            'central philippines state university candoni',
            'central philippines state university cauayan',
            'central philippines state university moises padilla',
            'central philippines state university valladolid',
        ],
        'Carlos Hilado Memorial State University' => [
            'chmsu',
            'carlos hilado memorial state university',
            'carlos hilado memorial state university main',
            'chmsu alijis',
            'carlos hilado memorial state university alijis',
            'chmsu fortune towne',
            'carlos hilado memorial state university fortune towne',
            'chmsu binalbagan',
            'carlos hilado memorial state university binalbagan',
            'chmsu talisay',
            'carlos hilado memorial state university talisay',
        ],
        'University of St. La Salle' => [
            'usls',
            'university of st la salle',
            'university of saint la salle',
        ],
        'University of Negros Occidental - Recoletos' => [
            'uno-r',
            'unor',
            'uno r',
            'university of negros occidental recoletos',
        ],
        'STI West Negros University' => [
            'sti wnu',
            'sti west negros university',
            'sti west negros',
        ],
        'Colegio San Agustin - Bacolod' => [
            'csab',
            'colegio san agustin bacolod',
        ],
        'La Consolacion College Bacolod' => [
            'lccb',
            'la consolacion college bacolod',
        ],
        'Riverside College, Inc.' => [
            'rci',
            'riverside college',
            'riverside college inc',
        ],
        'Bacolod City College' => [
            'bcc',
            'bacolod city college',
        ],
        'Bago City College' => [
            'bago city college',
        ],
        'Northern Negros State College of Science and Technology' => [
            'nonescost',
            'northern negros state college of science and technology',
        ],
        'Fellowship Baptist College' => [
            'fbc',
            'fellowship baptist college',
        ],
        'John B. Lacson Colleges Foundation - Bacolod' => [
            'jblcf-bacolod',
            'jblcf bacolod',
            'john b lacson colleges foundation bacolod',
            'john b lacson bacolod',
        ],
        'VMA Global College and Training Centers' => [
            'vma global',
            'vma global college',
            'vma global college and training centers',
        ],
        'AMA Computer College - Bacolod' => [
            'ama bacolod',
            'ama computer college bacolod',
            'ama',
        ],
        'Asian College of Aeronautics - Bacolod' => [
            'aca bacolod',
            'asian college of aeronautics bacolod',
        ],
        'Abea-Binzons College' => [
            'abea binzons',
            'abea-binzons college',
        ],
        'West Negros College' => [
            'wnc',
            'west negros college',
        ],
        'Talisay City College' => [
            'tcc',
            'talisay city college',
        ],
        'Silay Institute' => [
            'silay institute',
        ],
        'State University of Northern Negros - Main Campus' => [
            'sunn main',
            'state university of northern negros',
            'state university of northern negros main',
            'sunn',
        ],
        'State University of Northern Negros - Escalante City Campus' => [
            'sunn escalante',
            'state university of northern negros escalante',
        ],
        'State University of Northern Negros - Victorias City Campus' => [
            'sunn victorias',
            'state university of northern negros victorias',
        ],
        'State University of Northern Negros - San Carlos City Campus' => [
            'sunn san carlos',
            'state university of northern negros san carlos',
        ],
        'Kabankalan Catholic College' => [
            'kcc',
            'kabankalan catholic college',
        ],
        'Colegio de Santa Rita de San Carlos, Inc.' => [
            'cssr',
            'colegio de santa rita de san carlos',
        ],
        'Colegio de Sta. Ana de Victorias' => [
            'csav',
            'colegio de sta ana de victorias',
            'colegio de santa ana de victorias',
        ],
        'Colegio de Sto. Tomas Recoletos' => [
            'cstr',
            'colegio de sto tomas recoletos',
            'colegio de santo tomas recoletos',
        ],
        'La Carlota City College' => [
            'lccc',
            'la carlota city college',
        ],
        'I-TECH College' => [
            'itech',
            'i-tech',
            'i-tech college',
            'i tech college',
            'i-tech computer education',
        ],
        'Technological University of the Philippines - Visayas' => [
            'tup visayas',
            'tup-v',
            'technological university of the philippines visayas',
        ],
        'Philippine Normal University - Visayas' => [
            'pnu visayas',
            'pnu-v',
            'philippine normal university visayas',
        ],
        'Binalbagan Catholic College' => [
            'bcc binalbagan',
            'binalbagan catholic college',
        ],
        'Southland College' => [
            'southland college',
        ],
        'Paglaum State University' => [
            'psu',
            'paglaum state university',
        ],
    ];
}

function scholarship_course_options(): array
{
    return [
        'BS Information Technology',
        'BS Computer Science',
        'BS Information Systems',
        'BS Accountancy',
        'BS Business Administration',
        'BS Hospitality Management',
        'BS Tourism Management',
        'BS Secondary Education',
        'BS Elementary Education',
        'BS Psychology',
        'BS Criminology',
        'BS Nursing',
        'BS Midwifery',
        'BS Medical Technology',
        'BS Civil Engineering',
        'BS Mechanical Engineering',
        'BS Electrical Engineering',
        'BS Industrial Engineering',
        'BS Agriculture',
        'BS Agribusiness',
        'BS Fisheries',
    ];
}

function format_title_case_text(string $value): string
{
    $value = strtolower($value);
    return preg_replace_callback('/[a-z]+/i', static function (array $matches): string {
        $word = (string) ($matches[0] ?? '');
        if ($word === '') {
            return $word;
        }
        return strtoupper(substr($word, 0, 1)) . substr($word, 1);
    }, $value) ?? $value;
}

function validate_typed_academic_text(string $value, string $label, int $minLength = 3, int $maxLength = 120): array
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    if ($value === '') {
        return ['ok' => false, 'value' => '', 'error' => $label . ' is required.'];
    }

    $length = strlen($value);
    if ($length < $minLength || $length > $maxLength) {
        return ['ok' => false, 'value' => '', 'error' => $label . ' must be between ' . $minLength . ' and ' . $maxLength . ' characters.'];
    }

    if (preg_match("/^[A-Za-z0-9 .,&()'\\/-]+$/", $value) !== 1) {
        return ['ok' => false, 'value' => '', 'error' => $label . ' contains invalid characters.'];
    }

    if (preg_match("/[.,&()'\\/-]{2,}/", $value) === 1) {
        return ['ok' => false, 'value' => '', 'error' => $label . ' has repeated symbols.'];
    }

    if (preg_match('/[A-Za-z]/', $value) !== 1) {
        return ['ok' => false, 'value' => '', 'error' => $label . ' must include letters.'];
    }

    $formatted = format_title_case_text($value);
    return ['ok' => true, 'value' => $formatted, 'error' => ''];
}

function normalize_course_name(string $courseName): string
{
    $courseName = trim($courseName);
    if ($courseName === '') {
        return '';
    }

    return preg_replace('/\s+/', ' ', $courseName) ?? $courseName;
}

function is_valid_scholarship_course(string $courseName): bool
{
    $courseName = trim($courseName);
    if ($courseName === '') {
        return false;
    }

    foreach (scholarship_course_options() as $allowed) {
        if (strcasecmp($courseName, $allowed) === 0) {
            return true;
        }
    }
    return false;
}

function normalize_school_name(string $schoolName): string
{
    $schoolName = trim($schoolName);
    if ($schoolName === '') {
        return '';
    }

    $schoolName = preg_replace('/\s+/', ' ', $schoolName) ?? $schoolName;
    $key = strtolower((string) (preg_replace('/[^a-z0-9]+/', '', $schoolName) ?? ''));

    foreach (school_name_aliases() as $canonical => $aliases) {
        $candidateKeys = array_merge([$canonical], $aliases);
        foreach ($candidateKeys as $candidate) {
            $candidateKey = strtolower((string) (preg_replace('/[^a-z0-9]+/', '', (string) $candidate) ?? ''));
            if ($candidateKey !== '' && $candidateKey === $key) {
                return $canonical;
            }
        }
    }

    return $schoolName;
}

function is_valid_negros_occidental_school_name(string $schoolName): bool
{
    $schoolName = trim($schoolName);
    if ($schoolName === '') {
        return false;
    }

    foreach (negros_occidental_colleges_universities() as $allowed) {
        if (strcasecmp($schoolName, $allowed) === 0) {
            return true;
        }
    }
    return false;
}

function is_valid_barangay(string $barangay): bool
{
    return normalize_barangay($barangay) !== '';
}

function calculate_age_from_birth_date(?string $birthDate): ?int
{
    $birthDate = trim((string) $birthDate);
    if ($birthDate === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    if (!$date || $date->format('Y-m-d') !== $birthDate) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    if ($date > $today) {
        return null;
    }

    return $date->diff($today)->y;
}

function application_status_options(): array
{
    return [
        'under_review',
        'needs_resubmission',
        'for_interview',
        'for_soa',
        'approved_for_release',
        'released',
        'rejected',
    ];
}

function approved_phase_statuses(): array
{
    return ['for_soa', 'approved_for_release', 'released'];
}

function application_status_meta(string $status): array
{
    $status = trim($status);

    $map = [
        'under_review' => [
            'label' => 'Under Review',
            'short_label' => 'Review',
            'applicant_title' => 'Your application is being reviewed.',
            'applicant_detail' => 'Wait for document review results. You will be notified if anything needs to be replaced.',
            'staff_title' => 'Review submitted documents.',
            'staff_detail' => 'Check each uploaded requirement, add optional notes, then either request resubmission or move the applicant to interview.',
            'next_action_label' => 'Review documents',
            'tone' => 'info',
        ],
        'needs_resubmission' => [
            'label' => 'Needs Resubmission',
            'short_label' => 'Resubmit',
            'applicant_title' => 'Replace the incomplete or rejected documents.',
            'applicant_detail' => 'Upload only the documents marked for resubmission, then send them back for review.',
            'staff_title' => 'Waiting for applicant replacements.',
            'staff_detail' => 'Applicant must re-upload the rejected documents before the record can move forward.',
            'next_action_label' => 'Upload replacement files',
            'tone' => 'warning',
        ],
        'for_interview' => [
            'label' => 'For Interview',
            'short_label' => 'Interview',
            'applicant_title' => 'Prepare for your interview schedule.',
            'applicant_detail' => 'Check the interview date, time, and location. Bring the required identification on schedule.',
            'staff_title' => 'Schedule or complete the interview stage.',
            'staff_detail' => 'Set the interview schedule, then move qualified applicants to SOA submission.',
            'next_action_label' => 'Schedule interview',
            'tone' => 'primary',
        ],
        'for_soa' => [
            'label' => 'Submit SOA',
            'short_label' => 'SOA',
            'applicant_title' => 'Submit your SOA or student copy.',
            'applicant_detail' => 'Submit the school-issued SOA before the deadline so final release can be prepared.',
            'staff_title' => 'Collect and confirm SOA submission.',
            'staff_detail' => 'Track the deadline, record the SOA receipt, and move complete records to release approval.',
            'next_action_label' => 'Submit SOA',
            'tone' => 'primary',
        ],
        'approved_for_release' => [
            'label' => 'Approved for Payout',
            'short_label' => 'Approved',
            'applicant_title' => 'Your application is approved for payout scheduling.',
            'applicant_detail' => 'Wait for the payout schedule and keep your QR code ready.',
            'staff_title' => 'Prepare the payout release step.',
            'staff_detail' => 'This record is cleared for disbursement scheduling and release confirmation.',
            'next_action_label' => 'Prepare payout release',
            'tone' => 'secondary',
        ],
        'released' => [
            'label' => 'Released',
            'short_label' => 'Released',
            'applicant_title' => 'Payout has been released.',
            'applicant_detail' => 'This application is complete.',
            'staff_title' => 'Application completed.',
            'staff_detail' => 'No further action is needed unless records or reporting must be reviewed.',
            'next_action_label' => 'Completed',
            'tone' => 'success',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'short_label' => 'Rejected',
            'applicant_title' => 'This application was not approved.',
            'applicant_detail' => 'Review the notes for the recorded reason.',
            'staff_title' => 'Application closed as rejected.',
            'staff_detail' => 'No further workflow action is available for this record.',
            'next_action_label' => 'Closed',
            'tone' => 'danger',
        ],
    ];

    return $map[$status] ?? [
        'label' => ucwords(str_replace('_', ' ', $status !== '' ? $status : 'unknown')),
        'short_label' => ucwords(str_replace('_', ' ', $status !== '' ? $status : 'unknown')),
        'applicant_title' => 'Application status updated.',
        'applicant_detail' => 'Please check the latest application details for the next step.',
        'staff_title' => 'Review application status.',
        'staff_detail' => 'Check the record and continue the workflow as needed.',
        'next_action_label' => 'Review record',
        'tone' => 'secondary',
    ];
}

function application_status_label(string $status): string
{
    return (string) (application_status_meta($status)['label'] ?? ucwords(str_replace('_', ' ', $status)));
}

function application_workflow_steps(): array
{
    return [
        'under_review',
        'needs_resubmission',
        'for_interview',
        'for_soa',
        'approved_for_release',
        'released',
    ];
}

function application_timeline_steps(string $status): array
{
    $steps = [];
    $workflow = application_workflow_steps();
    $currentIndex = array_search($status, $workflow, true);

    foreach ($workflow as $index => $stepStatus) {
        $meta = application_status_meta($stepStatus);
        $state = 'upcoming';
        if ($status === 'rejected') {
            $state = $stepStatus === 'under_review' ? 'current' : 'upcoming';
        } elseif ($status === 'needs_resubmission') {
            if ($stepStatus === 'under_review') {
                $state = 'complete';
            } elseif ($stepStatus === 'needs_resubmission') {
                $state = 'current';
            }
        } elseif ($currentIndex !== false) {
            if ($index < $currentIndex) {
                $state = 'complete';
            } elseif ($index === $currentIndex) {
                $state = 'current';
            }
        }

        $steps[] = [
            'status' => $stepStatus,
            'label' => (string) ($meta['label'] ?? application_status_label($stepStatus)),
            'short_label' => (string) ($meta['short_label'] ?? application_status_label($stepStatus)),
            'state' => $state,
        ];
    }

    if ($status === 'rejected') {
        $steps[] = [
            'status' => 'rejected',
            'label' => 'Rejected',
            'short_label' => 'Rejected',
            'state' => 'current',
        ];
    }

    return $steps;
}

function application_next_action_summary(array $application, string $audience = 'applicant'): array
{
    $status = trim((string) ($application['status'] ?? ''));
    $meta = application_status_meta($status);
    $isStaff = strtolower(trim($audience)) === 'staff';

    $title = (string) ($meta[$isStaff ? 'staff_title' : 'applicant_title'] ?? 'Review application status.');
    $detail = (string) ($meta[$isStaff ? 'staff_detail' : 'applicant_detail'] ?? '');

    if (!$isStaff && $status === 'needs_resubmission') {
        $rejectedCount = (int) ($application['rejected_document_count'] ?? 0);
        if ($rejectedCount > 0) {
            $title = 'Upload ' . $rejectedCount . ' replacement document' . ($rejectedCount === 1 ? '' : 's') . '.';
        }
    }

    if (!$isStaff && $status === 'for_interview' && !empty($application['interview_date']) && !empty($application['interview_location'])) {
        $title = 'Attend the scheduled interview.';
        $detail = 'Interview schedule: '
            . date('M d, Y h:i A', strtotime((string) $application['interview_date']))
            . ' at '
            . trim((string) $application['interview_location']) . '.';
    }

    if (!$isStaff && $status === 'for_soa' && !empty($application['soa_submission_deadline'])) {
        if (!empty($application['soa_submitted_at'])) {
            $title = 'SOA submitted successfully.';
            $detail = 'Your uploaded SOA is being reviewed by the scholarship office.';
        } else {
            $detail = 'Submit the SOA on or before '
                . date('M d, Y', strtotime((string) $application['soa_submission_deadline']))
                . '.';
        }
    }

    if ($isStaff && $status === 'for_interview') {
        $hasInterviewSchedule = trim((string) ($application['interview_date'] ?? '')) !== ''
            && trim((string) ($application['interview_location'] ?? '')) !== '';
        if ($hasInterviewSchedule) {
            $title = 'Interview scheduled. Move qualified applicants to SOA after completion.';
            $detail = 'Current schedule: '
                . date('M d, Y h:i A', strtotime((string) $application['interview_date']))
                . ' at '
                . trim((string) ($application['interview_location'] ?? '')) . '.';
        }
    }

    return [
        'title' => $title,
        'detail' => $detail,
        'action_label' => (string) ($meta['next_action_label'] ?? 'Review record'),
        'label' => (string) ($meta['label'] ?? application_status_label($status)),
    ];
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'under_review' => 'text-bg-info',
        'needs_resubmission' => 'text-bg-warning',
        'for_interview' => 'text-bg-warning',
        'for_soa' => 'text-bg-warning',
        'approved_for_release' => 'text-bg-secondary',
        'released' => 'text-bg-success',
        'scheduled' => 'text-bg-info',
        'cancelled' => 'text-bg-danger',
        'success' => 'text-bg-success',
        'failed' => 'text-bg-danger',
        'queued' => 'text-bg-secondary',
        'rejected' => 'text-bg-danger',
        default => 'text-bg-light'
    };
}

function application_status_group(string $status): string
{
    return match ($status) {
        'released' => 'released',
        'rejected' => 'rejected',
        default => 'in_progress',
    };
}

function application_status_group_label(string $status): string
{
    return match (application_status_group($status)) {
        'released' => 'Released',
        'rejected' => 'Rejected',
        default => 'In Progress',
    };
}

function application_status_group_badge_class(string $status): string
{
    return match (application_status_group($status)) {
        'released' => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        default => 'text-bg-warning',
    };
}

function excerpt(string $text, int $length = 160): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }

    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length - 3) . '...';
}

function upload_document(string $fieldName, string $targetDir): ?string
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed upload for field: ' . $fieldName);
    }

    $maxUploadMb = (int) (getenv('UPLOAD_MAX_FILE_MB') ?: 25);
    if ($maxUploadMb < 5) {
        $maxUploadMb = 5;
    }
    $maxSize = $maxUploadMb * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('File too large (max ' . $maxUploadMb . 'MB): ' . $fieldName);
    }

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Invalid file type for: ' . $fieldName);
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $newName = uniqid($fieldName . '_', true) . '.' . $ext;
    $fullPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
    if (!move_uploaded_file((string) $file['tmp_name'], $fullPath)) {
        throw new RuntimeException('Could not move uploaded file: ' . $fieldName);
    }

    return str_replace('\\', '/', $fullPath);
}

function upload_any_file(string $fieldName, string $targetDir, array $allowedExt = ['pdf', 'jpg', 'jpeg', 'png']): ?array
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed upload for field: ' . $fieldName);
    }

    $maxUploadMb = (int) (getenv('UPLOAD_MAX_FILE_MB') ?: 25);
    if ($maxUploadMb < 8) {
        $maxUploadMb = 8;
    }
    $maxSize = $maxUploadMb * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('File too large (max ' . $maxUploadMb . 'MB): ' . $fieldName);
    }

    $originalName = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Invalid file type for: ' . $fieldName);
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $newName = uniqid('tmp_', true) . '.' . $ext;
    $fullPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
    if (!move_uploaded_file((string) $file['tmp_name'], $fullPath)) {
        throw new RuntimeException('Could not store uploaded file: ' . $fieldName);
    }

    $mimeType = (string) ($file['type'] ?? '');
    return [
        'file_path' => str_replace('\\', '/', $fullPath),
        'original_name' => $originalName,
        'ext' => $ext,
        'mime' => $mimeType,
    ];
}

function save_base64_image(string $base64Image, string $targetDir): string
{
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
        throw new RuntimeException('Invalid image data format.');
    }

    $ext = strtolower($matches[1]);
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Unsupported image format.');
    }

    $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
    $decoded = base64_decode($imageData, true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid base64 image data.');
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = uniqid('photo_', true) . '.jpg';
    $fullPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
    file_put_contents($fullPath, $decoded);

    return str_replace('\\', '/', $fullPath);
}

function wizard_default_state(): array
{
    return [
        'step1' => [],
        'step2' => [],
        'step3' => [],
        'documents' => [],
        'step1_done' => false,
        'step2_done' => false,
        'step3_done' => false,
        'step4_done' => false,
        'photo_path' => null,
    ];
}

function wizard_step1_is_complete(array $state): bool
{
    $step1 = is_array($state['step1'] ?? null) ? $state['step1'] : [];
    $required = ['applicant_type', 'semester', 'school_year', 'school_name', 'school_type', 'course'];
    foreach ($required as $field) {
        if (trim((string) ($step1[$field] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

function wizard_step2_is_complete(array $state): bool
{
    $step2 = is_array($state['step2'] ?? null) ? $state['step2'] : [];
    if (
        trim((string) ($step2['last_name'] ?? '')) === ''
        || trim((string) ($step2['first_name'] ?? '')) === ''
        || trim((string) ($step2['email'] ?? '')) === ''
        || trim((string) ($step2['contact_number'] ?? '')) === ''
        || trim((string) ($step2['barangay'] ?? '')) === ''
    ) {
        return false;
    }

    return filter_var((string) ($step2['email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false
        && is_valid_mobile_number((string) ($step2['contact_number'] ?? ''))
        && is_valid_barangay((string) ($step2['barangay'] ?? ''));
}

function wizard_has_progress(array $state): bool
{
    $state = array_merge(wizard_default_state(), $state);
    return !empty($state['step1'])
        || !empty($state['step2'])
        || !empty($state['step3'])
        || !empty($state['documents'])
        || !empty($state['photo_path'])
        || (bool) ($state['step1_done'] ?? false)
        || (bool) ($state['step2_done'] ?? false)
        || (bool) ($state['step3_done'] ?? false)
        || (bool) ($state['step4_done'] ?? false);
}

function wizard_resume_step(array $state): int
{
    $state = array_merge(wizard_default_state(), $state);

    if (!(bool) ($state['step1_done'] ?? false)) {
        return 1;
    }
    if (!(bool) ($state['step2_done'] ?? false)) {
        return 2;
    }
    if (!(bool) ($state['step3_done'] ?? false)) {
        return 3;
    }
    if (!(bool) ($state['step4_done'] ?? false)) {
        return 4;
    }
    if (trim((string) ($state['photo_path'] ?? '')) === '') {
        return 5;
    }
    return 6;
}

function wizard_state(): array
{
    if (!isset($_SESSION['application_wizard']) || !is_array($_SESSION['application_wizard'])) {
        $_SESSION['application_wizard'] = wizard_default_state();
    }

    $state = array_merge(wizard_default_state(), $_SESSION['application_wizard']);

    // Backward-compatible upgrade for drafts saved before step done flags existed.
    if (!(bool) ($state['step1_done'] ?? false) && wizard_step1_is_complete($state)) {
        $state['step1_done'] = true;
    }
    if (!(bool) ($state['step2_done'] ?? false) && wizard_step2_is_complete($state)) {
        $state['step2_done'] = true;
    }
    if (!(bool) ($state['step3_done'] ?? false) && !empty($state['step3'])) {
        $state['step3_done'] = true;
    }

    $_SESSION['application_wizard'] = $state;
    return $_SESSION['application_wizard'];
}

function wizard_save(array $state): void
{
    $_SESSION['application_wizard'] = array_merge(wizard_default_state(), $state);
}

function wizard_load_persistent_draft(mysqli $conn, int $userId): ?array
{
    if ($userId <= 0 || !table_exists($conn, 'application_wizard_drafts')) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT wizard_json, current_step
         FROM application_wizard_drafts
         WHERE user_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $decoded = json_decode((string) ($row['wizard_json'] ?? ''), true);
    if (!is_array($decoded)) {
        return null;
    }

    $state = array_merge(wizard_default_state(), $decoded);
    if (!(bool) ($state['step1_done'] ?? false) && wizard_step1_is_complete($state)) {
        $state['step1_done'] = true;
    }
    if (!(bool) ($state['step2_done'] ?? false) && wizard_step2_is_complete($state)) {
        $state['step2_done'] = true;
    }
    if (!(bool) ($state['step3_done'] ?? false) && !empty($state['step3'])) {
        $state['step3_done'] = true;
    }

    $currentStep = (int) ($row['current_step'] ?? 0);
    $currentStep = max(1, min(6, $currentStep));

    return [
        'state' => $state,
        'current_step' => $currentStep,
    ];
}

function wizard_save_persistent_draft(mysqli $conn, int $userId, array $state, ?int $currentStep = null): void
{
    if ($userId <= 0 || !table_exists($conn, 'application_wizard_drafts')) {
        return;
    }

    $normalized = array_merge(wizard_default_state(), $state);
    if (!(bool) ($normalized['step1_done'] ?? false) && wizard_step1_is_complete($normalized)) {
        $normalized['step1_done'] = true;
    }
    if (!(bool) ($normalized['step2_done'] ?? false) && wizard_step2_is_complete($normalized)) {
        $normalized['step2_done'] = true;
    }

    if (!wizard_has_progress($normalized)) {
        $stmtDelete = $conn->prepare("DELETE FROM application_wizard_drafts WHERE user_id = ? LIMIT 1");
        if ($stmtDelete) {
            $stmtDelete->bind_param('i', $userId);
            $stmtDelete->execute();
            $stmtDelete->close();
        }
        return;
    }

    $stepToStore = $currentStep ?? wizard_resume_step($normalized);
    $stepToStore = max(1, min(6, $stepToStore));
    $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO application_wizard_drafts (user_id, wizard_json, current_step)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            wizard_json = VALUES(wizard_json),
            current_step = VALUES(current_step),
            updated_at = CURRENT_TIMESTAMP"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isi', $userId, $payload, $stepToStore);
    $stmt->execute();
    $stmt->close();
}

function wizard_clear_persistent_draft(mysqli $conn, int $userId): void
{
    if ($userId <= 0 || !table_exists($conn, 'application_wizard_drafts')) {
        return;
    }
    $stmt = $conn->prepare("DELETE FROM application_wizard_drafts WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function wizard_clear(): void
{
    $state = wizard_state();
    foreach ($state['documents'] as $doc) {
        $path = (string) ($doc['file_path'] ?? '');
        if ($path !== '' && str_contains($path, 'uploads/tmp/') && file_exists(__DIR__ . '/../' . $path)) {
            @unlink(__DIR__ . '/../' . $path);
        }
    }

    $photoPath = (string) ($state['photo_path'] ?? '');
    if ($photoPath !== '' && str_contains($photoPath, 'uploads/tmp/') && file_exists(__DIR__ . '/../' . $photoPath)) {
        @unlink(__DIR__ . '/../' . $photoPath);
    }

    unset($_SESSION['application_wizard']);
}

function array_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function json_array(?string $json): array
{
    if (!$json) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function application_qr_payload(array $application): string
{
    $payload = [
        'application_no' => $application['application_no'] ?? '',
        'qr_token' => $application['qr_token'] ?? '',
        'full_name' => trim((string) (($application['first_name'] ?? '') . ' ' . ($application['last_name'] ?? ''))),
        'school_year' => $application['school_year'] ?? '',
    ];
    return json_encode($payload, JSON_UNESCAPED_SLASHES);
}

function qr_data_uri(string $data, int $size = 320, int $margin = 10): string
{
    if (!class_exists(\Endroid\QrCode\Builder\Builder::class)) {
        return '';
    }

    try {
        $builder = new \Endroid\QrCode\Builder\Builder(
            writer: new \Endroid\QrCode\Writer\PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $data,
            encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
            errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::High,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin
        );
        $result = $builder->build();
        return $result->getDataUri();
    } catch (Throwable) {
        return '';
    }
}

function extract_qr_identifiers(string $rawScan): array
{
    $rawScan = trim($rawScan);
    if ($rawScan === '') {
        return [
            'qr_token' => null,
            'application_no' => null,
            'payload' => [],
        ];
    }

    $payload = json_decode($rawScan, true);
    if (is_array($payload)) {
        $qrToken = trim((string) ($payload['qr_token'] ?? ''));
        $applicationNo = trim((string) ($payload['application_no'] ?? ''));
        return [
            'qr_token' => $qrToken !== '' ? $qrToken : null,
            'application_no' => $applicationNo !== '' ? $applicationNo : null,
            'payload' => $payload,
        ];
    }

    $rawUpper = strtoupper($rawScan);
    if (str_starts_with($rawUpper, 'QR-')) {
        return [
            'qr_token' => $rawScan,
            'application_no' => null,
            'payload' => [],
        ];
    }

    if (str_starts_with($rawUpper, 'SE-') || str_starts_with($rawUpper, 'LEGACY-')) {
        return [
            'qr_token' => null,
            'application_no' => $rawScan,
            'payload' => [],
        ];
    }

    return [
        'qr_token' => null,
        'application_no' => null,
        'payload' => [],
    ];
}

function table_exists(mysqli $conn, string $tableName): bool
{
    $tableName = trim($tableName);
    if ($tableName === '') {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function generate_application_no(mysqli $conn): string
{
    $year = date('Y');
    $prefix = 'SE-' . $year . '-';
    $sequenceTable = 'application_no_sequences';
    if (!table_exists($conn, $sequenceTable)) {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS application_no_sequences (
                sequence_year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
                last_number INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (table_exists($conn, $sequenceTable)) {
        $yearInt = (int) $year;
        $stmtSeq = $conn->prepare(
            "INSERT INTO application_no_sequences (sequence_year, last_number)
             VALUES (?, 1)
             ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)"
        );
        if ($stmtSeq) {
            $stmtSeq->bind_param('i', $yearInt);
            $stmtSeq->execute();
            $stmtSeq->close();

            $nextNumber = (int) ($conn->insert_id ?? 0);
            if ($nextNumber <= 0) {
                $stmtCurrent = $conn->prepare(
                    "SELECT last_number
                     FROM application_no_sequences
                     WHERE sequence_year = ?
                     LIMIT 1"
                );
                if ($stmtCurrent) {
                    $stmtCurrent->bind_param('i', $yearInt);
                    $stmtCurrent->execute();
                    $row = $stmtCurrent->get_result()->fetch_assoc();
                    $stmtCurrent->close();
                    $nextNumber = (int) ($row['last_number'] ?? 0);
                }
            }

            if ($nextNumber > 0) {
                return $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM applications WHERE application_no LIKE CONCAT(?, '%')");
    if (!$stmt) {
        return $prefix . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $prefix . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
}

function generate_qr_token(): string
{
    return 'QR-' . date('YmdHis') . '-' . bin2hex(random_bytes(5));
}

function active_requirements(mysqli $conn, ?string $applicantType, ?string $schoolType): array
{
    $applicantType = $applicantType ?: '';
    $schoolType = $schoolType ?: '';

    $periodRequirementRows = current_period_requirements($conn, $applicantType, $schoolType);
    if ($periodRequirementRows) {
        return $periodRequirementRows;
    }

    $sql = "SELECT id, requirement_name, description, applicant_type, school_type, is_required, sort_order
            FROM requirement_templates
            WHERE is_active = 1
              AND (? = '' OR applicant_type IS NULL OR applicant_type = ?)
              AND (? = '' OR school_type IS NULL OR school_type = ?)
            ORDER BY sort_order ASC, id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $applicantType, $applicantType, $schoolType, $schoolType);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function ensure_application_period_requirements_table(mysqli $conn): bool
{
    static $ensured = false;

    if ($ensured) {
        return table_exists($conn, 'application_period_requirements');
    }

    $ensured = true;
    if (table_exists($conn, 'application_period_requirements')) {
        return true;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS application_period_requirements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_period_id INT UNSIGNED NOT NULL,
            requirement_template_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_application_period_requirement (application_period_id, requirement_template_id),
            CONSTRAINT fk_application_period_requirements_period
                FOREIGN KEY (application_period_id) REFERENCES application_periods(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_application_period_requirements_template
                FOREIGN KEY (requirement_template_id) REFERENCES requirement_templates(id)
                ON DELETE CASCADE
        )"
    );

    return table_exists($conn, 'application_period_requirements');
}

function save_application_period_requirements(mysqli $conn, int $periodId, array $requirementTemplateIds): void
{
    if ($periodId <= 0 || !ensure_application_period_requirements_table($conn)) {
        return;
    }

    $cleanIds = array_values(array_unique(array_filter(array_map(
        static fn($value): int => (int) $value,
        $requirementTemplateIds
    ), static fn(int $value): bool => $value > 0)));

    $conn->query("DELETE FROM application_period_requirements WHERE application_period_id = " . $periodId);
    if (!$cleanIds) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO application_period_requirements (application_period_id, requirement_template_id)
         VALUES (?, ?)"
    );

    foreach ($cleanIds as $templateId) {
        $stmt->bind_param('ii', $periodId, $templateId);
        $stmt->execute();
    }

    $stmt->close();
}

function current_period_requirements(mysqli $conn, ?string $applicantType, ?string $schoolType): array
{
    if (!ensure_application_period_requirements_table($conn)) {
        return [];
    }

    $openPeriod = current_open_application_period($conn);
    $periodId = (int) ($openPeriod['id'] ?? 0);
    if ($periodId <= 0) {
        return [];
    }

    $applicantType = $applicantType ?: '';
    $schoolType = $schoolType ?: '';

    $sql = "SELECT rt.id, rt.requirement_name, rt.description, rt.applicant_type, rt.school_type, rt.is_required, rt.sort_order
            FROM application_period_requirements apr
            INNER JOIN requirement_templates rt ON rt.id = apr.requirement_template_id
            WHERE apr.application_period_id = ?
              AND rt.is_active = 1
              AND (? = '' OR rt.applicant_type IS NULL OR rt.applicant_type = ?)
              AND (? = '' OR rt.school_type IS NULL OR rt.school_type = ?)
            ORDER BY rt.sort_order ASC, rt.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issss', $periodId, $applicantType, $applicantType, $schoolType, $schoolType);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function application_period_timeline_for_user(mysqli $conn, int $userId): array
{
    if ($userId <= 0 || !table_exists($conn, 'application_periods') || !table_exists($conn, 'applications')) {
        return [];
    }

    $periodRows = [];
    $result = $conn->query(
        "SELECT id, academic_year, semester, period_name, start_date, end_date
         FROM application_periods
         ORDER BY
            CASE WHEN start_date IS NULL THEN 1 ELSE 0 END ASC,
            start_date ASC,
            id ASC"
    );
    if ($result instanceof mysqli_result) {
        $periodRows = $result->fetch_all(MYSQLI_ASSOC);
    }
    if ($periodRows === []) {
        return [];
    }

    $applicationRows = [];
    $stmt = $conn->prepare(
        "SELECT id, application_no, application_period_id, semester, school_year, status, submitted_at, created_at
         FROM applications
         WHERE user_id = ?
         ORDER BY COALESCE(submitted_at, created_at) ASC, id ASC"
    );
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $applicationRows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    $applicationsByPeriodId = [];
    $applicationsByKey = [];
    foreach ($applicationRows as $applicationRow) {
        $periodId = (int) ($applicationRow['application_period_id'] ?? 0);
        if ($periodId > 0) {
            $applicationsByPeriodId[$periodId] = $applicationRow;
        }

        $semester = trim((string) ($applicationRow['semester'] ?? ''));
        $schoolYear = trim((string) ($applicationRow['school_year'] ?? ''));
        if ($semester !== '' && $schoolYear !== '') {
            $applicationsByKey[$semester . '|' . $schoolYear] = $applicationRow;
        }
    }

    $timeline = [];
    foreach ($periodRows as $periodRow) {
        $periodId = (int) ($periodRow['id'] ?? 0);
        $semester = trim((string) ($periodRow['semester'] ?? ''));
        $academicYear = trim((string) ($periodRow['academic_year'] ?? ''));
        $match = $applicationsByPeriodId[$periodId] ?? null;
        if (!$match && $semester !== '' && $academicYear !== '') {
            $match = $applicationsByKey[$semester . '|' . $academicYear] ?? null;
        }

        $hasApplication = is_array($match);
        $status = trim((string) ($match['status'] ?? ''));
        $isReleased = $status === 'released';

        $timeline[] = [
            'period_label' => format_application_period($periodRow),
            'application_id' => (int) ($match['id'] ?? 0),
            'application_no' => trim((string) ($match['application_no'] ?? '')),
            'status' => $status,
            'label' => $hasApplication ? 'Applied / Released' : 'No application',
            'badge_class' => $hasApplication
                ? ($isReleased ? 'text-bg-success' : 'text-bg-primary')
                : 'text-bg-light',
            'has_application' => $hasApplication,
            'is_released' => $isReleased,
        ];
    }

    return $timeline;
}

function move_temp_file_to_final(string $relativePath, string $targetDir, string $newPrefix): ?string
{
    $relativePath = trim($relativePath);
    if ($relativePath === '') {
        return null;
    }

    $source = __DIR__ . '/../' . ltrim($relativePath, '/');
    if (!file_exists($source)) {
        return null;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $newName = $newPrefix . '_' . uniqid('', true) . '.' . $ext;
    $destination = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
    rename($source, $destination);

    $baseProject = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
    $normalized = str_replace('\\', '/', $destination);
    if ($baseProject !== '' && str_starts_with($normalized, $baseProject . '/')) {
        return substr($normalized, strlen($baseProject) + 1);
    }
    return str_replace('\\', '/', $destination);
}

function in_array_safe(string $needle, array $haystack): bool
{
    return in_array($needle, $haystack, true);
}

function request_ip_address(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['HTTP_CLIENT_IP'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') {
            continue;
        }
        $parts = explode(',', $candidate);
        foreach ($parts as $part) {
            $ip = trim($part);
            if ($ip !== '') {
                return $ip;
            }
        }
    }

    return '';
}

function audit_logs_table_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = table_exists($conn, 'audit_logs');
    return $ready;
}

function audit_log(
    mysqli $conn,
    string $action,
    ?int $userId = null,
    ?string $userRole = null,
    ?string $entityType = null,
    ?string $entityId = null,
    ?string $description = null,
    array $metadata = []
): void {
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno) {
        return;
    }
    if (!audit_logs_table_ready($conn)) {
        return;
    }

    $action = trim($action);
    if ($action === '') {
        return;
    }

    $currentUser = current_user();
    if ($userId === null && is_array($currentUser)) {
        $userId = (int) ($currentUser['id'] ?? 0);
        if ($userId <= 0) {
            $userId = null;
        }
    }
    if ($userRole === null && is_array($currentUser)) {
        $userRole = trim((string) ($currentUser['role'] ?? ''));
    }

    $userRole = trim((string) ($userRole ?? ''));
    $entityType = trim((string) ($entityType ?? ''));
    $entityId = trim((string) ($entityId ?? ''));
    $description = trim((string) ($description ?? ''));

    if ($userRole !== '') {
        $userRole = function_exists('mb_substr') ? mb_substr($userRole, 0, 20) : substr($userRole, 0, 20);
    } else {
        $userRole = null;
    }
    if ($entityType !== '') {
        $entityType = function_exists('mb_substr') ? mb_substr($entityType, 0, 80) : substr($entityType, 0, 80);
    } else {
        $entityType = null;
    }
    if ($entityId !== '') {
        $entityId = function_exists('mb_substr') ? mb_substr($entityId, 0, 80) : substr($entityId, 0, 80);
    } else {
        $entityId = null;
    }
    if ($description !== '') {
        $description = function_exists('mb_substr') ? mb_substr($description, 0, 255) : substr($description, 0, 255);
    } else {
        $description = null;
    }

    $ipAddress = request_ip_address();
    if ($ipAddress !== '') {
        $ipAddress = function_exists('mb_substr') ? mb_substr($ipAddress, 0, 45) : substr($ipAddress, 0, 45);
    } else {
        $ipAddress = null;
    }
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent !== '') {
        $userAgent = function_exists('mb_substr') ? mb_substr($userAgent, 0, 255) : substr($userAgent, 0, 255);
    } else {
        $userAgent = null;
    }

    $metadataJson = null;
    if ($metadata !== []) {
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metadataJson === false) {
            $metadataJson = null;
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO audit_logs
         (user_id, user_role, action, entity_type, entity_id, description, metadata_json, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        'issssssss',
        $userId,
        $userRole,
        $action,
        $entityType,
        $entityId,
        $description,
        $metadataJson,
        $ipAddress,
        $userAgent
    );
    $stmt->execute();
    $stmt->close();
}

function notifications_table_ready(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = table_exists($conn, 'notifications');
    return $ready;
}

function create_notification(
    mysqli $conn,
    int $userId,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $relatedUrl = null,
    ?int $createdByUserId = null
): void {
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno) {
        return;
    }
    if (!notifications_table_ready($conn) || $userId <= 0) {
        return;
    }

    $title = trim($title);
    $message = trim($message);
    $notificationType = trim(strtolower($notificationType));
    $relatedUrl = trim((string) $relatedUrl);

    if ($title === '' || $message === '') {
        return;
    }

    if ($notificationType === '') {
        $notificationType = 'system';
    }

    $title = function_exists('mb_substr') ? mb_substr($title, 0, 180) : substr($title, 0, 180);
    $notificationType = function_exists('mb_substr') ? mb_substr($notificationType, 0, 40) : substr($notificationType, 0, 40);
    if ($relatedUrl !== '') {
        $relatedUrl = function_exists('mb_substr') ? mb_substr($relatedUrl, 0, 255) : substr($relatedUrl, 0, 255);
    } else {
        $relatedUrl = null;
    }

    $createdBy = ($createdByUserId !== null && $createdByUserId > 0) ? $createdByUserId : null;

    if ($createdBy === null) {
        $stmt = $conn->prepare(
            "INSERT INTO notifications
             (user_id, title, message, notification_type, related_url, created_by)
             VALUES (?, ?, ?, ?, ?, NULL)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('issss', $userId, $title, $message, $notificationType, $relatedUrl);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO notifications
             (user_id, title, message, notification_type, related_url, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('issssi', $userId, $title, $message, $notificationType, $relatedUrl, $createdBy);
    }

    $stmt->execute();
    $stmt->close();
}

function create_notifications_for_roles(
    mysqli $conn,
    array $roles,
    string $title,
    string $message,
    string $notificationType = 'system',
    ?string $relatedUrl = null,
    ?int $createdByUserId = null
): int {
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno) {
        return 0;
    }
    if (!notifications_table_ready($conn)) {
        return 0;
    }

    $allowedRoles = ['admin', 'staff', 'applicant'];
    $roles = array_values(array_unique(array_filter(
        $roles,
        static fn($role): bool => in_array((string) $role, $allowedRoles, true)
    )));
    if ($roles === []) {
        return 0;
    }

    $quotedRoles = [];
    foreach ($roles as $role) {
        $quotedRoles[] = "'" . $conn->real_escape_string((string) $role) . "'";
    }
    $sql = "SELECT id FROM users WHERE status = 'active' AND role IN (" . implode(', ', $quotedRoles) . ")";
    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        return 0;
    }

    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $targetUserId = (int) ($row['id'] ?? 0);
        if ($targetUserId <= 0) {
            continue;
        }
        create_notification(
            $conn,
            $targetUserId,
            $title,
            $message,
            $notificationType,
            $relatedUrl,
            $createdByUserId
        );
        $count++;
    }

    return $count;
}

function unread_notification_count(mysqli $conn, int $userId): int
{
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno || $userId <= 0) {
        return 0;
    }
    if (!notifications_table_ready($conn)) {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM notifications
         WHERE user_id = ?
           AND is_read = 0"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function list_notifications(mysqli $conn, int $userId, int $limit = 30, bool $unreadOnly = false): array
{
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno || $userId <= 0) {
        return [];
    }
    if (!notifications_table_ready($conn)) {
        return [];
    }

    $limit = max(1, min(200, $limit));

    if ($unreadOnly) {
        $stmt = $conn->prepare(
            "SELECT id, title, message, notification_type, related_url, is_read, read_at, created_at
             FROM notifications
             WHERE user_id = ?
               AND is_read = 0
             ORDER BY created_at DESC, id DESC
             LIMIT " . (int) $limit
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT id, title, message, notification_type, related_url, is_read, read_at, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT " . (int) $limit
        );
    }
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function mark_notification_read(mysqli $conn, int $notificationId, int $userId): bool
{
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno || $notificationId <= 0 || $userId <= 0) {
        return false;
    }
    if (!notifications_table_ready($conn)) {
        return false;
    }

    $stmt = $conn->prepare(
        "UPDATE notifications
         SET is_read = 1,
             read_at = COALESCE(read_at, NOW())
         WHERE id = ?
           AND user_id = ?
           AND is_read = 0
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows > 0;
    $stmt->close();
    return $affected;
}

function mark_all_notifications_read(mysqli $conn, int $userId): int
{
    if (!db_ready() || !$conn instanceof mysqli || $conn->connect_errno || $userId <= 0) {
        return 0;
    }
    if (!notifications_table_ready($conn)) {
        return 0;
    }

    $stmt = $conn->prepare(
        "UPDATE notifications
         SET is_read = 1,
             read_at = COALESCE(read_at, NOW())
         WHERE user_id = ?
           AND is_read = 0"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $affected = max(0, (int) $stmt->affected_rows);
    $stmt->close();
    return $affected;
}

function notification_type_badge_class(string $type): string
{
    return match (strtolower(trim($type))) {
        'application', 'application_status' => 'text-bg-primary',
        'interview' => 'text-bg-warning',
        'payout', 'disbursement' => 'text-bg-success',
        'period' => 'text-bg-info',
        'security' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
}

function audit_action_label(string $action): string
{
    $action = trim($action);
    if ($action === '') {
        return '-';
    }

    return match ($action) {
        'announcement_created' => 'Announcement Created',
        'announcement_deleted' => 'Announcement Deleted',
        'announcement_sms_broadcast' => 'Announcement SMS Broadcast',
        'announcement_status_changed' => 'Announcement Status Changed',
        'announcement_updated' => 'Announcement Updated',
        'application_mark_soa_submitted' => 'Application Marked SOA Submitted',
        'application_period_close_all' => 'All Application Periods Closed',
        'application_period_created' => 'Application Period Created',
        'application_period_extended' => 'Application Period Deadline Extended',
        'application_period_set_open' => 'Application Period Set Open',
        'application_period_updated' => 'Application Period Updated',
        'application_set_soa_deadline' => 'SOA Deadline Set',
        'application_status_updated' => 'Application Status Updated',
        'application_submit_failed' => 'Application Submission Failed',
        'application_submitted' => 'Application Submitted',
        'change_mobile_cancelled' => 'Mobile Number Change Cancelled',
        'change_mobile_otp_generated_dev_mode' => 'Mobile Change Verification Code Generated (Dev Mode)',
        'change_mobile_otp_send_failed' => 'Mobile Change Verification Code Send Failed',
        'change_mobile_otp_sent' => 'Mobile Change Verification Code Sent',
        'change_mobile_otp_verify_failed' => 'Mobile Change Verification Code Check Failed',
        'change_mobile_success' => 'Mobile Number Changed',
        'disbursement_created' => 'Payout Schedule Created',
        'disbursement_date_updated' => 'Payout Schedule Updated',
        'forgot_password_otp_generated_dev_mode' => 'Forgot Password Verification Code Generated (Dev Mode)',
        'forgot_password_otp_send_failed' => 'Forgot Password Verification Code Send Failed',
        'forgot_password_otp_sent' => 'Forgot Password Verification Code Sent',
        'forgot_password_otp_verify_failed' => 'Forgot Password Verification Code Check Failed',
        'forgot_password_reset_success' => 'Password Reset Successful',
        'interview_schedule_updated' => 'Interview Schedule Updated',
        'login_blocked_inactive' => 'Login Blocked (Inactive Account)',
        'login_failed' => 'Login Failed',
        'login_success' => 'Login Successful',
        'logout' => 'Logout',
        'password_changed' => 'Password Changed',
        'profile_updated' => 'Profile Updated',
        'qr_scan_verified' => 'QR Scan Verified',
        'register_account_created' => 'Account Registered',
        'register_otp_cancelled' => 'Registration Verification Code Cancelled',
        'register_otp_generated_dev_mode' => 'Registration Verification Code Generated (Dev Mode)',
        'register_otp_send_failed' => 'Registration Verification Code Send Failed',
        'register_otp_sent' => 'Registration Verification Code Sent',
        'register_otp_verify_failed' => 'Registration Verification Code Check Failed',
        'requirement_template_created' => 'Requirement Template Created',
        'requirement_template_status_changed' => 'Requirement Template Status Changed',
        'sms_bulk_sent' => 'Bulk SMS Sent',
        'sms_selected_sent' => 'Selected SMS Sent',
        'sms_single_sent' => 'Single SMS Sent',
        'sms_template_created' => 'SMS Template Created',
        'sms_template_deleted' => 'SMS Template Deleted',
        'sms_template_updated' => 'SMS Template Updated',
        'staff_account_created' => 'Staff Account Created',
        'staff_account_updated' => 'Staff Account Updated',
        'staff_account_deleted' => 'Staff Account Deleted',
        default => ucwords(str_replace('_', ' ', $action)),
    };
}

function audit_entity_label(?string $entityType): string
{
    $entityType = trim((string) $entityType);
    if ($entityType === '') {
        return 'System';
    }

    return match ($entityType) {
        'application_period' => 'Application Period',
        'application' => 'Application',
        'disbursement' => 'Payout Schedule',
        'registration' => 'Registration',
        'password_reset' => 'Password Reset',
        'auth' => 'Authentication',
        'sms' => 'SMS',
        'sms_template' => 'SMS Template',
        'user' => 'User Account',
        default => ucwords(str_replace('_', ' ', $entityType)),
    };
}
