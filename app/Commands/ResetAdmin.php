<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;

class ResetAdmin extends BaseCommand
{
    protected $group = 'Administration';
    protected $name = 'admin:reset';
    protected $description = 'Set the primary administrator login email and password interactively.';
    protected $usage = 'admin:reset [email]';

    public function run(array $params): void
    {
        $email = trim((string) ($params[0] ?? CLI::prompt('Administrator email')));
        $password = CLI::prompt('New password');
        $confirmation = CLI::prompt('Confirm new password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.');
        }
        if ($password === '' || ! is_string($password) || strlen($password) < 8) {
            throw new RuntimeException('The password must contain at least 8 characters.');
        }
        if ($password !== $confirmation) {
            throw new RuntimeException('The passwords do not match.');
        }

        $db = Database::connect();
        $db->transStart();
        $db->table('people')->where('person_id', 1)->update(['email' => $email]);
        $db->table('employees')->where('person_id', 1)->update([
            'username' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'hash_version' => 2,
            'deleted' => 0,
        ]);
        $db->transComplete();

        if (! $db->transStatus()) {
            throw new RuntimeException('The administrator account could not be updated.');
        }

        CLI::write('Administrator account updated successfully.', 'green');
    }
}
