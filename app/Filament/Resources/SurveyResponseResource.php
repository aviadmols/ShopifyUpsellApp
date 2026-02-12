<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyResponseResource\Pages;
use App\Models\SurveyResponse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SurveyResponseResource extends Resource
{
    protected static ?string $model = SurveyResponse::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Surveys';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('shop_id')
                ->relationship('shop', 'shop_domain')
                ->disabled(),
            Forms\Components\Select::make('survey_id')
                ->relationship('survey', 'name')
                ->disabled(),
            Forms\Components\TextInput::make('surface')->disabled(),
            Forms\Components\TextInput::make('order_id')->disabled(),
            Forms\Components\TextInput::make('checkout_token')->disabled(),
            Forms\Components\TextInput::make('customer_id')->disabled(),
            Forms\Components\Textarea::make('reward_code_shown')->disabled(),
            Forms\Components\KeyValue::make('answers')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('survey.name')
                    ->label('Survey')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('surface')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->relationship('shop', 'shop_domain')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('survey_id')
                    ->relationship('survey', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyResponses::route('/'),
            'view' => Pages\ViewSurveyResponse::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

