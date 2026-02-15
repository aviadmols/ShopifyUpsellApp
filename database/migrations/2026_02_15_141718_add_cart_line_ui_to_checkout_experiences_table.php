<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checkout_experiences', function (Blueprint $table) {
            // Cart line UI – Popover width
            $table->string('cart_line_popover_width_mode', 20)->nullable();
            $table->string('cart_line_popover_width_preset', 20)->nullable();
            $table->unsignedSmallInteger('cart_line_popover_width_px')->nullable();
            // Cart line UI – +/- buttons
            $table->string('cart_line_plus_minus_kind', 20)->nullable();
            $table->string('cart_line_plus_minus_appearance', 20)->nullable();
            $table->string('cart_line_plus_minus_size', 20)->nullable();
            $table->string('cart_line_plus_minus_corner_radius', 30)->nullable();

            // Quantity rules
            $table->string('quantity_rule_mode', 30)->nullable();
            $table->json('quantity_include_product_ids')->nullable();
            $table->json('quantity_exclude_product_ids')->nullable();
            $table->json('quantity_include_collection_ids')->nullable();
            $table->json('quantity_exclude_collection_ids')->nullable();
            $table->json('quantity_include_tags')->nullable();
            $table->json('quantity_exclude_tags')->nullable();
            $table->json('quantity_include_vendors')->nullable();
            $table->json('quantity_exclude_vendors')->nullable();
            $table->json('quantity_include_product_types')->nullable();
            $table->json('quantity_exclude_product_types')->nullable();
            $table->string('quantity_require_subscription_state', 30)->nullable();
            $table->decimal('quantity_min_subtotal', 12, 2)->nullable();
            $table->decimal('quantity_max_subtotal', 12, 2)->nullable();
            $table->unsignedInteger('quantity_min_cart_items')->nullable();
            $table->unsignedInteger('quantity_max_cart_items')->nullable();

            // Subscription rules
            $table->string('subscription_rule_mode', 30)->nullable();
            $table->json('subscription_include_product_ids')->nullable();
            $table->json('subscription_exclude_product_ids')->nullable();
            $table->json('subscription_include_collection_ids')->nullable();
            $table->json('subscription_exclude_collection_ids')->nullable();
            $table->json('subscription_include_tags')->nullable();
            $table->json('subscription_exclude_tags')->nullable();
            $table->json('subscription_include_vendors')->nullable();
            $table->json('subscription_exclude_vendors')->nullable();
            $table->json('subscription_include_product_types')->nullable();
            $table->json('subscription_exclude_product_types')->nullable();
            $table->string('subscription_require_subscription_state', 30)->nullable();
            $table->decimal('subscription_min_subtotal', 12, 2)->nullable();
            $table->decimal('subscription_max_subtotal', 12, 2)->nullable();
            $table->unsignedInteger('subscription_min_cart_items')->nullable();
            $table->unsignedInteger('subscription_max_cart_items')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkout_experiences', function (Blueprint $table) {
            $table->dropColumn([
                'cart_line_popover_width_mode',
                'cart_line_popover_width_preset',
                'cart_line_popover_width_px',
                'cart_line_plus_minus_kind',
                'cart_line_plus_minus_appearance',
                'cart_line_plus_minus_size',
                'cart_line_plus_minus_corner_radius',
                'quantity_rule_mode',
                'quantity_include_product_ids',
                'quantity_exclude_product_ids',
                'quantity_include_collection_ids',
                'quantity_exclude_collection_ids',
                'quantity_include_tags',
                'quantity_exclude_tags',
                'quantity_include_vendors',
                'quantity_exclude_vendors',
                'quantity_include_product_types',
                'quantity_exclude_product_types',
                'quantity_require_subscription_state',
                'quantity_min_subtotal',
                'quantity_max_subtotal',
                'quantity_min_cart_items',
                'quantity_max_cart_items',
                'subscription_rule_mode',
                'subscription_include_product_ids',
                'subscription_exclude_product_ids',
                'subscription_include_collection_ids',
                'subscription_exclude_collection_ids',
                'subscription_include_tags',
                'subscription_exclude_tags',
                'subscription_include_vendors',
                'subscription_exclude_vendors',
                'subscription_include_product_types',
                'subscription_exclude_product_types',
                'subscription_require_subscription_state',
                'subscription_min_subtotal',
                'subscription_max_subtotal',
                'subscription_min_cart_items',
                'subscription_max_cart_items',
            ]);
        });
    }
};
