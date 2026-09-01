<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

/**
 * Builds the write-free plan for the contact-message migration slice.
 *
 * sn_contact_message carries real PII (name/email/phone/message) from museum
 * visitors. Unlike the CMS-entry slices there is no slug/collection to
 * deduplicate against — every row maps 1:1 to a new cms_form_submissions row
 * in cms-domain, keyed only by (legacy_table, legacy_id) in the control
 * plane, so a retry never creates a duplicate.
 */
final class LegacySliceDAnalyzer
{
    /**
     * @param array<string, list<array<string, mixed>>> $tables
     * @return array{
     *     summary: array<string, mixed>,
     *     mappings: list<array<string, mixed>>,
     *     issues: list<array<string, mixed>>,
     *     quarantine: list<array<string, mixed>>,
     *     assets: list<array<string, mixed>>
     * }
     */
    public function analyze(array $tables, string $sourcePath, string $sourceHash): array
    {
        $statusTitles = [];
        foreach ($tables['sn_contact_status'] ?? [] as $status) {
            $statusId = $this->stringValue($status['id'] ?? '');
            if ($statusId !== '') {
                $statusTitles[$statusId] = $this->stringValue($status['title'] ?? '');
            }
        }

        /** @var list<array<string, mixed>> $mappings */
        $mappings = [];
        /** @var list<array<string, mixed>> $issues */
        $issues = [];
        /** @var list<array<string, mixed>> $quarantine */
        $quarantine = [];
        $selected = 0;

        foreach ($tables['sn_contact_message'] ?? [] as $message) {
            $legacyId = $this->stringValue($message['id'] ?? '');
            if ($legacyId === '') {
                $issues[] = $this->issue('sn_contact_message', '', 'missing_identity', 'id', 'Contact message has no legacy identity.', 'error');
                continue;
            }

            if (! $this->validDateTime($message['date_send'] ?? null)) {
                $issues[] = $this->issue('sn_contact_message', $legacyId, 'invalid_date', 'date_send', 'date_send will fall back to the import timestamp.', 'warning');
            }

            $statusId = $this->stringValue($message['status_id'] ?? '');
            if ($statusId !== '' && ! isset($statusTitles[$statusId])) {
                $issues[] = $this->issue('sn_contact_message', $legacyId, 'fk_missing', 'status_id', 'status_id is not present in sn_contact_status; will default to new.', 'warning');
            }

            if (trim($this->stringValue($message['email_address'] ?? '')) === '' && trim($this->stringValue($message['message_text'] ?? '')) === '') {
                $issues[] = $this->issue('sn_contact_message', $legacyId, 'empty_row', 'email_address,message_text', 'Row has neither an email nor a message body.', 'warning');
            }

            $mappings[] = $this->mapping('sn_contact_message', $legacyId, LegacyMigrationCatalog::TARGET_CMS, 'form_submission', 'contacto-legacy:' . $legacyId, $sourceHash);
            $selected++;
        }

        return [
            'summary' => [
                'slice' => 'D',
                'mode' => LegacyMigrationCatalog::MODE_DRY_RUN,
                'source' => ['path' => $sourcePath, 'sha256' => $sourceHash],
                'legacy_rows_read' => [
                    'sn_contact_message' => count($tables['sn_contact_message'] ?? []),
                    'sn_contact_status' => count($tables['sn_contact_status'] ?? []),
                ],
                'slice_rows_selected' => [
                    'contact_messages' => $selected,
                ],
                'targets_planned' => [
                    'cms_form_submissions' => $selected,
                ],
                'issues' => count($issues),
                'quarantine' => count($quarantine),
            ],
            'mappings' => $mappings,
            'issues' => $issues,
            'quarantine' => $quarantine,
            'assets' => [],
        ];
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function validDateTime(mixed $value): bool
    {
        $date = $this->stringValue($value);
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);

        return $parsed !== false && $parsed->format('Y-m-d H:i:s') === $date;
    }

    /** @return array<string, mixed> */
    private function mapping(string $table, string $legacyId, string $targetSystem, string $targetType, string $targetKey, string $sourceHash): array
    {
        return [
            'legacy_table' => $table,
            'legacy_id' => $legacyId,
            'target_system' => $targetSystem,
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'target_id' => null,
            'source_hash' => $sourceHash,
            'status' => LegacyMigrationCatalog::MAP_PLANNED,
        ];
    }

    /** @return array<string, mixed> */
    private function issue(string $table, string $legacyId, string $class, string $field, string $note, string $severity): array
    {
        return [
            'legacy_table' => $table,
            'legacy_id' => $legacyId,
            'issue_class' => $class,
            'severity' => $severity,
            'field' => $field,
            'original_value' => null,
            'applied_value' => null,
            'note' => $note,
        ];
    }
}
