<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea el catálogo de subcategorías con que se clasifican los egresos.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('subcategorias_egreso', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Elimina el catálogo de subcategorías de egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('subcategorias_egreso');
    }
};
