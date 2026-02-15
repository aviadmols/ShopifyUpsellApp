<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'checkout_experiences';

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $addString = function (string $column, int $length = 255) use ($blueprint, $table): void {
                if (! Schema::hasColumn($table, $column)) {
                    $blueprint->string($column, $length)->nullable();
                }
            };
            $addJson = function (string $column) use ($blueprint, $table): void {
                if (! Schema::hasColumn($table, $column)) {
                    $blueprint->json($column)->nullable();
                }
            };
            $addUnsignedSmallInt = function (string $column) use ($blueprint, $table): void {
                if (! Schema::hasColumn($table, $column)) {
                    $blueprint->unsignedSmallInteger($column)->nullable();
                }
            };
            $addUnsignedInt = function (string $column) use ($blueprint, $table): void {
                if (! Schema::hasColumn($table, $column)) {
                    $blueprint->unsignedInteger($column)->nullable();
                }
            };
            $addDecimal = function (string $column) use ($blueprint, $table): void {
                if (! Schema::hasColumn($table, $column)) {
                    $blueprint->decimal($column, 12, 2)->nullable();
                }
            };

            // Cart line appearance columns (from advanced UI plan)
            $addString('cart_line_popover_width_mode', 20);
            $addString('cart_line_popover_width_preset', 20);
            $addUnsignedSmallInt('cart_line_popover_width_px');
            $addString('cart_line_plus_minus_kind', 20);
            $addString('cart_line_plus_minus_appearance', 20);
            $addString('cart_line_plus_minus_size', 20);
            $addString('cart_line_plus_minus_corner_radius', 30);

            // Quantity rules
            $addString('quantity_rule_mode', 30);
            $addJson('quantity_include_product_ids');
            $addJson('quantity_exclude_product_ids');
            $addJson('quantity_include_collection_ids');
            $addJson('quantity_exclude_collection_ids');
            $addJson('quantity_include_tags');
            $addJson('quantity_exclude_tags');
            $addJson('quantity_include_vendors');
            $addJson('quantity_exclude_vendors');
            $addJson('quantity_include_product_types');
            $addJson('quantity_exclude_product_types');
            $addString('quantity_require_subscription_state', 30);
            $addDecimal('quantity_min_subtotal');
            $addDecimal('quantity_max_subtotal');
            $addUnsignedInt('quantity_min_cart_items');
            $addUnsignedInt('quantity_max_cart_items');

            // Subscription rules
            $addString('subscription_rule_mode', 30);
            $addJson('subscription_include_product_ids');
            $addJson('subscription_exclude_product_ids');
            $addJson('subscription_include_collection_ids');
            $addJson('subscription_exclude_collection_ids');
            $addJson('subscription_include_tags');
            $addJson('subscription_exclude_tags');
            $addJson('subscription_include_vendors');
            $addJson('subscription_exclude_vendors');
            $addJson('subscription_include_product_types');
            $addJson('subscription_exclude_product_types');
            $addString('subscription_require_subscription_state', 30);
            $addDecimal('subscription_min_subtotal');
            $addDecimal('subscription_max_subtotal');
            $addUnsignedInt('subscription_min_cart_items');
            $addUnsignedInt('subscription_max_cart_items');
        });
    }

    public function down(): void
    {
        // Intentionally no-op: this is a safe production backfill migration.
    }
};
