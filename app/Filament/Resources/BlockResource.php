<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockResource\Pages;
use App\Models\Block;
use App\Models\Placement;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BlockResource extends Resource
{
    protected static ?string $model = Block::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Blocks';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Block';

    public static function form(Form $form): Form
    {
        $contentTypes = [
            'content_icon_features' => 'Icon features (icon + title + description)',
            'content_banner' => 'Banner (image + text + button)',
            'content_rich_text' => 'Rich text',
            'content_button' => 'Button / CTA',
            'content_product_card' => 'Product card',
        ];
        $checkoutTypes = ['upsell' => 'Upsell (offers)', 'progress_bar' => 'Progress bar'] + $contentTypes;
        $thankYouTypes = $contentTypes;
        $postPurchaseTypes = ['post_purchase_funnel' => 'Post-purchase funnel'];

        return $form
            ->schema([
                Forms\Components\Section::make('Block identity')
                    ->schema([
                        Forms\Components\Select::make('shop_id')
                            ->relationship('shop', 'shop_domain', fn (Builder $q) => $q->whereNull('uninstalled_at'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('surface')
                            ->options(array_combine(Block::surfaces(), Block::surfaces()))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('type', '')),
                        Forms\Components\Select::make('type')
                            ->options(function (Get $get) use ($checkoutTypes, $thankYouTypes, $postPurchaseTypes) {
                                $surface = $get('surface');
                                if ($surface === 'checkout') {
                                    return $checkoutTypes;
                                }
                                if ($surface === 'thank_you') {
                                    return $thankYouTypes;
                                }
                                if ($surface === 'post_purchase') {
                                    return $postPurchaseTypes;
                                }

                                return [];
                            })
                            ->required(fn (Get $get): bool => ! empty($get('surface')))
                            ->live(),
                        Forms\Components\TextInput::make('name')
                            ->label('Admin label')
                            ->placeholder('e.g. Checkout upsell 1')
                            ->maxLength(255),
                        Forms\Components\Select::make('rule_id')
                            ->relationship(
                                'rule',
                                'name',
                                fn (Builder $q, Get $get) => $get('shop_id')
                                    ? $q->where('shop_id', $get('shop_id'))
                                    : $q->whereRaw('1 = 0')
                            )
                            ->searchable()
                            ->helperText('Optional: show this block only when rule conditions match.'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                self::schemaCheckoutUpsell($form),
                self::schemaCheckoutProgressBar($form),
                self::schemaContentIconFeatures($form),
                self::schemaContentBannerRichTextButton($form),
                self::schemaContentProductCard($form),
                self::schemaPostPurchaseFunnel($form),

                Forms\Components\KeyValue::make('extra_config')
                    ->label('Extra config (optional)')
                    ->reorderable()
                    ->helperText('Merged into block config for advanced use.'),
            ]);
    }

    protected static function schemaCheckoutUpsell(Form $form): Forms\Components\Section
    {
        return Forms\Components\Section::make('Upsell block (Checkout)')
            ->description('Offers and display. Each offer can have its own rule in Offer resource.')
            ->schema([
                Forms\Components\TextInput::make('offer_ids_csv')
                    ->label('Offer IDs (comma separated)')
                    ->placeholder('1,2,3')
                    ->required(),
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
                    ->label('Offer IDs (order = funnel steps)')
                    ->placeholder('1,2,3')
                    ->required(),
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
                    ->options(array_combine(Block::surfaces(), Block::surfaces())),
                Tables\Filters\SelectFilter::make('type')
                    ->options(array_combine(Block::types(), Block::types())),
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
