<?php

namespace App\Filament\Resources\BlockResource\Pages;

use App\Filament\Forms\Components\RuleBuilder;
use App\Filament\Resources\BlockResource;
use App\Filament\Widgets\WidgetRegistry;
use App\Models\Block;
use App\Models\BlockOffer;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Rule;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

class CreateBlock extends CreateRecord
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
                    return view('filament.components.block-preview', $data);
                }),
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
        $config = self::buildBlockConfig($state);
        $previewOffers = [];
        if (($surface === 'checkout' && $type === 'upsell') || ($surface === 'post_purchase' && $type === 'post_purchase_funnel')) {
            $shopId = $state['shop_id'] ?? null;
            $widgetOffers = is_array($state['widget_offers'] ?? null) ? $state['widget_offers'] : [];
            $previewOffers = self::enrichPreviewOffers($shopId, $widgetOffers);
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
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = (string) ($data['type'] ?? '');
        $shopId = $data['shop_id'] ?? null;
        $surface = (string) ($data['surface'] ?? '');
        if (WidgetRegistry::isSingleton($type) && $shopId && $surface) {
            $exists = Block::where('shop_id', $shopId)->where('surface', $surface)->where('type', $type)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'type' => __('This widget type can only be created once per shop. One already exists for this shop.'),
                ]);
            }
        }
        $this->widgetOffersData = is_array($data['widget_offers'] ?? null) ? $data['widget_offers'] : [];
        $data['config'] = self::buildBlockConfig($data);
        self::unsetConfigKeys($data);
        unset($data['widget_offers']);

        return $data;
    }

    protected function afterCreate(): void
    {
        self::syncWidgetOffers($this->record, $this->widgetOffersData);
    }

    /**
     * Create/update offers from widget_offers repeater, sync block_offers pivot, update block config.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function syncWidgetOffers(Block $block, array $items): void
    {
        $shopId = $block->shop_id;
        if (! $shopId) {
            return;
        }
        $offerIds = [];
        $sortOrder = 0;
        foreach ($items as $item) {
            $variantId = trim((string) ($item['variant_id_manual'] ?? ''));
            if ($variantId === '') {
                $variantId = $item['product_variant_id'] ?? null;
            }
            if (! $variantId) {
                continue;
            }
            $conditions = RuleBuilder::buildConditionsFromState($item);
            $ruleId = null;
            if (! empty($conditions)) {
                $rule = Rule::create([
                    'shop_id' => $shopId,
                    'name' => 'Widget offer rule ' . substr(md5(serialize($item)), 0, 8),
                    'conditions' => $conditions,
                ]);
                $ruleId = $rule->id;
            }
            $offer = Offer::create([
                'shop_id' => $shopId,
                'rule_id' => $ruleId,
                'title' => (string) ($item['title'] ?? 'Upsell offer'),
                'description' => (string) ($item['description'] ?? ''),
                'product_variant_id' => is_string($variantId) || is_numeric($variantId) ? (string) $variantId : '',
                'discount_type' => (string) ($item['discount_type'] ?? 'none'),
                'discount_value' => (float) ($item['discount_value'] ?? 0),
                'image_url' => (string) ($item['image_url'] ?? ''),
                'offer_type' => (string) ($item['offer_type'] ?? 'one_time'),
                'selling_plan_id' => (string) ($item['selling_plan_id'] ?? ''),
                'recharge_subscription_variant_id' => (string) ($item['recharge_subscription_variant_id'] ?? ''),
                'allow_subscription_in_post_purchase' => (bool) ($item['allow_subscription_in_post_purchase'] ?? false),
            ]);
            $offerIds[] = $offer->id;
            BlockOffer::create([
                'block_id' => $block->id,
                'offer_id' => $offer->id,
                'sort_order' => $sortOrder++,
            ]);
        }
        if ($offerIds !== []) {
            $config = $block->config ?? [];
            $config['offer_ids'] = $offerIds;
            $block->update(['config' => $config]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function buildBlockConfig(array $data): array
    {
        $surface = (string) ($data['surface'] ?? '');
        $type = (string) ($data['type'] ?? '');
        $extra = is_array($data['extra_config'] ?? null) ? $data['extra_config'] : [];
        $config = [];

        if ($surface === 'checkout' && $type === 'upsell') {
            $offerIds = ! empty($data['widget_offers']) ? [] : Placement::normalizeIntList((string) ($data['offer_ids_csv'] ?? ''));
            $config = [
                'offer_ids' => $offerIds,
                'max_offers' => max(1, (int) ($data['max_offers'] ?? 3)),
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
                'show_quantity' => (bool) ($data['show_quantity'] ?? true),
            ];
        } elseif ($surface === 'checkout' && $type === 'progress_bar') {
            $config = [
                'progress_bar_enabled' => true,
                'progress_bar_type' => (string) ($data['progress_bar_type'] ?? 'free_shipping'),
                'progress_bar_goal' => max(0.01, (float) ($data['progress_bar_goal'] ?? 100)),
                'progress_bar_message_below' => (string) ($data['progress_bar_message_below'] ?? "You're {amount} away from free shipping!"),
                'progress_bar_message_achieved' => (string) ($data['progress_bar_message_achieved'] ?? "You've unlocked free shipping!"),
                'progress_bar_discount_type' => (string) ($data['progress_bar_discount_type'] ?? 'percentage'),
                'progress_bar_discount_value' => max(0, (float) ($data['progress_bar_discount_value'] ?? 0)),
            ];
        } elseif ($type === 'content_icon_features') {
            $items = $data['icon_features_items'] ?? [];
            $config = [
                'icon_features' => is_array($items) ? array_values(array_filter($items, fn ($i) => ! empty($i['title']))) : [],
            ];
        } elseif (in_array($type, ['content_banner', 'content_rich_text', 'content_button'], true)) {
            $config = [
                'title' => (string) ($data['title'] ?? ''),
                'body' => (string) ($data['body'] ?? ''),
                'image_url' => (string) ($data['image_url'] ?? ''),
                'button_label' => (string) ($data['button_label'] ?? ''),
                'button_url' => (string) ($data['button_url'] ?? ''),
                'text_size' => (string) ($data['text_size'] ?? 'medium'),
                'text_appearance' => (string) ($data['text_appearance'] ?? 'default'),
                'button_kind' => (string) ($data['button_kind'] ?? 'secondary'),
                'spacing' => (string) ($data['spacing'] ?? 'tight'),
            ];
        } elseif ($type === 'content_product_card') {
            $config = [
                'title' => (string) ($data['title'] ?? ''),
                'body' => (string) ($data['body'] ?? ''),
                'image_url' => (string) ($data['image_url'] ?? ''),
                'product_id' => (string) ($data['product_id'] ?? ''),
                'price_text' => (string) ($data['price_text'] ?? ''),
                'badge_text' => (string) ($data['badge_text'] ?? ''),
                'show_price' => (bool) ($data['show_price'] ?? true),
                'button_label' => (string) ($data['button_label'] ?? ''),
                'button_url' => (string) ($data['button_url'] ?? ''),
                'text_size' => (string) ($data['text_size'] ?? 'medium'),
                'button_kind' => (string) ($data['button_kind'] ?? 'secondary'),
                'spacing' => (string) ($data['spacing'] ?? 'tight'),
            ];
        } elseif ($surface === 'post_purchase' && $type === 'post_purchase_funnel') {
            $offerIds = ! empty($data['widget_offers']) ? [] : Placement::normalizeIntList((string) ($data['offer_ids_csv'] ?? ''));
            $config = [
                'offer_ids' => $offerIds,
                'max_offers' => max(1, (int) ($data['max_offers'] ?? 3)),
                'cooldown_hours' => max(0, (int) ($data['cooldown_hours'] ?? 24)),
                'allow_reoffer' => (bool) ($data['allow_reoffer'] ?? false),
                'funnel_headline_template' => (string) ($data['funnel_headline_template'] ?? ''),
                'funnel_show_progress' => (bool) ($data['funnel_show_progress'] ?? true),
                'funnel_step_labels' => (string) ($data['funnel_step_labels'] ?? 'Order, Offer, Bonus, Done'),
                'show_timer' => (bool) ($data['show_timer'] ?? false),
                'timer_seconds' => max(0, (int) ($data['timer_seconds'] ?? 300)),
                'timer_label' => (string) ($data['timer_label'] ?? 'For a limited time'),
                'urgency_message' => (string) ($data['urgency_message'] ?? ''),
                'cta_text' => (string) ($data['cta_text'] ?? 'Pay Now'),
                'decline_text' => (string) ($data['decline_text'] ?? 'Decline offer'),
                'quantity_default' => max(1, (int) ($data['quantity_default'] ?? 1)),
                'quantity_min' => max(1, (int) ($data['quantity_min'] ?? 1)),
                'quantity_max' => max(1, (int) ($data['quantity_max'] ?? 10)),
            ];
        }

        foreach ($extra as $key => $value) {
            if ($key !== '' && $value !== null && $value !== '') {
                $config[(string) $key] = $value;
            }
        }

        return $config;
    }

    /**
     * Build full form state from Get (for live preview). Reads all keys used by buildBlockConfig.
     *
     * @return array<string, mixed>
     */
    /**
     * Enrich widget_offers with product/variant data from Shopify for preview.
     *
     * @param  int|string|null  $shopId
     * @param  array<int, array<string, mixed>>  $widgetOffers
     * @return array<int, array{title: string, description: string, image_url: string, price: string}>
     */
    public static function enrichPreviewOffers($shopId, array $widgetOffers): array
    {
        if (! $shopId) {
            return [];
        }
        $shop = \App\Models\Shop::find((int) $shopId);
        if (! $shop) {
            return [];
        }
        $service = app(\App\Services\ShopifyGraphQLService::class);
        $max = 5;
        $out = [];
        foreach ($widgetOffers as $i => $item) {
            if (count($out) >= $max) {
                break;
            }
            $variantId = trim((string) ($item['variant_id_manual'] ?? ''));
            if ($variantId === '') {
                $variantId = $item['product_variant_id'] ?? null;
            }
            if (! $variantId) {
                continue;
            }
            $gid = str_starts_with($variantId, 'gid://') ? $variantId : 'gid://shopify/ProductVariant/'.preg_replace('/\D/', '', $variantId);
            if ($gid === 'gid://shopify/ProductVariant/') {
                continue;
            }
            try {
                $variant = $service->getProductVariant($shop, $gid);
            } catch (\Throwable) {
                $variant = null;
            }
            $title = (string) ($item['title'] ?? '');
            $description = (string) ($item['description'] ?? '');
            $imageUrl = (string) ($item['image_url'] ?? '');
            $price = '—';
            if ($variant) {
                if ($title === '') {
                    $productTitle = (string) ($variant['product']['title'] ?? 'Product');
                    $variantTitle = (string) ($variant['title'] ?? '');
                    $title = strtolower($variantTitle) !== 'default title' ? $productTitle.' - '.$variantTitle : $productTitle;
                }
                if ($imageUrl === '') {
                    $imageUrl = (string) ($variant['product']['featuredImage']['url'] ?? '');
                }
                $price = isset($variant['price']) ? (string) $variant['price'] : '—';
            } else {
                if ($title === '') {
                    $title = 'Offer '.(count($out) + 1);
                }
            }
            $out[] = [
                'title' => $title,
                'description' => $description,
                'image_url' => $imageUrl,
                'price' => $price,
            ];
        }

        return $out;
    }

    public static function getStateFromGet(\Filament\Forms\Get $get): array
    {
        $keys = [
            'surface', 'type', 'shop_id', 'extra_config', 'offer_ids_csv', 'widget_offers',
            'max_offers', 'display_mode', 'require_expanded', 'section_heading', 'title_size', 'title_appearance',
            'show_price', 'show_description', 'image_aspect_ratio', 'image_fit', 'image_corner_radius',
            'button_kind', 'button_appearance', 'card_spacing', 'divider_between_cards',
            'progress_bar_type', 'progress_bar_goal', 'progress_bar_message_below', 'progress_bar_message_achieved',
            'progress_bar_discount_type', 'progress_bar_discount_value',
            'icon_features_items',
            'title', 'body', 'image_url', 'button_label', 'button_url', 'text_size', 'text_appearance', 'spacing',
            'product_id', 'price_text', 'badge_text',
            'cooldown_hours', 'allow_reoffer', 'funnel_headline_template', 'funnel_show_progress', 'funnel_step_labels',
            'show_timer', 'timer_seconds', 'timer_label', 'urgency_message', 'cta_text', 'decline_text',
            'quantity_default', 'quantity_min', 'quantity_max',
        ];
        $state = [];
        foreach ($keys as $key) {
            $state[$key] = $get($key);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function unsetConfigKeys(array &$data): void
    {
        $keys = [
            'offer_ids_csv', 'max_offers', 'display_mode', 'require_expanded',
            'section_heading', 'title_size', 'title_appearance', 'show_price', 'show_description',
            'image_aspect_ratio', 'image_fit', 'image_corner_radius', 'button_kind', 'button_appearance',
            'card_spacing', 'divider_between_cards',
            'progress_bar_type', 'progress_bar_goal', 'progress_bar_message_below', 'progress_bar_message_achieved',
            'progress_bar_discount_type', 'progress_bar_discount_value',
            'icon_features_items',
            'title', 'body', 'image_url', 'button_label', 'button_url', 'text_size', 'text_appearance', 'spacing',
            'product_id', 'price_text', 'badge_text',
            'cooldown_hours', 'allow_reoffer', 'funnel_headline_template', 'funnel_show_progress', 'funnel_step_labels',
            'show_timer', 'timer_seconds', 'timer_label', 'urgency_message', 'cta_text', 'decline_text',
            'quantity_default', 'quantity_min', 'quantity_max',
            'extra_config', 'widget_offers',
        ];
        foreach ($keys as $key) {
            unset($data[$key]);
        }
    }
}
