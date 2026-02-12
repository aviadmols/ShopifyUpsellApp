<?php

namespace App\Filament\Resources\BlockResource\Pages;

use App\Filament\Resources\BlockResource;
use App\Filament\Resources\CheckoutExperienceResource;
use App\Http\Controllers\Api\CheckoutUpsellController;
use App\Models\CheckoutExperience;
use App\Models\Offer;
use App\Models\Placement;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;

class EditBlock extends EditRecord
{
    protected static string $resource = BlockResource::class;

    /** @var array<string, mixed> */
    public array $blockPreviewData = [];

    /** @var array<int, array<string, mixed>> */
    public array $widgetOffersData = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Preview widget')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function (): void {
                    $this->blockPreviewData = $this->getBlockPreviewData();
                })
                ->modalHeading('Widget preview')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function (): View {
                    $data = array_merge(['surface' => '', 'type' => '', 'config' => []], $this->blockPreviewData);
                    if (($data['surface'] === '' || $data['type'] === '') && isset($this->record)) {
                        $data['surface'] = (string) $this->record->surface;
                        $data['type'] = (string) $this->record->type;
                        $data['config'] = is_array($this->record->config) ? $this->record->config : [];
                    }
                    return view('filament.components.block-preview', $data);
                }),
            Actions\Action::make('edit_checkout_experience')
                ->label('Edit Checkout Experience')
                ->icon('heroicon-o-shopping-cart')
                ->color('gray')
                ->visible(fn (): bool => $this->record !== null && $this->record->surface === 'checkout')
                ->url(function (): string {
                    $shop = $this->record?->shop;
                    if (! $shop) {
                        return CheckoutExperienceResource::getUrl('index');
                    }
                    $experience = CheckoutExperience::where('shop_id', $shop->id)->first();
                    if ($experience) {
                        return CheckoutExperienceResource::getUrl('edit', ['record' => $experience]);
                    }
                    return CheckoutExperienceResource::getUrl('create', ['shop_id' => $shop->id]);
                })
                ->openUrlInNewTab(false),
            Actions\Action::make('checkout_health')
                ->label('Check checkout health')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn (): bool => $this->record !== null && $this->record->surface === 'checkout' && $this->record->type === 'upsell')
                ->modalHeading('Checkout health')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): View => view('filament.components.checkout-health-result', [
                    'result' => $this->getCheckoutHealthResult(),
                ])),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Run checkout health check and return result for modal display.
     *
     * @return array{error?: bool, message?: string, success?: bool, count?: int, block_error?: string|null, display_settings?: array<string, mixed>, experience?: array<string, mixed>}
     */
    protected function getCheckoutHealthResult(): array
    {
        $block = $this->record;
        if (! $block || $block->surface !== 'checkout' || $block->type !== 'upsell') {
            return ['error' => true, 'message' => 'Block is not a checkout upsell widget.'];
        }
        $shop = $block->shop;
        if (! $shop) {
            return ['error' => true, 'message' => 'Block has no shop. Assign a shop to this widget.'];
        }
        if ($shop->uninstalled_at !== null) {
            return ['error' => true, 'message' => 'Store is not connected. Reinstall the app for this store.'];
        }

        $experienceSummary = $this->getCheckoutExperienceSummary($shop);
        try {
            $controller = app(CheckoutUpsellController::class);
            $context = ['subtotal' => 0, 'line_items' => [], 'customer' => [], 'shipping_country' => null, 'utms' => [], 'url_params' => []];
            $payload = $controller->buildUpsellPayloadForBlock($block, $shop, $context, null);
            $count = count($payload['offers'] ?? []);
            $blockError = $payload['block_error'] ?? null;
            $displaySettings = array_merge(
                ['display_mode' => $payload['display_mode'] ?? 'stacked'],
                $payload['ui'] ?? []
            );
            if (isset($displaySettings['progress_bar'])) {
                $displaySettings['progress_bar'] = is_array($displaySettings['progress_bar'])
                    ? '(array)'
                    : (string) $displaySettings['progress_bar'];
            }
            return [
                'success' => true,
                'count' => $count,
                'block_error' => $blockError,
                'display_settings' => $displaySettings,
                'experience' => $experienceSummary,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage().' in '.$e->getFile().':'.$e->getLine(),
                'experience' => $experienceSummary,
            ];
        }
    }

    /**
     * Summary of Checkout Experience for this shop (for health modal).
     *
     * @return array{exists: bool, id?: int, quantity_upsell: bool, quantity_cart: bool, subscription_upgrade: bool, message: string}
     */
    protected function getCheckoutExperienceSummary(\App\Models\Shop $shop): array
    {
        $experience = CheckoutExperience::where('shop_id', $shop->id)->first();
        if (! $experience) {
            return [
                'exists' => false,
                'quantity_upsell' => false,
                'quantity_cart' => false,
                'subscription_upgrade' => false,
                'message' => 'No Checkout Experience for this store. In Checkout block settings you can set "Checkout Experience ID" only after creating one in Admin → Checkout experience.',
            ];
        }
        return [
            'exists' => true,
            'id' => $experience->id,
            'quantity_upsell' => (bool) $experience->quantity_in_upsell_enabled,
            'quantity_cart' => (bool) $experience->quantity_in_cart_enabled,
            'subscription_upgrade' => (bool) $experience->subscription_upgrade_enabled,
            'message' => 'Checkout Experience is configured. Use its ID in the block\'s "Checkout Experience ID" in Shopify Checkout settings to enable quantity/subscription for this widget.',
        ];
    }

    /**
     * Build surface, type, config from current form state for preview.
     *
     * @return array{surface: string, type: string, config: array<string, mixed>}
     */
    protected function getBlockPreviewData(): array
    {
        $state = $this->form->getState();
        $surface = (string) ($state['surface'] ?? '');
        $type = (string) ($state['type'] ?? '');

        if (($surface === '' || $type === '') && isset($this->record)) {
            $surface = (string) $this->record->surface;
            $type = (string) $this->record->type;
            $config = is_array($this->record->config) ? $this->record->config : [];
            $previewOffers = [];
            if (($surface === 'checkout' && $type === 'upsell') || ($surface === 'post_purchase' && $type === 'post_purchase_funnel')) {
                $widgetOffers = self::widgetOffersFromBlock($this->record);
                $previewOffers = CreateBlock::enrichPreviewOffers($this->record->shop_id, $widgetOffers);
            }

            return [
                'surface' => $surface,
                'type' => $type,
                'config' => $config,
                'preview_offers' => $previewOffers,
            ];
        }

        $config = CreateBlock::buildBlockConfig($state);
        $previewOffers = [];
        if (($surface === 'checkout' && $type === 'upsell') || ($surface === 'post_purchase' && $type === 'post_purchase_funnel')) {
            $shopId = $state['shop_id'] ?? null;
            $widgetOffers = is_array($state['widget_offers'] ?? null) ? $state['widget_offers'] : [];
            $previewOffers = CreateBlock::enrichPreviewOffers($shopId, $widgetOffers);
        }

        return [
            'surface' => $surface,
            'type' => $type,
            'config' => $config,
            'preview_offers' => $previewOffers,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load('blockOffers.offer.rule');
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        $surface = (string) ($data['surface'] ?? '');
        $type = (string) ($data['type'] ?? '');

        if ($surface === 'checkout' && $type === 'upsell') {
            $data['widget_offers'] = self::widgetOffersFromBlock($this->record);
            $data['offer_ids_csv'] = implode(',', Placement::normalizeIntList($config['offer_ids'] ?? []));
            $data['max_offers'] = (int) ($config['max_offers'] ?? 3);
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
            $data['show_quantity'] = (bool) ($config['show_quantity'] ?? true);
        } elseif ($surface === 'checkout' && $type === 'progress_bar') {
            $data['progress_bar_type'] = (string) ($config['progress_bar_type'] ?? 'free_shipping');
            $data['progress_bar_goal'] = (float) ($config['progress_bar_goal'] ?? 100);
            $data['progress_bar_message_below'] = (string) ($config['progress_bar_message_below'] ?? "You're {amount} away from free shipping!");
            $data['progress_bar_message_achieved'] = (string) ($config['progress_bar_message_achieved'] ?? "You've unlocked free shipping!");
            $data['progress_bar_discount_type'] = (string) ($config['progress_bar_discount_type'] ?? 'percentage');
            $data['progress_bar_discount_value'] = (float) ($config['progress_bar_discount_value'] ?? 0);
        } elseif ($type === 'content_icon_features') {
            $data['icon_features_items'] = $config['icon_features'] ?? [];
        } elseif (in_array($type, ['content_banner', 'content_rich_text', 'content_button'], true)) {
            $data['title'] = (string) ($config['title'] ?? '');
            $data['body'] = (string) ($config['body'] ?? '');
            $data['image_url'] = (string) ($config['image_url'] ?? '');
            $data['button_label'] = (string) ($config['button_label'] ?? '');
            $data['button_url'] = (string) ($config['button_url'] ?? '');
            $data['text_size'] = (string) ($config['text_size'] ?? 'medium');
            $data['text_appearance'] = (string) ($config['text_appearance'] ?? 'default');
            $data['button_kind'] = (string) ($config['button_kind'] ?? 'secondary');
            $data['spacing'] = (string) ($config['spacing'] ?? 'tight');
        } elseif ($type === 'content_product_card') {
            $data['title'] = (string) ($config['title'] ?? '');
            $data['body'] = (string) ($config['body'] ?? '');
            $data['image_url'] = (string) ($config['image_url'] ?? '');
            $data['product_id'] = (string) ($config['product_id'] ?? '');
            $data['price_text'] = (string) ($config['price_text'] ?? '');
            $data['badge_text'] = (string) ($config['badge_text'] ?? '');
            $data['show_price'] = (bool) ($config['show_price'] ?? true);
            $data['button_label'] = (string) ($config['button_label'] ?? '');
            $data['button_url'] = (string) ($config['button_url'] ?? '');
            $data['text_size'] = (string) ($config['text_size'] ?? 'medium');
            $data['button_kind'] = (string) ($config['button_kind'] ?? 'secondary');
            $data['spacing'] = (string) ($config['spacing'] ?? 'tight');
        } elseif ($surface === 'post_purchase' && $type === 'post_purchase_funnel') {
            $data['widget_offers'] = self::widgetOffersFromBlock($this->record);
            $data['offer_ids_csv'] = implode(',', Placement::normalizeIntList($config['offer_ids'] ?? []));
            $data['max_offers'] = (int) ($config['max_offers'] ?? 3);
            $data['cooldown_hours'] = (int) ($config['cooldown_hours'] ?? 24);
            $data['allow_reoffer'] = (bool) ($config['allow_reoffer'] ?? false);
            $data['funnel_headline_template'] = (string) ($config['funnel_headline_template'] ?? '');
            $data['funnel_show_progress'] = (bool) ($config['funnel_show_progress'] ?? true);
            $data['funnel_step_labels'] = (string) ($config['funnel_step_labels'] ?? 'Order, Offer, Bonus, Done');
            $data['show_timer'] = (bool) ($config['show_timer'] ?? false);
            $data['timer_seconds'] = (int) ($config['timer_seconds'] ?? 300);
            $data['timer_label'] = (string) ($config['timer_label'] ?? 'For a limited time');
            $data['urgency_message'] = (string) ($config['urgency_message'] ?? '');
            $data['cta_text'] = (string) ($config['cta_text'] ?? 'Pay Now');
            $data['decline_text'] = (string) ($config['decline_text'] ?? 'Decline offer');
            $data['quantity_default'] = max(1, (int) ($config['quantity_default'] ?? 1));
            $data['quantity_min'] = max(1, (int) ($config['quantity_min'] ?? 1));
            $data['quantity_max'] = max(1, (int) ($config['quantity_max'] ?? 10));
        }

        $reserved = [
            'offer_ids', 'max_offers', 'display_mode', 'require_expanded',
            'section_heading', 'title_size', 'title_appearance', 'show_price', 'show_description',
            'image_aspect_ratio', 'image_fit', 'image_corner_radius', 'button_kind', 'button_appearance',
            'card_spacing', 'divider_between_cards',
            'progress_bar_enabled', 'progress_bar_type', 'progress_bar_goal', 'progress_bar_message_below',
            'progress_bar_message_achieved', 'progress_bar_discount_type', 'progress_bar_discount_value',
            'icon_features',
            'title', 'body', 'image_url', 'button_label', 'button_url', 'product_id', 'price_text', 'badge_text',
            'text_size', 'text_appearance', 'spacing',
            'cooldown_hours', 'allow_reoffer', 'funnel_headline_template', 'funnel_show_progress', 'funnel_step_labels',
            'show_timer', 'timer_seconds', 'timer_label', 'urgency_message', 'cta_text', 'decline_text',
            'quantity_default', 'quantity_min', 'quantity_max',
        ];
        $data['extra_config'] = [];
        foreach ($config as $key => $value) {
            if (! in_array($key, $reserved, true)) {
                $data['extra_config'][$key] = $value;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->widgetOffersData = is_array($data['widget_offers'] ?? null) ? $data['widget_offers'] : [];
        $data['config'] = CreateBlock::buildBlockConfig($data);
        CreateBlock::unsetConfigKeys($data);
        unset($data['widget_offers']);

        return $data;
    }

    protected function afterSave(): void
    {
        $block = $this->record;
        if (($block->surface === 'checkout' && $block->type === 'upsell') || ($block->surface === 'post_purchase' && $block->type === 'post_purchase_funnel')) {
            $block->blockOffers()->delete();
            CreateBlock::syncWidgetOffers($block, $this->widgetOffersData);
        }
    }

    /**
     * Build widget_offers form state from block's blockOffers->offer (for edit).
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function widgetOffersFromBlock(\App\Models\Block $block): array
    {
        $out = [];
        foreach ($block->blockOffers as $bo) {
            $offer = $bo->offer;
            if (! $offer instanceof Offer) {
                continue;
            }
            $conditions = $offer->rule?->conditions ?? [];
            $matchType = isset($conditions['or']) ? 'or' : 'and';
            $rows = $conditions[$matchType] ?? [];
            $ruleConditions = [];
            foreach ($rows as $cond) {
                foreach ($cond as $field => $value) {
                    $ruleConditions[] = ['field' => $field, 'value' => is_array($value) ? implode(',', $value) : $value];
                }
            }
            $out[] = [
                'product_variant_id' => $offer->product_variant_id,
                'variant_id_manual' => $offer->product_variant_id,
                'title' => $offer->title,
                'description' => $offer->description,
                'discount_type' => $offer->discount_type,
                'discount_value' => $offer->discount_value,
                'image_url' => $offer->image_url,
                'offer_type' => $offer->offer_type,
                'selling_plan_id' => $offer->selling_plan_id,
                'recharge_subscription_variant_id' => $offer->recharge_subscription_variant_id,
                'allow_subscription_in_post_purchase' => $offer->allow_subscription_in_post_purchase,
                'rule_match_type' => $matchType,
                'rule_conditions' => $ruleConditions,
            ];
        }
        return $out;
    }
}
