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

            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
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

            [$decoded, $decodeError] = $this->decodeAssistantJson($content);
            if (! is_array($decoded)) {
                $this->logAiRequest('generate', $body, $responseBody, null, 'error', $decodeError ?? 'Invalid JSON in content', $durationMs);
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
            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
            $this->logAiRequest('generate', $body, null, null, 'error', $e->getMessage(), $durationMs);
            Log::error('OpenRouter request failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Decode strict JSON object from assistant content, with repair fallbacks.
     *
     * @return array{0: array<string, mixed>|null, 1: string|null} [decoded, error]
     */
    private function decodeAssistantJson(string $content): array
    {
        $original = $content;
        $content = trim($content);

        // Strip common markdown code fences.
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            $content = trim($content);
        }

        // Remove UTF-8 BOM if present.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $candidates = [];
        $candidates[] = $content;
        $extracted = $this->extractOuterJsonObject($content);
        if ($extracted !== null) {
            $candidates[] = $extracted;
        }
        $candidates[] = $this->escapeControlCharsInsideJsonStrings($content);
        if ($extracted !== null) {
            $candidates[] = $this->escapeControlCharsInsideJsonStrings($extracted);
        }

        $lastError = null;
        foreach (array_values(array_unique($candidates)) as $candidate) {
            try {
                /** @var mixed $decoded */
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return [$decoded, null];
                }
                $lastError = 'Decoded JSON is not an object';
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        // Include a short preview to help debugging without dumping full content.
        $preview = substr($original, 0, 180);
        return [null, 'Invalid JSON in content: ' . ($lastError ?? 'unknown') . ' | preview=' . $preview];
    }

    private function extractOuterJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        return substr($text, $start, $end - $start + 1);
    }

    /**
     * Some providers occasionally return invalid JSON due to raw control characters inside string values.
     * This function escapes \\r, \\n, \\t when they appear INSIDE JSON strings.
     */
    private function escapeControlCharsInsideJsonStrings(string $jsonLike): string
    {
        $out = '';
        $inString = false;
        $escape = false;
        $len = strlen($jsonLike);

        for ($i = 0; $i < $len; $i++) {
            $ch = $jsonLike[$i];

            if ($inString) {
                if ($escape) {
                    $out .= $ch;
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $out .= $ch;
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $out .= $ch;
                    $inString = false;
                    continue;
                }
                if ($ch === "\n") {
                    $out .= '\\n';
                    continue;
                }
                if ($ch === "\r") {
                    $out .= '\\r';
                    continue;
                }
                if ($ch === "\t") {
                    $out .= '\\t';
                    continue;
                }
                // Other ASCII control chars.
                $ord = ord($ch);
                if ($ord < 0x20) {
                    // Replace with a space to keep JSON valid.
                    $out .= ' ';
                    continue;
                }
                $out .= $ch;
                continue;
            }

            if ($ch === '"') {
                $out .= $ch;
                $inString = true;
                continue;
            }
            $out .= $ch;
        }

        return $out;
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

            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
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
            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
            $this->logAiRequest('summarize', $body, null, null, 'error', $e->getMessage(), $durationMs);
            Log::error('OpenRouter summarize failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Summarize widget session events (timeline + what happened) in English.
     *
     * @param  array<string, mixed>  $sessionContext  session_key, stats, events (sanitized)
     */
    public function summarizeWidgetSession(array $sessionContext): ?string
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return null;
        }

        $system = <<<PROMPT
You are an expert Shopify Checkout extension debugging assistant.

Given structured session logs for a single session_key, produce a clear ENGLISH summary for a developer. Format the entire response as clear bullet points so it is easy to scan.

Requirements:
- Output in English only.
- Start with 1–2 short executive summary bullets (what happened in this session).
- Then use only bullet points: each point must be one focused sentence on its own line. Use a hyphen and space "- " at the start of each bullet line.
- Include: whether the widget was shown (widget_shown), whether rules passed (rule_passed), and any clicks (click_target + meta). Use timestamps where relevant.
- If there are user identifiers in checkout_attributes (e.g. _zyxel_user_id, _axon_client_id, igId), put them in a dedicated bullet.
- Call out anomalies in separate bullets (e.g. multiple shops, missing view events, clicks without view, rule failed, widget not shown).
- Keep each bullet concise and high-signal. No long paragraphs.
PROMPT;

        $userContent = "Session logs (JSON):\n" . json_encode($sessionContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $body = [
            'model' => $this->getModel(),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => 1200,
            'temperature' => 0.2,
        ];

        $start = microtime(true);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post(self::BASE_URL, $body);

            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
            $responseBody = $response->body();

            if (! $response->successful()) {
                $this->logAiRequest('session_summary', $body, $responseBody, null, 'error', 'HTTP ' . $response->status(), $durationMs);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            $summary = is_string($content) ? trim($content) : null;
            $this->logAiRequest(
                'session_summary',
                $body,
                $responseBody,
                $summary !== null ? ['summary' => $summary] : null,
                $summary !== null ? 'ok' : 'error',
                $summary !== null ? null : 'Missing content',
                $durationMs
            );
            return $summary;
        } catch (\Throwable $e) {
            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
            $this->logAiRequest('session_summary', $body, null, null, 'error', $e->getMessage(), $durationMs);
            Log::error('OpenRouter session summary failed', ['message' => $e->getMessage()]);
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

            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
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
            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
            $this->logAiRequest('refine', $body, null, null, 'error', $e->getMessage(), $durationMs);
            Log::error('OpenRouter refine failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Diagnose why a widget was or was not visible for a single session event.
     * Returns a short diagnosis in English, or null on failure.
     *
     * @param  array<string, mixed>  $payload  block metadata, rule, context_snapshot, computed diagnostics, stored widget_shown/rule_passed
     */
    public function diagnoseWidgetSessionEventVisibility(array $payload): ?string
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return null;
        }

        $system = <<<PROMPT
You are an expert at debugging Shopify Checkout extension visibility. You receive a single widget session event payload: block metadata, rule conditions, context snapshot (cart, customer, country, UTMs, checkout_attributes, etc.), and computed diagnostics (whether the rule was expected to pass, upgrade card expected state, item counts). You also see the stored values for widget_shown and rule_passed from the actual session.

Important: computed_diagnostics use the block's **current** configuration. If the block was edited after the event (e.g. required checkout attributes like igTestGroups, cart-wide mappings), a discrepancy between widget_shown and upgrade_card_enabled_expected can be because the API at event time used a different config. If the payload includes diagnosis_note or discrepancy_possible_cause, mention that as a likely explanation when there is a mismatch.

Your task: produce a short, clear diagnosis IN ENGLISH ONLY. Format your response as clear bullet points so a developer can scan quickly.

Requirements:
- Start with one short conclusion line (what happened: widget shown or not, and why).
- Then list 3–6 bullet points. Each bullet must be one focused sentence on its own line.
- Use a hyphen and space "- " at the start of each bullet line.
- Cover: whether the rule passed (expected vs stored), upgrade card state if applicable (enabled, items count), any contradiction (e.g. widget_shown=true but upgrade_card_enabled_expected=false), and the most likely cause. When widget_shown differs from upgrade_card_enabled_expected, consider first: config changed after the event (required attributes, cart-wide mappings); then rule or cart line matching.
- Keep each point concise and actionable.
PROMPT;

        $userContent = "Session event payload (JSON):\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $body = [
            'model' => $this->getModel(),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => 800,
            'temperature' => 0.2,
        ];

        $start = microtime(true);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post(self::BASE_URL, $body);

            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
            $responseBody = $response->body();

            if (! $response->successful()) {
                $this->logAiRequest('widget_visibility_diagnosis', $body, $responseBody, null, 'error', 'HTTP ' . $response->status(), $durationMs);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            $diagnosis = is_string($content) ? trim($content) : null;
            $this->logAiRequest(
                'widget_visibility_diagnosis',
                $body,
                $responseBody,
                $diagnosis !== null ? ['diagnosis' => $diagnosis] : null,
                $diagnosis !== null ? 'ok' : 'error',
                $diagnosis !== null ? null : 'Missing content',
                $durationMs
            );
            return $diagnosis;
        } catch (\Throwable $e) {
            $durationMs = max(1, (int) round((microtime(true) - $start) * 1000));
            $this->logAiRequest('widget_visibility_diagnosis', $body, null, null, 'error', $e->getMessage(), $durationMs);
            Log::error('OpenRouter widget visibility diagnosis failed', ['message' => $e->getMessage()]);
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
- "updated_rule_conditions": object. Valid structure: {"and": [{"field": "value"}, ...]} or {"or": [...]}. Use only condition keys from the schema. For subscription/one-time/selling plan: use line_items_has_line_without_selling_plan (cart has at least one line with selling_plan_id null — one-time) or line_items_has_line_with_selling_plan (cart has at least one line with selling_plan_id set — subscription). Do NOT use line_item_property_equals with "subscription,no" or similar; the app checks selling_plan_id on the line item, not a property. For SKU use line_item_sku_matches or line_item_sku_segment_between; for other conditions use schema keys.
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
- "rule_conditions": array. Each item is {"field": "<condition_key>", "value": "<value>"}. Use only condition fields from the schema. For "only when cart has one-time items" or "no subscription" or "no selling plan": use field line_items_has_line_without_selling_plan (value can be true or 1). For "only when cart has subscription items" or "has selling plan": use line_items_has_line_with_selling_plan. Do NOT use line_item_property_equals with "subscription,no" — the app checks selling_plan_id (null vs set) on the line item. For SKU use line_items_has_product_id or line_item_sku_matches; for custom line properties use line_item_property_equals with that property key.
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
