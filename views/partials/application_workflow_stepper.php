<?php
$workflowSteps = is_array($workflowSteps ?? null) ? $workflowSteps : [];
$workflowLabel = trim((string) ($workflowLabel ?? 'Application workflow'));
?>
<div class="app-workflow-stepper" aria-label="<?php echo htmlspecialchars($workflowLabel); ?>">
    <?php foreach ($workflowSteps as $step): ?>
        <?php $state = (string) ($step['state'] ?? 'upcoming'); ?>
        <div class="app-workflow-step app-workflow-step-<?php echo htmlspecialchars($state); ?>">
            <span class="app-workflow-dot"></span>
            <span class="app-workflow-text"><?php echo htmlspecialchars((string) ($step['short_label'] ?? $step['label'] ?? 'Step')); ?></span>
        </div>
    <?php endforeach; ?>
</div>
