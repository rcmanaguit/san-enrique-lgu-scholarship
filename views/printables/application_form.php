<?php
use App\Config\Database;

require __DIR__ . '/../layouts/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . base_url('login'));
    exit;
}

$appId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$appId) {
    redirect_with_flash('student/dashboard', 'error', 'Application ID is required to print the form.');
}

$db = Database::connect();

$stmt = $db->prepare("
    SELECT
        a.*,
        p.*,
        u.email,
        u.phone_number
    FROM applications a
    JOIN student_profiles p ON a.student_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $appId]);
$application = $stmt->fetch();

if (!$application) {
    redirect_with_flash('student/dashboard', 'error', 'Application not found.');
}

if ($_SESSION['role'] === 'Student' && (int) $application['user_id'] !== (int) $_SESSION['user_id']) {
    redirect_with_flash('student/dashboard', 'error', 'You are not allowed to print this application.');
}

$documentsStmt = $db->prepare("
    SELECT document_type, file_path, status, rejection_remarks, uploaded_at
    FROM documents
    WHERE application_id = :application_id
    ORDER BY id ASC
");
$documentsStmt->execute(['application_id' => $appId]);
$documents = $documentsStmt->fetchAll();

$toDataUri = static function (?string $path): string {
    $normalizedPath = trim((string) $path);
    if ($normalizedPath === '' || !is_file($normalizedPath)) {
        return '';
    }

    $mimeType = mime_content_type($normalizedPath) ?: 'application/octet-stream';
    $binary = file_get_contents($normalizedPath);
    if ($binary === false) {
        return '';
    }

    return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
};

$na = static function ($value): string {
    $normalized = trim((string) $value);
    return $normalized === '' ? 'N/A' : $normalized;
};

$formatDate = static function (?string $value, string $fallback = 'N/A'): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return $fallback;
    }

    try {
        return (new DateTimeImmutable($normalized))->format('F d, Y');
    } catch (Throwable $exception) {
        return $normalized;
    }
};

$calculateAge = static function (?string $dateOfBirth): string {
    $normalized = trim((string) $dateOfBirth);
    if ($normalized === '') {
        return '';
    }

    try {
        $birthDate = new DateTimeImmutable($normalized);
        $today = new DateTimeImmutable('today');
        return (string) $birthDate->diff($today)->y;
    } catch (Throwable $exception) {
        return '';
    }
};

$formatMoney = static function ($value): string {
    if ($value === null || $value === '') {
        return 'N/A';
    }

    return 'PHP ' . number_format((float) $value, 2);
};

$findDocument = static function (array $documents, string $type): ?array {
    foreach ($documents as $document) {
        if (($document['document_type'] ?? '') === $type) {
            return $document;
        }
    }

    return null;
};

$documentStatusLabel = static function (?array $document): string {
    if (!$document) {
        return 'Not Submitted';
    }

    $status = trim((string) ($document['status'] ?? ''));
    return $status === '' ? 'Submitted' : str_replace('_', ' ', $status);
};
$siblings = json_decode((string) ($application['siblings_json'] ?? '[]'), true);
if (!is_array($siblings)) {
    $siblings = [];
}
$education = json_decode((string) ($application['education_json'] ?? '[]'), true);
if (!is_array($education)) {
    $education = [];
}
$grants = json_decode((string) ($application['grants_json'] ?? '[]'), true);
if (!is_array($grants)) {
    $grants = [];
}

$applicationNumber = 'SELGU-APP-' . date('Y', strtotime((string) $application['created_at'])) . '-' . str_pad((string) $application['id'], 5, '0', STR_PAD_LEFT);
$fullName = trim(
    trim((string) ($application['last_name'] ?? '')) . ', ' .
    trim((string) ($application['first_name'] ?? '')) .
    (!empty($application['middle_name']) ? ' ' . strtoupper(substr((string) $application['middle_name'], 0, 1)) . '.' : '') .
    (!empty($application['suffix']) ? ' ' . trim((string) $application['suffix']) : '')
);
$fullAddress = $na($application['address_line'] ?? '') . ', Brgy. ' . $na($application['address_barangay'] ?? '') . ', San Enrique, Negros Occidental';
$calculatedAge = $calculateAge($application['date_of_birth'] ?? '');
$photoSrc = $toDataUri($application['id_picture_path'] ?? '');
$signatureSrc = $toDataUri($application['e_signature_path'] ?? '');
$gradesDocument = $findDocument($documents, 'Grades');
$residencyDocument = $findDocument($documents, 'Residency');
$soaDocument = $findDocument($documents, 'SOA');
$backUrl = $_SESSION['role'] === 'Admin'
    ? 'admin/dashboard'
    : ($_SESSION['role'] === 'Staff' ? 'staff/dashboard' : 'student/dashboard');
?>

<style>
@page {
    size: 8.5in 13in;
    margin: 0.28in;
}

body {
    background: #eef3f7;
}

.print-page-shell {
    max-width: 8.5in;
    margin: 0 auto 2rem;
}

.print-toolbar {
    position: sticky;
    top: 0;
    z-index: 10;
}

.physical-form {
    width: 100%;
    background: #fff;
    color: #15232d;
    border: 1px solid #b8c8d4;
    box-shadow: 0 12px 30px rgba(19, 46, 67, 0.14);
    padding: 0.16in 0.18in 0.2in;
    font-family: "Manrope", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    font-size: 10.5px;
    line-height: 1.18;
}

.paper-table {
    width: 100%;
    border-collapse: collapse;
}

.paper-table th,
.paper-table td {
    border: 1px solid #667884;
    padding: 3px 4px;
    vertical-align: top;
}

.paper-table th {
    font-weight: 700;
}

.paper-free {
    border: none !important;
    padding: 0 !important;
}

.gov-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.gov-logo {
    width: 62px;
    height: 62px;
    object-fit: contain;
    flex: 0 0 auto;
}

.gov-line-1 {
    font-size: 13px;
}

.gov-line-2 {
    font-size: 11px;
}

.gov-line-3 {
    font-size: 21px;
    font-weight: 800;
    line-height: 1.02;
}

.gov-line-4 {
    font-size: 12px;
    font-weight: 700;
}

.program-title {
    margin-top: 4px;
    color: #1696d2;
    font-family: "Sora", "Times New Roman", serif;
    font-size: 27px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    text-shadow: 1px 1px 0 #ffc296;
}

.program-subtitle {
    font-size: 11px;
    font-weight: 700;
}

.photo-box {
    width: 2in;
    height: 2in;
    border: 1px solid #667884;
    margin-left: auto;
    overflow: hidden;
    text-align: center;
    position: relative;
}

.photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-box-note {
    position: absolute;
    inset: auto 0 0 0;
    background: rgba(255, 255, 255, 0.9);
    border-top: 1px solid #c4d1d8;
    font-size: 8px;
    padding: 2px 0;
}

.section-bar {
    background: #dfeef7;
    font-weight: 800;
    letter-spacing: 0.2px;
}

.muted-note {
    font-size: 8.8px;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 4px 8px;
}

.checkbox-line {
    white-space: nowrap;
}

.signature-row {
    display: flex;
    gap: 18px;
    justify-content: space-between;
    margin-top: 18px;
}

.signature-block {
    width: 31%;
    text-align: center;
}

.signature-line {
    position: relative;
    height: 36px;
    border-bottom: 1px solid #000;
}

.signature-line img {
    position: absolute;
    left: 50%;
    bottom: 2px;
    transform: translateX(-50%);
    max-width: 2.25in;
    max-height: 0.7in;
    object-fit: contain;
}

.sign-role {
    font-weight: 700;
    margin-top: 4px;
}

.sign-caption {
    font-size: 8.8px;
}

.office-box {
    min-height: 50px;
}

@media print {
    .no-print,
    .sidebar,
    .notification-bell-button,
    .notification-dropdown,
    footer {
        display: none !important;
    }

    body {
        background: #fff !important;
    }

    .print-page-shell {
        max-width: none;
        margin: 0;
    }

    .physical-form {
        border: none;
        box-shadow: none;
        padding: 0;
    }
}
</style>

<div class="no-print print-toolbar bg-white border rounded p-2 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="small">
        Application No: <strong><?php echo htmlspecialchars($applicationNumber); ?></strong>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo htmlspecialchars(base_url($backUrl)); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i>Print / Save PDF
        </button>
    </div>
</div>

<div class="print-page-shell">
    <div class="physical-form">
        <table class="paper-table" style="margin-bottom: 4px;">
            <tr>
                <td class="paper-free" style="width: 76%;">
                    <div class="gov-header">
                        <img class="gov-logo" src="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>" alt="San Enrique LGU Logo">
                        <div>
                            <div class="gov-line-1">Republic of the Philippines</div>
                            <div class="gov-line-2">Province of Negros Occidental</div>
                            <div class="gov-line-3">MUNICIPALITY OF SAN ENRIQUE</div>
                            <div class="gov-line-4">Office of the Municipal Mayor</div>
                            <div class="program-title">LGU Scholarship Program</div>
                            <div class="program-subtitle">Official Scholarship Application Form</div>
                        </div>
                    </div>
                </td>
                <td class="paper-free" style="width: 24%;">
                    <div class="photo-box">
                        <?php if ($photoSrc !== ''): ?>
                            <img src="<?php echo htmlspecialchars($photoSrc); ?>" alt="2x2 Applicant Photo">
                        <?php else: ?>
                            <div style="padding-top: 48px; font-weight: 700;">2 x 2 PHOTO</div>
                        <?php endif; ?>
                        <div class="photo-box-note">Exact 2 x 2 inches</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="paper-table">
            <tr>
                <td style="width: 15%;"><strong>Application No.</strong></td>
                <td style="width: 20%;"><?php echo htmlspecialchars($applicationNumber); ?></td>
                <td style="width: 15%;"><strong>Control No.</strong></td>
                <td style="width: 20%;"><?php echo htmlspecialchars($applicationNumber); ?></td>
                <td style="width: 12%;"><strong>Date Filed</strong></td>
                <td style="width: 18%;"><?php echo htmlspecialchars($formatDate($application['created_at'] ?? '')); ?></td>
            </tr>
            <tr>
                <td><strong>Semester</strong></td>
                <td><?php echo htmlspecialchars($na($application['semester'] ?? '')); ?></td>
                <td><strong>School Year</strong></td>
                <td><?php echo htmlspecialchars($na($application['school_year'] ?? '')); ?></td>
                <td><strong>Status</strong></td>
                <td><?php echo htmlspecialchars(str_replace('_', ' ', $na($application['status'] ?? ''))); ?></td>
            </tr>
            <tr>
                <td><strong>Application Type</strong></td>
                <td><?php echo htmlspecialchars($na($application['application_type'] ?? '')); ?></td>
                <td><strong>Applicant Type</strong></td>
                <td><?php echo htmlspecialchars($na($application['application_type'] ?? '')); ?></td>
                <td><strong>Age</strong></td>
                <td><?php echo htmlspecialchars($na($calculatedAge)); ?></td>
            </tr>
        </table>

        <table class="paper-table" style="margin-top: 6px;">
            <tr class="section-bar">
                <td colspan="6">I. PERSONAL INFORMATION</td>
            </tr>
            <tr>
                <td style="width: 14%;"><strong>Last Name</strong></td>
                <td style="width: 24%;"><?php echo htmlspecialchars($na($application['last_name'] ?? '')); ?></td>
                <td style="width: 14%;"><strong>First Name</strong></td>
                <td style="width: 24%;"><?php echo htmlspecialchars($na($application['first_name'] ?? '')); ?></td>
                <td style="width: 10%;"><strong>Suffix</strong></td>
                <td style="width: 14%;"><?php echo htmlspecialchars($na($application['suffix'] ?? '')); ?></td>
            </tr>
            <tr>
                <td><strong>Middle Name</strong></td>
                <td><?php echo htmlspecialchars($na($application['middle_name'] ?? '')); ?></td>
                <td><strong>Sex</strong></td>
                <td><?php echo htmlspecialchars($na($application['sex'] ?? '')); ?></td>
                <td><strong>Age</strong></td>
                <td><?php echo htmlspecialchars($na($calculatedAge)); ?></td>
            </tr>
            <tr>
                <td><strong>Date of Birth</strong></td>
                <td><?php echo htmlspecialchars($formatDate($application['date_of_birth'] ?? '')); ?></td>
                <td><strong>Civil Status</strong></td>
                <td><?php echo htmlspecialchars($na($application['civil_status'] ?? '')); ?></td>
                <td><strong>Contact No.</strong></td>
                <td><?php echo htmlspecialchars($na($application['phone_number'] ?? '')); ?></td>
            </tr>
            <tr>
                <td><strong>Place of Birth</strong></td>
                <td colspan="3"><?php echo htmlspecialchars($na($application['place_of_birth'] ?? '')); ?></td>
                <td><strong>Barangay</strong></td>
                <td><?php echo htmlspecialchars($na($application['address_barangay'] ?? '')); ?></td>
            </tr>
            <tr>
                <td><strong>Email Address</strong></td>
                <td colspan="3"><?php echo htmlspecialchars($na($application['email'] ?? '')); ?></td>
                <td><strong>Municipality</strong></td>
                <td>San Enrique</td>
            </tr>
            <tr>
                <td><strong>House No./Street/Purok</strong></td>
                <td colspan="5"><?php echo htmlspecialchars($na($application['address_line'] ?? '')); ?></td>
            </tr>
            <tr>
                <td><strong>Province</strong></td>
                <td><?php echo htmlspecialchars('Negros Occidental'); ?></td>
                <td><strong>Residence Address</strong></td>
                <td colspan="3"><?php echo htmlspecialchars($fullAddress); ?></td>
            </tr>
        </table>

        <table class="paper-table" style="margin-top: 6px;">
            <tr class="section-bar">
                <td colspan="4">II. FAMILY BACKGROUND</td>
            </tr>
            <tr>
                <td style="width: 17%;"><strong>Mother's Name</strong></td>
                <td style="width: 33%;"><?php echo htmlspecialchars($na($application['mother_name'] ?? '')); ?></td>
                <td style="width: 17%;"><strong>Father's Name</strong></td>
                <td style="width: 33%;"><?php echo htmlspecialchars($na($application['father_name'] ?? '')); ?></td>
            </tr>
            <tr>
                <td><strong>Occupation</strong></td>
                <td><?php echo htmlspecialchars($na($application['mother_occupation'] ?? '')); ?></td>
                <td><strong>Occupation</strong></td>
                <td><?php echo htmlspecialchars($na($application['father_occupation'] ?? '')); ?></td>
            </tr>
            <tr>
                <td><strong>Monthly Income</strong></td>
                <td><?php echo htmlspecialchars($formatMoney($application['mother_monthly_income'] ?? null)); ?></td>
                <td><strong>Monthly Income</strong></td>
                <td><?php echo htmlspecialchars($formatMoney($application['father_monthly_income'] ?? null)); ?></td>
            </tr>
        </table>

        <table class="paper-table" style="margin-top: 6px;">
            <tr class="section-bar">
                <td colspan="5">III. SIBLINGS</td>
            </tr>
            <tr>
                <td style="width: 30%;"><strong>Name</strong></td>
                <td style="width: 10%;"><strong>Age</strong></td>
                <td style="width: 24%;"><strong>Highest Educational Attainment</strong></td>
                <td style="width: 20%;"><strong>Occupation</strong></td>
                <td style="width: 16%;"><strong>Monthly Income</strong></td>
            </tr>
            <?php for ($i = 0; $i < 5; $i++): ?>
                <?php $sibling = $siblings[$i] ?? ['name' => '', 'age' => '', 'education' => '', 'occupation' => '', 'income' => '']; ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($sibling['name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($sibling['age'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($sibling['education'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($sibling['occupation'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($sibling['income'] ?? '')); ?></td>
                </tr>
            <?php endfor; ?>
        </table>

        <table class="paper-table" style="margin-top: 6px;">
            <tr class="section-bar">
                <td colspan="4">IV. EDUCATIONAL BACKGROUND</td>
            </tr>
            <tr>
                <td style="width: 13%;"><strong>Level</strong></td>
                <td style="width: 43%;"><strong>School</strong></td>
                <td style="width: 16%;"><strong>Year</strong></td>
                <td style="width: 28%;"><strong>Honors / Awards</strong></td>
            </tr>
            <?php
            $defaultEducationRows = [
                ['level' => 'Elementary', 'school' => '', 'year' => '', 'honors' => ''],
                ['level' => 'High School', 'school' => '', 'year' => '', 'honors' => ''],
                ['level' => 'College', 'school' => $application['school_name'] ?? '', 'year' => $application['year_level'] ?? '', 'honors' => '', 'course' => $application['course'] ?? ''],
                ['level' => 'Course', 'school' => $application['course'] ?? '', 'year' => '', 'honors' => ''],
            ];
            ?>
            <?php for ($i = 0; $i < 4; $i++): ?>
                <?php $row = $education[$i] ?? $defaultEducationRows[$i]; ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['level'] ?? '')); ?></td>
                    <?php if (strtolower(trim((string) ($row['level'] ?? ''))) === 'course'): ?>
                        <td colspan="3"><?php echo htmlspecialchars($na($row['school'] ?? $application['course'] ?? '')); ?></td>
                    <?php else: ?>
                        <td><?php echo htmlspecialchars((string) ($row['school'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['year'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['honors'] ?? '')); ?></td>
                    <?php endif; ?>
                </tr>
            <?php endfor; ?>
        </table>

        <table class="paper-table" style="margin-top: 6px;">
            <tr class="section-bar">
                <td colspan="2">V. SCHOLARSHIP GRANTS AVAILED</td>
            </tr>
            <tr>
                <td style="width: 58%;"><strong>Scholarship Program</strong></td>
                <td style="width: 42%;"><strong>Year / Period</strong></td>
            </tr>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <?php $grant = $grants[$i] ?? ['program' => '', 'period' => '']; ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($grant['program'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($grant['period'] ?? '')); ?></td>
                </tr>
            <?php endfor; ?>
        </table>

        <table class="paper-table" style="margin-top: 6px;">
            <tr class="section-bar">
                <td colspan="4">VI. REQUIREMENTS / ATTACHMENTS</td>
            </tr>
            <tr>
                <td style="width: 34%;"><strong>Document</strong></td>
                <td style="width: 18%;"><strong>Status</strong></td>
                <td style="width: 18%;"><strong>Date Uploaded</strong></td>
                <td style="width: 30%;"><strong>Remarks</strong></td>
            </tr>
            <tr>
                <td>Report Card / Copy of Grades</td>
                <td><?php echo htmlspecialchars($documentStatusLabel($gradesDocument)); ?></td>
                <td><?php echo htmlspecialchars($formatDate($gradesDocument['uploaded_at'] ?? '', '')); ?></td>
                <td><?php echo htmlspecialchars($na($gradesDocument['rejection_remarks'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>Barangay Residency Certification</td>
                <td><?php echo htmlspecialchars($documentStatusLabel($residencyDocument)); ?></td>
                <td><?php echo htmlspecialchars($formatDate($residencyDocument['uploaded_at'] ?? '', '')); ?></td>
                <td><?php echo htmlspecialchars($na($residencyDocument['rejection_remarks'] ?? '')); ?></td>
            </tr>
            <tr>
                <td>Statement of Account / Assessment</td>
                <td><?php echo htmlspecialchars($documentStatusLabel($soaDocument)); ?></td>
                <td><?php echo htmlspecialchars($formatDate($soaDocument['uploaded_at'] ?? '', '')); ?></td>
                <td><?php echo htmlspecialchars($na($soaDocument['rejection_remarks'] ?? '')); ?></td>
            </tr>
        </table>

        <table class="paper-table" style="margin-top: 6px;">
            <tr class="section-bar">
                <td colspan="2">VII. ELIGIBILITY CHECKLIST</td>
            </tr>
            <tr>
                <td style="width: 56%;">
                    <div class="checkbox-grid">
                        <div class="checkbox-line">[ ] Resident of San Enrique</div>
                        <div class="checkbox-line">[ ] College Student</div>
                        <div class="checkbox-line">[ ] Application Complete</div>
                        <div class="checkbox-line">[ ] Grades Reviewed</div>
                        <div class="checkbox-line">[ ] Residency Verified</div>
                        <div class="checkbox-line">[ ] SOA Reviewed</div>
                    </div>
                </td>
                <td style="width: 44%;">
                    <strong>For Office Use:</strong>
                    <div class="office-box"></div>
                </td>
            </tr>
        </table>

        <table class="paper-table" style="margin-top: 6px;">
            <tr class="section-bar">
                <td colspan="2">VIII. APPLICANT CERTIFICATION</td>
            </tr>
            <tr>
                <td colspan="2">
                    I hereby certify that all information stated in this application form and all attached documents are true, correct, and complete to the best of my knowledge. I understand that any false statement or falsified document shall be sufficient ground for disqualification from the LGU Scholarship Program.
                </td>
            </tr>
        </table>

        <div class="signature-row">
            <div class="signature-block">
                <div class="signature-line">
                    <?php if ($signatureSrc !== ''): ?>
                        <img src="<?php echo htmlspecialchars($signatureSrc); ?>" alt="Applicant Signature">
                    <?php endif; ?>
                </div>
                <div class="sign-role">Signature of Applicant</div>
                <div class="sign-caption"><?php echo htmlspecialchars($na($fullName)); ?></div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="sign-role">Checked By</div>
                <div class="sign-caption">Scholarship Staff / Processor</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="sign-role">Verified By</div>
                <div class="sign-caption">Scholarship Coordinator / Authorized Officer</div>
            </div>
        </div>

        <table class="paper-table" style="margin-top: 12px;">
            <tr class="section-bar">
                <td colspan="4">IX. OFFICE USE ONLY</td>
            </tr>
            <tr>
                <td style="width: 18%;"><strong>Received By</strong></td>
                <td style="width: 32%;"></td>
                <td style="width: 18%;"><strong>Date Received</strong></td>
                <td style="width: 32%;"></td>
            </tr>
            <tr>
                <td><strong>Interview Schedule</strong></td>
                <td></td>
                <td><strong>Final Action</strong></td>
                <td></td>
            </tr>
            <tr>
                <td><strong>Remarks</strong></td>
                <td colspan="3" style="height: 46px;"></td>
            </tr>
        </table>

        <div class="muted-note" style="margin-top: 10px;">
            Official Facebook Page: LGU-San Enrique Scholars
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
