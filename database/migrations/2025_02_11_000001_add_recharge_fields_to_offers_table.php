<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Recharge/subscription support: offer type, selling plan, post-purchase subscription.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('offer_type')->default('one_time')->after('image_url'); // one_time, subscription, both
            $table->string('selling_plan_id')->nullable()->after('offer_type'); // Shopify selling plan GID
            $table->string('recharge_subscription_variant_id')->nullable()->after('selling_plan_id'); // Recharge variant mapping if needed
            $table->boolean('allow_subscription_in_post_purchase')->default(false)->after('recharge_subscription_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'offer_type',
                'selling_plan_id',
                'recharge_subscription_variant_id',
                'allow_subscription_in_post_purchase',
            ]);
        });
    }
};
