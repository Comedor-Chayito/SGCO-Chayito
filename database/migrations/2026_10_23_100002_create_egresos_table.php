<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de egresos con correlativo interno único y comprobante
     * irrepetible por proveedor.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();
            $table->string('correlativo', 12)->unique();
            $table->foreignId('subcategoria_egreso_id')->constrained('subcategorias_egreso')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('proveedor', 150);
            $table->string('numero_comprobante', 50);
            $table->date('fecha_comprobante');
            $table->decimal('monto_total', 10, 2);
            $table->text('descripcion')->nullable();
            $table->timestamps();

            // Evita registrar dos veces la misma factura del mismo proveedor.
            $table->unique(['proveedor', 'numero_comprobante']);
            $table->index('fecha_comprobante');
        });
    }

    /**
     * Elimina la tabla de egresos.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('egresos');
    }
};
