<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use App\Models\UserModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * Feature tests for PATCH /api/v1/auth/me — self-service profile update.
 *
 * Covers the rules introduced when self-edit was split from the admin
 * endpoint: any authenticated user can change their own first_name,
 * last_name and avatar_url; email/password/role assignments are not part
 * of the allowlist and must be ignored or rejected.
 */
class AuthMeUpdateTest extends ApiTestCase
{
    use AuthTestTrait;

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    public function testUpdateMeRequiresAuth(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::flush();
        $this->clearTestRequestHeaders();

        $result = $this->withBodyFormat('json')->patch('/api/v1/auth/me', [
            'first_name' => 'Anon',
        ]);

        $result->assertStatus(401);
    }

    public function testRegularUserCanUpdateOwnNameAndAvatar(): void
    {
        $this->actAs('user');
        $this->resetRequest();

        $result = $this->withBodyFormat('json')->patch('/api/v1/auth/me', [
            'first_name' => 'Renamed',
            'last_name'  => 'Self',
            'avatar_url' => 'https://example.com/avatars/me.png',
        ]);

        $result->assertStatus(200);

        // Contract: PATCH /auth/me returns the canonical "me" shape with
        // refreshed `permissions`. A regression here silently breaks UI
        // gating on the consumer side (e.g. admin's session-user refresh
        // after an avatar change). Lock the field.
        $json = $this->getResponseJson($result);
        $this->assertArrayHasKey('permissions', $json['data']);
        $this->assertIsArray($json['data']['permissions']);
        $this->assertArrayHasKey('roles', $json['data']);

        /** @var \App\Entities\UserEntity|null $reloaded */
        $reloaded = $this->userModel->find($this->currentUserId);
        $this->assertNotNull($reloaded);
        $this->assertSame('Renamed', $reloaded->first_name);
        $this->assertSame('Self', $reloaded->last_name);
        $this->assertSame('https://example.com/avatars/me.png', $reloaded->avatar_url);
    }

    public function testUpdateMeIgnoresDisallowedFields(): void
    {
        $this->actAs('user', ['email' => 'self-email@example.com']);
        $originalEmail = 'self-email@example.com';
        $this->resetRequest();

        $result = $this->withBodyFormat('json')->patch('/api/v1/auth/me', [
            'first_name' => 'Stays',
            'email'      => 'hijack@example.com',
            'password'   => 'NewPassw0rd!!',
            'role_ids'   => [1, 2, 3],
        ]);

        $result->assertStatus(200);

        /** @var \App\Entities\UserEntity|null $reloaded */
        $reloaded = $this->userModel->find($this->currentUserId);
        $this->assertNotNull($reloaded);
        $this->assertSame('Stays', $reloaded->first_name);
        $this->assertSame($originalEmail, strtolower((string) $reloaded->email));
    }

    public function testUpdateMeWithEmptyPayloadReturns422(): void
    {
        $this->actAs('user');
        $this->resetRequest();

        $result = $this->withBodyFormat('json')->patch('/api/v1/auth/me', []);

        $result->assertStatus(422);
    }
}
