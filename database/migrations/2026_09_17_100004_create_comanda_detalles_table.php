<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de detalle de comandas: platillos y cantidades por comanda.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('comanda_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_id')->constrained('comandas')->restrictOnDelete();
            $table->foreignId('platillo_id')->constrained('platillos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Elimina la tabla de detalle de comandas.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('comanda_detalles');
    }
};
