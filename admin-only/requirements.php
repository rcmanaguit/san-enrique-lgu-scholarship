<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

/** @var mixed $conn */
$conn = $GLOBALS['conn'] ?? null;

require_login('../login.php');
require_admin('../index.php');

$pageTitle = 'Requirement Templates';
$templates = [];

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
        redirect('requirements.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'create') {
        $requirementName = trim((string) ($_POST['requirement_name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $applicantType = trim((string) ($_POST['applicant_type'] ?? ''));
        $schoolType = trim((string) ($_POST['school_type'] ?? ''));
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $sortOrder = (int) ($_POST['sort_order'] ?? 100);

        if ($requirementName === '') {
            set_flash('danger', 'Requirement name is required.');
        } else {
            $applicantType = in_array($applicantType, ['new', 'renew'], true) ? $applicantType : null;
            $schoolType = in_array($schoolType, ['public', 'private'], true) ? $schoolType : null;

            $stmt = $conn->prepare(
                "INSERT INTO requirement_templates
                (requirement_name, description, applicant_type, school_type, is_required, is_active, sort_order)
                VALUES (?, ?, ?, ?, ?, 1, ?)"
            );
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
                    'sort_order' => $sortOrder,
                ]
            );
            set_flash('success', 'Requirement template created.');
        }
        redirect('requirements.php');
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $newStatus = (int) ($_POST['new_status'] ?? 0);
        $stmt = $conn->prepare("UPDATE requirement_templates SET is_active = ? WHERE id = ?");
        $stmt->bind_param('ii', $newStatus, $id);
        $stmt->execute();
        $stmt->close();
        audit_log(
            $conn,
            'requirement_template_status_changed',
            null,
            null,
            'requirement_template',
            (string) $id,
            'Requirement template active status changed.',
            ['new_status' => $newStatus]
        );
        set_flash('success', 'Requirement status updated.');
        redirect('requirements.php');
    }
}

$result = $conn->query("SELECT * FROM requirement_templates ORDER BY sort_order ASC, id ASC");
if ($result instanceof mysqli_result) {
    $templates = $result->fetch_all(MYSQLI_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 mb-3"><i class="fa-solid fa-list-check me-2 text-primary"></i>Requirement Templates</h1>

<div class="card card-soft shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6">Add Dynamic Requirement</h2>
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
                    <option value="renew">Re-New</option>
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
            <div class="col-12 col-md-1">
                <label class="form-label form-label-sm">Sort</label>
                <input type="number" name="sort_order" class="form-control form-control-sm" value="100">
            </div>
            <div class="col-12 col-md-7">
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
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Add</button>
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
                    <th>Scope</th>
                    <th>Required</th>
                    <th>Status</th>
                    <th>Sort</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $row['requirement_name']) ?></strong>
                            <div class="small text-muted"><?= e((string) $row['description']) ?></div>
                        </td>
                        <td class="small">
                            Applicant: <?= e((string) ($row['applicant_type'] ?: 'All')) ?><br>
                            School: <?= e((string) ($row['school_type'] ?: 'All')) ?>
                        </td>
                        <td><?= (int) $row['is_required'] === 1 ? 'Yes' : 'No' ?></td>
                        <td><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                        <td><?= (int) $row['sort_order'] ?></td>
                        <td class="text-end">
                            <form method="post" class="d-inline" data-requirement-name="<?= e((string) $row['requirement_name']) ?>" data-crud-toggle-on="Activate" data-crud-toggle-off="Deactivate">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= (int) $row['is_active'] === 1 ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <?= (int) $row['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
