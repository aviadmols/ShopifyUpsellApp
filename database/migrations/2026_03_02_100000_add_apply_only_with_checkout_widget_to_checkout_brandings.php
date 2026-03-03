<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_brandings', function (Blueprint $table) {
            $table->boolean('apply_only_with_checkout_widget')->default(true)->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_brandings', function (Blueprint $table) {
            $table->dropColumn('apply_only_with_checkout_widget');
        });
    }
};
