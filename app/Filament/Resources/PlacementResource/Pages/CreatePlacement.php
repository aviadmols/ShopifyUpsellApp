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
            $data['funnel_headline_template'],
            $data['funnel_show_progress'],
            $data['funnel_step_labels'],
            $data['timer_label'],
            $data['urgency_message'],
            $data['cta_text'],
            $data['decline_text'],
            $data['quantity_default'],
            $data['quantity_min'],
            $data['quantity_max'],
            $data['section_heading'],
            $data['title_size'],
            $data['title_appearance'],
            $data['show_price'],
            $data['show_description'],
            $data['image_aspect_ratio'],
            $data['image_fit'],
            $data['image_corner_radius'],
            $data['button_kind'],
            $data['button_appearance'],
            $data['card_spacing'],
            $data['divider_between_cards'],
            $data['progress_bar_enabled'],
            $data['progress_bar_type'],
            $data['progress_bar_goal'],
            $data['progress_bar_message_below'],
            $data['progress_bar_message_achieved'],
            $data['progress_bar_discount_type'],
            $data['progress_bar_discount_value'],
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
                'section_heading' => (string) ($data['section_heading'] ?? 'Add to your order'),
                'title_size' => (string) ($data['title_size'] ?? 'medium'),
                'title_appearance' => (string) ($data['title_appearance'] ?? 'default'),
                'show_price' => (bool) ($data['show_price'] ?? true),
                'show_description' => (bool) ($data['show_description'] ?? true),
                'image_aspect_ratio' => trim((string) ($data['image_aspect_ratio'] ?? '')),
                'image_fit' => (string) ($data['image_fit'] ?? 'cover'),
                'image_corner_radius' => (string) ($data['image_corner_radius'] ?? 'base'),
                'button_kind' => (string) ($data['button_kind'] ?? 'secondary'),
                'button_appearance' => (string) ($data['button_appearance'] ?? 'default'),
                'card_spacing' => (string) ($data['card_spacing'] ?? 'loose'),
                'divider_between_cards' => (bool) ($data['divider_between_cards'] ?? false),
                'progress_bar_enabled' => (bool) ($data['progress_bar_enabled'] ?? false),
                'progress_bar_type' => (string) ($data['progress_bar_type'] ?? 'free_shipping'),
                'progress_bar_goal' => max(0.01, (float) ($data['progress_bar_goal'] ?? 100)),
                'progress_bar_message_below' => (string) ($data['progress_bar_message_below'] ?? "You're {amount} away from free shipping!"),
                'progress_bar_message_achieved' => (string) ($data['progress_bar_message_achieved'] ?? "You've unlocked free shipping!"),
                'progress_bar_discount_type' => (string) ($data['progress_bar_discount_type'] ?? 'percentage'),
                'progress_bar_discount_value' => max(0, (float) ($data['progress_bar_discount_value'] ?? 0)),
            ];
        } elseif ($type === 'post_purchase') {
            $config = [
                'offer_ids' => Placement::normalizeIntList((string) ($data['offer_ids_csv'] ?? '')),
                'max_offers' => max(1, (int) ($data['max_offers'] ?? 3)),
                'cooldown_hours' => max(0, (int) ($data['cooldown_hours'] ?? 24)),
                'allow_reoffer' => (bool) ($data['allow_reoffer'] ?? false),
                'show_timer' => (bool) ($data['show_timer'] ?? false),
                'timer_seconds' => max(0, (int) ($data['timer_seconds'] ?? 300)),
                'funnel_headline_template' => (string) ($data['funnel_headline_template'] ?? ''),
                'funnel_show_progress' => (bool) ($data['funnel_show_progress'] ?? true),
                'funnel_step_labels' => (string) ($data['funnel_step_labels'] ?? 'Order, Offer, Bonus, Done'),
                'timer_label' => (string) ($data['timer_label'] ?? 'For a limited time'),
                'urgency_message' => (string) ($data['urgency_message'] ?? ''),
                'cta_text' => (string) ($data['cta_text'] ?? 'Pay Now'),
                'decline_text' => (string) ($data['decline_text'] ?? 'Decline offer'),
                'quantity_default' => max(1, (int) ($data['quantity_default'] ?? 1)),
                'quantity_min' => max(1, (int) ($data['quantity_min'] ?? 1)),
                'quantity_max' => max(1, (int) ($data['quantity_max'] ?? 10)),
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
