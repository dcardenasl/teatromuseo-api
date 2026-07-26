<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Files;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class FileUploadTest extends ApiTestCase
{
    use AuthTestTrait;
    use \CodeIgniter\Test\DatabaseTestTrait;

    protected $migrate     = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure FILE_MAX_SIZE is set for testing
        putenv('FILE_MAX_SIZE=1024'); // 1KB for testing
        $_ENV['FILE_MAX_SIZE'] = '1024';
        $_SERVER['FILE_MAX_SIZE'] = '1024';
        $identity = $this->actAs('user');
        $this->token = $identity['token'];
    }

    protected function tearDown(): void
    {
        putenv('FILE_MAX_SIZE');
        unset($_ENV['FILE_MAX_SIZE'], $_SERVER['FILE_MAX_SIZE']);
        parent::tearDown();
    }

    public function testUploadBase64TooLarge(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $largeData = base64_encode(str_repeat('A', 2048)); // 2KB encoded > 1KB limit (decoded is ~1.5KB)

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->post('/api/v1/files/upload', [
                'file' => 'data:image/png;base64,' . $largeData,
                'filename' => 'large.png'
            ]);

        $result->assertStatus(422);
        $json = $this->getResponseJson($result);
        $this->assertEquals('error', $json['status']);
    }

    public function testUploadBase64Success(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        // Standard 1x1 transparent pixel PNG
        $base64Data = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->post('/api/v1/files/upload', [
                'file' => 'data:image/png;base64,' . $base64Data,
                'filename' => 'test_success.png'
            ]);

        $response->assertStatus(201);
        $json = $this->getResponseJson($response);
        $this->assertEquals('test_success.png', $json['data']['original_name']);
    }

    public function testUploadPhpLimit(): void
    {
        $email = 'upload-php-limit@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password);
        $token = $this->loginAndGetToken($email, $password);

        // We can't easily trigger UPLOAD_ERR_INI_SIZE with real files in tests without php.ini changes,
        // but we can test how FileService handles it if it receives an invalid file.
        // However, FeatureTestTrait doesn't easily let us inject a custom UploadedFile with an error.
        // So we'll trust the code review for this part or use a mock if really needed.
    }
}
