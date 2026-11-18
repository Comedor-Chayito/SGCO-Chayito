<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la bandera activa a las mesas para retirarlas del POS sin borrar sus comandas históricas.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->boolean('activa')->default(true)->after('estado');
        });
    }

    /**
     * Quita la bandera activa de las mesas.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->dropColumn('activa');
        });
    }
};
