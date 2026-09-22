<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de cierres diarios de caja (bitácora inalterable).
     * Solo se permite un cierre por día (restricción UNIQUE en fecha).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function up(): void
    {
        Schema::create('cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha')->unique();
            $table->decimal('saldo_inicial', 10, 2);
            $table->decimal('total_ventas_efectivo', 10, 2)->default(0.00);
            $table->decimal('total_ventas_transferencia', 10, 2)->default(0.00);
            $table->decimal('total_retiros', 10, 2)->default(0.00);
            $table->decimal('saldo_final', 10, 2);
            $table->decimal('venta_del_dia', 10, 2);
            $table->decimal('diferencia', 10, 2)->default(0.00);
            $table->text('observaciones')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    /**
     * Elimina la tabla de cierres de caja.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function down(): void
    {
        Schema::dropIfExists('cierres_caja');
    }
};
