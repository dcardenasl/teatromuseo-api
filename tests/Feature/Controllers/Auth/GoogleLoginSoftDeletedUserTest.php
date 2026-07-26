<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use App\DTO\Response\Identity\GoogleIdentityResponseDTO;
use App\Interfaces\Auth\GoogleIdentityServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Models\UserModel;
use Tests\Support\ApiTestCase;

/**
 * Audit B9.2 (2026-05-07): pin the contract for "soft-deleted user
 * attempts Google login again". The implementation in
 * `App\Services\Auth\Actions\GoogleLoginAction` deliberately reactivates
 * soft-deleted users (sets `deleted_at = NULL`, status reverts to
 * `pending_approval` or `active` per the env). This test pins that
 * behavior so a future refactor that accidentally turns the lookup
 * into a hard-find (no `withDeleted()`) is caught immediately.
 *
 * @internal
 */
final class GoogleLoginSoftDeletedUserTest extends ApiTestCase
{
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();

        // Reset the entire dependency chain that closes over the
        // googleIdentityService — leaving any of these around causes the
        // next test in the suite to consume a stale mock and hit
        // "$email must not be accessed before initialization".
        \Config\Services::resetSingle('googleIdentityService');
        \Config\Services::resetSingle('emailService');
        \Config\Services::resetSingle('googleLoginAction');
        \Config\Services::resetSingle('googleAuthHandler');
        \Config\Services::resetSingle('authService');
    }

    protected function tearDown(): void
    {
        \Config\Services::resetSingle('googleIdentityService');
        \Config\Services::resetSingle('emailService');
        \Config\Services::resetSingle('googleLoginAction');
        \Config\Services::resetSingle('googleAuthHandler');
        \Config\Services::resetSingle('authService');
        parent::tearDown();
    }

    public function testSoftDeletedUserGoogleLoginReactivatesAccount(): void
    {
        // Seed a previously-active user, then soft-delete them.
        $email = 'soft-deleted-google@example.com';
        $userId = $this->userModel->insert([
            'email'             => $email,
            'first_name'        => 'Old',
            'last_name'         => 'User',
            'password'          => password_hash('Password!23', PASSWORD_BCRYPT),
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'approved_at'       => date('Y-m-d H:i:s'),
            'oauth_provider'    => 'google',
            'oauth_provider_id' => 'google-sub-old',
        ]);
        $this->assertIsInt($userId);
        $this->userModel->delete($userId);

        // Confirm the soft-delete actually wrote `deleted_at`.
        $deletedRow = $this->userModel->withDeleted()->find($userId);
        $this->assertNotNull(
            $deletedRow->deleted_at ?? null,
            'Test fixture invalid — user should be soft-deleted before invoking Google login.'
        );

        $this->injectGoogleIdentityMock([
            'provider'    => 'google',
            'provider_id' => 'google-sub-old',
            'email'       => $email,
            'first_name'  => 'Reactivated',
            'last_name'   => 'User',
            'avatar_url'  => null,
            'claims'      => [],
        ]);
        $this->injectEmailServiceMock();

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        // Soft-deleted reactivation flow returns 202 Accepted with a
        // pending-registration message (the user must be re-approved).
        $result->assertStatus(202);

        $row = $this->userModel->withDeleted()->find($userId);
        $this->assertInstanceOf(\App\Entities\UserEntity::class, $row);
        $this->assertNull(
            $row->deleted_at,
            'After Google reactivation, deleted_at must be cleared.'
        );
        $this->assertContains(
            $row->status,
            ['pending_approval', 'active'],
            'Reactivated user must land in pending_approval or active depending on env policy.'
        );
        $this->assertSame('google', $row->oauth_provider);
    }

    public function testHardLookupWouldMiss_butReactivationFindsTheUser(): void
    {
        // Regression guard: this test fails if the action's user lookup is
        // ever changed from `findByEmailWithDeleted` to `findByEmail`. The
        // soft-deleted user would then NOT be found, and the action would
        // create a duplicate `pending` row in `users`. We assert exactly
        // one row at the email after the flow.
        $email = 'reactivation-uniqueness@example.com';
        $userId = $this->userModel->insert([
            'email'             => $email,
            'first_name'        => 'Old',
            'last_name'         => 'Account',
            'password'          => password_hash('Password!23', PASSWORD_BCRYPT),
            'status'            => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'approved_at'       => date('Y-m-d H:i:s'),
            'oauth_provider'    => 'google',
            'oauth_provider_id' => 'google-sub-uniq',
        ]);
        $this->assertIsInt($userId);
        $this->userModel->delete($userId);

        $this->injectGoogleIdentityMock([
            'provider'    => 'google',
            'provider_id' => 'google-sub-uniq',
            'email'       => $email,
            'first_name'  => 'Old',
            'last_name'   => 'Account',
            'avatar_url'  => null,
            'claims'      => [],
        ]);
        $this->injectEmailServiceMock();

        $this->withBodyFormat('json')
            ->post('/api/v1/auth/google-login', [
                'id_token' => 'google.id.token',
            ]);

        $count = $this->userModel->withDeleted()->where('email', $email)->countAllResults();
        $this->assertSame(1, $count, 'Reactivation must NOT produce a duplicate row at the same email.');
    }

    private function injectGoogleIdentityMock(array $identityData): void
    {
        $mock = $this->createMock(GoogleIdentityServiceInterface::class);
        $mock->method('verifyIdToken')->willReturn(
            GoogleIdentityResponseDTO::fromArray($identityData)
        );
        \Config\Services::injectMock('googleIdentityService', $mock);
    }

    private function injectEmailServiceMock(): void
    {
        $mock = $this->createMock(EmailServiceInterface::class);
        $mock->method('queueTemplate')->willReturn(1);
        \Config\Services::injectMock('emailService', $mock);
    }
}
