<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('login.php');

$applicationId = (int) ($_GET['id'] ?? 0);
$user = current_user();

if ($applicationId <= 0) {
    set_flash('warning', 'Invalid application reference.');
    redirect('my-application.php');
}

$sql = "SELECT a.*, u.email
        FROM applications a
        INNER JOIN users u ON u.id = a.user_id
        WHERE a.id = ?";
if (!user_has_role(['admin', 'staff'])) {
    $sql .= " AND a.user_id = " . (int) $user['id'];
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
    redirect(user_has_role(['admin', 'staff']) ? 'shared/applications.php' : 'my-application.php');
}

$stmtDocs = $conn->prepare("SELECT requirement_name, file_path FROM application_documents WHERE application_id = ? ORDER BY id ASC");
$stmtDocs->bind_param('i', $applicationId);
$stmtDocs->execute();
$docsResult = $stmtDocs->get_result();
$documents = $docsResult instanceof mysqli_result ? $docsResult->fetch_all(MYSQLI_ASSOC) : [];
$stmtDocs->close();

$siblings = json_array((string) ($application['siblings_json'] ?? ''));
$education = json_array((string) ($application['educational_background_json'] ?? ''));
$grants = json_array((string) ($application['grants_availed_json'] ?? ''));
$qrDataUri = qr_data_uri(application_qr_payload($application), 140, 4);
$headerLogoRelativePath = 'assets/images/branding/lgu-logo.png';
$headerLogoAbsolutePath = __DIR__ . '/' . $headerLogoRelativePath;
$headerLogoSrc = file_exists($headerLogoAbsolutePath) ? $headerLogoRelativePath : '';
$naValue = static function ($value): string {
    $text = trim((string) $value);
    return $text === '' ? 'N/A' : $text;
};
$addressLine = trim((string) ($application['address'] ?? ''));
$barangayLine = trim((string) ($application['barangay'] ?? ''));
$townLine = trim((string) ($application['town'] ?? san_enrique_town()));
$provinceLine = trim((string) ($application['province'] ?? san_enrique_province()));

$containsToken = static function (string $haystack, string $needle): bool {
    if ($haystack === '' || $needle === '') {
        return false;
    }
    return stripos($haystack, $needle) !== false;
};

$addressParts = [];
if ($addressLine !== '') {
    $addressParts[] = $addressLine;
}
if ($barangayLine !== '' && !$containsToken($addressLine, $barangayLine)) {
    $addressParts[] = $barangayLine;
}
if ($townLine !== '' && !$containsToken($addressLine, $townLine)) {
    $addressParts[] = $townLine;
}
if ($provinceLine !== '' && !$containsToken($addressLine, $provinceLine)) {
    $addressParts[] = $provinceLine;
}

$fullAddress = implode(', ', $addressParts);

$hideNavbar = true;
$hideFooter = true;
$bodyClass = 'bg-light print-preview-page';
$extraHead = <<<HTML
<style>
:root {
    --print-page-width: 8.5in;
    --print-page-height: 13in;
    --print-margin: 0in;
    --paper-width: calc(var(--print-page-width) - (var(--print-margin) * 2));
    --paper-height: calc(var(--print-page-height) - (var(--print-margin) * 2));
    --preview-scale: 1;
}
body.print-mode-with-margins {
    --print-margin: 0.25in;
}
@page {
    size: 8.5in 13in;
    margin: 0;
}
.print-toolbar {
    position: sticky;
    top: 0;
    z-index: 20;
}
.preview-meta {
    font-size: 0.82rem;
    color: #5f7380;
}
.preview-surface {
    border: 1px solid #cddce8;
    border-radius: 12px;
    padding: 0.8rem;
    background:
        linear-gradient(180deg, #f7fbff 0%, #f2f7fc 100%);
    overflow: auto;
}
.paper-scale-wrap {
    width: calc(var(--paper-width) * var(--preview-scale));
    min-height: calc(var(--paper-height) * var(--preview-scale));
    margin: 0 auto;
}
.application-paper {
    width: var(--paper-width);
    min-height: var(--paper-height);
    height: var(--paper-height);
    margin: 0;
    background: #fff;
    border: 1px solid #b7c9d8;
    padding: 0.2in 0.22in;
    color: #1d2d35;
    font-size: 11px;
    box-sizing: border-box;
    overflow: hidden;
    transform: scale(var(--preview-scale));
    transform-origin: top left;
    box-shadow: 0 14px 30px rgba(24, 74, 111, 0.18);
}
.paper-table {
    width: 100%;
    border-collapse: collapse;
}
.paper-table th,
.paper-table td {
    border: 1px solid #6d7d87;
    padding: 3px;
    vertical-align: top;
    line-height: 1.2;
    height: 20px;
}
.paper-title {
    color: #1a98d5;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
    text-shadow: 1px 1px 0 #ffb68a;
    font-size: 29px;
    line-height: 1;
    white-space: nowrap;
}
.paper-subtitle {
    font-weight: 700;
    font-size: 12px;
    margin-top: 1px;
    white-space: nowrap;
}
.section-title {
    font-weight: 800;
    margin: 6px 0 2px;
    font-size: 11px;
}
.qr-box {
    width: 1.4in;
    height: 1.4in;
    border: 1px solid #6d7d87;
    display: flex;
    align-items: center;
    justify-content: center;
}
.photo-box {
    width: 1.65in;
    height: 1.65in;
    border: 1px solid #6d7d87;
    text-align: center;
    overflow: hidden;
}
.photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.small-note {
    font-size: 9px;
}
.govt-header-line-1 {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.15;
}
.govt-header-line-2 {
    font-size: 12px;
    line-height: 1.1;
}
.govt-header-line-3 {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.05;
}
.govt-header-line-4 {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.1;
}
.program-heading-wrap {
    padding-top: 4px;
    text-align: left;
}
.govt-header-wrap {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
}
.govt-header-logo {
    width: 62px;
    height: 62px;
    object-fit: contain;
    flex: 0 0 auto;
}
.govt-header-text {
    text-align: left;
}
@media (max-width: 991.98px) {
    .print-toolbar {
        border-radius: 12px !important;
        padding: 0.65rem !important;
    }
    .print-toolbar .btn {
        min-width: 0;
    }
    .preview-surface {
        padding: 0.45rem;
    }
}
@media (max-width: 575.98px) {
    .print-toolbar .btn,
    .print-toolbar .btn-group {
        width: 100%;
    }
    .print-toolbar .btn-group .btn {
        flex: 1 1 auto;
    }
    .print-toolbar .d-flex {
        width: 100%;
        flex-wrap: wrap;
    }
    .preview-meta {
        font-size: 0.76rem;
    }
}
@media print {
    .print-toolbar { display: none !important; }
    .preview-meta { display: none !important; }
    .preview-surface {
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
        overflow: visible !important;
    }
    .paper-scale-wrap {
        width: auto !important;
        min-height: auto !important;
        margin: 0 !important;
    }
    body { background: #fff !important; }
    main.py-4 { padding: 0 !important; }
    .container { max-width: 100% !important; padding: 0 !important; }
    .application-paper {
        width: var(--paper-width) !important;
        height: var(--paper-height) !important;
        min-height: var(--paper-height) !important;
        border: none;
        margin: 0;
        box-shadow: none;
        transform: none !important;
        page-break-inside: avoid;
        break-inside: avoid;
    }
}
</style>
HTML;

$pageTitle = 'Printable Application Form';
include __DIR__ . '/includes/header.php';
?>

<div class="print-toolbar d-flex justify-content-between align-items-center flex-wrap gap-2 bg-white border rounded p-2 mb-2">
    <div class="small text-wrap">
        Application No: <strong><?= e((string) $application['application_no']) ?></strong>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <div class="btn-group btn-group-sm" role="group" aria-label="Preview zoom controls">
            <button type="button" class="btn btn-outline-secondary" id="zoomOutBtn" title="Zoom out">
                <i class="fa-solid fa-magnifying-glass-minus"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="fitWidthBtn" title="Fit to screen">
                Fit
            </button>
            <button type="button" class="btn btn-outline-secondary" id="zoomResetBtn" title="Reset zoom">
                100%
            </button>
            <button type="button" class="btn btn-outline-secondary" id="zoomInBtn" title="Zoom in">
                <i class="fa-solid fa-magnifying-glass-plus"></i>
            </button>
        </div>
        <span class="badge text-bg-light" id="previewScaleLabel">100%</span>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="marginModeBtn">
            Margins: Off
        </button>
        <a href="<?= user_has_role(['admin', 'staff']) ? 'shared/applications.php' : 'my-application.php' ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
        <button type="button" class="btn btn-primary btn-sm" id="printNowBtn">
            <i class="fa-solid fa-print me-1"></i>Print / Save PDF
        </button>
    </div>
</div>

<p class="preview-meta mb-2">
    Mobile tip: use <strong>Fit</strong> for full-page preview, then zoom in for details before printing.
</p>

<div class="preview-surface" id="previewSurface">
<div class="paper-scale-wrap" id="paperScaleWrap">
<div class="application-paper" id="applicationPaper">
    <table class="w-100 mb-2" style="border-collapse:collapse;">
        <tr>
            <td style="width:1.6in;vertical-align:top;">
                <div class="qr-box">
                    <?php if ($qrDataUri !== ''): ?>
                        <img src="<?= e($qrDataUri) ?>" alt="QR Code" style="width:125px;height:125px;">
                    <?php else: ?>
                        <span class="small-note">QR unavailable</span>
                    <?php endif; ?>
                </div>
                <div class="small-note mt-1 text-center">QR Reference</div>
            </td>
            <td style="vertical-align:top;padding-left:8px;">
                <div class="govt-header-wrap">
                    <?php if ($headerLogoSrc !== ''): ?>
                        <img src="<?= e($headerLogoSrc) ?>" alt="Municipality of San Enrique Logo" class="govt-header-logo">
                    <?php endif; ?>
                    <div class="govt-header-text">
                        <div class="govt-header-line-1">REPUBLIC OF THE PHILIPPINES</div>
                        <div class="govt-header-line-2">Province of Negros Occidental</div>
                        <div class="govt-header-line-3">MUNICIPALITY OF SAN ENRIQUE</div>
                        <div class="govt-header-line-4">OFFICE OF THE MAYOR</div>
                    </div>
                </div>
            </td>
            <td style="width:1.85in;vertical-align:top;" rowspan="2">
                <div class="photo-box">
                    <?php if (!empty($application['photo_path'])): ?>
                        <img src="<?= e((string) $application['photo_path']) ?>" alt="2x2 Photo">
                    <?php else: ?>
                        <div style="padding-top:38px;">2x2<br>Photo</div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="program-heading-wrap">
                <div class="paper-title">LGU SCHOLARSHIP PROGRAM</div>
                <div class="paper-subtitle">MUNICIPALITY OF SAN ENRIQUE, NEGROS OCCIDENTAL</div>
            </td>
        </tr>
    </table>

    <div class="section-title">PERSONAL INFORMATION</div>
    <table class="paper-table">
        <tr>
            <td style="width:14%"><strong>LAST NAME</strong></td>
            <td style="width:30%"><?= e($naValue($application['last_name'] ?? '')) ?></td>
            <td style="width:9%"><strong>AGE</strong></td>
            <td style="width:20%"><?= e($naValue($application['age'] ?? '')) ?></td>
            <td style="width:13%;text-align:center;"><strong>NEW</strong><br><span class="small-note">Please check (/)</span><br><?= ($application['applicant_type'] ?? '') === 'new' ? '✓' : '' ?></td>
            <td style="width:14%;text-align:center;"><strong>RE-NEW</strong><br><span class="small-note">Please check (/)</span><br><?= ($application['applicant_type'] ?? '') === 'renew' ? '✓' : '' ?></td>
        </tr>
        <tr>
            <td><strong>GIVEN NAME</strong></td>
            <td><?= e($naValue($application['first_name'] ?? '')) ?></td>
            <td><strong>CIVIL STATUS</strong></td>
            <td><?= e($naValue($application['civil_status'] ?? '')) ?></td>
            <td colspan="2">&nbsp;</td>
        </tr>
        <tr>
            <td><strong>MIDDLE NAME</strong></td>
            <td><?= e($naValue($application['middle_name'] ?? '')) ?></td>
            <td><strong>SEX</strong></td>
            <td colspan="3"><?= e($naValue($application['sex'] ?? '')) ?></td>
        </tr>
        <tr>
            <td><strong>DATE OF BIRTH</strong></td>
            <td><?= e($naValue($application['birth_date'] ?? '')) ?></td>
            <td><strong>CONTACT #</strong></td>
            <td colspan="3"><?= e($naValue($application['contact_number'] ?? '')) ?></td>
        </tr>
        <tr>
            <td><strong>PLACE OF BIRTH</strong></td>
            <td colspan="5"><?= e($naValue($application['birth_place'] ?? '')) ?></td>
        </tr>
        <tr>
            <td><strong>ADDRESS</strong></td>
            <td colspan="5"><?= e($naValue($fullAddress)) ?></td>
        </tr>
        <tr>
            <td><strong>SCHOOL</strong></td>
            <td colspan="3"><?= e($naValue($application['school_name'] ?? '')) ?></td>
            <td><strong>TYPE</strong></td>
            <td><?= e($naValue(strtoupper((string) ($application['school_type'] ?? '')))) ?></td>
        </tr>
        <tr>
            <td><strong>COURSE</strong></td>
            <td colspan="3"><?= e($naValue($application['course'] ?? '')) ?></td>
            <td><strong>SEM / SY</strong></td>
            <td><?= e($naValue($application['semester'] ?? '')) ?> / <?= e($naValue($application['school_year'] ?? '')) ?></td>
        </tr>
    </table>

    <div class="section-title">FAMILY BACKGROUND</div>
    <table class="paper-table">
        <tr>
            <td style="width:17%"><strong>MOTHER'S NAME</strong></td>
            <td style="width:33%"><?= e($naValue($application['mother_name'] ?? '')) ?></td>
            <td style="width:17%"><strong>FATHER'S NAME</strong></td>
            <td style="width:33%"><?= e($naValue($application['father_name'] ?? '')) ?></td>
        </tr>
        <tr>
            <td><strong>AGE</strong></td>
            <td><?= e($naValue($application['mother_age'] ?? '')) ?></td>
            <td><strong>AGE</strong></td>
            <td><?= e($naValue($application['father_age'] ?? '')) ?></td>
        </tr>
        <tr>
            <td><strong>OCCUPATION</strong></td>
            <td><?= e($naValue($application['mother_occupation'] ?? '')) ?></td>
            <td><strong>OCCUPATION</strong></td>
            <td><?= e($naValue($application['father_occupation'] ?? '')) ?></td>
        </tr>
        <tr>
            <td><strong>MONTHLY INCOME</strong></td>
            <td><?= e($naValue($application['mother_monthly_income'] ?? '')) ?></td>
            <td><strong>MONTHLY INCOME</strong></td>
            <td><?= e($naValue($application['father_monthly_income'] ?? '')) ?></td>
        </tr>
    </table>

    <div class="section-title">MEMBERS OF THE FAMILY (SIBLINGS)</div>
    <table class="paper-table">
        <tr>
            <th style="width:32%">Name</th>
            <th style="width:8%">Age</th>
            <th style="width:17%">Highest Educational Attainment</th>
            <th style="width:21%">Occupation</th>
            <th style="width:22%">Monthly Income</th>
        </tr>
        <?php for ($i = 0; $i < 5; $i++): ?>
            <?php $row = $siblings[$i] ?? ['name' => '', 'age' => '', 'education' => '', 'occupation' => '', 'income' => '']; ?>
            <tr>
                <td><?= e((string) ($row['name'] ?? '')) ?></td>
                <td><?= e((string) ($row['age'] ?? '')) ?></td>
                <td><?= e((string) ($row['education'] ?? '')) ?></td>
                <td><?= e((string) ($row['occupation'] ?? '')) ?></td>
                <td><?= e((string) ($row['income'] ?? '')) ?></td>
            </tr>
        <?php endfor; ?>
    </table>

    <div class="section-title">EDUCATIONAL BACKGROUND</div>
    <table class="paper-table">
        <tr>
            <th style="width:13%">Level</th>
            <th style="width:43%">School</th>
            <th style="width:16%">Year</th>
            <th style="width:28%">Honors/Awards</th>
        </tr>
        <?php
        $defaultRows = [
            ['level' => 'Elementary', 'school' => '', 'year' => '', 'honors' => ''],
            ['level' => 'High School', 'school' => '', 'year' => '', 'honors' => ''],
            ['level' => 'College', 'school' => '', 'year' => '', 'honors' => ''],
            ['level' => 'Course', 'school' => '', 'year' => '', 'honors' => ''],
        ];
        ?>
        <?php for ($i = 0; $i < 4; $i++): ?>
            <?php $row = $education[$i] ?? $defaultRows[$i]; ?>
            <tr>
                <td><?= e((string) ($row['level'] ?? '')) ?></td>
                <td><?= e((string) ($row['school'] ?? '')) ?></td>
                <td><?= e((string) ($row['year'] ?? '')) ?></td>
                <td><?= e((string) ($row['honors'] ?? '')) ?></td>
            </tr>
        <?php endfor; ?>
    </table>

    <div class="section-title">SCHOLARSHIP GRANTS AVAILED</div>
    <table class="paper-table">
        <tr>
            <th style="width:58%">Scholarship Program</th>
            <th style="width:42%">Year/Period</th>
        </tr>
        <?php for ($i = 0; $i < 3; $i++): ?>
            <?php $row = $grants[$i] ?? ['program' => '', 'period' => '']; ?>
            <tr>
                <td><?= e((string) ($row['program'] ?? '')) ?></td>
                <td><?= e((string) ($row['period'] ?? '')) ?></td>
            </tr>
        <?php endfor; ?>
    </table>

    <div class="d-flex justify-content-end mt-4">
        <div style="width:2.8in;text-align:center;">
            <div style="border-bottom:1px solid #000;height:24px;"></div>
            <div style="font-weight:700;margin-top:4px;">Signature of Applicant</div>
        </div>
    </div>

    <table class="paper-table mt-4">
        <tr>
            <td style="width:35%">
                <strong>FB PAGE:</strong><br>
                <span style="color:#1a98d5;font-weight:700;">LGU-SAN ENRIQUE SCHOLARS</span>
            </td>
            <td style="width:65%">
                <strong style="color:#ca6640;">PLEASE ATTACH THE FOLLOWING DOCUMENTS:</strong>
                <ul style="margin:3px 0 0 16px;padding:0;">
                    <?php if ($documents): ?>
                        <?php foreach ($documents as $doc): ?>
                            <li><?= e((string) $doc['requirement_name']) ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>Report Card / Previous Semester (Photocopy)</li>
                        <li>1 pc 2x2 Picture</li>
                        <li>Barangay Residency</li>
                        <li>Original Student's Copy / Statement of Account (SOA)</li>
                    <?php endif; ?>
                </ul>
            </td>
        </tr>
    </table>
</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const surface = document.getElementById('previewSurface');
    const paper = document.getElementById('applicationPaper');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomResetBtn = document.getElementById('zoomResetBtn');
    const fitWidthBtn = document.getElementById('fitWidthBtn');
    const scaleLabel = document.getElementById('previewScaleLabel');
    const marginModeBtn = document.getElementById('marginModeBtn');
    const printNowBtn = document.getElementById('printNowBtn');
    if (!surface || !paper) {
        return;
    }

    const minScale = 0.55;
    const maxScale = 1.25;
    const step = 0.05;
    let scale = 1;
    let fitMode = window.innerWidth < 992;

    const setScale = function (nextScale) {
        scale = Math.max(minScale, Math.min(maxScale, nextScale));
        document.documentElement.style.setProperty('--preview-scale', String(scale));
        if (scaleLabel) {
            scaleLabel.textContent = Math.round(scale * 100) + '%';
        }
        if (zoomOutBtn) {
            zoomOutBtn.disabled = scale <= minScale + 0.001;
        }
        if (zoomInBtn) {
            zoomInBtn.disabled = scale >= maxScale - 0.001;
        }
    };

    let marginsEnabled = false;
    let printPageStyle = null;
    const syncMarginModeUi = function () {
        document.body.classList.toggle('print-mode-with-margins', marginsEnabled);
        if (marginModeBtn) {
            marginModeBtn.textContent = marginsEnabled ? 'Margins: On' : 'Margins: Off';
        }
    };
    const applyPrintPageRule = function () {
        if (printPageStyle && printPageStyle.parentNode) {
            printPageStyle.parentNode.removeChild(printPageStyle);
            printPageStyle = null;
        }
        printPageStyle = document.createElement('style');
        printPageStyle.setAttribute('data-print-page-rule', '1');
        printPageStyle.textContent = marginsEnabled
            ? '@media print { @page { size: 8.5in 13in; margin: 0.25in; } }'
            : '@media print { @page { size: 8.5in 13in; margin: 0; } }';
        document.head.appendChild(printPageStyle);
    };

    const fitToWidth = function () {
        const paperWidth = paper.offsetWidth || 816;
        const available = Math.max(320, surface.clientWidth - 8);
        const fitted = Math.min(1, available / paperWidth);
        setScale(fitted);
    };

    if (fitMode) {
        fitToWidth();
    } else {
        setScale(1);
    }

    syncMarginModeUi();
    applyPrintPageRule();

    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function () {
            fitMode = false;
            setScale(scale - step);
        });
    }
    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function () {
            fitMode = false;
            setScale(scale + step);
        });
    }
    if (zoomResetBtn) {
        zoomResetBtn.addEventListener('click', function () {
            fitMode = false;
            setScale(1);
        });
    }
    if (fitWidthBtn) {
        fitWidthBtn.addEventListener('click', function () {
            fitMode = true;
            fitToWidth();
        });
    }
    if (marginModeBtn) {
        marginModeBtn.addEventListener('click', function () {
            marginsEnabled = !marginsEnabled;
            syncMarginModeUi();
            applyPrintPageRule();
            if (fitMode) {
                fitToWidth();
            }
        });
    }
    if (printNowBtn) {
        printNowBtn.addEventListener('click', function () {
            applyPrintPageRule();
            window.print();
        });
    }
    window.addEventListener('beforeprint', applyPrintPageRule);

    window.addEventListener('resize', function () {
        if (fitMode) {
            fitToWidth();
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
