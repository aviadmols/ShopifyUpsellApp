<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    protected $fillable = [
        'shop_id',
        'surface',
        'type',
        'name',
        'config',
        'sort_order',
        'rule_id',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    /** Surfaces: where the block is shown */
    public static function surfaces(): array
    {
        return ['checkout', 'thank_you', 'post_purchase'];
    }

    /**
     * Block types per surface.
     * checkout: upsell, progress_bar, content_icon_features, content_banner, content_rich_text, content_button, content_product_card
     * thank_you: same content types
     * post_purchase: post_purchase_funnel
     */
    public static function types(): array
    {
        return [
            'upsell',
            'progress_bar',
            'content_icon_features',
            'content_banner',
            'content_rich_text',
            'content_button',
            'content_product_card',
            'post_purchase_funnel',
        ];
    }

    /** Map legacy thank_you_blocks.type to blocks.type */
    public static function thankYouBlockTypeToBlockType(string $thankYouType): string
    {
        return match ($thankYouType) {
            'banner' => 'content_banner',
            'text' => 'content_rich_text',
            'button' => 'content_button',
            'product_card' => 'content_product_card',
            default => 'content_rich_text',
        };
    }

    /** Normalized offer_ids from config (for upsell / post_purchase_funnel). */
    public function getOfferIds(): array
    {
        $raw = $this->config['offer_ids'] ?? [];
        if (is_string($raw)) {
            $raw = array_filter(array_map('intval', explode(',', $raw)));
        }

        return array_values(array_filter((array) $raw, fn ($id) => (int) $id > 0));
    }
}
