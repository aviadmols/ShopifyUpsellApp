<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = [
        'shop_domain',
        'access_token',
        'scope',
        'installed_at',
        'uninstalled_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function thankYouBlocks(): HasMany
    {
        return $this->hasMany(ThankYouBlock::class);
    }

    public function postPurchaseLogs(): HasMany
    {
        return $this->hasMany(PostPurchaseLog::class);
    }

    /**
     * Check if shop is currently installed (not uninstalled).
     */
    public function isInstalled(): bool
    {
        return $this->uninstalled_at === null;
    }
}
