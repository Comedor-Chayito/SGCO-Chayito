<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de requisiciones internas de insumos.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('requisiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_solicita_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('usuario_aprueba_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada', 'transferida'])->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->timestamps();

            $table->index('estado');
        });
    }

    /**
     * Elimina la tabla de requisiciones internas.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-10
     * @módulo Inventario – RF-INV-004
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('requisiciones');
    }
};
