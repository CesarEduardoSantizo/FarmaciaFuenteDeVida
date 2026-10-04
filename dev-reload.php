<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$latestChange = filemtime(__FILE__);
$directory = new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($directory);

foreach ($files as $file) {
    if (!$file->isFile() || $file->getFilename() === 'dev-reload.php') {
        continue;
    }

    $latestChange = max($latestChange, $file->getMTime());
}

echo json_encode(['lastModified' => $latestChange]);
