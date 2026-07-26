<?php

declare(strict_types=1);

// Strips the `repositories` block from composer.json so CI resolves
// dcardenasl/ci4-api-core and dcardenasl/ci4-api-scaffolding from
// Packagist instead of the sibling workspace paths that only exist
// on a developer's machine.
//
// Removing `repositories` changes the content-hash that composer.lock
// pins, which makes `composer validate --strict` fail. We recompute
// the hash using the same algorithm as Composer\Package\Locker so the
// lock stays in sync with the stripped composer.json.
//
// Safe to call unconditionally — exits 0 when no `repositories` key
// is present.

$root         = dirname(__DIR__);
$composerFile = $root . '/composer.json';
$lockFile     = $root . '/composer.lock';

if (! is_file($composerFile)) {
    fwrite(STDERR, "composer.json not found at {$composerFile}\n");
    exit(1);
}

$composerJson = (string) file_get_contents($composerFile);
$composerData = json_decode($composerJson, true, flags: JSON_THROW_ON_ERROR);

if (! array_key_exists('repositories', $composerData)) {
    echo "No `repositories` block present; nothing to strip.\n";
    exit(0);
}

unset($composerData['repositories']);

$strippedJson = json_encode(
    $composerData,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
) . "\n";

file_put_contents($composerFile, $strippedJson);
echo "Stripped local path `repositories` from composer.json.\n";

if (is_file($lockFile)) {
    $lockData = json_decode((string) file_get_contents($lockFile), true, flags: JSON_THROW_ON_ERROR);

    $lockData['content-hash'] = computeContentHash($strippedJson);

    file_put_contents(
        $lockFile,
        json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
    );

    echo "Recomputed composer.lock content-hash.\n";
}

// Mirrors Composer\Package\Locker::getContentHash() so a strict
// `composer validate` after the strip sees a fresh hash.
function computeContentHash(string $composerFileContents): string
{
    $content = json_decode($composerFileContents, true, flags: JSON_THROW_ON_ERROR);

    $relevantKeys = [
        'name',
        'version',
        'require',
        'require-dev',
        'conflict',
        'replace',
        'provide',
        'minimum-stability',
        'prefer-stable',
        'repositories',
        'extra',
    ];

    $relevantContent = [];

    foreach (array_intersect($relevantKeys, array_keys($content)) as $key) {
        $relevantContent[$key] = $content[$key];
    }

    if (isset($content['config']['platform'])) {
        $relevantContent['config']['platform'] = $content['config']['platform'];
    }

    ksort($relevantContent);

    return hash('md5', json_encode($relevantContent, JSON_THROW_ON_ERROR));
}
