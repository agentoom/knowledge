<?php

namespace App\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResolveAdminCredentials
{
    /**
     * Resolve the admin email and password from environment or generate a random password.
     *
     * Priority:
     * 1. ADMIN_EMAIL env var (falls back to 'admin@agentoom.com')
     * 2. ADMIN_PASSWORD env var (if set, use it; otherwise generate random)
     *
     * When a password is generated, it is written to storage/app/initial-admin-password.txt
     * as a fallback in case the console output is missed.
     *
     * @return array{email: string, password: string, wasGenerated: bool}
     */
    public static function resolve(): array
    {
        $email = env('ADMIN_EMAIL', 'admin@agentoom.com');
        $password = env('ADMIN_PASSWORD');

        $wasGenerated = ($password === null || $password === '' || $password === '0');

        if ($wasGenerated) {
            $password = Str::password(length: 20, letters: true, numbers: true, symbols: true);
        }

        // Always write to file on seed/install runs (overwrite, don't accumulate)
        $filePath = storage_path('app/initial-admin-password.txt');

        if ($wasGenerated || ! file_exists($filePath)) {
            $dir = dirname($filePath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($filePath, $password.PHP_EOL);
        }

        return [
            'email' => $email,
            'password' => $password,
            'wasGenerated' => $wasGenerated,
        ];
    }

    /**
     * Output the admin credentials to a console command with prominent formatting.
     */
    public static function outputToConsole(Command $command, string $email, string $password, bool $wasGenerated): void
    {
        if ($wasGenerated) {
            $command->newLine();
            $command->line(str_repeat('=', 72));
            $command->warn('  ADMIN PASSWORD (save this — shown once)');
            $command->line(str_repeat('=', 72));
            $command->newLine();
            $command->info("  Email:    {$email}");
            $command->info("  Password: {$password}");
            $command->newLine();
            $command->line(str_repeat('=', 72));
            $command->warn('  Also saved to: storage/app/initial-admin-password.txt');
            $command->line(str_repeat('=', 72));
            $command->newLine();
        } else {
            $command->info("Admin user created with email: {$email}");
        }
    }
}
