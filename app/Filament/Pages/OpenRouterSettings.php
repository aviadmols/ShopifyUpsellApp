<?php

namespace App\Filament\Pages;

use App\Models\AiRequestLog;
use App\Models\Setting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OpenRouterSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationGroup = 'Developer';
    protected static ?string $navigationLabel = 'AI (OpenRouter)';
    protected static ?int $navigationSort = 100;
    protected static string $view = 'filament.pages.open-router-settings';
    protected static ?string $title = 'OpenRouter / AI settings';

    public ?string $openrouter_api_key = null;
    public ?string $openrouter_model = null;

    /** @var string */
    public $activeTab = 'settings';

    /** Logs tab filters */
    public ?string $logFlow = null;
    public ?string $logStatus = null;
    public ?string $logModel = null;
    public ?string $logDateFrom = null;
    public ?string $logDateTo = null;

    /** Modal: selected log for detail view */
    public ?int $viewingLogId = null;

    public function mount(): void
    {
        $this->openrouter_api_key = Setting::get('openrouter_api_key') ?? '';
        $this->openrouter_model = Setting::get('openrouter_model') ?? 'openai/gpt-4o-mini';
    }

    public function getAiLogs(): LengthAwarePaginator
    {
        $query = AiRequestLog::query()->orderByDesc('created_at');

        if ($this->logFlow !== null && $this->logFlow !== '') {
            $query->where('flow', $this->logFlow);
        }
        if ($this->logStatus !== null && $this->logStatus !== '') {
            $query->where('status', $this->logStatus);
        }
        if ($this->logModel !== null && $this->logModel !== '') {
            $query->where('model', $this->logModel);
        }
        if ($this->logDateFrom !== null && $this->logDateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->logDateFrom);
        }
        if ($this->logDateTo !== null && $this->logDateTo !== '') {
            $query->whereDate('created_at', '<=', $this->logDateTo);
        }

        return $query->paginate(15);
    }

    public function getViewingLog(): ?AiRequestLog
    {
        if ($this->viewingLogId === null) {
            return null;
        }
        return AiRequestLog::find($this->viewingLogId);
    }

    public function openLogModal(int $id): void
    {
        $this->viewingLogId = $id;
        $this->dispatch('open-modal', 'ai-log-detail');
    }

    public function closeLogModal(): void
    {
        $this->viewingLogId = null;
    }

    public function getUserPromptFromRequest(?string $requestPayload): string
    {
        if ($requestPayload === null || $requestPayload === '') {
            return '';
        }
        $data = json_decode($requestPayload, true);
        if (! is_array($data) || empty($data['messages'])) {
            return '';
        }
        foreach ($data['messages'] as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'user' && isset($msg['content'])) {
                return is_string($msg['content']) ? $msg['content'] : json_encode($msg['content']);
            }
        }
        return '';
    }

    public function getSystemPromptFromRequest(?string $requestPayload): string
    {
        if ($requestPayload === null || $requestPayload === '') {
            return '';
        }
        $data = json_decode($requestPayload, true);
        if (! is_array($data) || empty($data['messages'])) {
            return '';
        }
        foreach ($data['messages'] as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'system' && isset($msg['content'])) {
                return is_string($msg['content']) ? $msg['content'] : json_encode($msg['content']);
            }
        }
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('openrouter_api_key')
                    ->label('OpenRouter API Key')
                    ->password()
                    ->placeholder('sk-or-v1-...')
                    ->helperText('Get your key at openrouter.ai. Stored securely in settings.')
                    ->maxLength(255),
                Select::make('openrouter_model')
                    ->label('Model')
                    ->options([
                        'openai/gpt-4o-mini' => 'GPT-4o Mini',
                        'openai/gpt-4o' => 'GPT-4o',
                        'openai/gpt-4-turbo' => 'GPT-4 Turbo',
                        'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet',
                        'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
                        'google/gemini-2.0-flash-001' => 'Gemini 2.0 Flash',
                        'meta-llama/llama-3.1-70b-instruct' => 'Llama 3.1 70B',
                    ])
                    ->default('openai/gpt-4o-mini')
                    ->required(),
            ])
            ->statePath('')
            ->columns(1);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        Setting::set('openrouter_api_key', (string) ($data['openrouter_api_key'] ?? ''));
        Setting::set('openrouter_model', (string) ($data['openrouter_model'] ?? 'openai/gpt-4o-mini'));
        $this->openrouter_model = Setting::get('openrouter_model') ?? 'openai/gpt-4o-mini';
        \Filament\Notifications\Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    protected function getFormStatePath(): ?string
    {
        return '';
    }
}
