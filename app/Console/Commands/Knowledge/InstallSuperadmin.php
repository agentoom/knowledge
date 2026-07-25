<?php

namespace App\Console\Commands\Knowledge;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

class InstallSuperadmin extends Command
{
    protected $signature = 'knowledge:install';

    protected $description = 'Create the superadmin user if it does not already exist';

    public function handle(): int
    {
        if (User::where('email', 'knowledge@agentoom.com')->exists()) {
            $this->info('Superadmin user already exists.');

            return self::SUCCESS;
        }

        User::create([
            'name' => 'Super Admin',
            'email' => 'knowledge@agentoom.com',
            'password' => 'changeme',
            'role' => Role::Admin,
        ]);

        $this->info('Superadmin user created successfully.');
        $this->warn('Email: knowledge@agentoom.com');
        $this->warn('Password: changeme');
        $this->warn('Please change the password immediately.');

        return self::SUCCESS;
    }
}
