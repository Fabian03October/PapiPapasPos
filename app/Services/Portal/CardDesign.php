<?php

namespace App\Services\Portal;

use App\Models\WalletCardSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * El diseño de "Mi tarjeta" que el negocio elige en el panel ("Diseño de
 * tarjeta"), con los valores de siempre cuando algo no se ha elegido. Calcula
 * solo los colores de texto para que se lean sobre el fondo y sobre el acento.
 */
class CardDesign
{
    public const DEFAULTS = [
        'bg' => '#0a0a0a',
        'accent' => '#f5b32f',
        'title' => 'Tarjeta de sellos',
        'stamp_emoji' => '🍟',
        'discount_icon' => '%',
        'gift_icon' => '🎁',
    ];

    /**
     * @return array{bg: string, accent: string, fg: string, on_accent: string, muted: string, panel: string,
     *     panel_2: string, line: string, title: string, stamp_emoji: string, stamp_image_url: ?string,
     *     logo_url: string, discount_icon: string, gift_icon: string, welcome_message: ?string, contact_info: ?string}
     */
    /** Subir la versión si cambian las llaves del arreglo (un caché viejo no las tendría). */
    public const CACHE_KEY = 'mi-tarjeta:diseno:v2';

    public static function current(?WalletCardSettings $settings = null): array
    {
        // En caché: se lee en cada visita a "Mi tarjeta" y casi nunca cambia.
        // Se borra al guardar el diseño (WalletCardSettings::booted).
        if (! $settings) {
            return Cache::rememberForever(self::CACHE_KEY, fn () => self::current(WalletCardSettings::current()));
        }

        $s = $settings;

        return self::build([
            'bg' => $s->card_bg_color,
            'accent' => $s->card_accent_color,
            'title' => $s->card_title,
            'stamp_emoji' => $s->stamp_emoji,
            'stamp_image_path' => $s->stamp_image_path,
            'logo_path' => $s->card_logo_path,
            'discount_icon' => $s->discount_icon,
            'gift_icon' => $s->gift_icon,
            'welcome_message' => $s->welcome_message,
            'contact_info' => $s->contact_info,
        ]);
    }

    /** Desde valores sueltos (también lo usa la vista previa del panel antes de guardar). */
    public static function build(array $v): array
    {
        $bg = self::hex($v['bg'] ?? null) ?? self::DEFAULTS['bg'];
        $accent = self::hex($v['accent'] ?? null) ?? self::DEFAULTS['accent'];
        $dark = self::luminance($bg) < 0.4;
        $fg = $dark ? '#ffffff' : '#111111';

        return [
            'bg' => $bg,
            'accent' => $accent,
            'fg' => $fg,
            'on_accent' => self::luminance($accent) < 0.45 ? '#ffffff' : '#111111',
            'muted' => self::mix($fg, $bg, 0.6),
            'panel' => self::mix($fg, $bg, 0.07),
            'panel_2' => self::mix($fg, $bg, 0.12),
            'line' => self::mix($fg, $bg, 0.3),
            'title' => filled($v['title'] ?? null) ? $v['title'] : self::DEFAULTS['title'],
            'stamp_emoji' => filled($v['stamp_emoji'] ?? null) ? $v['stamp_emoji'] : self::DEFAULTS['stamp_emoji'],
            'stamp_image_url' => self::publicUrl($v['stamp_image_path'] ?? null),
            'logo_url' => self::publicUrl($v['logo_path'] ?? null) ?? '/pwa/logo-header.png',
            // El logo de siempre trae fondo negro: sobre fondos claros va en
            // una placa negra (si no, se ve como un rectángulo pegado).
            'logo_badge' => ! self::publicUrl($v['logo_path'] ?? null) && ! $dark,
            'discount_icon' => filled($v['discount_icon'] ?? null) ? $v['discount_icon'] : self::DEFAULTS['discount_icon'],
            'gift_icon' => filled($v['gift_icon'] ?? null) ? $v['gift_icon'] : self::DEFAULTS['gift_icon'],
            'welcome_message' => filled($v['welcome_message'] ?? null) ? $v['welcome_message'] : null,
            'contact_info' => filled($v['contact_info'] ?? null) ? $v['contact_info'] : null,
        ];
    }

    /** Variables CSS para la página de la tarjeta. */
    public static function cssVars(array $d): string
    {
        return collect(['bg', 'fg', 'accent', 'on_accent', 'muted', 'panel', 'panel_2', 'line'])
            ->map(fn ($k) => '--'.str_replace('_', '-', $k).':'.$d[$k])
            ->implode(';');
    }

    protected static function publicUrl(?string $path): ?string
    {
        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    protected static function hex(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
    }

    /** @return array{0: int, 1: int, 2: int} */
    protected static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    protected static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** $amount de $top sobre $base (0 = base, 1 = top). */
    protected static function mix(string $top, string $base, float $amount): string
    {
        $t = self::rgb($top);
        $b = self::rgb($base);

        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($b[$i] + ($t[$i] - $b[$i]) * $amount), [0, 1, 2]));
    }
}
