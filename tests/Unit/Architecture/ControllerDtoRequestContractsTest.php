<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrails for DTO-driven controller request handling.
 */
class ControllerDtoRequestContractsTest extends CIUnitTestCase
{
    /**
     * Controllers exempt from the handleRequest() / ApiController convention —
     * e.g. the health probe, which extends CI4's Controller and exposes no DTO
     * request pipeline. Adding an entry is a conscious, reviewable decision.
     *
     * @var list<string>
     */
    private const CONTROLLER_EXCEPTIONS = [
        'HealthController',
    ];

    /**
     * @return array<string, array<int, string>>
     */
    private function controllerSnippets(): array
    {
        return [
            'app/Controllers/Api/V1/Users/UserController.php' => [
                "handleRequest('index', UserIndexRequestDTO::class)",
                "handleRequest('store', UserCreateRequestDTO::class)",
                "UserUpdateRequestDTO::class",
            ],
            'app/Controllers/Api/V1/Admin/ApiKeyController.php' => [
                "handleRequest('index', ApiKeyIndexRequestDTO::class)",
                "handleRequest('store', ApiKeyCreateRequestDTO::class)",
                "ApiKeyUpdateRequestDTO::class",
            ],
            'app/Controllers/Api/V1/Admin/AuditController.php' => [
                "handleRequest('index', AuditIndexRequestDTO::class)",
                "handleRequest(\n            'byEntity',\n            AuditByEntityRequestDTO::class",
            ],
            'app/Controllers/Api/V1/Files/FileController.php' => [
                "handleRequest('index', FileIndexRequestDTO::class)",
                "handleRequest('upload', FileUploadRequestDTO::class)",
                "fileService->destroy(\$id, \$context)",
            ],
            'app/Controllers/Api/V1/Admin/MetricsController.php' => [
                "handleRequest('getOverview', MetricsQueryRequestDTO::class)",
                "handleRequest('getRequestStats', MetricsQueryRequestDTO::class)",
                "handleRequest('getSlowRequests', SlowRequestsQueryRequestDTO::class)",
                "handleRequest(\n            'getCustomMetric',\n            CustomMetricQueryRequestDTO::class",
                "handleRequest('record', RecordMetricRequestDTO::class)",
            ],
        ];
    }

    public function testControllersUseHandleRequestWithRequestDtos(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $violations = [];

        foreach ($this->controllerSnippets() as $relativePath => $requiredSnippets) {
            $path = $root . '/' . $relativePath;
            $source = file_get_contents($path);

            if (! is_string($source) || $source === '') {
                $violations[] = "{$relativePath}: could not read source";
                continue;
            }

            foreach ($requiredSnippets as $snippet) {
                if (! str_contains($source, $snippet)) {
                    $violations[] = "{$relativePath}: missing snippet -> {$snippet}";
                }
            }
        }

        $this->assertSame([], $violations, "Controller DTO pipeline violations:\n- " . implode("\n- ", $violations));
    }

    /**
     * Dynamic sweep: every controller under app/Controllers/Api must extend
     * ApiController and orchestrate via handleRequest(), so a freshly scaffolded
     * controller is covered without editing this test. Curated per-controller
     * DTO contracts above still pin the hand-written controllers precisely.
     */
    public function testAllApiControllersFollowHandleRequestConvention(): void
    {
        $dir        = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Controllers/Api';
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), 'Controller.php')) {
                continue;
            }

            $name = $file->getBasename('.php');
            if (in_array($name, self::CONTROLLER_EXCEPTIONS, true)) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! is_string($source) || $source === '') {
                $violations[] = "{$name}: could not read source";
                continue;
            }

            if (! str_contains($source, 'extends ApiController')) {
                $violations[] = "{$name}: must extend ApiController";
            }

            if (! str_contains($source, 'handleRequest(')) {
                $violations[] = "{$name}: must orchestrate via handleRequest() (or be added to CONTROLLER_EXCEPTIONS with rationale)";
            }
        }

        $this->assertSame([], $violations, "Controller convention violations:\n- " . implode("\n- ", $violations));
    }
}
