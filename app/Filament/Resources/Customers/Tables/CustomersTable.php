<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Jobs\SendWalletMessageJob;
use App\Models\Customer;
use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use App\Services\WebPushService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('birthdate')
                    ->label('Cumpleaños')
                    ->date('d/M')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('current_level')
                    ->label('Nivel')
                    ->badge()
                    ->sortable(),
                TextColumn::make('current_visits')
                    ->label('Progreso')
                    ->formatStateUsing(fn (int $state) => "{$state} / 8 visitas")
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Cliente desde')
                    ->date('d/M/Y')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->placeholder('Todos')
                    ->default(true),
            ])
            ->recordActions([
                // Antes abría el enlace de guardado en el dispositivo del
                // encargado: la tarjeta del cliente terminaba en la cuenta de
                // Google del encargado. Ahora se muestra un QR para que el
                // CLIENTE lo escanee con su teléfono.
                // Siempre visible: "Mi tarjeta" no depende de Google Wallet.
                Action::make('wallet_link')
                    ->label('Tarjeta del cliente')
                    ->icon(Heroicon::OutlinedWallet)
                    ->color('gray')
                    ->modalHeading(fn (Customer $record) => "Tarjeta de {$record->name}")
                    ->modalContent(fn (Customer $record) => view('filament.wallet-card-qr', [
                        'customer' => $record,
                        'url' => $record->cardUrl(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                // Abre WhatsApp con el chat del cliente y el mensaje con su
                // enlace ya escrito: solo falta tocar enviar.
                Action::make('whatsapp_card')
                    ->label('Enviar por WhatsApp')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->visible(fn (Customer $record) => $record->whatsappCardUrl() !== null)
                    ->url(fn (Customer $record) => $record->whatsappCardUrl())
                    ->openUrlInNewTab(),
                Action::make('send_message')
                    ->label('Mandar mensaje')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
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
                            ->rows(3)
                            ->live()
                            ->hint(fn (?string $state) => strlen($state ?? '').'/400'),
                        Toggle::make('notify')
                            ->label('Enviar notificación push')
                            ->default(true),
                    ])
                    ->action(function (array $data, Customer $record): void {
                        $message = WalletMessage::create([
                            'title' => $data['title'],
                            'body' => $data['body'],
                            'audience' => 'customer',
                            'customer_id' => $record->id,
                            'notify' => (bool) ($data['notify'] ?? false),
                            'sent_by_user_id' => auth()->id(),
                            'status' => 'queued',
                        ]);

                        SendWalletMessageJob::dispatch($message);

                        Notification::make()
                            ->title('Mensaje en cola de envío')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
