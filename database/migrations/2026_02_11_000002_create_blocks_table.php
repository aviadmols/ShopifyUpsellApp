<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Unified blocks for checkout, thank_you, post_purchase.
     * Config shape per type: see App\Models\Block type constants and config docs.
     */
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('surface'); // checkout, thank_you, post_purchase
            $table->string('type'); // upsell, progress_bar, content_*, post_purchase_funnel
            $table->string('name')->default(''); // admin label
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('rule_id')->nullable()->constrained('rules')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->index(['shop_id', 'surface']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
