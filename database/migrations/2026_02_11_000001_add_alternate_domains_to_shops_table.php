<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Store alternate domains (e.g. millsdailypacks.myshopify.com, millsdailypacks-usa.myshopify.com)
     * so checkout/API can resolve the same shop from different URLs.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->json('alternate_domains')->nullable()->after('shop_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('alternate_domains');
        });
    }
};
