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
                    ->helperText('Merged into block config for advanced use.'),
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
        $typesWithPlaceholders = ['upsell', 'checkout_upgrade_card', 'progress_bar', 'content_icon_features', 'content_banner', 'content_rich_text', 'content_button', 'content_product_card', 'post_purchase_funnel'];
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
                Forms\Components\Section::make('Design')
                    ->schema([
                        Forms\Components\Select::make('upgrade_card_title_size')
                            ->label('Headline size')
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
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\Repeater::make('upgrade_mappings_items')
                    ->label('Upgrade mappings')
                    ->schema([
                        Forms\Components\Section::make('Match (when to show this upgrade)')
                            ->schema([
                                Forms\Components\TextInput::make('match_product_id')
                                    ->label('Product ID (optional)')
                                    ->placeholder('GID or numeric'),
                                Forms\Components\TextInput::make('match_variant_id')
                                    ->label('Variant ID (optional)')
                                    ->placeholder('GID or numeric'),
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
                                    ->default([]),
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
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                        Forms\Components\Repeater::make('plans')
                            ->label('Plans (optional, for subscription)')
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
                    ->addActionLabel('Add mapping'),
                Forms\Components\Repeater::make('upgrade_card_plans')
                    ->label('Card plans dropdown (optional)')
                    ->helperText('Options shown in the card dropdown (id + label).')
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
                    ->addActionLabel('Add plan option'),
            ])
            ->columns(2)
            ->visible(fn (Get $get): bool => $get('surface') === 'checkout' && $get('type') === 'checkout_upgrade_card');
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
