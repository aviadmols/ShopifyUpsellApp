<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopResource\Pages;
use App\Filament\Resources\ShopResource\RelationManagers;
use App\Models\Shop;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static ?string $recordTitleAttribute = 'shop_domain';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Shops';

    protected static ?string $navigationLabel = 'Connected shops';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('shop_domain')
                    ->required()
                    ->maxLength(255)
                    ->readOnly()
                    ->helperText('Domain recorded at install (e.g. millsdailypacks-usa.myshopify.com).'),
                Forms\Components\TagsInput::make('alternate_domains')
                    ->label('Alternate domains (same shop)')
                    ->placeholder('millsdailypacks.myshopify.com')
                    ->helperText('If checkout sends a different domain (e.g. millsdailypacks.myshopify.com or millsdailypacks-usa), add it here. One per line.')
                    ->splitKeys(['Tab', ',']),
                Forms\Components\TextInput::make('scope')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('installed_at')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('uninstalled_at')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shop_domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Shop $record): string => $record->uninstalled_at ? 'Uninstalled' : 'Connected')
                    ->color(fn (Shop $record): string => $record->uninstalled_at ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('installed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('offers_count')
                    ->counts('offers')
                    ->label('Offers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rules_count')
                    ->counts('rules')
                    ->label('Rules')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('installed')
                    ->placeholder('All shops')
                    ->trueLabel('Connected only')
                    ->falseLabel('Uninstalled only')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('uninstalled_at'),
                        false: fn (Builder $q) => $q->whereNotNull('uninstalled_at'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('reinstall')
                    ->label('Reinstall')
                    ->url(fn (Shop $record): string => route('auth.install', ['shop' => $record->shop_domain]))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Shop $record): bool => (bool) $record->uninstalled_at),
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
            'index' => Pages\ListShops::route('/'),
            'edit' => Pages\EditShop::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
