<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ThankYouBlockResource\Pages;
use App\Models\ThankYouBlock;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ThankYouBlockResource extends Resource
{
    protected static ?string $model = ThankYouBlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Thank You';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('shop_id')
                    ->relationship('shop', 'shop_domain')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('type')
                    ->options(array_combine(ThankYouBlock::blockTypes(), ThankYouBlock::blockTypes()))
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('title')
                    ->maxLength(255)
                    ->helperText('Main heading/title of the block.'),
                Forms\Components\Textarea::make('body')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('image_url')
                    ->label('Image URL')
                    ->url()
                    ->maxLength(500),
                Forms\Components\TextInput::make('button_label')
                    ->maxLength(80)
                    ->visible(fn (Get $get): bool => in_array((string) $get('type'), ['banner', 'button', 'product_card'], true)),
                Forms\Components\TextInput::make('button_url')
                    ->label('Button URL')
                    ->maxLength(500)
                    ->visible(fn (Get $get): bool => in_array((string) $get('type'), ['banner', 'button', 'product_card'], true)),

                Forms\Components\Fieldset::make('Product card options')
                    ->visible(fn (Get $get): bool => $get('type') === 'product_card')
                    ->schema([
                        Forms\Components\TextInput::make('product_id')
                            ->label('Product ID / handle')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('price_text')
                            ->label('Price text')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('badge_text')
                            ->label('Badge text')
                            ->maxLength(120),
                        Forms\Components\Toggle::make('show_price')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Fieldset::make('Style')
                    ->schema([
                        Forms\Components\Select::make('text_size')
                            ->options([
                                'small' => 'Small',
                                'medium' => 'Medium',
                                'large' => 'Large',
                            ])
                            ->default('medium'),
                        Forms\Components\Select::make('text_appearance')
                            ->options([
                                'default' => 'Default',
                                'subdued' => 'Subdued',
                            ])
                            ->default('default'),
                        Forms\Components\Toggle::make('title_bold')
                            ->default(true)
                            ->label('Title bold'),
                        Forms\Components\Select::make('button_kind')
                            ->options([
                                'secondary' => 'Secondary',
                                'primary' => 'Primary',
                            ])
                            ->default('secondary'),
                        Forms\Components\Select::make('spacing')
                            ->options([
                                'tight' => 'Tight',
                                'loose' => 'Loose',
                            ])
                            ->default('tight'),
                        Forms\Components\Toggle::make('divider_before')
                            ->default(false),
                        Forms\Components\Toggle::make('divider_after')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\KeyValue::make('advanced_config')
                    ->label('Advanced config (optional)')
                    ->reorderable()
                    ->helperText('Any extra keys will be merged into block config.'),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListThankYouBlocks::route('/'),
            'create' => Pages\CreateThankYouBlock::route('/create'),
            'edit' => Pages\EditThankYouBlock::route('/{record}/edit'),
        ];
    }
}
