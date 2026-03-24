<?php

// 1. Start the session so we can remember if a user is logged in
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// 2. Load Composer's Autoloader (This automatically loads all our classes and libraries!)
// We use __DIR__ . '/../' because index.php is inside /public, and vendor is one folder up.
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Support/helpers.php';

// 3. Load Environment Variables securely from the .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// 4. Initialize the Bramus Router
$router = new \Bramus\Router\Router();

// Configure the app base path from environment so deployment can run at root or in a subfolder.
$configuredBasePath = trim((string) ($_ENV['APP_BASE_PATH'] ?? ''), '/');
$basePath = $configuredBasePath !== '' ? '/' . $configuredBasePath : '';
$_SERVER['APP_BASE_PATH'] = $basePath;
$router->setBasePath($basePath);

// ==========================================
// 🚦 APPLICATION ROUTES
// ==========================================

// Homepage / Login Screen
$router->get('/', function () {
    require __DIR__ . '/../views/public/home.php';
});

$router->get('/login', function () {
    require __DIR__ . '/../views/auth/login.php';
});

// Authentication Routes (Login, Register, OTP)
$router->post('/login', '\App\Controllers\AuthController@login');
$router->get('/forgot-password', function () {
    require __DIR__ . '/../views/auth/forgot_password.php';
});
$router->post('/forgot-password', '\App\Controllers\AuthController@forgotPassword');
$router->get('/recover-account', function () {
    require __DIR__ . '/../views/auth/recover_account.php';
});
$router->post('/recover-account', '\App\Controllers\AuthController@recoverAccount');
$router->get('/register', function () {
    require __DIR__ . '/../views/auth/register.php';
});
$router->post('/register', '\App\Controllers\AuthController@register');
$router->get('/verify-otp', function () {
    require __DIR__ . '/../views/auth/otp_verify.php';
});
$router->post('/verify-otp', '\App\Controllers\AuthController@verifyOtp');
$router->post('/verify-otp/resend', '\App\Controllers\AuthController@resendVerificationOtp');
$router->get('/verify-otp/cancel', '\App\Controllers\AuthController@cancelVerificationOtp');
$router->get('/reset-password', function () {
    require __DIR__ . '/../views/auth/reset_password.php';
});
$router->post('/reset-password', '\App\Controllers\AuthController@resetPassword');
$router->get('/notifications/feed', '\App\Controllers\NotificationController@feed');
$router->post('/notifications/mark-read', '\App\Controllers\NotificationController@markAsRead');
$router->post('/notifications/mark-all-read', '\App\Controllers\NotificationController@markAllAsRead');
$router->get('/account/settings', '\App\Controllers\AuthController@accountSettings');
$router->post('/account/settings', '\App\Controllers\AuthController@updateAccountSettings');
$router->get('/logout', '\App\Controllers\AuthController@logout');
$router->get('/search/suggestions', '\App\Controllers\StaffController@searchSuggestions');
$router->get('/search', '\App\Controllers\StaffController@globalSearch');

// ------------------------------------------
// 🎓 STUDENT PORTAL ROUTES
// ------------------------------------------
$router->mount('/student', function () use ($router) {
    // The main timeline dashboard
    $router->get('/dashboard', '\App\Controllers\StudentController@dashboard');

    // The application form page
    $router->get('/apply', '\App\Controllers\StudentController@showForm');

    // View a submitted application record
    $router->get('/application/(\d+)', '\App\Controllers\StudentController@viewApplicationRecord');

    // Secure inline preview for student-owned uploaded files
    $router->get('/application/(\d+)/document/(\d+)', '\App\Controllers\StudentController@viewApplicationDocument');

    // Where the form data goes when they click "Submit"
    $router->post('/submit-application', '\App\Controllers\StudentController@submitApplication');

    // View past semesters
    $router->get('/history', '\App\Controllers\StudentController@history');

    $router->get('/print-form', '\App\Controllers\PrintableFormController@preview');
    $router->get('/print-form/pdf', '\App\Controllers\PrintableFormController@pdf');
});

// ------------------------------------------
// 📋 LGU STAFF ROUTES
// ------------------------------------------
$router->mount('/staff', function () use ($router) {
    $router->get('/dashboard', '\App\Controllers\StaffController@dashboard');
    $router->get('/applications', '\App\Controllers\StaffController@applications');
    $router->get('/verify-documents/(\d+)', '\App\Controllers\StaffController@verifyDocuments'); // The (\d+) catches the Application ID
    $router->get('/document-preview/(\d+)', '\App\Controllers\StaffController@documentPreview');
    $router->get('/document-version-preview/(\d+)', '\App\Controllers\StaffController@documentVersionPreview');
    $router->post('/update-document-status', '\App\Controllers\StaffController@updateDocumentStatus');
    $router->get('/batch-interview', function () {
        header('Location: ' . \base_url('admin/batch-interview'));
        exit;
    });
    $router->get('/batch-interview/export', function () {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $target = \base_url('admin/batch-interview/export');
        if ($query !== '') {
            $target .= '?' . $query;
        }
        header('Location: ' . $target);
        exit;
    });
    $router->get('/archive', '\App\Controllers\StaffController@archive');
    $router->get('/master-record/(\d+)', '\App\Controllers\StaffController@masterRecord');
    $router->post('/archive/toggle', '\App\Controllers\StaffController@toggleArchiveApplication');
    $router->get('/print-notice/(\d+)/([a-z-]+)', '\App\Controllers\StaffController@printNotice');
    $router->post('/create-batch', function () {
        header('Location: ' . \base_url('admin/batch-interview'));
        exit;
    });
    $router->post('/reschedule-batch', function () {
        header('Location: ' . \base_url('admin/batch-interview'));
        exit;
    });
    $router->post('/record-interview-result', function () {
        header('Location: ' . \base_url('admin/batch-interview'));
        exit;
    });
    $router->post('/add-case-note', '\App\Controllers\StaffController@addCaseNote');
    $router->get('/print-form', '\App\Controllers\PrintableFormController@preview');
    $router->get('/print-form/pdf', '\App\Controllers\PrintableFormController@pdf');
});

// ------------------------------------------
// 🏛️ LGU ADMIN ROUTES
// ------------------------------------------
$router->mount('/admin', function () use ($router) {
    $router->get('/dashboard', '\App\Controllers\AdminController@dashboard');
    $router->get('/batch-interview', '\App\Controllers\StaffController@batchInterview');
    $router->get('/batch-interview/export', '\App\Controllers\StaffController@exportInterviewList');
    $router->get('/reports', '\App\Controllers\ReportController@index');
    $router->get('/generate-payroll', '\App\Controllers\AdminController@generatePayroll');
    $router->get('/final-approval', '\App\Controllers\AdminController@finalApproval');
    $router->get('/final-approval/export', '\App\Controllers\AdminController@exportPayoutList');
    $router->get('/audit-logs', '\App\Controllers\AdminController@auditLogs');
    $router->get('/exceptions', '\App\Controllers\AdminController@exceptions');
    $router->get('/announcements', '\App\Controllers\AdminController@announcements');
    $router->post('/announcements', '\App\Controllers\AdminController@saveAnnouncement');
    $router->post('/announcements/toggle', '\App\Controllers\AdminController@toggleAnnouncement');
    $router->get('/users', '\App\Controllers\AdminController@users');
    $router->post('/users/create', '\App\Controllers\AdminController@createUser');
    $router->post('/users/status', '\App\Controllers\AdminController@updateUserStatus');
    $router->post('/users/reset-password', '\App\Controllers\AdminController@resetUserPassword');
    $router->get('/settings', '\App\Controllers\AdminController@settings');
    $router->post('/settings', '\App\Controllers\AdminController@saveSettings');
    $router->get('/account-recovery', '\App\Controllers\AdminController@accountRecovery');
    $router->get('/account-recovery/lookup', '\App\Controllers\AdminController@accountRecoveryLookup');
    $router->post('/account-recovery', '\App\Controllers\AdminController@processAccountRecovery');
    $router->post('/create-batch', '\App\Controllers\StaffController@createBatch');
    $router->post('/reschedule-batch', '\App\Controllers\StaffController@rescheduleInterviewBatch');
    $router->post('/record-interview-result', '\App\Controllers\StaffController@recordInterviewResult');
    $router->post('/create-payout-batch', '\App\Controllers\AdminController@createPayoutBatch');
    $router->post('/reschedule-payout-batch', '\App\Controllers\AdminController@reschedulePayoutBatch');
    $router->get('/print-form', '\App\Controllers\PrintableFormController@preview');
    $router->get('/print-form/pdf', '\App\Controllers\PrintableFormController@pdf');
});

// ==========================================
// ⚠️ 404 ERROR HANDLER
// ==========================================
$router->set404(function () {
    header('HTTP/1.1 404 Not Found');
    echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>";
    echo "<h1>404 - Page Not Found</h1>";
    echo "<p>The page you are looking for in the LGU Scholarship System does not exist.</p>";
    echo "<a href='" . htmlspecialchars(base_url(), ENT_QUOTES, 'UTF-8') . "'>Return to Homepage</a>";
    echo "</div>";
});

// 5. Run the Router!
$router->run();
