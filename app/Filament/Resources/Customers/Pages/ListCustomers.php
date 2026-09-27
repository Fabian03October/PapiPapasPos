<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Jobs\SendWalletMessageJob;
use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('broadcast_message')
                ->label('Mandar mensaje a todos')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('gray')
                ->visible(fn () => app(GoogleWalletService::class)->isEnabled())
                ->schema([
                    TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(60)
                        ->live()
                        ->hint(fn (?string $state) => strlen($state ?? '').'/60'),
                    Textarea::make('body')
                        ->label('Mensaje')
                        ->required()
                        ->maxLength(400)
                        ->rows(4)
                        ->live()
                        ->hint(fn (?string $state) => strlen($state ?? '').'/400'),
                    Toggle::make('notify')
                        ->label('Enviar notificación push')
                        ->helperText('Máximo 3 notificaciones por tarjeta cada 24h — resérvalo para avisos importantes.')
                        ->default(false),
                ])
                ->requiresConfirmation()
                ->modalDescription('Se manda a TODOS los clientes que guardaron su tarjeta. No se puede deshacer.')
                ->action(function (array $data): void {
                    $notify = (bool) ($data['notify'] ?? false);

                    if ($notify && WalletMessage::dailyBroadcastLimitReached()) {
                        Notification::make()
                            ->title('Ya se alcanzó el máximo de difusiones con notificación de hoy')
                            ->body('Puedes mandarlo sin notificación (se agrega en silencio a la tarjeta), o intenta mañana.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $message = WalletMessage::create([
                        'title' => $data['title'],
                        'body' => $data['body'],
                        'audience' => 'all',
                        'notify' => $notify,
                        'sent_by_user_id' => auth()->id(),
                        'status' => 'queued',
                    ]);

                    SendWalletMessageJob::dispatch($message);

                    Notification::make()
                        ->title('Mensaje en cola de envío')
                        ->success()
                        ->send();
                }),
        ];
    }
}
