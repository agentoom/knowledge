<?php

namespace Database\Seeders;

use App\Actions\ResolveAdminCredentials;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            KnowledgeDemoSeeder::class,
        ]);

        $credentials = ResolveAdminCredentials::resolve();

        User::firstOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => 'Admin',
                'password' => $credentials['password'],
                'role' => 'admin',
            ]
        );

        if (app()->runningInConsole()) {
            ResolveAdminCredentials::outputToConsole(
                $this->command,
                $credentials['email'],
                $credentials['password'],
                $credentials['wasGenerated']
            );
        }
    }
}
