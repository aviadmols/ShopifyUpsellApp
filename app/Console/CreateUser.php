<?php

namespace App\Console;

use App\Models\User;
use Illuminate\Console\Command;

class CreateUser extends Command
{
    protected $signature = 'user:create {name : Display name} {email : Login email} {password : Password}';

    protected $description = 'Create a new admin user (e.g. php artisan user:create "Admin" admin@example.com MyPassword123)';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("User with email [{$email}] already exists. Use user:reset-password to change password.");

            return self::FAILURE;
        }

        User::create([
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => $this->argument('password'),
        ]);

        $this->info("User [{$email}] created. You can log in to Filament with this email and password.");

        return self::SUCCESS;
    }
}
