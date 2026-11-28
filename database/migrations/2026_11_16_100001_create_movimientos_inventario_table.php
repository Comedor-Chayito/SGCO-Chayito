<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de movimientos de inventario: cada cambio de stock_actual de un insumo
     * deja un registro con tipo, cantidad con signo, stock resultante y usuario.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-16
     *
     * @módulo Inventario – US-INV-04 (CC-114)
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->enum('tipo', ['entrada', 'venta', 'requisicion', 'ajuste']);

            // Positiva cuando el stock sube, negativa cuando baja.
            $table->decimal('cantidad', 14, 6);
            $table->decimal('stock_resultante', 14, 6);

            $table->foreignId('egreso_id')->nullable()->constrained('egresos')->restrictOnDelete();
            $table->foreignId('comanda_id')->nullable()->constrained('comandas')->restrictOnDelete();
            $table->foreignId('requisicion_id')->nullable()->constrained('requisiciones')->restrictOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->timestamps();

            $table->index(['insumo_id', 'created_at']);
            $table->index('tipo');
        });
    }

    /**
     * Elimina la tabla de movimientos de inventario.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-16
     *
     * @módulo Inventario – US-INV-04 (CC-114)
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
