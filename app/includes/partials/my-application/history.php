<?php
declare(strict_types=1);
?>
<?php if (db_ready() && !$applications): ?>
    <div class="card card-soft">
        <div class="card-body">
            <p class="text-muted mb-3">No application records found for this view.</p>
            <?php if ($canCreateNewApplication): ?>
                <a href="apply.php" class="btn btn-primary">Start Application</a>
            <?php else: ?>
                <button class="btn btn-secondary" disabled>Application Period Closed</button>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (db_ready()): ?>
    <?php if ($periodTimeline): ?>
        <details class="workflow-panel workflow-panel-collapsible applicant-history-panel mb-3">
            <summary>
                <span>Application History by Period</span>
                <small><?= count($periodTimeline) ?> period<?= count($periodTimeline) === 1 ? '' : 's' ?></small>
            </summary>
            <div class="workflow-panel-body">
                <div class="scholar-period-history">
                    <?php foreach ($periodTimeline as $timelineRow): ?>
                        <span class="scholar-period-chip">
                            <span class="scholar-period-chip__label"><?= e((string) ($timelineRow['period_label'] ?? 'Period')) ?></span>
                            <span class="badge <?= e((string) ($timelineRow['badge_class'] ?? 'text-bg-light')) ?>">
                                <?= e((string) ($timelineRow['label'] ?? 'No application')) ?>
                            </span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>
    <?php endif; ?>
    <details class="workflow-panel workflow-panel-collapsible applicant-history-panel">
        <summary>
            <span>Application History</span>
            <small><?= count($applications) ?> record<?= count($applications) === 1 ? '' : 's' ?></small>
        </summary>
        <div class="workflow-panel-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 applicant-history-table">
                    <thead>
                        <tr>
                            <th>Application Number</th>
                            <th>Period</th>
                            <th>Current Step</th>
                            <th>Next Action</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $row): ?>
                        <?php
                        $rowId = (int) ($row['id'] ?? 0);
                        $rowStatusCode = (string) ($row['status'] ?? '');
                        $row['rejected_document_count'] = count($resubmissionTargetsByAppId[$rowId] ?? []);
                        $rowNextAction = application_next_action_summary($row, 'applicant');
                        ?>
                        <tr class="applicant-history-row js-open-application-modal-row" data-app-id="<?= $rowId ?>" tabindex="0" role="button" aria-label="View details for application <?= e((string) $row['application_no']) ?>">
                            <td>
                                <div class="applicant-cell-label d-md-none">Application Number</div>
                                <strong class="applicant-app-link"><?= e((string) $row['application_no']) ?></strong>
                                <?php if ((int) ($row['is_archived'] ?? 0) === 1): ?>
                                    <span class="badge text-bg-secondary ms-1">Archived</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success ms-1">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="applicant-cell-label d-md-none">Period</div>
                                <span><?= e((string) $row['semester']) ?> / <?= e((string) $row['school_year']) ?></span>
                            </td>
                            <td>
                                <div class="applicant-cell-label d-md-none">Current Step</div>
                                <span class="badge <?= status_badge_class($rowStatusCode) ?>">
                                    <?= e(strtoupper(application_status_label($rowStatusCode))) ?>
                                </span>
                            </td>
                            <td>
                                <div class="applicant-cell-label d-md-none">Next Action</div>
                                <div class="small fw-semibold"><?= e((string) ($rowNextAction['title'] ?? 'Check status details.')) ?></div>
                            </td>
                            <td>
                                <div class="applicant-cell-label d-md-none">Updated</div>
                                <?= date('M d, Y h:i A', strtotime((string) $row['updated_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>
<?php endif; ?>
