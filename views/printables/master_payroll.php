<?php
require __DIR__ . '/../layouts/header.php';

// Security: ONLY the Admin can generate official payrolls
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ' . base_url('login'));
    exit;
}

$payrollData = $approvedScholars ?? [];
$selectedSchoolType = $filterType ?? 'All';

// Calculate the Grand Total
$grandTotal = 0;
foreach ($payrollData as $row) {
    $grandTotal += (float) ($row['final_grant_amount'] ?? 0);
}
?>

<div class="no-print position-fixed bottom-0 end-0 m-4" style="z-index: 1000;">
    <button onclick="window.print()"
        class="btn btn-primary btn-lg rounded-circle shadow-lg d-flex justify-content-center align-items-center"
        style="width: 60px; height: 60px;">
        <i class="fa-solid fa-print fs-4"></i>
    </button>
</div>

<div class="no-print container-fluid bg-dark py-3 mb-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center px-4">
        <h5 class="text-white mb-0"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Payroll Generator Preview</h5>
        <a href="<?php echo htmlspecialchars(base_url('admin/dashboard')); ?>" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-arrow-left me-1"></i> Back
            to Dashboard</a>
    </div>
</div>

<div class="container bg-white text-dark printable-form-container p-4 p-md-5 mb-5 shadow"
    style="min-height: 11in; max-width: 8.5in; margin: auto;">

    <div class="print-header position-relative text-center mb-4 pb-3 border-bottom border-2 border-dark">
        <img src="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>"
            alt="San Enrique Logo" style="width: 80px; position: absolute; left: 20px; top: 0;">

        <div style="font-family: 'Times New Roman', Times, serif;">
            <p class="mb-0" style="font-size: 11pt;">Republic of the Philippines</p>
            <p class="mb-0" style="font-size: 11pt;">Province of Negros Occidental</p>
            <h5 class="mb-0 fw-bold mt-1" style="font-size: 13pt;">MUNICIPALITY OF SAN ENRIQUE</h5>
            <p class="mb-0 fw-bold mt-1" style="font-size: 12pt;">OFFICE OF THE MUNICIPAL MAYOR</p>
        </div>
    </div>

    <div class="text-center mb-4">
        <h4 class="fw-bold text-decoration-underline" style="font-family: 'Times New Roman', Times, serif;">MASTER
            PAYROLL</h4>
        <p class="fw-bold mb-0">EDUCATIONAL ASSISTANCE PROGRAM</p>
        <p class="small text-muted mb-0">School Type: <?php echo htmlspecialchars((string) $selectedSchoolType); ?></p>
        <p class="small text-muted mb-0">School Year 2025-2026 | 1st Semester</p>
        <p class="small text-muted mb-0">Municipality of San Enrique, Negros Occidental</p>
    </div>

    <table class="table table-bordered form-table align-middle border-dark" style="font-size: 10pt;">
        <thead class="text-center bg-light-blue fw-bold">
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 25%;">Name of Grantee<br><small>(Last Name, First Name, M.I.)</small></th>
                <th style="width: 15%;">Barangay</th>
                <th style="width: 25%;">Name of Institution</th>
                <th style="width: 15%;">Amount<br>(₱)</th>
                <th style="width: 15%;">Signature / Thumbmark</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payrollData)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No approved scholars pending for payout.</td>
                </tr>
            <?php else: ?>
                <?php $counter = 1;
                foreach ($payrollData as $row): ?>
                    <tr>
                        <td class="text-center">
                            <?php echo $counter++; ?>
                        </td>
                        <td class="fw-bold">
                            <?php
                            $mi = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                            echo htmlspecialchars(strtoupper($row['last_name'] . ', ' . $row['first_name'] . ' ' . $mi));
                            ?>
                        </td>
                        <td class="text-center">
                            <?php echo htmlspecialchars($row['address_barangay']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['school_name']); ?>
                        </td>
                        <td class="text-end fw-bold px-3">
                            <?php echo number_format((float) ($row['final_grant_amount'] ?? 0), 2); ?>
                        </td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="bg-light-blue fw-bold">
                    <td colspan="4" class="text-end pe-3 text-uppercase">Grand Total Amount</td>
                    <td class="text-end px-3 fs-6">₱
                        <?php echo number_format($grandTotal, 2); ?>
                    </td>
                    <td></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row mt-5 pt-4 text-center"
        style="font-family: 'Times New Roman', Times, serif; page-break-inside: avoid;">
        <div class="col-4">
            <p class="mb-5">Prepared by:</p>
            <h6 class="fw-bold mb-0 text-decoration-underline text-uppercase">JUAN DELA CRUZ</h6>
            <p class="small mb-0">Scholarship Coordinator</p>
        </div>
        <div class="col-4">
            <p class="mb-5">Certified Funds Available:</p>
            <h6 class="fw-bold mb-0 text-decoration-underline text-uppercase">MARIA CLARA REYES</h6>
            <p class="small mb-0">Municipal Treasurer</p>
        </div>
        <div class="col-4">
            <p class="mb-5">Approved by:</p>
            <h6 class="fw-bold mb-0 text-decoration-underline text-uppercase">HON. JOVEN N. MANSINARES</h6>
            <p class="small mb-0">Municipal Mayor</p>
        </div>
    </div>

</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
