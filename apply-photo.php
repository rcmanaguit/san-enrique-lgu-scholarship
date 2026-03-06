<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    throw new RuntimeException('Database connection is not available.');
}

require_login('login.php');
require_role(['applicant'], 'index.php');

if (!db_ready()) {
    set_flash('warning', 'The system setup is not complete yet. Please contact the administrator.');
    redirect('dashboard.php');
}

$openPeriod = current_open_application_period($conn);
if (!$openPeriod) {
    set_flash('warning', 'Applications are currently closed. You cannot submit an application without an open application period.');
    redirect('dashboard.php');
}

$pageTitle = 'Photo Capture';
$bodyClass = 'apply-page';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css'];
$extraJs = ['assets/js/capture-utils.js', 'assets/js/capture-ui.js'];
$user = current_user();

if (applicant_has_application_in_period($conn, (int) ($user['id'] ?? 0), $openPeriod)) {
    set_flash('warning', 'You already submitted an application for the current open period. Only one application is allowed per period.');
    redirect('my-application.php');
}

$wizard = wizard_state();
$persistentDraft = wizard_load_persistent_draft($conn, (int) ($user['id'] ?? 0));
if (is_array($persistentDraft['state'] ?? null)) {
    $wizard = array_merge($wizard, (array) $persistentDraft['state']);
    wizard_save($wizard);
}

if (!(bool) ($wizard['step1_done'] ?? false)) {
    redirect('apply.php?step=1');
}
if (!(bool) ($wizard['step2_done'] ?? false)) {
    redirect('apply.php?step=2');
}
if (!(bool) ($wizard['step3_done'] ?? false)) {
    redirect('apply.php?step=3');
}
if (!(bool) ($wizard['step4_done'] ?? false)) {
    redirect('apply.php?step=4');
}

$persistWizard = static function (array $state, int $currentStep) use ($conn, $user): void {
    wizard_save($state);
    wizard_save_persistent_draft($conn, (int) ($user['id'] ?? 0), $state, $currentStep);
};

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('apply-photo.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'save_photo') {
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
                redirect('apply-photo.php');
            }
        } catch (Throwable $e) {
            set_flash('danger', 'Photo upload failed: ' . $e->getMessage());
            redirect('apply-photo.php');
        }

        $persistWizard($wizard, 6);
        redirect('apply.php?step=6');
    }
}

$existingPhotoPath = (string) ($wizard['photo_path'] ?? '');
$stepLabels = [1 => 'Program', 2 => 'Personal', 3 => 'Family', 4 => 'Requirements', 5 => '2x2 Photo', 6 => 'Review'];

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0"><i class="fa-solid fa-camera me-2 text-primary"></i>Step 5: 2x2 Photo Capture</h1>
    <div class="d-flex gap-2">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
        <a href="apply.php?reset=1" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reset Draft</a>
    </div>
</div>

<div class="card card-soft mb-3 wizard-step-summary">
    <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-primary">Step 5 of 6</span>
            <span class="small text-muted">2x2 Photo</span>
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
                if ($stepNo < 5) {
                    $class .= ' done';
                } elseif ($stepNo === 5) {
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<div class="card card-soft shadow-sm">
    <div class="card-body p-4">
        <p class="small text-muted mb-2">Choose a source, crop to square, then apply photo before proceeding.</p>
        <ol class="photo-simple-steps small text-muted mb-3">
            <li>Choose <strong>Upload Photo</strong> or <strong>Use Camera</strong>.</li>
            <li>Keep face centered in guide box.</li>
            <li>Crop to square and tap <strong>Apply Photo</strong>.</li>
        </ol>

        <form method="post" enctype="multipart/form-data" id="step5Form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_photo">
            <input type="hidden" name="photo_base64" id="photoBase64">

            <div class="photo-capture-shell">
                <div class="photo-mode-switch mb-3" role="group" aria-label="Photo source">
                    <button type="button" class="btn btn-outline-primary active" data-photo-mode="upload" aria-pressed="true">
                        <i class="fa-solid fa-upload me-1"></i>Upload Photo
                    </button>
                    <button type="button" class="btn btn-outline-primary" data-photo-mode="camera" aria-pressed="false">
                        <i class="fa-solid fa-camera me-1"></i>Camera
                    </button>
                </div>
                <div class="photo-status photo-status-info mb-3" id="photoStatusWrap">
                    <span class="photo-status-icon" id="photoStatusIcon"><i class="fa-solid fa-circle-info"></i></span>
                    <p class="small mb-0" id="photoStatus">Choose a source to begin.</p>
                </div>
            </div>

            <div class="row g-3 align-items-start">
                <div class="col-12 col-lg-7">
                    <div id="uploadPanel" class="photo-panel">
                        <label class="form-label">Choose Image</label>
                        <div class="photo-upload-drop">
                            <input type="file" name="photo_upload" id="photoInput" class="form-control" accept="image/*">
                        </div>
                        <div class="photo-file-name mt-2" id="photoFileName">No file selected yet.</div>
                    </div>

                    <div id="cameraPanel" class="photo-panel d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Camera Capture</label>
                            <button type="button" id="toggleFullscreenBtn" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-expand me-1"></i>Full Screen
                            </button>
                        </div>
                        <div class="camera-stage">
                            <video id="cameraVideo" class="d-none" autoplay playsinline muted></video>
                            <canvas id="cameraCanvas" class="d-none"></canvas>
                            <div id="cameraPlaceholder" class="camera-placeholder">
                                <i class="fa-solid fa-camera-retro me-1"></i>Camera preview appears here after permission
                            </div>
                            <div id="cameraGuides" class="camera-guides d-none" aria-hidden="true"></div>
                        </div>
                        <div class="camera-control-grid photo-camera-controls mt-2">
                            <button type="button" id="captureBtn" class="btn btn-primary photo-capture-circle" disabled aria-label="Capture Photo">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                        </div>
                    </div>

                    <div class="photo-source-shell mt-3 d-none" id="photoSourceShell">
                        <div class="photo-source-header mb-2">
                            <h3 class="h6 mb-0">Crop Photo</h3>
                            <span class="badge text-bg-light border"><i class="fa-solid fa-square me-1"></i>1:1 Ratio</span>
                        </div>
                        <img id="photoSource" src="" alt="Source" class="img-fluid d-none photo-source">
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" id="cropBtn" class="btn btn-primary d-none wizard-mobile-full">
                            <i class="fa-solid fa-check me-1"></i>Apply Photo
                        </button>
                        <button type="button" id="clearSourceBtn" class="btn btn-outline-danger d-none wizard-mobile-full">
                            <i class="fa-solid fa-rotate-right me-1"></i>Retake Photo
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
                <button type="submit" class="btn btn-primary" id="step5NextBtn">
                    <span class="step5-next-label"><i class="fa-solid fa-arrow-right me-1"></i>Next Step</span>
                    <span class="step5-loading-label d-none"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving photo...</span>
                </button>
            </div>
            <p class="small text-muted mt-2 mb-0" id="step5SubmitNote">Apply a cropped photo to continue.</p>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.SE_CAPTURE_UI || typeof window.SE_CAPTURE_UI.initPhotoCaptureForm !== 'function') {
        return;
    }
    window.SE_CAPTURE_UI.initPhotoCaptureForm({
        formId: 'step5Form',
        inputId: 'photoInput',
        sourceId: 'photoSource',
        previewFrameId: 'photoPreviewFrame',
        cropBtnId: 'cropBtn',
        clearSourceBtnId: 'clearSourceBtn',
        base64InputId: 'photoBase64',
        statusWrapId: 'photoStatusWrap',
        statusIconId: 'photoStatusIcon',
        statusTextId: 'photoStatus',
        fileNameId: 'photoFileName',
        uploadPanelId: 'uploadPanel',
        cameraPanelId: 'cameraPanel',
        sourceShellId: 'photoSourceShell',
        modeButtonsSelector: '[data-photo-mode]',
        videoId: 'cameraVideo',
        canvasId: 'cameraCanvas',
        placeholderId: 'cameraPlaceholder',
        guidesId: 'cameraGuides',
        fullscreenBtnId: 'toggleFullscreenBtn',
        captureBtnId: 'captureBtn',
        submitBtnId: 'step5NextBtn',
        submitNoteId: 'step5SubmitNote'
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

