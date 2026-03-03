<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_brandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('checkout_profile_id')->nullable();
            $table->json('design_system')->nullable();
            $table->json('customizations')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        Schema::table('checkout_brandings', function (Blueprint $table) {
            $table->unique('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_brandings');
    }
};
