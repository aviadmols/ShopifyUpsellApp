<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('surface'); // checkout, thank_you, post_purchase

            // Context identifiers (availability depends on surface)
            $table->string('order_id')->nullable();
            $table->string('checkout_token')->nullable();
            $table->string('customer_id')->nullable();

            $table->json('answers')->nullable();
            $table->string('reward_code_shown')->nullable();

            $table->timestamps();
        });

        Schema::table('survey_responses', function (Blueprint $table) {
            $table->index(['shop_id', 'survey_id']);
            $table->index(['survey_id', 'created_at']);
            $table->index(['shop_id', 'surface']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};

