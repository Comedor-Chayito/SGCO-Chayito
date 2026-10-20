<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasa las cantidades de requisición a 3 decimales, igual que el stock de insumos,
     * para que la transferencia de bodega no redondee.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-004 (CC-46)
     */
    public function up(): void
    {
        Schema::table('requisicion_detalles', function (Blueprint $table) {
            $table->decimal('cantidad_solicitada', 10, 3)->change();
            $table->decimal('cantidad_aprobada', 10, 3)->nullable()->change();
        });
    }

    /**
     * Regresa las cantidades de requisición a 2 decimales.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-004 (CC-46)
     */
    public function down(): void
    {
        Schema::table('requisicion_detalles', function (Blueprint $table) {
            $table->decimal('cantidad_solicitada', 10, 2)->change();
            $table->decimal('cantidad_aprobada', 10, 2)->nullable()->change();
        });
    }
};
