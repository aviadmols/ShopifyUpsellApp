<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlacementResource\Pages;
use App\Models\Placement;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlacementResource extends Resource
{
    protected static ?string $model = Placement::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Upsell';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('shop_id')
                    ->relationship('shop', 'shop_domain')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('placement_type')
                    ->options(array_combine(Placement::placementTypes(), Placement::placementTypes()))
                    ->required()
                    ->live(),

                Forms\Components\Fieldset::make('Checkout config')
                    ->description('Offers and layout. Display mode is controlled here (not from Offer form).')
                    ->visible(fn (Get $get): bool => $get('placement_type') === 'checkout')
                    ->schema([
                        Forms\Components\TextInput::make('offer_ids_csv')
                            ->label('Offer IDs (comma separated)')
                            ->helperText('Example: 1,2,3')
                            ->required(),
                        Forms\Components\TextInput::make('max_offers')
                            ->numeric()
                            ->default(3)
                            ->required(),
                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(100),
                        Forms\Components\Select::make('display_mode')
                            ->options([
                                'stacked' => 'Stacked cards',
                                'single' => 'Single card',
                            ])
                            ->default('stacked'),
                        Forms\Components\Toggle::make('require_expanded')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Fieldset::make('Checkout UI')
                    ->description('How the block looks in checkout (Shopify Checkout UI options).')
                    ->visible(fn (Get $get): bool => $get('placement_type') === 'checkout')
                    ->schema([
                        Forms\Components\TextInput::make('section_heading')
                            ->label('Section heading')
                            ->default('Add to your order')
                            ->maxLength(100),
                        Forms\Components\Select::make('title_size')
                            ->label('Title text size')
                            ->options([
                                'small' => 'Small',
                                'medium' => 'Medium',
                                'large' => 'Large',
                                'extraLarge' => 'Extra large',
                            ])
                            ->default('medium'),
                        Forms\Components\Select::make('title_appearance')
                            ->label('Title appearance')
                            ->options([
                                'default' => 'Default',
                                'accent' => 'Accent',
                                'subdued' => 'Subdued',
                                'info' => 'Info',
                                'success' => 'Success',
                                'warning' => 'Warning',
                                'critical' => 'Critical',
                            ])
                            ->default('default'),
                        Forms\Components\Toggle::make('show_price')
                            ->label('Show price')
                            ->default(true),
                        Forms\Components\Toggle::make('show_description')
                            ->label('Show description')
                            ->default(true),
                        Forms\Components\Select::make('image_aspect_ratio')
                            ->label('Image aspect ratio')
                            ->options([
                                '' => 'Auto',
                                '1' => '1:1',
                                '1.25' => '5:4',
                                '1.5' => '3:2',
                                '0.75' => '4:3',
                            ])
                            ->default(''),
                        Forms\Components\Select::make('image_fit')
                            ->label('Image fit')
                            ->options([
                                'cover' => 'Cover',
                                'contain' => 'Contain',
                                'fill' => 'Fill',
                            ])
                            ->default('cover'),
                        Forms\Components\Select::make('image_corner_radius')
                            ->label('Image corner radius')
                            ->options([
                                'none' => 'None',
                                'small' => 'Small',
                                'base' => 'Base',
                                'large' => 'Large',
                            ])
                            ->default('base'),
                        Forms\Components\Select::make('button_kind')
                            ->label('Button kind')
                            ->options([
                                'primary' => 'Primary',
                                'secondary' => 'Secondary',
                                'plain' => 'Plain',
                            ])
                            ->default('secondary'),
                        Forms\Components\Select::make('button_appearance')
                            ->label('Button appearance')
                            ->options([
                                'default' => 'Default',
                                'monochrome' => 'Monochrome',
                                'critical' => 'Critical',
                            ])
                            ->default('default'),
                        Forms\Components\Select::make('card_spacing')
                            ->label('Card spacing')
                            ->options([
                                'tight' => 'Tight',
                                'loose' => 'Loose',
                                'extraLoose' => 'Extra loose',
                            ])
                            ->default('loose'),
                        Forms\Components\Toggle::make('divider_between_cards')
                            ->label('Divider between cards')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Fieldset::make('Progress bar (checkout)')
                    ->description('Show a progress bar toward a goal (e.g. free shipping or discount). Uses cart subtotal. Configure the actual discount/shipping in Shopify (Discounts / Shipping).')
                    ->visible(fn (Get $get): bool => $get('placement_type') === 'checkout')
                    ->schema([
                        Forms\Components\Toggle::make('progress_bar_enabled')
                            ->label('Enable progress bar')
                            ->default(false),
                        Forms\Components\Select::make('progress_bar_type')
                            ->label('Goal type')
                            ->options([
                                'free_shipping' => 'Free shipping',
                                'discount' => 'Discount (e.g. % off)',
                            ])
                            ->default('free_shipping')
                            ->visible(fn (Get $get): bool => (bool) $get('progress_bar_enabled')),
                        Forms\Components\TextInput::make('progress_bar_goal')
                            ->label('Goal amount')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->suffix('(store currency)')
                            ->helperText('Subtotal must reach this to unlock the reward.')
                            ->required()
                            ->visible(fn (Get $get): bool => (bool) $get('progress_bar_enabled')),
                        Forms\Components\TextInput::make('progress_bar_message_below')
                            ->label('Message when below goal')
                            ->placeholder("You're {amount} away from free shipping!")
                            ->helperText('Placeholders: {amount} = remaining, {goal} = goal amount.')
                            ->maxLength(200)
                            ->visible(fn (Get $get): bool => (bool) $get('progress_bar_enabled')),
                        Forms\Components\TextInput::make('progress_bar_message_achieved')
                            ->label('Message when goal reached')
                            ->placeholder("You've unlocked free shipping!")
                            ->maxLength(200)
                            ->visible(fn (Get $get): bool => (bool) $get('progress_bar_enabled')),
                        Forms\Components\Select::make('progress_bar_discount_type')
                            ->label('Discount type')
                            ->options([
                                'percentage' => 'Percentage off',
                                'fixed' => 'Fixed amount off',
                            ])
                            ->default('percentage')
                            ->visible(fn (Get $get): bool => (bool) $get('progress_bar_enabled') && $get('progress_bar_type') === 'discount'),
                        Forms\Components\TextInput::make('progress_bar_discount_value')
                            ->label('Discount value')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Percentage (e.g. 10) or fixed amount in store currency.')
                            ->visible(fn (Get $get): bool => (bool) $get('progress_bar_enabled') && $get('progress_bar_type') === 'discount'),
                    ])
                    ->columns(2),

                Forms\Components\Fieldset::make('Post-purchase funnel')
                    ->description('Multiple offers in sequence. Each step shows one offer; "Decline" moves to the next.')
                    ->visible(fn (Get $get): bool => $get('placement_type') === 'post_purchase')
                    ->schema([
                        Forms\Components\TextInput::make('offer_ids_csv')
                            ->label('Offer IDs (order = funnel steps)')
                            ->helperText('Example: 1,2,3 — first eligible offer is step 1, then 2, then 3.')
                            ->required(),
                        Forms\Components\TextInput::make('max_offers')
                            ->label('Max offers in funnel')
                            ->numeric()
                            ->minValue(1)
                            ->default(3)
                            ->helperText('How many offers to show one after another (1 = single offer).'),
                        Forms\Components\TextInput::make('cooldown_hours')
                            ->label('Cooldown (hours)')
                            ->numeric()
                            ->minValue(0)
                            ->default(24),
                        Forms\Components\Toggle::make('allow_reoffer')
                            ->label('Allow same customer to see offer again later')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Fieldset::make('Post-purchase UI (funnel look & feel)')
                    ->visible(fn (Get $get): bool => $get('placement_type') === 'post_purchase')
                    ->schema([
                        Forms\Components\TextInput::make('funnel_headline_template')
                            ->label('Headline template')
                            ->placeholder('{first_name}, before you go!')
                            ->helperText('Placeholders: {first_name}, {order_id}.')
                            ->maxLength(120),
                        Forms\Components\Toggle::make('funnel_show_progress')
                            ->label('Show progress steps (e.g. Order → Offer → Done)')
                            ->default(true),
                        Forms\Components\TextInput::make('funnel_step_labels')
                            ->label('Progress step labels')
                            ->placeholder('Order, Offer, Bonus, Done')
                            ->helperText('Comma-separated; will be truncated to number of offers + 1.')
                            ->visible(fn (Get $get): bool => (bool) $get('funnel_show_progress')),
                        Forms\Components\Toggle::make('show_timer')
                            ->label('Show countdown timer')
                            ->default(false),
                        Forms\Components\TextInput::make('timer_seconds')
                            ->label('Timer duration (seconds)')
                            ->numeric()
                            ->minValue(0)
                            ->default(300)
                            ->visible(fn (Get $get): bool => (bool) $get('show_timer')),
                        Forms\Components\TextInput::make('timer_label')
                            ->label('Timer label')
                            ->placeholder('For a limited time')
                            ->maxLength(80)
                            ->visible(fn (Get $get): bool => (bool) $get('show_timer')),
                        Forms\Components\Textarea::make('urgency_message')
                            ->label('Urgency message')
                            ->placeholder("Don't miss out on this offer... it expires after you leave this page!")
                            ->rows(2)
                            ->maxLength(300),
                        Forms\Components\TextInput::make('cta_text')
                            ->label('CTA button text')
                            ->placeholder('Pay Now')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('decline_text')
                            ->label('Decline link text')
                            ->placeholder('Decline offer')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('quantity_default')
                            ->label('Default quantity')
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                        Forms\Components\TextInput::make('quantity_min')
                            ->label('Min quantity')
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                        Forms\Components\TextInput::make('quantity_max')
                            ->label('Max quantity')
                            ->numeric()
                            ->minValue(1)
                            ->default(10),
                    ])
                    ->columns(2),

                Forms\Components\Fieldset::make('Thank you config')
                    ->visible(fn (Get $get): bool => $get('placement_type') === 'thank_you')
                    ->schema([
                        Forms\Components\TextInput::make('block_ids_csv')
                            ->label('Block IDs (comma separated)')
                            ->helperText('Example: 5,8,12'),
                    ]),

                Forms\Components\KeyValue::make('extra_config')
                    ->label('Additional config (optional)')
                    ->reorderable()
                    ->helperText('Advanced: merged into generated config for this placement.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('placement_type')
                    ->searchable(),
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
            'index' => Pages\ListPlacements::route('/'),
            'create' => Pages\CreatePlacement::route('/create'),
            'edit' => Pages\EditPlacement::route('/{record}/edit'),
        ];
    }
}
