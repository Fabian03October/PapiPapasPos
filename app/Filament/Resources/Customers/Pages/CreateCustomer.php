<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Mail\CustomerQrMail;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        if (blank($this->record->email)) {
            Notification::make()
                ->warning()
                ->title('Cliente creado sin correo')
                ->body('No se envió el QR porque este cliente no tiene correo registrado.')
                ->send();

            return;
        }

        Mail::to($this->record->email)->send(new CustomerQrMail($this->record));

        Notification::make()
            ->success()
            ->title('QR enviado')
            ->body('Se mandó el código de fidelidad a ' . $this->record->email)
            ->send();
    }
}