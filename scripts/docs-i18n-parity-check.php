#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$docsRoot = $root . '/docs';

if (!is_dir($docsRoot)) {
    fwrite(STDERR, "docs directory not found: {$docsRoot}\n");
    exit(1);
}

$ignore = [
    'docs/.DS_Store',
];

$allDocs = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsRoot));

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    if ($file->getExtension() !== 'md') {
        continue;
    }

    $absolutePath = $file->getPathname();
    $relativePath = str_replace($root . '/', '', $absolutePath);

    if (in_array($relativePath, $ignore, true)) {
        continue;
    }

    $allDocs[] = $relativePath;
}

sort($allDocs);

$missingSpanish = [];
$missingEnglish = [];

foreach ($allDocs as $path) {
    if (str_ends_with($path, '.es.md')) {
        $englishPath = substr($path, 0, -6) . '.md';
        if (!file_exists($root . '/' . $englishPath)) {
            $missingEnglish[] = $path . " -> missing {$englishPath}";
        }
        continue;
    }

    $spanishPath = substr($path, 0, -3) . '.es.md';
    if (!file_exists($root . '/' . $spanishPath)) {
        $missingSpanish[] = $path . " -> missing {$spanishPath}";
    }
}

if ($missingSpanish !== [] || $missingEnglish !== []) {
    foreach ($missingSpanish as $line) {
        fwrite(STDERR, "Missing ES pair: {$line}\n");
    }

    foreach ($missingEnglish as $line) {
        fwrite(STDERR, "Missing EN pair: {$line}\n");
    }

    exit(1);
}

fwrite(STDOUT, "docs-i18n-parity-check passed\n");
exit(0);
