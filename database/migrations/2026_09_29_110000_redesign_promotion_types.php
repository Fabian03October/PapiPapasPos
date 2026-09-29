<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De 3 tipos confusos (percent_off_sale, fixed_price_combo,
 * fixed_price_single) a 2 claros (free, discount) + un "scope" explícito
 * (product | modifier) que dice a qué se aplica, sin mezclarlos de forma
 * ambigua como antes. Ver PromotionService y PromotionForm para la lógica
 * nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('scope')->nullable()->after('type');
            $table->string('discount_mode')->nullable()->after('scope');
        });

        // El enum de "type" todavía solo acepta los 3 valores viejos en este
        // punto - si intentáramos guardar 'discount' ahorita, MySQL lo
        // guardaría como '' en silencio (no error). Primero se amplía el
        // enum para aceptar AMBOS juegos de valores, luego se reclasifican
        // los datos, y hasta el final se angosta al juego nuevo nada más.
        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('percent_off_sale', 'fixed_price_combo', 'fixed_price_single', 'free', 'discount') NOT NULL");

        // Reclasifica las promociones que ya existan (para no perder
        // ninguna configuración ya hecha).
        foreach (DB::table('promotions')->get() as $promo) {
            $hasModifiers = DB::table('promotion_modifiers')->where('promotion_id', $promo->id)->exists();

            [$type, $discountMode] = match ($promo->type) {
                'fixed_price_combo', 'fixed_price_single' => ['discount', 'fixed_price'],
                default => ['discount', 'percent'],
            };

            DB::table('promotions')->where('id', $promo->id)->update([
                'scope' => $hasModifiers ? 'modifier' : 'product',
                'discount_mode' => $discountMode,
                'type' => $type,
            ]);
        }

        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('free', 'discount') NOT NULL");
        DB::statement("ALTER TABLE promotions MODIFY COLUMN scope ENUM('product', 'modifier') NOT NULL DEFAULT 'product'");
        DB::statement("ALTER TABLE promotions MODIFY COLUMN discount_mode ENUM('percent', 'fixed_price') NULL");
    }

    public function down(): void
    {
        // Revertir a los 3 tipos viejos es lossy (no se puede distinguir
        // combo de single otra vez) - se regresa todo a percent_off_sale
        // como aproximación segura.
        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('percent_off_sale', 'fixed_price_combo', 'fixed_price_single') NOT NULL DEFAULT 'percent_off_sale'");
        DB::table('promotions')->update(['type' => 'percent_off_sale']);

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['scope', 'discount_mode']);
        });
    }
};
