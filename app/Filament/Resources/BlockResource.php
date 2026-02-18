<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\RuleBuilder;
use App\Filament\Resources\BlockResource\Pages\CreateBlock;
use App\Filament\Resources\BlockResource\Pages;
use App\Filament\Resources\OfferResource;
use App\Filament\Widgets\WidgetRegistry;
use App\Models\Block;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Rule;
use App\Models\Shop;
use App\Services\ShopifyGraphQLService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlockResource extends Resource
{
    protected static ?string $model = Block::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Widgets';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Widget';

    protected static ?string $pluralModelLabel = 'Widgets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Widget identity')
                    ->description('For Checkout: create one widget per block (e.g. Upsell, Progress bar, Icon features). In Shopify → Checkout → Customize, add the app block and set "Widget ID" to this widget\'s ID (number in the table). For Upsell, add offers below or you will see "no offers" in Checkout.')
                    ->schema([
                        Forms\Components\Select::make('shop_id')
                            ->options(fn (): array => Shop::whereNull('uninstalled_at')->pluck('shop_domain', 'id')->all())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('surface')
                            ->options(array_combine(WidgetRegistry::surfaces(), WidgetRegistry::surfaces()))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('type', '')),
                        Forms\Components\Select::make('type')
                            ->options(fn (Get $get): array => WidgetRegistry::typeOptionsForSurface($get('surface')))
                            ->required(fn (Get $get): bool => ! empty($get('surface')))
                            ->live(),
                        Forms\Components\TextInput::make('name')
                            ->label('Admin label')
                            ->placeholder('e.g. Checkout upsell 1')
                            ->helperText('For "Widget ID" in Checkout settings use the widget ID number (table column ID), not this label.')
                            ->maxLength(255),
                        Forms\Components\Select::make('rule_id')
                            ->options(function (Get $get): array {
                                $shopId = $get('shop_id');
                                if (! $shopId) {
                                    return [];
                                }
                                return Rule::where('shop_id', $shopId)->pluck('name', 'id')->all();
                            })
                            ->searchable()
                            ->live()
                            ->helperText('Optional: show this block only when rule conditions match.'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Preview')
                    ->description('Live preview — updates as you change Surface, Type and options below.')
                    ->schema([
                        Forms\Components\Placeholder::make('preview_live')
                            ->label('')
                            ->content(function (Get $get): \Illuminate\Support\HtmlString {
                                try {
                                    $state = CreateBlock::getStateFromGet($get);
                                    $surface = (string) ($state['surface'] ?? '');
                                    $type = (string) ($state['type'] ?? '');
                                    $config = CreateBlock::buildBlockConfig($state);
                                    $previewOffers = [];
                                    if (($surface === 'checkout' && $type === 'upsell') || ($surface === 'post_purchase' && $type === 'post_purchase_funnel')) {
                                        $shopId = $state['shop_id'] ?? null;
                                        $widgetOffers = $state['widget_offers'] ?? [];
                                        $previewOffers = CreateBlock::enrichPreviewOffers($shopId, is_array($widgetOffers) ? $widgetOffers : []);
                                    }
                                    $html = view('filament.components.block-preview', [
                                        'surface' => $surface,
                                        'type' => $type,
                                        'config' => $config,
                                        'preview_offers' => $previewOffers,
                                    ])->render();

                                    return new \Illuminate\Support\HtmlString($html);
                                } catch (\Throwable $e) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="rounded-lg border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-400">'
                                        . '<p class="font-medium">Preview error</p><p class="mt-1">' . e($e->getMessage()) . '</p></div>'
                                    );
                                }
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(fn (Get $get): bool => empty($get('surface')) || empty($get('type'))),

                self::schemaCheckoutUpsell($form),
                self::schemaCheckoutUpgradeCard($form),
                self::schemaCheckoutUpgradeAllOtp($form),
                self::schemaCheckoutProgressBar($form),
                self::schemaContentIconFeatures($form),
                self::schemaContentBannerRichTextButton($form),
                self::schemaContentProductCard($form),
                self::schemaPostPurchaseFunnel($form),

                self::schemaWidgetOffers($form),

                self::schemaRuntimeVariables($form),

                Forms\Components\Section::make('AI-generated widget')
                    ->description('This widget was created with AI. You can review and edit the generated description and PHP logic below.')
                    ->schema([
                        Forms\Components\Textarea::make('runtime_rule_conditions_json')
                            ->label('Runtime logic (rule conditions JSON)')
                            ->helperText('Editable: this JSON is what the server actually evaluates to decide if the widget should show.')
                            ->rows(10)
                            ->extraAttributes(['class' => 'font-mono'])
                            ->visible(fn (?Block $record): bool => $record !== null)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('ai_generated_description')
                            ->label('What it does')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('ai_generated_php')
                            ->label('PHP / logic (reference)')
                            ->helperText('Editable reference only. Actual runtime checks use the Rule conditions JSON shown above.')
                            ->rows(18)
                            ->extraAttributes(['class' => 'font-mono'])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (?Block $record): bool => $record && (strlen($record->ai_generated_php ?? '') > 0 || strlen($record->ai_generated_description ?? '') > 0 || strlen($record->ai_prompt ?? '') > 0))
                    ->collapsible()
                    ->collapsed(false),

                Forms\Components\KeyValue::make('extra_config')
                    ->label('Extra config (optional)')
                    ->reorderable()
                    ->helperText('Merged into block config for advanced use.')
                    ->dehydrateStateUsing(static function ($state): array {
                        $state = is_array($state) ? $state : [];
                        $out = [];
                        foreach ($state as $k => $v) {
                            $key = trim((string) $k);
                            if ($key === '') {
                                continue;
                            }
                            if (is_array($v) || is_object($v)) {
                                $val = json_encode($v, JSON_UNESCAPED_UNICODE);
                            } else {
                                $val = $v === null ? null : trim((string) $v);
                            }
                            $out[$key] = ($val !== null && $val !== '') ? $val : null;
                        }
                        return $out;
                    }),
            ]);
    }

    protected static function schemaWidgetOffers(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Offers (manage products & rules)')
            ->description('Add offers and set rules per offer. Order below is the display order.')
            ->schema([
                Forms\Components\Repeater::make('widget_offers')
                    ->label('Offers')
                    ->defaultItems(0)
                    ->reorderable()
                    ->reorderableWithButtons()
                    ->schema([
                        Forms\Components\Select::make('product_variant_id')
                            ->label('Product variant (search)')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => OfferResource::variantOptions($get('../../shop_id'), $search))
                            ->getOptionLabelsUsing(fn ($value, Get $get): array => $value ? OfferResource::variantLabels($get('../../shop_id'), [(string) $value]) : [])
                            ->helperText('Pick a shop above first. If the list is empty (no token or scope), use the manual field below.'),
                        Forms\Components\TextInput::make('variant_id_manual')
                            ->label('Variant ID (manual)')
                            ->placeholder('e.g. 48072914534655 or gid://shopify/ProductVariant/48072914534655')
                            ->maxLength(255)
                            ->helperText('Enter variant ID when search does not work. Used if filled; otherwise the search selection above is used.')
                            ->live(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->default('Upsell offer')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')->columnSpanFull(),
                        Forms\Components\Select::make('discount_type')
                            ->options(array_combine(Offer::discountTypes(), Offer::discountTypes()))
                            ->default('none')
                            ->live(),
                        Forms\Components\TextInput::make('discount_value')
                            ->numeric()
                            ->required(fn (Get $get): bool => in_array($get('discount_type'), ['percentage', 'fixed'], true)),
                        Forms\Components\TextInput::make('image_url')->label('Image URL')->url()->maxLength(500),
                        Forms\Components\Select::make('offer_type')
                            ->options([
                                'one_time' => 'One-time only',
                                'subscription' => 'Subscription only (Recharge)',
                                'both' => 'One-time and subscription',
                            ])
                            ->default('one_time')
                            ->live(),
                        Forms\Components\TextInput::make('selling_plan_id')
                            ->label('Selling plan ID (GID)')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => in_array($get('offer_type'), ['subscription', 'both'], true)),
                        Forms\Components\TextInput::make('recharge_subscription_variant_id')
                            ->label('Recharge subscription variant ID')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => in_array($get('offer_type'), ['subscription', 'both'], true)),
                        Forms\Components\Toggle::make('allow_subscription_in_post_purchase')
                            ->default(false)
                            ->visible(fn (Get $get): bool => in_array($get('offer_type'), ['subscription', 'both'], true)),
                        Forms\Components\Placeholder::make('rule_section')
                            ->label('Show this offer when (optional)')
                            ->content('Set conditions below. Leave empty to always show.'),
                        ...RuleBuilder::schema(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->visible(fn (Get $get): bool => (($get('surface') === 'checkout' && $get('type') === 'upsell') || ($get('surface') === 'post_purchase' && $get('type') === 'post_purchase_funnel')))
            ->collapsed(false);
    }

    protected static function schemaRuntimeVariables(Form $form): Forms\Components\Section
    {
        $typesWithPlaceholders = ['upsell', 'checkout_upgrade_card', 'checkout_upgrade_all_otp', 'progress_bar', 'content_icon_features', 'content_banner', 'content_rich_text', 'content_button', 'content_product_card', 'post_purchase_funnel'];
        return Forms\Components\Section::make('Runtime variables (placeholders)')
            ->description('To replace placeholders like {dog_names_message} in your headline / section heading / description, define them here. The PHP snippet in "AI-generated widget" is reference only and is NOT executed.')
            ->schema([
                Forms\Components\Textarea::make('runtime_variables_json')
                    ->label('Runtime variables (JSON)')
                    ->rows(14)
                    ->extraAttributes(['class' => 'font-mono text-sm'])
                    ->helperText('Example for "Dog Name" line item property: {"dog_names_message":{"type":"plural_message_from_property","property":"Dog Name","singular":"Your dog ({value}) deserves the best","plural":"Your dogs ({values}) deserve the best","empty":""}}')
                    ->placeholder('{}'),
            ])
            ->visible(fn (Get $get): bool => in_array((string) ($get('type') ?? ''), $typesWithPlaceholders, true))
            ->collapsible()
            ->collapsed(false);
    }

    protected static function schemaCheckoutUpsell(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Upsell block (Checkout)')
            ->description('Display options. Add offers in "Offers (manage products & rules)" above, or use comma-separated Offer IDs below. If you leave both empty, Checkout will show "no offers".')
            ->schema([
                Forms\Components\TextInput::make('offer_ids_csv')
                    ->label('Offer IDs (comma separated, fallback)')
                    ->placeholder('1,2,3')
                    ->required(fn (Get $get): bool => empty($get('widget_offers')))
                    ->helperText('Required when no offers are added above.'),
                Forms\Components\TextInput::make('max_offers')
                    ->numeric()
                    ->default(3)
                    ->minValue(1),
                Forms\Components\Select::make('display_mode')
                    ->options(['stacked' => 'Stacked', 'single' => 'Single card', 'grid' => 'Grid (2 columns)', 'row' => 'Row (image left, details right)'])
                    ->default('stacked'),
                Forms\Components\Toggle::make('require_expanded')
                    ->default(false),
                Forms\Components\TextInput::make('section_heading')
                    ->default('Add to your order')
                    ->maxLength(100),
                Forms\Components\Select::make('title_size')
                    ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large', 'extraLarge' => 'Extra large'])
                    ->default('medium'),
                Forms\Components\Select::make('title_appearance')
                    ->options(['default' => 'Default', 'accent' => 'Accent', 'subdued' => 'Subdued', 'info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'critical' => 'Critical'])
                    ->default('default'),
                Forms\Components\Toggle::make('show_price')->default(true),
                Forms\Components\Toggle::make('show_description')->default(true),
                Forms\Components\Select::make('image_aspect_ratio')
                    ->options(['' => 'Auto', '1' => '1:1', '1.25' => '5:4', '1.5' => '3:2', '0.75' => '4:3'])
                    ->default(''),
                Forms\Components\Select::make('image_fit')
                    ->options(['cover' => 'Cover', 'contain' => 'Contain', 'fill' => 'Fill'])
                    ->default('cover'),
                Forms\Components\Select::make('image_corner_radius')
                    ->options(['none' => 'None', 'small' => 'Small', 'base' => 'Base', 'large' => 'Large'])
                    ->default('base'),
                Forms\Components\Select::make('button_kind')
                    ->options(['primary' => 'Primary', 'secondary' => 'Secondary', 'plain' => 'Plain'])
                    ->default('secondary'),
                Forms\Components\Select::make('button_appearance')
                    ->options(['default' => 'Default', 'monochrome' => 'Monochrome', 'critical' => 'Critical'])
                    ->default('default'),
                Forms\Components\Select::make('card_spacing')
                    ->options(['tight' => 'Tight', 'loose' => 'Loose', 'extraLoose' => 'Extra loose'])
                    ->default('loose'),
                Forms\Components\Toggle::make('divider_between_cards')->default(false),
                Forms\Components\Toggle::make('show_quantity')
                    ->label('Show quantity selector')
                    ->default(true)
                    ->helperText('When on, customers can choose quantity (1–max) before adding to cart. Requires Checkout Experience → Quantity in upsell block to be enabled for the shop.'),
            ])
            ->columns(2)
            ->visible(fn (Get $get): bool => $get('surface') === 'checkout' && $get('type') === 'upsell');
    }

    protected static function schemaCheckoutUpgradeCard(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Upgrade card (Checkout Order Summary)')
            ->description('Single card after cart line list. Match cart lines by product/variant/SKU/properties and offer subscription or bundle swap. To show in Checkout: (1) Note this block\'s ID (from the Blocks table or the URL when editing). (2) In Shopify Partners → your app → Extensions → "Zyg Upgrade Card" → Settings, set Widget ID to that ID, and set Extension secret / API URL. (3) In the store: Settings → Checkout → Customize → add the "Zyg Upgrade Card" app block to the Order summary (cart) area → Save.')
            ->schema([
                Forms\Components\Section::make('When to show this block (Checkout Order Summary)')
                    ->description('The block is shown only when the rule below matches (e.g. cart subtotal, product in cart) and at least one upgrade mapping matches a cart line. No rule = block can show whenever a mapping matches.')
                    ->schema([
                        Forms\Components\Select::make('rule_id')
                            ->label('Block visibility rule')
                            ->options(function (Get $get): array {
                                $shopId = $get('shop_id');
                                if (! $shopId) {
                                    return [];
                                }
                                return Rule::where('shop_id', $shopId)->pluck('name', 'id')->all();
                            })
                            ->searchable()
                            ->placeholder('No rule — show whenever a mapping matches')
                            ->helperText('Create rules in the Rules menu. Example: "Subtotal ≥ 50" or "Cart has product X".'),
                        Forms\Components\Placeholder::make('upgrade_card_rule_summary')
                            ->label('')
                            ->content(function (Get $get): string {
                                $ruleId = $get('rule_id');
                                if (! $ruleId) {
                                    return '';
                                }
                                $rule = Rule::find($ruleId);
                                if (! $rule) {
                                    return '';
                                }
                                $conditions = $rule->conditions ?? [];
                                if (! is_array($conditions) || $conditions === []) {
                                    return 'Rule: '.$rule->name.' (no conditions).';
                                }
                                $parts = [];
                                $group = isset($conditions['or']) ? 'or' : 'and';
                                $rows = $conditions[$group] ?? [];
                                foreach (is_array($rows) ? $rows : [] as $c) {
                                    if (! is_array($c)) {
                                        continue;
                                    }
                                    foreach ($c as $field => $value) {
                                        if ($value === null) {
                                            continue;
                                        }
                                        $valueStr = is_array($value) ? implode(', ', $value) : (string) $value;
                                        if ($valueStr !== '') {
                                            $parts[] = $field.': '.$valueStr;
                                        }
                                    }
                                }
                                return $parts === [] ? 'Rule: '.$rule->name : 'Rule: '.$rule->name.' — '.implode(' '.$group.' ', $parts);
                            })
                            ->visible(fn (Get $get): bool => (bool) $get('rule_id')),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\TextInput::make('upgrade_card_headline')
                    ->label('Headline')
                    ->placeholder('Upgrade to subscribe & save')
                    ->maxLength(120),
                Forms\Components\Textarea::make('upgrade_card_description')
                    ->label('Description')
                    ->rows(2)
                    ->placeholder('Get 15% off when you subscribe')
                    ->maxLength(300),
                Forms\Components\TextInput::make('upgrade_card_cta_label')
                    ->label('CTA button label')
                    ->default('Upgrade')
                    ->maxLength(60),
                Forms\Components\TextInput::make('upgrade_card_cart_subtotal_min')
                    ->label('Min. cart subtotal (optional)')
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('0'),
                Forms\Components\TextInput::make('upgrade_card_cart_items_count_min')
                    ->label('Min. cart items (optional)')
                    ->numeric()
                    ->minValue(0)
                    ->integer()
                    ->placeholder('0'),
                Forms\Components\Section::make('Default design')
                    ->description('Used when an offer does not define its own design. Each offer can override these in "Design for this offer" inside the mapping.')
                    ->schema([
                        Forms\Components\Select::make('upgrade_card_display_mode')
                            ->label('Card content')
                            ->options([
                                'text' => 'Headline + description + button (default)',
                                'image' => 'Image + button only',
                            ])
                            ->default('text')
                            ->live()
                            ->helperText('Image mode shows only the image and CTA button; no headline or description.'),
                        Forms\Components\TextInput::make('upgrade_card_image_url')
                            ->label('Image URL')
                            ->url()
                            ->placeholder('https://…')
                            ->maxLength(2048)
                            ->visible(fn (Get $get): bool => (string) ($get('upgrade_card_display_mode') ?? '') === 'image')
                            ->helperText('Required when using "Image + button only". Image will be shown above the button.'),
                        Forms\Components\Select::make('upgrade_card_title_size')
                            ->label('Headline size')
                            ->visible(fn (Get $get): bool => (string) ($get('upgrade_card_display_mode') ?? 'text') !== 'image')
                            ->options([
                                'small' => 'Small',
                                'medium' => 'Medium',
                                'large' => 'Large',
                            ])
                            ->default('medium'),
                        Forms\Components\Select::make('upgrade_card_button_kind')
                            ->label('CTA button style')
                            ->options([
                                'primary' => 'Primary',
                                'secondary' => 'Secondary',
                                'plain' => 'Plain',
                            ])
                            ->default('secondary'),
                        Forms\Components\Select::make('upgrade_card_spacing')
                            ->label('Card spacing')
                            ->options([
                                'tight' => 'Tight',
                                'loose' => 'Loose',
                            ])
                            ->default('tight'),
                        Forms\Components\Toggle::make('upgrade_card_show_border')
                            ->label('Show card border')
                            ->default(true),
                        Forms\Components\Select::make('upgrade_card_border_radius')
                            ->label('Card corner radius')
                            ->options([
                                'none' => 'None',
                                'base' => 'Base',
                                'large' => 'Large',
                            ])
                            ->default('base'),
                        Forms\Components\Select::make('upgrade_card_padding')
                            ->label('Card padding')
                            ->options([
                                'none' => 'None',
                                'tight' => 'Tight',
                                'base' => 'Base',
                                'loose' => 'Loose',
                            ])
                            ->default('base'),
                        Forms\Components\Toggle::make('upgrade_card_show_items')
                            ->label('Show matched items list')
                            ->default(true)
                            ->visible(fn (Get $get): bool => (string) ($get('upgrade_card_display_mode') ?? 'text') !== 'image'),
                        Forms\Components\TextInput::make('upgrade_card_plan_label')
                            ->label('Plan dropdown label')
                            ->default('Plan')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('upgrade_card_items_max_visible')
                            ->label('Max items visible')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(10)
                            ->default(3),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\Section::make('Flow view')
                    ->description('Summary of when each offer is shown and what is offered (read-only).')
                    ->schema([
                        Forms\Components\Placeholder::make('upgrade_flow_view')
                            ->label('')
                            ->content(function (Get $get): \Illuminate\Support\HtmlString {
                                $items = $get('upgrade_mappings_items');
                                if (! is_array($items) || $items === []) {
                                    return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-500">Add mappings below to see the flow.</p>');
                                }
                                $steps = [];
                                $itemsArr = array_values($items);
                                foreach ($itemsArr as $idx => $m) {
                                    if (! is_array($m)) {
                                        continue;
                                    }
                                    $when = [];
                                    if (! empty($m['match_product_id'])) {
                                        $when[] = 'Product '.preg_replace('/\D/', '', (string) $m['match_product_id']) ?: $m['match_product_id'];
                                    }
                                    if (! empty($m['match_variant_id'])) {
                                        $when[] = 'Variant '.preg_replace('/\D/', '', (string) $m['match_variant_id']) ?: $m['match_variant_id'];
                                    }
                                    if (! empty($m['match_sku_segment'])) {
                                        $when[] = 'SKU contains «'.e((string) $m['match_sku_segment']).'»';
                                    }
                                    if (! empty($m['match_quantity_min'])) {
                                        $when[] = 'Qty ≥ '.$m['match_quantity_min'];
                                    }
                                    if (! empty($m['match_quantity_max'])) {
                                        $when[] = 'Qty ≤ '.$m['match_quantity_max'];
                                    }
                                    $sub = (string) ($m['match_subscription'] ?? 'any');
                                    if ($sub === 'must_be_subscription') {
                                        $when[] = 'Subscription only';
                                    } elseif ($sub === 'must_be_one_time') {
                                        $when[] = 'One-time only';
                                    }
                                    $whenStr = count($when) > 0 ? implode(' · ', $when) : 'Any cart line';
                                    $offer = (string) ($m['target_variant_id'] ?? '');
                                    $offerStr = $offer !== '' ? ('Variant '.preg_replace('/\D/', '', $offer) ?: $offer) : '—';
                                    $plans = $m['plans'] ?? [];
                                    if (is_array($plans) && isset($plans[0]['label']) && (string) $plans[0]['label'] !== '') {
                                        $offerStr .= ' · '.e((string) $plans[0]['label']);
                                    }
                                    $stepNum = $idx + 1;
                                    $next = isset($itemsArr[$idx + 1]) ? 'Step '.($idx + 2) : 'End';
                                    $steps[] = '<div class="border border-gray-200 rounded p-2 mb-2 text-sm"><strong>Step '.$stepNum.':</strong> When cart: '.e($whenStr).' → Offer: '.e($offerStr).'<br><span class="text-gray-500">Next: '.e($next).'</span></div>';
                                }
                                return new \Illuminate\Support\HtmlString('<div class="space-y-1">'.implode('', $steps).'</div>');
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\Repeater::make('upgrade_mappings_items')
                    ->label('Upgrade mappings')
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(function (array $state): string {
                                $when = [];
                                if (! empty($state['match_product_id'])) {
                                    $when[] = 'Product '.preg_replace('/\D/', '', (string) $state['match_product_id']) ?: $state['match_product_id'];
                                }
                                if (! empty($state['match_variant_id'])) {
                                    $when[] = 'Variant '.preg_replace('/\D/', '', (string) $state['match_variant_id']) ?: $state['match_variant_id'];
                                }
                                if (! empty($state['match_sku_segment'])) {
                                    $when[] = 'SKU «'.e((string) $state['match_sku_segment']).'»';
                                }
                                if (! empty($state['match_quantity_min'])) {
                                    $when[] = 'Qty ≥ '.$state['match_quantity_min'];
                                }
                                if (! empty($state['match_quantity_max'])) {
                                    $when[] = 'Qty ≤ '.$state['match_quantity_max'];
                                }
                                $sub = (string) ($state['match_subscription'] ?? 'any');
                                if ($sub === 'must_be_subscription') {
                                    $when[] = 'Subscription';
                                } elseif ($sub === 'must_be_one_time') {
                                    $when[] = 'One-time';
                                }
                                $whenStr = count($when) > 0 ? implode(' · ', $when) : 'Any cart line';
                                $offer = (string) ($state['target_variant_id'] ?? '');
                                $offerStr = $offer !== '' ? ('Variant '.preg_replace('/\D/', '', $offer) ?: $offer) : '—';
                                $plans = $state['plans'] ?? [];
                                if (is_array($plans) && isset($plans[0]['label']) && (string) $plans[0]['label'] !== '') {
                                    $offerStr .= ' · '.e((string) $plans[0]['label']);
                                }
                                return 'When: '.$whenStr.' → Offer: '.$offerStr;
                            })
                    ->schema([
                        Forms\Components\Placeholder::make('mapping_summary')
                            ->label('')
                            ->content(function (Get $get): \Illuminate\Support\HtmlString {
                                $when = [];
                                if ((string) $get('match_product_id') !== '') {
                                    $when[] = 'Product '.preg_replace('/\D/', '', (string) $get('match_product_id')) ?: $get('match_product_id');
                                }
                                if ((string) $get('match_variant_id') !== '') {
                                    $when[] = 'Variant '.preg_replace('/\D/', '', (string) $get('match_variant_id')) ?: $get('match_variant_id');
                                }
                                if ((string) $get('match_sku_segment') !== '') {
                                    $when[] = 'SKU contains «'.e((string) $get('match_sku_segment')).'»';
                                }
                                if ((string) $get('match_sku_regex') !== '') {
                                    $when[] = 'SKU regex';
                                }
                                $qMin = $get('match_quantity_min');
                                $qMax = $get('match_quantity_max');
                                if ($qMin !== null && $qMin !== '') {
                                    $when[] = 'Qty ≥ '.$qMin;
                                }
                                if ($qMax !== null && $qMax !== '') {
                                    $when[] = 'Qty ≤ '.$qMax;
                                }
                                $sub = (string) ($get('match_subscription') ?? 'any');
                                if ($sub === 'must_be_subscription') {
                                    $when[] = 'Subscription only';
                                } elseif ($sub === 'must_be_one_time') {
                                    $when[] = 'One-time only';
                                }
                                $whenStr = count($when) > 0 ? implode(' · ', $when) : 'Any cart line';
                                $offer = (string) $get('target_variant_id');
                                $offerStr = $offer !== '' ? ('Variant '.preg_replace('/\D/', '', $offer) ?: $offer) : '—';
                                $plans = $get('plans');
                                $firstPlanLabel = null;
                                if (is_array($plans) && isset($plans[0]['label']) && (string) $plans[0]['label'] !== '') {
                                    $firstPlanLabel = (string) $plans[0]['label'];
                                }
                                if ($firstPlanLabel !== null) {
                                    $offerStr .= ' · '.$firstPlanLabel;
                                }
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="text-sm"><strong>When:</strong> '.e($whenStr).'</div><div class="text-sm mt-1"><strong>Offer:</strong> '.e($offerStr).'</div>'
                                );
                            }),
                        Forms\Components\Section::make('Match (when to show this upgrade)')
                            ->schema([
                                Forms\Components\TextInput::make('match_variant_id')
                                    ->label('Variant ID (optional)')
                                    ->placeholder('GID or numeric'),
                                Forms\Components\TextInput::make('match_product_id')
                                    ->label('Product ID (optional)')
                                    ->placeholder('GID or numeric'),
                                Forms\Components\Section::make('Advanced')
                                    ->description('SKU, quantity, subscription, line properties — click to expand.')
                                    ->schema([
                                        Forms\Components\TextInput::make('match_sku_regex')
                                            ->label('SKU regex (optional)')
                                            ->placeholder('/^ABC-\\d+$/'),
                                        Forms\Components\TextInput::make('match_sku_segment')
                                            ->label('SKU contains (optional)')
                                            ->placeholder('SUB'),
                                        Forms\Components\TextInput::make('match_line_item_property_exists')
                                            ->label('Line has property (optional)')
                                            ->placeholder('Dog Name'),
                                        Forms\Components\KeyValue::make('match_line_item_property_equals')
                                            ->label('Property equals (optional)')
                                            ->keyPlaceholder('key')
                                            ->valuePlaceholder('value')
                                            ->helperText('For «no selling plan» or «has subscription» use «Line subscription status» below — do not use keys like subscription or selling_plan_id here. Property equals is for custom line item properties (e.g. Dog Name, gift message).')
                                            ->default([])
                                            ->dehydrateStateUsing(static function ($state): array {
                                                $state = is_array($state) ? $state : [];
                                                $out = [];
                                                foreach ($state as $k => $v) {
                                                    $key = trim((string) $k);
                                                    if ($key === '') {
                                                        continue;
                                                    }
                                                    if (is_array($v) || is_object($v)) {
                                                        $val = json_encode($v, JSON_UNESCAPED_UNICODE);
                                                    } else {
                                                        $val = $v === null ? null : trim((string) $v);
                                                    }
                                                    $out[$key] = ($val !== null && $val !== '') ? $val : null;
                                                }
                                                return $out;
                                            }),
                                        Forms\Components\TextInput::make('match_quantity_min')
                                            ->label('Line quantity min (optional)')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->placeholder('e.g. 2'),
                                        Forms\Components\TextInput::make('match_quantity_max')
                                            ->label('Line quantity max (optional)')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->placeholder('e.g. 10'),
                                        Forms\Components\Select::make('match_subscription')
                                            ->label('Line subscription status')
                                            ->options([
                                                'any' => 'Any (subscription or one-time)',
                                                'must_be_subscription' => 'Must be subscription (has selling plan)',
                                                'must_be_one_time' => 'Must be one-time (no selling plan)',
                                            ])
                                            ->default('any'),
                                        Forms\Components\TextInput::make('match_selling_plan_id')
                                            ->label('Selling plan ID (optional, exact plan)')
                                            ->placeholder('GID or numeric')
                                            ->maxLength(255),
                                    ])
                                    ->columns(2)
                                    ->collapsible()
                                    ->collapsed(),
                            ])
                            ->columns(2)
                            ->collapsible(),
                        Forms\Components\Select::make('action_type')
                            ->options(['subscription' => 'Subscription (Recharge)', 'bundle_swap' => 'Bundle swap'])
                            ->default('subscription')
                            ->required(),
                        Forms\Components\TextInput::make('target_variant_id')
                            ->label('Target variant ID')
                            ->placeholder('Variant GID or numeric ID (use Tools → Products, variants & selling plans)')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Free text field: enter a Variant GID (e.g. gid://shopify/ProductVariant/123) or a numeric variant ID. Use Tools → Products, variants & selling plans to browse and copy IDs from the store.'),
                        Forms\Components\TextInput::make('selling_plan_id')
                            ->label('Selling plan ID (optional)')
                            ->placeholder('Selling plan GID or numeric ID')
                            ->maxLength(255)
                            ->helperText('Optional. Use Tools → Products, variants & selling plans to find selling plan IDs for products that have subscriptions.'),
                        Forms\Components\Section::make('Offer design (text or image for this mapping)')
                            ->description('When this mapping is the first match, show either headline + description + CTA, or an image + CTA. Placeholders in text: {cart_subtotal}, {first_product_title}, {matched_quantity}, {matched_variant_id}, {matched_is_subscription}.')
                            ->schema([
                                Forms\Components\Select::make('mapping_display_mode')
                                    ->label('Show')
                                    ->options([
                                        'text' => 'Headline + description + button',
                                        'image' => 'Image + button only',
                                    ])
                                    ->default('text')
                                    ->live(),
                                Forms\Components\TextInput::make('mapping_image_url')
                                    ->label('Image URL')
                                    ->url()
                                    ->placeholder('https://…')
                                    ->maxLength(2048)
                                    ->visible(fn (Get $get): bool => (string) ($get('mapping_display_mode') ?? '') === 'image')
                                    ->helperText('Shown instead of headline/description when this mapping matches.'),
                                Forms\Components\TextInput::make('mapping_headline')
                                    ->label('Headline override')
                                    ->maxLength(120)
                                    ->placeholder('Upgrade to subscribe & save')
                                    ->visible(fn (Get $get): bool => (string) ($get('mapping_display_mode') ?? 'text') !== 'image'),
                                Forms\Components\Textarea::make('mapping_description')
                                    ->label('Description override')
                                    ->rows(2)
                                    ->maxLength(300)
                                    ->placeholder('Get {discount_percent}% off when you subscribe')
                                    ->visible(fn (Get $get): bool => (string) ($get('mapping_display_mode') ?? 'text') !== 'image'),
                                Forms\Components\TextInput::make('mapping_cta_label')
                                    ->label('CTA label override')
                                    ->maxLength(60)
                                    ->placeholder('Upgrade'),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(),
                        Forms\Components\Section::make('Design for this offer')
                            ->description('Optional. Override card appearance when this mapping is the first match. Leave empty to use the default design above.')
                            ->schema([
                                Forms\Components\Select::make('mapping_title_size')
                                    ->label('Headline size')
                                    ->options([
                                        'small' => 'Small',
                                        'medium' => 'Medium',
                                        'large' => 'Large',
                                    ])
                                    ->placeholder('Use default'),
                                Forms\Components\Select::make('mapping_button_kind')
                                    ->label('CTA button style')
                                    ->options([
                                        'primary' => 'Primary',
                                        'secondary' => 'Secondary',
                                        'plain' => 'Plain',
                                    ])
                                    ->placeholder('Use default'),
                                Forms\Components\Select::make('mapping_spacing')
                                    ->label('Card spacing')
                                    ->options([
                                        'tight' => 'Tight',
                                        'loose' => 'Loose',
                                    ])
                                    ->placeholder('Use default'),
                                Forms\Components\Toggle::make('mapping_show_border')
                                    ->label('Show card border'),
                                Forms\Components\Select::make('mapping_border_radius')
                                    ->label('Card corner radius')
                                    ->options([
                                        'none' => 'None',
                                        'base' => 'Base',
                                        'large' => 'Large',
                                    ])
                                    ->placeholder('Use default'),
                                Forms\Components\Select::make('mapping_padding')
                                    ->label('Card padding')
                                    ->options([
                                        'none' => 'None',
                                        'tight' => 'Tight',
                                        'base' => 'Base',
                                        'loose' => 'Loose',
                                    ])
                                    ->placeholder('Use default'),
                                Forms\Components\Toggle::make('mapping_show_items')
                                    ->label('Show matched items list'),
                                Forms\Components\TextInput::make('mapping_plan_label')
                                    ->label('Plan dropdown label')
                                    ->maxLength(40)
                                    ->placeholder('Use default'),
                                Forms\Components\TextInput::make('mapping_items_max_visible')
                                    ->label('Max items visible')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->placeholder('Use default'),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(),
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                        Forms\Components\Repeater::make('plans')
                            ->label('Plan options for this offer (dropdown + action)')
                            ->helperText('Options shown in the card dropdown when this mapping matches. Each plan can have its own variant and selling plan ID for the action.')
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->label('Plan ID')
                                    ->required()
                                    ->placeholder('1_month'),
                                Forms\Components\TextInput::make('label')
                                    ->label('Plan label')
                                    ->required()
                                    ->placeholder('Deliver every 1 month'),
                                Forms\Components\TextInput::make('target_variant_id')
                                    ->label('Target variant (if different per plan)')
                                    ->placeholder('GID or numeric'),
                                Forms\Components\TextInput::make('selling_plan_id')
                                    ->label('Selling plan ID (Shopify)')
                                    ->placeholder('gid://shopify/SellingPlan/…'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add plan'),
                    ])
                    ->columns(1)
                    ->defaultItems(0)
                    ->addActionLabel('Add mapping')
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('upgrade_card_plans')
                    ->label('Card plans dropdown (fallback only)')
                    ->helperText('Used only when the matching mapping has no plan options. Prefer defining plans inside each mapping above.')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Plan ID')
                            ->required()
                            ->placeholder('1_month'),
                        Forms\Components\TextInput::make('label')
                            ->label('Label')
                            ->required()
                            ->placeholder('Every 1 month'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add plan option')
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->visible(fn (Get $get): bool => $get('surface') === 'checkout' && $get('type') === 'checkout_upgrade_card');
    }

    protected static function schemaCheckoutUpgradeAllOtp(Form $form): Forms\Components\Section
    {
        $defaultSubtext = "Upgrade your items to subscription and save up to {{saving.amount}} today!\n\n- Get bigger savings\n- Automatic resupply\n- Free shipping\n- Modify or cancel anytime (no strings attached)\n- 90-day money-back guarantee";
        return Forms\Components\Section::make('Upgrade all to subscription (OTP cart)')
            ->description('Shown only when the cart has no subscriptions. One click converts all eligible cart items to subscription. Use the same "Zyg Upgrade Card" extension and set Widget ID to this block\'s ID. Use the optional "Block visibility rule" above to restrict when this block appears.')
            ->schema([
                Forms\Components\TextInput::make('upgrade_all_otp_headline')
                    ->label('Headline')
                    ->default('UPGRADE TO SUBSCRIPTION AND SAVE')
                    ->maxLength(120),
                Forms\Components\Textarea::make('upgrade_all_otp_subtext')
                    ->label('Subtext')
                    ->rows(6)
                    ->default($defaultSubtext)
                    ->helperText('Use {{saving.amount}} for total savings. Lines starting with "- " become bullets.')
                    ->maxLength(1000),
                Forms\Components\TextInput::make('upgrade_all_otp_product_list_label')
                    ->label('Product list label')
                    ->placeholder('Deliver every {{frequency}}:')
                    ->default('Deliver every {{frequency}}:')
                    ->maxLength(120),
                Forms\Components\TextInput::make('upgrade_all_otp_cta_label')
                    ->label('CTA button label')
                    ->default('SUBSCRIBE & SAVE')
                    ->maxLength(60),
                Forms\Components\TextInput::make('upgrade_all_otp_success_headline')
                    ->label('Success headline (after upgrade)')
                    ->placeholder('You saved {{saving.amount}} by upgrading products to a subscription!')
                    ->default('You saved {{saving.amount}} by upgrading products to a subscription!')
                    ->maxLength(120),
                Forms\Components\TextInput::make('upgrade_all_otp_undo_link_text')
                    ->label('Undo link text')
                    ->default('Undo savings')
                    ->maxLength(60),
                Forms\Components\Section::make('Design')
                    ->schema([
                        Forms\Components\Select::make('upgrade_all_otp_title_size')
                            ->label('Headline size')
                            ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                            ->default('medium'),
                        Forms\Components\Select::make('upgrade_all_otp_button_kind')
                            ->label('CTA button style')
                            ->options(['primary' => 'Primary', 'secondary' => 'Secondary', 'plain' => 'Plain'])
                            ->default('primary'),
                        Forms\Components\Select::make('upgrade_all_otp_spacing')
                            ->label('Spacing')
                            ->options(['tight' => 'Tight', 'loose' => 'Loose'])
                            ->default('tight'),
                        Forms\Components\Toggle::make('upgrade_all_otp_show_border')
                            ->label('Show card border')
                            ->default(true),
                        Forms\Components\Select::make('upgrade_all_otp_padding')
                            ->label('Card padding')
                            ->options(['none' => 'None', 'tight' => 'Tight', 'base' => 'Base', 'loose' => 'Loose'])
                            ->default('base'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->columns(1)
            ->visible(fn (Get $get): bool => $get('surface') === 'checkout' && $get('type') === 'checkout_upgrade_all_otp');
    }

    /**
     * Selling plan options for variant (Shopify) for Upgrade card.
     *
     * @return array<string, string>  id => name
     */
    public static function sellingPlanOptionsForVariant(?int $shopId, ?string $variantGid): array
    {
        if (! $shopId || ! $variantGid || trim($variantGid) === '') {
            return [];
        }
        $shop = Shop::whereNull('uninstalled_at')->find($shopId);
        if (! $shop) {
            return [];
        }
        try {
            $plans = app(ShopifyGraphQLService::class)->getSellingPlansForVariant($shop, trim($variantGid));
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($plans as $plan) {
            $id = (string) ($plan['id'] ?? '');
            $name = (string) ($plan['name'] ?? $id);
            if ($id !== '') {
                $out[$id] = $name;
            }
        }
        return $out;
    }

    protected static function schemaCheckoutProgressBar(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Progress bar block (Checkout)')
            ->schema([
                Forms\Components\Select::make('progress_bar_type')
                    ->options(['free_shipping' => 'Free shipping', 'discount' => 'Discount'])
                    ->default('free_shipping'),
                Forms\Components\TextInput::make('progress_bar_goal')
                    ->label('Goal amount')
                    ->numeric()
                    ->minValue(0.01)
                    ->default(100)
                    ->required(),
                Forms\Components\TextInput::make('progress_bar_message_below')
                    ->label('Message when below goal')
                    ->placeholder("You're {amount} away from free shipping!")
                    ->default("You're {amount} away from free shipping!")
                    ->maxLength(200),
                Forms\Components\TextInput::make('progress_bar_message_achieved')
                    ->label('Message when goal reached')
                    ->placeholder("You've unlocked free shipping!")
                    ->default("You've unlocked free shipping!")
                    ->maxLength(200),
                Forms\Components\Select::make('progress_bar_discount_type')
                    ->options(['percentage' => 'Percentage', 'fixed' => 'Fixed'])
                    ->default('percentage')
                    ->visible(fn (Get $get): bool => $get('progress_bar_type') === 'discount'),
                Forms\Components\TextInput::make('progress_bar_discount_value')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => $get('progress_bar_type') === 'discount'),
            ])
            ->columns(2)
            ->visible(fn (Get $get): bool => $get('surface') === 'checkout' && $get('type') === 'progress_bar');
    }

    protected static function schemaContentIconFeatures(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Icon features (e.g. Secure Payments, Guarantee, Fast Shipping)')
            ->schema([
                Forms\Components\Repeater::make('icon_features_items')
                    ->label('Items')
                    ->schema([
                        Forms\Components\Select::make('icon')
                            ->options([
                                'lock' => 'Lock (secure)',
                                'bag' => 'Bag / store',
                                'truck' => 'Truck (shipping)',
                                'gift' => 'Gift',
                                'checkCircle' => 'Check circle',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('subtitle')
                            ->maxLength(300)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add feature'),
            ])
            ->visible(fn (Get $get): bool => ($get('surface') === 'checkout' || $get('surface') === 'thank_you') && $get('type') === 'content_icon_features');
    }

    protected static function schemaContentBannerRichTextButton(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Content (banner / text / button)')
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['content_banner', 'content_rich_text'], true)),
                Forms\Components\Textarea::make('body')
                    ->rows(4)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['content_banner', 'content_rich_text'], true)),
                Forms\Components\TextInput::make('image_url')
                    ->label('Image URL')
                    ->url()
                    ->maxLength(500)
                    ->visible(fn (Get $get): bool => $get('type') === 'content_banner'),
                Forms\Components\TextInput::make('button_label')
                    ->maxLength(80)
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['content_banner', 'content_button'], true)),
                Forms\Components\TextInput::make('button_url')
                    ->label('Button URL')
                    ->maxLength(500)
                    ->visible(fn (Get $get): bool => in_array($get('type'), ['content_banner', 'content_button'], true)),
                Forms\Components\Select::make('text_size')
                    ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                    ->default('medium'),
                Forms\Components\Select::make('text_appearance')
                    ->options(['default' => 'Default', 'subdued' => 'Subdued'])
                    ->default('default'),
                Forms\Components\Select::make('button_kind')
                    ->options(['primary' => 'Primary', 'secondary' => 'Secondary'])
                    ->default('secondary'),
                Forms\Components\Select::make('spacing')
                    ->options(['tight' => 'Tight', 'loose' => 'Loose'])
                    ->default('tight'),
            ])
            ->columns(2)
            ->visible(fn (Get $get): bool => in_array($get('type'), ['content_banner', 'content_rich_text', 'content_button'], true) && in_array($get('surface'), ['checkout', 'thank_you'], true));
    }

    protected static function schemaContentProductCard(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Product card content')
            ->schema([
                Forms\Components\TextInput::make('title')->maxLength(255),
                Forms\Components\Textarea::make('body')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('image_url')->label('Image URL')->url()->maxLength(500),
                Forms\Components\TextInput::make('product_id')
                    ->label('Product ID / handle')
                    ->maxLength(255),
                Forms\Components\TextInput::make('price_text')->maxLength(120),
                Forms\Components\TextInput::make('badge_text')->maxLength(120),
                Forms\Components\Toggle::make('show_price')->default(true),
                Forms\Components\TextInput::make('button_label')->maxLength(80),
                Forms\Components\TextInput::make('button_url')->label('Button URL')->maxLength(500),
                Forms\Components\Select::make('text_size')
                    ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                    ->default('medium'),
                Forms\Components\Select::make('button_kind')
                    ->options(['primary' => 'Primary', 'secondary' => 'Secondary'])
                    ->default('secondary'),
                Forms\Components\Select::make('spacing')
                    ->options(['tight' => 'Tight', 'loose' => 'Loose'])
                    ->default('tight'),
            ])
            ->columns(2)
            ->visible(fn (Get $get): bool => $get('type') === 'content_product_card' && in_array($get('surface'), ['checkout', 'thank_you'], true));
    }

    protected static function schemaPostPurchaseFunnel(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Post-purchase funnel')
            ->schema([
                Forms\Components\TextInput::make('offer_ids_csv')
                    ->label('Offer IDs (order = funnel steps, fallback)')
                    ->placeholder('1,2,3')
                    ->required(fn (Get $get): bool => empty($get('widget_offers')))
                    ->helperText('Required when no offers are added in the section above.'),
                Forms\Components\TextInput::make('max_offers')
                    ->numeric()
                    ->minValue(1)
                    ->default(3),
                Forms\Components\TextInput::make('cooldown_hours')
                    ->numeric()
                    ->minValue(0)
                    ->default(24),
                Forms\Components\Toggle::make('allow_reoffer')->default(false),
                Forms\Components\TextInput::make('funnel_headline_template')
                    ->placeholder('{first_name}, before you go!')
                    ->maxLength(120),
                Forms\Components\Toggle::make('funnel_show_progress')->default(true),
                Forms\Components\TextInput::make('funnel_step_labels')
                    ->placeholder('Order, Offer, Bonus, Done')
                    ->maxLength(200),
                Forms\Components\Toggle::make('show_timer')->default(false),
                Forms\Components\TextInput::make('timer_seconds')->numeric()->minValue(0)->default(300)->visible(fn (Get $get): bool => (bool) $get('show_timer')),
                Forms\Components\TextInput::make('timer_label')->placeholder('For a limited time')->maxLength(80)->visible(fn (Get $get): bool => (bool) $get('show_timer')),
                Forms\Components\Textarea::make('urgency_message')->rows(2)->maxLength(300),
                Forms\Components\TextInput::make('cta_text')->placeholder('Pay Now')->maxLength(40),
                Forms\Components\TextInput::make('decline_text')->placeholder('Decline offer')->maxLength(40),
                Forms\Components\TextInput::make('quantity_default')->numeric()->minValue(1)->default(1),
                Forms\Components\TextInput::make('quantity_min')->numeric()->minValue(1)->default(1),
                Forms\Components\TextInput::make('quantity_max')->numeric()->minValue(1)->default(10),
            ])
            ->columns(2)
            ->visible(fn (Get $get): bool => $get('surface') === 'post_purchase' && $get('type') === 'post_purchase_funnel');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->label('ID'),
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('surface')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->relationship('shop', 'shop_domain')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('surface')
                    ->options(array_combine(WidgetRegistry::surfaces(), WidgetRegistry::surfaces())),
                Tables\Filters\SelectFilter::make('type')
                    ->options(WidgetRegistry::allTypeLabels()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlocks::route('/'),
            'create' => Pages\CreateBlock::route('/create'),
            'edit' => Pages\EditBlock::route('/{record}/edit'),
        ];
    }
}
