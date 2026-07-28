<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

use CodeIgniter\HTTP\CURLRequest;

/**
 * Authenticated JSON/multipart client used by the hub migration command.
 */
final class LegacyHttpDomainClient implements LegacyDomainClientInterface
{
    private CURLRequest $http;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $bearerToken,
        ?CURLRequest $http = null,
        private readonly int $timeout = 30
    ) {
        $url = rtrim(trim($baseUrl), '/');
        if ($url === '') {
            throw new \InvalidArgumentException('Legacy domain client base URL cannot be empty.');
        }
        if (trim($bearerToken) === '') {
            throw new \InvalidArgumentException('Legacy domain client bearer token cannot be empty.');
        }

        $this->http = $http ?? \Config\Services::curlrequest([], null, null, false);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->request('POST', $path, ['json' => $payload]);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function upload(string $path, string $filePath, string $filename, array $fields = []): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new \InvalidArgumentException("Legacy asset is not readable: {$filePath}");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open legacy asset: {$filePath}");
        }

        try {
            $multipart = [
                [
                    'name'     => 'file',
                    'contents' => $handle,
                    'filename' => $filename,
                ],
            ];
            foreach ($fields as $name => $value) {
                $multipart[] = [
                    'name'     => (string) $name,
                    'contents' => (string) $value,
                ];
            }

            return $this->request('POST', $path, ['multipart' => $multipart]);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options): array
    {
        $path = '/' . ltrim(trim($path), '/');
        $headers = [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . trim($this->bearerToken),
        ];

        if (isset($options['json'])) {
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->http->request($method, rtrim($this->baseUrl, '/') . $path, [
            'http_errors' => false,
            'timeout'     => $this->timeout,
            'headers'     => $headers,
        ] + $options);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $detail = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $body;
            throw new \RuntimeException("Legacy domain request {$method} {$path} failed with HTTP {$status}: " . substr((string) $detail, 0, 1000));
        }
        if (! is_array($decoded)) {
            throw new \RuntimeException("Legacy domain request {$method} {$path} returned invalid JSON.");
        }

        return $decoded;
    }
}
