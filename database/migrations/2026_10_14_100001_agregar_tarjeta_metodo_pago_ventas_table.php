<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega "tarjeta" a los métodos de pago permitidos en ventas.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002
     *
     * @return void
     */
    public function up(): void
    {
        $this->cambiarEnum(['efectivo', 'tarjeta', 'transferencia']);
    }

    /**
     * Revierte a los métodos originales. Se niega si ya existen ventas con tarjeta,
     * porque reescribirlas alteraría el histórico contable.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002
     *
     * @return void
     *
     * @throws \RuntimeException Si hay ventas registradas con tarjeta.
     */
    public function down(): void
    {
        if (DB::table('ventas')->where('metodo_pago', 'tarjeta')->exists()) {
            throw new \RuntimeException('Existen ventas pagadas con tarjeta; no se puede revertir.');
        }

        $this->cambiarEnum(['efectivo', 'transferencia']);
    }

    /**
     * Redefine los valores permitidos de la columna metodo_pago de ventas.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002
     *
     * @param  array<int, string> $metodos Valores permitidos del enum.
     * @return void
     */
    private function cambiarEnum(array $metodos): void
    {
        Schema::table('ventas', function (Blueprint $table) use ($metodos) {
            $table->enum('metodo_pago', $metodos)->default('efectivo')->change();
        });
    }
};
