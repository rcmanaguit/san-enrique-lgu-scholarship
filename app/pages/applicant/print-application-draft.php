<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_login('login.php');
require_role(['applicant'], 'index.php');

$wizard = wizard_state();
if (!(bool) ($wizard['step1_done'] ?? false) || !(bool) ($wizard['step2_done'] ?? false) || !(bool) ($wizard['step3_done'] ?? false)) {
    set_flash('warning', 'Please complete Steps 1 to 3 first.');
    redirect('apply.php?step=1');
}

$step1 = is_array($wizard['step1'] ?? null) ? $wizard['step1'] : [];
$step2 = is_array($wizard['step2'] ?? null) ? $wizard['step2'] : [];
$step3 = is_array($wizard['step3'] ?? null) ? $wizard['step3'] : [];
$photoPath = trim((string) ($wizard['photo_path'] ?? ''));
$documents = is_array($wizard['documents'] ?? null) ? $wizard['documents'] : [];
$motherDraftLabel = !empty($step3['mother_na']) ? 'N/A' : (!empty($step3['mother_deceased']) ? 'Deceased' : (string) ($step3['mother_name'] ?? 'N/A'));
$fatherDraftLabel = !empty($step3['father_na']) ? 'N/A' : (!empty($step3['father_deceased']) ? 'Deceased' : (string) ($step3['father_name'] ?? 'N/A'));

$title = 'Printable Form Preview (Draft)';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f8fb; margin:0; color:#1a2b35; }
        .wrap { max-width: 980px; margin: 16px auto; background:#fff; border:1px solid #d9e4ec; border-radius:10px; padding:16px; }
        .head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border-bottom:1px solid #e4edf3; padding-bottom:10px; margin-bottom:12px; }
        .head h1 { margin:0; font-size:20px; }
        .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; background:#eef6ff; color:#1e5f8c; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .card { border:1px solid #e1ebf2; border-radius:8px; padding:10px; background:#fff; }
        .card h2 { margin:0 0 8px; font-size:14px; }
        .kv { margin:0; font-size:13px; line-height:1.55; }
        .kv strong { display:inline-block; min-width:150px; color:#385365; }
        .photo { width:2in; height:2in; border:1px solid #b7c9d8; border-radius:4px; overflow:hidden; background:#fafcfe; display:flex; align-items:center; justify-content:center; }
        .photo img { width:100%; height:100%; object-fit:cover; }
        .list { margin:0; padding-left:16px; font-size:13px; }
        .muted { color:#678192; font-size:12px; }
        @media (max-width: 768px) { .grid { grid-template-columns:1fr; } .kv strong { min-width:120px; } }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <div>
                <h1>LGU Scholarship Application Form</h1>
                <div class="muted">Draft preview before final submission</div>
            </div>
            <span class="badge">Preview Only</span>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Program Details</h2>
                <p class="kv"><strong>Applicant Type:</strong> <?= e((string) ($step1['applicant_type'] ?? '')) ?></p>
                <p class="kv"><strong>Semester:</strong> <?= e((string) ($step1['semester'] ?? '')) ?></p>
                <p class="kv"><strong>School Year:</strong> <?= e((string) ($step1['school_year'] ?? '')) ?></p>
                <p class="kv"><strong>School:</strong> <?= e((string) ($step1['school_name'] ?? '')) ?></p>
                <p class="kv"><strong>School Type:</strong> <?= e((string) ($step1['school_type'] ?? '')) ?></p>
                <p class="kv"><strong>Course:</strong> <?= e((string) ($step1['course'] ?? '')) ?></p>
            </div>
            <div class="card">
                <h2>2x2 Photo</h2>
                <div class="photo">
                    <?php if ($photoPath !== ''): ?>
                        <img src="<?= e($photoPath) ?>" alt="2x2 Photo">
                    <?php else: ?>
                        <span class="muted">No photo uploaded yet</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid" style="margin-top:12px;">
            <div class="card">
                <h2>Personal Information</h2>
                <p class="kv"><strong>Name:</strong> <?= e(trim((string) (($step2['last_name'] ?? '') . ', ' . ($step2['first_name'] ?? '') . ' ' . ($step2['middle_name'] ?? '') . ' ' . ($step2['suffix'] ?? '')))) ?></p>
                <p class="kv"><strong>Birth Date:</strong> <?= e((string) ($step2['birth_date'] ?? '')) ?></p>
                <p class="kv"><strong>Sex:</strong> <?= e((string) ($step2['sex'] ?? '')) ?></p>
                <p class="kv"><strong>Civil Status:</strong> <?= e((string) ($step2['civil_status'] ?? '')) ?></p>
                <p class="kv"><strong>Contact:</strong> <?= e((string) ($step2['contact_number'] ?? '')) ?></p>
                <p class="kv"><strong>Address:</strong> <?= e(trim((string) (($step2['address'] ?? '') . ', ' . ($step2['barangay'] ?? '') . ', ' . ($step2['town'] ?? '') . ', ' . ($step2['province'] ?? '')), ', ')) ?></p>
            </div>
            <div class="card">
                <h2>Family Information</h2>
                <p class="kv"><strong>Mother:</strong> <?= e($motherDraftLabel) ?></p>
                <p class="kv"><strong>Father:</strong> <?= e($fatherDraftLabel) ?></p>
                <p class="kv"><strong>Siblings:</strong> <?= e((string) count((array) ($step3['siblings'] ?? []))) ?></p>
                <p class="kv"><strong>Education Rows:</strong> <?= e((string) count((array) ($step3['education'] ?? []))) ?></p>
                <p class="kv"><strong>Grants Rows:</strong> <?= e((string) count((array) ($step3['grants'] ?? []))) ?></p>
            </div>
        </div>

        <div class="card" style="margin-top:12px;">
            <h2>Uploaded Requirements</h2>
            <?php if ($documents): ?>
                <ul class="list">
                    <?php foreach ($documents as $doc): ?>
                        <li><?= e((string) ($doc['requirement_name'] ?? 'Requirement')) ?> - <?= e((string) ($doc['original_name'] ?? basename((string) ($doc['file_path'] ?? '')))) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted mb-0">No uploaded requirements yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

