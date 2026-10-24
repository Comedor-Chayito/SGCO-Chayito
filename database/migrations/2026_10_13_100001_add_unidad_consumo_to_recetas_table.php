<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la columna unidad_consumo a la tabla recetas y amplía la precisión
     * de cantidad_por_porcion para soportar consumos pequeños (ej. 0.003 kg de sal).
     * Ambos campos son necesarios para la deducción automática de inventario con conversión
     * de unidades (US-INV-01 / CC-46).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     */
    public function up(): void
    {
        Schema::table('recetas', function (Blueprint $table) {
            // Amplía la precisión: 6 decimales para consumos tipo 0.003 kg (sal, especias)
            $table->decimal('cantidad_por_porcion', 14, 6)->change();

            // Unidad en la que se expresó la cantidad al registrar la receta (ej. 'g' si la bodega usa 'kg')
            $table->string('unidad_consumo', 20)->nullable()->after('cantidad_por_porcion');
        });

        $recetas = DB::table('recetas')
            ->join('insumos', 'insumos.id', '=', 'recetas.insumo_id')
            ->select('recetas.id', 'insumos.unidad_medida')
            ->get();

        foreach ($recetas as $receta) {
            DB::table('recetas')
                ->where('id', $receta->id)
                ->update(['unidad_consumo' => $receta->unidad_medida]);
        }

        Schema::table('recetas', function (Blueprint $table) {
            $table->string('unidad_consumo', 20)->nullable(false)->change();
        });
    }

    /**
     * Revierte los cambios de esquema sobre la tabla recetas.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     */
    public function down(): void
    {
        Schema::table('recetas', function (Blueprint $table) {
            $table->dropColumn('unidad_consumo');
            $table->decimal('cantidad_por_porcion', 10, 2)->change();
        });
    }
};
