<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el área operativa que solicita los insumos de bodega.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     */
    public function up(): void
    {
        Schema::table('requisiciones', function (Blueprint $table) {
            $table->string('area_solicitante', 30)->after('usuario_aprueba_id');
        });
    }

    /**
     * Elimina el área operativa almacenada en las requisiciones.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     */
    public function down(): void
    {
        Schema::table('requisiciones', function (Blueprint $table) {
            $table->dropColumn('area_solicitante');
        });
    }
};
