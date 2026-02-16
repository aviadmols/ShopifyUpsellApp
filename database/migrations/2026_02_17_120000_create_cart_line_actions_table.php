<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_line_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_experience_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('label', 120);
            $table->text('message')->nullable();
            $table->string('action_type', 40);
            $table->string('target_variant_gid', 128)->nullable();
            $table->unsignedInteger('target_quantity')->default(1);
            $table->string('target_selling_plan_id', 128)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('rule_mode', 30)->nullable()->default('all');
            $table->json('include_product_ids')->nullable();
            $table->json('exclude_product_ids')->nullable();
            $table->json('include_collection_ids')->nullable();
            $table->json('exclude_collection_ids')->nullable();
            $table->json('include_tags')->nullable();
            $table->json('exclude_tags')->nullable();
            $table->json('include_vendors')->nullable();
            $table->json('exclude_vendors')->nullable();
            $table->json('include_product_types')->nullable();
            $table->json('exclude_product_types')->nullable();
            $table->string('require_subscription_state', 30)->nullable();
            $table->decimal('min_subtotal', 12, 2)->nullable();
            $table->decimal('max_subtotal', 12, 2)->nullable();
            $table->unsignedInteger('min_cart_items')->nullable();
            $table->unsignedInteger('max_cart_items')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_line_actions');
    }
};
