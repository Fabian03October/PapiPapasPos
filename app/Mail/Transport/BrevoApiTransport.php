<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Manda correo por la API HTTPS de Brevo en vez de SMTP directo. Railway
 * bloquea/cuelga las conexiones SMTP salientes (confirmado en vivo: el
 * socket se colgaba hasta que PHP lo mataba a los 30s) - por HTTPS normal
 * (mismo puerto 443 que ya usan Resend y la API de Google Wallet) sí
 * funciona.
 */
class BrevoApiTransport extends AbstractTransport
{
    private const API_URL = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(protected string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $payload = array_filter([
            'sender' => $this->addressToArray($envelope->getSender()),
            'to' => $this->addressesToArray($this->getRecipients($email, $envelope)),
            'cc' => $this->addressesToArray($email->getCc()) ?: null,
            'bcc' => $this->addressesToArray($email->getBcc()) ?: null,
            'replyTo' => $email->getReplyTo() ? $this->addressToArray($email->getReplyTo()[0]) : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
            'attachment' => $this->attachmentsToArray($email) ?: null,
        ]);

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(15)->post(self::API_URL, $payload);

        if ($response->failed()) {
            throw new TransportException(
                sprintf('Request to Brevo API failed. Reason: %s.', $response->body()),
                $response->status()
            );
        }
    }

    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        return array_filter($envelope->getRecipients(), function (Address $address) use ($email) {
            return in_array($address, array_merge($email->getCc(), $email->getBcc()), true) === false;
        });
    }

    protected function addressesToArray(array $addresses): array
    {
        return array_values(array_map([$this, 'addressToArray'], $addresses));
    }

    protected function addressToArray(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName() ?: null,
        ]);
    }

    protected function attachmentsToArray(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $attachments[] = [
                'name' => $filename ?: 'archivo',
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        return $attachments;
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }
}
