<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiRequestLog extends Model
{
    protected $table = 'ai_request_logs';

    protected $fillable = [
        'flow',
        'model',
        'request_payload',
        'response_payload',
        'parsed_output',
        'status',
        'error',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
        ];
    }

    public static function flows(): array
    {
        return ['generate', 'summarize', 'refine'];
    }
}
