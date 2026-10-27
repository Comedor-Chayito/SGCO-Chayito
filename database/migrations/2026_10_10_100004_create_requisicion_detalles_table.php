<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de detalle de requisiciones: insumos y cantidades solicitadas.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('requisicion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_id')->constrained('requisiciones')->restrictOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->decimal('cantidad_solicitada', 10, 2);
            $table->decimal('cantidad_aprobada', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Elimina la tabla de detalle de requisiciones.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicion_detalles');
    }
};
