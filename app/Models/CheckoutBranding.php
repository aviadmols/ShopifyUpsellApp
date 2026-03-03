<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutBranding extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'checkout_profile_id',
        'design_system',
        'customizations',
        'is_enabled',
        'apply_only_with_checkout_widget',
    ];

    protected function casts(): array
    {
        return [
            'design_system' => 'array',
            'customizations' => 'array',
            'is_enabled' => 'boolean',
            'apply_only_with_checkout_widget' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
