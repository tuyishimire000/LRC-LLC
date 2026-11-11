<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateFinanceUser extends Command
{
    protected $signature = 'lrc:finance-user';
    protected $description = 'Create a staff account with the finance_manager role';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Finance manager full name'));
        $email = strtolower(trim((string) $this->ask('Finance manager email')));
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Invalid details or passwords do not match. No account was created.');
            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('An account with this email already exists.');
            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'finance_manager'],
            ['label' => 'Finance manager']
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->info('Finance manager account created. Keep the password secure.');
        $this->warn('Payment maker/checker controls require a different finance user to verify each payment.');
        return self::SUCCESS;
    }
}
