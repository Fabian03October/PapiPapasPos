<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WalletCardSettings extends Model
{
    protected $table = 'wallet_card_settings';

    protected $fillable = [
        'program_name',
        'issuer_name',
        'logo_path',
        'hero_image_path',
        'hex_background_color',
        'contact_info',
    ];

    /**
     * Fila única de configuración. Ojo: buscarla por id=1 se rompe en
     * cuanto esa fila deja de tener exactamente ese id (ej. un rollback de
     * transacción en pruebas hace que MySQL nunca reutilice el 1) - cada
     * llamada crearía una fila nueva en vez de reusar la guardada. Por eso
     * se toma "la primera que exista", sin asumir cuál id tiene.
     */
    public static function current(): self
    {
        return static::first() ?? static::create([
            'program_name' => config('services.google_wallet.program_name'),
            'issuer_name' => config('services.google_wallet.issuer_name'),
            'hex_background_color' => config('services.google_wallet.hex_background_color'),
        ]);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : config('services.google_wallet.logo_url');
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        return $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null;
    }
}
