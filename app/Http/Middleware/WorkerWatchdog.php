<?php

namespace App\Http\Middleware;

use App\Mail\WorkerDownAlert;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vigila al worker (cola + programador). Si el worker se cae no puede avisar
 * él mismo, así que lo revisa el POS mientras lo usan los cajeros: cada 10
 * minutos como máximo. Si el worker no da "señal de vida" (ver
 * AppServiceProvider: Queue::looping) en 15 min, o hay trabajos esperando
 * más de 10 min, manda un correo (máximo uno cada 2 horas).
 *
 * Contexto: del 10 al 22 de octubre de 2026 el worker estuvo caído y nadie se
 * enteró (no hubo sellos en Wallet, avisos ni respaldo diario).
 */
class WorkerWatchdog
{
    public const HEARTBEAT_KEY = 'worker:latido';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->enabled() && Cache::add('vigilante:revision', true, now()->addMinutes(10))) {
            try {
                $this->check();
            } catch (\Throwable $e) {
                Log::error('Vigilante del worker: no se pudo revisar', ['error' => $e->getMessage()]);
            }
        }

        return $response;
    }

    protected function enabled(): bool
    {
        return app()->environment('production') && filled(config('services.alertas.email'));
    }

    /** @return array<int, string> los problemas encontrados (vacío = todo bien) */
    public function problems(): array
    {
        $problems = [];
        $latido = Cache::get(self::HEARTBEAT_KEY);

        if (! $latido || now()->timestamp - (int) $latido > 15 * 60) {
            $problems[] = $latido
                ? 'El worker no da señales de vida desde '.now()->setTimestamp((int) $latido)->locale('es')->diffForHumans().'.'
                : 'El worker no ha dado ninguna señal de vida.';
        }

        $atorados = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->subMinutes(10)->timestamp)
            ->count();

        if ($atorados > 0) {
            $problems[] = "Hay {$atorados} trabajo(s) esperando en la cola desde hace más de 10 minutos.";
        }

        return $problems;
    }

    protected function check(): void
    {
        $problems = $this->problems();

        if ($problems === [] || ! Cache::add('vigilante:alerta-enviada', true, now()->addHours(2))) {
            return;
        }

        Log::error('Vigilante del worker: '.implode(' ', $problems));

        Mail::to(config('services.alertas.email'))->send(new WorkerDownAlert($problems));
    }
}
