<?php

namespace App\Filament\Resources\WidgetSessionEventResource\Pages;

use App\Filament\Resources\WidgetSessionEventResource;
use App\Models\Block;
use App\Services\CartLineUpgradeMatcher;
use App\Services\OpenRouterService;
use App\Services\RuleEngine;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View;

class ViewWidgetSessionEvent extends ViewRecord
{
    protected static string $resource = WidgetSessionEventResource::class;

    public ?string $aiDiagnosisResult = null;

    /** @var array<string, mixed>|null */
    public ?array $aiDiagnosisPayload = null;

    public ?string $aiDiagnosisError = null;

    public bool $aiDiagnosisLoading = false;

    public function mutateFormDataBeforeFill(array $data): array
    {
        $data['shop_domain'] = $this->record->shop_domain;
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('ai_diagnosis')
                ->label('AI Diagnosis')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->modalHeading('Widget visibility diagnosis')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): View => view('filament.actions.widget-session-ai-diagnosis', [
                    'diagnosis' => $this->aiDiagnosisResult,
                    'payload' => $this->aiDiagnosisPayload,
                    'error' => $this->aiDiagnosisError,
                    'loading' => $this->aiDiagnosisLoading,
                ])),
        ];
    }

    public function runAiDiagnosis(): void
    {
        $this->aiDiagnosisResult = null;
        $this->aiDiagnosisPayload = null;
        $this->aiDiagnosisError = null;
        $this->aiDiagnosisLoading = true;

        $event = $this->record;
        $blockId = $event->block_id;
        if (! $blockId) {
            $this->aiDiagnosisError = 'No block_id on this event.';
            $this->aiDiagnosisLoading = false;
            return;
        }

        $block = Block::with('rule')->find($blockId);
        if (! $block) {
            $this->aiDiagnosisError = 'Block not found.';
            $this->aiDiagnosisLoading = false;
            return;
        }

        $openRouter = app(OpenRouterService::class);
        if (! $openRouter->isConfigured()) {
            Notification::make()
                ->title('OpenRouter not configured')
                ->body('Set your API key in Developer → AI (OpenRouter).')
                ->danger()
                ->send();
            $this->aiDiagnosisError = 'OpenRouter is not configured. Set your API key in Developer → AI (OpenRouter).';
            $this->aiDiagnosisLoading = false;
            return;
        }

        $context = $this->buildContextFromSnapshot($event->context_snapshot ?? []);

        $rule = $block->rule;
        $rulePassedExpected = true;
        $ruleReasons = [];
        if ($block->rule_id && $rule) {
            $rulePassedExpected = app(RuleEngine::class)->evaluate($rule->conditions ?? [], $context);
            $ruleReasons[] = $rulePassedExpected ? 'Rule passed' : 'Rule failed';
        } else {
            $ruleReasons[] = 'No rule attached';
        }

        $upgradeCardEnabledExpected = null;
        $itemsCountExpected = null;
        $actionsCountExpected = null;
        if (strtolower((string) $block->type) === 'checkout_upgrade_card') {
            $matcher = app(CartLineUpgradeMatcher::class);
            $payload = $matcher->run($block->config ?? [], $context);
            $upgradeCardEnabledExpected = $payload['enabled'] ?? false;
            $itemsCountExpected = count($payload['items'] ?? []);
            $actionsCountExpected = count($payload['actions'] ?? []);
        }

        $payload = [
            'block' => [
                'id' => $block->id,
                'type' => $block->type,
                'surface' => $block->surface,
            ],
            'rule' => [
                'rule_id' => $block->rule_id,
                'conditions' => $rule?->conditions ?? [],
                'match_type' => 'and',
            ],
            'context_snapshot' => $context,
            'computed_diagnostics' => [
                'rule_passed_expected' => $rulePassedExpected,
                'rule_reasons' => $ruleReasons,
                'upgrade_card_enabled_expected' => $upgradeCardEnabledExpected,
                'items_count_expected' => $itemsCountExpected,
                'actions_count_expected' => $actionsCountExpected,
            ],
            'stored_in_event' => [
                'widget_shown' => $event->widget_shown,
                'rule_passed' => $event->rule_passed,
            ],
        ];

        $this->aiDiagnosisPayload = $payload;
        $diagnosis = $openRouter->diagnoseWidgetSessionEventVisibility($payload);
        if ($diagnosis !== null) {
            $this->aiDiagnosisResult = $diagnosis;
        } else {
            $this->aiDiagnosisError = 'AI diagnosis failed. Check Developer → AI logs for details.';
        }
        $this->aiDiagnosisLoading = false;
    }

    /**
     * Build context array for RuleEngine and CartLineUpgradeMatcher from stored context_snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function buildContextFromSnapshot(array $snapshot): array
    {
        $lineItems = $snapshot['line_items'] ?? [];
        if (! is_array($lineItems)) {
            $lineItems = [];
        }
        return [
            'subtotal' => $snapshot['subtotal'] ?? 0,
            'line_items' => $lineItems,
            'customer' => $snapshot['customer'] ?? [],
            'shipping_country' => $snapshot['shipping_country'] ?? null,
            'utms' => $snapshot['utms'] ?? [],
            'url_params' => $snapshot['url_params'] ?? [],
            'checkout_attributes' => $snapshot['checkout_attributes'] ?? [],
        ];
    }
}
