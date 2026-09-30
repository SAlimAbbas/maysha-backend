<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maysha:seed-admin {--email= : The email for the admin} {--password= : The password for the admin} {--name= : The name of the admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed or update the initial system administrator using environment variables or options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?: env('ADMIN_SEED_EMAIL');
        $password = $this->option('password') ?: env('ADMIN_SEED_PASSWORD');
        $name = $this->option('name') ?: env('ADMIN_SEED_NAME', 'Maysha Super Admin');

        if (! $email || ! $password) {
            $this->error('Admin email and password must be provided via --email/--password or ADMIN_SEED_EMAIL/ADMIN_SEED_PASSWORD in .env.');

            return self::FAILURE;
        }

        if (strlen($password) < 10) {
            $this->error('Admin password must be at least 10 characters long.');

            return self::FAILURE;
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $this->info("Admin user '{$admin->email}' successfully provisioned with role 'admin'.");

        return self::SUCCESS;
    }
}
