<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_experiences', function (Blueprint $table) {
            $table->string('cart_line_quantity_label_text', 120)->nullable();
            $table->string('cart_line_quantity_label_size', 20)->nullable();
            $table->string('cart_line_quantity_label_alignment', 20)->nullable();
            $table->string('cart_line_popover_padding_x', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('checkout_experiences', function (Blueprint $table) {
            $table->dropColumn([
                'cart_line_quantity_label_text',
                'cart_line_quantity_label_size',
                'cart_line_quantity_label_alignment',
                'cart_line_popover_padding_x',
            ]);
        });
    }
};
