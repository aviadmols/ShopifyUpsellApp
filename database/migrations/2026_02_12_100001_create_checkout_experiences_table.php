<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            // Quantity in upsell block
            $table->boolean('quantity_in_upsell_enabled')->default(false);
            $table->unsignedSmallInteger('quantity_default')->default(1);
            $table->unsignedSmallInteger('quantity_min')->default(1);
            $table->unsignedSmallInteger('quantity_max')->default(10);

            // Quantity on cart lines (cart-line-item)
            $table->boolean('quantity_in_cart_enabled')->default(false);

            // Subscription upgrade message (Recharge / Selling Plans)
            $table->boolean('subscription_upgrade_enabled')->default(false);
            $table->string('subscription_upgrade_headline')->nullable();
            $table->string('subscription_upgrade_cta')->nullable();

            $table->timestamps();
        });

        Schema::table('checkout_experiences', function (Blueprint $table) {
            $table->unique('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_experiences');
    }
};
