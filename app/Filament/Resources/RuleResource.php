<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RuleResource\Pages;
use App\Models\Rule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RuleResource extends Resource
{
    protected static ?string $model = Rule::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Upsell';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Managed from Widgets (Blocks)
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('shop_id')
                    ->relationship('shop', 'shop_domain')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('rule_match_type')
                    ->label('Match type')
                    ->options([
                        'and' => 'All conditions (AND)',
                        'or' => 'Any condition (OR)',
                    ])
                    ->default('and')
                    ->required(),
                Forms\Components\Repeater::make('rule_conditions')
                    ->label('Conditions')
                    ->defaultItems(1)
                    ->schema([
                        Forms\Components\Select::make('field')
                            ->options([
                                'subtotal_gte' => 'Subtotal >=',
                                'subtotal_lte' => 'Subtotal <=',
                                'line_items_has_product_id' => 'Cart has product ID',
                                'line_items_has_any_product_id' => 'Cart has any product IDs (comma separated)',
                                'line_items_has_variant_id' => 'Cart has variant ID',
                                'line_items_has_any_variant_id' => 'Cart has any variant IDs (comma separated)',
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
                Forms\Components\Hidden::make('conditions'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('conditions')
                    ->label('Conditions')
                    ->formatStateUsing(function ($state): string {
                        $conditions = is_array($state) ? $state : [];
                        $type = isset($conditions['or']) ? 'OR' : 'AND';
                        $items = (array) ($conditions[strtolower($type)] ?? []);

                        return count($items) . " ({$type})";
                    }),
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
            'index' => Pages\ListRules::route('/'),
            'create' => Pages\CreateRule::route('/create'),
            'edit' => Pages\EditRule::route('/{record}/edit'),
        ];
    }
}
