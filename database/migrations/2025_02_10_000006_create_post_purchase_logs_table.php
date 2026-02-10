<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Logs post-purchase impressions, accept, decline; idempotency for accept.
     */
    public function up(): void
    {
        Schema::create('post_purchase_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('order_id'); // Shopify order GID or numeric id
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->string('event'); // impression, accept, decline
            $table->string('idempotency_key')->unique()->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_purchase_logs');
    }
};
