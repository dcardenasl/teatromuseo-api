#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$languageRoot = $root . '/app/Language';
$locales = ['en', 'es'];
$errors = [];

/**
 * @param array<string, mixed> $data
 * @return array<string, string>
 */
function flattenKeys(array $data, string $prefix = ''): array
{
    $out = [];

    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        if (is_array($value)) {
            foreach (flattenKeys($value, $path) as $nestedKey => $nestedValue) {
                $out[$nestedKey] = $nestedValue;
            }
            continue;
        }

        $out[$path] = (string) $value;
    }

    return $out;
}

/**
 * @return array<string>
 */
function listPhpFiles(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}

/**
 * @return array{catalog: array<string, array<string, string>>, files: array<string, list<string>>}
 */
function buildLanguageCatalog(string $languageRoot, array $locales, array &$errors): array
{
    $catalog = [];
    $filesByLocale = [];

    foreach ($locales as $locale) {
        $localeDir = $languageRoot . '/' . $locale;

        if (!is_dir($localeDir)) {
            $errors[] = "Missing locale directory: {$localeDir}";
            continue;
        }

        $languageFiles = glob($localeDir . '/*.php') ?: [];
        sort($languageFiles);

        $filesByLocale[$locale] = array_map(
            static fn (string $file): string => basename($file),
            $languageFiles
        );

        foreach ($languageFiles as $file) {
            $basename = basename($file, '.php');
            try {
                $data = require $file;
            } catch (\Throwable $e) {
                $errors[] = "ParseError in {$locale}/{$basename}.php: {$e->getMessage()}";
                continue;
            }

            if (!is_array($data)) {
                $errors[] = "Language file does not return array: {$file}";
                continue;
            }

            $flat = flattenKeys($data);
            foreach ($flat as $key => $value) {
                $catalog[$locale]["{$basename}.{$key}"] = $value;
            }
        }
    }

    return ['catalog' => $catalog, 'files' => $filesByLocale];
}

$languageData = buildLanguageCatalog($languageRoot, $locales, $errors);
$catalog = $languageData['catalog'];
$filesByLocale = $languageData['files'];

$baseLocale = $locales[0];
$baseFiles = $filesByLocale[$baseLocale] ?? [];

foreach ($locales as $locale) {
    if ($locale === $baseLocale) {
        continue;
    }

    $localeFiles = $filesByLocale[$locale] ?? [];
    $missing = array_diff($baseFiles, $localeFiles);
    $extra = array_diff($localeFiles, $baseFiles);

    foreach ($missing as $file) {
        $errors[] = "Missing language file in {$locale}: {$file}";
    }

    foreach ($extra as $file) {
        $errors[] = "Extra language file in {$locale}: {$file}";
    }
}

$allFiles = [];
foreach ($filesByLocale as $localeFiles) {
    $allFiles = array_merge($allFiles, $localeFiles);
}
$allFiles = array_values(array_unique($allFiles));
sort($allFiles);

foreach ($allFiles as $file) {
    $fileKeyPrefix = basename($file, '.php') . '.';

    $keysByLocale = [];
    foreach ($locales as $locale) {
        $localeKeys = [];

        foreach (array_keys($catalog[$locale] ?? []) as $key) {
            if (str_starts_with($key, $fileKeyPrefix)) {
                $localeKeys[] = $key;
            }
        }

        sort($localeKeys);
        $keysByLocale[$locale] = $localeKeys;
    }

    $baseKeys = $keysByLocale[$baseLocale] ?? [];

    foreach ($locales as $locale) {
        if ($locale === $baseLocale) {
            continue;
        }

        $missingKeys = array_diff($baseKeys, $keysByLocale[$locale]);
        $extraKeys = array_diff($keysByLocale[$locale], $baseKeys);

        foreach ($missingKeys as $key) {
            $errors[] = "Missing key in {$locale}: {$key}";
        }

        foreach ($extraKeys as $key) {
            $errors[] = "Extra key in {$locale}: {$key}";
        }
    }
}

$ignoredNamespaces = [
    'CodeIgniter',
    'Errors',
];

$ignoredFiles = [
    'app/Commands/MakeCrud.php',
];

foreach (listPhpFiles($root . '/app') as $file) {
    $relative = str_replace($root . '/', '', $file);
    if (in_array($relative, $ignoredFiles, true)) {
        continue;
    }

    $lines = file($file);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $lineNumber => $line) {
        if (!preg_match_all('/\\blang\\(\\s*([\'\"])([^\'\"]+)\\1/', $line, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $langKey = $match[2];

            // Skip keys with variables
            if (str_contains($langKey, '$') || str_contains($langKey, '{')) {
                continue;
            }

            if (!str_contains($langKey, '.')) {
                continue;
            }

            $namespace = explode('.', $langKey)[0];
            if (in_array($namespace, $ignoredNamespaces, true)) {
                continue;
            }

            foreach ($locales as $locale) {
                if (!isset($catalog[$locale][$langKey])) {
                    $errors[] = "Missing lang key {$langKey} in {$locale} (used at {$relative}:" . ($lineNumber + 1) . ')';
                }
            }
        }
    }
}

foreach (glob($root . '/app/Models/*.php') ?: [] as $modelFile) {
    $content = file_get_contents($modelFile);
    if ($content === false) {
        continue;
    }

    if (!preg_match_all("/=>\\s*'([A-Za-z][A-Za-z0-9_]*(?:\\.[A-Za-z0-9_]+)+)'/", $content, $matches)) {
        continue;
    }

    foreach ($matches[1] as $langKey) {
        if (!str_starts_with($langKey, 'InputValidation.')) {
            $relative = str_replace($root . '/', '', $modelFile);
            $errors[] = "Model validation messages must use InputValidation.* ({$relative} uses {$langKey})";
        }
    }
}

$exceptionAllowlist = [
    $root . '/app/Services/JwtService.php',
];

$validationExceptionScanIgnoredFiles = [
    $root . '/app/Commands/MakeCrud.php',
];

$hardcodedScanDirs = [
    $root . '/app/Services',
    $root . '/app/Controllers',
    $root . '/app/Traits',
];

foreach ($hardcodedScanDirs as $scanDir) {
    foreach (listPhpFiles($scanDir) as $file) {
        if (in_array($file, $exceptionAllowlist, true)) {
            continue;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }

        if (!preg_match_all('/throw new\\s+[A-Za-z0-9_\\\\]+Exception\\s*\\(\\s*([\'\"])([^\'\"]+)\\1/', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches as $match) {
            $message = $match[2][0];
            $offset = $match[2][1];

            if ($message === '' || str_starts_with($message, 'CodeIgniter.')) {
                continue;
            }

            $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
            $relative = str_replace($root . '/', '', $file);
            $errors[] = "Hardcoded exception message in {$relative}:{$lineNumber} ({$message})";
        }
    }
}

foreach (listPhpFiles($root . '/app') as $file) {
    if (in_array($file, $validationExceptionScanIgnoredFiles, true)) {
        continue;
    }

    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }

    if (!preg_match_all('/throw new\\s+[A-Za-z0-9_\\\\]*ValidationException\\s*\\((.*?)\\);/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($matches[1] as $match) {
        $args = $match[0];
        $offset = $match[1];

        if (!str_contains($args, '[') || !str_contains($args, '=>')) {
            continue;
        }

        if (!preg_match_all('/=>\\s*([\'"])([^\'"]+)\\1/', $args, $arrayValueMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($arrayValueMatches as $valueMatch) {
            $rawValue = $valueMatch[2][0];
            $valueOffset = $valueMatch[2][1];
            $prefix = substr($args, 0, $valueOffset);

            // Allow localized message sources only.
            if (preg_match('/=>\\s*lang\\(/', substr($prefix, -20)) === 1) {
                continue;
            }

            $lineNumber = substr_count(substr($content, 0, $offset + $valueOffset), "\n") + 1;
            $relative = str_replace($root . '/', '', $file);
            $errors[] = "Hardcoded ValidationException error message in {$relative}:{$lineNumber} ({$rawValue})";
        }
    }
}

if ($errors !== []) {
    echo "i18n-check failed:" . PHP_EOL;
    foreach ($errors as $error) {
        echo " - {$error}" . PHP_EOL;
    }
    exit(1);
}

echo 'i18n-check passed' . PHP_EOL;
exit(0);
