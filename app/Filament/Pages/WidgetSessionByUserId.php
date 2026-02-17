<?php

namespace App\Filament\Pages;

use App\Filament\Resources\WidgetSessionEventResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class WidgetSessionByUserId extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Logs';
    protected static ?string $navigationLabel = 'By User ID';
    protected static ?string $title = 'Widget session logs by User ID';

    protected static string $view = 'filament.pages.widget-session-by-user-id';

    protected static ?string $slug = 'widget-session-events/by-user-id';

    protected static ?int $navigationSort = 3;

    public string $zyxel_user_id = '';

    public function mount(): void
    {
        $this->zyxel_user_id = (string) request()->query('zyxel_user_id', '');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('zyxel_user_id')
                    ->label('User ID (_zyxel_user_id)')
                    ->placeholder('e.g. 5aac078c-755f-4667-abaa-32fcfe7309b0')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Enter the _zyxel_user_id from checkout_attributes to see all widget session events for that user.'),
            ])
            ->statePath('')
            ->columns(1);
    }

    public function viewEvents(): void
    {
        $this->validate([
            'zyxel_user_id' => 'required|string|max:255',
        ]);
        $value = trim($this->zyxel_user_id);
        $url = WidgetSessionEventResource::getUrl('index', [
            'tableFilters' => [
                'zyxel_user_id' => [
                    'zyxel_user_id' => $value,
                ],
            ],
        ]);
        $this->redirect($url);
    }
}
