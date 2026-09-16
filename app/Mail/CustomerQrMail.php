<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CustomerQrMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $qrBase64;

    public function __construct(public Customer $customer)
    {
                $qrSvg = QrCode::format('svg')->size(280)->margin(1)->generate($customer->qr_code);
        $this->qrBase64 = base64_encode($qrSvg);
    }

    public function build()
    {
        return $this->subject('Tu código de fidelidad — posPapisV1')
            ->view('emails.customer-qr');
    }
}