<?php

namespace App\Filament\Resources\PlacementResource\Pages;

use App\Filament\Resources\PlacementResource;
use App\Models\Placement;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlacement extends EditRecord
{
    protected static string $resource = PlacementResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        $type = (string) ($data['placement_type'] ?? '');

        $data['offer_ids_csv'] = '';
        $data['block_ids_csv'] = '';
        $data['max_offers'] = 1;
        $data['priority'] = 100;
        $data['display_mode'] = 'stacked';
        $data['require_expanded'] = false;
        $data['cooldown_hours'] = 24;
        $data['allow_reoffer'] = false;
        $data['show_timer'] = false;
        $data['timer_seconds'] = 300;
        $data['extra_config'] = [];

        if ($type === 'checkout') {
            $data['offer_ids_csv'] = implode(',', Placement::normalizeIntList($config['offer_ids'] ?? []));
            $data['max_offers'] = (int) ($config['max_offers'] ?? 3);
            $data['priority'] = (int) ($config['priority'] ?? 100);
            $data['display_mode'] = (string) ($config['display_mode'] ?? 'stacked');
            $data['require_expanded'] = (bool) ($config['require_expanded'] ?? false);
            unset($config['offer_ids'], $config['max_offers'], $config['priority'], $config['display_mode'], $config['require_expanded']);
        } elseif ($type === 'post_purchase') {
            $data['offer_ids_csv'] = implode(',', Placement::normalizeIntList($config['offer_ids'] ?? []));
            $data['max_offers'] = (int) ($config['max_offers'] ?? 1);
            $data['cooldown_hours'] = (int) ($config['cooldown_hours'] ?? 24);
            $data['allow_reoffer'] = (bool) ($config['allow_reoffer'] ?? false);
            $data['show_timer'] = (bool) ($config['show_timer'] ?? false);
            $data['timer_seconds'] = (int) ($config['timer_seconds'] ?? 300);
            unset($config['offer_ids'], $config['max_offers'], $config['cooldown_hours'], $config['allow_reoffer'], $config['show_timer'], $config['timer_seconds']);
        } elseif ($type === 'thank_you') {
            $data['block_ids_csv'] = implode(',', Placement::normalizeIntList($config['block_ids'] ?? []));
            unset($config['block_ids']);
        }

        $data['extra_config'] = $config;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $type = (string) ($data['placement_type'] ?? '');
        $extra = is_array($data['extra_config'] ?? null) ? $data['extra_config'] : [];
        $config = [];

        if ($type === 'checkout') {
            $config = [
                'offer_ids' => Placement::normalizeIntList((string) ($data['offer_ids_csv'] ?? '')),
                'max_offers' => max(1, (int) ($data['max_offers'] ?? 3)),
                'priority' => (int) ($data['priority'] ?? 100),
                'display_mode' => (string) ($data['display_mode'] ?? 'stacked'),
                'require_expanded' => (bool) ($data['require_expanded'] ?? false),
            ];
        } elseif ($type === 'post_purchase') {
            $config = [
                'offer_ids' => Placement::normalizeIntList((string) ($data['offer_ids_csv'] ?? '')),
                'max_offers' => max(1, (int) ($data['max_offers'] ?? 1)),
                'cooldown_hours' => max(0, (int) ($data['cooldown_hours'] ?? 24)),
                'allow_reoffer' => (bool) ($data['allow_reoffer'] ?? false),
                'show_timer' => (bool) ($data['show_timer'] ?? false),
                'timer_seconds' => max(0, (int) ($data['timer_seconds'] ?? 300)),
            ];
        } elseif ($type === 'thank_you') {
            $config = [
                'block_ids' => Placement::normalizeIntList((string) ($data['block_ids_csv'] ?? '')),
            ];
        }

        foreach ($extra as $key => $value) {
            if ($key !== '' && $value !== null && $value !== '') {
                $config[(string) $key] = $value;
            }
        }

        $data['config'] = $config;
        unset(
            $data['offer_ids_csv'],
            $data['block_ids_csv'],
            $data['max_offers'],
            $data['priority'],
            $data['display_mode'],
            $data['require_expanded'],
            $data['cooldown_hours'],
            $data['allow_reoffer'],
            $data['show_timer'],
            $data['timer_seconds'],
            $data['extra_config']
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
