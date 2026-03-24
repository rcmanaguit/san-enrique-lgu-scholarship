<?php

namespace App\Controllers;

use App\Config\Database;

class ReportController
{
    private const REPORT_TYPES = [
        'application_summary' => 'Application Summary',
        'application_funnel' => 'Application Funnel Analytics',
        'processing_analytics' => 'Processing Time Analytics',
        'scholar_distribution' => 'Scholar Distribution',
        'interview_outcomes' => 'Interview Outcomes',
        'payout_report' => 'Payout Report',
        'budget_analytics' => 'Budget Analytics',
        'document_compliance' => 'Document Compliance',
        'application_trends' => 'Application Trends',
    ];

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
            header('Location: ' . \base_url('login'));
            exit;
        }
    }

    public function index()
    {
        $db = Database::connect();
        $filters = $this->collectFilters();
        $result = $this->buildReport($db, $filters);
        $reportOptions = self::REPORT_TYPES;
        $schoolYearOptions = $this->fetchDistinctValues($db, 'applications', 'school_year');
        $semesterOptions = $this->fetchDistinctValues($db, 'applications', 'semester');
        $barangayOptions = $this->fetchDistinctValues($db, 'student_profiles', 'address_barangay');
        $schoolTypeOptions = $this->fetchDistinctValues($db, 'student_profiles', 'school_type');

        if (($filters['export'] ?? '') === 'excel') {
            $this->streamOfficeDocument($filters['report'], $result['title'], $result['columns'], $result['rows'], 'excel');
        }

        if (($filters['export'] ?? '') === 'word') {
            $this->streamOfficeDocument($filters['report'], $result['title'], $result['columns'], $result['rows'], 'word');
        }

        require __DIR__ . '/../../views/admin/reports.php';
    }

    private function collectFilters(): array
    {
        $report = trim((string) ($_GET['report'] ?? 'application_summary'));
        if (!array_key_exists($report, self::REPORT_TYPES)) {
            $report = 'application_summary';
        }

        return [
            'report' => $report,
            'school_year' => trim((string) ($_GET['school_year'] ?? '')),
            'semester' => trim((string) ($_GET['semester'] ?? '')),
            'barangay' => trim((string) ($_GET['barangay'] ?? '')),
            'school_type' => trim((string) ($_GET['school_type'] ?? '')),
            'export' => trim((string) ($_GET['export'] ?? '')),
        ];
    }

    private function buildReport(\PDO $db, array $filters): array
    {
        return match ($filters['report']) {
            'application_funnel' => $this->buildApplicationFunnelReport($db, $filters),
            'processing_analytics' => $this->buildProcessingAnalyticsReport($db, $filters),
            'scholar_distribution' => $this->buildScholarDistributionReport($db, $filters),
            'interview_outcomes' => $this->buildInterviewOutcomesReport($db, $filters),
            'payout_report' => $this->buildPayoutReport($db, $filters),
            'budget_analytics' => $this->buildBudgetAnalyticsReport($db, $filters),
            'document_compliance' => $this->buildDocumentComplianceReport($db, $filters),
            'application_trends' => $this->buildApplicationTrendsReport($db, $filters),
            default => $this->buildApplicationSummaryReport($db, $filters),
        };
    }

    private function buildApplicationFunnelReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters);
        $stmt = $db->prepare("
            SELECT a.status, COUNT(*) AS total_applications
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE $whereSql
            GROUP BY a.status
        ");
        $stmt->execute($params);
        $statusCounts = [];
        foreach ($stmt->fetchAll() as $row) {
            $statusCounts[(string) ($row['status'] ?? '')] = (int) ($row['total_applications'] ?? 0);
        }

        $stages = [
            ['stage_label' => 'Submitted / Initial Review', 'statuses' => ['Submitted', 'Initial_Review']],
            ['stage_label' => 'For Corrections', 'statuses' => ['Pending_Resubmission', 'SOA_Resubmission_Required', 'SOA_Overdue']],
            ['stage_label' => 'Interview Stage', 'statuses' => ['For_Interview']],
            ['stage_label' => 'SOA Review', 'statuses' => ['Eligible_Awaiting_SOA', 'SOA_Under_Review']],
            ['stage_label' => 'Approved', 'statuses' => ['Approved_Pending_Payroll', 'Approved_Finished']],
            ['stage_label' => 'Closed', 'statuses' => ['Not_Eligible', 'Forfeited']],
        ];

        $totalApplications = array_sum($statusCounts);
        $approvedCount = ($statusCounts['Approved_Pending_Payroll'] ?? 0) + ($statusCounts['Approved_Finished'] ?? 0);
        $closedCount = ($statusCounts['Not_Eligible'] ?? 0) + ($statusCounts['Forfeited'] ?? 0);
        $rows = [];

        foreach ($stages as $index => $stage) {
            $count = 0;
            foreach ($stage['statuses'] as $status) {
                $count += $statusCounts[$status] ?? 0;
            }

            $rows[] = [
                'stage_order' => $index + 1,
                'stage_label' => (string) $stage['stage_label'],
                'applications' => $count,
                'share_of_total' => $this->formatPercentage($count, $totalApplications),
            ];
        }

        return [
            'title' => self::REPORT_TYPES['application_funnel'],
            'description' => 'High-level workflow funnel showing how applications are distributed across the major processing stages.',
            'columns' => [
                'stage_order' => '#',
                'stage_label' => 'Funnel Stage',
                'applications' => 'Applications',
                'share_of_total' => 'Share of Total',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Applications in Funnel', 'value' => number_format($totalApplications)],
                ['label' => 'Approved Rate', 'value' => $this->formatPercentage($approvedCount, $totalApplications)],
                ['label' => 'Closed Rate', 'value' => $this->formatPercentage($closedCount, $totalApplications)],
            ],
            'charts' => [
                [
                    'id' => 'applicationFunnelChart',
                    'type' => 'bar',
                    'title' => 'Application Funnel',
                    'labels' => array_map(static fn(array $row): string => (string) $row['stage_label'], $rows),
                    'datasets' => [
                        [
                            'label' => 'Applications',
                            'data' => array_map(static fn(array $row): int => (int) ($row['applications'] ?? 0), $rows),
                            'backgroundColor' => ['#0d6efd', '#f39c12', '#6f42c1', '#20c997', '#198754', '#dc3545'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildProcessingAnalyticsReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters);
        $stmt = $db->prepare("
            SELECT
                a.status AS status_label,
                COUNT(*) AS applications,
                AVG(TIMESTAMPDIFF(DAY, a.created_at, COALESCE(a.interview_result_at, a.updated_at, a.created_at))) AS avg_processing_days,
                MAX(TIMESTAMPDIFF(DAY, a.created_at, COALESCE(a.interview_result_at, a.updated_at, a.created_at))) AS max_processing_days,
                SUM(
                    CASE
                        WHEN TIMESTAMPDIFF(DAY, a.created_at, COALESCE(a.interview_result_at, a.updated_at, a.created_at)) <= 7 THEN 1
                        ELSE 0
                    END
                ) AS within_7_days
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE $whereSql
            GROUP BY a.status
            ORDER BY avg_processing_days DESC, applications DESC
        ");
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll();

        $rows = array_map(function (array $row): array {
            $applications = (int) ($row['applications'] ?? 0);
            $within7 = (int) ($row['within_7_days'] ?? 0);

            return [
                'status_label' => str_replace('_', ' ', (string) ($row['status_label'] ?? 'Unknown')),
                'applications' => $applications,
                'avg_processing_days' => number_format((float) ($row['avg_processing_days'] ?? 0), 1),
                'max_processing_days' => (int) ($row['max_processing_days'] ?? 0),
                'within_7_days_rate' => $this->formatPercentage($within7, $applications),
            ];
        }, $rawRows);

        $totalApplications = array_sum(array_map(static fn(array $row): int => (int) ($row['applications'] ?? 0), $rawRows));
        $weightedDays = 0.0;
        $within7Total = 0;
        foreach ($rawRows as $row) {
            $weightedDays += (float) ($row['avg_processing_days'] ?? 0) * (int) ($row['applications'] ?? 0);
            $within7Total += (int) ($row['within_7_days'] ?? 0);
        }
        $overallAverage = $totalApplications > 0 ? $weightedDays / $totalApplications : 0.0;

        return [
            'title' => self::REPORT_TYPES['processing_analytics'],
            'description' => 'Average processing time by current application status, including same-week handling rate.',
            'columns' => [
                'status_label' => 'Current Status',
                'applications' => 'Applications',
                'avg_processing_days' => 'Avg Days in Process',
                'max_processing_days' => 'Longest Case (Days)',
                'within_7_days_rate' => 'Handled Within 7 Days',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Tracked Applications', 'value' => number_format($totalApplications)],
                ['label' => 'Overall Avg Days', 'value' => number_format($overallAverage, 1)],
                ['label' => 'Within 7 Days', 'value' => $this->formatPercentage($within7Total, $totalApplications)],
            ],
            'charts' => [
                [
                    'id' => 'processingAverageChart',
                    'type' => 'bar',
                    'title' => 'Average Processing Days by Status',
                    'labels' => array_column($rows, 'status_label'),
                    'datasets' => [
                        [
                            'label' => 'Avg Days',
                            'data' => array_map(static fn(array $row): float => (float) ($row['avg_processing_days'] ?? 0), $rows),
                            'backgroundColor' => '#fd7e14',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildApplicationSummaryReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters);
        $stmt = $db->prepare("
            SELECT a.status AS status_label, COUNT(*) AS total_applications
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE $whereSql
            GROUP BY a.status
            ORDER BY total_applications DESC, a.status ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalApplications = array_sum(array_map(static fn($row) => (int) $row['total_applications'], $rows));
        $approvedApplications = 0;
        foreach ($rows as $row) {
            if (in_array($row['status_label'], ['Approved_Pending_Payroll', 'Approved_Finished'], true)) {
                $approvedApplications += (int) $row['total_applications'];
            }
        }

        return [
            'title' => self::REPORT_TYPES['application_summary'],
            'description' => 'Summary of applications grouped by processing status.',
            'columns' => [
                'status_label' => 'Application Status',
                'total_applications' => 'Total Applications',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total Applications', 'value' => number_format($totalApplications)],
                ['label' => 'Approved Applications', 'value' => number_format($approvedApplications)],
                ['label' => 'Distinct Statuses', 'value' => number_format(count($rows))],
            ],
            'charts' => [
                [
                    'id' => 'applicationStatusChart',
                    'type' => 'bar',
                    'title' => 'Applications by Status',
                    'labels' => array_map(static fn ($row) => str_replace('_', ' ', (string) ($row['status_label'] ?? '')), $rows),
                    'datasets' => [
                        [
                            'label' => 'Applications',
                            'data' => array_map(static fn ($row) => (int) ($row['total_applications'] ?? 0), $rows),
                            'backgroundColor' => '#0d6efd',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildScholarDistributionReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters, true);
        $stmt = $db->prepare("
            SELECT
                p.address_barangay AS barangay,
                p.school_type,
                COUNT(*) AS approved_scholars,
                COALESCE(SUM(a.final_grant_amount), 0) AS total_grant_amount
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE $whereSql
              AND a.status IN ('Approved_Pending_Payroll', 'Approved_Finished')
            GROUP BY p.address_barangay, p.school_type
            ORDER BY approved_scholars DESC, p.address_barangay ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalScholars = array_sum(array_map(static fn($row) => (int) $row['approved_scholars'], $rows));
        $totalGrantAmount = array_sum(array_map(static fn($row) => (float) $row['total_grant_amount'], $rows));

        return [
            'title' => self::REPORT_TYPES['scholar_distribution'],
            'description' => 'Approved scholars broken down by barangay and institution type.',
            'columns' => [
                'barangay' => 'Barangay',
                'school_type' => 'School Type',
                'approved_scholars' => 'Approved Scholars',
                'total_grant_amount' => 'Total Grant Amount',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Approved Scholars', 'value' => number_format($totalScholars)],
                ['label' => 'Total Grant Amount', 'value' => 'PHP ' . number_format($totalGrantAmount, 2)],
                ['label' => 'Barangay Groups', 'value' => number_format(count($rows))],
            ],
            'money_columns' => ['total_grant_amount'],
            'charts' => [
                [
                    'id' => 'scholarDistributionChart',
                    'type' => 'bar',
                    'title' => 'Approved Scholars by Barangay',
                    'labels' => array_map(static fn ($row) => (string) ($row['barangay'] ?? 'Unknown'), $rows),
                    'datasets' => [
                        [
                            'label' => 'Approved Scholars',
                            'data' => array_map(static fn ($row) => (int) ($row['approved_scholars'] ?? 0), $rows),
                            'backgroundColor' => '#198754',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildPayoutReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters, true);
        $stmt = $db->prepare("
            SELECT
                CONCAT(p.last_name, ', ', p.first_name) AS scholar_name,
                p.address_barangay AS barangay,
                p.school_name,
                a.school_year,
                a.semester,
                a.status AS payout_status,
                COALESCE(a.final_grant_amount, 0) AS approved_amount,
                COALESCE(b.batch_name, 'Unassigned') AS payout_batch,
                COALESCE(DATE_FORMAT(b.scheduled_date, '%Y-%m-%d %H:%i'), 'Not Scheduled') AS payout_schedule,
                COALESCE(b.venue, 'Not Scheduled') AS venue
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            LEFT JOIN batches b ON a.payout_batch_id = b.id
            WHERE $whereSql
              AND a.status IN ('Approved_Pending_Payroll', 'Approved_Finished')
            ORDER BY b.scheduled_date DESC, p.last_name ASC, p.first_name ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalAmount = array_sum(array_map(static fn($row) => (float) $row['approved_amount'], $rows));

        return [
            'title' => self::REPORT_TYPES['payout_report'],
            'description' => 'Approved payouts, assigned batches, schedules, and release venues.',
            'columns' => [
                'scholar_name' => 'Scholar Name',
                'barangay' => 'Barangay',
                'school_name' => 'School',
                'school_year' => 'School Year',
                'semester' => 'Semester',
                'payout_status' => 'Status',
                'approved_amount' => 'Approved Amount',
                'payout_batch' => 'Payout Batch',
                'payout_schedule' => 'Payout Schedule',
                'venue' => 'Venue',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Scholars Listed', 'value' => number_format(count($rows))],
                ['label' => 'Total Approved Amount', 'value' => 'PHP ' . number_format($totalAmount, 2)],
                ['label' => 'Assigned Batches', 'value' => number_format(count(array_unique(array_column($rows, 'payout_batch'))))],
            ],
            'money_columns' => ['approved_amount'],
            'charts' => [
                [
                    'id' => 'payoutStatusChart',
                    'type' => 'doughnut',
                    'title' => 'Payout Status',
                    'labels' => array_values(array_keys(array_reduce($rows, static function ($carry, $row) {
                        $label = (string) ($row['payout_status'] ?? 'Unknown');
                        $carry[$label] = ($carry[$label] ?? 0) + 1;
                        return $carry;
                    }, []))),
                    'datasets' => [
                        [
                            'label' => 'Scholars',
                            'data' => array_values(array_reduce($rows, static function ($carry, $row) {
                                $label = (string) ($row['payout_status'] ?? 'Unknown');
                                $carry[$label] = ($carry[$label] ?? 0) + 1;
                                return $carry;
                            }, [])),
                            'backgroundColor' => ['#0d6efd', '#198754', '#f39c12', '#6c757d'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildBudgetAnalyticsReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters, true);
        $stmt = $db->prepare("
            SELECT
                CONCAT(a.school_year, ' | ', a.semester) AS release_period,
                COUNT(*) AS approved_scholars,
                COALESCE(SUM(a.final_grant_amount), 0) AS total_approved_amount,
                COALESCE(AVG(a.final_grant_amount), 0) AS average_grant_amount,
                COALESCE(MIN(a.final_grant_amount), 0) AS minimum_grant_amount,
                COALESCE(MAX(a.final_grant_amount), 0) AS maximum_grant_amount
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE $whereSql
              AND a.status IN ('Approved_Pending_Payroll', 'Approved_Finished')
            GROUP BY a.school_year, a.semester
            ORDER BY a.school_year DESC, FIELD(a.semester, '1st Semester', '2nd Semester')
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalBudget = array_sum(array_map(static fn(array $row): float => (float) ($row['total_approved_amount'] ?? 0), $rows));
        $totalScholars = array_sum(array_map(static fn(array $row): int => (int) ($row['approved_scholars'] ?? 0), $rows));
        $overallAverageGrant = $totalScholars > 0 ? $totalBudget / $totalScholars : 0.0;

        return [
            'title' => self::REPORT_TYPES['budget_analytics'],
            'description' => 'Budget allocation patterns by school year and semester for approved scholars.',
            'columns' => [
                'release_period' => 'Release Period',
                'approved_scholars' => 'Approved Scholars',
                'total_approved_amount' => 'Total Approved Amount',
                'average_grant_amount' => 'Average Grant',
                'minimum_grant_amount' => 'Minimum Grant',
                'maximum_grant_amount' => 'Maximum Grant',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Approved Scholars', 'value' => number_format($totalScholars)],
                ['label' => 'Total Budget', 'value' => 'PHP ' . number_format($totalBudget, 2)],
                ['label' => 'Average Grant', 'value' => 'PHP ' . number_format($overallAverageGrant, 2)],
            ],
            'money_columns' => ['total_approved_amount', 'average_grant_amount', 'minimum_grant_amount', 'maximum_grant_amount'],
            'charts' => [
                [
                    'id' => 'budgetAnalyticsChart',
                    'type' => 'bar',
                    'title' => 'Approved Budget by Period',
                    'labels' => array_map(static fn(array $row): string => (string) ($row['release_period'] ?? ''), $rows),
                    'datasets' => [
                        [
                            'label' => 'Total Approved Amount',
                            'data' => array_map(static fn(array $row): float => (float) ($row['total_approved_amount'] ?? 0), $rows),
                            'backgroundColor' => '#198754',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildDocumentComplianceReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters, true);
        $stmt = $db->prepare("
            SELECT
                CONCAT(p.last_name, ', ', p.first_name) AS scholar_name,
                a.school_year,
                a.semester,
                d.document_type,
                d.status AS document_status,
                COALESCE(d.rejection_remarks, '') AS rejection_remarks,
                COALESCE(DATE_FORMAT(d.updated_at, '%Y-%m-%d %H:%i'), DATE_FORMAT(d.uploaded_at, '%Y-%m-%d %H:%i')) AS last_updated
            FROM documents d
            JOIN applications a ON d.application_id = a.id
            JOIN student_profiles p ON a.student_id = p.id
            WHERE $whereSql
            ORDER BY d.updated_at DESC, p.last_name ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $pendingCount = 0;
        $rejectedCount = 0;
        foreach ($rows as $row) {
            if ($row['document_status'] === 'Pending') {
                $pendingCount++;
            }
            if ($row['document_status'] === 'Rejected') {
                $rejectedCount++;
            }
        }

        return [
            'title' => self::REPORT_TYPES['document_compliance'],
            'description' => 'Document status tracking for grades, residency, and SOA submissions.',
            'columns' => [
                'scholar_name' => 'Scholar Name',
                'school_year' => 'School Year',
                'semester' => 'Semester',
                'document_type' => 'Document Type',
                'document_status' => 'Document Status',
                'rejection_remarks' => 'Rejection Remarks',
                'last_updated' => 'Last Updated',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Documents Listed', 'value' => number_format(count($rows))],
                ['label' => 'Pending Documents', 'value' => number_format($pendingCount)],
                ['label' => 'Rejected Documents', 'value' => number_format($rejectedCount)],
            ],
            'charts' => [
                [
                    'id' => 'documentComplianceChart',
                    'type' => 'bar',
                    'title' => 'Documents by Status',
                    'labels' => array_values(array_keys(array_reduce($rows, static function ($carry, $row) {
                        $label = (string) ($row['document_status'] ?? 'Unknown');
                        $carry[$label] = ($carry[$label] ?? 0) + 1;
                        return $carry;
                    }, []))),
                    'datasets' => [
                        [
                            'label' => 'Documents',
                            'data' => array_values(array_reduce($rows, static function ($carry, $row) {
                                $label = (string) ($row['document_status'] ?? 'Unknown');
                                $carry[$label] = ($carry[$label] ?? 0) + 1;
                                return $carry;
                            }, [])),
                            'backgroundColor' => '#f39c12',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildInterviewOutcomesReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters, true);
        $stmt = $db->prepare("
            SELECT
                COALESCE(a.interview_result, 'Pending') AS interview_outcome,
                COUNT(*) AS applicants,
                SUM(
                    CASE
                        WHEN a.status IN ('Eligible_Awaiting_SOA', 'SOA_Under_Review', 'SOA_Resubmission_Required', 'Approved_Pending_Payroll', 'Approved_Finished') THEN 1
                        ELSE 0
                    END
                ) AS moved_forward
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE $whereSql
              AND (
                    a.interview_result IS NOT NULL
                    OR a.status IN ('For_Interview', 'Eligible_Awaiting_SOA', 'SOA_Under_Review', 'SOA_Resubmission_Required', 'Approved_Pending_Payroll', 'Approved_Finished', 'Not_Eligible', 'Forfeited')
                  )
            GROUP BY COALESCE(a.interview_result, 'Pending')
            ORDER BY applicants DESC, interview_outcome ASC
        ");
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll();

        $rows = array_map(function (array $row): array {
            $applicants = (int) ($row['applicants'] ?? 0);
            $movedForward = (int) ($row['moved_forward'] ?? 0);

            return [
                'interview_outcome' => (string) ($row['interview_outcome'] ?? 'Pending'),
                'applicants' => $applicants,
                'moved_forward' => $movedForward,
                'advance_rate' => $this->formatPercentage($movedForward, $applicants),
            ];
        }, $rawRows);

        $totalApplicants = array_sum(array_map(static fn(array $row): int => (int) ($row['applicants'] ?? 0), $rawRows));
        $passedCount = 0;
        $absentCount = 0;
        foreach ($rawRows as $row) {
            if ((string) ($row['interview_outcome'] ?? '') === 'Passed') {
                $passedCount = (int) ($row['applicants'] ?? 0);
            }
            if ((string) ($row['interview_outcome'] ?? '') === 'Absent') {
                $absentCount = (int) ($row['applicants'] ?? 0);
            }
        }

        return [
            'title' => self::REPORT_TYPES['interview_outcomes'],
            'description' => 'Interview outcomes with follow-through rates into the next workflow stages.',
            'columns' => [
                'interview_outcome' => 'Interview Outcome',
                'applicants' => 'Applicants',
                'moved_forward' => 'Moved Forward',
                'advance_rate' => 'Advance Rate',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Interviewed / Tracked', 'value' => number_format($totalApplicants)],
                ['label' => 'Pass Rate', 'value' => $this->formatPercentage($passedCount, $totalApplicants)],
                ['label' => 'Absence Rate', 'value' => $this->formatPercentage($absentCount, $totalApplicants)],
            ],
            'charts' => [
                [
                    'id' => 'interviewOutcomeChart',
                    'type' => 'doughnut',
                    'title' => 'Interview Outcomes',
                    'labels' => array_map(static fn(array $row): string => (string) ($row['interview_outcome'] ?? 'Unknown'), $rows),
                    'datasets' => [
                        [
                            'label' => 'Applicants',
                            'data' => array_map(static fn(array $row): int => (int) ($row['applicants'] ?? 0), $rows),
                            'backgroundColor' => ['#198754', '#dc3545', '#6c757d', '#0d6efd'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildApplicationTrendsReport(\PDO $db, array $filters): array
    {
        [$whereSql, $params] = $this->buildApplicationFilterSql($filters);
        $stmt = $db->prepare("
            SELECT
                CONCAT(a.school_year, ' | ', a.semester) AS scholarship_period,
                COUNT(*) AS submitted_applications,
                SUM(CASE WHEN a.status IN ('Approved_Pending_Payroll', 'Approved_Finished') THEN 1 ELSE 0 END) AS approved_applications,
                SUM(CASE WHEN a.status IN ('Pending_Resubmission', 'SOA_Resubmission_Required', 'Not_Eligible', 'Forfeited') THEN 1 ELSE 0 END) AS flagged_applications
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE $whereSql
            GROUP BY a.school_year, a.semester
            ORDER BY a.school_year DESC, FIELD(a.semester, '1st Semester', '2nd Semester')
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalSubmitted = array_sum(array_map(static fn($row) => (int) $row['submitted_applications'], $rows));

        return [
            'title' => self::REPORT_TYPES['application_trends'],
            'description' => 'Scholarship-period view of submissions, approvals, and flagged applications.',
            'columns' => [
                'scholarship_period' => 'Scholarship Period',
                'submitted_applications' => 'Submitted',
                'approved_applications' => 'Approved',
                'flagged_applications' => 'Flagged',
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Periods Covered', 'value' => number_format(count($rows))],
                ['label' => 'Submitted Applications', 'value' => number_format($totalSubmitted)],
                ['label' => 'Latest Period', 'value' => $rows[0]['scholarship_period'] ?? 'N/A'],
            ],
            'charts' => [
                [
                    'id' => 'applicationTrendsChart',
                    'type' => 'line',
                    'title' => 'Application Trends by Scholarship Period',
                    'labels' => array_reverse(array_map(static fn ($row) => (string) ($row['scholarship_period'] ?? ''), $rows)),
                    'datasets' => [
                        [
                            'label' => 'Submitted',
                            'data' => array_reverse(array_map(static fn ($row) => (int) ($row['submitted_applications'] ?? 0), $rows)),
                            'borderColor' => '#0d6efd',
                            'backgroundColor' => 'rgba(13, 110, 253, 0.12)',
                            'fill' => false,
                            'tension' => 0.25,
                        ],
                        [
                            'label' => 'Approved',
                            'data' => array_reverse(array_map(static fn ($row) => (int) ($row['approved_applications'] ?? 0), $rows)),
                            'borderColor' => '#198754',
                            'backgroundColor' => 'rgba(25, 135, 84, 0.12)',
                            'fill' => false,
                            'tension' => 0.25,
                        ],
                        [
                            'label' => 'Flagged',
                            'data' => array_reverse(array_map(static fn ($row) => (int) ($row['flagged_applications'] ?? 0), $rows)),
                            'borderColor' => '#dc3545',
                            'backgroundColor' => 'rgba(220, 53, 69, 0.12)',
                            'fill' => false,
                            'tension' => 0.25,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildApplicationFilterSql(array $filters, bool $includeProfileFilters = true): array
    {
        $conditions = ['1=1'];
        $params = [];

        if ($filters['school_year'] !== '') {
            $conditions[] = 'a.school_year = :school_year';
            $params['school_year'] = $filters['school_year'];
        }

        if ($filters['semester'] !== '') {
            $conditions[] = 'a.semester = :semester';
            $params['semester'] = $filters['semester'];
        }

        if ($includeProfileFilters && $filters['barangay'] !== '') {
            $conditions[] = 'p.address_barangay = :barangay';
            $params['barangay'] = $filters['barangay'];
        }

        if ($includeProfileFilters && $filters['school_type'] !== '') {
            $conditions[] = 'p.school_type = :school_type';
            $params['school_type'] = $filters['school_type'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function fetchDistinctValues(\PDO $db, string $table, string $column): array
    {
        $stmt = $db->query("SELECT DISTINCT $column AS value FROM $table WHERE $column IS NOT NULL AND $column <> '' ORDER BY $column ASC");
        return array_values(array_filter(array_map(static fn($row) => (string) ($row['value'] ?? ''), $stmt->fetchAll())));
    }

    private function streamOfficeDocument(string $reportKey, string $reportTitle, array $columns, array $rows, string $format): never
    {
        $timestamp = date('Ymd-His');
        if ($format === 'excel') {
            $filename = $reportKey . '-' . $timestamp . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        } else {
            $filename = $reportKey . '-' . $timestamp . '.doc';
            header('Content-Type: application/msword; charset=UTF-8');
        }
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo '<html><head><meta charset="UTF-8"><title>' . htmlspecialchars($reportTitle, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #1b2d3d; }
            h1 { font-size: 18px; margin-bottom: 6px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #9fb3c8; padding: 8px; text-align: left; vertical-align: top; }
            th { background: #dfeaf5; }
        </style></head><body>';
        echo '<h1>' . htmlspecialchars($reportTitle, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<table><thead><tr>';
        foreach ($columns as $columnLabel) {
            echo '<th>' . htmlspecialchars((string) $columnLabel, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach (array_keys($columns) as $columnKey) {
                echo '<td>' . htmlspecialchars((string) ($row[$columnKey] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    private function formatPercentage(int|float $value, int|float $total): string
    {
        if ((float) $total <= 0.0) {
            return '0.0%';
        }

        return number_format(((float) $value / (float) $total) * 100, 1) . '%';
    }
}
