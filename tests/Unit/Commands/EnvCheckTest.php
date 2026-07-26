<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Commands\EnvCheck;

class EnvCheckTest extends CIUnitTestCase
{
    private EnvCheck $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new EnvCheck(service('logger'), service('commands'));
    }

    /**
     * @return callable(string): (string|null)
     */
    private function fakeEnv(array $values): callable
    {
        return static function (string $key) use ($values) {
            return array_key_exists($key, $values) ? $values[$key] : null;
        };
    }

    private function strongSecret(int $length = 64): string
    {
        return str_repeat('aB1c2D3e4F!', (int) ceil($length / 11));
    }

    public function testValidateReturnsNoErrorsForFullyConfiguredDevelopmentEnv(): void
    {
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'http://localhost:8080/',
            'database.default.hostname'     => '127.0.0.1',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'root',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(32)),
            'JWT_SECRET_KEY'                => $this->strongSecret(),
        ]);

        $errors = $this->command->validate($resolver, 'development');

        $this->assertSame([], $errors);
    }

    public function testValidateReportsMissingRequiredVars(): void
    {
        $resolver = $this->fakeEnv([]);

        $errors = $this->command->validate($resolver, 'development');

        $this->assertContains('app.baseURL is not set', $errors);
        $this->assertContains('database.default.hostname is not set', $errors);
        $this->assertContains('JWT_SECRET_KEY is not set', $errors);
        $this->assertContains('encryption.key is not set', $errors);
    }

    public function testValidateReportsEmptyRequiredVars(): void
    {
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => '',
            'database.default.hostname'     => '   ',
            'database.default.database'     => '',
            'database.default.username'     => '',
            'encryption.key'                => '',
            'JWT_SECRET_KEY'                => '',
        ]);

        $errors = $this->command->validate($resolver, 'development');

        $this->assertContains('app.baseURL is empty', $errors);
        $this->assertContains('database.default.hostname is empty', $errors);
    }

    public function testValidateRejectsShortJwtSecret(): void
    {
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'http://localhost:8080/',
            'database.default.hostname'     => '127.0.0.1',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'root',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(32)),
            'JWT_SECRET_KEY'                => 'too-short',
        ]);

        $errors = $this->command->validate($resolver, 'development');

        $this->assertContains('JWT_SECRET_KEY is too short', $errors);
    }

    public function testValidateRejectsPlaceholderJwtSecret(): void
    {
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'http://localhost:8080/',
            'database.default.hostname'     => '127.0.0.1',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'root',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(32)),
            'JWT_SECRET_KEY'                => str_repeat('your-secret-here-CHANGE-ME-1234567890', 3),
        ]);

        $errors = $this->command->validate($resolver, 'development');

        $this->assertContains('JWT_SECRET_KEY appears to be a placeholder', $errors);
    }

    public function testValidateRejectsRepeatingCharSecret(): void
    {
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'http://localhost:8080/',
            'database.default.hostname'     => '127.0.0.1',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'root',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(32)),
            'JWT_SECRET_KEY'                => str_repeat('a', 80),
        ]);

        $errors = $this->command->validate($resolver, 'development');

        $this->assertContains('JWT_SECRET_KEY appears to be a placeholder', $errors);
    }

    public function testValidateAcceptsHex2binEncryptionKeyOf32Bytes(): void
    {
        // CI4's default encryption.key is 32 bytes (AES-256). Should pass.
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'http://localhost:8080/',
            'database.default.hostname'     => '127.0.0.1',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'root',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(32)),
            'JWT_SECRET_KEY'                => $this->strongSecret(),
        ]);

        $errors = $this->command->validate($resolver, 'development');

        $this->assertSame([], $errors);
    }

    public function testValidateRejectsTooShortEncryptionKey(): void
    {
        // 16 bytes is below the 32-byte AES-256 minimum.
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'http://localhost:8080/',
            'database.default.hostname'     => '127.0.0.1',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'root',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(16)),
            'JWT_SECRET_KEY'                => $this->strongSecret(),
        ]);

        $errors = $this->command->validate($resolver, 'development');

        $this->assertContains('encryption.key is too short', $errors);
    }

    public function testValidateRequiresCorsAllowedOriginsInProduction(): void
    {
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'https://api.example.com/',
            'database.default.hostname'     => 'db.example.com',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'app',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(64)),
            'JWT_SECRET_KEY'                => $this->strongSecret(),
        ]);

        $errors = $this->command->validate($resolver, 'production');

        $this->assertContains('CORS_ALLOWED_ORIGINS is required in production', $errors);
    }

    public function testValidatePassesInProductionWithCorsConfigured(): void
    {
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'https://api.example.com/',
            'database.default.hostname'     => 'db.example.com',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'app',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(64)),
            'JWT_SECRET_KEY'                => $this->strongSecret(),
            'CORS_ALLOWED_ORIGINS'          => 'https://admin.example.com',
        ]);

        $errors = $this->command->validate($resolver, 'production');

        $this->assertSame([], $errors);
    }

    public function testStrictModePromotesEmailAndSentryToErrors(): void
    {
        $resolver = $this->fakeEnv([
            'app.baseURL'                   => 'https://api.example.com/',
            'database.default.hostname'     => 'db.example.com',
            'database.default.database'     => 'ci4_website_builder_api',
            'database.default.username'     => 'app',
            'encryption.key'                => 'hex2bin:' . bin2hex(random_bytes(64)),
            'JWT_SECRET_KEY'                => $this->strongSecret(),
            'CORS_ALLOWED_ORIGINS'          => 'https://admin.example.com',
        ]);

        $errors = $this->command->validate($resolver, 'development', strict: true);

        $this->assertContains('EMAIL_FROM_ADDRESS is required in strict mode', $errors);
        $this->assertContains('SENTRY_DSN is required in strict mode', $errors);
    }
}
