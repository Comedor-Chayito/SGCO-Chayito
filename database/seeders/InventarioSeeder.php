<?php

namespace Database\Seeders;

use App\Models\Insumo;
use App\Models\Platillo;
use App\Models\Receta;
use Illuminate\Database\Seeder;

class InventarioSeeder extends Seeder
{
    /**
     * Inserta datos ficticios de demostración para el módulo Inventario:
     * insumos de bodega (con casos normales, bajo mínimo y agotados) y una receta
     * por cada platillo de PosSeeder. No son las recetas reales del comedor.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003 / RF-INV-004
     *
     * @return void
     */
    public function run(): void
    {
        // [nombre, unidad_medida, stock_actual, stock_minimo]
        $insumos = [
            ['Pollo en piezas',      'kg',     18.500, 8.000],
            ['Carne de res',         'kg',      6.200, 8.000],
            ['Carne molida',         'kg',      4.000, 3.000],
            ['Mojarra',              'unidad',  0.000, 10.000],
            ['Gallina india',        'unidad',  2.000, 3.000],
            ['Huevo',                'unidad', 30.000, 24.000],
            ['Arroz',                'kg',     22.000, 10.000],
            ['Frijol rojo',          'kg',      3.500, 5.000],
            ['Masa de maíz',         'kg',     25.000, 10.000],
            ['Espagueti',            'kg',      5.000, 2.000],
            ['Pasta codito',         'kg',      2.000, 1.000],
            ['Queso duro',           'kg',      0.800, 1.500],
            ['Quesillo',             'kg',      0.000, 2.000],
            ['Cuajada',              'kg',      2.500, 1.000],
            ['Aceite vegetal',       'lt',      9.000, 4.000],
            ['Mayonesa',             'lt',      1.200, 1.000],
            ['Tomate',               'kg',      6.000, 3.000],
            ['Cebolla',              'kg',      4.000, 2.000],
            ['Papa',                 'kg',      7.000, 3.000],
            ['Zanahoria',            'kg',      3.000, 2.000],
            ['Güisquil',             'unidad',  8.000, 6.000],
            ['Lechuga',              'unidad',  4.000, 5.000],
            ['Pepino',               'unidad',  6.000, 4.000],
            ['Aguacate',             'unidad', 12.000, 10.000],
            ['Naranja',              'unidad', 40.000, 30.000],
            ['Fruta de temporada',   'kg',      5.000, 3.000],
            ['Azúcar',               'kg',      8.000, 3.000],
            ['Soda en lata',         'unidad',  0.000, 24.000],
        ];

        foreach ($insumos as [$nombre, $unidad, $stockActual, $stockMinimo]) {
            Insumo::firstOrCreate(['nombre' => $nombre], [
                'unidad_medida' => $unidad,
                'stock_actual'  => $stockActual,
                'stock_minimo'  => $stockMinimo,
            ]);
        }

        // Platillo => [insumo => cantidad por porción vendida]
        $recetas = [
            'Pollo Asado'         => ['Pollo en piezas' => 0.300, 'Aceite vegetal' => 0.010],
            'Carne Guisada'       => [
                'Carne de res' => 0.200,
                'Papa'         => 0.100,
                'Tomate'       => 0.050,
                'Cebolla'      => 0.030,
            ],
            'Pescado Frito'       => ['Mojarra' => 1.000, 'Aceite vegetal' => 0.050],
            'Tortitas de Carne'   => [
                'Carne molida'   => 0.150,
                'Huevo'          => 1.000,
                'Tomate'         => 0.060,
                'Aceite vegetal' => 0.020,
            ],
            'Pollo Encebollado'   => [
                'Pollo en piezas' => 0.250,
                'Cebolla'         => 0.080,
                'Aceite vegetal'  => 0.015,
            ],
            'Arroz Blanco'        => ['Arroz' => 0.080, 'Aceite vegetal' => 0.005],
            'Arroz Negrito'       => ['Arroz' => 0.080, 'Frijol rojo' => 0.030],
            'Casamiento'          => ['Arroz' => 0.050, 'Frijol rojo' => 0.050, 'Aceite vegetal' => 0.010],
            'Frijol Molido'       => ['Frijol rojo' => 0.080, 'Aceite vegetal' => 0.010],
            'Espagueti con Queso' => ['Espagueti' => 0.100, 'Queso duro' => 0.020, 'Tomate' => 0.040],
            'Ensalada Fresca'     => ['Lechuga' => 0.125, 'Tomate' => 0.050, 'Pepino' => 0.250],
            'Ensalada de Codos'   => ['Pasta codito' => 0.070, 'Mayonesa' => 0.030],
            'Verduras Cocidas'    => ['Zanahoria' => 0.060, 'Güisquil' => 0.250],
            'Cuajada'             => ['Cuajada' => 0.080],
            'Queso Fresco'        => ['Queso duro' => 0.060],
            'Aguacate'            => ['Aguacate' => 0.500],
            'Tortillas (x3)'      => ['Masa de maíz' => 0.120],
            'Pupusas de queso'    => ['Masa de maíz' => 0.090, 'Quesillo' => 0.040, 'Aceite vegetal' => 0.005],
            'Pupusas de frijol'   => ['Masa de maíz' => 0.090, 'Frijol rojo' => 0.040, 'Aceite vegetal' => 0.005],
            'Sopa de res'         => [
                'Carne de res' => 0.150,
                'Papa'         => 0.080,
                'Zanahoria'    => 0.040,
                'Güisquil'     => 0.250,
            ],
            'Sopa de Gallina'     => ['Gallina india' => 0.250, 'Papa' => 0.080, 'Zanahoria' => 0.040],
            'Refresco Natural'    => ['Fruta de temporada' => 0.150, 'Azúcar' => 0.030],
            'Jugo de Naranja'     => ['Naranja' => 3.000],
            'Soda Lata'           => ['Soda en lata' => 1.000],
        ];

        $idsInsumo = Insumo::pluck('id', 'nombre');
        $idsPlatillo = Platillo::pluck('id', 'nombre');

        foreach ($recetas as $platillo => $ingredientes) {
            if (! isset($idsPlatillo[$platillo])) {
                continue;
            }

            foreach ($ingredientes as $insumo => $cantidad) {
                Receta::updateOrCreate(
                    ['platillo_id' => $idsPlatillo[$platillo], 'insumo_id' => $idsInsumo[$insumo]],
                    ['cantidad_por_porcion' => $cantidad]
                );
            }
        }
    }
}
