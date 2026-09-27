<?php

namespace Tests\Feature;

use App\Jobs\SendWalletMessageJob;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletMessage;
use App\Services\GoogleWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WalletMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'manager']);

        return User::create([
            'name' => 'Manager',
            'email' => 'manager+'.uniqid().'@test.local',
            'password' => 'password',
            'pin' => '1234',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function makeMessage(array $overrides = []): WalletMessage
    {
        return WalletMessage::create(array_merge([
            'title' => 'Promo',
            'body' => 'Descuento especial',
            'audience' => 'all',
            'notify' => true,
            'sent_by_user_id' => $this->makeUser()->id,
            'status' => 'queued',
        ], $overrides));
    }

    public function test_envio_a_todos_llama_send_message_to_class(): void
    {
        $message = $this->makeMessage();

        $mock = Mockery::mock(GoogleWalletService::class);
        $mock->shouldReceive('sendMessageToClass')
            ->once()
            ->with($message->title, $message->body, true)
            ->andReturn(['success' => true, 'quota_exceeded' => false, 'error' => null]);

        (new SendWalletMessageJob($message))->handle($mock);

        $this->assertSame('sent', $message->fresh()->status);
    }

    public function test_envio_a_un_cliente_llama_send_message_to_object(): void
    {
        $customer = Customer::create(['name' => 'Cliente', 'phone' => '5500001111']);
        $message = $this->makeMessage(['audience' => 'customer', 'customer_id' => $customer->id, 'notify' => false]);

        $mock = Mockery::mock(GoogleWalletService::class);
        $mock->shouldReceive('sendMessageToObject')
            ->once()
            ->withArgs(fn ($c, $title, $body, $notify) => $c->is($customer) && $notify === false)
            ->andReturn(['success' => true, 'quota_exceeded' => false, 'error' => null]);

        (new SendWalletMessageJob($message))->handle($mock);

        $this->assertSame('sent', $message->fresh()->status);
    }

    public function test_reintenta_como_text_cuando_se_excede_la_cuota(): void
    {
        $message = $this->makeMessage(['notify' => true]);

        $mock = Mockery::mock(GoogleWalletService::class);
        $mock->shouldReceive('sendMessageToClass')
            ->once()
            ->with($message->title, $message->body, true)
            ->andReturn(['success' => false, 'quota_exceeded' => true, 'error' => 'cuota excedida']);
        $mock->shouldReceive('sendMessageToClass')
            ->once()
            ->with($message->title, $message->body, false)
            ->andReturn(['success' => true, 'quota_exceeded' => false, 'error' => null]);

        (new SendWalletMessageJob($message))->handle($mock);

        $this->assertSame('sent', $message->fresh()->status);
    }

    public function test_no_reintenta_si_no_pedia_notificacion(): void
    {
        $message = $this->makeMessage(['notify' => false]);

        $mock = Mockery::mock(GoogleWalletService::class);
        $mock->shouldReceive('sendMessageToClass')
            ->once()
            ->with($message->title, $message->body, false)
            ->andReturn(['success' => false, 'quota_exceeded' => false, 'error' => 'boom']);

        (new SendWalletMessageJob($message))->handle($mock);

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('boom', $message->error);
    }

    public function test_respeta_el_tope_diario_de_difusiones_con_notificacion(): void
    {
        config(['services.google_wallet.daily_broadcast_limit' => 1]);

        $this->makeMessage(['audience' => 'all', 'notify' => true]);

        $this->assertTrue(WalletMessage::dailyBroadcastLimitReached());

        // Un mensaje a un cliente específico, o sin notificar, no cuenta para el tope.
        $this->makeMessage(['audience' => 'customer', 'customer_id' => Customer::create(['name' => 'C', 'phone' => '5500002222'])->id, 'notify' => true]);
        $this->makeMessage(['audience' => 'all', 'notify' => false]);

        $this->assertSame(1, WalletMessage::dailyBroadcastCount());
    }
}
