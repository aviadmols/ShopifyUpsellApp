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
                    ->description('API/extension options: offer_ids, max_offers, priority, display_mode (stacked|single), require_expanded. Future: e.g. grid.')
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
                    ]),

                Forms\Components\Fieldset::make('Post-purchase config')
                    ->visible(fn (Get $get): bool => $get('placement_type') === 'post_purchase')
                    ->schema([
                        Forms\Components\TextInput::make('offer_ids_csv')
                            ->label('Offer IDs (comma separated)')
                            ->helperText('Example: 1,2,3')
                            ->required(),
                        Forms\Components\TextInput::make('max_offers')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('cooldown_hours')
                            ->numeric()
                            ->default(24),
                        Forms\Components\Toggle::make('allow_reoffer')
                            ->default(false),
                        Forms\Components\Toggle::make('show_timer')
                            ->default(false),
                        Forms\Components\TextInput::make('timer_seconds')
                            ->numeric()
                            ->default(300),
                    ]),

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
