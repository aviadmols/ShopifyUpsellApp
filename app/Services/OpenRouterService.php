<?php

namespace App\Services;

use App\Models\AiRequestLog;
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

        $start = microtime(true);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => request()->url(),
            ])->timeout(60)->post(self::BASE_URL, $body);

            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $responseBody = $response->body();

            if (! $response->successful()) {
                $this->logAiRequest('generate', $body, $responseBody, null, 'error', 'HTTP ' . $response->status(), $durationMs);
                Log::warning('OpenRouter API error', [
                    'status' => $response->status(),
                    'body' => $responseBody,
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (! is_string($content)) {
                $this->logAiRequest('generate', $body, $responseBody, null, 'error', 'Missing content in response', $durationMs);
                return null;
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                $this->logAiRequest('generate', $body, $responseBody, null, 'error', 'Invalid JSON in content', $durationMs);
                return null;
            }

            $result = [
                'config' => $decoded['config'] ?? [],
                'rule_conditions' => $decoded['rule_conditions'] ?? [],
                'rule_match_type' => $decoded['rule_match_type'] ?? 'and',
                'name' => (string) ($decoded['name'] ?? 'AI Widget'),
                'description' => (string) ($decoded['description'] ?? ''),
                'php_snippet' => (string) ($decoded['php_snippet'] ?? ''),
            ];
            $this->logAiRequest('generate', $body, $responseBody, $result, 'ok', null, $durationMs);
            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $this->logAiRequest('generate', $body, null, null, 'error', $e->getMessage(), $durationMs);
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

        $start = microtime(true);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(self::BASE_URL, $body);

            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $responseBody = $response->body();

            if (! $response->successful()) {
                $this->logAiRequest('summarize', $body, $responseBody, null, 'error', 'HTTP ' . $response->status(), $durationMs);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            $summary = is_string($content) ? trim($content) : null;
            $this->logAiRequest('summarize', $body, $responseBody, $summary !== null ? ['summary' => $summary] : null, $summary !== null ? 'ok' : 'error', $summary !== null ? null : 'Missing content', $durationMs);
            return $summary;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $this->logAiRequest('summarize', $body, null, null, 'error', $e->getMessage(), $durationMs);
            Log::error('OpenRouter summarize failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Refine widget logic from a user prompt: update rule conditions JSON and PHP snippet (and optionally config text).
     * Used for "Prompt-to-Fix" in Edit Block. Logic runs server-side via rule_conditions; php_snippet is reference only.
     *
     * @param  array<string, mixed>  $widgetSnapshot  Must include: rule_conditions (array), ai_generated_php (string), config (array), surface, type
     * @return array{updated_rule_conditions: array, updated_php_snippet: string, updated_text_fields: array<string, string>, explanation: string, warnings: array<int, string>}|null
     */
    public function refineWidget(string $userPrompt, array $widgetSnapshot): ?array
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return null;
        }

        $schemaService = app(BlockAISchemaService::class);
        $fullSchema = $schemaService->fullSchema();
        $schemaJson = json_encode($fullSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $currentRule = $widgetSnapshot['rule_conditions'] ?? [];
        $currentPhp = (string) ($widgetSnapshot['ai_generated_php'] ?? '');
        $currentConfig = is_array($widgetSnapshot['config'] ?? null) ? $widgetSnapshot['config'] : [];
        $surface = (string) ($widgetSnapshot['surface'] ?? 'checkout');
        $type = (string) ($widgetSnapshot['type'] ?? 'upsell');

        $contextJson = json_encode([
            'current_rule_conditions' => $currentRule,
            'current_php_snippet' => $currentPhp,
            'current_config_text_fields' => array_filter($currentConfig, fn ($v) => is_string($v)),
            'surface' => $surface,
            'type' => $type,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $systemPrompt = $this->buildRefineSystemPrompt($schemaJson);
        $userContent = "Current widget state (JSON):\n" . $contextJson . "\n\nUser request:\n" . $userPrompt;

        $body = [
            'model' => $this->getModel(),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => 4096,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ];

        $start = microtime(true);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => request()->url(),
            ])->timeout(60)->post(self::BASE_URL, $body);

            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $responseBody = $response->body();

            if (! $response->successful()) {
                $this->logAiRequest('refine', $body, $responseBody, null, 'error', 'HTTP ' . $response->status(), $durationMs);
                Log::warning('OpenRouter refine API error', [
                    'status' => $response->status(),
                    'body' => $responseBody,
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (! is_string($content)) {
                $this->logAiRequest('refine', $body, $responseBody, null, 'error', 'Missing content', $durationMs);
                return null;
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                $this->logAiRequest('refine', $body, $responseBody, null, 'error', 'Invalid JSON', $durationMs);
                return null;
            }

            $updatedRule = $decoded['updated_rule_conditions'] ?? $currentRule;
            $updatedRule = is_array($updatedRule) ? $updatedRule : $currentRule;

            $updatedPhp = isset($decoded['updated_php_snippet']) ? (string) $decoded['updated_php_snippet'] : $currentPhp;
            $updatedText = isset($decoded['updated_text_fields']) && is_array($decoded['updated_text_fields'])
                ? $decoded['updated_text_fields']
                : [];
            $explanation = (string) ($decoded['explanation'] ?? '');
            $warnings = isset($decoded['warnings']) && is_array($decoded['warnings'])
                ? array_values(array_map('strval', $decoded['warnings']))
                : [];

            $result = [
                'updated_rule_conditions' => $updatedRule,
                'updated_php_snippet' => $updatedPhp,
                'updated_text_fields' => $updatedText,
                'explanation' => $explanation,
                'warnings' => $warnings,
            ];
            $this->logAiRequest('refine', $body, $responseBody, $result, 'ok', null, $durationMs);
            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $this->logAiRequest('refine', $body, null, null, 'error', $e->getMessage(), $durationMs);
            Log::error('OpenRouter refine failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>|string|null  $parsedOutput
     */
    private function logAiRequest(string $flow, array $requestPayload, ?string $responsePayload, mixed $parsedOutput, string $status, ?string $error, int $durationMs): void
    {
        try {
            $parsedJson = is_array($parsedOutput) || is_string($parsedOutput)
                ? json_encode($parsedOutput, JSON_UNESCAPED_UNICODE)
                : null;
            AiRequestLog::create([
                'flow' => $flow,
                'model' => $requestPayload['model'] ?? null,
                'request_payload' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE),
                'response_payload' => $responsePayload,
                'parsed_output' => $parsedJson,
                'status' => $status,
                'error' => $error,
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI request log write failed', ['message' => $e->getMessage()]);
        }
    }

    private function buildRefineSystemPrompt(string $schemaJson): string
    {
        return <<<PROMPT
You are a widget logic refiner for a Shopify upsell app. You receive the current widget state (rule_conditions JSON, php_snippet, config) and a user request to change or fix the logic.

Important: The actual runtime decision (show/hide widget, which message) runs SERVER-SIDE using only the rule_conditions JSON. The php_snippet is for human reference only and is NOT executed. Support complex server-side logic by outputting valid rule_conditions that the app's RuleEngine evaluates.

Output a single JSON object with these exact keys:
- "updated_rule_conditions": object. Valid structure: {"and": [{"field": "value"}, ...]} or {"or": [...]}. Use only condition keys from the schema (e.g. line_items_has_product_id, line_item_property_equals, line_item_property_exists, line_item_sku_matches, line_item_sku_segment_between for SKU pattern/ranges). For SKU patterns like XXX-XXX-{num}-XXX with numeric segment between 100-300 or 300-400, use line_item_sku_segment_between with value format "pattern,segment_index,min,max" or multiple conditions as needed.
- "updated_php_snippet": string. Multi-line PHP-style pseudocode describing the logic for human review (display only).
- "updated_text_fields": object. Only keys that should change; e.g. {"section_heading": "...", "title": "Hi {property:Dog Name}"}. Use placeholders like {property:Key} for dynamic values from line item properties.
- "explanation": string. Short explanation of what you changed.
- "warnings": array of strings. Any caveats or unsupported parts of the request.

Schema (rule_conditions and config):
{$schemaJson}

Output only valid JSON, no markdown.
PROMPT;
    }

    private function buildSystemPrompt(string $surface, string $blockType, array $fullSchema): string
    {
        $schemaJson = json_encode($fullSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return <<<PROMPT
You are a widget (block) generator for a Shopify upsell app. Given the user's request in natural language, output a single JSON object with these exact keys:

- "config": object. Widget display config for block type "{$blockType}" on surface "{$surface}". Use only keys and value types from the schema below. For upsell, offer_ids can be empty [] if the user did not specify offers; the app will add offers later. For checkout_upgrade_card, config must include headline, description, cta_label, and upgrade_mappings (array of objects: each has "match" (product_id?, variant_id?, sku_regex?, sku_segment?, line_item_property_exists?, line_item_property_equals?), "action_type" (subscription|bundle_swap), "target_variant_id", "quantity", optional "plans" array with id, label, selling_plan_id, target_variant_id per plan).
- "rule_conditions": array. Each item is {"field": "<condition_key>", "value": "<value>"}. Use only condition fields from the schema (e.g. line_items_has_product_id, customer_has_tag, line_item_property_equals for subscription, etc.). For "only when cart has product with SKU X" use line_item_property_equals with a key that represents SKU if the app sends it, or line_items_has_product_id with product id if user gives product; if user only says SKU, use line_item_property_equals with something like "sku","X" or a property key the app might use.
- Dynamic message placeholders are supported server-side from line item properties. You can place placeholders in config text fields (title/body/section_heading/messages), for example:
  - {dog_name} (normalized token)
  - {Dog Name} (same as above; normalized)
  - {property:Dog Name} (explicit property lookup; recommended)
  - {prop:Dog Name} (alias)
  Use placeholders when user asks to inject a product property value into the message.
- Computed placeholders ("shortcodes") are supported via config.runtime_variables (object). These variables are computed SERVER-SIDE from the checkout context and can be referenced in text fields as {var_name}. Supported runtime variable definition types:
  - plural_message_from_property: {type, property, singular, plural, empty?, separator?, case_insensitive_unique?, max?}
  - unique_line_item_property_values: {type, property, separator?, case_insensitive_unique?, max?} (returns joined string)
  Example:
    runtime_variables: {
      "dog_names_message": {
        "type": "plural_message_from_property",
        "property": "Dog Name",
        "singular": "Your dog ({value}) deserves the best",
        "plural": "Your dogs ({values}) deserve the best",
        "empty": ""
      }
    }
- "rule_match_type": "and" or "or". How to combine conditions.
- "name": string. Short widget name (e.g. "Subscription message").
- "description": string. One sentence what this widget does and when it shows.
- "php_snippet": string. Required detailed PHP-style snippet for human review (display only, not executed). Return a multi-line snippet that includes: context extraction, condition checks, and a final boolean/result decision. Do NOT return a one-line comment. For checkout_upgrade_card, the snippet describes matching cart lines to upgrade_mappings and building remove/add actions; it is descriptive only, not executed.

Schema (endpoints, block_types with config_schema, rule_conditions):
{$schemaJson}

Output only valid JSON, no markdown.
PROMPT;
    }
}
