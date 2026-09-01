<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

use DateTimeImmutable;

/**
 * Converts the legacy date/time pair into the event-domain wall-clock format.
 *
 * Legacy values are intentionally parsed strictly. A malformed time is a data
 * quality issue and must never become midnight by accident.
 */
final class LegacyScheduleParser
{
    public function parse(mixed $date, mixed $time): ?string
    {
        return $this->parseMany($date, $time)[0] ?? null;
    }

    /**
     * Parse every explicit time in a legacy schedule field.
     *
     * Examples accepted by the old site include `20 hrs`, `21.00 hrs`, and
     * `12:00 y 16:30 hrs`. The last form represents two scheduled functions,
     * so collapsing it to one value would lose information.
     *
     * @return list<string>
     */
    public function parseMany(mixed $date, mixed $time): array
    {
        $dateValue = trim((string) ($date ?? ''));
        if (! $this->isValidDate($dateValue)) {
            return [];
        }

        $times = $this->normalizeTimes($time);

        return array_map(
            static fn (string $normalized): string => $dateValue . ' ' . $normalized,
            $times
        );
    }

    public function normalizeTime(mixed $time): ?string
    {
        return $this->normalizeTimes($time)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function normalizeTimes(mixed $time): array
    {
        $value = trim((string) ($time ?? ''));
        if ($value === '') {
            return [];
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $matches = [];
        preg_match_all('/(?<![\d:.])([01]?\d|2[0-3])(?:\s*[:.]\s*(\d{2,3})(?:\s*:\s*([0-5]\d))?)?(?![\d:.])/u', $value, $matches, PREG_SET_ORDER);

        $normalized = [];
        foreach ($matches as $match) {
            $hour = (int) $match[1];
            $minute = (string) ($match[2] ?? '00');
            $seconds = (string) ($match[3] ?? '00');

            // One legacy row contains `21:000 hrs`; the extra trailing zero
            // is an unambiguous typo for `21:00`, so repair only that exact
            // shape and keep all other malformed values rejected.
            if (strlen($minute) === 3 && $minute === '000') {
                $minute = '00';
            }
            if (strlen($minute) !== 2 || (int) $minute > 59) {
                continue;
            }

            $candidate = sprintf('%02d:%02d:%02d', $hour, (int) $minute, (int) $seconds);
            $parsed = DateTimeImmutable::createFromFormat('!H:i:s', $candidate);
            if ($parsed === false || $parsed->format('H:i:s') !== $candidate) {
                continue;
            }

            $normalized[$candidate] = true;
        }

        return array_keys($normalized);
    }

    private function isValidDate(string $date): bool
    {
        if ($date === '' || $date === '0000-00-00') {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
