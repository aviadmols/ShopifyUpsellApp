<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BlockResource\Pages\CreateBlock;
use App\Filament\Resources\BlockResource;
use App\Filament\Widgets\WidgetRegistry;
use App\Models\Block;
use App\Models\Rule;
use App\Models\Shop;
use App\Services\BlockAISchemaService;
use App\Services\OfferBuilderService;
use App\Services\OpenRouterService;
use App\Services\RuleEngine;
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
                    ->columnSpanFull(),
            ])
            ->statePath('')
            ->columns(2);
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
        $data = array_merge([
            'surface' => $surface,
            'type' => $type,
            'widget_offers' => [],
            'offer_ids_csv' => '',
        ], $config);
        return CreateBlock::buildBlockConfig($data);
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
