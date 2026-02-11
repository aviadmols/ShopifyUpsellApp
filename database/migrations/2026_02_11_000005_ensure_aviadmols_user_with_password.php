<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * וידוא: אם לא קיים יוזר עם aviadmols@gmail.com – יוצר עם סיסמה 987654321.
     * אם קיים – מעדכן סיסמה ל־987654321.
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
        // אין ביטול – משאיר את המשתמש כמו שהוא
    }
};
