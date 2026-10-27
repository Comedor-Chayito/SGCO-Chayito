<?php

namespace Database\Seeders;

use App\Models\Mesa;
use App\Models\Platillo;
use Illuminate\Database\Seeder;

class PosSeeder extends Seeder
{
    /**
     * Inserta datos de prueba para el módulo POS:
     * 10 mesas del comedor y 8 platillos del menú diario típico.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function run(): void
    {
        // Mesas del comedor
        $mesas = [
            ['numero' => 1,  'capacidad' => 4,  'estado' => 'libre'],
            ['numero' => 2,  'capacidad' => 4,  'estado' => 'libre'],
            ['numero' => 3,  'capacidad' => 6,  'estado' => 'libre'],
            ['numero' => 4,  'capacidad' => 6,  'estado' => 'libre'],
            ['numero' => 5,  'capacidad' => 2,  'estado' => 'libre'],
            ['numero' => 6,  'capacidad' => 2,  'estado' => 'libre'],
            ['numero' => 7,  'capacidad' => 4,  'estado' => 'libre'],
            ['numero' => 8,  'capacidad' => 4,  'estado' => 'libre'],
            ['numero' => 9,  'capacidad' => 8,  'estado' => 'libre'],
            ['numero' => 10, 'capacidad' => 8,  'estado' => 'libre'],
        ];

        foreach ($mesas as $mesa) {
            Mesa::firstOrCreate(['numero' => $mesa['numero']], $mesa);
        }

        // Platillos del menú diario (datos ficticios para pruebas)
        $platillos = [
            ['nombre' => 'Pollo Asado',              'descripcion' => 'Porción de pollo asado al carbón',    'precio_unitario' => 2.50, 'disponible' => true],
            ['nombre' => 'Carne Guisada',            'descripcion' => 'Carne de res guisada con papas',      'precio_unitario' => 3.00, 'disponible' => true],
            ['nombre' => 'Pescado Frito',            'descripcion' => 'Mojarra frita entera',                'precio_unitario' => 3.50, 'disponible' => true],
            ['nombre' => 'Tortitas de Carne',        'descripcion' => 'Tortitas de res con salsa de tomate', 'precio_unitario' => 2.25, 'disponible' => true],
            ['nombre' => 'Pollo Encebollado',        'descripcion' => 'Pechuga de pollo con cebolla frita',  'precio_unitario' => 2.50, 'disponible' => true],
            ['nombre' => 'Arroz Blanco',             'descripcion' => 'Porción de arroz blanco',             'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Arroz Negrito',            'descripcion' => 'Arroz cocido con caldo de frijol',    'precio_unitario' => 0.60, 'disponible' => true],
            ['nombre' => 'Casamiento',               'descripcion' => 'Mezcla de arroz y frijoles fritos',   'precio_unitario' => 0.75, 'disponible' => true],
            ['nombre' => 'Frijol Molido',            'descripcion' => 'Frijoles molidos fritos',             'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Espagueti con Queso',      'descripcion' => 'Porción de espagueti',                'precio_unitario' => 0.80, 'disponible' => true],
            ['nombre' => 'Ensalada Fresca',          'descripcion' => 'Lechuga, tomate, pepino y rábano',    'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Ensalada de Codos',        'descripcion' => 'Ensalada de pasta fría con mayonesa', 'precio_unitario' => 0.60, 'disponible' => true],
            ['nombre' => 'Verduras Cocidas',         'descripcion' => 'Zanahoria, güisquil y ejote',         'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Cuajada',                  'descripcion' => 'Porción de cuajada fresca',           'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Queso Fresco',             'descripcion' => 'Porción de queso duro o fresco',      'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Aguacate',                 'descripcion' => 'Mitad de aguacate',                   'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Tortillas (x3)',           'descripcion' => 'Tortillas de maíz artesanales',       'precio_unitario' => 0.25, 'disponible' => true],
            ['nombre' => 'Pupusas de queso',         'descripcion' => 'Pupusas rellenas de queso',           'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Pupusas de frijol',        'descripcion' => 'Pupusas rellenas de frijol',          'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Sopa de res',              'descripcion' => 'Sopa de res con verduras',            'precio_unitario' => 2.00, 'disponible' => true],
            ['nombre' => 'Sopa de Gallina',          'descripcion' => 'Sopa tradicional de gallina india',   'precio_unitario' => 2.50, 'disponible' => true],
            ['nombre' => 'Refresco Natural',         'descripcion' => 'Refresco de temporada',               'precio_unitario' => 0.50, 'disponible' => true],
            ['nombre' => 'Jugo de Naranja',          'descripcion' => 'Jugo natural exprimido',              'precio_unitario' => 1.00, 'disponible' => true],
            ['nombre' => 'Soda Lata',                'descripcion' => 'Bebida carbonatada en lata',          'precio_unitario' => 0.75, 'disponible' => true],
        ];

        foreach ($platillos as $platillo) {
            Platillo::firstOrCreate(['nombre' => $platillo['nombre']], $platillo);
        }
    }
}
