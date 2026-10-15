<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'pin',
        'role_id',
        'is_active',
        'must_change_pin',
    ];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'is_active' => 'boolean',
            'must_change_pin' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Usuarios activos con este PIN. Se entra al sistema SOLO con el PIN, así
     * que dos personas con el mismo PIN = una puede entrar como la otra
     * (incluso como el manager). Los PIN se guardan cifrados: hay que
     * compararlos uno por uno.
     */
    public static function conPin(string $pin): \Illuminate\Support\Collection
    {
        return static::with('role')
            ->where('is_active', true)
            ->get()
            ->filter(fn (self $user) => $user->pin && \Illuminate\Support\Facades\Hash::check($pin, $user->pin))
            ->values();
    }

    public static function pinEnUso(string $pin, ?int $exceptUserId = null): bool
    {
        return static::conPin($pin)->contains(fn (self $user) => $user->id !== $exceptUserId);
    }

    public function esManager(): bool
    {
        return $this->role?->name === 'manager';
    }

    public function esGerente(): bool
    {
        return $this->role?->name === 'gerente';
    }

    /**
     * Manager y gerente: entran al panel admin, generan códigos y cancelan o
     * reponen productos SIN pedir código (solo el cajero lo necesita). La
     * sección "Administración" (usuarios, configuración del negocio) es solo
     * del manager.
     */
    public function puedeAutorizar(): bool
    {
        return $this->esManager() || $this->esGerente();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->puedeAutorizar();
    }
}