<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombra el estado "en_cocina" a "en_preparacion" y agrega el estado "lista",
     * para que la cocina persista en BD qué comandas ya terminó.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return void
     */
    public function up(): void
    {
        $this->cambiarEnum(['pendiente', 'en_cocina', 'en_preparacion', 'lista', 'pagada', 'cancelada']);

        DB::table('comandas')
            ->where('estado', 'en_cocina')
            ->update(['estado' => 'en_preparacion']);

        $this->cambiarEnum(['pendiente', 'en_preparacion', 'lista', 'pagada', 'cancelada']);
    }

    /**
     * Revierte a los estados originales: "en_preparacion" y "lista" vuelven a "en_cocina".
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return void
     */
    public function down(): void
    {
        $this->cambiarEnum(['pendiente', 'en_cocina', 'en_preparacion', 'lista', 'pagada', 'cancelada']);

        DB::table('comandas')
            ->whereIn('estado', ['en_preparacion', 'lista'])
            ->update(['estado' => 'en_cocina']);

        $this->cambiarEnum(['pendiente', 'en_cocina', 'pagada', 'cancelada']);
    }

    /**
     * Redefine los valores permitidos de la columna estado de comandas.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  array<int, string> $estados Valores permitidos del enum.
     * @return void
     */
    private function cambiarEnum(array $estados): void
    {
        Schema::table('comandas', function (Blueprint $table) use ($estados) {
            $table->enum('estado', $estados)->default('pendiente')->change();
        });
    }
};
