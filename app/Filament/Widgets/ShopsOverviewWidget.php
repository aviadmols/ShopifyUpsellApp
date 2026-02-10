<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ConnectShop;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Rule;
use App\Models\Shop;
use App\Models\ThankYouBlock;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShopsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Overview';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $shopsCount = Shop::whereNull('uninstalled_at')->count();

        return [
            Stat::make('Connected shops', $shopsCount)
                ->icon('heroicon-o-building-storefront')
                ->description('Shops with the app installed')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->url(ConnectShop::getUrl())
                ->color('success'),
            Stat::make('Offers', Offer::count())
                ->icon('heroicon-o-gift'),
            Stat::make('Rules', Rule::count())
                ->icon('heroicon-o-scale'),
            Stat::make('Placements', Placement::count())
                ->icon('heroicon-o-map-pin'),
            Stat::make('Thank you blocks', ThankYouBlock::count())
                ->icon('heroicon-o-squares-2x2'),
        ];
    }
}
