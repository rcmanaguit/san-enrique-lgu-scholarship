<?php
declare(strict_types=1);

$pageHeaderEyebrow = isset($pageHeaderEyebrow) ? trim((string) $pageHeaderEyebrow) : '';
$pageHeaderTitle = isset($pageHeaderTitle) ? trim((string) $pageHeaderTitle) : '';
$pageHeaderDescription = isset($pageHeaderDescription) ? trim((string) $pageHeaderDescription) : '';
$pageHeaderPrimaryAction = isset($pageHeaderPrimaryAction) ? (string) $pageHeaderPrimaryAction : '';
$pageHeaderSecondaryInfo = isset($pageHeaderSecondaryInfo) ? (string) $pageHeaderSecondaryInfo : '';
$pageHeaderActions = isset($pageHeaderActions) ? (string) $pageHeaderActions : '';
$pageHeaderClass = isset($pageHeaderClass) ? trim((string) $pageHeaderClass) : '';
?>
<section class="card card-soft page-shell-header <?= e($pageHeaderClass) ?>">
    <div class="card-body">
        <div class="page-shell-header__content">
            <div class="page-shell-header__copy">
                <?php if ($pageHeaderEyebrow !== ''): ?>
                    <p class="page-shell-header__eyebrow mb-1"><?= e($pageHeaderEyebrow) ?></p>
                <?php endif; ?>
                <?php if ($pageHeaderTitle !== ''): ?>
                    <h1 class="page-shell-header__title h4 m-0"><?= $pageHeaderTitle ?></h1>
                <?php endif; ?>
                <?php if ($pageHeaderDescription !== ''): ?>
                    <p class="page-shell-header__description mb-0 mt-2"><?= $pageHeaderDescription ?></p>
                <?php endif; ?>
                <?php if ($pageHeaderSecondaryInfo !== ''): ?>
                    <div class="page-shell-header__meta mt-2"><?= $pageHeaderSecondaryInfo ?></div>
                <?php endif; ?>
            </div>
            <?php if ($pageHeaderPrimaryAction !== '' || $pageHeaderActions !== ''): ?>
                <div class="page-shell-header__actions">
                    <?= $pageHeaderPrimaryAction ?>
                    <?= $pageHeaderActions ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
