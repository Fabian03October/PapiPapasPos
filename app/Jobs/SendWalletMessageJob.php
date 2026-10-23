<?php

namespace App\Jobs;

use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWalletMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public WalletMessage $message) {}

    public function handle(GoogleWalletService $wallet): void
    {
        $result = $this->send($wallet, $this->message->notify);

        // Si se quería notificar y Google respondió cuota excedida, se reintenta
        // una sola vez en silencio (TEXT) en vez de perder el mensaje.
        if (! $result['success'] && $result['quota_exceeded'] && $this->message->notify) {
            $result = $this->send($wallet, false);
        }

        // Google Wallet apagado no es un fallo: el mensaje igual sale en "Mi tarjeta".
        $skipped = $result['skipped'] ?? false;
        $days = (int) config('services.google_wallet.message_days', 7);

        $this->message->update([
            'status' => ($result['success'] || $skipped) ? 'sent' : 'failed',
            'error' => $skipped ? null : $result['error'],
            // Historial: desde y hasta cuándo se vio en las tarjetas.
            'starts_at' => $result['starts_at'] ?? ($skipped ? now() : $this->message->starts_at),
            'ends_at' => $result['ends_at'] ?? ($skipped && $days > 0 ? now()->addDays($days) : $this->message->ends_at),
        ]);

        // Con notificación: también suena en los celulares con "Mi tarjeta".
        if ($this->message->notify && app(WebPushService::class)->isEnabled()) {
            SendWebPushJob::dispatch(
                $this->message->audience === 'all' ? null : $this->message->customer_id,
                $this->message->title,
                $this->message->body,
                'mensaje-'.$this->message->id,
            );
        }
    }

    protected function send(GoogleWalletService $wallet, bool $notify): array
    {
        return $this->message->audience === 'all'
            ? $wallet->sendMessageToClass($this->message->title, $this->message->body, $notify)
            : $wallet->sendMessageToObject($this->message->customer, $this->message->title, $this->message->body, $notify);
    }
}
