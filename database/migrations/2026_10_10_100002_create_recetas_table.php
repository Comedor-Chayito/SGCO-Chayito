<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de recetas: insumos y porción base por platillo.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platillo_id')->constrained('platillos')->restrictOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->decimal('cantidad_por_porcion', 10, 2);
            $table->timestamps();

            $table->unique(['platillo_id', 'insumo_id']);
        });
    }

    /**
     * Elimina la tabla de recetas.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};
