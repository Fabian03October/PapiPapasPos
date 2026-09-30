<?php

namespace App\Filament\Pages;

use App\Models\BusinessSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BusinessSettingsPage extends Page
{
    protected string $view = 'filament.pages.business-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Datos del negocio';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Datos del negocio';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = BusinessSettings::current();

        $this->form->fill([
            'name' => $settings->name,
            'address' => $settings->address,
            'contact_info' => $settings->contact_info,
            'show_iva' => $settings->show_iva,
            'iva_rate' => $settings->iva_rate,
            'thank_you_message' => $settings->thank_you_message,
            'personalize_thank_you' => $settings->personalize_thank_you,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('En el ticket del cliente')
                    ->description('Esto se imprime en el ticket de venta. La comanda de cocina no lleva nada de esto.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del negocio')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('address')
                            ->label('Dirección')
                            ->columnSpanFull(),
                        Textarea::make('contact_info')
                            ->label('Teléfono / redes sociales')
                            ->rows(2)
                            ->helperText('Ej. "📞 555-123-4567 · @papispapas"')
                            ->columnSpanFull(),
                    ]),

                Section::make('IVA')
                    ->description('Tus precios ya incluyen el IVA - esto solo agrega una línea informativa en el ticket, el total que se cobra no cambia.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('show_iva')
                            ->label('Mostrar el IVA desglosado en el ticket')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('iva_rate')
                            ->label('Tasa de IVA')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Get $get) => $get('show_iva')),
                    ]),

                Section::make('Mensaje de agradecimiento')
                    ->columns(2)
                    ->schema([
                        TextInput::make('thank_you_message')
                            ->label('Mensaje')
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('personalize_thank_you')
                            ->label('Personalizar con el nombre del cliente')
                            ->live()
                            ->helperText('Si la venta tiene un cliente identificado, se le pone su nombre (ej. "¡Gracias, Fabian, por tu compra!"). Si no hay cliente, siempre se usa el mensaje de arriba.')
                            ->columnSpanFull(),
                    ]),
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
        BusinessSettings::current()->update($this->form->getState());

        Notification::make()
            ->title('Datos del negocio actualizados')
            ->success()
            ->send();
    }
}
