<?php

namespace App\Filament\Resources\CheckoutBrandingResource\Pages;

use App\Filament\Resources\CheckoutBrandingResource;
use App\Services\CheckoutBrandingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCheckoutBranding extends EditRecord
{
    protected static string $resource = CheckoutBrandingResource::class;

    public function mutateFormDataBeforeFill(array $data): array
    {
        $customizations = is_array($data['customizations'] ?? null) ? $data['customizations'] : [];
        $header = $customizations['header'] ?? [];
        $data['header_alignment'] = $header['alignment'] ?? null;
        $data['header_position'] = $header['position'] ?? null;
        $data['cart_link_visibility'] = $customizations['cartLink']['visibility'] ?? null;
        $banner = $header['banner'] ?? [];
        $data['header_banner_media_image_id'] = is_array($banner) ? ($banner['mediaImageId'] ?? null) : null;
        $logoImage = $header['logo']['image'] ?? [];
        $data['header_logo_media_image_id'] = is_array($logoImage) ? ($logoImage['mediaImageId'] ?? null) : null;
        $data['global_corner_radius'] = $customizations['global']['cornerRadius'] ?? null;
        $data['primary_button_corner_radius'] = $customizations['primaryButton']['cornerRadius'] ?? null;
        $h1 = $customizations['headingLevel1']['typography'] ?? [];
        $data['heading_level1_weight'] = $h1['weight'] ?? null;
        $data['heading_level1_size'] = $h1['size'] ?? null;
        $h2 = $customizations['headingLevel2']['typography'] ?? [];
        $data['heading_level2_weight'] = $h2['weight'] ?? null;

        $designSystem = is_array($data['design_system'] ?? null) ? $data['design_system'] : [];
        $data['design_system_accent'] = $designSystem['colors']['global']['accent'] ?? null;
        $data['design_system_typography_base'] = $designSystem['typography']['size']['base'] ?? null;
        $data['design_system_typography_ratio'] = $designSystem['typography']['size']['ratio'] ?? null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CheckoutBrandingResource::mergeStructuredBrandingData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('apply')
                ->label('Apply to checkout')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->action(function () {
                    $service = app(CheckoutBrandingService::class);
                    $result = $service->applyBranding($this->record);
                    if ($result['success']) {
                        Notification::make()
                            ->title($result['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Apply failed')
                            ->body($result['message'])
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('reset')
                ->label('Reset to default')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reset checkout styling')
                ->modalDescription('This will remove all custom styling from the selected checkout profile and restore Shopify defaults.')
                ->action(function () {
                    $service = app(CheckoutBrandingService::class);
                    $result = $service->resetBranding($this->record);
                    if ($result['success']) {
                        Notification::make()
                            ->title($result['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Reset failed')
                            ->body($result['message'])
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
