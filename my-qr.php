<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login('login.php');

$user = current_user();
$applicationId = (int) ($_GET['id'] ?? 0);

if ($applicationId <= 0) {
    set_flash('warning', 'Select a valid application first.');
    redirect('my-application.php');
}

$sql = "SELECT id, application_no, qr_token, first_name, last_name, school_year, scholarship_type, status
        FROM applications
        WHERE id = ?";
if (!user_has_role(['admin', 'staff'])) {
    $sql .= " AND user_id = " . (int) $user['id'];
}
$sql .= " LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $applicationId);
$stmt->execute();
$result = $stmt->get_result();
$application = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$application) {
    set_flash('danger', 'Application not found.');
    redirect('my-application.php');
}

$qrDataUri = qr_data_uri(application_qr_payload($application), 330, 10);
$pageTitle = 'My Application QR Code';

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-qrcode me-2 text-primary"></i>Full QR Code</h1>
    <div class="d-flex gap-2">
        <a href="my-application.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i>Print
        </button>
    </div>
</div>

<div class="card card-soft shadow-sm">
    <div class="card-body text-center p-4">
        <h2 class="h5 mb-1"><?= e((string) $application['application_no']) ?></h2>
        <p class="text-muted mb-3"><?= e((string) $application['scholarship_type']) ?> | <?= e((string) $application['school_year']) ?></p>

        <div class="d-flex justify-content-center">
            <div style="width:350px;height:350px;border:1px solid #c7dbea;padding:10px;background:#fff;display:flex;align-items:center;justify-content:center;">
                <?php if ($qrDataUri !== ''): ?>
                    <img src="<?= e($qrDataUri) ?>" alt="Application QR Code" style="width:330px;height:330px;">
                <?php else: ?>
                    <span class="small text-muted">QR library not available.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-3">
            <div class="small text-muted">Present this QR code during verification/disbursement.</div>
            <div class="small text-muted">Status: <strong><?= e(strtoupper((string) $application['status'])) ?></strong></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
