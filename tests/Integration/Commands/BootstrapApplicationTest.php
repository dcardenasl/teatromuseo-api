<?php

declare(strict_types=1);

namespace Tests\Integration\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use ReflectionClass;
use Tests\Support\IntegrationTestCase;

/**
 * Integration tests for `apps:bootstrap`, focused on the `--create-api-key`
 * branch added by API-007.
 *
 * Note: `CLI::$options` is populated by `CLI::parseCommandLine()` from
 * `$_SERVER['argv']` when running through `php spark`. When invoking
 * commands programmatically via `service('commands')->run()`, that array
 * stays empty — so `CLI::getOption('--flag')` returns null for every
 * flag. We populate it manually to match what the real CLI does.
 *
 * @internal
 */
final class BootstrapApplicationTest extends IntegrationTestCase
{
    /**
     * @param array<int|string, mixed> $params  positional args use int keys; flag names use string keys with `true` (value-less) or string (value)
     */
    private function runCommand(string $command, array $params): string
    {
        $positional = [];
        $options    = [];

        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $positional[] = (string) $value;
                continue;
            }

            // CLI::getOption() returns true when the value is null; otherwise the value.
            $options[$key] = $value === true ? null : (string) $value;
        }

        $reflection      = new ReflectionClass(CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setValue(null, $options);

        $io = new MockInputOutput();
        CLI::setInputOutput($io);

        try {
            service('commands')->run($command, $positional);
        } finally {
            $optionsProperty->setValue(null, []);
            CLI::resetInputOutput();
        }

        return $io->getOutput();
    }

    public function testCreatesApplicationAndApiKeyBoundToApplicationId(): void
    {
        $output = $this->runCommand('apps:bootstrap', [
            'blog',
            'no-grant-user'  => true,
            'create-api-key' => true,
        ]);

        $this->seeInDatabase('applications', ['code' => 'blog']);
        $appId = (int) $this->grabFromDatabase('applications', 'id', ['code' => 'blog']);
        $this->assertGreaterThan(0, $appId);

        $this->seeInDatabase('api_keys', ['application_id' => $appId, 'is_active' => 1]);

        $result = \Config\Database::connect()
            ->table('api_keys')
            ->where('application_id', $appId)
            ->get();

        $this->assertNotFalse($result);
        $keyRow = $result->getRowArray();
        $this->assertNotNull($keyRow);
        $this->assertSame('blog-app-key', $keyRow['name']);
        $this->assertSame(12, strlen((string) $keyRow['key_prefix']));
        $this->assertSame(64, strlen((string) $keyRow['key_hash']));

        $this->assertMatchesRegularExpression('/API_KEY=apk_[a-f0-9]{48}/', $output);
        $this->assertStringContainsString("APP_ID={$appId}", $output);
    }

    public function testCustomApiKeyNameOverridesDefault(): void
    {
        $this->runCommand('apps:bootstrap', [
            'shop',
            'no-grant-user'  => true,
            'create-api-key' => true,
            'api-key-name'   => 'shop-prod',
        ]);

        $result = \Config\Database::connect()
            ->table('api_keys')
            ->join('applications', 'applications.id = api_keys.application_id')
            ->where('applications.code', 'shop')
            ->select('api_keys.name')
            ->get();

        $this->assertNotFalse($result);
        $key = $result->getRowArray();
        $this->assertNotNull($key);
        $this->assertSame('shop-prod', $key['name']);
    }

    public function testWithoutCreateApiKeyFlagDoesNotInsertApiKey(): void
    {
        $this->runCommand('apps:bootstrap', [
            'silent',
            'no-grant-user' => true,
        ]);

        $count = \Config\Database::connect()
            ->table('api_keys')
            ->join('applications', 'applications.id = api_keys.application_id')
            ->where('applications.code', 'silent')
            ->countAllResults();

        $this->assertSame(0, $count);
    }

    public function testRefusesToCreateSecondActiveKeyForSameApplication(): void
    {
        $this->runCommand('apps:bootstrap', [
            'dual',
            'no-grant-user'  => true,
            'create-api-key' => true,
        ]);

        $output = $this->runCommand('apps:bootstrap', [
            'dual',
            'no-grant-user'  => true,
            'create-api-key' => true,
        ]);

        $this->assertStringContainsString('API_KEY_EXISTS=', $output);

        $count = \Config\Database::connect()
            ->table('api_keys')
            ->join('applications', 'applications.id = api_keys.application_id')
            ->where('applications.code', 'dual')
            ->where('is_active', 1)
            ->countAllResults();

        $this->assertSame(1, $count, 'Second --create-api-key call must not insert a duplicate active key.');
    }
}
