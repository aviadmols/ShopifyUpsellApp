<?php

namespace App\Filament\Pages;

use App\Models\Offer;
use App\Models\Placement;
use App\Models\Shop;
use App\Services\RuleEngine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Illuminate\Support\Arr;

class Preview extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationGroup = 'Upsell';

    protected static ?string $navigationLabel = 'Preview';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.preview';

    protected static ?string $title = 'Preview: Which offer would render?';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill([
            'data' => [
                'placement_type' => 'post_purchase',
                'payload' => json_encode([
                    'order_id' => '123',
                    'subtotal' => 150,
                    'line_items' => [['product_id' => 456, 'quantity' => 1]],
                    'customer' => ['tags' => ['vip']],
                    'shipping_country' => 'US',
                ], JSON_PRETTY_PRINT),
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Preview context')
                    ->description('Select a shop and placement type, then paste sample order/cart JSON. Run to see which offer would be selected.')
                    ->schema([
                        Forms\Components\Select::make('shop_id')
                            ->options(fn (): array => Shop::query()
                                ->whereNull('uninstalled_at')
                                ->orderBy('shop_domain')
                                ->pluck('shop_domain', 'id')
                                ->toArray())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Shop'),
                        Forms\Components\Select::make('placement_type')
                            ->options([
                                'post_purchase' => 'Post-purchase',
                                'checkout' => 'Checkout',
                                'thank_you' => 'Thank you',
                            ])
                            ->required()
                            ->default('post_purchase'),
                        Forms\Components\Textarea::make('payload')
                            ->label('Order/Cart JSON')
                            ->rows(14)
                            ->columnSpanFull()
                            ->helperText('Sample: order_id, subtotal, line_items, customer, shipping_country'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function run(): void
    {
        $data = $this->form->getState();
        $shopId = $data['shop_id'] ?? null;
        $placementType = $data['placement_type'] ?? 'post_purchase';
        $payloadStr = $data['payload'] ?? '{}';

        $payload = json_decode($payloadStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->result = ['error' => 'Invalid JSON'];
            return;
        }

        $shop = \App\Models\Shop::whereNull('uninstalled_at')->find($shopId);
        if (! $shop) {
            $this->result = ['error' => 'Shop not found'];
            return;
        }

        $context = $this->normalizeContext($payload ?? []);
        $placement = Placement::where('shop_id', $shop->id)->where('placement_type', $placementType)->first();
        if (! $placement) {
            $this->result = ['match' => null, 'message' => 'No placement configured for this type'];
            return;
        }

        $offerIds = $placement->getOfferIds();
        $maxOffers = (int) ($placement->config['max_offers'] ?? 1);
        $eligible = [];
        $ruleEngine = app(RuleEngine::class);

        foreach ($offerIds as $id) {
            if (count($eligible) >= $maxOffers) {
                break;
            }
            $offer = Offer::where('shop_id', $shop->id)->find($id);
            if (! $offer) {
                continue;
            }
            if ($offer->rule_id) {
                $rule = $offer->rule;
                if (! $rule || ! $ruleEngine->evaluate($rule->conditions, $context)) {
                    continue;
                }
            }
            $eligible[] = $offer;
        }

        $matched = $eligible[0] ?? null;
        $this->result = [
            'match' => $matched ? [
                'offerId' => $matched->id,
                'title' => $matched->title,
                'variantId' => $matched->product_variant_id,
            ] : null,
            'context_used' => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeContext(array $payload): array
    {
        return [
            'order_id' => $payload['order_id'] ?? Arr::get($payload, 'order.id') ?? null,
            'subtotal' => $payload['subtotal'] ?? Arr::get($payload, 'order.subtotal') ?? 0,
            'line_items' => $payload['line_items'] ?? $payload['lineItems'] ?? Arr::get($payload, 'order.line_items') ?? [],
            'customer' => $payload['customer'] ?? [],
            'shipping_address' => $payload['shipping_address'] ?? $payload['shippingAddress'] ?? [],
            'shipping_country' => $payload['shipping_country'] ?? Arr::get($payload, 'shipping_address.country_code') ?? Arr::get($payload, 'shippingAddress.countryCode') ?? null,
        ];
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('run')
                ->label('Run preview')
                ->submit('run')
                ->icon('heroicon-o-play'),
        ];
    }

    /**
     * @return array<string, Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form($this->makeForm()->columns(1)->statePath('data')),
        ];
    }
}
