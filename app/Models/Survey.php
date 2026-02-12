<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'enabled',
        'surfaces',
        'conditions',
        'rule_match_type',
        'rule_conditions',
        'reward_type',
        'reward_code',
        'reward_message',
        'questions',
        'ui',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
            'surfaces' => 'array',
            'conditions' => 'array',
            'rule_conditions' => 'array',
            'questions' => 'array',
            'ui' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}

