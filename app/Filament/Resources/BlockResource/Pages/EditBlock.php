<?php

namespace App\Filament\Resources\BlockResource\Pages;

use App\Filament\Resources\BlockResource;
use App\Filament\Resources\CheckoutExperienceResource;
use App\Http\Controllers\Api\CheckoutUpsellController;
use App\Models\Block;
use App\Models\CheckoutExperience;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\Rule;
use App\Services\OpenRouterService;
use App\Services\RuleEngine;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

class EditBlock extends EditRecord
{
    protected static string $resource = BlockResource::class;

    /** @var array<string, mixed> */
    public array $blockPreviewData = [];

    /** @var array{log: string, summary: string|null}|null */
    public ?array $aiTestResult = null;

    /** @var array<int, array<string, mixed>> */
    public array $widgetOffersData = [];

    public string $refinePrompt = '';

    /** @var array{updated_rule_conditions: array, updated_php_snippet: string, updated_text_fields: array, explanation: string, warnings: array}|null */
    public ?array $refinePreview = null;

    public ?string $refineError = null;

    public function getSubheading(): ?string
    {
        if ($this->record && $this->record->surface === 'checkout' && $this->record->type === 'checkout_upgrade_card') {
            return 'Widget ID for Checkout: ' . $this->record->getKey() . ' — Set this as "Widget ID" in Shopify Partners (Extensions → Zyg Upgrade Card → Settings) and add the block in Checkout customization.';
        }

        return null;
    }

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
            Actions\Action::make('test_ai_widget')
                ->label('Test AI widget')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->visible(fn (): bool => $this->record !== null && (strlen($this->record->ai_generated_php ?? '') > 0 || $this->record->rule_id !== null))
                ->action(function (): void {
                    $this->aiTestResult = $this->runAiWidgetTest();
                })
                ->modalHeading('AI widget test')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): View => view('filament.components.ai-widget-test-result', [
                    'result' => $this->aiTestResult ?? ['log' => '', 'summary' => null],
                ])),
            Actions\Action::make('refine_with_ai')
                ->label('Refine with AI')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn (): bool => $this->record !== null && (strlen($this->record->ai_generated_php ?? '') > 0 || strlen($this->record->ai_generated_description ?? '') > 0 || $this->record->rule_id !== null))
                ->modalHeading('Refine widget logic')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): View => view('filament.components.refine-ai-modal', [
                    'refinePrompt' => $this->refinePrompt,
                    'refinePreview' => $this->refinePreview,
                    'refineError' => $this->refineError,
                ])),
            Actions\DeleteAction::make(),
        ];
    }

    protected function runAiWidgetTest(): array
    {
        $block = $this->record;
        $log = [];
        $log[] = 'Block ID: ' . ($block?->id ?? '—');
        $log[] = 'Surface: ' . ($block?->surface ?? '—');
        $log[] = 'Type: ' . ($block?->type ?? '—');
        $log[] = '';
        $conditions = $block?->rule?->conditions ?? [];
        if ($conditions === []) {
            $log[] = 'No rule conditions.';
            return ['log' => implode("\n", $log), 'summary' => null];
        }
        $engine = app(RuleEngine::class);
        $dummyContext = [
            'subtotal' => 150,
            'line_items' => [['product_id' => 123, 'variant_id' => 456, 'properties' => []]],
            'customer' => ['tags' => []],
            'shipping_country' => 'IL',
            'utms' => [],
            'url_params' => [],
        ];
        $passed = $engine->evaluate($conditions, $dummyContext);
        $log[] = 'Rule conditions: ' . json_encode($conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $log[] = '';
        $log[] = 'Dummy context: ' . json_encode($dummyContext, JSON_PRETTY_PRINT);
        $log[] = 'Result: ' . ($passed ? 'PASS' : 'FAIL');
        $summary = app(OpenRouterService::class)->summarizeTestResult([
            'conditions' => $conditions,
            'context' => $dummyContext,
            'result' => $passed ? 'pass' : 'fail',
            'log' => implode("\n", $log),
        ]);
        return ['log' => implode("\n", $log), 'summary' => $summary];
    }

    public function runRefinePreview(): void
    {
        $this->refineError = null;
        $this->refinePreview = null;

        $block = $this->record;
        if (! $block) {
            $this->refineError = 'No widget selected.';
            return;
        }

        $openRouter = app(OpenRouterService::class);
        if (! $openRouter->isConfigured()) {
            $this->refineError = 'OpenRouter is not configured. Set your API key in Developer → AI (OpenRouter).';
            return;
        }

        $snapshot = [
            'rule_conditions' => $block->rule?->conditions ?? [],
            'ai_generated_php' => (string) ($block->ai_generated_php ?? ''),
            'config' => is_array($block->config) ? $block->config : [],
            'surface' => (string) $block->surface,
            'type' => (string) $block->type,
        ];

        $result = $openRouter->refineWidget(trim($this->refinePrompt), $snapshot);
        if ($result === null) {
            $this->refineError = 'AI could not generate a refinement. Check your prompt and try again.';
            return;
        }

        $this->refinePreview = $result;
    }

    public function applyRefinePreview(): void
    {
        if (! $this->refinePreview || ! $this->record) {
            $this->clearRefinePreview();
            return;
        }

        $block = $this->record;
        $updatedRule = $this->refinePreview['updated_rule_conditions'] ?? [];
        $updatedPhp = (string) ($this->refinePreview['updated_php_snippet'] ?? '');
        $updatedText = $this->refinePreview['updated_text_fields'] ?? [];

        if ($updatedRule !== []) {
            $existingRule = $block->rule;
            if ($existingRule) {
                $existingRule->update(['conditions' => $updatedRule]);
            } else {
                $rule = Rule::create([
                    'shop_id' => $block->shop_id,
                    'name' => 'Widget rule ' . $block->id,
                    'conditions' => $updatedRule,
                ]);
                $block->update(['rule_id' => $rule->id]);
            }
        } else {
            $block->rule_id?->delete();
            $block->update(['rule_id' => null]);
        }

        $block->update(['ai_generated_php' => $updatedPhp]);

        if ($updatedText !== []) {
            $config = is_array($block->config) ? $block->config : [];
            foreach ($updatedText as $key => $value) {
                if (is_string($key) && $key !== '' && is_string($value)) {
                    $config[$key] = $value;
                }
            }
            $block->update(['config' => $config]);
        }

        $this->refinePreview = null;
        $this->refinePrompt = '';
        $this->refineError = null;

        $this->record->refresh();
        $this->form->fill($this->mutateFormDataBeforeFill($this->record->toArray()));

        \Filament\Notifications\Notification::make()
            ->title('Widget refined and saved')
            ->success()
            ->send();
    }

    public function clearRefinePreview(): void
    {
        $this->refinePreview = null;
        $this->refineError = null;
    }

    /**
     * Run checkout health check and return result for modal display.
     *
     * @return array{error?: bool, message?: string, success?: bool, count?: int, block_error?: string|null, display_settings?: array<string, mixed>, experience?: array<string, mixed>, ai?: array<string, mixed>, resolved_block_id?: int|null}
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
        $aiInsights = $this->getCheckoutAiInsights($block);
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
                'ai' => $aiInsights,
                'resolved_block_id' => isset($payload['resolved_block_id']) ? (int) $payload['resolved_block_id'] : null,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage().' in '.$e->getFile().':'.$e->getLine(),
                'experience' => $experienceSummary,
                'ai' => $aiInsights,
            ];
        }
    }

    /**
     * @return array{is_ai_generated: bool, current_block_id: int|null, checkout_ai_block_ids: array<int, int>}
     */
    protected function getCheckoutAiInsights(?Block $block): array
    {
        $shopId = $block?->shop_id;
        $isAiGenerated = strlen((string) ($block?->ai_generated_php ?? '')) > 0
            || strlen((string) ($block?->ai_generated_description ?? '')) > 0
            || strlen((string) ($block?->ai_prompt ?? '')) > 0;

        if (! $shopId) {
            return [
                'is_ai_generated' => $isAiGenerated,
                'current_block_id' => $block?->id,
                'checkout_ai_block_ids' => [],
            ];
        }

        $checkoutAiBlockIds = Block::query()
            ->where('shop_id', $shopId)
            ->where('surface', 'checkout')
            ->where(function ($query): void {
                $query->whereNotNull('ai_generated_php')
                    ->where('ai_generated_php', '!=', '')
                    ->orWhereNotNull('ai_generated_description')
                    ->where('ai_generated_description', '!=', '')
                    ->orWhereNotNull('ai_prompt')
                    ->where('ai_prompt', '!=', '');
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [
            'is_ai_generated' => $isAiGenerated,
            'current_block_id' => $block?->id,
            'checkout_ai_block_ids' => $checkoutAiBlockIds,
        ];
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
        $data['runtime_rule_conditions_json'] = $this->record->rule?->conditions
            ? (string) json_encode($this->record->rule->conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        $surface = (string) ($data['surface'] ?? '');
        $type = (string) ($data['type'] ?? '');

        if (isset($config['runtime_variables']) && is_array($config['runtime_variables'])) {
            $data['runtime_variables_json'] = json_encode($config['runtime_variables'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

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
        } elseif ($surface === 'checkout' && $type === 'checkout_upgrade_card') {
            $data['upgrade_card_headline'] = (string) ($config['headline'] ?? '');
            $data['upgrade_card_description'] = (string) ($config['description'] ?? '');
            $data['upgrade_card_cta_label'] = (string) ($config['cta_label'] ?? 'Upgrade');
            $data['upgrade_card_cart_subtotal_min'] = isset($config['cart_subtotal_min']) ? (string) $config['cart_subtotal_min'] : '';
            $data['upgrade_card_cart_items_count_min'] = isset($config['cart_items_count_min']) ? (string) $config['cart_items_count_min'] : '';
            $ui = is_array($config['ui'] ?? null) ? $config['ui'] : [];
            $data['upgrade_card_display_mode'] = (string) ($ui['display_mode'] ?? 'text');
            $data['upgrade_card_image_url'] = (string) ($ui['image_url'] ?? '');
            $data['upgrade_card_title_size'] = (string) ($ui['title_size'] ?? 'medium');
            $data['upgrade_card_button_kind'] = (string) ($ui['button_kind'] ?? 'secondary');
            $data['upgrade_card_spacing'] = (string) ($ui['spacing'] ?? 'tight');
            $data['upgrade_card_show_border'] = (bool) ($ui['show_border'] ?? true);
            $data['upgrade_card_border_radius'] = (string) ($ui['border_radius'] ?? 'base');
            $data['upgrade_card_padding'] = (string) ($ui['padding'] ?? 'base');
            $data['upgrade_card_show_items'] = (bool) ($ui['show_items'] ?? true);
            $data['upgrade_card_plan_label'] = (string) ($ui['plan_label'] ?? 'Plan');
            $data['upgrade_card_items_max_visible'] = (string) ($ui['items_max_visible'] ?? 3);
            $data['upgrade_card_plans'] = $config['plans'] ?? [];
            $data['upgrade_mappings_items'] = self::upgradeMappingsToFormItems($config['upgrade_mappings'] ?? []);

            // Backfill older AI-created widgets that saved empty config.
            if (trim((string) ($data['upgrade_card_headline'] ?? '')) === '' && $this->record?->ai_generated_name) {
                $data['upgrade_card_headline'] = \Illuminate\Support\Str::limit((string) $this->record->ai_generated_name, 80, '');
            }
            if (trim((string) ($data['upgrade_card_description'] ?? '')) === '' && $this->record?->ai_generated_description) {
                $data['upgrade_card_description'] = (string) $this->record->ai_generated_description;
            }
            if (trim((string) ($data['upgrade_card_cta_label'] ?? '')) === '') {
                $data['upgrade_card_cta_label'] = 'Upgrade';
            }
            if ((is_array($data['upgrade_mappings_items'] ?? null) ? $data['upgrade_mappings_items'] : []) === []) {
                $inferred = self::inferUpgradeMappingsFromAi($this->record);
                if ($inferred !== []) {
                    $data['upgrade_mappings_items'] = $inferred;
                }
            }
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
            'headline', 'description', 'cta_label', 'upgrade_mappings', 'plans', 'cart_subtotal_min', 'cart_items_count_min',
            'ui',
            'runtime_variables', 'runtimeVariables',
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
        $rawRuleJson = trim((string) ($data['runtime_rule_conditions_json'] ?? ''));
        if ($rawRuleJson === '') {
            $data['rule_id'] = null;
        } else {
            try {
                /** @var mixed $decoded */
                $decoded = json_decode($rawRuleJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'runtime_rule_conditions_json' => 'Invalid JSON: ' . $e->getMessage(),
                ]);
            }

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'runtime_rule_conditions_json' => 'Rule conditions JSON must be an object/array.',
                ]);
            }

            $existingRule = $this->record->rule;
            if ($existingRule) {
                $existingRule->update(['conditions' => $decoded]);
                $data['rule_id'] = $existingRule->id;
            } else {
                $rule = Rule::create([
                    'shop_id' => $this->record->shop_id,
                    'name' => 'Widget rule ' . $this->record->id,
                    'conditions' => $decoded,
                ]);
                $data['rule_id'] = $rule->id;
            }
        }

        // Upgrade card requires at least one match condition per mapping, otherwise it can never be enabled.
        $surface = (string) ($data['surface'] ?? $this->record?->surface ?? '');
        $type = (string) ($data['type'] ?? $this->record?->type ?? '');
        if ($surface === 'checkout' && $type === 'checkout_upgrade_card') {
            $items = is_array($data['upgrade_mappings_items'] ?? null) ? $data['upgrade_mappings_items'] : [];
            foreach ($items as $m) {
                if (! is_array($m)) {
                    continue;
                }
                $hasAnyMatch =
                    trim((string) ($m['match_product_id'] ?? '')) !== '' ||
                    trim((string) ($m['match_variant_id'] ?? '')) !== '' ||
                    trim((string) ($m['match_sku_regex'] ?? '')) !== '' ||
                    trim((string) ($m['match_sku_segment'] ?? '')) !== '' ||
                    trim((string) ($m['match_line_item_property_exists'] ?? '')) !== '' ||
                    (! empty($m['match_line_item_property_equals']) && is_array($m['match_line_item_property_equals']));

                if (! $hasAnyMatch) {
                    throw ValidationException::withMessages([
                        'upgrade_mappings_items' => 'Each upgrade mapping must include at least one Match condition (product ID, variant ID, SKU, or property).',
                    ]);
                }
            }
        }

        $this->widgetOffersData = is_array($data['widget_offers'] ?? null) ? $data['widget_offers'] : [];
        $data['config'] = CreateBlock::buildBlockConfig($data);
        CreateBlock::unsetConfigKeys($data);
        unset($data['widget_offers']);
        unset($data['runtime_rule_conditions_json']);

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
     * Convert stored upgrade_mappings config to form repeater items.
     *
     * @param  array<int, array<string, mixed>>  $mappings
     * @return array<int, array<string, mixed>>
     */
    protected static function upgradeMappingsToFormItems(array $mappings): array
    {
        $out = [];
        foreach ($mappings as $m) {
            if (! is_array($m)) {
                continue;
            }
            $match = $m['match'] ?? [];
            $plansList = is_array($m['plans'] ?? null) ? $m['plans'] : [];
            $firstPlanSellingPlanId = isset($plansList[0]) && is_array($plansList[0])
                ? (string) ($plansList[0]['selling_plan_id'] ?? '')
                : '';
            $mappingSellingPlanId = trim((string) ($m['selling_plan_id'] ?? ''));
            $effectiveSellingPlanId = $firstPlanSellingPlanId !== '' ? $firstPlanSellingPlanId : $mappingSellingPlanId;
            $item = [
                'match_product_id' => (string) ($match['product_id'] ?? ''),
                'match_variant_id' => (string) ($match['variant_id'] ?? ''),
                'match_sku_regex' => (string) ($match['sku_regex'] ?? ''),
                'match_sku_segment' => (string) ($match['sku_segment'] ?? ''),
                'match_line_item_property_exists' => (string) ($match['line_item_property_exists'] ?? ''),
                'match_line_item_property_equals' => is_array($match['line_item_property_equals'] ?? null) ? \App\Filament\Resources\BlockResource\Pages\CreateBlock::filterSubscriptionKeysFromPropertyEquals($match['line_item_property_equals']) : [],
                'match_quantity_min' => isset($match['quantity_min']) ? (string) $match['quantity_min'] : '',
                'match_quantity_max' => isset($match['quantity_max']) ? (string) $match['quantity_max'] : '',
                'match_subscription' => (string) ($match['subscription'] ?? 'any'),
                'match_selling_plan_id' => (string) ($match['selling_plan_id'] ?? ''),
                'action_type' => (string) ($m['action_type'] ?? 'subscription'),
                'target_variant_id' => (string) ($m['target_variant_id'] ?? ''),
                'selling_plan_id' => $effectiveSellingPlanId,
                'mapping_headline' => (string) ($m['headline'] ?? ''),
                'mapping_description' => (string) ($m['description'] ?? ''),
                'mapping_cta_label' => (string) ($m['cta_label'] ?? ''),
                'mapping_display_mode' => (string) ($m['display_mode'] ?? 'text'),
                'mapping_image_url' => (string) ($m['image_url'] ?? ''),
                'mapping_title_size' => (string) (($m['ui'] ?? [])['title_size'] ?? ''),
                'mapping_button_kind' => (string) (($m['ui'] ?? [])['button_kind'] ?? ''),
                'mapping_spacing' => (string) (($m['ui'] ?? [])['spacing'] ?? ''),
                'mapping_show_border' => array_key_exists('show_border', (array) ($m['ui'] ?? [])) ? (bool) ($m['ui']['show_border']) : null,
                'mapping_border_radius' => (string) (($m['ui'] ?? [])['border_radius'] ?? ''),
                'mapping_padding' => (string) (($m['ui'] ?? [])['padding'] ?? ''),
                'mapping_show_items' => array_key_exists('show_items', (array) ($m['ui'] ?? [])) ? (bool) ($m['ui']['show_items']) : null,
                'mapping_plan_label' => (string) (($m['ui'] ?? [])['plan_label'] ?? ''),
                'mapping_items_max_visible' => isset(($m['ui'] ?? [])['items_max_visible']) ? (int) ($m['ui']['items_max_visible']) : null,
                'quantity' => (int) ($m['quantity'] ?? 1),
                'plans' => $plansList,
            ];
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Best-effort inference for older AI-created upgrade cards that saved empty config.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function inferUpgradeMappingsFromAi(?Block $record): array
    {
        if (! $record) {
            return [];
        }

        $php = (string) ($record->ai_generated_php ?? '');
        if (trim($php) === '') {
            return [];
        }

        $matchVariantId = '';
        $conditions = $record->rule?->conditions ?? [];
        if (is_array($conditions)) {
            $groupKey = isset($conditions['or']) ? 'or' : 'and';
            $rows = is_array($conditions[$groupKey] ?? null) ? $conditions[$groupKey] : [];
            foreach ($rows as $cond) {
                if (! is_array($cond)) {
                    continue;
                }
                if (isset($cond['line_items_has_variant_id'])) {
                    $matchVariantId = (string) $cond['line_items_has_variant_id'];
                    break;
                }
                if (isset($cond['line_items_has_product_id']) && $matchVariantId === '') {
                    $matchVariantId = (string) $cond['line_items_has_product_id'];
                }
            }
        }

        if ($matchVariantId === '' && preg_match("/variant_id\\s*={2,3}\\s*'([^']+)'/i", $php, $m)) {
            $matchVariantId = (string) ($m[1] ?? '');
        }

        $targetVariantId = '';
        if (preg_match('/addCartLine[\\s\\S]*?ProductVariant\\/(\\d+)/i', $php, $m)) {
            $targetVariantId = (string) ($m[1] ?? '');
        } elseif (preg_match_all('/ProductVariant\\/(\\d+)/', $php, $mm) && ! empty($mm[1])) {
            $targetVariantId = (string) end($mm[1]);
        }

        $sellingPlanId = '';
        if (preg_match('/SellingPlan\\/(\\d+)/', $php, $m)) {
            $sellingPlanId = 'gid://shopify/SellingPlan/' . (string) ($m[1] ?? '');
        }

        $matchVariantId = trim($matchVariantId);
        $targetVariantId = trim($targetVariantId);
        if ($matchVariantId === '' || $targetVariantId === '') {
            return [];
        }

        $plans = [];
        if ($sellingPlanId !== '') {
            $plans[] = [
                'id' => 'default',
                'label' => 'Subscribe',
                'target_variant_id' => $targetVariantId,
                'selling_plan_id' => $sellingPlanId,
            ];
        }

        return [[
            'match_product_id' => '',
            'match_variant_id' => $matchVariantId,
            'match_sku_regex' => '',
            'match_sku_segment' => '',
            'match_line_item_property_exists' => '',
            'match_line_item_property_equals' => [],
            'action_type' => 'subscription',
            'target_variant_id' => $targetVariantId,
            'selling_plan_id' => $sellingPlanId,
            'quantity' => 1,
            'plans' => $plans,
        ]];
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
