<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el total de ventas con tarjeta a la bitácora de cierre de caja,
     * para que el reporte muestre lo vendido por cada método de pago.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-41)
     */
    public function up(): void
    {
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->decimal('total_ventas_tarjeta', 10, 2)->default(0.00)->after('total_ventas_efectivo');
        });
    }

    /**
     * Elimina el total de ventas con tarjeta de la bitácora de cierre.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-41)
     */
    public function down(): void
    {
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->dropColumn('total_ventas_tarjeta');
        });
    }
};
