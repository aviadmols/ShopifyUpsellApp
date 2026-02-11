<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Placement extends Model
{
    protected $fillable = [
        'shop_id',
        'placement_type',
        'config',
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

    /**
     * Placement types: checkout, post_purchase, thank_you.
     */
    public static function placementTypes(): array
    {
        return ['checkout', 'post_purchase', 'thank_you'];
    }

    /**
     * Normalized offer_ids from config (supports array or comma-separated string from Filament KeyValue).
     *
     * @return array<int, int>
     */
    public function getOfferIds(): array
    {
        return self::normalizeIntList($this->config['offer_ids'] ?? []);
    }

    /**
     * Normalized block_ids from config (supports array or comma-separated string).
     *
     * @return array<int, int>
     */
    public function getBlockIds(): array
    {
        return self::normalizeIntList($this->config['block_ids'] ?? []);
    }

    /**
     * @param  array<int, int>|string  $value
     * @return array<int, int>
     */
    public static function normalizeIntList(array|string $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('intval', explode(',', $value)));
        }

        return array_values(array_filter((array) $value, fn ($id) => (int) $id > 0));
    }
}
