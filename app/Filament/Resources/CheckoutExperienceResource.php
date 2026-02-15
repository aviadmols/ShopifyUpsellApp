<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckoutExperienceResource\Pages;
use App\Models\CheckoutExperience;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CheckoutExperienceResource extends Resource
{
    protected static ?string $model = CheckoutExperience::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Checkout';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Checkout experience';

    protected static ?string $modelLabel = 'Checkout experience';

    protected static ?string $pluralModelLabel = 'Checkout experience';

    protected static ?string $title = 'Checkout experience';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Shop')
                ->description('One Checkout experience config per store. Controls quantity and subscription upgrade in Checkout. Use this experience ID in Checkout Editor → Zyg Cart Line → Checkout Experience ID. Leave empty there to use the shop default.')
                ->schema([
                    Forms\Components\Select::make('shop_id')
                        ->relationship('shop', 'shop_domain', fn (Builder $query) => $query->whereNull('uninstalled_at'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->unique(ignoreRecord: true),
                ]),

            Forms\Components\Section::make('Quantity in upsell block')
                ->description('Let customers choose quantity when adding recommended products from the upsell block.')
                ->schema([
                    Forms\Components\Toggle::make('quantity_in_upsell_enabled')
                        ->label('Enable quantity selector')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('quantity_default')
                        ->label('Default quantity')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(1)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_upsell_enabled')),
                    Forms\Components\TextInput::make('quantity_min')
                        ->label('Minimum quantity')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(1)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_upsell_enabled')),
                    Forms\Components\TextInput::make('quantity_max')
                        ->label('Maximum quantity')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(10)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_upsell_enabled')),
                ])
                ->columns(2),

            Forms\Components\Section::make('Quantity on cart lines')
                ->description('Show +/- controls next to each line in the order summary so customers can change quantity.')
                ->schema([
                    Forms\Components\Toggle::make('quantity_in_cart_enabled')
                        ->label('Enable quantity editor on cart lines')
                        ->default(false)
                        ->live()
                        ->helperText('Uses cart-line-item extension. Not available with Apple Pay / Google Pay.'),
                    Forms\Components\Select::make('cart_line_modify_alignment')
                        ->label('Modify link alignment')
                        ->options([
                            'left' => 'Left',
                            'center' => 'Center',
                            'right' => 'Right',
                        ])
                        ->default('left')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\Toggle::make('cart_line_show_chevron')
                        ->label('Show chevron next to Modify (down when closed, up when open)')
                        ->default(true)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\Select::make('cart_line_quantity_size')
                        ->label('Quantity display size in popup')
                        ->options([
                            'small' => 'Small',
                            'medium' => 'Medium',
                            'large' => 'Large',
                        ])
                        ->default('medium')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                ])
                ->columns(2),

            Forms\Components\Section::make('Cart line appearance')
                ->description('Popover width and +/- button style (when quantity on cart lines is enabled).')
                ->schema([
                    Forms\Components\Select::make('cart_line_popover_width_mode')
                        ->label('Popover width')
                        ->options(['preset' => 'Preset (S/M/L/XL)', 'custom' => 'Custom (px)'])
                        ->default('preset')
                        ->live()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\Select::make('cart_line_popover_width_preset')
                        ->label('Width preset')
                        ->options(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large'])
                        ->default('md')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && ($get('cart_line_popover_width_mode') === 'preset')),
                    Forms\Components\TextInput::make('cart_line_popover_width_px')
                        ->label('Width (px)')
                        ->numeric()
                        ->minValue(200)
                        ->maxValue(600)
                        ->default(320)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && ($get('cart_line_popover_width_mode') === 'custom')),
                    Forms\Components\Select::make('cart_line_plus_minus_kind')
                        ->label('+/- button kind')
                        ->options(['plain' => 'Plain', 'secondary' => 'Secondary', 'primary' => 'Primary'])
                        ->default('plain')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\Select::make('cart_line_plus_minus_appearance')
                        ->label('+/- button appearance')
                        ->options(['default' => 'Default', 'monochrome' => 'Monochrome', 'critical' => 'Critical'])
                        ->default('monochrome')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\Select::make('cart_line_plus_minus_size')
                        ->label('+/- button size')
                        ->options(['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'])
                        ->default('small')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\Select::make('cart_line_plus_minus_corner_radius')
                        ->label('+/- button corner radius')
                        ->options(['none' => 'None', 'small' => 'Small', 'base' => 'Base', 'large' => 'Large', 'fullyRounded' => 'Fully rounded'])
                        ->default('base')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Cart line rules')
                ->description('Limit quantity editor and subscription upgrade by products, collections, tags, cart conditions. Leave "Rule mode" at All to show for every line.')
                ->schema([
                    Forms\Components\Select::make('quantity_rule_mode')
                        ->label('Quantity: rule mode')
                        ->options([
                            'all' => 'All lines',
                            'include_only' => 'Include only',
                            'exclude_only' => 'Exclude only',
                            'include_exclude' => 'Include + Exclude',
                        ])
                        ->default('all')
                        ->live()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\TagsInput::make('quantity_include_product_ids')
                        ->label('Quantity: include product IDs (GID or numeric)')
                        ->placeholder('e.g. gid://shopify/Product/123')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_exclude_product_ids')
                        ->label('Quantity: exclude product IDs')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_include_collection_ids')
                        ->label('Quantity: include collection IDs')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_exclude_collection_ids')
                        ->label('Quantity: exclude collection IDs')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_include_tags')
                        ->label('Quantity: include tags')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_exclude_tags')
                        ->label('Quantity: exclude tags')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_include_vendors')
                        ->label('Quantity: include vendors')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_exclude_vendors')
                        ->label('Quantity: exclude vendors')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_include_product_types')
                        ->label('Quantity: include product types')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('quantity_exclude_product_types')
                        ->label('Quantity: exclude product types')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled') && in_array($get('quantity_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\Select::make('quantity_require_subscription_state')
                        ->label('Quantity: subscription state')
                        ->options(['any' => 'Any', 'subscription' => 'Subscription only', 'one_time' => 'One-time only'])
                        ->default('any')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\TextInput::make('quantity_min_subtotal')
                        ->label('Quantity: min cart subtotal')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\TextInput::make('quantity_max_subtotal')
                        ->label('Quantity: max cart subtotal')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\TextInput::make('quantity_min_cart_items')
                        ->label('Quantity: min cart items count')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),
                    Forms\Components\TextInput::make('quantity_max_cart_items')
                        ->label('Quantity: max cart items count')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('quantity_in_cart_enabled')),

                    Forms\Components\Select::make('subscription_rule_mode')
                        ->label('Subscription upgrade: rule mode')
                        ->options([
                            'all' => 'All lines',
                            'include_only' => 'Include only',
                            'exclude_only' => 'Exclude only',
                            'include_exclude' => 'Include + Exclude',
                        ])
                        ->default('all')
                        ->live()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled')),
                    Forms\Components\TagsInput::make('subscription_include_product_ids')
                        ->label('Subscription: include product IDs')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_exclude_product_ids')
                        ->label('Subscription: exclude product IDs')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_include_collection_ids')
                        ->label('Subscription: include collection IDs')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_exclude_collection_ids')
                        ->label('Subscription: exclude collection IDs')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_include_tags')
                        ->label('Subscription: include tags')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_exclude_tags')
                        ->label('Subscription: exclude tags')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_include_vendors')
                        ->label('Subscription: include vendors')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_exclude_vendors')
                        ->label('Subscription: exclude vendors')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_include_product_types')
                        ->label('Subscription: include product types')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['include_only', 'include_exclude'], true)),
                    Forms\Components\TagsInput::make('subscription_exclude_product_types')
                        ->label('Subscription: exclude product types')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled') && in_array($get('subscription_rule_mode'), ['exclude_only', 'include_exclude'], true)),
                    Forms\Components\Select::make('subscription_require_subscription_state')
                        ->label('Subscription: show for')
                        ->options(['any' => 'Any', 'subscription' => 'Subscription only', 'one_time' => 'One-time only'])
                        ->default('one_time')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled')),
                    Forms\Components\TextInput::make('subscription_min_subtotal')
                        ->label('Subscription: min cart subtotal')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled')),
                    Forms\Components\TextInput::make('subscription_max_subtotal')
                        ->label('Subscription: max cart subtotal')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled')),
                    Forms\Components\TextInput::make('subscription_min_cart_items')
                        ->label('Subscription: min cart items count')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled')),
                    Forms\Components\TextInput::make('subscription_max_cart_items')
                        ->label('Subscription: max cart items count')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled')),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Subscription upgrade')
                ->description('Show a message to upgrade one-time items to subscription (Recharge / Shopify Selling Plans).')
                ->schema([
                    Forms\Components\Toggle::make('subscription_upgrade_enabled')
                        ->label('Show "Upgrade to subscription" on eligible lines')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('subscription_upgrade_headline')
                        ->label('Headline (optional)')
                        ->maxLength(120)
                        ->placeholder('e.g. Subscribe & save')
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled')),
                    Forms\Components\TextInput::make('subscription_upgrade_cta')
                        ->label('Button / link text')
                        ->maxLength(80)
                        ->default('Upgrade to subscription')
                        ->required(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled'))
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('subscription_upgrade_enabled')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->label('Shop')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('quantity_in_upsell_enabled')
                    ->label('Qty upsell')
                    ->boolean(),
                Tables\Columns\IconColumn::make('quantity_in_cart_enabled')
                    ->label('Qty cart')
                    ->boolean(),
                Tables\Columns\IconColumn::make('subscription_upgrade_enabled')
                    ->label('Sub. upgrade')
                    ->boolean(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCheckoutExperiences::route('/'),
            'create' => Pages\CreateCheckoutExperience::route('/create'),
            'edit' => Pages\EditCheckoutExperience::route('/{record}/edit'),
        ];
    }
}
