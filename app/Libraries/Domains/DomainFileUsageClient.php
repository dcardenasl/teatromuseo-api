<?php

declare(strict_types=1);

namespace App\Libraries\Domains;

use App\Interfaces\Files\DomainFileUsageClientInterface;
use CodeIgniter\HTTP\CURLRequest;
use Config\DomainWebhooks;

/**
 * Calls the internal/files/* routes each domain app exposes for the Hub
 * (see HubSignatureFilter on the domain side). Every call is HMAC-signed
 * with DomainWebhooks::$internalSecret since domain API keys are stored as
 * a one-way hash and can't be replayed by the Hub as proof of identity.
 */
final class DomainFileUsageClient implements DomainFileUsageClientInterface
{
    public function __construct(
        private readonly DomainWebhooks $config,
        private readonly ?CURLRequest $http = null,
    ) {
    }

    /**
     * @return list<array{source: string, resource: string, resource_id: int, label: string|null, role: string}>
     */
    public function collectUsages(int $fileId): array
    {
        /** @var list<array{source: string, resource: string, resource_id: int, label: string|null, role: string}> $usages */
        $usages = [];
        foreach ($this->collectUsageSnapshot($fileId)['usages'] as $row) {
            $usages[] = [
                'source' => (string) ($row['source'] ?? ''),
                'resource' => (string) ($row['resource'] ?? ''),
                'resource_id' => (int) ($row['resource_id'] ?? 0),
                'label' => isset($row['label']) ? (string) $row['label'] : null,
                'role' => (string) ($row['role'] ?? 'default'),
            ];
        }

        return $usages;
    }

    /**
     * Return one bounded result per configured domain. The Hub remains the
     * authority for the internal HMAC call and keeps the context returned by
     * a domain instead of forcing the BFF to query that domain a second time.
     *
     * @return array{
     *     complete: bool,
     *     sources: array<string, 'ok'|'unavailable'>,
     *     usages: list<array<string, mixed>>
     * }
     */
    public function collectUsageSnapshot(int $fileId): array
    {
        if ($this->config->internalSecret === '' || $this->config->domains === []) {
            return [
                'complete' => false,
                'sources' => [],
                'usages' => [],
            ];
        }

        $usages = [];
        $sources = [];
        foreach ($this->config->domains as $code => $baseUrl) {
            $sources[$code] = 'unavailable';
            try {
                $response = $this->call('GET', $baseUrl, "/api/v1/internal/files/{$fileId}/usage");
                $decoded = $this->decode($response);
                $rows = is_array($decoded['data']['usages'] ?? null) ? $decoded['data']['usages'] : [];
                $sources[$code] = 'ok';
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $usages[] = [
                        'source'      => isset($row['source']) ? (string) $row['source'] : $code,
                        'resource'    => isset($row['resource']) ? (string) $row['resource'] : '',
                        'resource_id' => isset($row['resource_id']) ? (int) $row['resource_id'] : 0,
                        'label'       => isset($row['label']) ? (string) $row['label'] : null,
                        'role'        => isset($row['role']) ? (string) $row['role'] : 'default',
                    ];
                    if (is_array($row['context'] ?? null)) {
                        $usages[array_key_last($usages)]['context'] = $row['context'];
                    }
                }
            } catch (\Throwable $e) {
                log_message('warning', "[DomainFileUsageClient] usage check failed for domain '{$code}' (file {$fileId}): " . $e->getMessage());
            }
        }

        return [
            'complete' => ! in_array('unavailable', $sources, true),
            'sources' => $sources,
            'usages' => $usages,
        ];
    }

    public function broadcastInvalidate(int $fileId): void
    {
        if ($this->config->internalSecret === '' || $this->config->domains === []) {
            return;
        }

        foreach ($this->config->domains as $code => $baseUrl) {
            try {
                $this->call('POST', $baseUrl, "/api/v1/internal/files/{$fileId}/invalidate-cache");
            } catch (\Throwable $e) {
                log_message('warning', "[DomainFileUsageClient] invalidate-cache failed for domain '{$code}' (file {$fileId}): " . $e->getMessage());
            }
        }
    }

    private function call(string $method, string $baseUrl, string $path): \CodeIgniter\HTTP\ResponseInterface
    {
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', strtoupper($method) . "\n" . $path . "\n" . $timestamp, $this->config->internalSecret);

        $http = $this->http ?? \Config\Services::curlrequest([], null, null, false);

        return $http->request($method, rtrim($baseUrl, '/') . $path, [
            'http_errors' => false,
            'timeout'     => $this->config->httpTimeoutSeconds,
            'headers'     => [
                'Accept'           => 'application/json',
                'X-Hub-Timestamp'  => $timestamp,
                'X-Hub-Signature'  => $signature,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\CodeIgniter\HTTP\ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Domain call returned HTTP {$status}");
        }

        $decoded = json_decode((string) $response->getBody(), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Domain call returned invalid JSON.');
        }

        return $decoded;
    }
}
