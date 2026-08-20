<?php
/**
 * Package the site template as an installable Joomla zip.
 *
 * Built with ZipArchive rather than PowerShell's Compress-Archive: the latter
 * writes Windows backslashes into the entry names, which Joomla's installer
 * does not understand.
 */

$root = __DIR__ . '/template';
$out  = __DIR__ . '/tpl_lkim-1.0.0.zip';

@unlink($out);

$zip = new ZipArchive();

if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create $out\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;

foreach ($files as $file) {
    $local = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
    $zip->addFile($file->getPathname(), $local);
    $count++;
}

$zip->close();

echo $count . ' files -> ' . basename($out) . ' (' . number_format(filesize($out)) . ' bytes)' . PHP_EOL;
