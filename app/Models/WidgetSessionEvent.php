<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetSessionEvent extends Model
{
    protected $table = 'widget_session_events';

    protected $fillable = [
        'shop_id',
        'block_id',
        'session_key',
        'event_type',
        'context_snapshot',
        'rule_passed',
        'widget_shown',
        'click_target',
    ];

    public function getShopDomainAttribute(): string
    {
        $this->loadMissing('shop');
        return $this->shop?->shop_domain ?? (string) $this->shop_id;
    }

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'rule_passed' => 'boolean',
            'widget_shown' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Scope: only events where context_snapshot.checkout_attributes._zyxel_user_id equals the given value.
     */
    public function scopeWhereZyxelUserId(Builder $query, string $userId): Builder
    {
        $userId = trim($userId);
        if ($userId === '') {
            return $query;
        }
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'mysql') {
            return $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(context_snapshot, '$.checkout_attributes._zyxel_user_id')) = ?",
                [$userId]
            );
        }
        if ($driver === 'sqlite') {
            return $query->whereRaw(
                "json_extract(context_snapshot, '$.checkout_attributes._zyxel_user_id') = ?",
                [$userId]
            );
        }
        return $query->where('context_snapshot', 'like', '%"_zyxel_user_id":"' . addslashes($userId) . '"%');
    }

    /**
     * Scope: only events where context_snapshot.checkout_attributes.igTestGroups equals the given value.
     */
    public function scopeWhereIgTestGroups(Builder $query, string $value): Builder
    {
        $value = trim($value);
        if ($value === '') {
            return $query;
        }
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'mysql') {
            return $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(context_snapshot, '$.checkout_attributes.igTestGroups')) = ?",
                [$value]
            );
        }
        if ($driver === 'sqlite') {
            return $query->whereRaw(
                "json_extract(context_snapshot, '$.checkout_attributes.igTestGroups') = ?",
                [$value]
            );
        }
        return $query->where('context_snapshot', 'like', '%"igTestGroups":"' . addslashes($value) . '"%');
    }
}
