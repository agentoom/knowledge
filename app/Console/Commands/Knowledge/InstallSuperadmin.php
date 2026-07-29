<?php

namespace App\Console\Commands\Knowledge;

use App\Actions\ResolveAdminCredentials;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

class InstallSuperadmin extends Command
{
    protected $signature = 'knowledge:install';

    protected $description = 'Create the superadmin user if it does not already exist';

    public function handle(): int
    {
        $credentials = ResolveAdminCredentials::resolve();

        if (User::where('email', $credentials['email'])->exists()) {
            $this->info('Superadmin user already exists.');

            return self::SUCCESS;
        }

        User::create([
            'name' => 'Super Admin',
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'role' => Role::Admin,
        ]);

        $this->info('Superadmin user created successfully.');
        ResolveAdminCredentials::outputToConsole(
            $this,
            $credentials['email'],
            $credentials['password'],
            $credentials['wasGenerated']
        );

        return self::SUCCESS;
    }
}
