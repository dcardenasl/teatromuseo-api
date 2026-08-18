<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use CodeIgniter\Test\CIUnitTestCase;
use Config\FilePolicy;

final class FilePolicyConfigTest extends CIUnitTestCase
{
    public function testCanonicalEnvironmentFlagEnablesSharedReadScope(): void
    {
        $state = $this->setEnvironment('false');

        try {
            $policy = new FilePolicy();

            $this->assertFalse($policy->userScopedFiles);
        } finally {
            $this->restoreEnvironment($state);
        }
    }

    /**
     * @return array{env: mixed, server: mixed, getenv: string|false}
     */
    private function setEnvironment(string $value): array
    {
        $key = 'FILE_USER_SCOPED_FILES';
        $state = [
            'env'    => array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
            'server' => array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
            'getenv' => getenv($key),
        ];

        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);

        return $state;
    }

    /**
     * @param array{env: mixed, server: mixed, getenv: string|false} $state
     */
    private function restoreEnvironment(array $state): void
    {
        $key = 'FILE_USER_SCOPED_FILES';

        if ($state['env'] === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $state['env'];
        }

        if ($state['server'] === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $state['server'];
        }

        if ($state['getenv'] === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $state['getenv']);
        }
    }
}
