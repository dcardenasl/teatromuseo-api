#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * check-coverage — audit B9.4 (2026-05-07)
 *
 * Parses a Clover XML report and exits non-zero when line coverage is
 * below the supplied threshold. Designed to be wired into CI right
 * after `phpunit --coverage-clover`.
 *
 * Usage:
 *   php scripts/check-coverage.php <threshold-percent> [clover-xml-path]
 *
 * Defaults:
 *   threshold     = 70
 *   clover-xml    = tests/coverage/clover.xml  (matches phpunit.xml)
 *
 * Exit codes:
 *   0  — line coverage >= threshold
 *   1  — line coverage < threshold (CI must fail)
 *   2  — clover file missing or unparseable (treated as a hard failure
 *        because CI then has no signal at all)
 */

$threshold = isset($argv[1]) ? (float) $argv[1] : 70.0;
$cloverPath = $argv[2] ?? __DIR__ . '/../tests/coverage/clover.xml';

if (! is_readable($cloverPath)) {
    fwrite(STDERR, "check-coverage: clover XML not found at {$cloverPath}\n");
    fwrite(STDERR, "Did you run phpunit with `<clover outputFile=\"…\"/>` configured?\n");
    exit(2);
}

$xml = @simplexml_load_file($cloverPath);
if ($xml === false || ! isset($xml->project->metrics)) {
    fwrite(STDERR, "check-coverage: clover XML at {$cloverPath} is malformed (missing /coverage/project/metrics).\n");
    exit(2);
}

$metrics = $xml->project->metrics;
$totalStatements = (int) ($metrics['statements'] ?? 0);
$coveredStatements = (int) ($metrics['coveredstatements'] ?? 0);

if ($totalStatements === 0) {
    fwrite(STDERR, "check-coverage: clover reports zero statements — coverage cannot be computed.\n");
    exit(2);
}

$percent = ($coveredStatements / $totalStatements) * 100.0;
$percentFmt = number_format($percent, 2);
$thresholdFmt = number_format($threshold, 2);

if ($percent + 0.001 < $threshold) {
    fwrite(STDERR, "check-coverage: FAIL — coverage {$percentFmt}% is below threshold {$thresholdFmt}%.\n");
    fwrite(STDERR, "  covered: {$coveredStatements} / {$totalStatements} statements\n");
    exit(1);
}

echo "check-coverage: PASS — coverage {$percentFmt}% >= threshold {$thresholdFmt}%.\n";
echo "  covered: {$coveredStatements} / {$totalStatements} statements\n";
exit(0);
