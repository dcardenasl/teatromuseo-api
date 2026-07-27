<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

/**
 * Small streaming-oriented reader for phpMyAdmin INSERT statements.
 *
 * It intentionally reads only requested tables and returns plain arrays. It
 * does not execute arbitrary SQL from the dump, which keeps dry-run analysis
 * isolated from the application databases.
 */
final class LegacySqlDumpReader
{
    private string $dumpPath;

    public function __construct(string $dumpPath)
    {
        $resolvedPath = realpath($dumpPath);
        if ($resolvedPath === false || ! is_file($resolvedPath)) {
            throw new \InvalidArgumentException("Legacy SQL dump '{$dumpPath}' does not exist.");
        }

        $this->dumpPath = $resolvedPath;
    }

    public function sourceHash(): string
    {
        $hash = hash_file('sha256', $this->dumpPath);
        if ($hash === false) {
            throw new \RuntimeException("Unable to hash legacy SQL dump '{$this->dumpPath}'.");
        }

        return $hash;
    }

    /**
     * @param list<string> $tableNames
     * @return array<string, list<array<string, mixed>>>
     */
    public function rowsForTables(array $tableNames): array
    {
        $wanted = array_fill_keys(array_values($tableNames), true);
        $rows = [];
        foreach ($wanted as $table => $_) {
            $rows[$table] = [];
        }

        $sql = file_get_contents($this->dumpPath);
        if ($sql === false) {
            throw new \RuntimeException("Unable to read legacy SQL dump '{$this->dumpPath}'.");
        }

        foreach ($this->statements($sql) as $statement) {
            $parsed = $this->parseInsert($statement);
            if ($parsed === null || ! isset($wanted[$parsed['table']])) {
                continue;
            }

            foreach ($parsed['rows'] as $row) {
                $rows[$parsed['table']][] = $row;
            }
        }

        return $rows;
    }

    /** @return \Generator<int, string> */
    private function statements(string $sql): \Generator
    {
        $start = 0;
        $length = strlen($sql);
        $inString = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            if ($inString && $character === '\\') {
                $index++;
                continue;
            }
            if ($character === "'") {
                if ($inString && ($sql[$index + 1] ?? null) === "'") {
                    $index++;
                    continue;
                }
                $inString = ! $inString;
                continue;
            }
            if ($character === ';' && ! $inString) {
                $statement = trim(substr($sql, $start, $index - $start));
                if ($statement !== '') {
                    yield $statement;
                }
                $start = $index + 1;
            }
        }
    }

    /** @return array{table: string, rows: list<array<string, mixed>>}|null */
    private function parseInsert(string $statement): ?array
    {
        $statement = preg_replace(
            '/\A(?:[ \t]*--[^\r\n]*(?:\r\n|\r|\n|$))+/',
            '',
            $statement
        ) ?? $statement;
        $statement = ltrim($statement);

        if (! preg_match(
            '/^INSERT\\s+INTO\\s+`(?P<table>[^`]+)`\\s*\\((?P<columns>.*?)\\)\\s*VALUES\\s*(?P<values>.*)$/is',
            $statement,
            $matches
        )) {
            return null;
        }

        $columns = array_map(
            static fn (string $column): string => trim($column, " `\t\r\n"),
            explode(',', (string) $matches['columns'])
        );
        $rows = [];
        foreach ($this->tuples((string) $matches['values']) as $tuple) {
            $values = $this->csvValues($tuple);
            if (count($values) !== count($columns)) {
                throw new \RuntimeException(sprintf(
                    'Column/value count mismatch in INSERT INTO %s (%d/%d).',
                    $matches['table'],
                    count($columns),
                    count($values)
                ));
            }

            $row = [];
            foreach ($columns as $index => $column) {
                $row[$column] = $values[$index];
            }
            $rows[] = $row;
        }

        return ['table' => (string) $matches['table'], 'rows' => $rows];
    }

    /** @return \Generator<int, string> */
    private function tuples(string $values): \Generator
    {
        $depth = 0;
        $tupleStart = null;
        $inString = false;
        $length = strlen($values);

        for ($index = 0; $index < $length; $index++) {
            $character = $values[$index];
            if ($inString && $character === '\\') {
                $index++;
                continue;
            }
            if ($character === "'") {
                if ($inString && ($values[$index + 1] ?? null) === "'") {
                    $index++;
                    continue;
                }
                $inString = ! $inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($character === '(') {
                if ($depth === 0) {
                    $tupleStart = $index + 1;
                }
                $depth++;
                continue;
            }
            if ($character === ')') {
                $depth--;
                if ($depth === 0 && $tupleStart !== null) {
                    yield substr($values, $tupleStart, $index - $tupleStart);
                    $tupleStart = null;
                }
            }
        }
    }

    /** @return list<mixed> */
    private function csvValues(string $tuple): array
    {
        $values = [];
        $start = 0;
        $inString = false;
        $length = strlen($tuple);

        for ($index = 0; $index < $length; $index++) {
            $character = $tuple[$index];
            if ($inString && $character === '\\') {
                $index++;
                continue;
            }
            if ($character === "'") {
                if ($inString && ($tuple[$index + 1] ?? null) === "'") {
                    $index++;
                    continue;
                }
                $inString = ! $inString;
                continue;
            }
            if ($character === ',' && ! $inString) {
                $values[] = $this->decodeValue(substr($tuple, $start, $index - $start));
                $start = $index + 1;
            }
        }
        $values[] = $this->decodeValue(substr($tuple, $start));

        return $values;
    }

    private function decodeValue(string $raw): mixed
    {
        $value = trim($raw);
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }
        if ($value === '' || $value[0] !== "'" || substr($value, -1) !== "'") {
            return $value;
        }

        $value = substr($value, 1, -1);
        $decoded = '';
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];
            if ($character === '\\' && isset($value[$index + 1])) {
                $index++;
                $escaped = $value[$index];
                $decoded .= match ($escaped) {
                    '0' => "\0",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'Z' => chr(26),
                    default => $escaped,
                };
                continue;
            }
            if ($character === "'" && ($value[$index + 1] ?? null) === "'") {
                $decoded .= "'";
                $index++;
                continue;
            }
            $decoded .= $character;
        }

        return $decoded;
    }
}
