<?php

namespace App\Jobs;

use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWalletMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public WalletMessage $message)
    {
    }

    public function handle(GoogleWalletService $wallet): void
    {
        $result = $this->send($wallet, $this->message->notify);

        // Si se quería notificar y Google respondió cuota excedida, se reintenta
        // una sola vez en silencio (TEXT) en vez de perder el mensaje.
        if (! $result['success'] && $result['quota_exceeded'] && $this->message->notify) {
            $result = $this->send($wallet, false);
        }

        $this->message->update([
            'status' => $result['success'] ? 'sent' : 'failed',
            'error' => $result['error'],
        ]);
    }

    protected function send(GoogleWalletService $wallet, bool $notify): array
    {
        return $this->message->audience === 'all'
            ? $wallet->sendMessageToClass($this->message->title, $this->message->body, $notify)
            : $wallet->sendMessageToObject($this->message->customer, $this->message->title, $this->message->body, $notify);
    }
}
