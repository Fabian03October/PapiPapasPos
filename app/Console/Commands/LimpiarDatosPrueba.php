<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;

/**
 * Borra las ventas, clientes, turnos y mensajes de prueba antes de operar
 * de verdad - pensado para correrse UNA VEZ, a mano, en la consola de
 * Railway. Deja intacto el catálogo (productos, categorías, insumos,
 * recetas, grupos de modificadores, promociones, niveles de fidelidad) y
 * la configuración (datos del negocio, tarjeta Wallet).
 *
 * Reinicia el stock de todos los insumos a 0 (para capturar el conteo real
 * desde cero) y borra el historial de movimientos de inventario junto con
 * todo lo demás, ya que quedaría huérfano de las ventas/turnos borrados.
 */
class LimpiarDatosPrueba extends Command
{
    use ConfirmableTrait;

    protected $signature = 'app:limpiar-datos-prueba';

    protected $description = 'Borra ventas, clientes, turnos, mensajes Wallet y usuarios de prueba - conserva catálogo, insumos/recetas y config';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $users = User::with('role')->orderBy('name')->get();

        if ($users->isEmpty()) {
            $this->error('No hay ningún usuario en la base de datos - algo anda mal, no continúo.');

            return self::FAILURE;
        }

        $this->info('Usuarios encontrados:');
        $this->table(
            ['Correo', 'Nombre', 'Rol'],
            $users->map(fn ($u) => [$u->email, $u->name, $u->role->name ?? '-'])
        );

        $keepEmail = $this->ask('¿Cuál correo quieres CONSERVAR? (todos los demás usuarios se borran)');
        $keepUser = $users->firstWhere('email', $keepEmail);

        if (! $keepUser) {
            $this->error("No encontré ningún usuario con el correo \"$keepEmail\" - no continúo.");

            return self::FAILURE;
        }

        $usersToDelete = $users->reject(fn ($u) => $u->id === $keepUser->id);

        $this->newLine();
        $this->warn('Esto va a borrar PARA SIEMPRE (no se puede deshacer):');
        $this->line('  - ' . DB::table('sales')->count() . ' ventas');
        $this->line('  - ' . DB::table('customers')->count() . ' clientes');
        $this->line('  - ' . DB::table('cash_sessions')->count() . ' turnos/cortes de caja');
        $this->line('  - ' . DB::table('wallet_messages')->count() . ' mensajes de Wallet');
        $this->line('  - ' . DB::table('inventory_movements')->count() . ' movimientos de inventario (compras, mermas, ajustes)');
        $this->line('  - ' . $usersToDelete->count() . ' usuario(s): ' . $usersToDelete->pluck('email')->implode(', '));
        $this->line('  - El stock de TODOS los insumos se pone en 0.');
        $this->newLine();
        $this->line('Se conserva: catálogo completo (productos, categorías, insumos, recetas, modificadores, promociones, niveles de fidelidad), datos del negocio, y el usuario ' . $keepUser->email . '.');
        $this->newLine();

        if ($this->ask('Escribe SI (mayúsculas) para confirmar') !== 'SI') {
            $this->error('Cancelado - no se borró nada.');

            return self::FAILURE;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'sale_item_modifiers',
            'sale_items',
            'sale_incident_items',
            'sale_incidents',
            'sales',
            'cash_session_denominations',
            'inventory_count_items',
            'inventory_counts',
            'cash_movements',
            'cash_sessions',
            'customer_loyalty_redemptions',
            'wallet_messages',
            'customers',
            'manager_authorization_codes',
            'pin_reset_tokens',
            'inventory_movements',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        DB::table('users')->whereIn('id', $usersToDelete->pluck('id'))->delete();
        DB::table('ingredients')->update(['stock_qty' => 0]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('Listo. Base de datos limpia - catálogo, insumos/recetas y ' . $keepUser->email . ' se quedaron intactos.');

        return self::SUCCESS;
    }
}
