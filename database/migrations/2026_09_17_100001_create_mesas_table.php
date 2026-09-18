<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de mesas del comedor.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique();
            $table->unsignedTinyInteger('capacidad')->nullable();
            $table->enum('estado', ['libre', 'ocupada'])->default('libre');
            $table->timestamps();
        });
    }

    /**
     * Elimina la tabla de mesas del comedor.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-17
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
