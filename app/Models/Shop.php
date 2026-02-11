<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = [
        'shop_domain',
        'alternate_domains',
        'access_token',
        'scope',
        'installed_at',
        'uninstalled_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'alternate_domains' => 'array',
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

    /**
     * Find shop by domain, including common alternates and configured alternate_domains.
     */
    public static function findByDomainOrAlternates(string $domain): ?self
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }

        $shop = self::where('shop_domain', $domain)->whereNull('uninstalled_at')->first();
        if ($shop) {
            return $shop;
        }

        // Try built-in: xxx-usa.myshopify.com <-> xxx.myshopify.com
        if (str_ends_with($domain, '-usa.myshopify.com')) {
            $alt = str_replace('-usa.myshopify.com', '.myshopify.com', $domain);
            $shop = self::where('shop_domain', $alt)->whereNull('uninstalled_at')->first();
            if ($shop) {
                return $shop;
            }
        }
        if (str_ends_with($domain, '.myshopify.com') && ! str_contains(substr($domain, 0, -16), '-usa')) {
            $alt = preg_replace('/(\.myshopify\.com)$/', '-usa$1', $domain);
            $shop = self::where('shop_domain', $alt)->whereNull('uninstalled_at')->first();
            if ($shop) {
                return $shop;
            }
        }

        // Find by alternate_domains (configured in Shops > Edit)
        $shops = self::whereNull('uninstalled_at')->get();
        foreach ($shops as $s) {
            $alts = $s->alternate_domains ?? [];
            if (in_array($domain, array_map('strtolower', array_map('trim', $alts)), true)) {
                return $s;
            }
        }

        return null;
    }
}
