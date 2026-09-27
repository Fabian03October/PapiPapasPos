<?php

namespace App\Filament\Pages;

use App\Models\WalletCardSettings;
use App\Services\GoogleWalletService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class WalletCardDesign extends Page
{
    protected string $view = 'filament.pages.wallet-card-design';

    protected static string|\UnitEnum|null $navigationGroup = 'Clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Diseño de tarjeta';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Diseño de tarjeta';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = WalletCardSettings::current();

        $this->form->fill([
            'program_name' => $settings->program_name,
            'issuer_name' => $settings->issuer_name,
            'hex_background_color' => $settings->hex_background_color,
            'logo_path' => $settings->logo_path,
            'hero_image_path' => $settings->hero_image_path,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('program_name')->label('Nombre del programa')->required(),
                TextInput::make('issuer_name')->label('Nombre del negocio')->required(),
                ColorPicker::make('hex_background_color')->label('Color de fondo de la tarjeta'),
                FileUpload::make('logo_path')
                    ->label('Logotipo')
                    ->image()
                    ->disk('public')
                    ->directory('wallet')
                    ->visibility('public'),
                FileUpload::make('hero_image_path')
                    ->label('Imagen de banner (opcional)')
                    ->helperText('Aparece como fondo arriba del logo en la tarjeta.')
                    ->image()
                    ->disk('public')
                    ->directory('wallet')
                    ->visibility('public'),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        WalletCardSettings::current()->update($data);

        $result = app(GoogleWalletService::class)->upsertClass();

        if (! $result['success']) {
            Notification::make()
                ->title('Se guardó, pero Google rechazó el cambio')
                ->body($result['error'])
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Diseño actualizado en Google Wallet')
            ->success()
            ->send();
    }
}
