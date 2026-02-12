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

    protected static ?string $navigationGroup = 'Widgets';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Checkout experience';

    protected static ?string $pluralModelLabel = 'Checkout experience';

    protected static ?string $title = 'Checkout experience';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Shop')
                ->description('One Checkout experience config per store. Controls quantity and subscription upgrade in Checkout.')
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
                        ->helperText('Uses cart-line-item extension. Not available with Apple Pay / Google Pay.'),
                ]),

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
