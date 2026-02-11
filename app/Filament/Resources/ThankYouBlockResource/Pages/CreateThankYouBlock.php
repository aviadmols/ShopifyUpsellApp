<?php

namespace App\Filament\Resources\ThankYouBlockResource\Pages;

use App\Filament\Resources\ThankYouBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateThankYouBlock extends CreateRecord
{
    protected static string $resource = ThankYouBlockResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $config = $this->buildConfig($data);
        $data['config'] = $config;

        unset(
            $data['title'],
            $data['body'],
            $data['image_url'],
            $data['button_label'],
            $data['button_url'],
            $data['product_id'],
            $data['price_text'],
            $data['badge_text'],
            $data['show_price'],
            $data['text_size'],
            $data['text_appearance'],
            $data['title_bold'],
            $data['button_kind'],
            $data['spacing'],
            $data['divider_before'],
            $data['divider_after'],
            $data['advanced_config']
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildConfig(array $data): array
    {
        $config = [
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'image_url' => (string) ($data['image_url'] ?? ''),
            'button_label' => (string) ($data['button_label'] ?? ''),
            'button_url' => (string) ($data['button_url'] ?? ''),
            'product_id' => (string) ($data['product_id'] ?? ''),
            'price_text' => (string) ($data['price_text'] ?? ''),
            'badge_text' => (string) ($data['badge_text'] ?? ''),
            'show_price' => (bool) ($data['show_price'] ?? true),
            'text_size' => (string) ($data['text_size'] ?? 'medium'),
            'text_appearance' => (string) ($data['text_appearance'] ?? 'default'),
            'title_bold' => (bool) ($data['title_bold'] ?? true),
            'button_kind' => (string) ($data['button_kind'] ?? 'secondary'),
            'spacing' => (string) ($data['spacing'] ?? 'tight'),
            'divider_before' => (bool) ($data['divider_before'] ?? false),
            'divider_after' => (bool) ($data['divider_after'] ?? false),
        ];

        $advanced = is_array($data['advanced_config'] ?? null) ? $data['advanced_config'] : [];
        foreach ($advanced as $key => $value) {
            if ($key !== '' && $value !== null && $value !== '') {
                $config[(string) $key] = $value;
            }
        }

        return array_filter($config, static fn ($value) => $value !== '');
    }
}
