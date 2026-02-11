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
        $data['funnel_headline_template'] = '';
        $data['funnel_show_progress'] = true;
        $data['funnel_step_labels'] = 'Order, Offer, Bonus, Done';
        $data['timer_label'] = 'For a limited time';
        $data['urgency_message'] = '';
        $data['cta_text'] = 'Pay Now';
        $data['decline_text'] = 'Decline offer';
        $data['quantity_default'] = 1;
        $data['quantity_min'] = 1;
        $data['quantity_max'] = 10;
        $data['extra_config'] = [];
        $data['section_heading'] = 'Add to your order';
        $data['title_size'] = 'medium';
        $data['title_appearance'] = 'default';
        $data['show_price'] = true;
        $data['show_description'] = true;
        $data['image_aspect_ratio'] = '';
        $data['image_fit'] = 'cover';
        $data['image_corner_radius'] = 'base';
        $data['button_kind'] = 'secondary';
        $data['button_appearance'] = 'default';
        $data['card_spacing'] = 'loose';
        $data['divider_between_cards'] = false;
        $data['progress_bar_enabled'] = false;
        $data['progress_bar_type'] = 'free_shipping';
        $data['progress_bar_goal'] = 100;
        $data['progress_bar_message_below'] = "You're {amount} away from free shipping!";
        $data['progress_bar_message_achieved'] = "You've unlocked free shipping!";
        $data['progress_bar_discount_type'] = 'percentage';
        $data['progress_bar_discount_value'] = 0;

        if ($type === 'checkout') {
            $data['offer_ids_csv'] = implode(',', Placement::normalizeIntList($config['offer_ids'] ?? []));
            $data['max_offers'] = (int) ($config['max_offers'] ?? 3);
            $data['priority'] = (int) ($config['priority'] ?? 100);
            $data['display_mode'] = (string) ($config['display_mode'] ?? 'stacked');
            $data['require_expanded'] = (bool) ($config['require_expanded'] ?? false);
            $data['section_heading'] = (string) ($config['section_heading'] ?? 'Add to your order');
            $data['title_size'] = (string) ($config['title_size'] ?? 'medium');
            $data['title_appearance'] = (string) ($config['title_appearance'] ?? 'default');
            $data['show_price'] = (bool) ($config['show_price'] ?? true);
            $data['show_description'] = (bool) ($config['show_description'] ?? true);
            $data['image_aspect_ratio'] = (string) ($config['image_aspect_ratio'] ?? '');
            $data['image_fit'] = (string) ($config['image_fit'] ?? 'cover');
            $data['image_corner_radius'] = (string) ($config['image_corner_radius'] ?? 'base');
            $data['button_kind'] = (string) ($config['button_kind'] ?? 'secondary');
            $data['button_appearance'] = (string) ($config['button_appearance'] ?? 'default');
            $data['card_spacing'] = (string) ($config['card_spacing'] ?? 'loose');
            $data['divider_between_cards'] = (bool) ($config['divider_between_cards'] ?? false);
            $data['progress_bar_enabled'] = (bool) ($config['progress_bar_enabled'] ?? false);
            $data['progress_bar_type'] = (string) ($config['progress_bar_type'] ?? 'free_shipping');
            $data['progress_bar_goal'] = (float) ($config['progress_bar_goal'] ?? 100);
            $data['progress_bar_message_below'] = (string) ($config['progress_bar_message_below'] ?? "You're {amount} away from free shipping!");
            $data['progress_bar_message_achieved'] = (string) ($config['progress_bar_message_achieved'] ?? "You've unlocked free shipping!");
            $data['progress_bar_discount_type'] = (string) ($config['progress_bar_discount_type'] ?? 'percentage');
            $data['progress_bar_discount_value'] = (float) ($config['progress_bar_discount_value'] ?? 0);
            $checkoutKeys = [
                'offer_ids', 'max_offers', 'priority', 'display_mode', 'require_expanded',
                'section_heading', 'title_size', 'title_appearance', 'show_price', 'show_description',
                'image_aspect_ratio', 'image_fit', 'image_corner_radius', 'button_kind', 'button_appearance',
                'card_spacing', 'divider_between_cards',
                'progress_bar_enabled', 'progress_bar_type', 'progress_bar_goal', 'progress_bar_message_below',
                'progress_bar_message_achieved', 'progress_bar_discount_type', 'progress_bar_discount_value',
            ];
            foreach ($checkoutKeys as $key) {
                unset($config[$key]);
            }
        } elseif ($type === 'post_purchase') {
            $data['offer_ids_csv'] = implode(',', Placement::normalizeIntList($config['offer_ids'] ?? []));
            $data['max_offers'] = (int) ($config['max_offers'] ?? 3);
            $data['cooldown_hours'] = (int) ($config['cooldown_hours'] ?? 24);
            $data['allow_reoffer'] = (bool) ($config['allow_reoffer'] ?? false);
            $data['show_timer'] = (bool) ($config['show_timer'] ?? false);
            $data['timer_seconds'] = (int) ($config['timer_seconds'] ?? 300);
            $data['funnel_headline_template'] = (string) ($config['funnel_headline_template'] ?? '');
            $data['funnel_show_progress'] = (bool) ($config['funnel_show_progress'] ?? true);
            $data['funnel_step_labels'] = (string) ($config['funnel_step_labels'] ?? 'Order, Offer, Bonus, Done');
            $data['timer_label'] = (string) ($config['timer_label'] ?? 'For a limited time');
            $data['urgency_message'] = (string) ($config['urgency_message'] ?? '');
            $data['cta_text'] = (string) ($config['cta_text'] ?? 'Pay Now');
            $data['decline_text'] = (string) ($config['decline_text'] ?? 'Decline offer');
            $data['quantity_default'] = max(1, (int) ($config['quantity_default'] ?? 1));
            $data['quantity_min'] = max(1, (int) ($config['quantity_min'] ?? 1));
            $data['quantity_max'] = max(1, (int) ($config['quantity_max'] ?? 10));
            $postPurchaseKeys = [
                'offer_ids', 'max_offers', 'cooldown_hours', 'allow_reoffer', 'show_timer', 'timer_seconds',
                'funnel_headline_template', 'funnel_show_progress', 'funnel_step_labels', 'timer_label',
                'urgency_message', 'cta_text', 'decline_text', 'quantity_default', 'quantity_min', 'quantity_max',
            ];
            foreach ($postPurchaseKeys as $key) {
                unset($config[$key]);
            }
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
