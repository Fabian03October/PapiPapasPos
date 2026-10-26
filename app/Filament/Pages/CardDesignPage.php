<?php

namespace App\Filament\Pages;

use App\Models\WalletCardSettings;
use App\Services\Portal\CardDesign;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * El negocio elige cómo se ve "Mi tarjeta": colores, logo, el sello, los
 * íconos de premio y los textos, con vista previa en vivo antes de guardar.
 * Lo que no se llena se queda como siempre (ver CardDesign::DEFAULTS).
 */
class CardDesignPage extends Page
{
    protected string $view = 'filament.pages.card-design';

    protected static ?string $slug = 'diseno-de-tarjeta';

    protected static string|\UnitEnum|null $navigationGroup = 'Clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?string $navigationLabel = 'Diseño de tarjeta';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Diseño de la tarjeta';

    public static function canAccess(): bool
    {
        return auth()->user()?->esManager() ?? false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $s = WalletCardSettings::current();

        $this->form->fill([
            'card_bg_color' => $s->card_bg_color ?? CardDesign::DEFAULTS['bg'],
            'card_accent_color' => $s->card_accent_color ?? CardDesign::DEFAULTS['accent'],
            'card_logo_path' => $s->card_logo_path,
            'card_title' => $s->card_title,
            'stamp_emoji' => $s->stamp_emoji,
            'stamp_image_path' => $s->stamp_image_path,
            'discount_icon' => $s->discount_icon,
            'gift_icon' => $s->gift_icon,
            'welcome_message' => $s->welcome_message,
            'contact_info' => $s->contact_info,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $hex = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 5])->schema([
                    Group::make([
                        Section::make('Colores')->columns(2)->schema([
                            ColorPicker::make('card_bg_color')->label('Fondo')->rules($hex)->live(),
                            ColorPicker::make('card_accent_color')->label('Acento')
                                ->helperText('Sellos, botones y premios.')->rules($hex)->live(),
                        ]),
                        Section::make('Logo')->schema([
                            FileUpload::make('card_logo_path')->label('Logo de la tarjeta')
                                ->helperText('PNG o JPG, de preferencia horizontal y con fondo transparente. Máximo 2 MB. Si no subes uno, se usa el de siempre.')
                                ->disk('public')->directory('mi-tarjeta')->visibility('public')->maxSize(2048)->live(),
                        ]),
                        Section::make('El sello')->columns(3)->schema([
                            TextInput::make('stamp_emoji')->label('Sello (emoji)')->placeholder('🍟')
                                ->helperText('Ej. 🍟 🥔 ⭐ 🔥 🌶️')->maxLength(16)->live(debounce: 400),
                            TextInput::make('discount_icon')->label('Casilla del descuento')->placeholder('%')
                                ->helperText('La 4ª visita.')->maxLength(16)->live(debounce: 400),
                            TextInput::make('gift_icon')->label('Casilla del regalo')->placeholder('🎁')
                                ->helperText('La 8ª visita.')->maxLength(16)->live(debounce: 400),
                            FileUpload::make('stamp_image_path')->label('O una imagen propia para el sello')
                                ->helperText('PNG cuadrado con fondo transparente (ej. 200×200). Si subes una, se usa en lugar del emoji.')
                                ->disk('public')->directory('mi-tarjeta')->visibility('public')->maxSize(1024)
                                ->columnSpanFull()->live(),
                        ]),
                        Section::make('Textos')->schema([
                            TextInput::make('card_title')->label('Título de la tarjeta')->placeholder('Tarjeta de sellos')
                                ->maxLength(40)->live(debounce: 400),
                            Textarea::make('welcome_message')->label('Bienvenida (al registrarse)')
                                ->placeholder("Ya eres parte de Papi's Papas. Guarda esta tarjeta en tu celular y muéstrala en cada visita.")
                                ->maxLength(200)->rows(2)->live(debounce: 600),
                            Textarea::make('contact_info')->label('Datos de contacto')
                                ->helperText('Se muestran al final de la tarjeta. Ej. dirección, teléfono, horario, redes.')
                                ->maxLength(300)->rows(3)->live(debounce: 600),
                        ]),
                    ])->columnSpan(['lg' => 3]),

                    View::make('filament.card-design-preview')
                        ->viewData(fn (Get $get) => ['design' => $this->previewDesign($get)])
                        ->columnSpan(['lg' => 2]),
                ]),
            ])
            ->statePath('data');
    }

    /** Diseño con lo que está en el formulario (aunque no se haya guardado). */
    protected function previewDesign(Get $get): array
    {
        $design = CardDesign::build([
            'bg' => $get('card_bg_color'),
            'accent' => $get('card_accent_color'),
            'title' => $get('card_title'),
            'stamp_emoji' => $get('stamp_emoji'),
            'discount_icon' => $get('discount_icon'),
            'gift_icon' => $get('gift_icon'),
            'welcome_message' => $get('welcome_message'),
            'contact_info' => $get('contact_info'),
        ]);

        if ($logo = $this->fileUrl($get('card_logo_path'))) {
            $design['logo_url'] = $logo;
            $design['logo_badge'] = false;
        }
        $design['stamp_image_url'] = $this->fileUrl($get('stamp_image_path'));

        return $design;
    }

    /** URL de una imagen del formulario: recién subida (temporal) o ya guardada. */
    protected function fileUrl(mixed $state): ?string
    {
        $file = is_array($state) ? collect($state)->first() : $state;

        if ($file instanceof TemporaryUploadedFile) {
            try {
                return $file->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        return is_string($file) && $file !== '' && Storage::disk('public')->exists($file)
            ? Storage::disk('public')->url($file)
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('Volver al diseño original')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('La tarjeta vuelve a negro y amarillo, con el logo y el sello 🍟 de siempre. Los datos de contacto se conservan.')
                ->action(function (): void {
                    WalletCardSettings::current()->update([
                        'card_bg_color' => null, 'card_accent_color' => null, 'card_logo_path' => null, 'card_title' => null,
                        'stamp_emoji' => null, 'stamp_image_path' => null, 'discount_icon' => null, 'gift_icon' => null,
                        'welcome_message' => null,
                    ]);
                    $this->mount();
                    Notification::make()->title('Diseño original restaurado')->success()->send();
                }),
            Action::make('save')
                ->label('Guardar diseño')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Si queda el color de siempre, se guarda vacío (así un cambio futuro
        // del diseño por defecto también le llega).
        foreach (['card_bg_color' => 'bg', 'card_accent_color' => 'accent'] as $field => $key) {
            if (strtolower((string) ($data[$field] ?? '')) === CardDesign::DEFAULTS[$key]) {
                $data[$field] = null;
            }
        }

        WalletCardSettings::current()->update($data);

        Notification::make()
            ->title('Diseño guardado')
            ->body('Los clientes lo ven la próxima vez que abran su tarjeta con internet.')
            ->success()
            ->send();
    }
}
