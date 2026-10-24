<?php

namespace Tests\Feature;

use App\Filament\Pages\CardDesignPage;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletCardSettings;
use App\Services\Portal\CardDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** "Diseño de tarjeta": el negocio personaliza "Mi tarjeta". */
class CardDesignTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => "{$role}@test.local", 'password' => 'x', 'pin' => $role === 'manager' ? '1234' : '4321',
            'role_id' => Role::firstOrCreate(['name' => $role])->id, 'is_active' => true]);
    }

    public function test_sin_diseno_la_tarjeta_se_ve_como_siempre(): void
    {
        $d = CardDesign::current();

        $this->assertSame('#0a0a0a', $d['bg']);
        $this->assertSame('#f5b32f', $d['accent']);
        $this->assertSame('🍟', $d['stamp_emoji']);
        $this->assertSame('Tarjeta de sellos', $d['title']);
        $this->assertSame('/pwa/logo-header.png', $d['logo_url']);
        $this->assertSame('#ffffff', $d['fg']);       // texto claro sobre fondo oscuro
        $this->assertSame('#111111', $d['on_accent']); // texto oscuro sobre amarillo
    }

    public function test_el_texto_siempre_se_lee_sobre_el_color_elegido(): void
    {
        $light = CardDesign::build(['bg' => '#ffffff', 'accent' => '#1d4ed8']);
        $this->assertSame('#111111', $light['fg']);        // fondo blanco → texto oscuro
        $this->assertSame('#ffffff', $light['on_accent']); // acento azul → texto blanco

        // Un color mal escrito se ignora.
        $this->assertSame('#0a0a0a', CardDesign::build(['bg' => 'rojo'])['bg']);
    }

    public function test_el_diseno_elegido_se_ve_en_la_tarjeta_del_cliente(): void
    {
        WalletCardSettings::current()->update([
            'card_bg_color' => '#ffffff', 'card_accent_color' => '#dc2626', 'card_title' => 'Club Papi',
            'stamp_emoji' => '⭐', 'gift_icon' => '🥤', 'contact_info' => 'Calzada de la República 102',
        ]);
        $customer = Customer::create(['name' => 'Ana', 'phone' => '5550000001', 'current_visits' => 3]);

        $html = $this->get("/mi-tarjeta/{$customer->qr_code}")
            ->assertOk()
            ->assertSee('--bg:#ffffff', false)
            ->assertSee('--accent:#dc2626', false)
            ->assertSee('Club Papi')
            ->assertSee('Calzada de la República 102')
            ->getContent();

        $this->assertSame(3, substr_count($html, '⭐'));
        $this->assertStringNotContainsString('🍟', $html);
        $this->assertStringContainsString('🥤', $html);

        $this->getJson("/mi-tarjeta/{$customer->qr_code}/datos")->assertJsonPath('design.stamp_emoji', '⭐');
        $this->get("/mi-tarjeta/{$customer->qr_code}/manifest.webmanifest")->assertJsonPath('theme_color', '#ffffff');
    }

    public function test_el_manager_guarda_el_diseno_y_puede_volver_al_original(): void
    {
        $manager = $this->user('manager');

        Livewire::actingAs($manager)
            ->test(CardDesignPage::class)
            ->assertSee('Vista previa')
            ->fillForm(['card_accent_color' => '#16a34a', 'stamp_emoji' => '🥔', 'card_title' => 'Mi club'])
            ->assertSee('🥔') // la vista previa cambia antes de guardar
            ->call('save')
            ->assertHasNoErrors();

        $s = WalletCardSettings::current()->fresh();
        $this->assertSame('#16a34a', $s->card_accent_color);
        $this->assertSame('🥔', $s->stamp_emoji);
        $this->assertNull($s->card_bg_color); // se quedó en el color de siempre → vacío

        Livewire::actingAs($manager)->test(CardDesignPage::class)->callAction('reset');
        $this->assertNull(WalletCardSettings::current()->fresh()->stamp_emoji);
    }

    public function test_un_color_invalido_no_se_guarda(): void
    {
        Livewire::actingAs($this->user('manager'))
            ->test(CardDesignPage::class)
            ->fillForm(['card_bg_color' => 'no-es-color'])
            ->call('save')
            ->assertHasErrors(['data.card_bg_color']);
    }

    public function test_solo_el_manager_entra_al_diseno(): void
    {
        $this->actingAs($this->user('cajero'));

        $this->assertFalse(CardDesignPage::canAccess());
    }
}
