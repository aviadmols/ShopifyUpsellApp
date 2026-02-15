<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    private const BASE_URL = 'https://openrouter.ai/api/v1/chat/completions';

    public function isConfigured(): bool
    {
        return (bool) $this->getApiKey();
    }

    public function getApiKey(): ?string
    {
        $key = Setting::get('openrouter_api_key');
        return is_string($key) && $key !== '' ? $key : null;
    }

    public function getModel(): string
    {
        $model = Setting::get('openrouter_model');
        return is_string($model) && $model !== '' ? $model : 'openai/gpt-4o-mini';
    }

    /**
     * Generate widget config + metadata from user prompt and block schema.
     *
     * @param  array<string, mixed>  $fullSchema  From BlockAISchemaService::fullSchema()
     * @return array{config: array, rule_conditions: array, name: string, description: string, php_snippet: string}|null
     */
    public function generateWidgetFromPrompt(string $userPrompt, string $surface, string $blockType, array $fullSchema): ?array
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return null;
        }

        $systemPrompt = $this->buildSystemPrompt($surface, $blockType, $fullSchema);
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $body = [
            'model' => $this->getModel(),
            'messages' => $messages,
            'max_tokens' => 4096,
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => request()->url(),
            ])->timeout(60)->post(self::BASE_URL, $body);

            if (! $response->successful()) {
                Log::warning('OpenRouter API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (! is_string($content)) {
                return null;
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                return null;
            }

            return [
                'config' => $decoded['config'] ?? [],
                'rule_conditions' => $decoded['rule_conditions'] ?? [],
                'rule_match_type' => $decoded['rule_match_type'] ?? 'and',
                'name' => (string) ($decoded['name'] ?? 'AI Widget'),
                'description' => (string) ($decoded['description'] ?? ''),
                'php_snippet' => (string) ($decoded['php_snippet'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::error('OpenRouter request failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Ask AI to summarize test result (valid/invalid + what it does).
     *
     * @param  array<string, mixed>  $testContext  config, rules, validation result, log lines
     */
    public function summarizeTestResult(array $testContext): ?string
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return null;
        }

        $userContent = "Summarize this widget test result in 2-4 short sentences. Say if it's valid and what the widget does.\n\n" . json_encode($testContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $body = [
            'model' => $this->getModel(),
            'messages' => [
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => 500,
            'temperature' => 0.2,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(self::BASE_URL, $body);

            if (! $response->successful()) {
                return null;
            }

            $content = $response->json('choices.0.message.content');
            return is_string($content) ? trim($content) : null;
        } catch (\Throwable $e) {
            Log::error('OpenRouter summarize failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function buildSystemPrompt(string $surface, string $blockType, array $fullSchema): string
    {
        $schemaJson = json_encode($fullSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return <<<PROMPT
You are a widget (block) generator for a Shopify upsell app. Given the user's request in natural language, output a single JSON object with these exact keys:

- "config": object. Widget display config for block type "{$blockType}" on surface "{$surface}". Use only keys and value types from the schema below. For upsell, offer_ids can be empty [] if the user did not specify offers; the app will add offers later.
- "rule_conditions": array. Each item is {"field": "<condition_key>", "value": "<value>"}. Use only condition fields from the schema (e.g. line_items_has_product_id, customer_has_tag, line_item_property_equals for subscription, etc.). For "only when cart has product with SKU X" use line_item_property_equals with a key that represents SKU if the app sends it, or line_items_has_product_id with product id if user gives product; if user only says SKU, use line_item_property_equals with something like "sku","X" or a property key the app might use.
- "rule_match_type": "and" or "or". How to combine conditions.
- "name": string. Short widget name (e.g. "Subscription message").
- "description": string. One sentence what this widget does and when it shows.
- "php_snippet": string. Required detailed PHP-style snippet for human review (display only, not executed). Return a multi-line snippet that includes: context extraction, condition checks, and a final boolean/result decision. Do NOT return a one-line comment.

Schema (endpoints, block_types with config_schema, rule_conditions):
{$schemaJson}

Output only valid JSON, no markdown.
PROMPT;
    }
}
