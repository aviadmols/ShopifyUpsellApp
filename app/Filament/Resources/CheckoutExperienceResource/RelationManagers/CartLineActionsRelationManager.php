<?php

namespace App\Filament\Resources\CheckoutExperienceResource\RelationManagers;

use App\Models\CartLineAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CartLineActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'cartLineActions';

    protected static ?string $title = 'Cart line actions (upgrade buttons)';

    protected static ?string $recordTitleAttribute = 'label';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name (internal)')
                    ->maxLength(80)
                    ->placeholder('e.g. Upgrade to bundle'),
                Forms\Components\TextInput::make('label')
                    ->label('Button label')
                    ->required()
                    ->maxLength(120)
                    ->placeholder('e.g. Upgrade to bundle'),
                Forms\Components\Textarea::make('message')
                    ->label('Message above/below button')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\Select::make('action_type')
                    ->label('Action type')
                    ->options(CartLineAction::actionTypes())
                    ->required()
                    ->live()
                    ->default(CartLineAction::ACTION_REPLACE_WITH_VARIANT),
                Forms\Components\TextInput::make('target_variant_gid')
                    ->label('Target variant GID')
                    ->placeholder('gid://shopify/ProductVariant/1234567890')
                    ->maxLength(128)
                    ->visible(fn (Forms\Get $get): bool => in_array($get('action_type'), [
                        CartLineAction::ACTION_REPLACE_WITH_VARIANT,
                        CartLineAction::ACTION_ADD_VARIANT,
                    ], true)),
                Forms\Components\TextInput::make('target_quantity')
                    ->label('Target quantity')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(1)
                    ->visible(fn (Forms\Get $get): bool => in_array($get('action_type'), [
                        CartLineAction::ACTION_ADD_VARIANT,
                        CartLineAction::ACTION_UPDATE_QUANTITY,
                    ], true)),
                Forms\Components\TextInput::make('target_selling_plan_id')
                    ->label('Target selling plan GID')
                    ->placeholder('gid://shopify/SellingPlan/123')
                    ->maxLength(128)
                    ->visible(fn (Forms\Get $get): bool => $get('action_type') === CartLineAction::ACTION_SWITCH_TO_SUBSCRIPTION),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->default(0),

                Forms\Components\Section::make('When to show this button')
                    ->schema([
                        Forms\Components\Select::make('rule_mode')
                            ->label('Rule mode')
                            ->options([
                                'all' => 'All lines',
                                'include_only' => 'Include only',
                                'exclude_only' => 'Exclude only',
                                'include_exclude' => 'Include + Exclude',
                            ])
                            ->default('all')
                            ->live(),
                        Forms\Components\TagsInput::make('include_product_ids')
                            ->label('Include product IDs')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['include_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('exclude_product_ids')
                            ->label('Exclude product IDs')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['exclude_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('include_collection_ids')
                            ->label('Include collection IDs')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['include_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('exclude_collection_ids')
                            ->label('Exclude collection IDs')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['exclude_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('include_tags')
                            ->label('Include tags')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['include_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('exclude_tags')
                            ->label('Exclude tags')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['exclude_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('include_vendors')
                            ->label('Include vendors')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['include_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('exclude_vendors')
                            ->label('Exclude vendors')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['exclude_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('include_product_types')
                            ->label('Include product types')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['include_only', 'include_exclude'], true)),
                        Forms\Components\TagsInput::make('exclude_product_types')
                            ->label('Exclude product types')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('rule_mode'), ['exclude_only', 'include_exclude'], true)),
                        Forms\Components\Select::make('require_subscription_state')
                            ->label('Subscription state')
                            ->options(['any' => 'Any', 'subscription' => 'Subscription only', 'one_time' => 'One-time only'])
                            ->default('any'),
                        Forms\Components\TextInput::make('min_subtotal')
                            ->label('Min cart subtotal')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('max_subtotal')
                            ->label('Max cart subtotal')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('min_cart_items')
                            ->label('Min cart items count')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('max_cart_items')
                            ->label('Max cart items count')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Button')
                    ->searchable(),
                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->formatStateUsing(fn (string $state): string => CartLineAction::actionTypes()[$state] ?? $state),
                Tables\Columns\TextColumn::make('rule_mode')
                    ->label('Rules')
                    ->badge(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['checkout_experience_id'] = $this->getOwnerRecord()->getKey();
                        return $data;
                    }),
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
}
