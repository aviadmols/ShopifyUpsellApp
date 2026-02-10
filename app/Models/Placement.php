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
}
