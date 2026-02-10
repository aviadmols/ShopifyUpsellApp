<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $fillable = [
        'shop_id',
        'rule_id',
        'title',
        'description',
        'product_variant_id',
        'discount_type',
        'discount_value',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
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

    public function postPurchaseLogs(): HasMany
    {
        return $this->hasMany(PostPurchaseLog::class);
    }

    /**
     * Discount types supported.
     */
    public static function discountTypes(): array
    {
        return ['none', 'percentage', 'fixed'];
    }
}
