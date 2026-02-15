<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $item = Cache::remember('setting:'.$key, 300, fn () => self::query()->where('key', $key)->first());

        return $item?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrInsert(
            ['key' => $key],
            ['value' => is_string($value) ? $value : json_encode($value), 'updated_at' => now()]
        );
        Cache::forget('setting:'.$key);
    }
}
