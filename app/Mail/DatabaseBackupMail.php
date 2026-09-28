<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DatabaseBackupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string $attachmentContents,
        protected string $attachmentName,
        protected float $sizeInMb,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Respaldo de base de datos - '.now()->format('d/M/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Respaldo automático de la base de datos generado el '
                .now()->translatedFormat('d \d\e F \d\e Y, H:i').'.</p>'
                .'<p>Tamaño: '.number_format($this->sizeInMb, 2).' MB.</p>'
                .'<p>Guárdalo en un lugar seguro (una carpeta en tu computadora, Google Drive, etc.).</p>',
        );
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $this->attachmentContents,
                $this->attachmentName
            ),
        ];
    }
}
