<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WidgetSessionEventResource\Pages;
use App\Models\WidgetSessionEvent;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WidgetSessionEventResource extends Resource
{
    protected static ?string $model = WidgetSessionEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Widget session logs';

    protected static ?string $modelLabel = 'Widget session event';

    protected static ?string $pluralModelLabel = 'Widget session logs';

    protected static ?string $navigationGroup = 'Logs';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                \Filament\Forms\Components\Section::make('Event')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('created_at')
                            ->label('Time')
                            ->content(fn ($state): string => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : '-'),
                        \Filament\Forms\Components\Placeholder::make('shop_domain')
                            ->label('Shop')
                            ->content(fn ($state): string => (string) ($state ?? '-')),
                        \Filament\Forms\Components\Placeholder::make('block_id')
                            ->content(fn ($state): string => $state !== null ? (string) $state : '-'),
                        \Filament\Forms\Components\Placeholder::make('session_key')
                            ->content(fn ($state): string => $state ? (strlen($state) > 24 ? substr($state, 0, 12) . '…' . substr($state, -8) : $state) : '-'),
                        \Filament\Forms\Components\Placeholder::make('event_type')
                            ->content(fn ($state): string => (string) ($state ?? '-')),
                        \Filament\Forms\Components\Placeholder::make('rule_passed')
                            ->content(fn ($state): string => $state === null ? '-' : ($state ? 'Yes' : 'No')),
                        \Filament\Forms\Components\Placeholder::make('widget_shown')
                            ->content(fn ($state): string => $state === null ? '-' : ($state ? 'Yes' : 'No')),
                        \Filament\Forms\Components\Placeholder::make('click_target')
                            ->content(fn ($state): string => (string) ($state ?? '-')),
                    ])
                    ->columns(2),
                \Filament\Forms\Components\Section::make('Context received from checkout')
                    ->schema([
                        \Filament\Forms\Components\Textarea::make('context_snapshot')
                            ->label('')
                            ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $state)
                            ->disabled()
                            ->rows(15),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->searchable(false),
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->label('Shop')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('block_id')
                    ->label('Block ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('session_key')
                    ->label('Session')
                    ->formatStateUsing(fn (?string $state): string => $state && strlen($state) > 20 ? substr($state, 0, 10) . '…' . substr($state, -6) : ($state ?? '-'))
                    ->tooltip(fn ($record) => $record->session_key),
                Tables\Columns\TextColumn::make('event_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'view' => 'gray',
                        'click' => 'success',
                        default => 'primary',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('rule_passed')
                    ->label('Rule passed')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\IconColumn::make('widget_shown')
                    ->label('Shown')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('click_target')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('shop_id')
                    ->relationship('shop', 'shop_domain')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('event_type')
                    ->options([
                        'view' => 'View',
                        'click' => 'Click',
                    ]),
                Tables\Filters\SelectFilter::make('widget_shown')
                    ->options([
                        true => 'Shown',
                        false => 'Not shown',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWidgetSessionEvents::route('/'),
            'view' => Pages\ViewWidgetSessionEvent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
