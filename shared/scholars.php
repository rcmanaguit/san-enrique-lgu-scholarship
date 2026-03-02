<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Scholars Records';
$scholars = [];

if (db_ready()) {
    $sql = "SELECT a.id AS application_id, a.scholarship_type, a.school_year, a.status, a.updated_at,
                   u.first_name, u.last_name, u.email, u.phone, u.school_name, u.course,
                   COALESCE(SUM(d.amount), 0) AS total_disbursed
            FROM applications a
            INNER JOIN users u ON u.id = a.user_id
            LEFT JOIN disbursements d ON d.application_id = a.id
            WHERE a.status IN ('approved', 'for_soa_submission', 'soa_submitted')
            GROUP BY a.id
            ORDER BY a.updated_at DESC";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $scholars = $result->fetch_all(MYSQLI_ASSOC);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 m-0">Approved Scholars</h1>
    <?php if (is_admin()): ?>
        <div class="btn-group btn-group-sm">
            <a href="../admin-only/export-reports.php?dataset=approved_scholars&format=pdf" class="btn btn-outline-primary"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a>
            <a href="../admin-only/export-reports.php?dataset=approved_scholars&format=docx" class="btn btn-outline-primary"><i class="fa-solid fa-file-word me-1"></i>DOCX</a>
            <a href="../admin-only/export-reports.php?dataset=approved_scholars&format=xlsx" class="btn btn-outline-primary"><i class="fa-solid fa-file-excel me-1"></i>XLSX</a>
        </div>
    <?php endif; ?>
</div>

<div class="card card-soft shadow-sm">
    <?php if (!$scholars): ?>
        <div class="card-body text-muted">No approved scholars yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Scholar</th>
                        <th>Program</th>
                        <th>School</th>
                        <th>Scholarship</th>
                        <th>SY</th>
                        <th>Total Disbursed</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scholars as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                            <div class="small text-muted"><?= e($row['email']) ?> | <?= e($row['phone'] ?? '-') ?></div>
                        </td>
                        <td><?= e($row['course'] ?? '-') ?></td>
                        <td><?= e($row['school_name'] ?? '-') ?></td>
                        <td><?= e($row['scholarship_type']) ?></td>
                        <td><?= e($row['school_year']) ?></td>
                        <td>PHP <?= number_format((float) $row['total_disbursed'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
