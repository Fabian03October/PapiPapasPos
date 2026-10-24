<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Jobs\SendWalletMessageJob;
use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use App\Services\WebPushService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                ->visible(fn () => app(GoogleWalletService::class)->isEnabled() || app(WebPushService::class)->isEnabled())
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
                        ->helperText(fn () => (($limit = (int) config('services.webpush.daily_broadcast_limit', 3)) > 0
                            ? "Hasta {$limit} mensajes a todos con aviso por día. "
                            : '').'En Google Wallet, después de 3 avisos al día los demás llegan en silencio.')
                        ->default(false),
                ])
                ->requiresConfirmation()
                ->modalDescription('Se manda a TODOS los clientes que guardaron su tarjeta. No se puede deshacer.')
                ->action(function (array $data): void {
                    $notify = (bool) ($data['notify'] ?? false);

                    if ($notify && WalletMessage::dailyBroadcastLimitReached()) {
                        Notification::make()
                            ->title('Ya se mandaron los mensajes con aviso de hoy')
                            ->body('Para no cansar a los clientes hay un máximo al día (variable AVISOS_A_TODOS_POR_DIA). Puedes mandarlo sin aviso, o intenta mañana.')
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
