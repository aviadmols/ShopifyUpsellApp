<?php

namespace App\Filament\Pages;

use App\Models\Shop;
use App\Services\ShopifyGraphQLService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class StoreProductsTool extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'כלי עזר';

    protected static ?string $navigationLabel = 'מוצרים, וריאנטים ותוכניות מכירה';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'filament.pages.store-products-tool';

    protected static ?string $title = 'מוצרי החנות – וריאנטים ו-Selling Plans';

    public ?int $shop_id = null;

    /** @var array<int, array{id: string, title: string, image_url: string|null, variants: array, selling_plans: array}> */
    public array $products = [];

    public string $loadError = '';

    public function mount(): void
    {
        $this->shop_id = Shop::whereNull('uninstalled_at')->value('id');
    }

    /** @return \Illuminate\Support\Collection<int, Shop> */
    public function getShopsProperty()
    {
        return Shop::whereNull('uninstalled_at')->orderBy('shop_domain')->get(['id', 'shop_domain']);
    }

    public function loadProducts(): void
    {
        $this->loadError = '';
        $this->products = [];

        if (! $this->shop_id) {
            $this->loadError = 'נא לבחור חנות.';

            return;
        }

        $shop = Shop::whereNull('uninstalled_at')->find($this->shop_id);
        if (! $shop) {
            $this->loadError = 'חנות לא נמצאה.';

            return;
        }

        try {
            $service = app(ShopifyGraphQLService::class);
            $this->products = $service->listProductsWithVariantsAndSellingPlans($shop, 100);
        } catch (\Throwable $e) {
            Log::warning('StoreProductsTool: failed to load products', [
                'shop_id' => $this->shop_id,
                'message' => $e->getMessage(),
            ]);
            $this->loadError = 'טעינה נכשלה: ' . $e->getMessage();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('load')
                ->label('טען מוצרים')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('loadProducts'),
        ];
    }
}
