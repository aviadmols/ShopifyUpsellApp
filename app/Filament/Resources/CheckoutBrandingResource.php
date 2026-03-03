<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckoutBrandingResource\Pages;
use App\Models\CheckoutBranding;
use App\Models\Shop;
use App\Services\CheckoutBrandingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CheckoutBrandingResource extends Resource
{
    protected static ?string $model = CheckoutBranding::class;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Checkout';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Checkout styling';

    protected static ?string $modelLabel = 'Checkout styling';

    protected static ?string $pluralModelLabel = 'Checkout styling';

    protected static ?string $title = 'Checkout styling';

    public static function form(Form $form): Form
    {
        $designSystemDocs = 'https://shopify.dev/docs/api/admin-graphql/latest/objects/CheckoutBrandingDesignSystem';
        $customizationsDocs = 'https://shopify.dev/docs/apps/build/checkout/styling';

        $stylingDocsUrl = 'https://shopify.dev/docs/apps/build/checkout/styling';

        return $form->schema([
            Forms\Components\Section::make('Required permissions')
                ->description('Checkout styling is available only when the store and app have the following.')
                ->schema([
                    Forms\Components\Placeholder::make('required_permissions')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString(
                            '<p class="text-sm">Requires <strong>Shopify Plus</strong> or a <strong>Development store</strong>, and these OAuth scopes: <code class="rounded bg-gray-100 dark:bg-gray-800 px-1">read_checkout_branding_settings</code>, <code class="rounded bg-gray-100 dark:bg-gray-800 px-1">write_checkout_branding_settings</code>.</p>'
                            . '<p class="mt-2 text-sm"><a href="'.e($stylingDocsUrl).'" target="_blank" rel="noopener" class="text-primary-600 dark:text-primary-400 underline">About checkout styling (Shopify docs)</a></p>'
                        )),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make('Shop')
                ->description('One checkout styling config per store. Styling is applied only when you click "Apply to checkout" on the edit page.')
                ->schema([
                    Forms\Components\Select::make('shop_id')
                        ->relationship('shop', 'shop_domain', fn (Builder $query) => $query->whereNull('uninstalled_at'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->unique(ignoreRecord: true)
                        ->live(),
                ]),

            Forms\Components\Section::make('When to apply')
                ->description('Styling applies to the selected checkout profile. Use a draft profile to preview; after publishing, the styling appears on live checkout.')
                ->schema([
                    Forms\Components\Select::make('checkout_profile_id')
                        ->label('Checkout profile')
                        ->options(function (Forms\Get $get): array {
                            $shopId = $get('shop_id');
                            if (! $shopId) {
                                return [];
                            }
                            $shop = Shop::find($shopId);
                            if (! $shop) {
                                return [];
                            }
                            $service = app(CheckoutBrandingService::class);
                            $profiles = $service->getCheckoutProfiles($shop);
                            return collect($profiles)->pluck('name', 'id')->all();
                        })
                        ->searchable()
                        ->helperText('Select a shop first; profiles load from Shopify. Requires Shopify Plus or Development store.'),
                    Forms\Components\Toggle::make('apply_only_with_checkout_widget')
                        ->label('Apply only when store has a checkout widget')
                        ->default(true)
                        ->helperText('If enabled, "Apply to checkout" will run only when this store has at least one Checkout widget (block). Prevents styling from applying when no widget is in use.'),
                    Forms\Components\Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->default(false)
                        ->helperText('Must be enabled before applying. Apply is still manual via the "Apply to checkout" button on the edit page.'),
                ]),

            Forms\Components\Section::make('Header')
                ->description('Checkout header alignment, position, cart link visibility, and header images.')
                ->schema([
                    Forms\Components\Select::make('header_alignment')
                        ->label('Header alignment')
                        ->options([
                            'START' => 'Start',
                            'CENTER' => 'Center',
                            'END' => 'End',
                        ])
                        ->placeholder('—'),
                    Forms\Components\Select::make('header_position')
                        ->label('Header position')
                        ->options([
                            'START' => 'Start',
                            'END' => 'End',
                        ])
                        ->placeholder('—'),
                    Forms\Components\Select::make('cart_link_visibility')
                        ->label('Cart link visibility')
                        ->options([
                            'VISIBLE' => 'Visible',
                            'HIDDEN' => 'Hidden',
                        ])
                        ->placeholder('—')
                        ->helperText('Show or hide the cart icon in the header and the cart link in breadcrumbs.'),
                    Forms\Components\TextInput::make('header_banner_media_image_id')
                        ->label('Header banner image ID')
                        ->placeholder('gid://shopify/MediaImage/...')
                        ->maxLength(255)
                        ->helperText('Upload an image in Shopify Admin (Content → Files), then paste the media image GID here. No SVG.'),
                    Forms\Components\TextInput::make('header_logo_media_image_id')
                        ->label('Header logo image ID')
                        ->placeholder('gid://shopify/MediaImage/...')
                        ->maxLength(255)
                        ->helperText('Upload a logo in Content → Files and paste the media image GID here.'),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Colors & typography')
                ->description('Design system: global accent color and base typography.')
                ->schema([
                    Forms\Components\TextInput::make('design_system_accent')
                        ->label('Global accent color')
                        ->placeholder('#000000')
                        ->helperText('Hex color for designSystem.colors.global.accent'),
                    Forms\Components\TextInput::make('design_system_typography_base')
                        ->label('Typography base size')
                        ->numeric()
                        ->placeholder('16')
                        ->helperText('designSystem.typography.size.base (px)'),
                    Forms\Components\TextInput::make('design_system_typography_ratio')
                        ->label('Typography ratio')
                        ->numeric()
                        ->step(0.1)
                        ->placeholder('1.4')
                        ->helperText('designSystem.typography.size.ratio'),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Global & primary button')
                ->description('Global corner radius and primary button style.')
                ->schema([
                    Forms\Components\Select::make('global_corner_radius')
                        ->label('Global corner radius')
                        ->options([
                            'NONE' => 'None',
                            'BASE' => 'Base',
                            'LARGE' => 'Large',
                        ])
                        ->placeholder('—'),
                    Forms\Components\Select::make('primary_button_corner_radius')
                        ->label('Primary button corner radius')
                        ->options([
                            'NONE' => 'None',
                            'BASE' => 'Base',
                            'LARGE' => 'Large',
                        ])
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Headings')
                ->description('Heading level 1 and 2 typography.')
                ->schema([
                    Forms\Components\Select::make('heading_level1_weight')
                        ->label('Heading 1 weight')
                        ->options([
                            'BASE' => 'Base',
                            'BOLD' => 'Bold',
                        ])
                        ->placeholder('—'),
                    Forms\Components\Select::make('heading_level1_size')
                        ->label('Heading 1 size')
                        ->options([
                            'SMALL' => 'Small',
                            'MEDIUM' => 'Medium',
                            'LARGE' => 'Large',
                        ])
                        ->placeholder('—'),
                    Forms\Components\Select::make('heading_level2_weight')
                        ->label('Heading 2 weight')
                        ->options([
                            'BASE' => 'Base',
                            'BOLD' => 'Bold',
                        ])
                        ->placeholder('—'),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Design system (advanced)')
                ->description('Raw JSON. Structured fields above are merged into this on save.')
                ->schema([
                    Forms\Components\Textarea::make('design_system')
                        ->label('Design system (JSON)')
                        ->rows(12)
                        ->columnSpanFull()
                        ->placeholder('{}')
                        ->formatStateUsing(fn ($state) => \is_array($state) ? json_encode($state, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) : (string) $state)
                        ->dehydrateStateUsing(fn ($state) => \is_string($state) ? (json_decode($state, true) ?? []) : (array) $state)
                        ->helperText(new \Illuminate\Support\HtmlString(
                            'Valid JSON. See <a href="'.e($designSystemDocs).'" target="_blank" rel="noopener">CheckoutBrandingDesignSystem</a> for structure.'
                        )),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make('Customizations (advanced)')
                ->description('Raw JSON for all customizations. Structured fields above are merged into this on save.')
                ->schema([
                    Forms\Components\Textarea::make('customizations')
                        ->label('Customizations (JSON)')
                        ->rows(12)
                        ->columnSpanFull()
                        ->placeholder('{}')
                        ->formatStateUsing(fn ($state) => \is_array($state) ? json_encode($state, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) : (string) $state)
                        ->dehydrateStateUsing(fn ($state) => \is_string($state) ? (json_decode($state, true) ?? []) : (array) $state)
                        ->helperText(new \Illuminate\Support\HtmlString(
                            'Valid JSON. Keys: header, primaryButton, secondaryButton, main, orderSummary, headingLevel1, headingLevel2, etc. See <a href="'.e($customizationsDocs).'" target="_blank" rel="noopener">Checkout styling docs</a>.'
                        )),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('shop.shop_domain')
                    ->label('Shop')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('checkout_profile_id')
                    ->label('Profile')
                    ->formatStateUsing(fn (?string $state): string => $state ? (str_contains($state, '/') ? '…'.substr($state, -12) : $state) : '—')
                    ->tooltip(fn ($record) => $record?->checkout_profile_id ?? ''),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
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
            'index' => Pages\ListCheckoutBrandings::route('/'),
            'create' => Pages\CreateCheckoutBranding::route('/create'),
            'edit' => Pages\EditCheckoutBranding::route('/{record}/edit'),
        ];
    }

    /**
     * Merge structured form fields (header_*, design_system_*, etc.) into design_system and customizations; remove virtual keys.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeStructuredBrandingData(array $data): array
    {
        $customizations = is_array($data['customizations'] ?? null) ? $data['customizations'] : [];
        if (isset($data['header_alignment']) && $data['header_alignment'] !== null && $data['header_alignment'] !== '') {
            $customizations['header'] = array_merge($customizations['header'] ?? [], ['alignment' => $data['header_alignment']]);
        }
        if (isset($data['header_position']) && $data['header_position'] !== null && $data['header_position'] !== '') {
            $customizations['header'] = array_merge($customizations['header'] ?? [], ['position' => $data['header_position']]);
        }
        if (isset($data['cart_link_visibility']) && $data['cart_link_visibility'] !== null && $data['cart_link_visibility'] !== '') {
            $customizations['cartLink'] = array_merge($customizations['cartLink'] ?? [], ['visibility' => $data['cart_link_visibility']]);
        }
        if (isset($data['header_banner_media_image_id']) && trim((string) $data['header_banner_media_image_id']) !== '') {
            $customizations['header'] = array_merge($customizations['header'] ?? [], ['banner' => ['mediaImageId' => trim((string) $data['header_banner_media_image_id'])]]);
        }
        if (isset($data['header_logo_media_image_id']) && trim((string) $data['header_logo_media_image_id']) !== '') {
            $customizations['header'] = array_merge($customizations['header'] ?? [], ['logo' => ['image' => ['mediaImageId' => trim((string) $data['header_logo_media_image_id'])]]]);
        }
        if (isset($data['global_corner_radius']) && $data['global_corner_radius'] !== null && $data['global_corner_radius'] !== '') {
            $customizations['global'] = array_merge($customizations['global'] ?? [], ['cornerRadius' => $data['global_corner_radius']]);
        }
        if (isset($data['primary_button_corner_radius']) && $data['primary_button_corner_radius'] !== null && $data['primary_button_corner_radius'] !== '') {
            $customizations['primaryButton'] = array_merge($customizations['primaryButton'] ?? [], ['cornerRadius' => $data['primary_button_corner_radius']]);
        }
        $h1Typo = $customizations['headingLevel1']['typography'] ?? [];
        if (isset($data['heading_level1_weight']) && $data['heading_level1_weight'] !== null && $data['heading_level1_weight'] !== '') {
            $h1Typo['weight'] = $data['heading_level1_weight'];
        }
        if (isset($data['heading_level1_size']) && $data['heading_level1_size'] !== null && $data['heading_level1_size'] !== '') {
            $h1Typo['size'] = $data['heading_level1_size'];
        }
        if ($h1Typo !== []) {
            $customizations['headingLevel1'] = array_merge($customizations['headingLevel1'] ?? [], ['typography' => $h1Typo]);
        }
        if (isset($data['heading_level2_weight']) && $data['heading_level2_weight'] !== null && $data['heading_level2_weight'] !== '') {
            $customizations['headingLevel2'] = array_merge($customizations['headingLevel2'] ?? [], [
                'typography' => array_merge($customizations['headingLevel2']['typography'] ?? [], ['weight' => $data['heading_level2_weight']]),
            ]);
        }
        $data['customizations'] = $customizations;

        $designSystem = is_array($data['design_system'] ?? null) ? $data['design_system'] : [];
        if (isset($data['design_system_accent']) && $data['design_system_accent'] !== null && (string) $data['design_system_accent'] !== '') {
            $designSystem['colors'] = array_merge($designSystem['colors'] ?? [], [
                'global' => array_merge($designSystem['colors']['global'] ?? [], ['accent' => (string) $data['design_system_accent']]),
            ]);
        }
        $typoSize = $designSystem['typography']['size'] ?? [];
        if (isset($data['design_system_typography_base']) && $data['design_system_typography_base'] !== null && (string) $data['design_system_typography_base'] !== '') {
            $typoSize['base'] = (int) $data['design_system_typography_base'];
        }
        if (isset($data['design_system_typography_ratio']) && $data['design_system_typography_ratio'] !== null && (string) $data['design_system_typography_ratio'] !== '') {
            $typoSize['ratio'] = (float) $data['design_system_typography_ratio'];
        }
        if ($typoSize !== []) {
            $designSystem['typography'] = array_merge($designSystem['typography'] ?? [], ['size' => $typoSize]);
        }
        $data['design_system'] = $designSystem;

        $virtualKeys = [
            'header_alignment', 'header_position', 'cart_link_visibility', 'header_banner_media_image_id', 'header_logo_media_image_id',
            'global_corner_radius', 'primary_button_corner_radius',
            'heading_level1_weight', 'heading_level1_size', 'heading_level2_weight',
            'design_system_accent', 'design_system_typography_base', 'design_system_typography_ratio',
        ];
        foreach ($virtualKeys as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}
