<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Jobs\SendWalletMessageJob;
use App\Models\Customer;
use App\Models\LoyaltyLevel;
use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use App\Services\LoyaltyService;
use App\Services\WebPushService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

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
                // Si el cliente reenvió su enlace (o perdió el celular): nuevo
                // código; el enlace y QR viejos dejan de servir.
                // Corregir sellos/nivel a mano (ej. pasar su tarjeta física a la
                // digital). Solo el manager; queda en la bitácora.
                Action::make('adjust_loyalty')
                    ->label('Sellos y nivel')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->esManager() ?? false)
                    ->modalHeading(fn (Customer $record) => "Sellos y nivel de {$record->name}")
                    ->modalDescription(fn (Customer $record) => "Hoy tiene: nivel {$record->current_level}, {$record->current_visits} sello(s), {$record->cards_completed} tarjeta(s) llena(s) en su nivel. El cambio queda en la bitácora.")
                    ->fillForm(fn (Customer $record) => [
                        'level' => $record->current_level,
                        'visits' => $record->current_visits,
                        'cards' => $record->cards_completed,
                        'reward' => 'none',
                        'reason' => '',
                    ])
                    ->schema([
                        Select::make('level')
                            ->label('Nivel')
                            ->options(fn () => LoyaltyLevel::orderBy('level_number')->get()
                                ->mapWithKeys(fn (LoyaltyLevel $l) => [$l->level_number => "Nivel {$l->level_number} (se llena {$l->timesToAdvance()} vez/veces)"]))
                            ->required()
                            ->live(),
                        TextInput::make('visits')
                            ->label('Sellos en su tarjeta actual')
                            ->helperText('De 0 a '.(LoyaltyService::VISITS_FOR_GIFT - 1).'. Ej. si su tarjeta física tiene 4 sellos, pon 4.')
                            ->numeric()->integer()->minValue(0)->maxValue(LoyaltyService::VISITS_FOR_GIFT - 1)->required(),
                        TextInput::make('cards')
                            ->label('Tarjetas ya llenas en este nivel')
                            ->helperText(fn (Get $get) => ($l = LoyaltyLevel::forLevelNumber((int) $get('level')))
                                ? 'De 0 a '.($l->timesToAdvance() - 1).' (este nivel se llena '.$l->timesToAdvance().' vez/veces).'
                                : null)
                            ->numeric()->integer()->minValue(0)
                            ->maxValue(fn (Get $get) => max(0, (LoyaltyLevel::forLevelNumber((int) $get('level'))?->timesToAdvance() ?? 1) - 1))
                            ->required(),
                        Select::make('reward')
                            ->label('Darle un premio por cobrar')
                            ->helperText('Si ya lo ganó (ej. en su tarjeta física) y no lo ha usado. Vence en '.LoyaltyService::PENDING_REWARD_DAYS.' días.')
                            ->options(['none' => 'No', 'discount' => 'Sí: el descuento de su nivel', 'gift' => 'Sí: el regalo de su nivel'])
                            ->required(),
                        Textarea::make('reason')
                            ->label('Motivo')
                            ->placeholder('Ej. Pasa de tarjeta física a digital')
                            ->required()->maxLength(255)->rows(2),
                    ])
                    ->action(function (array $data, Customer $record): void {
                        try {
                            app(LoyaltyService::class)->adjust(
                                $record, auth()->user(), (int) $data['level'], (int) $data['visits'], (int) $data['cards'],
                                (string) $data['reason'], $data['reward'] === 'none' ? null : $data['reward'],
                            );
                        } catch (ValidationException $e) {
                            Notification::make()->title('No se guardó')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();

                            return;
                        }

                        Notification::make()->title('Sellos y nivel actualizados')->body('Su tarjeta se actualiza sola.')->success()->send();
                    }),
                Action::make('regenerate_card')
                    ->label('Cambiar enlace')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Customer $record) => "¿Cambiar el enlace de {$record->name}?")
                    ->modalDescription('Úsalo si el cliente compartió su enlace o perdió su celular. Su enlace y su QR actuales dejan de funcionar (también en caja) y se desactivan sus avisos. Sus sellos y premios NO cambian. Después mándale su enlace nuevo por WhatsApp.')
                    ->modalSubmitActionLabel('Sí, cambiar enlace')
                    ->action(function (Customer $record): void {
                        $record->regenerateCardCode();

                        Notification::make()
                            ->title('Enlace cambiado')
                            ->body('Mándale su enlace nuevo con "Enviar por WhatsApp".')
                            ->success()
                            ->send();
                    }),
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
