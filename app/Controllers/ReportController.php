<?php

namespace App\Controllers;

use App\Config\Database;

class ReportController
{
    private const REPORT_TYPES = [
        'application_summary' => 'Application Summary',
        'application_funnel' => 'Application Workflow Overview',
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
        $reports = $this->buildAllReports($db, $filters);
        $overviewSummary = $this->buildOverviewSummary($reports);
        $schoolYearOptions = $this->fetchDistinctValues($db, 'applications', 'school_year');
        $semesterOptions = $this->fetchDistinctValues($db, 'applications', 'semester');
        $barangayOptions = $this->fetchDistinctValues($db, 'student_profiles', 'address_barangay');
        $schoolTypeOptions = $this->fetchDistinctValues($db, 'student_profiles', 'school_type');

        if (($filters['export'] ?? '') === 'excel') {
            $this->streamCombinedOfficeDocument('admin-reports-dashboard', 'Admin Reports Dashboard', $reports, 'excel');
        }

        if (($filters['export'] ?? '') === 'word') {
            $this->streamCombinedOfficeDocument('admin-reports-dashboard', 'Admin Reports Dashboard', $reports, 'word');
        }

        require __DIR__ . '/../../views/admin/reports.php';
    }

    private function collectFilters(): array
    {
        return [
            'school_year' => trim((string) ($_GET['school_year'] ?? '')),
            'semester' => trim((string) ($_GET['semester'] ?? '')),
            'barangay' => trim((string) ($_GET['barangay'] ?? '')),
            'school_type' => trim((string) ($_GET['school_type'] ?? '')),
            'export' => trim((string) ($_GET['export'] ?? '')),
        ];
    }

    private function buildAllReports(\PDO $db, array $filters): array
    {
        return [
            'application_summary' => $this->buildApplicationSummaryReport($db, $filters),
            'application_funnel' => $this->buildApplicationFunnelReport($db, $filters),
            'scholar_distribution' => $this->buildScholarDistributionReport($db, $filters),
            'interview_outcomes' => $this->buildInterviewOutcomesReport($db, $filters),
            'payout_report' => $this->buildPayoutReport($db, $filters),
            'budget_analytics' => $this->buildBudgetAnalyticsReport($db, $filters),
            'document_compliance' => $this->buildDocumentComplianceReport($db, $filters),
            'application_trends' => $this->buildApplicationTrendsReport($db, $filters),
        ];
    }

    private function buildOverviewSummary(array $reports): array
    {
        $applicationSummary = $reports['application_summary']['rows'] ?? [];
        $payoutRows = $reports['payout_report']['rows'] ?? [];
        $documentRows = $reports['document_compliance']['rows'] ?? [];
        $workflowRows = $reports['application_funnel']['rows'] ?? [];

        $totalApplications = 0;
        $approvedApplications = 0;
        foreach ($applicationSummary as $row) {
            $count = (int) ($row['total_applications'] ?? 0);
            $totalApplications += $count;

            if (in_array((string) ($row['status_label'] ?? ''), ['Approved_Pending_Payroll', 'Approved_Finished'], true)) {
                $approvedApplications += $count;
            }
        }

        $forInterviewApplications = 0;
        $forCorrectionApplications = 0;
        foreach ($workflowRows as $row) {
            $stageLabel = (string) ($row['stage_label'] ?? '');
            if ($stageLabel === 'Interview Stage') {
                $forInterviewApplications = (int) ($row['applications'] ?? 0);
            }
            if ($stageLabel === 'For Corrections') {
                $forCorrectionApplications = (int) ($row['applications'] ?? 0);
            }
        }

        $pendingDocuments = 0;
        foreach ($documentRows as $row) {
            if ((string) ($row['document_status'] ?? '') === 'Pending') {
                $pendingDocuments++;
            }
        }

        $totalPayoutAmount = 0.0;
        foreach ($payoutRows as $row) {
            $totalPayoutAmount += (float) ($row['approved_amount'] ?? 0);
        }

        return [
            ['label' => 'Total Applications', 'value' => number_format($totalApplications)],
            ['label' => 'Approved Applications', 'value' => number_format($approvedApplications)],
            ['label' => 'For Interview', 'value' => number_format($forInterviewApplications)],
            ['label' => 'For Corrections', 'value' => number_format($forCorrectionApplications)],
            ['label' => 'Pending Documents', 'value' => number_format($pendingDocuments)],
            ['label' => 'Approved Payout Value', 'value' => 'PHP ' . number_format($totalPayoutAmount, 2)],
        ];
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

        $stageShareValues = array_map(static function (array $row) use ($totalApplications): float {
            $applications = (int) ($row['applications'] ?? 0);
            return $totalApplications > 0 ? round(($applications / $totalApplications) * 100, 1) : 0.0;
        }, $rows);

        return [
            'title' => self::REPORT_TYPES['application_funnel'],
            'description' => 'High-level workflow view showing how applications are distributed across the major processing stages.',
            'columns' => [
                'stage_order' => '#',
                'stage_label' => 'Workflow Stage',
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
                    'title' => 'Applications by Workflow Stage',
                    'labels' => array_map(static fn(array $row): string => (string) $row['stage_label'], $rows),
                    'datasets' => [
                        [
                            'label' => 'Applications',
                            'data' => array_map(static fn(array $row): int => (int) ($row['applications'] ?? 0), $rows),
                            'backgroundColor' => ['#0d6efd', '#f39c12', '#6f42c1', '#20c997', '#198754', '#dc3545'],
                        ],
                    ],
                ],
                [
                    'id' => 'applicationWorkflowShareChart',
                    'type' => 'doughnut',
                    'title' => 'Workflow Stage Share',
                    'labels' => array_map(static fn(array $row): string => (string) ($row['stage_label'] ?? ''), $rows),
                    'datasets' => [
                        [
                            'label' => 'Share %',
                            'data' => $stageShareValues,
                            'backgroundColor' => ['#0d6efd', '#f39c12', '#6f42c1', '#20c997', '#198754', '#dc3545'],
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
                [
                    'id' => 'applicationStatusShareChart',
                    'type' => 'doughnut',
                    'title' => 'Application Status Share',
                    'labels' => array_map(static fn ($row) => str_replace('_', ' ', (string) ($row['status_label'] ?? '')), $rows),
                    'datasets' => [
                        [
                            'label' => 'Applications',
                            'data' => array_map(static fn ($row) => (int) ($row['total_applications'] ?? 0), $rows),
                            'backgroundColor' => ['#0d6efd', '#6f42c1', '#f39c12', '#20c997', '#198754', '#dc3545', '#6c757d', '#6610f2', '#fd7e14', '#0dcaf0'],
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
        $schoolTypeTotals = [];
        foreach ($rows as $row) {
            $schoolType = (string) ($row['school_type'] ?? 'Unknown');
            $schoolTypeTotals[$schoolType] = ($schoolTypeTotals[$schoolType] ?? 0) + (int) ($row['approved_scholars'] ?? 0);
        }

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
                [
                    'id' => 'scholarDistributionTypeChart',
                    'type' => 'doughnut',
                    'title' => 'Approved Scholars by School Type',
                    'labels' => array_keys($schoolTypeTotals),
                    'datasets' => [
                        [
                            'label' => 'Approved Scholars',
                            'data' => array_values($schoolTypeTotals),
                            'backgroundColor' => ['#198754', '#0d6efd', '#f39c12', '#6f42c1'],
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
        $batchTotals = [];
        foreach ($rows as $row) {
            $batch = (string) ($row['payout_batch'] ?? 'Unassigned');
            $batchTotals[$batch] = ($batchTotals[$batch] ?? 0.0) + (float) ($row['approved_amount'] ?? 0);
        }

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
                [
                    'id' => 'payoutBatchAmountChart',
                    'type' => 'bar',
                    'title' => 'Approved Amount by Payout Batch',
                    'labels' => array_keys($batchTotals),
                    'datasets' => [
                        [
                            'label' => 'Approved Amount',
                            'data' => array_values($batchTotals),
                            'backgroundColor' => '#20c997',
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
                [
                    'id' => 'budgetAverageGrantChart',
                    'type' => 'line',
                    'title' => 'Average Grant by Period',
                    'labels' => array_map(static fn(array $row): string => (string) ($row['release_period'] ?? ''), $rows),
                    'datasets' => [
                        [
                            'label' => 'Average Grant',
                            'data' => array_map(static fn(array $row): float => (float) ($row['average_grant_amount'] ?? 0), $rows),
                            'borderColor' => '#0d6efd',
                            'backgroundColor' => 'rgba(13, 110, 253, 0.12)',
                            'fill' => false,
                            'tension' => 0.25,
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
        $documentTypeTotals = [];
        foreach ($rows as $row) {
            $documentType = (string) ($row['document_type'] ?? 'Unknown');
            $documentTypeTotals[$documentType] = ($documentTypeTotals[$documentType] ?? 0) + 1;
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
                [
                    'id' => 'documentTypeChart',
                    'type' => 'doughnut',
                    'title' => 'Documents by Type',
                    'labels' => array_keys($documentTypeTotals),
                    'datasets' => [
                        [
                            'label' => 'Documents',
                            'data' => array_values($documentTypeTotals),
                            'backgroundColor' => ['#0d6efd', '#f39c12', '#dc3545', '#20c997', '#6f42c1'],
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
                [
                    'id' => 'interviewAdvanceRateChart',
                    'type' => 'bar',
                    'title' => 'Advance Rate by Interview Outcome',
                    'labels' => array_map(static fn(array $row): string => (string) ($row['interview_outcome'] ?? 'Unknown'), $rows),
                    'datasets' => [
                        [
                            'label' => 'Advance Rate %',
                            'data' => array_map(static fn(array $row): float => (float) rtrim((string) ($row['advance_rate'] ?? '0%'), '%'), $rows),
                            'backgroundColor' => '#6f42c1',
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
                [
                    'id' => 'applicationApprovalRateChart',
                    'type' => 'bar',
                    'title' => 'Approval Rate by Scholarship Period',
                    'labels' => array_reverse(array_map(static fn ($row) => (string) ($row['scholarship_period'] ?? ''), $rows)),
                    'datasets' => [
                        [
                            'label' => 'Approval Rate %',
                            'data' => array_reverse(array_map(static function ($row): float {
                                $submitted = (int) ($row['submitted_applications'] ?? 0);
                                $approved = (int) ($row['approved_applications'] ?? 0);
                                return $submitted > 0 ? round(($approved / $submitted) * 100, 1) : 0.0;
                            }, $rows)),
                            'backgroundColor' => '#198754',
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

    private function streamCombinedOfficeDocument(string $reportKey, string $reportTitle, array $reports, string $format): never
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
            h1 { font-size: 20px; margin-bottom: 12px; }
            h2 { font-size: 16px; margin: 28px 0 8px; }
            p { margin: 0 0 8px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
            th, td { border: 1px solid #9fb3c8; padding: 8px; text-align: left; vertical-align: top; }
            th { background: #dfeaf5; }
        </style></head><body>';
        echo '<h1>' . htmlspecialchars($reportTitle, ENT_QUOTES, 'UTF-8') . '</h1>';

        foreach ($reports as $report) {
            $title = (string) ($report['title'] ?? 'Report');
            $description = (string) ($report['description'] ?? '');
            $columns = $report['columns'] ?? [];
            $rows = $report['rows'] ?? [];

            echo '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
            if ($description !== '') {
                echo '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
            }

            echo '<table><thead><tr>';
            foreach ($columns as $columnLabel) {
                echo '<th>' . htmlspecialchars((string) $columnLabel, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            echo '</tr></thead><tbody>';

            if ($rows === []) {
                echo '<tr><td colspan="' . max(1, count($columns)) . '">No matching records.</td></tr>';
            } else {
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach (array_keys($columns) as $columnKey) {
                        echo '<td>' . htmlspecialchars((string) ($row[$columnKey] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    }
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
        }

        echo '</body></html>';
        exit;
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
