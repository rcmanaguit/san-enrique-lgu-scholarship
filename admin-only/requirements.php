<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_admin('../index.php');

$pageTitle = 'Requirements';
$templates = [];

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('requirements.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $requirementName = trim((string) ($_POST['requirement_name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $applicantType = trim((string) ($_POST['applicant_type'] ?? ''));
        $schoolType = trim((string) ($_POST['school_type'] ?? ''));
        $isRequired = isset($_POST['is_required']) ? 1 : 0;

        if ($requirementName === '') {
            set_flash('danger', 'Requirement name is required.');
        } else {
            $applicantType = in_array($applicantType, ['new', 'renew'], true) ? $applicantType : null;
            $schoolType = in_array($schoolType, ['public', 'private'], true) ? $schoolType : null;

            if ($action === 'update' && $id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE requirement_templates
                     SET requirement_name = ?, description = ?, applicant_type = ?, school_type = ?, is_required = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('ssssii', $requirementName, $description, $applicantType, $schoolType, $isRequired, $id);
                $stmt->execute();
                $stmt->close();
                audit_log(
                    $conn,
                    'requirement_template_updated',
                    null,
                    null,
                    'requirement_template',
                    (string) $id,
                    'Requirement template updated.',
                    [
                        'requirement_name' => $requirementName,
                        'is_required' => $isRequired,
                    ]
                );
                set_flash('success', 'Requirement template updated.');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO requirement_templates
                    (requirement_name, description, applicant_type, school_type, is_required, is_active, sort_order)
                    VALUES (?, ?, ?, ?, ?, 1, ?)"
                );
                $sortOrder = 100;
                $stmt->bind_param('ssssii', $requirementName, $description, $applicantType, $schoolType, $isRequired, $sortOrder);
                $stmt->execute();
                $newRequirementId = (int) $stmt->insert_id;
                $stmt->close();
                audit_log(
                    $conn,
                    'requirement_template_created',
                    null,
                    null,
                    'requirement_template',
                    (string) $newRequirementId,
                    'Requirement template created.',
                    [
                        'requirement_name' => $requirementName,
                        'is_required' => $isRequired,
                    ]
                );
                set_flash('success', 'Requirement template created.');
            }
        }
        redirect('requirements.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM requirement_templates WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        audit_log(
            $conn,
            'requirement_template_deleted',
            null,
            null,
            'requirement_template',
            (string) $id,
            'Requirement template deleted.'
        );
        set_flash('success', 'Requirement template deleted.');
        redirect('requirements.php');
    }
}

$result = $conn->query("SELECT * FROM requirement_templates ORDER BY id ASC");
if ($result instanceof mysqli_result) {
    $templates = $result->fetch_all(MYSQLI_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>
<?php
$pageHeaderEyebrow = 'Settings';
$pageHeaderTitle = '<i class="fa-solid fa-list-check me-2 text-primary"></i>Requirements';
$pageHeaderDescription = 'Maintain the master requirement list here. Period-specific requirement selection happens in Application Periods.';
include __DIR__ . '/../includes/partials/page-shell-header.php';
?>

<div class="card card-soft shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6">Add requirement template</h2>
        <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="col-12 col-md-4">
                <label class="form-label form-label-sm">Requirement Name *</label>
                <input type="text" name="requirement_name" class="form-control form-control-sm" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm">Applicant Type</label>
                <select name="applicant_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="new">New</option>
                    <option value="renew">Renew</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label form-label-sm">School Type</label>
                <select name="school_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label form-label-sm">Description</label>
                <input type="text" name="description" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="is_required" id="is_required" checked>
                    <label class="form-check-label small" for="is_required">Required</label>
                </div>
            </div>
            <div class="col-12 col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Add Requirement</button>
            </div>
        </form>
    </div>
</div>

<div class="card card-soft shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Requirement</th>
                    <th>Required</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$templates): ?>
                    <tr>
                        <td colspan="3" class="text-muted">No requirements yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($templates as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $row['requirement_name']) ?></strong>
                            <div class="small text-muted"><?= e((string) $row['description']) ?></div>
                        </td>
                        <td><?= (int) $row['is_required'] === 1 ? 'Yes' : 'No' ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editRequirementModal"
                                    data-requirement-id="<?= (int) $row['id'] ?>"
                                    data-requirement-name="<?= e((string) $row['requirement_name']) ?>"
                                    data-description="<?= e((string) $row['description']) ?>"
                                    data-applicant-type="<?= e((string) ($row['applicant_type'] ?? '')) ?>"
                                    data-school-type="<?= e((string) ($row['school_type'] ?? '')) ?>"
                                    data-is-required="<?= (int) $row['is_required'] ?>"
                                >Edit Requirement</button>
                                <form method="post" class="d-inline" data-requirement-name="<?= e((string) $row['requirement_name']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete Requirement</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="editRequirementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h2 class="modal-title h5 mb-0">Edit Requirement</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="editRequirementId" value="0">
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Requirement Name *</label>
                        <input type="text" name="requirement_name" id="editRequirementName" class="form-control form-control-sm" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label form-label-sm">Applicant Type</label>
                            <select name="applicant_type" id="editApplicantType" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="new">New</option>
                                <option value="renew">Renew</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-sm">School Type</label>
                            <select name="school_type" id="editSchoolType" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="public">Public</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label form-label-sm">Description</label>
                        <input type="text" name="description" id="editDescription" class="form-control form-control-sm">
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_required" id="editIsRequired" value="1">
                        <label class="form-check-label small" for="editIsRequired">Required</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editRequirementModal');
    if (!(editModal instanceof HTMLElement)) {
        return;
    }

    editModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        const idInput = document.getElementById('editRequirementId');
        const nameInput = document.getElementById('editRequirementName');
        const descriptionInput = document.getElementById('editDescription');
        const applicantTypeInput = document.getElementById('editApplicantType');
        const schoolTypeInput = document.getElementById('editSchoolType');
        const isRequiredInput = document.getElementById('editIsRequired');

        if (idInput instanceof HTMLInputElement) {
            idInput.value = String(trigger.getAttribute('data-requirement-id') || '0');
        }
        if (nameInput instanceof HTMLInputElement) {
            nameInput.value = String(trigger.getAttribute('data-requirement-name') || '');
        }
        if (descriptionInput instanceof HTMLInputElement) {
            descriptionInput.value = String(trigger.getAttribute('data-description') || '');
        }
        if (applicantTypeInput instanceof HTMLSelectElement) {
            applicantTypeInput.value = String(trigger.getAttribute('data-applicant-type') || '');
        }
        if (schoolTypeInput instanceof HTMLSelectElement) {
            schoolTypeInput.value = String(trigger.getAttribute('data-school-type') || '');
        }
        if (isRequiredInput instanceof HTMLInputElement) {
            isRequiredInput.checked = String(trigger.getAttribute('data-is-required') || '0') === '1';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
