<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class ConnectShop extends Page
{
    use InteractsWithFormActions;
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Shops';

    protected static ?string $navigationLabel = 'Connect shop';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.connect-shop';

    protected static ?string $title = 'Connect a new shop';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shop domain')
                    ->description('Enter your Shopify store domain (e.g. mystore.myshopify.com). You will be redirected to Shopify to authorize the app.')
                    ->schema([
                        Forms\Components\TextInput::make('shop_domain')
                            ->label('Shop domain')
                            ->placeholder('mystore.myshopify.com')
                            ->required()
                            ->maxLength(255)
                            ->rules(['regex:/^([a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com|[a-zA-Z0-9][a-zA-Z0-9\-]+)$'])
                            ->validationAttribute('shop domain'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function connect(): void
    {
        $data = $this->form->getState();
        $domain = Str::lower(trim($data['shop_domain'] ?? ''));
        if (! str_ends_with($domain, '.myshopify.com')) {
            $domain = $domain . '.myshopify.com';
        }
        $this->redirect(route('auth.install', ['shop' => $domain]));
    }

    /**
     * @return array<Filament\Actions\Action>
     */
    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('connect')
                ->label('Continue to Shopify')
                ->submit('connect')
                ->icon('heroicon-o-arrow-right'),
        ];
    }

    /**
     * @return array<string, Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form($this->makeForm()->columns(1)->statePath('data')),
        ];
    }
}
