<?php

namespace Tests\Feature\Crud;

use App\Models\Platillo;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Scripts de captura (5 corridas) y edición (5 corridas) de la pantalla de platillos (CC-177).
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Administración – RF-ADM-008 / US-ADM-03 / CC-177
 */
class PlatilloCrudTest extends CrudTestCase
{
    /**
     * Corridas de captura: datos del formulario de alta de platillo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-177
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function capturas(): array
    {
        return [
            'cap-pla-01 solo nombre y precio' => [['nombre' => 'Pupusa de frijol', 'precio_unitario' => 0.75]],
            'cap-pla-02 con descripción' => [
                ['nombre' => 'Sopa de gallina', 'descripcion' => 'Con arroz', 'precio_unitario' => 4.50],
            ],
            'cap-pla-03 no disponible' => [
                ['nombre' => 'Tamal pisque', 'precio_unitario' => 1.00, 'disponible' => false],
            ],
            'cap-pla-04 precio entero' => [['nombre' => 'Plato del día', 'precio_unitario' => 3]],
            'cap-pla-05 precio con centavos' => [['nombre' => 'Horchata', 'precio_unitario' => 0.99]],
        ];
    }

    /**
     * Corridas de edición: nuevos nombre, descripción y precio.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-177
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function ediciones(): array
    {
        return [
            'edi-pla-01 cambiar nombre' => [['nombre' => 'Pollo encebollado', 'precio_unitario' => 4.00]],
            'edi-pla-02 subir precio' => [['nombre' => 'Pollo guisado', 'precio_unitario' => 4.75]],
            'edi-pla-03 bajar precio' => [['nombre' => 'Pollo guisado', 'precio_unitario' => 3.25]],
            'edi-pla-04 agregar descripción' => [
                ['nombre' => 'Pollo guisado', 'descripcion' => 'Con tortillas', 'precio_unitario' => 4.00],
            ],
            'edi-pla-05 todo a la vez' => [
                ['nombre' => 'Pollo en salsa', 'descripcion' => 'Picante', 'precio_unitario' => 5.10],
            ],
        ];
    }

    /**
     * Captura un platillo y verifica que quede en el catálogo con su disponibilidad.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-177
     *
     * @param  array<string, mixed>  $datos  Datos del formulario.
     * @return void
     */
    #[DataProvider('capturas')]
    public function test_captura_registra_platillo(array $datos): void
    {
        $this->assertContratoOk($this->postJson('/api/platillos', $datos), 201);

        $this->assertDatabaseHas('platillos', [
            'nombre' => $datos['nombre'],
            'precio_unitario' => $datos['precio_unitario'],
            'disponible' => $datos['disponible'] ?? true,
        ]);
    }

    /**
     * Edita un platillo existente y verifica los datos nuevos.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – RF-ADM-008 / CC-177
     *
     * @param  array<string, mixed>  $datos  Datos del formulario de edición.
     * @return void
     */
    #[DataProvider('ediciones')]
    public function test_edicion_actualiza_platillo(array $datos): void
    {
        $platillo = Platillo::create(['nombre' => 'Pollo guisado', 'precio_unitario' => 4.00]);

        $this->assertContratoOk($this->putJson("/api/platillos/{$platillo->id}", $datos), 200);

        $this->assertDatabaseHas('platillos', ['id' => $platillo->id] + $datos);
    }
}
