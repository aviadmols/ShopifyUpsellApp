<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BlockResource\Pages\CreateBlock;
use App\Filament\Resources\BlockResource;
use App\Filament\Widgets\WidgetRegistry;
use App\Models\Block;
use App\Models\Rule;
use App\Models\Shop;
use App\Services\BlockAISchemaService;
use App\Services\CartLineUpgradeMatcher;
use App\Services\OfferBuilderService;
use App\Services\OpenRouterService;
use App\Services\RuleEngine;
use App\Services\ShopifyGraphQLService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class CreateBlockWithAI extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = null;
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.create-block-with-ai';
    protected static ?string $title = 'New Widget With AI';

    public int $step = 1;
    public ?int $shop_id = null;
    public ?string $surface = null;
    public ?string $type = null;
    public string $prompt = '';
    public ?array $generated = null;
    public string $testLog = '';
    public ?string $testSummary = null;

    /** @var string */
    public string $mentionQuery = '';

    /** @var array<int, array{id: string, label: string}> */
    public array $mentionResults = [];

    public bool $mentionOpen = false;

    public ?string $mentionSelectedVariantId = null;

    /** @var array<int, array{id: string, name: string}> */
    public array $mentionSellingPlans = [];

    public function mount(): void
    {
        $this->prompt = '';
        $this->generated = null;
        $this->testLog = '';
        $this->testSummary = null;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('shop_id')
                    ->label('Shop')
                    ->options(fn (): array => Shop::whereNull('uninstalled_at')->pluck('shop_domain', 'id')->all())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('surface')
                    ->label('Surface')
                    ->options(array_combine(WidgetRegistry::surfaces(), WidgetRegistry::surfaces()))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, \Filament\Forms\Set $set) => $set('type', null)),
                Select::make('type')
                    ->label('Block type')
                    ->options(fn (\Filament\Forms\Get $get): array => WidgetRegistry::typeOptionsForSurface($get('surface')))
                    ->required(fn (\Filament\Forms\Get $get): bool => ! empty($get('surface')))
                    ->live(),
                Textarea::make('prompt')
                    ->label('What do you want this widget to do?')
                    ->placeholder('e.g. Show message for subscription save only for customers without subscription who have in cart product with SKU X.')
                    ->rows(5)
                    ->required()
                    ->live()
                    ->columnSpanFull(),
            ])
            ->statePath('')
            ->columns(2);
    }

    public function updatedPrompt(string $value): void
    {
        $this->refreshMentionState($value);
    }

    public function updatedType(): void
    {
        $this->resetMention();
    }

    public function updatedShopId(): void
    {
        $this->resetMention();
    }

    private function resetMention(): void
    {
        $this->mentionQuery = '';
        $this->mentionResults = [];
        $this->mentionOpen = false;
        $this->mentionSelectedVariantId = null;
        $this->mentionSellingPlans = [];
    }

    private function refreshMentionState(string $prompt): void
    {
        // Only enable mention search for Upgrade Card widgets.
        if (($this->type ?? '') !== 'checkout_upgrade_card') {
            $this->resetMention();
            return;
        }
        if (! $this->shop_id) {
            $this->resetMention();
            return;
        }

        // Detect last "@query" token at end of prompt.
        if (! preg_match('/(?:^|\\s)@([^\\s]{1,40})$/u', $prompt, $m)) {
            $this->mentionOpen = false;
            $this->mentionQuery = '';
            $this->mentionResults = [];
            return;
        }

        $query = trim((string) ($m[1] ?? ''));
        $this->mentionQuery = $query;
        $this->mentionSelectedVariantId = null;
        $this->mentionSellingPlans = [];

        if ($query === '' || mb_strlen($query) < 2) {
            $this->mentionOpen = true;
            $this->mentionResults = [];
            return;
        }

        $shopId = (int) $this->shop_id;
        $shop = Shop::whereNull('uninstalled_at')->find($shopId);
        if (! $shop) {
            $this->mentionOpen = true;
            $this->mentionResults = [];
            return;
        }

        try {
            $items = app(ShopifyGraphQLService::class)->searchProductVariants($shop, $query, 10);
        } catch (\Throwable) {
            $items = [];
        }

        $results = [];
        foreach ($items as $it) {
            $id = (string) ($it['id'] ?? '');
            $label = (string) ($it['label'] ?? '');
            if ($id !== '' && $label !== '') {
                $results[] = ['id' => $id, 'label' => $label];
            }
        }

        $this->mentionOpen = true;
        $this->mentionResults = $results;
    }

    public function selectMentionVariant(string $variantId): void
    {
        $variantId = trim($variantId);
        if ($variantId === '' || ! $this->shop_id) {
            return;
        }

        // Replace the last "@query" token with the selected variant ID.
        $this->prompt = preg_replace('/(?:^|\\s)@[^\\s]{1,40}$/u', ' ' . $variantId, $this->prompt) ?? $this->prompt;
        $this->mentionOpen = false;
        $this->mentionSelectedVariantId = $variantId;
        $this->mentionResults = [];
        $this->mentionQuery = '';

        $shop = Shop::whereNull('uninstalled_at')->find((int) $this->shop_id);
        if (! $shop) {
            return;
        }

        $variantGid = CartLineUpgradeMatcher::variantToGid($variantId);
        if ($variantGid === '') {
            return;
        }

        try {
            $this->mentionSellingPlans = app(ShopifyGraphQLService::class)->getSellingPlansForVariant($shop, $variantGid);
        } catch (\Throwable) {
            $this->mentionSellingPlans = [];
        }
    }

    public function insertSellingPlanId(string $sellingPlanId): void
    {
        $sellingPlanId = trim($sellingPlanId);
        if ($sellingPlanId === '') {
            return;
        }
        $this->prompt = rtrim($this->prompt) . "\n" . $sellingPlanId . "\n";
    }

    public function generate(): void
    {
        $this->validate([
            'shop_id' => 'required|exists:shops,id',
            'surface' => 'required|string',
            'type' => 'required|string',
            'prompt' => 'required|string',
        ]);

        $openRouter = app(OpenRouterService::class);
        if (! $openRouter->isConfigured()) {
            Notification::make()->title('OpenRouter not configured')->body('Set your API key in Developer -> AI (OpenRouter).')->danger()->send();
            return;
        }

        $schemaService = app(BlockAISchemaService::class);
        $fullSchema = $schemaService->fullSchema();
        $result = $openRouter->generateWidgetFromPrompt($this->prompt, $this->surface, $this->type, $fullSchema);

        if ($result === null) {
            Notification::make()->title('Generation failed')->body('AI could not generate widget. Check OpenRouter key and model.')->danger()->send();
            return;
        }

        $this->generated = $result;
        $this->step = 2;
        $this->testLog = '';
        $this->testSummary = null;
        Notification::make()->title('Widget generated')->success()->send();
    }

    public function runTest(): void
    {
        if ($this->generated === null) {
            return;
        }

        $log = [];
        $config = $this->generated['config'] ?? [];
        $ruleConditions = $this->generated['rule_conditions'] ?? [];
        $matchType = $this->generated['rule_match_type'] ?? 'and';

        $log[] = '--- Config ---';
        $log[] = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $log[] = '';

        if ($ruleConditions !== []) {
            $builder = app(OfferBuilderService::class);
            $rows = [];
            foreach ($ruleConditions as $c) {
                $rows[] = ['field' => $c['field'] ?? '', 'value' => $c['value'] ?? ''];
            }
            $conditions = $builder->buildConditions($rows, $matchType);
            $log[] = '--- Rule conditions ---';
            $log[] = json_encode($conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $log[] = '';
            $engine = app(RuleEngine::class);
            $dummyContext = [
                'subtotal' => 150,
                'line_items' => [['product_id' => 123, 'variant_id' => 456, 'properties' => []]],
                'customer' => ['tags' => []],
                'shipping_country' => 'IL',
                'utms' => [],
                'url_params' => [],
            ];
            $passed = $engine->evaluate($conditions, $dummyContext);
            $log[] = '--- Test context (dummy) ---';
            $log[] = json_encode($dummyContext, JSON_PRETTY_PRINT);
            $log[] = 'Rule result: ' . ($passed ? 'PASS' : 'FAIL');
        } else {
            $log[] = 'No rule conditions.';
        }

        $this->testLog = implode("\n", $log);
        $this->testSummary = app(OpenRouterService::class)->summarizeTestResult([
            'config' => $config,
            'rule_conditions' => $ruleConditions,
            'rule_match_type' => $matchType,
            'log' => $this->testLog,
        ]) ?? 'Test completed. See log above.';
        Notification::make()->title('Test done')->success()->send();
    }

    public function saveWidget(): void
    {
        if ($this->generated === null || ! $this->shop_id) {
            Notification::make()->title('Nothing to save')->warning()->send();
            return;
        }

        $config = $this->generated['config'] ?? [];
        $ruleConditions = $this->generated['rule_conditions'] ?? [];
        $matchType = $this->generated['rule_match_type'] ?? 'and';
        $name = $this->generated['name'] ?? 'AI Widget';
        $description = $this->generated['description'] ?? '';
        $phpSnippet = $this->generated['php_snippet'] ?? '';

        $ruleId = null;
        if ($ruleConditions !== []) {
            $builder = app(OfferBuilderService::class);
            $rows = [];
            foreach ($ruleConditions as $c) {
                $rows[] = ['field' => $c['field'] ?? '', 'value' => $c['value'] ?? ''];
            }
            $conditions = $builder->buildConditions($rows, $matchType);
            $rule = Rule::create([
                'shop_id' => $this->shop_id,
                'name' => 'AI rule ' . substr(md5($name), 0, 8),
                'conditions' => $conditions,
            ]);
            $ruleId = $rule->id;
        }

        $block = Block::create([
            'shop_id' => $this->shop_id,
            'surface' => $this->surface,
            'type' => $this->type,
            'name' => $name,
            'config' => $this->normalizeConfig($config),
            'rule_id' => $ruleId,
            'sort_order' => 0,
            'ai_generated_name' => $name,
            'ai_generated_description' => $description,
            'ai_generated_php' => $phpSnippet,
            'ai_prompt' => $this->prompt,
        ]);

        Notification::make()->title('Widget created')->body('ID: ' . $block->id)->success()->send();
        $this->redirect(BlockResource::getUrl('edit', ['record' => $block]));
    }

    private function normalizeConfig(array $config): array
    {
        $surface = $this->surface ?? '';
        $type = $this->type ?? '';
        // The AI returns "config" in the API payload shape (e.g. headline/upgrade_mappings for upgrade card),
        // but CreateBlock::buildBlockConfig expects the Filament form state keys (upgrade_card_headline, upgrade_mappings_items, etc).
        // Map shapes here so AI-created widgets persist correctly.
        if ($surface === 'checkout' && $type === 'checkout_upgrade_card') {
            $ui = is_array($config['ui'] ?? null) ? $config['ui'] : [];

            $data = [
                'surface' => $surface,
                'type' => $type,
                'widget_offers' => [],
                'offer_ids_csv' => '',

                'upgrade_card_headline' => (string) ($config['headline'] ?? $config['upgrade_card_headline'] ?? ''),
                'upgrade_card_description' => (string) ($config['description'] ?? $config['upgrade_card_description'] ?? ''),
                'upgrade_card_cta_label' => (string) ($config['cta_label'] ?? $config['upgrade_card_cta_label'] ?? 'Upgrade'),
                'upgrade_card_cart_subtotal_min' => $config['cart_subtotal_min'] ?? null,
                'upgrade_card_cart_items_count_min' => $config['cart_items_count_min'] ?? null,

                'upgrade_card_display_mode' => (string) ($ui['display_mode'] ?? 'text'),
                'upgrade_card_image_url' => (string) ($ui['image_url'] ?? ''),
                'upgrade_card_title_size' => (string) ($ui['title_size'] ?? 'medium'),
                'upgrade_card_button_kind' => (string) ($ui['button_kind'] ?? 'secondary'),
                'upgrade_card_spacing' => (string) ($ui['spacing'] ?? 'tight'),
                'upgrade_card_show_border' => (bool) ($ui['show_border'] ?? true),

                'upgrade_card_plans' => is_array($config['plans'] ?? null) ? $config['plans'] : [],
                'upgrade_mappings_items' => $this->upgradeMappingsToFormItems(is_array($config['upgrade_mappings'] ?? null) ? $config['upgrade_mappings'] : []),
                'extra_config' => [],
            ];

            return CreateBlock::buildBlockConfig($data);
        }

        $data = array_merge([
            'surface' => $surface,
            'type' => $type,
            'widget_offers' => [],
            'offer_ids_csv' => '',
        ], $config);

        return CreateBlock::buildBlockConfig($data);
    }

    /**
     * Convert stored upgrade_mappings config to Filament form repeater items.
     *
     * @param  array<int, array<string, mixed>>  $mappings
     * @return array<int, array<string, mixed>>
     */
    private function upgradeMappingsToFormItems(array $mappings): array
    {
        $out = [];
        foreach ($mappings as $m) {
            if (! is_array($m)) {
                continue;
            }
            $match = $m['match'] ?? [];
            $plansList = is_array($m['plans'] ?? null) ? $m['plans'] : [];
            $firstPlanSellingPlanId = isset($plansList[0]) && is_array($plansList[0])
                ? (string) ($plansList[0]['selling_plan_id'] ?? '')
                : '';

            $out[] = [
                'match_product_id' => (string) ($match['product_id'] ?? ''),
                'match_variant_id' => (string) ($match['variant_id'] ?? ''),
                'match_sku_regex' => (string) ($match['sku_regex'] ?? ''),
                'match_sku_segment' => (string) ($match['sku_segment'] ?? ''),
                'match_line_item_property_exists' => (string) ($match['line_item_property_exists'] ?? ''),
                'match_line_item_property_equals' => is_array($match['line_item_property_equals'] ?? null) ? $match['line_item_property_equals'] : [],
                'action_type' => (string) ($m['action_type'] ?? 'subscription'),
                'target_variant_id' => (string) ($m['target_variant_id'] ?? ''),
                'selling_plan_id' => $firstPlanSellingPlanId,
                'quantity' => (int) ($m['quantity'] ?? 1),
                'plans' => $plansList,
            ];
        }
        return $out;
    }

    public function backToStep1(): void
    {
        $this->step = 1;
        $this->generated = null;
        $this->testLog = '';
        $this->testSummary = null;
    }

    public static function getSlug(): string
    {
        return 'create-widget-with-ai';
    }

    public function getTitle(): string | Htmlable
    {
        return static::$title ?? 'New Widget With AI';
    }

    protected function getFormStatePath(): ?string
    {
        return '';
    }
}
