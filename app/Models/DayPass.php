<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayPass extends Model
{
    /** Sin 0/O, 1/I/L: que no se confundan al escribirlo del papel. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 8;

    public const BENEFITS = [
        'product' => 'Producto gratis',
        'percent' => '% de descuento',
        'amount' => '$ de descuento',
    ];

    protected $fillable = ['code', 'benefit_type', 'product_id', 'value', 'expires_on', 'note', 'created_by', 'used_at', 'used_sale_id'];

    protected $casts = [
        'expires_on' => 'date',
        'used_at' => 'datetime',
        'value' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (DayPass $pass) {
            $pass->code ??= static::newCode();
        });
    }

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Lo que escribió el cajero → como se guardó (mayúsculas, sin espacios ni guiones). */
    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', (string) $code));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'used_sale_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_on->endOfDay()->isPast();
    }

    /** Disponible | Usado | Vencido */
    public function status(): string
    {
        return match (true) {
            $this->used_at !== null => 'Usado',
            $this->isExpired() => 'Vencido',
            default => 'Disponible',
        };
    }

    /** "Papis gratis", "15% de descuento", "$50 de descuento". */
    public function benefitLabel(): string
    {
        return match ($this->benefit_type) {
            'product' => ($this->product?->name ?? 'Producto').' gratis',
            'percent' => rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'% de descuento',
            default => '$'.rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').' de descuento',
        };
    }

    /** "ABCD-EFGH": más fácil de leer en el papel y en pantalla. */
    public function displayCode(): string
    {
        return substr($this->code, 0, 4).'-'.substr($this->code, 4);
    }
}
