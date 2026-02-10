<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rule extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
