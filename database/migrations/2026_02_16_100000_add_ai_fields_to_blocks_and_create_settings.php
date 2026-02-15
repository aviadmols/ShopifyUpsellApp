<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->string('ai_generated_name')->nullable()->after('name');
            $table->text('ai_generated_description')->nullable()->after('ai_generated_name');
            $table->longText('ai_generated_php')->nullable()->after('ai_generated_description');
            $table->longText('ai_prompt')->nullable()->after('ai_generated_php');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 128)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn(['ai_generated_name', 'ai_generated_description', 'ai_generated_php', 'ai_prompt']);
        });
        Schema::dropIfExists('settings');
    }
};
