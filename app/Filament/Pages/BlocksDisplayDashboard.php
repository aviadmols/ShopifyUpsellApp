<?php

namespace App\Filament\Pages;

use App\Models\Block;
use App\Models\WidgetSessionEvent;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class BlocksDisplayDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Logs';

    protected static ?string $navigationLabel = 'Blocks display';

    protected static ?string $title = 'Blocks display dashboard';

    protected static string $view = 'filament.pages.blocks-display-dashboard';

    protected static ?string $slug = 'blocks-display-dashboard';

    protected static ?int $navigationSort = 1;

    public string $ig_test_groups = '';

    public string $date_preset = '';

    public string $date_from = '';

    public string $date_to = '';

    public function mount(): void
    {
        $this->ig_test_groups = (string) request()->query('ig_test_groups', '');
        $this->date_preset = (string) request()->query('date_preset', '');
        $this->date_from = (string) request()->query('date_from', '');
        $this->date_to = (string) request()->query('date_to', '');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function getDateRange(): ?array
    {
        $preset = $this->date_preset ?? '';
        $tz = config('app.timezone', 'UTC');
        if ($preset === 'today') {
            return [Carbon::now($tz)->startOfDay(), Carbon::now($tz)->copy()->endOfDay()];
        }
        if ($preset === 'last7') {
            return [Carbon::now($tz)->subDays(6)->startOfDay(), Carbon::now($tz)->copy()->endOfDay()];
        }
        if ($preset === 'last30') {
            return [Carbon::now($tz)->subDays(29)->startOfDay(), Carbon::now($tz)->copy()->endOfDay()];
        }
        if ($preset === 'custom') {
            $fromRaw = $this->date_from ?? '';
            $toRaw = $this->date_to ?? '';
            $fromStr = $fromRaw instanceof Carbon ? $fromRaw->format('Y-m-d') : trim((string) $fromRaw);
            $toStr = $toRaw instanceof Carbon ? $toRaw->format('Y-m-d') : trim((string) $toRaw);
            if ($fromStr !== '' && $toStr !== '') {
                $from = Carbon::parse($fromStr, $tz)->startOfDay();
                $to = Carbon::parse($toStr, $tz)->endOfDay();

                return [$from, $to];
            }
        }

        return null;
    }

    public function getDateRangeLabel(): string
    {
        $range = $this->getDateRange();
        if ($range === null) {
            return 'All time';
        }

        return $range[0]->format('Y-m-d') . ' to ' . $range[1]->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('date_preset')
                    ->label('Date range')
                    ->options([
                        '' => 'All time',
                        'today' => 'Today',
                        'last7' => 'Last 7 days',
                        'last30' => 'Last 30 days',
                        'custom' => 'Custom range',
                    ])
                    ->live(debounce: 300),
                DatePicker::make('date_from')
                    ->label('From date')
                    ->visible(fn () => ($this->date_preset ?? '') === 'custom')
                    ->live(debounce: 300),
                DatePicker::make('date_to')
                    ->label('To date')
                    ->visible(fn () => ($this->date_preset ?? '') === 'custom')
                    ->live(debounce: 300),
                TextInput::make('ig_test_groups')
                    ->label('igTestGroups (optional filter)')
                    ->placeholder('e.g. f515f4f90f42')
                    ->maxLength(64)
                    ->helperText('Filter by checkout_attributes.igTestGroups from session logs. Leave empty for all sessions.')
                    ->live(debounce: 500),
            ])
            ->statePath('')
            ->columns(1);
    }

    private function applyDateRange($query)
    {
        $range = $this->getDateRange();
        if ($range !== null) {
            $query->whereBetween('created_at', $range);
        }

        return $query;
    }

    /**
     * @return array{total_sessions: int, sessions_viewed: int, sessions_clicked: int}
     */
    public function getSessionsSummary(): array
    {
        $filter = trim($this->ig_test_groups ?? '');
        $filtered = $filter !== '';

        $baseQuery = function () use ($filter, $filtered) {
            $q = WidgetSessionEvent::query()->whereNotNull('session_key');
            $this->applyDateRange($q);
            if ($filtered) {
                $q->whereIgTestGroups($filter);
            }

            return $q;
        };

        $totalSessions = (int) ((clone $baseQuery())->selectRaw('count(distinct session_key) as c')->value('c') ?? 0);

        $viewedQuery = WidgetSessionEvent::query()
            ->where('event_type', 'view')
            ->where('widget_shown', true)
            ->whereNotNull('session_key');
        $this->applyDateRange($viewedQuery);
        if ($filtered) {
            $viewedQuery->whereIgTestGroups($filter);
        }
        $sessionsViewed = (int) ($viewedQuery->selectRaw('count(distinct session_key) as c')->value('c') ?? 0);

        $clickQuery = WidgetSessionEvent::query()
            ->where('event_type', 'click')
            ->whereNotNull('session_key');
        $this->applyDateRange($clickQuery);
        if ($filtered) {
            $sessionKeys = WidgetSessionEvent::query()
                ->where('event_type', 'view')
                ->whereIgTestGroups($filter)
                ->whereNotNull('session_key');
            $this->applyDateRange($sessionKeys);
            $keys = $sessionKeys->distinct()->pluck('session_key')->filter()->values()->all();
            if (empty($keys)) {
                $sessionsClicked = 0;
            } else {
                $sessionsClicked = (int) ((clone $clickQuery)->whereIn('session_key', $keys)->selectRaw('count(distinct session_key) as c')->value('c') ?? 0);
            }
        } else {
            $sessionsClicked = (int) ($clickQuery->selectRaw('count(distinct session_key) as c')->value('c') ?? 0);
        }

        return [
            'total_sessions' => $totalSessions,
            'sessions_viewed' => $sessionsViewed,
            'sessions_clicked' => $sessionsClicked,
        ];
    }

    /**
     * @return array{total_displays: int, total_clicks: int, filtered: bool}
     */
    public function getSummary(): array
    {
        $filter = trim($this->ig_test_groups ?? '');
        $filtered = $filter !== '';

        $displaysQuery = WidgetSessionEvent::query()
            ->where('event_type', 'view')
            ->where('widget_shown', true);
        $this->applyDateRange($displaysQuery);
        if ($filtered) {
            $displaysQuery->whereIgTestGroups($filter);
        }
        $totalDisplays = (int) $displaysQuery->count();

        if (! $filtered) {
            $clicksQuery = WidgetSessionEvent::query()->where('event_type', 'click');
            $this->applyDateRange($clicksQuery);
            $totalClicks = (int) $clicksQuery->count();
        } else {
            $sessionKeysQuery = WidgetSessionEvent::query()
                ->where('event_type', 'view')
                ->whereIgTestGroups($filter)
                ->whereNotNull('session_key');
            $this->applyDateRange($sessionKeysQuery);
            $sessionKeys = $sessionKeysQuery->distinct()
                ->pluck('session_key')
                ->filter()
                ->values()
                ->all();
            $totalClicks = empty($sessionKeys)
                ? 0
                : (int) WidgetSessionEvent::query()
                    ->where('event_type', 'click')
                    ->whereIn('session_key', $sessionKeys)
                    ->when($this->getDateRange() !== null, fn ($q) => $this->applyDateRange($q))
                    ->count();
        }

        return [
            'total_displays' => $totalDisplays,
            'total_clicks' => $totalClicks,
            'filtered' => $filtered,
        ];
    }

    /**
     * @return array<int, array{block_id: int|null, block_name: string, displays: int, clicks: int}>
     */
    public function getBlocksDisplayData(): array
    {
        $filter = trim($this->ig_test_groups ?? '');
        $filtered = $filter !== '';

        $displaysQuery = WidgetSessionEvent::query()
            ->where('event_type', 'view')
            ->where('widget_shown', true)
            ->whereNotNull('block_id');
        $this->applyDateRange($displaysQuery);
        if ($filtered) {
            $displaysQuery->whereIgTestGroups($filter);
        }
        $displaysByBlock = $displaysQuery->selectRaw('block_id, count(*) as cnt')
            ->groupBy('block_id')
            ->pluck('cnt', 'block_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (! $filtered) {
            $clicksQuery = WidgetSessionEvent::query()
                ->where('event_type', 'click')
                ->whereNotNull('block_id');
            $this->applyDateRange($clicksQuery);
            $clicksByBlock = $clicksQuery->selectRaw('block_id, count(*) as cnt')
                ->groupBy('block_id')
                ->pluck('cnt', 'block_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        } else {
            $sessionKeysQuery = WidgetSessionEvent::query()
                ->where('event_type', 'view')
                ->whereIgTestGroups($filter)
                ->whereNotNull('session_key');
            $this->applyDateRange($sessionKeysQuery);
            $sessionKeys = $sessionKeysQuery->distinct()
                ->pluck('session_key')
                ->filter()
                ->values()
                ->all();
            $clicksByBlock = [];
            if (! empty($sessionKeys)) {
                $clicksByBlockQuery = WidgetSessionEvent::query()
                    ->where('event_type', 'click')
                    ->whereIn('session_key', $sessionKeys)
                    ->whereNotNull('block_id');
                $this->applyDateRange($clicksByBlockQuery);
                $clicksByBlock = $clicksByBlockQuery->selectRaw('block_id, count(*) as cnt')
                    ->groupBy('block_id')
                    ->pluck('cnt', 'block_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }
        }

        $blockIds = array_unique(array_merge(
            array_keys($displaysByBlock),
            array_keys($clicksByBlock)
        ));
        $blockIds = array_filter($blockIds, fn ($id) => $id !== null && $id !== '');
        $blocks = empty($blockIds) ? [] : Block::whereIn('id', $blockIds)->get()->keyBy('id');

        $rows = [];
        foreach ($blockIds as $blockId) {
            $block = $blocks->get($blockId);
            $rows[] = [
                'block_id' => $blockId,
                'block_name' => $block ? ($block->name ?: 'Block #' . $blockId) : 'Block #' . $blockId,
                'displays' => $displaysByBlock[$blockId] ?? 0,
                'clicks' => $clicksByBlock[$blockId] ?? 0,
            ];
        }
        usort($rows, fn ($a, $b) => ($b['displays'] + $b['clicks']) <=> ($a['displays'] + $a['clicks']));

        return $rows;
    }
}
