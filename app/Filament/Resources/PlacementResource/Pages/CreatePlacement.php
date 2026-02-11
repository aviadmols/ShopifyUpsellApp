<?php

namespace App\Filament\Resources\PlacementResource\Pages;

use App\Filament\Resources\PlacementResource;
use App\Models\Placement;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePlacement extends CreateRecord
{
    protected static string $resource = PlacementResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['config'] = $this->buildPlacementConfig($data);
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildPlacementConfig(array $data): array
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

        return $config;
    }
}
