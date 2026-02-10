<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Creates the default admin user (Aviadmols@gmail.com).
     * Default password: ChangeMe123! — change after first login.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'Aviadmols@gmail.com'],
            [
                'name' => 'Aviad',
                'password' => 'ChangeMe123!',
            ]
        );
    }
}
