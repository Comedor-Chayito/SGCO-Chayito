<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea los comprobantes de compras con su desglose de IVA crédito fiscal.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_compra');
            $table->decimal('monto_total', 10, 2);
            $table->decimal('costo_neto', 10, 2);
            $table->decimal('iva_credito_fiscal', 10, 2);
            $table->timestamps();

            $table->index('fecha_compra');
            $table->index('created_at');
        });
    }

    /**
     * Elimina los registros de compras y su desglose contable.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
