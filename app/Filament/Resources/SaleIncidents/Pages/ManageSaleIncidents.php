<?php

namespace App\Filament\Resources\SaleIncidents\Pages;

use App\Filament\Resources\SaleIncidents\SaleIncidentResource;
use App\Models\ManagerAuthorizationCode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

class ManageSaleIncidents extends ManageRecords
{
    protected static string $resource = SaleIncidentResource::class;

    protected ?ManagerAuthorizationCode $activeCode = null;

    protected function loadActiveCode(): void
    {
        $this->activeCode = ManagerAuthorizationCode::where('user_id', auth()->id())
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    protected function getHeaderActions(): array
    {
        $this->loadActiveCode();

        return [
            $this->activeCode ? $this->codigoVigenteAction() : $this->generarCodigoAction(),
        ];
    }

    protected function generarCodigoAction(): Action
    {
        return Action::make('generar_codigo')
            ->label('Generar código temporal')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->action(function () {
                $code = (string) random_int(100000, 999999);

                ManagerAuthorizationCode::create([
                    'user_id' => auth()->id(),
                    'code' => Hash::make($code),
                    'expires_at' => now()->addMinutes(10),
                ]);

                Notification::make()
                    ->title('Código temporal: ' . $code)
                    ->body('Válido 10 minutos, de un solo uso. Dícteselo al cajero por teléfono — no le des tu PIN real.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    protected function codigoVigenteAction(): Action
    {
        return Action::make('codigo_activo')
            ->label('Código vigente')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->disabled()
            ->extraAttributes([
                'x-data' => '{ expiresAt: ' . ($this->activeCode?->expires_at->timestamp * 1000) . ', remaining: "" }',
                'x-init' => '
                    const tick = () => {
                        const diff = expiresAt - Date.now();
                        if (diff <= 0) { $wire.$refresh(); return; }
                        const m = Math.floor(diff / 60000);
                        const s = Math.floor((diff % 60000) / 1000);
                        remaining = m + ":" + String(s).padStart(2, "0");
                    };
                    tick();
                    setInterval(tick, 1000);
                ',
                'x-text' => 'remaining ? ("Vigente · expira en " + remaining) : "Código vigente"',
            ]);
    }
}