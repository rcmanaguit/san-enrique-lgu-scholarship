<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$fullName = trim((string) (($noticeApplication['first_name'] ?? '') . ' ' . ($noticeApplication['middle_name'] ?? '') . ' ' . ($noticeApplication['last_name'] ?? '')));
$noticeTitle = match ($type) {
    'interview' => 'Interview Notice',
    'payout' => 'Payout Notice',
    default => 'Notice of Disqualification',
};
$applicationNumber = 'SELGU-APP-' . date('Y', strtotime((string) ($noticeApplication['created_at'] ?? 'now'))) . '-' . str_pad((string) ($noticeApplication['id'] ?? 0), 5, '0', STR_PAD_LEFT);
?>

<div class="container py-4">
    <div class="card shadow-sm border-0">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <img src="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>" alt="LGU Logo" style="width: 72px; height: 72px; object-fit: contain;">
                <h4 class="fw-bold mt-3 mb-1">Municipality of San Enrique, Negros Occidental</h4>
                <div class="text-muted">LGU Scholarship Records Management System</div>
            </div>

            <div class="text-end mb-4">Date: <?php echo htmlspecialchars(date('F d, Y')); ?></div>

            <h3 class="text-center fw-bold mb-4"><?php echo htmlspecialchars($noticeTitle); ?></h3>

            <p>Dear <strong><?php echo htmlspecialchars($fullName); ?></strong>,</p>

            <?php if ($type === 'interview'): ?>
                <p>
                    This is to formally inform you that your scholarship application has been scheduled for interview on
                    <strong><?php echo htmlspecialchars(date('F d, Y h:i A', strtotime((string) $noticeApplication['interview_schedule']))); ?></strong>
                    at <strong><?php echo htmlspecialchars((string) $noticeApplication['interview_venue']); ?></strong>.
                </p>
                <p>Please bring a valid identification card and be present at least 15 minutes before your scheduled time.</p>
            <?php elseif ($type === 'payout'): ?>
                <p>
                    This is to formally inform you that your scholarship payout is scheduled on
                    <strong><?php echo htmlspecialchars(date('F d, Y h:i A', strtotime((string) $noticeApplication['payout_schedule']))); ?></strong>
                    at <strong><?php echo htmlspecialchars((string) $noticeApplication['payout_venue']); ?></strong>.
                </p>
                <p>Please bring your valid identification card and original Statement of Account during payout.</p>
            <?php else: ?>
                <p>
                    After evaluation of your scholarship application, we regret to inform you that your application was not approved for the current term.
                </p>
                <p>
                    Recorded interview result:
                    <strong><?php echo htmlspecialchars((string) ($noticeApplication['interview_result'] ?? 'Not Eligible')); ?></strong>.
                </p>
                <p>You may contact the LGU office if you need clarification regarding your application status.</p>
            <?php endif; ?>

            <div class="mt-4">
                <div><strong>Application No.:</strong> <?php echo htmlspecialchars($applicationNumber); ?></div>
                <div><strong>School:</strong> <?php echo htmlspecialchars((string) ($noticeApplication['school_name'] ?? '')); ?></div>
                <div><strong>Course:</strong> <?php echo htmlspecialchars((string) ($noticeApplication['course'] ?? '')); ?></div>
                <div><strong>Address:</strong> <?php echo htmlspecialchars((string) (($noticeApplication['address_line'] ?? '') . ', ' . ($noticeApplication['address_barangay'] ?? '') . ', San Enrique, Negros Occidental')); ?></div>
            </div>

            <div class="mt-5 pt-4">
                <div class="fw-bold">Respectfully,</div>
                <div class="mt-5 border-top pt-2" style="max-width: 280px;">Scholarship Office</div>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function () {
    window.print();
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
