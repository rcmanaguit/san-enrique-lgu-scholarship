<?php
require __DIR__ . '/../layouts/header.php';

$filters = $filters ?? [];
$summaryCards = $result['summary'] ?? [];
$moneyColumns = $result['money_columns'] ?? [];
$charts = $result['charts'] ?? [];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <div class="row">

        <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 app-page-shell">

            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Reports</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-chart-column me-2"></i>Admin Reports</h1>
                    <p class="app-page-subtitle">Generate filtered reports for applications, scholars, documents, payouts, and trends.</p>
                </div>
                <div class="app-page-header-actions">
                    <a href="<?php echo htmlspecialchars(base_url('admin/reports') . '?' . http_build_query(array_merge($filters, ['export' => 'excel']))); ?>" class="btn btn-outline-success fw-bold">
                        <i class="fa-solid fa-file-excel me-2"></i>Excel
                    </a>
                    <a href="<?php echo htmlspecialchars(base_url('admin/reports') . '?' . http_build_query(array_merge($filters, ['export' => 'word']))); ?>" class="btn btn-outline-primary fw-bold">
                        <i class="fa-solid fa-file-word me-2"></i>Word
                    </a>
                    <button type="button" class="btn btn-success fw-bold" onclick="window.print();">
                        <i class="fa-solid fa-file-pdf me-2"></i>Print / Save as PDF
                    </button>
                </div>
            </section>

            <section class="app-surface mb-4 no-print">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title">Filter Options</h2>
                        <p class="app-surface-copy">Narrow the report output before exporting or printing.</p>
                    </div>
                </div>
                <div class="app-surface-body">
                    <form method="GET" action="<?php echo htmlspecialchars(base_url('admin/reports')); ?>" data-live-submit data-live-submit-delay="250">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4 col-xl-3">
                                <label class="form-label fw-bold">Report Type</label>
                                <select class="form-select" name="report">
                                    <?php foreach ($reportOptions as $reportKey => $reportLabel): ?>
                                        <option value="<?php echo htmlspecialchars($reportKey); ?>" <?php echo ($filters['report'] ?? '') === $reportKey ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($reportLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <label class="form-label fw-bold">School Year</label>
                                <select class="form-select" name="school_year">
                                    <option value="">All</option>
                                    <?php foreach ($schoolYearOptions as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filters['school_year'] ?? '') === $option ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <label class="form-label fw-bold">Semester</label>
                                <select class="form-select" name="semester">
                                    <option value="">All</option>
                                    <?php foreach ($semesterOptions as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filters['semester'] ?? '') === $option ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <label class="form-label fw-bold">Barangay</label>
                                <select class="form-select" name="barangay">
                                    <option value="">All</option>
                                    <?php foreach ($barangayOptions as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filters['barangay'] ?? '') === $option ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-xl-2">
                                <label class="form-label fw-bold">School Type</label>
                                <select class="form-select" name="school_type">
                                    <option value="">All</option>
                                    <?php foreach ($schoolTypeOptions as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($filters['school_type'] ?? '') === $option ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-xl-1 d-grid">
                                <a href="<?php echo htmlspecialchars(base_url('admin/reports')); ?>" class="btn btn-outline-secondary fw-bold">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <div class="row g-3 mb-4">
                <?php foreach ($summaryCards as $summaryCard): ?>
                    <div class="col-md-4">
                        <div class="app-stat-card h-100" style="--stat-accent: var(--lgu-primary);">
                            <div class="app-stat-card-body">
                                <div>
                                    <div class="app-stat-kicker"><?php echo htmlspecialchars((string) ($summaryCard['label'] ?? 'Summary')); ?></div>
                                    <p class="app-stat-value"><?php echo htmlspecialchars((string) ($summaryCard['value'] ?? '0')); ?></p>
                                </div>
                                <div class="app-stat-icon">
                                    <i class="fa-solid fa-chart-simple"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($charts)): ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($charts as $chart): ?>
                        <div class="col-lg-<?php echo count($charts) > 1 ? '6' : '12'; ?>">
                            <section class="app-surface h-100">
                                <div class="app-surface-header">
                                    <div>
                                        <h2 class="app-surface-title"><?php echo htmlspecialchars((string) ($chart['title'] ?? 'Chart')); ?></h2>
                                    </div>
                                </div>
                                <div class="app-surface-body">
                                    <canvas id="<?php echo htmlspecialchars((string) ($chart['id'] ?? 'reportChart')); ?>" height="120"></canvas>
                                </div>
                            </section>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="app-surface app-table-card">
                <div class="app-surface-header">
                    <div>
                        <h2 class="app-surface-title"><?php echo htmlspecialchars((string) ($result['title'] ?? 'Report')); ?></h2>
                        <p class="app-surface-copy"><?php echo htmlspecialchars((string) ($result['description'] ?? '')); ?></p>
                    </div>
                </div>
                <div class="p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 report-table">
                            <thead>
                                <tr>
                                    <?php foreach (($result['columns'] ?? []) as $columnLabel): ?>
                                        <th class="py-3 px-3"><?php echo htmlspecialchars((string) $columnLabel); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($result['rows'])): ?>
                                    <tr>
                                        <td colspan="<?php echo count($result['columns'] ?? []); ?>" class="p-0">
                                            <div class="app-empty-state">
                                                <div class="app-empty-state-icon"><i class="fa-solid fa-filter-circle-xmark"></i></div>
                                                <h3 class="app-empty-state-title">No matching records</h3>
                                                <p class="app-empty-state-copy">Try adjusting the report filters to widen the results.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($result['rows'] as $row): ?>
                                        <tr>
                                            <?php foreach (($result['columns'] ?? []) as $columnKey => $columnLabel): ?>
                                                <?php $value = $row[$columnKey] ?? ''; ?>
                                                <td class="px-3">
                                                    <?php if (in_array($columnKey, $moneyColumns, true)): ?>
                                                        <?php echo 'PHP ' . number_format((float) $value, 2); ?>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars((string) $value); ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </main>
    </div>
</div>

<?php if (!empty($charts)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const charts = <?php echo json_encode($charts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    charts.forEach(function (chartConfig) {
        const canvas = document.getElementById(chartConfig.id);
        if (!canvas) {
            return;
        }

        new Chart(canvas.getContext('2d'), {
            type: chartConfig.type || 'bar',
            data: {
                labels: chartConfig.labels || [],
                datasets: chartConfig.datasets || []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: (chartConfig.datasets || []).length > 1 || chartConfig.type === 'doughnut'
                    }
                },
                scales: chartConfig.type === 'doughnut' ? {} : {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    });
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
