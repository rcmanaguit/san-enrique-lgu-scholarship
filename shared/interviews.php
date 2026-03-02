<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login('../login.php');
require_role(['admin', 'staff'], '../index.php');

$pageTitle = 'Interview Scheduling';
$forInterview = [];

if (is_post() && db_ready()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid request token.');
    } else {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $interviewDate = trim((string) ($_POST['interview_date'] ?? ''));
        $interviewLocation = trim((string) ($_POST['interview_location'] ?? ''));

        if ($applicationId <= 0 || !$interviewDate || !$interviewLocation) {
            set_flash('danger', 'Complete interview schedule details.');
        } else {
            $status = 'for_interview';
            $stmtApplicant = $conn->prepare(
                "SELECT a.application_no, u.id AS user_id, u.phone
                 FROM applications a
                 INNER JOIN users u ON u.id = a.user_id
                 WHERE a.id = ?
                 LIMIT 1"
            );
            $stmtApplicant->bind_param('i', $applicationId);
            $stmtApplicant->execute();
            $applicant = $stmtApplicant->get_result()->fetch_assoc();
            $stmtApplicant->close();

            $stmt = $conn->prepare(
                "UPDATE applications
                 SET status = ?, interview_date = ?, interview_location = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param('sssi', $status, $interviewDate, $interviewLocation, $applicationId);
            $stmt->execute();
            $stmt->close();

            if ($applicant) {
                $message = 'San Enrique LGU Scholarship: Interview schedule for application ' . $applicant['application_no']
                    . ' is set on ' . date('M d, Y h:i A', strtotime($interviewDate))
                    . ' at ' . $interviewLocation . '.';
                sms_send((string) ($applicant['phone'] ?? ''), $message, (int) ($applicant['user_id'] ?? 0), 'status_update');
                create_notification(
                    $conn,
                    (int) ($applicant['user_id'] ?? 0),
                    'Interview Schedule Updated',
                    'Your interview for application ' . (string) ($applicant['application_no'] ?? '') . ' is scheduled on ' . date('M d, Y h:i A', strtotime($interviewDate)) . ' at ' . $interviewLocation . '.',
                    'interview',
                    'my-application.php',
                    (int) (current_user()['id'] ?? 0)
                );
            }
            audit_log(
                $conn,
                'interview_schedule_updated',
                null,
                null,
                'application',
                (string) $applicationId,
                'Interview schedule saved.',
                [
                    'interview_date' => $interviewDate,
                    'interview_location' => $interviewLocation,
                ]
            );
            set_flash('success', 'Interview schedule saved.');
        }
    }
    redirect('interviews.php');
}

if (db_ready()) {
    $sql = "SELECT a.id, a.application_no, a.scholarship_type, a.status, a.interview_date, a.interview_location, u.first_name, u.last_name, u.phone
            FROM applications a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.status IN ('submitted', 'for_review', 'for_interview')
            ORDER BY a.updated_at DESC";
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        $forInterview = $result->fetch_all(MYSQLI_ASSOC);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<h1 class="h4 mb-3">Interview Scheduling</h1>

<?php if (!$forInterview): ?>
    <div class="card card-soft"><div class="card-body text-muted">No applications for interview scheduling.</div></div>
<?php else: ?>
    <div class="row g-3">
    <?php foreach ($forInterview as $row): ?>
        <div class="col-12">
            <div class="card card-soft shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h2 class="h6 mb-1"><?= e((string) ($row['application_no'] ?? ('#' . (int) $row['id']))) ?> - <?= e($row['first_name'] . ' ' . $row['last_name']) ?></h2>
                            <p class="small text-muted mb-1"><?= e($row['phone'] ?? '-') ?></p>
                            <p class="mb-0 small"><?= e($row['scholarship_type']) ?></p>
                        </div>
                        <span class="badge <?= status_badge_class((string) $row['status']) ?>"><?= e(strtoupper((string) $row['status'])) ?></span>
                    </div>

                    <form method="post" class="row g-2 mt-3" data-crud-modal="1" data-crud-title="Save Interview Schedule?" data-crud-message="Save interview date, time, and location for application {application_no}?" data-crud-confirm-text="Save Schedule" data-crud-kind="primary" data-application-no="<?= e((string) ($row['application_no'] ?? ('#' . (int) $row['id']))) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_interview_schedule">
                        <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                        <div class="col-12 col-md-4">
                            <label class="form-label form-label-sm">Interview Date & Time</label>
                            <input type="datetime-local" name="interview_date" class="form-control form-control-sm"
                                   value="<?= !empty($row['interview_date']) ? date('Y-m-d\TH:i', strtotime((string) $row['interview_date'])) : '' ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label form-label-sm">Location</label>
                            <input type="text" name="interview_location" class="form-control form-control-sm"
                                   value="<?= e((string) ($row['interview_location'] ?? '')) ?>" placeholder="LGU Hall, San Enrique">
                        </div>
                        <div class="col-12 col-md-2 d-grid">
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

