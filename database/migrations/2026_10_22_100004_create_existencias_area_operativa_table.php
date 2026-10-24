<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea el inventario operativo que recibe transferencias desde la bodega central.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-54
     */
    public function up(): void
    {
        Schema::create('existencias_area_operativa', function (Blueprint $table) {
            $table->id();
            $table->string('area', 30);
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->decimal('stock_actual', 14, 6)->default(0);
            $table->timestamps();

            $table->unique(['area', 'insumo_id']);
        });
    }

    /**
     * Elimina el inventario operativo de las áreas.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-54
     */
    public function down(): void
    {
        Schema::dropIfExists('existencias_area_operativa');
    }
};
