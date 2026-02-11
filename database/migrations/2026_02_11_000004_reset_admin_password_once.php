<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * One-time reset for aviadmols@gmail.com so you can log in after deploy.
     * Change your password in the app after first login.
     */
    public function up(): void
    {
        $user = User::where('email', 'aviadmols@gmail.com')->first();
        if ($user) {
            $user->password = Hash::make('TempPass2025!');
            $user->save();
        }
    }

    public function down(): void
    {
        // Nothing to reverse
    }
};
