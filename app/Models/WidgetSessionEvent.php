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
