<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class CheckoutExtensionSecretWidget extends Widget
{
    protected static string $view = 'filament.widgets.checkout-extension-secret-widget';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Checkout extension secret';

    public ?string $extensionSecret = null;

    public function mount(): void
    {
        $this->extensionSecret = (string) config('shopify.checkout_extension_secret');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'extensionSecret' => $this->extensionSecret ?? '',
        ]);
    }
}
