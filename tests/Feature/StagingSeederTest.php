<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * deploy.sh corre los seeders en CADA deploy, también en producción: los
 * datos de staging solo pueden aparecer con SEED_STAGING_DATA=true.
 */
class StagingSeederTest extends TestCase
{
    use RefreshDatabase;

    private function setFlag(?string $value): void
    {
        if ($value === null) {
            putenv('SEED_STAGING_DATA');
            unset($_ENV['SEED_STAGING_DATA'], $_SERVER['SEED_STAGING_DATA']);
        } else {
            putenv("SEED_STAGING_DATA={$value}");
            $_ENV['SEED_STAGING_DATA'] = $_SERVER['SEED_STAGING_DATA'] = $value;
        }
    }

    protected function tearDown(): void
    {
        $this->setFlag(null);
        parent::tearDown();
    }

    public function test_sin_la_variable_no_carga_datos_de_staging(): void
    {
        $this->setFlag(null);
        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(Product::where('name', 'Papi-rings')->exists());
        $this->assertFalse(User::where('email', 'cajero@staging.test')->exists());
        $this->assertNull(CashSession::open());
    }

    public function test_con_la_variable_carga_catalogo_cajero_y_caja_sin_duplicar(): void
    {
        $this->setFlag('true');
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // como pasa en cada deploy

        $this->assertSame(1, Product::where('name', 'Papi-rings')->count());
        $this->assertSame(3, Product::where('name', 'Refrescos')->first()->variants()->count());
        $this->assertSame(1, User::where('email', 'cajero@staging.test')->count());
        $this->assertSame(1, CashSession::whereNull('closed_at')->count());
    }
}
