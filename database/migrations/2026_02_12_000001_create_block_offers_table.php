<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot: blocks <-> offers with sort order for widget-based offer management.
     */
    public function up(): void
    {
        Schema::create('block_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['block_id', 'offer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_offers');
    }
};
