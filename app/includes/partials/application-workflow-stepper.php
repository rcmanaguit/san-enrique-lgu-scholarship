<?php
declare(strict_types=1);

$timelineSteps = is_array($timelineSteps ?? null) ? $timelineSteps : [];
$stepperClass = trim((string) ($stepperClass ?? ''));
$stepperLabel = trim((string) ($stepperLabel ?? 'Application workflow progress'));
?>
<div class="application-stepper<?= $stepperClass !== '' ? ' ' . e($stepperClass) : '' ?>" aria-label="<?= e($stepperLabel) ?>">
    <?php foreach ($timelineSteps as $timelineStep): ?>
        <?php $timelineState = (string) ($timelineStep['state'] ?? 'upcoming'); ?>
        <div class="application-step application-step-<?= e($timelineState) ?>">
            <div class="application-step-dot"></div>
            <div class="application-step-label"><?= e((string) ($timelineStep['short_label'] ?? $timelineStep['label'] ?? 'Step')) ?></div>
        </div>
    <?php endforeach; ?>
</div>
