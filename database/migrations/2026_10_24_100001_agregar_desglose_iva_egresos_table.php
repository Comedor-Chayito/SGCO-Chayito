<?php

use App\Services\IvaCreditoFiscalService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega a cada egreso el desglose de IVA crédito fiscal (costo neto e IVA)
     * y lo calcula para los egresos que ya estaban registrados.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – US-CON-02 / RF-CON-006 (CC-68)
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->decimal('costo_neto', 10, 2)->nullable()->after('monto_total');
            $table->decimal('iva_credito_fiscal', 10, 2)->nullable()->after('costo_neto');
        });

        // Los egresos previos se desglosan con la misma fórmula que los nuevos.
        $ivaCreditoFiscalService = app(IvaCreditoFiscalService::class);

        DB::table('egresos')
            ->select(['id', 'monto_total'])
            ->orderBy('id')
            ->chunkById(200, function ($egresos) use ($ivaCreditoFiscalService) {
                foreach ($egresos as $egreso) {
                    DB::table('egresos')
                        ->where('id', $egreso->id)
                        ->update($ivaCreditoFiscalService->calcular((float) $egreso->monto_total));
                }
            });

        Schema::table('egresos', function (Blueprint $table) {
            $table->decimal('costo_neto', 10, 2)->nullable(false)->change();
            $table->decimal('iva_credito_fiscal', 10, 2)->nullable(false)->change();
        });
    }

    /**
     * Elimina el desglose de IVA crédito fiscal de los egresos.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – US-CON-02 / RF-CON-006 (CC-68)
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            $table->dropColumn(['costo_neto', 'iva_credito_fiscal']);
        });
    }
};
