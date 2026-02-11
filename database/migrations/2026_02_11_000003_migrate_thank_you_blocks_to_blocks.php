<?php

use App\Models\Block;
use App\Models\ThankYouBlock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy existing thank_you_blocks into blocks (surface=thank_you).
     * thank_you_blocks table is kept for now (legacy fallback).
     */
    public function up(): void
    {
        if (! Schema::hasTable('thank_you_blocks') || ! Schema::hasTable('blocks')) {
            return;
        }

        ThankYouBlock::query()->orderBy('id')->each(function (ThankYouBlock $ty) {
            Block::create([
                'shop_id' => $ty->shop_id,
                'surface' => 'thank_you',
                'type' => Block::thankYouBlockTypeToBlockType($ty->type),
                'name' => (string) (is_array($ty->config) ? ($ty->config['title'] ?? '') : ''),
                'config' => is_array($ty->config) ? $ty->config : [],
                'sort_order' => (int) $ty->sort_order,
                'rule_id' => null,
            ]);
        });
    }

    /**
     * Reverse: do not delete blocks that might have been created manually; no-op.
     */
    public function down(): void
    {
        // Optional: delete only rows that came from thank_you_blocks (e.g. by a marker in config).
        // For safety we do nothing so existing blocks remain.
    }
};
