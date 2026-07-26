<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

class BootstrapSuperadmin extends BaseCommand
{
    protected $group = 'Users';
    protected $name = 'users:bootstrap-superadmin';
    protected $description = 'Create the first superadmin user (idempotent: exits successfully if one already exists).';
    protected $usage = 'users:bootstrap-superadmin --email <email> --password <password> [--first-name <first>] [--last-name <last>]';
    protected $options = [
        '--email'      => 'Superadmin email (required)',
        '--password'   => 'Superadmin password (required)',
        '--first-name' => 'First name (optional)',
        '--last-name'  => 'Last name (optional)',
    ];

    public function run(array $params)
    {
        $email = trim((string) $this->resolveOption('email'));
        $password = (string) $this->resolveOption('password');
        $firstName = $this->resolveOptional('first-name');
        $lastName = $this->resolveOptional('last-name');

        if ($email === '' || $password === '') {
            CLI::error('Both --email and --password are required.');
            return EXIT_ERROR;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            CLI::error('Invalid email format.');
            return EXIT_ERROR;
        }

        $db = \Config\Database::connect();

        $roleResult     = $db->table('roles')
            ->where('code', 'superadmin')
            ->get();
        $superadminRole = $roleResult === false ? null : $roleResult->getRowArray();

        if ($superadminRole === null) {
            CLI::error('Superadmin role not found. Run "php spark db:seed RbacBootstrapSeeder" first.');
            return EXIT_ERROR;
        }

        $superadminRoleId = (int) $superadminRole['id'];

        // Refuse to run if any user already has the superadmin role
        $existingResult     = $db->table('user_roles')
            ->where('role_id', $superadminRoleId)
            ->limit(1)
            ->get();
        $existingSuperadmin = $existingResult === false ? null : $existingResult->getRowArray();

        if ($existingSuperadmin !== null) {
            CLI::write('A superadmin already exists. Bootstrap can only run once.', 'yellow');
            return EXIT_SUCCESS;
        }

        /** @var \App\Models\UserModel $userModel */
        $userModel = model(\App\Models\UserModel::class);

        /** @var \App\Entities\UserEntity|null $existingUser */
        $existingUser = $userModel
            ->withDeleted()
            ->where('email', $email)
            ->first();

        if ($existingUser !== null) {
            if (! empty($existingUser->deleted_at)) {
                $userModel->update((int) $existingUser->id, ['deleted_at' => null]);
            }

            $updateData = [
                'status' => 'active',
                'password' => password_hash($password, PASSWORD_BCRYPT),
            ];

            if ($firstName !== null) {
                $updateData['first_name'] = $firstName;
            }
            if ($lastName !== null) {
                $updateData['last_name'] = $lastName;
            }
            if (empty($existingUser->email_verified_at)) {
                $updateData['email_verified_at'] = date('Y-m-d H:i:s');
            }

            if (! $userModel->update((int) $existingUser->id, $updateData)) {
                CLI::error('Failed to promote existing user to superadmin.');
                return EXIT_ERROR;
            }

            Services::userRoleAssignmentService()->assignRole((int) $existingUser->id, $superadminRoleId);
            CLI::write('Existing user promoted to superadmin.', 'green');
            CLI::write('User ID: ' . (int) $existingUser->id, 'green');
            return EXIT_SUCCESS;
        }

        $userId = $userModel->insert([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        if (! $userId) {
            CLI::error('Failed to create superadmin user.');
            return EXIT_ERROR;
        }

        Services::userRoleAssignmentService()->assignRole((int) $userId, $superadminRoleId);
        CLI::write('Superadmin user created successfully.', 'green');
        CLI::write('User ID: ' . (int) $userId, 'green');
        return EXIT_SUCCESS;
    }

    private function resolveOptional(string $option): ?string
    {
        $value = $this->resolveOption($option);
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function resolveOption(string $name): ?string
    {
        $value = CLI::getOption($name);

        if ($value === null || $value === true) {
            foreach (CLI::getOptions() as $key => $val) {
                if (str_starts_with($key, "{$name}=")) {
                    return substr($key, strlen($name) + 1);
                }
            }
        }

        if ($value === true) {
            return null;
        }

        return is_string($value) ? $value : null;
    }
}
