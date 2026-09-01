<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Base URLs of the domain apps this Hub can call *into* (reverse of the usual
 * domain→Hub direction), plus the shared secret used to sign those calls.
 *
 * Used by DomainFileUsageClient to ask each domain "is file X in use?"
 * before a destructive file operation, and to push a cache-invalidation
 * notice after replace()/destroy()/forceDestroy(). See HubSignatureFilter
 * on the domain side for the corresponding verification.
 *
 * Optional by design: an empty $internalSecret or an empty $domains list
 * simply means cross-domain usage checks are skipped (fail-open) — the Hub
 * still boots and serves everything else. This mirrors DomainAppsRegistry's
 * `code` values (cms/catalog/event) so the two can be cross-referenced.
 */
class DomainWebhooks extends BaseConfig
{
    public string $internalSecret = '';

    /**
     * @var array<string, string> domain code => base URL (no trailing slash)
     */
    public array $domains = [];

    public int $httpTimeoutSeconds = 3;

    public function __construct()
    {
        parent::__construct();

        $this->internalSecret = (string) (env('HUB_INTERNAL_SECRET') ?: '');

        $this->domains = array_filter([
            'cms'     => (string) (env('CMS_DOMAIN_URL') ?: ''),
            'catalog' => (string) (env('CATALOG_DOMAIN_URL') ?: ''),
            'event'   => (string) (env('EVENT_DOMAIN_URL') ?: ''),
        ], static fn (string $url): bool => $url !== '');

        $timeout = env('HUB_DOMAIN_TIMEOUT');
        if ($timeout !== null && $timeout !== false && $timeout !== '') {
            $this->httpTimeoutSeconds = (int) $timeout;
        }
    }
}
