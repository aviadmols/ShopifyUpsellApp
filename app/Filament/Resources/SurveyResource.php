<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\RuleBuilder;
use App\Filament\Resources\SurveyResource\Pages;
use App\Models\Survey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Surveys';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Survey')
                ->schema([
                    Forms\Components\Select::make('shop_id')
                        ->relationship('shop', 'shop_domain', fn (Builder $query) => $query->whereNull('uninstalled_at'))
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Checkout feedback'),
                    Forms\Components\Toggle::make('enabled')
                        ->default(true),
                    Forms\Components\CheckboxList::make('surfaces')
                        ->label('Show on')
                        ->options([
                            'checkout' => 'Checkout',
                            'thank_you' => 'Thank you',
                            'post_purchase' => 'Post-purchase',
                        ])
                        ->default(['checkout'])
                        ->columns(1)
                        ->helperText('Choose where this survey can be shown. Post-purchase requires Shopify Plus.')
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Conditions')
                ->description('Use the same rule engine conditions as widgets (subtotal, products in cart, customer tag, UTMs, etc.).')
                ->schema([
                    ...RuleBuilder::schema(),
                    Forms\Components\Hidden::make('conditions'),
                ]),

            Forms\Components\Section::make('Questions')
                ->description('Build your questionnaire. Start simple (single choice / text) and expand later.')
                ->schema([
                    Forms\Components\Repeater::make('questions')
                        ->defaultItems(1)
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->options([
                                    'single_choice' => 'Single choice',
                                    'multi_choice' => 'Multiple choice',
                                    'select' => 'Select dropdown',
                                    'text' => 'Text input',
                                ])
                                ->required()
                                ->default('single_choice')
                                ->live(),
                            Forms\Components\Textarea::make('prompt')
                                ->label('Question')
                                ->rows(2)
                                ->required()
                                ->maxLength(500),
                            Forms\Components\Toggle::make('required')
                                ->default(true),
                            Forms\Components\Repeater::make('options')
                                ->label('Options')
                                ->schema([
                                    Forms\Components\TextInput::make('value')
                                        ->required()
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('label')
                                        ->required()
                                        ->maxLength(200),
                                ])
                                ->defaultItems(2)
                                ->visible(fn (Forms\Get $get): bool => in_array((string) $get('type'), ['single_choice', 'multi_choice', 'select'], true))
                                ->columns(2),
                            Forms\Components\TextInput::make('placeholder')
                                ->label('Placeholder (optional)')
                                ->maxLength(200)
                                ->visible(fn (Forms\Get $get): bool => (string) $get('type') === 'text'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Reward')
                ->description('Static coupon code shown after submission (buyer copies into the Discount code field).')
                ->schema([
                    Forms\Components\Hidden::make('reward_type')->default('code'),
                    Forms\Components\TextInput::make('reward_code')
                        ->label('Coupon code')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. MILLS10'),
                    Forms\Components\Textarea::make('reward_message')
                        ->label('Reward message (optional)')
                        ->rows(2)
                        ->maxLength(500)
                        ->placeholder('e.g. Copy this code and apply it on the next step.'),
                ])
                ->columns(2),

            Forms\Components\Section::make('UI')
                ->schema([
                    Forms\Components\TextInput::make('ui.title')
                        ->label('Title')
                        ->maxLength(120)
                        ->default('Quick question'),
                    Forms\Components\Textarea::make('ui.description')
                        ->label('Description (optional)')
                        ->rows(2)
                        ->maxLength(300),
                    Forms\Components\TextInput::make('ui.submit_label')
                        ->label('Submit button label')
                        ->maxLength(40)
                        ->default('Submit'),
                    Forms\Components\TextInput::make('ui.thanks_title')
                        ->label('Thank you title')
                        ->maxLength(120)
                        ->default('Thanks!'),
                    Forms\Components\Textarea::make('ui.thanks_body')
                        ->label('Thank you message (optional)')
                        ->rows(2)
                        ->maxLength(400),
                ])
                ->columns(2),
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
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('surfaces')
                    ->label('Surfaces')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : '')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveys::route('/'),
            'create' => Pages\CreateSurvey::route('/create'),
            'edit' => Pages\EditSurvey::route('/{record}/edit'),
        ];
    }
}

