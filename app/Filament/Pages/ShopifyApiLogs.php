<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

class ShopifyApiLogs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Developer';

    protected static ?string $navigationLabel = 'Shopify API logs';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.shopify-api-logs';

    protected static ?string $title = 'Shopify API requests';

    public ?string $logContent = null;

    public ?string $logPath = null;

    public bool $fileExists = false;

    public function mount(): void
    {
        $this->refreshLog();
    }

    public function refreshLog(): void
    {
        $this->logPath = storage_path('logs/shopify_api.log');
        $this->fileExists = File::exists($this->logPath);
        if (! $this->fileExists) {
            $this->logContent = 'Log file not created yet. Requests to Shopify will appear here after the first API call.';

            return;
        }
        $full = File::get($this->logPath);
        $lines = explode("\n", $full);
        $last = array_slice($lines, -400);
        $this->logContent = implode("\n", $last);
        if (count($lines) > 400) {
            $this->logContent = "… (showing last 400 lines)\n" . $this->logContent;
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
