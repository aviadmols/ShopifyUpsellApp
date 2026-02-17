<?php

namespace App\Filament\Pages;

use App\Models\WidgetSessionEvent;
use App\Services\OpenRouterService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class WidgetSessionAISummary extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Logs';
    protected static ?string $navigationLabel = 'AI Session Summary';
    protected static ?string $title = 'AI Session Summary';

    protected static string $view = 'filament.pages.widget-session-ai-summary';

    /** Put it next to widget-session-events in the URL. */
    protected static ?string $slug = 'widget-session-events/ai-summary';

    public string $session_key = '';

    public ?string $summary = null;

    public ?string $debug_payload = null;

    public bool $loading = false;

    public function mount(): void
    {
        $this->session_key = '';
        $this->summary = null;
        $this->debug_payload = null;
        $this->loading = false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('session_key')
                    ->label('Session ID (session_key)')
                    ->helperText('Paste the session_key you see in Widget session logs. Example: a checkout token.')
                    ->required()
                    ->maxLength(255)
                    ->live(),
            ])
            ->statePath('')
            ->columns(1);
    }

    public function generate(): void
    {
        $this->validate([
            'session_key' => 'required|string|max:255',
        ]);

        $openRouter = app(OpenRouterService::class);
        if (! $openRouter->isConfigured()) {
            Notification::make()
                ->title('OpenRouter not configured')
                ->body('Set your API key in Developer → AI (OpenRouter).')
                ->danger()
                ->send();
            return;
        }

        $this->loading = true;
        $this->summary = null;

        $sessionKey = trim($this->session_key);

        $events = WidgetSessionEvent::query()
            ->with('shop')
            ->where('session_key', $sessionKey)
            ->orderBy('created_at')
            ->limit(300)
            ->get();

        if ($events->isEmpty()) {
            $this->loading = false;
            $this->summary = "No events found for session_key: {$sessionKey}";
            $this->debug_payload = null;
            return;
        }

        $shops = $events->pluck('shop.shop_domain')->filter()->unique()->values()->all();
        $clickTargets = $events->where('event_type', 'click')->pluck('click_target')->filter()->countBy()->all();
        $viewCount = (int) $events->where('event_type', 'view')->count();
        $clickCount = (int) $events->where('event_type', 'click')->count();

        $sanitizedEvents = $events->map(function (WidgetSessionEvent $e): array {
            $snap = is_array($e->context_snapshot ?? null) ? $e->context_snapshot : null;
            return [
                'id' => $e->id,
                'time' => $e->created_at?->toISOString(),
                'shop' => $e->shop?->shop_domain,
                'block_id' => $e->block_id,
                'event_type' => $e->event_type,
                'rule_passed' => $e->rule_passed,
                'widget_shown' => $e->widget_shown,
                'click_target' => $e->click_target,
                'context_snapshot' => $snap,
            ];
        })->all();

        $payload = [
            'session_key' => $sessionKey,
            'stats' => [
                'events_total' => count($sanitizedEvents),
                'views' => $viewCount,
                'clicks' => $clickCount,
                'shops' => $shops,
                'click_targets' => $clickTargets,
                'time_from' => $events->first()?->created_at?->toISOString(),
                'time_to' => $events->last()?->created_at?->toISOString(),
            ],
            'events' => $sanitizedEvents,
        ];

        $this->debug_payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $summary = $openRouter->summarizeWidgetSession($payload);
        $this->summary = $summary ?? 'AI summary failed. Check Developer → AI (OpenRouter) logs for details.';
        $this->loading = false;
    }

    protected function getFormStatePath(): ?string
    {
        return '';
    }
}

