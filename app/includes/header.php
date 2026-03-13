<?php
declare(strict_types=1);

if (!isset($pageTitle)) {
    $pageTitle = 'San Enrique LGU Scholarship System';
}

$currentScript = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$onRolePage = str_contains($currentScript, '/shared/')
    || str_contains($currentScript, '/admin-only/')
    || str_contains($currentScript, '/staff-only/');
$onAdminPage = $onRolePage;
$assetBase = $onRolePage ? '../' : '';
$bodyClass = $bodyClass ?? '';
$bodyClass = trim('app-body ' . $bodyClass);
$extraCss = $extraCss ?? [];
$extraHead = $extraHead ?? '';
$faviconRelativePath = 'assets/images/branding/lgu-logo.png';
$faviconAbsolutePath = __DIR__ . '/../../' . $faviconRelativePath;
$hasFavicon = file_exists($faviconAbsolutePath);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e($assetBase) ?>assets/vendor/fonts/google-fonts.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= e($assetBase) ?>assets/vendor/fontawesome/css/all.min.css">
    <?php if ($hasFavicon): ?>
        <link rel="icon" type="image/png" href="<?= e($assetBase . $faviconRelativePath) ?>">
        <link rel="shortcut icon" href="<?= e($assetBase . $faviconRelativePath) ?>">
        <link rel="apple-touch-icon" href="<?= e($assetBase . $faviconRelativePath) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e($assetBase) ?>assets/css/style.css">
    <?php foreach ($extraCss as $cssFile): ?>
        <link rel="stylesheet" href="<?= e((string) $cssFile) ?>">
    <?php endforeach; ?>
    <?= $extraHead ?>
</head>
<body class="<?= e($bodyClass) ?>">
<?php if (empty($hideNavbar)): ?>
    <?php include __DIR__ . '/navbar.php'; ?>
<?php endif; ?>

<main class="app-main py-4">
    <div class="container">
        <?php
        $alerts = get_flash_messages();
        foreach ($alerts as $type => $messages):
            foreach ($messages as $message):
                ?>
                <div class="alert alert-<?= e($type) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <?= e((string) $message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php
            endforeach;
        endforeach;
        ?>
