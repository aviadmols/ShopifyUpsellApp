<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Models\Offer;
use App\Models\Shop;
use App\Services\ShopifyGraphQLService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Upsell';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Step 1: Product selection')
                    ->description('Pick one or multiple variants directly from Shopify. If Shopify is unavailable, manual variant ID still works.')
                    ->schema([
                        Forms\Components\Select::make('shop_id')
                            ->relationship('shop', 'shop_domain', fn (Builder $query) => $query->whereNull('uninstalled_at'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('selected_variant_ids')
                            ->label('Variants from Shopify')
                            ->multiple(fn (string $operation): bool => $operation === 'create')
                            ->searchable()
                            ->helperText('Requires app scope: read_products. On create: select one or more; on edit: select one. If the list is empty, check that the shop is connected and has granted product read access.')
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => self::variantOptions($get('shop_id'), $search))
                            ->getOptionLabelsUsing(fn ($values, Get $get): array => self::variantLabels($get('shop_id'), (array) ($values ?? []))),
                        Forms\Components\Textarea::make('variant_ids_manual')
                            ->label('Variant IDs (manual / open field)')
                            ->placeholder('48072914534655'."\n".'gid://shopify/ProductVariant/48072914534655')
                            ->helperText('One variant ID per line or comma-separated. Use when the dropdown is empty or to paste IDs from Shopify. On edit, the first ID is used.')
                            ->columnSpanFull()
                            ->rows(3),
                        Forms\Components\TextInput::make('product_variant_id')
                            ->label('Single variant ID (legacy fallback)')
                            ->maxLength(255)
                            ->helperText('Single ID or GID. Prefer the dropdown or the manual field above.')
                            ->visible(fn (Get $get): bool => empty(array_filter((array) ($get('selected_variant_ids') ?? []))) && empty(trim((string) ($get('variant_ids_manual') ?? ''))))
                            ->required(fn (Get $get): bool => empty(array_filter((array) ($get('selected_variant_ids') ?? []))) && empty(trim((string) ($get('variant_ids_manual') ?? '')))),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->default('Upsell offer')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('discount_type')
                            ->options(array_combine(Offer::discountTypes(), Offer::discountTypes()))
                            ->required()
                            ->default('none')
                            ->live(),
                        Forms\Components\TextInput::make('discount_value')
                            ->numeric()
                            ->required(fn (Get $get): bool => in_array($get('discount_type'), ['percentage', 'fixed'], true)),
                        Forms\Components\TextInput::make('image_url')
                            ->url()
                            ->maxLength(500)
                            ->label('Image URL'),

                        Forms\Components\Fieldset::make('Recharge / Subscription')
                            ->schema([
                                Forms\Components\Select::make('offer_type')
                                    ->options([
                                        'one_time' => 'One-time only',
                                        'subscription' => 'Subscription only (Recharge)',
                                        'both' => 'One-time and subscription',
                                    ])
                                    ->default('one_time')
                                    ->live()
                                    ->label('Offer type'),
                                Forms\Components\TextInput::make('selling_plan_id')
                                    ->label('Selling plan ID (Shopify GID)')
                                    ->placeholder('gid://shopify/SellingPlan/...')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => in_array($get('offer_type'), ['subscription', 'both'], true))
                                    ->helperText('Optional. From Shopify Admin → Products → Subscriptions / Recharge.'),
                                Forms\Components\TextInput::make('recharge_subscription_variant_id')
                                    ->label('Recharge subscription variant ID')
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => in_array($get('offer_type'), ['subscription', 'both'], true)),
                                Forms\Components\Toggle::make('allow_subscription_in_post_purchase')
                                    ->label('Allow adding as subscription in post-purchase')
                                    ->default(false)
                                    ->visible(fn (Get $get): bool => in_array($get('offer_type'), ['subscription', 'both'], true)),
                            ])
                            ->columns(2),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Step 2: Where to show this offer')
                    ->description('Pick placement types and configure each placement with the maximum available options.')
                    ->schema([
                        Forms\Components\CheckboxList::make('placement_types')
                            ->label('Placements')
                            ->options([
                                'checkout' => 'Checkout',
                                'post_purchase' => 'Post-purchase',
                                'thank_you' => 'Thank you / Order status',
                            ])
                            ->default(['checkout'])
                            ->columns(1)
                            ->live()
                            ->required(),

                        Forms\Components\Fieldset::make('Checkout options')
                            ->visible(fn (Get $get): bool => in_array('checkout', (array) ($get('placement_types') ?? []), true))
                            ->schema([
                                Forms\Components\TextInput::make('checkout_max_offers')
                                    ->numeric()
                                    ->default(3)
                                    ->minValue(1)
                                    ->label('Max offers'),
                                Forms\Components\TextInput::make('checkout_priority')
                                    ->numeric()
                                    ->default(100)
                                    ->label('Priority'),
                                Forms\Components\Select::make('checkout_display_mode')
                                    ->options([
                                        'stacked' => 'Stacked cards',
                                        'single' => 'Single card',
                                    ])
                                    ->default('stacked')
                                    ->label('Display mode'),
                                Forms\Components\Toggle::make('checkout_require_expanded')
                                    ->label('Render only when block is expanded')
                                    ->default(false),
                            ])
                            ->columns(2),

                        Forms\Components\Fieldset::make('Post-purchase options')
                            ->visible(fn (Get $get): bool => in_array('post_purchase', (array) ($get('placement_types') ?? []), true))
                            ->schema([
                                Forms\Components\TextInput::make('post_purchase_max_offers')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->label('Max offers'),
                                Forms\Components\TextInput::make('post_purchase_cooldown_hours')
                                    ->numeric()
                                    ->default(24)
                                    ->minValue(0)
                                    ->label('Cooldown hours'),
                                Forms\Components\Toggle::make('post_purchase_allow_reoffer')
                                    ->default(false)
                                    ->label('Allow re-offer after cooldown'),
                                Forms\Components\Toggle::make('post_purchase_show_timer')
                                    ->default(false)
                                    ->label('Show countdown timer'),
                                Forms\Components\TextInput::make('post_purchase_timer_seconds')
                                    ->numeric()
                                    ->default(300)
                                    ->minValue(0)
                                    ->label('Timer seconds'),
                            ])
                            ->columns(2),

                        Forms\Components\Fieldset::make('Thank-you options')
                            ->visible(fn (Get $get): bool => in_array('thank_you', (array) ($get('placement_types') ?? []), true))
                            ->schema([
                                Forms\Components\Toggle::make('thank_you_enable_product_card')
                                    ->default(true)
                                    ->label('Create product card block'),
                                Forms\Components\TextInput::make('thank_you_title')
                                    ->default('Recommended for you'),
                                Forms\Components\Textarea::make('thank_you_body')
                                    ->rows(3),
                                Forms\Components\TextInput::make('thank_you_button_url')
                                    ->label('Button URL')
                                    ->maxLength(500),
                                Forms\Components\TextInput::make('thank_you_sort_order')
                                    ->numeric()
                                    ->default(100),
                            ])
                            ->columns(1),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Step 3: Rule conditions (optional)')
                    ->description('Define optional eligibility conditions. Leave off to show to everyone.')
                    ->schema([
                        Forms\Components\Toggle::make('rule_enabled')
                            ->label('Enable conditions')
                            ->default(false)
                            ->live(),
                        Forms\Components\TextInput::make('rule_name')
                            ->visible(fn (Get $get): bool => (bool) $get('rule_enabled'))
                            ->maxLength(255)
                            ->default('Offer rule'),
                        Forms\Components\Select::make('rule_match_type')
                            ->visible(fn (Get $get): bool => (bool) $get('rule_enabled'))
                            ->options([
                                'and' => 'All conditions (AND)',
                                'or' => 'Any condition (OR)',
                            ])
                            ->default('and'),
                        Forms\Components\Repeater::make('rule_conditions')
                            ->visible(fn (Get $get): bool => (bool) $get('rule_enabled'))
                            ->defaultItems(1)
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->options([
                                        'subtotal_gte' => 'Subtotal >=',
                                        'subtotal_lte' => 'Subtotal <=',
                                        'line_items_has_product_id' => 'Cart has product ID',
                                        'line_items_has_any_product_id' => 'Cart has any product IDs (comma separated)',
                                        'customer_has_tag' => 'Customer has tag',
                                        'shipping_country_in' => 'Shipping country in (comma separated ISO codes)',
                                        'utm_param_equals' => 'UTM param equals (param_name,value)',
                                        'utm_param_contains' => 'UTM param contains (param_name,substring)',
                                        'url_param_equals' => 'URL param equals (param_name,value)',
                                        'url_param_contains' => 'URL param contains (param_name,substring)',
                                        'line_item_property_equals' => 'Line item has property (key,value)',
                                        'line_item_property_exists' => 'Line item has property key',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('value')
                                    ->required()
                                    ->maxLength(1000)
                                    ->placeholder('e.g. utm_source,google or _my_prop,value'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('rule.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product_variant_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('offer_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state ?? 'one_time') {
                        'subscription' => 'Subscription',
                        'both' => 'One-time + Subscription',
                        default => 'One-time',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('discount_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_value')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('image_url')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->relationship('shop', 'shop_domain')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function variantOptions(?int $shopId, string $search): array
    {
        $shop = $shopId ? Shop::whereNull('uninstalled_at')->find($shopId) : null;
        if (! $shop) {
            return [];
        }

        try {
            if (! $shop->access_token) {
                return [];
            }
        } catch (DecryptException $e) {
            Log::warning('OfferResource: Shop access_token decrypt failed (wrong key or corrupted)', ['shop_id' => $shopId]);

            return [];
        }

        try {
            $items = app(ShopifyGraphQLService::class)->searchProductVariants($shop, $search, 25);
        } catch (\Throwable $e) {
            Log::warning('OfferResource: Shopify variant search failed', ['shop_id' => $shopId, 'message' => $e->getMessage()]);

            return [];
        }

        $results = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            $label = (string) ($item['label'] ?? '');
            if ($id !== '' && $label !== '') {
                $results[$id] = $label;
            }
        }

        return $results;
    }

    /**
     * @param  array<int, string|array>  $values  May contain nested arrays when Select receives array state.
     * @return array<string, string>
     */
    public static function variantLabels(?int $shopId, array $values): array
    {
        $values = self::normalizeVariantIdsToScalars($values);
        $labels = [];
        foreach ($values as $value) {
            $labels[(string) $value] = (string) $value;
        }

        if (empty($values)) {
            return $labels;
        }

        $shop = $shopId ? Shop::whereNull('uninstalled_at')->find($shopId) : null;
        if (! $shop) {
            return $labels;
        }
        try {
            if (! $shop->access_token) {
                return $labels;
            }
        } catch (DecryptException $e) {
            Log::warning('OfferResource: Shop access_token decrypt failed (wrong key or corrupted)', ['shop_id' => $shopId]);

            return $labels;
        }

        $service = app(ShopifyGraphQLService::class);
        foreach ($values as $gid) {
            $gid = (string) $gid;
            try {
                $variant = $service->getProductVariant($shop, $gid);
            } catch (\Throwable) {
                continue;
            }

            if (! $variant) {
                continue;
            }

            $productTitle = (string) ($variant['product']['title'] ?? 'Product');
            $variantTitle = (string) ($variant['title'] ?? 'Variant');
            $price = (string) ($variant['price'] ?? '');

            $label = $productTitle;
            if (strtolower($variantTitle) !== 'default title') {
                $label .= " - {$variantTitle}";
            }
            if ($price !== '') {
                $label .= ' (' . $price . ')';
            }
            $labels[$gid] = $label;
        }

        return $labels;
    }

    /**
     * Flatten variant id list so Filament Select never receives array keys (array_key_exists requires string|int).
     *
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    public static function normalizeVariantIdsToScalars(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            if (is_array($v)) {
                $v = array_values($v)[0] ?? null;
            }
            if ($v !== null && $v !== '' && (is_string($v) || is_numeric($v))) {
                $out[] = (string) $v;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Parse variant IDs from a string (newline or comma separated). Returns non-empty trimmed IDs.
     *
     * @return array<int, string>
     */
    public static function parseVariantIdsFromString(?string $input): array
    {
        if (trim((string) $input) === '') {
            return [];
        }
        $ids = preg_split('/[\s,]+/', (string) $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = array_map('trim', $ids);
        $ids = array_values(array_filter($ids, fn (string $id): bool => $id !== ''));

        return $ids;
    }
}
