<?php

namespace App\Console;

use App\Models\User;
use Illuminate\Console\Command;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password {email : User email} {password : New password}';

    protected $description = 'Reset a user password by email (e.g. php artisan user:reset-password user@example.com NewPassword123)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] not found.");

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Password updated for {$user->email}.");

        return self::SUCCESS;
    }
}
