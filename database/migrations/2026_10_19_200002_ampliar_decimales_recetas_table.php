<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasa la porción base de las recetas a 3 decimales (ej. 0.125 kg por porción).
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003 (CC-46)
     */
    public function up(): void
    {
        Schema::table('recetas', function (Blueprint $table) {
            $table->decimal('cantidad_por_porcion', 14, 6)->change();
        });
    }

    /**
     * Regresa la porción base de las recetas a 2 decimales.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003 (CC-46)
     */
    public function down(): void
    {
        Schema::table('recetas', function (Blueprint $table) {
            $table->decimal('cantidad_por_porcion', 10, 2)->change();
        });
    }
};
