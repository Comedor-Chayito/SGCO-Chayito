<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasa el stock de insumos a 3 decimales para registrar fracciones como 0.005 kg,
     * y vuelve único el nombre del insumo para evitar duplicados en bodega.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003 (CC-46)
     */
    public function up(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            $table->decimal('stock_actual', 14, 6)->default(0)->change();
            $table->decimal('stock_minimo', 14, 6)->default(0)->change();
            $table->unique('nombre');
        });
    }

    /**
     * Regresa el stock de insumos a 2 decimales y quita la restricción de nombre único.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003 (CC-46)
     */
    public function down(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            $table->dropUnique(['nombre']);
            $table->decimal('stock_actual', 10, 2)->default(0)->change();
            $table->decimal('stock_minimo', 10, 2)->default(0)->change();
        });
    }
};
