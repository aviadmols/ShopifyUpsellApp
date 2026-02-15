<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;

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

    public function mount(): void
    {
        $this->openrouter_api_key = Setting::get('openrouter_api_key') ?? '';
        $this->openrouter_model = Setting::get('openrouter_model') ?? 'openai/gpt-4o-mini';
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
