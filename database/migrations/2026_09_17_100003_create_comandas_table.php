<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de comandas registradas por meseros o cajeros.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('comandas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesa_id')->nullable()->constrained('mesas')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->enum('canal', ['mesa', 'para_llevar', 'whatsapp'])->default('mesa');
            $table->enum('estado', ['pendiente', 'en_cocina', 'pagada', 'cancelada'])->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->timestamps();

            $table->index('estado');
        });
    }

    /**
     * Elimina la tabla de comandas.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('comandas');
    }
};
