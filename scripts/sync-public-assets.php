<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$sourceRoot = dirname(__DIR__) . '/assets';
$targetRoot = dirname(__DIR__) . '/public/assets';

if (!is_dir($sourceRoot)) {
    fwrite(STDERR, "Source assets directory not found.\n");
    exit(1);
}

if (!is_dir($targetRoot) && !mkdir($targetRoot, 0777, true) && !is_dir($targetRoot)) {
    fwrite(STDERR, "Unable to create public/assets.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$copiedFiles = 0;
foreach ($iterator as $item) {
    $sourcePath = $item->getPathname();
    $relativePath = substr($sourcePath, strlen($sourceRoot) + 1);
    $targetPath = $targetRoot . DIRECTORY_SEPARATOR . $relativePath;

    if ($item->isDir()) {
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0777, true);
        }
        continue;
    }

    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    copy($sourcePath, $targetPath);
    $copiedFiles++;
}

fwrite(STDOUT, "Copied {$copiedFiles} asset file(s) to public/assets.\n");
