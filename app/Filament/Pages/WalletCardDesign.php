<?php

namespace App\Filament\Pages;

use App\Models\WalletCardSettings;
use App\Services\GoogleWalletService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
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

    // Escondida del menú por ahora: el guardado de imágenes sigue fallando
    // en producción (Google tarda demasiado en validarlas y la petición
    // truena). Se retoma en la v2. La página sigue funcionando si alguien
    // entra directo a la URL, solo no aparece en la navegación.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

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
            'contact_info' => $settings->contact_info,
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
                    ->helperText('PNG, JPG, WEBP o SVG. Máximo 2MB — entre más pesada la imagen, más tarda Google en aceptar el cambio.')
                    // Sin ->image()/->acceptedFileTypes(): esos validan el
                    // tipo de archivo detectando el contenido real del
                    // archivo en el servidor, y en Railway a veces falla y
                    // rechaza imágenes genuinas (ya lo confirmamos con dos
                    // archivos distintos). Es un campo que solo usa el
                    // manager, así que no hace falta esa validación estricta.
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('wallet')
                    ->visibility('public'),
                FileUpload::make('hero_image_path')
                    ->label('Imagen de banner (opcional)')
                    ->helperText('Aparece como fondo arriba del logo en la tarjeta. PNG, JPG, WEBP o SVG. Máximo 2MB.')
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('wallet')
                    ->visibility('public'),
                Textarea::make('contact_info')
                    ->label('Información de contacto')
                    ->helperText('Se muestra en la tarjeta. Puedes usar emojis, ej. "📍 Av. Principal 123 · 📞 555-123-4567".')
                    ->rows(3)
                    ->columnSpanFull(),
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
