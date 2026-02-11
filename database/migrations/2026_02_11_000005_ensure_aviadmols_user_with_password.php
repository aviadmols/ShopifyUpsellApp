<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ensure user aviadmols@gmail.com exists with password 987654321; create or update.
     */
    public function up(): void
    {
        User::updateOrCreate(
            ['email' => 'aviadmols@gmail.com'],
            [
                'name' => 'Aviad',
                'password' => '987654321',
            ]
        );
    }

    public function down(): void
    {
        // No rollback – leave user as is
    }
};
