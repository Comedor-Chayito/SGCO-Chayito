<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de retiros parciales de caja realizados por el cajero durante su turno.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('retiros_parciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('motivo', 150);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Elimina la tabla de retiros parciales.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('retiros_parciales');
    }
};
