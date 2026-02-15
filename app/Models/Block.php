<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Block extends Model
{
    protected $fillable = [
        'shop_id',
        'surface',
        'type',
        'name',
        'ai_generated_name',
        'ai_generated_description',
        'ai_generated_php',
        'ai_prompt',
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

    public function blockOffers(): HasMany
    {
        return $this->hasMany(BlockOffer::class)->orderBy('sort_order');
    }

    /** Offers linked to this block via pivot (order by sort_order). */
    public function offers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Offer::class,
            BlockOffer::class,
            'block_id',
            'id',
            'id',
            'offer_id'
        )->orderBy('block_offers.sort_order');
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

    /** Normalized offer IDs: from block_offers pivot first, then fallback to config offer_ids. */
    public function getOfferIds(): array
    {
        $pivot = $this->blockOffers()->pluck('offer_id')->all();
        if ($pivot !== []) {
            return array_values(array_map('intval', $pivot));
        }
        $raw = $this->config['offer_ids'] ?? [];
        if (is_string($raw)) {
            $raw = array_filter(array_map('intval', explode(',', $raw)));
        }

        return array_values(array_filter((array) $raw, fn ($id) => (int) $id > 0));
    }
}
