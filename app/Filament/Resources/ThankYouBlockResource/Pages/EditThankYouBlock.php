<?php

namespace App\Filament\Resources\ThankYouBlockResource\Pages;

use App\Filament\Resources\ThankYouBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditThankYouBlock extends EditRecord
{
    protected static string $resource = ThankYouBlockResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];

        $data['title'] = (string) ($config['title'] ?? '');
        $data['body'] = (string) ($config['body'] ?? '');
        $data['image_url'] = (string) ($config['image_url'] ?? '');
        $data['button_label'] = (string) ($config['button_label'] ?? ($config['body'] ?? ''));
        $data['button_url'] = (string) ($config['button_url'] ?? '');
        $data['product_id'] = (string) ($config['product_id'] ?? '');
        $data['price_text'] = (string) ($config['price_text'] ?? '');
        $data['badge_text'] = (string) ($config['badge_text'] ?? '');
        $data['show_price'] = (bool) ($config['show_price'] ?? true);
        $data['text_size'] = (string) ($config['text_size'] ?? 'medium');
        $data['text_appearance'] = (string) ($config['text_appearance'] ?? 'default');
        $data['title_bold'] = (bool) ($config['title_bold'] ?? true);
        $data['button_kind'] = (string) ($config['button_kind'] ?? 'secondary');
        $data['spacing'] = (string) ($config['spacing'] ?? 'tight');
        $data['divider_before'] = (bool) ($config['divider_before'] ?? false);
        $data['divider_after'] = (bool) ($config['divider_after'] ?? false);

        $reserved = [
            'title',
            'body',
            'image_url',
            'button_label',
            'button_url',
            'product_id',
            'price_text',
            'badge_text',
            'show_price',
            'text_size',
            'text_appearance',
            'title_bold',
            'button_kind',
            'spacing',
            'divider_before',
            'divider_after',
        ];

        foreach ($reserved as $key) {
            unset($config[$key]);
        }
        $data['advanced_config'] = $config;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
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

        $data['config'] = array_filter($config, static fn ($value) => $value !== '');

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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
