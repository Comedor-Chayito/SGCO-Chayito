<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de ventas registradas al procesar el cobro en caja.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comandas')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->decimal('total', 10, 2);
            $table->decimal('monto_recibido', 10, 2);
            $table->decimal('cambio', 10, 2)->default(0.00);
            $table->enum('metodo_pago', ['efectivo', 'transferencia'])->default('efectivo');
            $table->timestamps();

            $table->index('comanda_id');
            $table->index('created_at');
        });
    }

    /**
     * Elimina la tabla de ventas.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};

