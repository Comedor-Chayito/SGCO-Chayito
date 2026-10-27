<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía la precisión del inventario para conservar descuentos por receta en fracciones.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003 / CC-47
     */
    public function up(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            $table->decimal('stock_actual', 14, 6)->default(0)->change();
            $table->decimal('stock_minimo', 14, 6)->default(0)->change();
        });
    }

    /**
     * Restablece la precisión original del inventario.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003 / CC-47
     */
    public function down(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            $table->decimal('stock_actual', 10, 2)->default(0)->change();
            $table->decimal('stock_minimo', 10, 2)->default(0)->change();
        });
    }
};
