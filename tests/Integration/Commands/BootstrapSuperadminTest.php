<?php

declare(strict_types=1);

namespace Tests\Integration\Commands;

use App\Database\Seeds\RbacBootstrapSeeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use ReflectionClass;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
final class BootstrapSuperadminTest extends IntegrationTestCase
{
    protected $seed        = RbacBootstrapSeeder::class;

    /**
     * @param array<string, mixed> $params
     */
    private function runCommand(string $command, array $params): string
    {
        $options = [];

        foreach ($params as $key => $value) {
            $options[$key] = $value === true ? null : (string) $value;
        }

        $reflection      = new ReflectionClass(CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setValue(null, $options);

        $io = new MockInputOutput();
        CLI::setInputOutput($io);

        try {
            service('commands')->run($command, []);
        } finally {
            $optionsProperty->setValue(null, []);
            CLI::resetInputOutput();
        }

        return $io->getOutput();
    }

    public function testSecondRunIsIdempotentAndDoesNotCreateAnotherSuperadmin(): void
    {
        $firstOutput = $this->runCommand('users:bootstrap-superadmin', [
            'email'      => 'first.owner@test.example',
            'password'   => 'TestPass456!',
            'first-name' => 'First',
            'last-name'  => 'Superadmin',
        ]);

        $this->assertStringContainsString('Superadmin user created successfully.', $firstOutput);

        $db = \Config\Database::connect();
        $superadminRoleId = (int) $this->grabFromDatabase('roles', 'id', ['code' => 'superadmin']);

        $beforeSecondRun = $db->table('user_roles')
            ->where('role_id', $superadminRoleId)
            ->countAllResults();

        $secondOutput = $this->runCommand('users:bootstrap-superadmin', [
            'email'    => 'second.owner@test.example',
            'password' => 'TestPass456!',
        ]);

        $this->assertStringContainsString('A superadmin already exists. Bootstrap can only run once.', $secondOutput);

        $afterSecondRun = $db->table('user_roles')
            ->where('role_id', $superadminRoleId)
            ->countAllResults();

        $this->assertSame($beforeSecondRun, $afterSecondRun, 'Second run must not assign superadmin role to a new user.');
        $this->dontSeeInDatabase('users', ['email' => 'second.owner@test.example']);
    }
}
