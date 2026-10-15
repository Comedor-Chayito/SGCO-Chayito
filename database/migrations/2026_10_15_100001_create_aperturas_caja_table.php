<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de aperturas de caja con desglose de saldo inicial por denominación.
     * Solo se permite una apertura por día comercial (restricción UNIQUE en fecha).
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     */
    public function up(): void
    {
        Schema::create('aperturas_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha')->unique();
            $table->decimal('saldo_inicial', 10, 2);
            $table->json('denominaciones');
            $table->text('observaciones')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    /**
     * Elimina la tabla de aperturas de caja.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     */
    public function down(): void
    {
        Schema::dropIfExists('aperturas_caja');
    }
};
