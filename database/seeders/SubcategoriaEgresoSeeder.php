<?php

namespace Database\Seeders;

use App\Models\SubcategoriaEgreso;
use Illuminate\Database\Seeder;

class SubcategoriaEgresoSeeder extends Seeder
{
    /**
     * Carga las subcategorías predefinidas con que se clasifican los egresos.
     * Es idempotente: se puede ejecutar varias veces sin duplicar registros.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @return void
     */
    public function run(): void
    {
        $subcategorias = [
            // Compras de insumos de cocina
            'Aves',
            'Carnes',
            'Pescados y mariscos',
            'Lácteos',
            'Verduras y frutas',
            'Granos básicos',
            'Abarrotes',
            'Bebidas',
            // Gastos de operación del comedor
            'Desechables',
            'Limpieza',
            'Gas',
            'Servicios básicos',
            // Solo el gasto nominal de salarios, sin ISSS ni AFP
            'Planilla',
            'Otros',
        ];

        foreach ($subcategorias as $nombre) {
            SubcategoriaEgreso::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
