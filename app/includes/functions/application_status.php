<?php
declare(strict_types=1);

function application_status_options(): array
{
    return [
        'under_review',
        'needs_resubmission',
        'for_interview',
        'for_soa',
        'approved_for_release',
        'released',
        'rejected',
    ];
}

function approved_phase_statuses(): array
{
    return ['for_soa', 'approved_for_release', 'released'];
}

function application_status_meta(string $status): array
{
    $status = trim($status);

    $map = [
        'under_review' => [
            'label' => 'Under Review',
            'short_label' => 'Review',
            'staff_label' => 'Review Documents',
            'applicant_title' => 'Your application is being reviewed.',
            'applicant_detail' => 'Wait for document review results. You will be notified if anything needs to be replaced.',
            'staff_title' => 'Review submitted documents.',
            'staff_detail' => 'Check each uploaded requirement, add optional notes, then either request resubmission or move the applicant to interview.',
            'next_action_label' => 'Review documents',
            'tone' => 'info',
        ],
        'needs_resubmission' => [
            'label' => 'Needs Resubmission',
            'short_label' => 'Resubmit',
            'staff_label' => 'For Compliance',
            'applicant_title' => 'Replace the incomplete or rejected documents.',
            'applicant_detail' => 'Upload only the documents marked for resubmission, then send them back for review.',
            'staff_title' => 'Waiting for applicant replacements.',
            'staff_detail' => 'Applicant must re-upload the rejected documents before the record can move forward.',
            'next_action_label' => 'Upload replacement files',
            'tone' => 'warning',
        ],
        'for_interview' => [
            'label' => 'For Interview',
            'short_label' => 'Interview',
            'staff_label' => 'For Interview',
            'applicant_title' => 'Prepare for your interview schedule.',
            'applicant_detail' => 'Check the interview date, time, and location. Bring the required identification on schedule.',
            'staff_title' => 'Schedule or complete the interview stage.',
            'staff_detail' => 'Set the interview schedule, then move qualified applicants to SOA submission.',
            'next_action_label' => 'Schedule interview',
            'tone' => 'primary',
        ],
        'for_soa' => [
            'label' => 'Submit SOA',
            'short_label' => 'SOA',
            'staff_label' => 'For Compliance',
            'applicant_title' => 'Submit your SOA or student copy.',
            'applicant_detail' => 'Submit the school-issued SOA before the deadline so final release can be prepared.',
            'staff_title' => 'Collect and confirm SOA submission.',
            'staff_detail' => 'Track the deadline, record the SOA receipt, and move complete records to release approval.',
            'next_action_label' => 'Submit SOA',
            'tone' => 'primary',
        ],
        'approved_for_release' => [
            'label' => 'Approved for Payout',
            'short_label' => 'Approved',
            'staff_label' => 'For Release',
            'applicant_title' => 'Your application is approved for payout scheduling.',
            'applicant_detail' => 'Wait for the payout schedule and bring a valid ID on release day.',
            'staff_title' => 'Prepare the payout release step.',
            'staff_detail' => 'This record is cleared for disbursement scheduling and release confirmation.',
            'next_action_label' => 'Prepare payout release',
            'tone' => 'secondary',
        ],
        'released' => [
            'label' => 'Released',
            'short_label' => 'Released',
            'applicant_title' => 'Payout has been released.',
            'applicant_detail' => 'This application is complete.',
            'staff_title' => 'Application completed.',
            'staff_detail' => 'No further action is needed unless records or reporting must be reviewed.',
            'next_action_label' => 'Completed',
            'tone' => 'success',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'short_label' => 'Rejected',
            'applicant_title' => 'This application was not approved.',
            'applicant_detail' => 'Review the notes for the recorded reason.',
            'staff_title' => 'Application closed as rejected.',
            'staff_detail' => 'No further workflow action is available for this record.',
            'next_action_label' => 'Closed',
            'tone' => 'danger',
        ],
    ];

    return $map[$status] ?? [
        'label' => ucwords(str_replace('_', ' ', $status !== '' ? $status : 'unknown')),
        'short_label' => ucwords(str_replace('_', ' ', $status !== '' ? $status : 'unknown')),
        'applicant_title' => 'Application status updated.',
        'applicant_detail' => 'Please check the latest application details for the next step.',
        'staff_title' => 'Review application status.',
        'staff_detail' => 'Check the record and continue the workflow as needed.',
        'next_action_label' => 'Review record',
        'tone' => 'secondary',
    ];
}

function application_status_label(string $status): string
{
    return (string) (application_status_meta($status)['label'] ?? ucwords(str_replace('_', ' ', $status)));
}

function application_staff_status_label(string $status): string
{
    $meta = application_status_meta($status);
    return (string) ($meta['staff_label'] ?? $meta['label'] ?? ucwords(str_replace('_', ' ', $status)));
}

function application_staff_queue_label(string $queue): string
{
    return match (trim($queue)) {
        'all' => 'All Queues',
        'under_review' => 'Review',
        'compliance' => 'Compliance',
        'for_interview' => 'Interview',
        'approved_for_release' => 'Release',
        'completed' => 'Completed',
        default => ucwords(str_replace('_', ' ', trim($queue))),
    };
}

function application_workflow_steps(): array
{
    return [
        'under_review',
        'needs_resubmission',
        'for_interview',
        'for_soa',
        'approved_for_release',
        'released',
    ];
}

function application_timeline_steps(string $status): array
{
    $steps = [];
    $workflow = application_workflow_steps();
    $currentIndex = array_search($status, $workflow, true);

    foreach ($workflow as $index => $stepStatus) {
        $meta = application_status_meta($stepStatus);
        $state = 'upcoming';
        if ($status === 'rejected') {
            $state = $stepStatus === 'under_review' ? 'current' : 'upcoming';
        } elseif ($status === 'needs_resubmission') {
            if ($stepStatus === 'under_review') {
                $state = 'complete';
            } elseif ($stepStatus === 'needs_resubmission') {
                $state = 'current';
            }
        } elseif ($currentIndex !== false) {
            if ($index < $currentIndex) {
                $state = 'complete';
            } elseif ($index === $currentIndex) {
                $state = 'current';
            }
        }

        $steps[] = [
            'status' => $stepStatus,
            'label' => (string) ($meta['label'] ?? application_status_label($stepStatus)),
            'short_label' => (string) ($meta['short_label'] ?? application_status_label($stepStatus)),
            'state' => $state,
        ];
    }

    if ($status === 'rejected') {
        $steps[] = [
            'status' => 'rejected',
            'label' => 'Rejected',
            'short_label' => 'Rejected',
            'state' => 'current',
        ];
    }

    return $steps;
}

function application_next_action_summary(array $application, string $audience = 'applicant'): array
{
    $status = trim((string) ($application['status'] ?? ''));
    $meta = application_status_meta($status);
    $isStaff = strtolower(trim($audience)) === 'staff';

    $title = (string) ($meta[$isStaff ? 'staff_title' : 'applicant_title'] ?? 'Review application status.');
    $detail = (string) ($meta[$isStaff ? 'staff_detail' : 'applicant_detail'] ?? '');

    if (!$isStaff && $status === 'needs_resubmission') {
        $rejectedCount = (int) ($application['rejected_document_count'] ?? 0);
        if ($rejectedCount > 0) {
            $title = 'Upload ' . $rejectedCount . ' replacement document' . ($rejectedCount === 1 ? '' : 's') . '.';
        }
    }

    if (!$isStaff && $status === 'for_interview' && !empty($application['interview_date']) && !empty($application['interview_location'])) {
        $title = 'Attend the scheduled interview.';
        $detail = 'Interview schedule: '
            . date('M d, Y h:i A', strtotime((string) $application['interview_date']))
            . ' at '
            . trim((string) $application['interview_location']) . '.';
    }

    if (!$isStaff && $status === 'for_soa' && !empty($application['soa_submission_deadline'])) {
        if (!empty($application['soa_submitted_at'])) {
            $title = 'SOA submitted successfully.';
            $detail = 'Your uploaded SOA is being reviewed by the scholarship office.';
        } else {
            $detail = 'Submit the SOA on or before '
                . date('M d, Y', strtotime((string) $application['soa_submission_deadline']))
                . '.';
        }
    }

    if ($isStaff && $status === 'for_interview') {
        $hasInterviewSchedule = trim((string) ($application['interview_date'] ?? '')) !== ''
            && trim((string) ($application['interview_location'] ?? '')) !== '';
        if ($hasInterviewSchedule) {
            $title = 'Interview scheduled. Move qualified applicants to SOA after completion.';
            $detail = 'Current schedule: '
                . date('M d, Y h:i A', strtotime((string) $application['interview_date']))
                . ' at '
                . trim((string) ($application['interview_location'] ?? '')) . '.';
        }
    }

    return [
        'title' => $title,
        'detail' => $detail,
        'action_label' => (string) ($meta['next_action_label'] ?? 'Review record'),
        'label' => $isStaff ? application_staff_status_label($status) : (string) ($meta['label'] ?? application_status_label($status)),
    ];
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'under_review' => 'text-bg-info',
        'needs_resubmission' => 'text-bg-warning',
        'for_interview' => 'text-bg-warning',
        'for_soa' => 'text-bg-warning',
        'approved_for_release' => 'text-bg-secondary',
        'released' => 'text-bg-success',
        'scheduled' => 'text-bg-info',
        'cancelled' => 'text-bg-danger',
        'success' => 'text-bg-success',
        'failed' => 'text-bg-danger',
        'queued' => 'text-bg-secondary',
        'rejected' => 'text-bg-danger',
        default => 'text-bg-light'
    };
}

function application_status_group(string $status): string
{
    return match ($status) {
        'released' => 'released',
        'rejected' => 'rejected',
        default => 'in_progress',
    };
}

function application_status_group_label(string $status): string
{
    return match (application_status_group($status)) {
        'released' => 'Released',
        'rejected' => 'Rejected',
        default => 'In Progress',
    };
}

function application_status_group_badge_class(string $status): string
{
    return match (application_status_group($status)) {
        'released' => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        default => 'text-bg-warning',
    };
}

function excerpt(string $text, int $length = 160): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }

    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length - 3) . '...';
}
