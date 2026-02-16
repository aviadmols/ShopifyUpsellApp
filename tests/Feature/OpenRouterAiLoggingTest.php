<?php

namespace Tests\Feature;

use App\Models\AiRequestLog;
use App\Models\Setting;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterAiLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('openrouter_api_key', 'sk-test-key');
        Setting::set('openrouter_model', 'openai/gpt-4o-mini');
    }

    public function test_generate_widget_logs_request(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'config' => ['title' => 'Test'],
                                'rule_conditions' => [],
                                'rule_match_type' => 'and',
                                'name' => 'Test Widget',
                                'description' => 'Test',
                                'php_snippet' => '// test',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(OpenRouterService::class);
        $before = AiRequestLog::count();
        $result = $service->generateWidgetFromPrompt('Show a banner', 'checkout', 'upsell', [
            'block_types' => [],
            'rule_conditions' => [],
        ]);
        $after = AiRequestLog::count();

        $this->assertNotNull($result);
        $this->assertSame($before + 1, $after);
        $log = AiRequestLog::where('flow', 'generate')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('ok', $log->status);
        $this->assertSame('openai/gpt-4o-mini', $log->model);
        $this->assertNotNull($log->request_payload);
        $this->assertNotNull($log->response_payload);
        $this->assertNotNull($log->parsed_output);
        $this->assertGreaterThan(0, $log->duration_ms);
    }

    public function test_refine_widget_logs_request(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'updated_rule_conditions' => ['and' => []],
                                'updated_php_snippet' => '// refined',
                                'updated_text_fields' => [],
                                'explanation' => 'Done',
                                'warnings' => [],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(OpenRouterService::class);
        $before = AiRequestLog::count();
        $result = $service->refineWidget('Make it simpler', [
            'rule_conditions' => [],
            'ai_generated_php' => '// old',
            'config' => [],
            'surface' => 'checkout',
            'type' => 'upsell',
        ]);
        $after = AiRequestLog::count();

        $this->assertNotNull($result);
        $this->assertSame($before + 1, $after);
        $log = AiRequestLog::where('flow', 'refine')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('ok', $log->status);
    }

    public function test_failed_request_logs_with_error_status(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $service = app(OpenRouterService::class);
        $before = AiRequestLog::count();
        $result = $service->generateWidgetFromPrompt('Test', 'checkout', 'upsell', [
            'block_types' => [],
            'rule_conditions' => [],
        ]);
        $after = AiRequestLog::count();

        $this->assertNull($result);
        $this->assertSame($before + 1, $after);
        $log = AiRequestLog::where('flow', 'generate')->latest()->first();
        $this->assertSame('error', $log->status);
        $this->assertStringContainsString('401', $log->error ?? '');
    }
}
