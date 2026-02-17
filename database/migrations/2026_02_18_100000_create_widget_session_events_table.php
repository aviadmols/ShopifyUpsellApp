<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->unsignedBigInteger('block_id')->nullable()->index();
            $table->string('session_key', 255)->nullable()->index();
            $table->string('event_type', 32)->index(); // 'view' | 'click'
            $table->json('context_snapshot')->nullable();
            $table->boolean('rule_passed')->nullable();
            $table->boolean('widget_shown')->nullable();
            $table->string('click_target', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_session_events');
    }
};
