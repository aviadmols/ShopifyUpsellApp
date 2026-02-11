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
                                $state = CreateBlock::getStateFromGet($get);
                                $surface = (string) ($state['surface'] ?? '');
                                $type = (string) ($state['type'] ?? '');
                                $config = CreateBlock::buildBlockConfig($state);
                                $html = view('filament.components.block-preview', [
                                    'surface' => $surface,
                                    'type' => $type,
                                    'config' => $config,
                                ])->render();

                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(fn (Get $get): bool => empty($get('surface')) || empty($get('type'))),

                self::schemaCheckoutUpsell($form),
                self::schemaCheckoutProgressBar($form),
                self::schemaContentIconFeatures($form),
                self::schemaContentBannerRichTextButton($form),
                self::schemaContentProductCard($form),
                self::schemaPostPurchaseFunnel($form),

                self::schemaWidgetOffers($form),
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
                    ->options(['stacked' => 'Stacked', 'single' => 'Single card'])
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
            ])
            ->columns(2)
            ->visible(fn (Get $get): bool => $get('surface') === 'checkout' && $get('type') === 'upsell');
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
