<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

class CheckoutExtensionLogs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Developer';

    protected static ?string $navigationLabel = 'Checkout widget logs';

    protected static ?int $navigationSort = 110;

    protected static string $view = 'filament.pages.checkout-extension-logs';

    protected static ?string $title = 'Checkout extension logs';

    public ?string $logContent = null;

    public ?string $logPath = null;

    public bool $fileExists = false;

    public function mount(): void
    {
        $this->refreshLog();
    }

    public function refreshLog(): void
    {
        $this->logPath = storage_path('logs/checkout_extension.log');
        $this->fileExists = File::exists($this->logPath);
        if (! $this->fileExists) {
            $this->logContent = 'Log file not created yet. Open Checkout (or the editor) to generate widget logs, then refresh.';

            return;
        }
        $full = File::get($this->logPath);
        $lines = explode("\n", $full);
        $last = array_slice($lines, -800);
        $this->logContent = implode("\n", $last);
        if (count($lines) > 800) {
            $this->logContent = "… (showing last 800 lines)\n" . $this->logContent;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshLog'),
        ];
    }
}

