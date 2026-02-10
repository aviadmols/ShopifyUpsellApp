<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThankYouBlock extends Model
{
    protected $fillable = [
        'shop_id',
        'type',
        'config',
        'sort_order',
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
     * Block types for thank you page.
     */
    public static function blockTypes(): array
    {
        return ['banner', 'text', 'button', 'product_card'];
    }
}
