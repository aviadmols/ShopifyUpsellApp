<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('name')->default('');
            $table->boolean('enabled')->default(true);

            // Surfaces where this survey can be shown: checkout, thank_you, post_purchase
            $table->json('surfaces')->nullable();

            // RuleEngine-compatible conditions (built from rule_match_type + rule_conditions)
            $table->json('conditions')->nullable();
            $table->string('rule_match_type')->default('and');
            $table->json('rule_conditions')->nullable();

            // Reward: currently only static coupon code shown to buyer
            $table->string('reward_type')->default('code');
            $table->string('reward_code')->default('');
            $table->string('reward_message')->nullable();

            // Questions schema
            $table->json('questions')->nullable();
            // UI settings: title, description, button labels, etc.
            $table->json('ui')->nullable();

            $table->timestamps();
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->index(['shop_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};

