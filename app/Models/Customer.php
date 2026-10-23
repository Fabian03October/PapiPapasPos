<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'birthdate',
        'email',
        'qr_code',
        'current_level',
        'current_visits',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->qr_code)) {
                $customer->qr_code = (string) Str::uuid();
            }
        });
    }

    /** Clientes viejos pueden no tener QR: se le asigna uno al necesitarlo. */
    public function ensureQrCode(): string
    {
        if (empty($this->qr_code)) {
            $this->qr_code = (string) Str::uuid();
            $this->saveQuietly();
        }

        return $this->qr_code;
    }

    /** Enlace a "Mi tarjeta" (la app web del cliente). */
    public function cardUrl(): string
    {
        return route('mi-tarjeta', $this->ensureQrCode());
    }

    /**
     * WhatsApp con el mensaje y el enlace de su tarjeta ya escritos. Teléfono
     * de 10 dígitos = México (+52). Sin teléfono válido: null.
     */
    public function whatsappCardUrl(): ?string
    {
        $phone = preg_replace('/\D/', '', (string) $this->phone);

        if (strlen($phone) === 10) {
            $phone = '52'.$phone;
        } elseif (strlen($phone) < 11) {
            return null;
        }

        $first = trim(strtok((string) $this->name, ' ') ?: '');
        $text = "¡Hola {$first}! Tu tarjeta de sellos de Papi's Papas ya vive en tu celular 🍟\n\n"
            ."Ábrela aquí: {$this->cardUrl()}\n\n"
            .'Ahí ves tus sellos, tus premios y nuestras promos. Guárdala en tu pantalla de inicio: funciona aunque no tengas internet.';

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function appleWalletPass()
    {
        return $this->hasOne(AppleWalletPass::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function loyaltyRedemptions()
    {
        return $this->hasMany(CustomerLoyaltyRedemption::class);
    }

    public function currentLoyaltyLevel(): ?LoyaltyLevel
    {
        return LoyaltyLevel::forLevelNumber($this->current_level);
    }

    public function loyaltyQrBase64(int $size = 280): string
    {
        return base64_encode(QrCode::format('svg')->size($size)->margin(1)->generate($this->qr_code));
    }
}
