<?php

namespace App\Models;

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
}
