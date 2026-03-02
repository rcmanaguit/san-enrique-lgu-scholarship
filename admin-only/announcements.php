<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_admin('../index.php');

$pageTitle = 'Manage Announcements';
$announcements = [];
$requirementTemplates = [];
$editAnnouncement = null;
$user = current_user();

if (is_post() && db_ready()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'create') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $content = trim((string) ($_POST['content'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $sendSms = isset($_POST['send_sms']) ? 1 : 0;

            if (!$title || !$content) {
                set_flash('danger', 'Title and content are required.');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO announcements (title, content, is_active, created_by) VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param('ssii', $title, $content, $isActive, $user['id']);
                $stmt->execute();
                $newAnnouncementId = (int) $stmt->insert_id;
                $stmt->close();
                audit_log(
                    $conn,
                    'announcement_created',
                    (int) ($user['id'] ?? 0),
                    (string) ($user['role'] ?? 'admin'),
                    'announcement',
                    (string) $newAnnouncementId,
                    'Announcement created.',
                    [
                        'title' => $title,
                        'is_active' => $isActive,
                        'send_sms' => $sendSms,
                    ]
                );

                if ($sendSms === 1 && $isActive === 1) {
                    $phones = [];
                    $resultPhones = $conn->query("SELECT phone FROM users WHERE role = 'applicant' AND status = 'active' AND phone IS NOT NULL AND phone <> ''");
                    if ($resultPhones instanceof mysqli_result) {
                        while ($rowPhone = $resultPhones->fetch_assoc()) {
                            $phones[] = (string) $rowPhone['phone'];
                        }
                    }
                    $smsMessage = 'San Enrique LGU Scholarship Update: ' . $title . '. ' . excerpt($content, 120);
                    sms_send_bulk($phones, $smsMessage, 'bulk');
                    audit_log(
                        $conn,
                        'announcement_sms_broadcast',
                        (int) ($user['id'] ?? 0),
                        (string) ($user['role'] ?? 'admin'),
                        'announcement',
                        (string) $newAnnouncementId,
                        'Announcement SMS broadcast initiated.',
                        ['recipient_count' => count($phones)]
                    );
                }

                set_flash('success', 'Announcement published.');
            }
        }

        if ($action === 'update') {
            $id = (int) ($_POST['announcement_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $content = trim((string) ($_POST['content'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $sendSms = isset($_POST['send_sms']) ? 1 : 0;

            if ($id <= 0) {
                set_flash('danger', 'Invalid announcement selected.');
            } elseif ($title === '' || $content === '') {
                set_flash('danger', 'Title and content are required.');
            } else {
                $previousTitle = '';
                $previousStatus = 0;

                $lookupStmt = $conn->prepare("SELECT title, is_active FROM announcements WHERE id = ? LIMIT 1");
                if ($lookupStmt) {
                    $lookupStmt->bind_param('i', $id);
                    $lookupStmt->execute();
                    $lookupResult = $lookupStmt->get_result();
                    $lookupRow = $lookupResult instanceof mysqli_result ? ($lookupResult->fetch_assoc() ?: null) : null;
                    $lookupStmt->close();

                    if (is_array($lookupRow)) {
                        $previousTitle = trim((string) ($lookupRow['title'] ?? ''));
                        $previousStatus = (int) ($lookupRow['is_active'] ?? 0);
                    }
                }

                if ($previousTitle === '') {
                    set_flash('warning', 'Announcement not found.');
                } else {
                    $updateStmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, is_active = ? WHERE id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param('ssii', $title, $content, $isActive, $id);
                        $updateStmt->execute();
                        $updated = $updateStmt->affected_rows >= 0;
                        $updateStmt->close();

                        if ($updated) {
                            audit_log(
                                $conn,
                                'announcement_updated',
                                (int) ($user['id'] ?? 0),
                                (string) ($user['role'] ?? 'admin'),
                                'announcement',
                                (string) $id,
                                'Announcement updated.',
                                [
                                    'old_title' => $previousTitle,
                                    'new_title' => $title,
                                    'old_status' => $previousStatus,
                                    'new_status' => $isActive,
                                    'send_sms' => $sendSms,
                                ]
                            );

                            if ($sendSms === 1 && $isActive === 1) {
                                $phones = [];
                                $resultPhones = $conn->query("SELECT phone FROM users WHERE role = 'applicant' AND status = 'active' AND phone IS NOT NULL AND phone <> ''");
                                if ($resultPhones instanceof mysqli_result) {
                                    while ($rowPhone = $resultPhones->fetch_assoc()) {
                                        $phones[] = (string) $rowPhone['phone'];
                                    }
                                }

                                $smsMessage = 'San Enrique LGU Scholarship Update: ' . $title . '. ' . excerpt($content, 120);
                                sms_send_bulk($phones, $smsMessage, 'bulk');
                                audit_log(
                                    $conn,
                                    'announcement_sms_broadcast',
                                    (int) ($user['id'] ?? 0),
                                    (string) ($user['role'] ?? 'admin'),
                                    'announcement',
                                    (string) $id,
                                    'Announcement SMS broadcast initiated from update.',
                                    ['recipient_count' => count($phones)]
                                );
                            }

                            set_flash('success', 'Announcement updated.');
                        } else {
                            set_flash('danger', 'Unable to update announcement.');
                        }
                    } else {
                        set_flash('danger', 'Unable to update announcement.');
                    }
                }
            }
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['announcement_id'] ?? 0);
            $newStatus = (int) ($_POST['new_status'] ?? 0);
            $stmt = $conn->prepare("UPDATE announcements SET is_active = ? WHERE id = ?");
            $stmt->bind_param('ii', $newStatus, $id);
            $stmt->execute();
            $stmt->close();
            audit_log(
                $conn,
                'announcement_status_changed',
                (int) ($user['id'] ?? 0),
                (string) ($user['role'] ?? 'admin'),
                'announcement',
                (string) $id,
                'Announcement active status changed.',
                ['new_status' => $newStatus]
            );
            set_flash('success', 'Announcement status updated.');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['announcement_id'] ?? 0);
            if ($id <= 0) {
                set_flash('danger', 'Invalid announcement selected.');
            } else {
                $existingTitle = '';
                $existingStatus = 0;

                $lookupStmt = $conn->prepare("SELECT title, is_active FROM announcements WHERE id = ? LIMIT 1");
                if ($lookupStmt) {
                    $lookupStmt->bind_param('i', $id);
                    $lookupStmt->execute();
                    $lookupResult = $lookupStmt->get_result();
                    $lookupRow = $lookupResult instanceof mysqli_result ? ($lookupResult->fetch_assoc() ?: null) : null;
                    $lookupStmt->close();

                    if (is_array($lookupRow)) {
                        $existingTitle = trim((string) ($lookupRow['title'] ?? ''));
                        $existingStatus = (int) ($lookupRow['is_active'] ?? 0);
                    }
                }

                if ($existingTitle === '') {
                    set_flash('warning', 'Announcement not found or already deleted.');
                } else {
                    $deleteStmt = $conn->prepare("DELETE FROM announcements WHERE id = ? LIMIT 1");
                    if ($deleteStmt) {
                        $deleteStmt->bind_param('i', $id);
                        $deleteStmt->execute();
                        $deleted = $deleteStmt->affected_rows > 0;
                        $deleteStmt->close();

                        if ($deleted) {
                            audit_log(
                                $conn,
                                'announcement_deleted',
                                (int) ($user['id'] ?? 0),
                                (string) ($user['role'] ?? 'admin'),
                                'announcement',
                                (string) $id,
                                'Announcement deleted permanently.',
                                [
                                    'title' => $existingTitle,
                                    'was_active' => $existingStatus,
                                ]
                            );
                            set_flash('success', 'Announcement deleted permanently.');
                        } else {
                            set_flash('danger', 'Unable to delete announcement.');
                        }
                    } else {
                        set_flash('danger', 'Unable to delete announcement.');
                    }
                }
            }
        }
    }

    redirect('announcements.php');
}

if (db_ready()) {
    $requirementsSql = "SELECT requirement_name, description, is_required
                        FROM requirement_templates
                        WHERE is_active = 1
                        ORDER BY sort_order ASC, id ASC";
    $requirementsResult = $conn->query($requirementsSql);
    if ($requirementsResult instanceof mysqli_result) {
        $requirementTemplates = $requirementsResult->fetch_all(MYSQLI_ASSOC);
    }

    $editId = (int) ($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $editStmt = $conn->prepare("SELECT id, title, content, is_active FROM announcements WHERE id = ? LIMIT 1");
        if ($editStmt) {
            $editStmt->bind_param('i', $editId);
            $editStmt->execute();
            $editResult = $editStmt->get_result();
            $editAnnouncement = $editResult instanceof mysqli_result ? ($editResult->fetch_assoc() ?: null) : null;
            $editStmt->close();
        }
    }

    $sql = "SELECT a.id, a.title, a.content, a.is_active, a.created_at, u.first_name, u.last_name
            FROM announcements a
            LEFT JOIN users u ON u.id = a.created_by
            ORDER BY a.created_at DESC";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $announcements = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$isEditMode = is_array($editAnnouncement);
$formModeTitle = $isEditMode ? 'Edit Announcement' : 'Create Announcement';
$formModeAction = $isEditMode ? 'update' : 'create';
$submitButtonText = $isEditMode ? 'Save Changes' : 'Save Announcement';
$defaultTitle = $isEditMode ? trim((string) ($editAnnouncement['title'] ?? '')) : '';
$defaultContent = $isEditMode ? trim((string) ($editAnnouncement['content'] ?? '')) : '';
$defaultIsActive = $isEditMode ? ((int) ($editAnnouncement['is_active'] ?? 0) === 1) : true;
$smsProviderLabel = sms_provider_label();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0">Announcement Management</h1>
</div>

<div class="card card-soft shadow-sm mb-4 announcement-create-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <h2 class="h6 mb-0" id="announcement-form"><?= e($formModeTitle) ?></h2>
            <?php if ($isEditMode): ?>
                <a href="announcements.php" class="btn btn-sm btn-outline-secondary">Cancel Edit</a>
            <?php endif; ?>
        </div>
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= e($formModeAction) ?>">
            <?php if ($isEditMode): ?>
                <input type="hidden" name="announcement_id" value="<?= (int) ($editAnnouncement['id'] ?? 0) ?>">
            <?php endif; ?>
            <div class="col-12 col-lg-8">
                <label class="form-label">Announcement Template</label>
                <select id="announcementTemplate" class="form-select">
                    <option value="">Custom (No Template)</option>
                    <option value="application_open">Application Period Open</option>
                    <option value="deadline_extension">Deadline Extension</option>
                    <option value="requirements_update">Requirements Update</option>
                    <option value="interview_schedule">Interview Schedule Notice</option>
                    <option value="results_release">Results / Status Notice</option>
                    <option value="soa_reminder">SOA/Student Copy Reminder</option>
                    <option value="payout_advisory">Payout Schedule Advisory</option>
                    <option value="office_advisory">Office/System Advisory</option>
                </select>
            </div>
            <div class="col-12 col-lg-4 announcement-template-actions">
                <label class="form-label d-none d-lg-block">Quick Action</label>
                <button type="button" class="btn btn-outline-primary w-100 announcement-template-btn" id="insertRequirementsBtn">
                    <i class="fa-solid fa-list-check me-1"></i>Insert Active Requirements List
                </button>
            </div>
            <div class="col-12">
                <div class="form-text mt-0">Choose a template to auto-fill title and content, then adjust details before publishing.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" name="title" id="announcementTitle" value="<?= e($defaultTitle) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Content</label>
                <textarea class="form-control" name="content" id="announcementContent" rows="7" required><?= e($defaultContent) ?></textarea>
            </div>
            <div class="col-12 form-check ms-1">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $defaultIsActive ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Publish now</label>
            </div>
            <div class="col-12 form-check ms-1">
                <input class="form-check-input" type="checkbox" name="send_sms" id="send_sms">
                <label class="form-check-label" for="send_sms">
                    <?= $isEditMode ? 'Send update SMS to applicants (' . e($smsProviderLabel) . ')' : 'Send SMS to applicants (' . e($smsProviderLabel) . ')' ?>
                </label>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?= e($submitButtonText) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card card-soft shadow-sm mb-4 announcement-requirements-card">
    <div class="card-body">
        <h2 class="h6 mb-2">Active Requirements Reference</h2>
        <?php if (!$requirementTemplates): ?>
            <p class="text-muted mb-0">No active requirements found.</p>
        <?php else: ?>
            <ol class="mb-0 small">
                <?php foreach ($requirementTemplates as $req): ?>
                    <li class="mb-1">
                        <strong><?= e((string) ($req['requirement_name'] ?? 'Requirement')) ?></strong>
                        <?php if ((int) ($req['is_required'] ?? 1) === 0): ?>
                            <span class="badge text-bg-light ms-1">Optional</span>
                        <?php endif; ?>
                        <?php if (trim((string) ($req['description'] ?? '')) !== ''): ?>
                            <div class="text-muted"><?= e((string) $req['description']) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade modal-se" id="announcementPromptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="modal-se-title-wrap">
                    <span class="modal-se-icon" id="announcementPromptIcon">
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                    <div>
                        <h5 class="modal-title mb-0" id="announcementPromptTitle">Confirmation</h5>
                        <small class="text-muted" id="announcementPromptSubtitle">San Enrique LGU Scholarship</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-0" id="announcementPromptMessage"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" id="announcementPromptCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="announcementPromptConfirm">Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const templateSelect = document.getElementById('announcementTemplate');
        const titleInput = document.getElementById('announcementTitle');
        const contentInput = document.getElementById('announcementContent');
        const insertRequirementsBtn = document.getElementById('insertRequirementsBtn');
        const promptModalEl = document.getElementById('announcementPromptModal');
        const promptIconEl = document.getElementById('announcementPromptIcon');
        const promptTitleEl = document.getElementById('announcementPromptTitle');
        const promptMessageEl = document.getElementById('announcementPromptMessage');
        const promptCancelBtn = document.getElementById('announcementPromptCancel');
        const promptConfirmBtn = document.getElementById('announcementPromptConfirm');
        const promptSubtitleEl = document.getElementById('announcementPromptSubtitle');

        if (!templateSelect || !titleInput || !contentInput || !insertRequirementsBtn || !promptModalEl || !promptIconEl || !promptTitleEl || !promptMessageEl || !promptCancelBtn || !promptConfirmBtn || !promptSubtitleEl) {
            return;
        }

        const requirementTemplates = <?= json_encode($requirementTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const promptModal = typeof bootstrap !== 'undefined' ? new bootstrap.Modal(promptModalEl) : null;

        function showPrompt(options) {
            const opts = Object.assign({
                title: 'Confirmation',
                message: '',
                confirmText: 'Continue',
                cancelText: 'Cancel',
                kind: 'info',
                hideCancel: false,
                confirmClass: 'btn-primary'
            }, options || {});

            if (!promptModal) {
                if (opts.hideCancel) {
                    window.alert(opts.message);
                    return Promise.resolve(true);
                }
                return Promise.resolve(window.confirm(opts.message));
            }

            const iconClassMap = {
                info: 'fa-solid fa-circle-info',
                warning: 'fa-solid fa-triangle-exclamation',
                success: 'fa-solid fa-circle-check',
                danger: 'fa-solid fa-circle-xmark'
            };
            const iconClass = iconClassMap[opts.kind] || iconClassMap.info;

            promptTitleEl.textContent = String(opts.title || 'Confirmation');
            promptMessageEl.textContent = String(opts.message || '');
            promptSubtitleEl.textContent = 'San Enrique LGU Scholarship';

            promptIconEl.className = 'modal-se-icon';
            promptIconEl.classList.add('is-' + String(opts.kind || 'info'));
            promptIconEl.innerHTML = '<i class="' + iconClass + '"></i>';

            promptConfirmBtn.textContent = String(opts.confirmText || 'Continue');
            promptCancelBtn.textContent = String(opts.cancelText || 'Cancel');
            promptCancelBtn.classList.toggle('d-none', !!opts.hideCancel);
            const confirmClass = String(opts.confirmClass || 'btn-primary').trim();
            const classTokens = confirmClass.split(/\s+/).filter(function (token) { return token !== ''; });
            if (!classTokens.includes('btn')) {
                classTokens.unshift('btn');
            }
            promptConfirmBtn.className = classTokens.join(' ');
            if (!promptCancelBtn.classList.contains('d-none')) {
                promptCancelBtn.className = 'btn btn-outline-secondary';
            }

            return new Promise(function (resolve) {
                let settled = false;

                function cleanup() {
                    promptConfirmBtn.onclick = null;
                    promptCancelBtn.onclick = null;
                }

                promptConfirmBtn.onclick = function () {
                    settled = true;
                    cleanup();
                    promptModal.hide();
                    resolve(true);
                };

                promptCancelBtn.onclick = function () {
                    settled = true;
                    cleanup();
                    promptModal.hide();
                    resolve(false);
                };

                const hiddenHandler = function () {
                    promptModalEl.removeEventListener('hidden.bs.modal', hiddenHandler);
                    if (!settled) {
                        settled = true;
                        cleanup();
                        resolve(false);
                    }
                };
                promptModalEl.addEventListener('hidden.bs.modal', hiddenHandler);

                promptModal.show();
            });
        }

        function buildRequirementsBlock() {
            if (!Array.isArray(requirementTemplates) || requirementTemplates.length === 0) {
                return 'Required Documents:\n- No active requirements configured yet.';
            }

            const lines = ['Required Documents:'];
            requirementTemplates.forEach(function (req, index) {
                const name = String((req && req.requirement_name) || 'Requirement').trim();
                const description = String((req && req.description) || '').trim();
                const required = Number((req && req.is_required) || 1) === 1;
                let line = '- ' + (index + 1) + '. ' + name;
                if (!required) {
                    line += ' (Optional)';
                }
                if (description) {
                    line += ' - ' + description;
                }
                lines.push(line);
            });

            return lines.join('\n');
        }

        const requirementsBlock = buildRequirementsBlock();
        const templateFactory = {
            application_open: function () {
                return {
                    title: 'Application Period Open - [Semester] [School Year]',
                    content:
                        'Good day, scholars and applicants.\n\n' +
                        'The San Enrique LGU Scholarship application period for [Semester] [School Year] is now OPEN.\n\n' +
                        requirementsBlock + '\n\n' +
                        'Submission Deadline: [Date]\n' +
                        'Office: Mayor\'s Office, Municipality of San Enrique\n\n' +
                        'Please complete your online application and submit physical requirements on time.',
                };
            },
            deadline_extension: function () {
                return {
                    title: 'Deadline Extension - [Semester] [School Year]',
                    content:
                        'Please be informed that the deadline for scholarship application/requirements submission has been extended.\n\n' +
                        'Previous Deadline: [Old Date]\n' +
                        'New Deadline: [New Date]\n' +
                        'Office: Mayor\'s Office, Municipality of San Enrique\n\n' +
                        'Late submissions beyond the extended deadline may no longer be accepted.',
                };
            },
            requirements_update: function () {
                return {
                    title: 'Requirements Update - [Semester] [School Year]',
                    content:
                        'Please be informed of the updated checklist of requirements for the San Enrique LGU Scholarship.\n\n' +
                        requirementsBlock + '\n\n' +
                        'For clarifications, please visit the Mayor\'s Office.',
                };
            },
            interview_schedule: function () {
                return {
                    title: 'Interview Schedule Notice - [Semester] [School Year]',
                    content:
                        'Qualified applicants are advised to attend the scholarship interview.\n\n' +
                        'Interview Date: [Date]\n' +
                        'Interview Time: [Time]\n' +
                        'Venue: [Location]\n\n' +
                        'Please bring a valid ID and your application reference number.',
                };
            },
            results_release: function () {
                return {
                    title: 'Application Status Notice - [Semester] [School Year]',
                    content:
                        'The initial evaluation results for scholarship applications are now available.\n\n' +
                        'Please login to your account to check your status and next steps.\n\n' +
                        'If your status is FOR SOA SUBMISSION, please submit your SOA/Student Copy at the Mayor\'s Office.',
                };
            },
            soa_reminder: function () {
                return {
                    title: 'SOA/Student Copy Submission Reminder',
                    content:
                        'Reminder to approved applicants:\n\n' +
                        'Please submit your SOA/Student Copy at the Mayor\'s Office on or before [Deadline Date].\n\n' +
                        'Only applicants with completed SOA submission can proceed to payout scheduling.',
                };
            },
            payout_advisory: function () {
                return {
                    title: 'Payout Schedule Advisory - [Semester] [School Year]',
                    content:
                        'Please be informed of the payout schedule for the San Enrique LGU Scholarship.\n\n' +
                        'Payout Date: [Date]\n' +
                        'Time: [Time]\n' +
                        'Venue: [Location]\n\n' +
                        'Bring a valid ID and follow your assigned schedule.',
                };
            },
            office_advisory: function () {
                return {
                    title: 'Office/System Advisory',
                    content:
                        'Please be informed of an important advisory regarding scholarship services.\n\n' +
                        'Advisory Details: [Details]\n' +
                        'Effective Date: [Date]\n\n' +
                        'Please monitor official announcements for further updates.',
                };
            }
        };

        async function applyTemplate(templateKey) {
            if (!templateFactory[templateKey]) {
                return;
            }

            const hasExistingValue = titleInput.value.trim() !== '' || contentInput.value.trim() !== '';
            if (hasExistingValue) {
                const proceed = await showPrompt({
                    title: 'Replace Current Draft?',
                    message: 'Replace current title/content with selected template?',
                    confirmText: 'Replace',
                    cancelText: 'Keep Current',
                    kind: 'warning',
                    confirmClass: 'btn-primary'
                });
                if (!proceed) {
                    return;
                }
            }

            const generated = templateFactory[templateKey]();
            titleInput.value = generated.title;
            contentInput.value = generated.content;
            titleInput.focus();
        }

        function insertRequirements() {
            if (!requirementsBlock) {
                return;
            }

            if (contentInput.value.trim() === '') {
                contentInput.value = requirementsBlock;
                return;
            }

            if (contentInput.value.includes('Required Documents:')) {
                showPrompt({
                    title: 'Requirements Already Added',
                    message: 'Requirements list already exists in content.',
                    confirmText: 'OK',
                    kind: 'info',
                    hideCancel: true,
                    confirmClass: 'btn-primary'
                });
                return;
            }

            contentInput.value = contentInput.value.trimEnd() + '\n\n' + requirementsBlock;
        }

        templateSelect.addEventListener('change', async function () {
            const templateKey = String(templateSelect.value || '');
            if (templateKey === '') {
                return;
            }
            await applyTemplate(templateKey);
        });

        insertRequirementsBtn.addEventListener('click', insertRequirements);

        const deleteForms = document.querySelectorAll('.js-delete-announcement-form');
        deleteForms.forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const announcementTitle = String(form.getAttribute('data-announcement-title') || '').trim();
                const proceed = await showPrompt({
                    title: 'Delete Announcement?',
                    message: (
                        'You are about to permanently delete "' + (announcementTitle || 'this announcement') + '". ' +
                        'This action cannot be undone.'
                    ),
                    confirmText: 'Delete Permanently',
                    cancelText: 'Cancel',
                    kind: 'danger',
                    confirmClass: 'btn btn-danger'
                });

                if (proceed) {
                    form.submit();
                }
            });
        });
    });
</script>

<div class="card card-soft shadow-sm">
    <div class="card-body">
        <h2 class="h6">All Announcements</h2>
        <?php if (!$announcements): ?>
            <p class="text-muted mb-0">No announcements yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($announcements as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e($row['title']) ?></strong>
                                <div class="small text-muted"><?= e(excerpt((string) $row['content'], 90)) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= (int) $row['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= (int) $row['is_active'] === 1 ? 'Published' : 'Archived' ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime((string) $row['created_at'])) ?></td>
                            <td class="text-end">
                                <a href="announcements.php?edit=<?= (int) $row['id'] ?>#announcement-form" class="btn btn-sm btn-outline-secondary me-1">
                                    Edit
                                </a>
                                <form method="post" class="d-inline me-1" data-announcement-title="<?= e((string) $row['title']) ?>" data-crud-toggle-on="Unarchive" data-crud-toggle-off="Archive">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="announcement_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= (int) $row['is_active'] === 1 ? 0 : 1 ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">
                                        <?= (int) $row['is_active'] === 1 ? 'Archive' : 'Unarchive' ?>
                                    </button>
                                </form>
                                <form method="post" class="d-inline js-delete-announcement-form" data-announcement-title="<?= e((string) $row['title']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="announcement_id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

