<?php

namespace Tests\Feature\Crud;

use App\Models\Insumo;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Scripts de captura (5 corridas) y edición (5 corridas) de la pantalla de insumos (CC-177).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-27
 * @módulo Inventario – RF-INV-003 / CC-177
 */
class InsumoCrudTest extends CrudTestCase
{
    /**
     * Corridas de captura: datos del formulario de alta de insumo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-177
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function capturas(): array
    {
        return [
            'cap-ins-01 masa en kg' => [
                ['nombre' => 'Harina de maíz', 'unidad_medida' => 'kg', 'stock_minimo' => 5],
            ],
            'cap-ins-02 volumen en lt' => [
                ['nombre' => 'Aceite vegetal', 'unidad_medida' => 'lt', 'stock_minimo' => 2],
            ],
            'cap-ins-03 conteo en unidades' => [
                ['nombre' => 'Huevos', 'unidad_medida' => 'unidad', 'stock_minimo' => 30],
            ],
            'cap-ins-04 sin mínimo' => [['nombre' => 'Sal', 'unidad_medida' => 'libra']],
            'cap-ins-05 mínimo decimal' => [
                ['nombre' => 'Café molido', 'unidad_medida' => 'kg', 'stock_minimo' => 0.5],
            ],
        ];
    }

    /**
     * Corridas de edición: datos nuevos para un insumo sin existencias ni movimientos.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-177
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function ediciones(): array
    {
        return [
            'edi-ins-01 renombrar' => [['nombre' => 'Queso duro blando', 'unidad_medida' => 'kg', 'stock_minimo' => 1]],
            'edi-ins-02 subir mínimo' => [['nombre' => 'Queso duro', 'unidad_medida' => 'kg', 'stock_minimo' => 4]],
            'edi-ins-03 mínimo en cero' => [['nombre' => 'Queso duro', 'unidad_medida' => 'kg', 'stock_minimo' => 0]],
            'edi-ins-04 cambiar unidad sin saldo' => [
                ['nombre' => 'Queso duro', 'unidad_medida' => 'libra', 'stock_minimo' => 2],
            ],
            'edi-ins-05 todo a la vez' => [['nombre' => 'Quesillo', 'unidad_medida' => 'g', 'stock_minimo' => 500]],
        ];
    }

    /**
     * Captura un insumo: se registra con existencia cero y el mínimo indicado.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-177
     *
     * @param  array<string, mixed> $datos Datos del formulario.
     * @return void
     */
    #[DataProvider('capturas')]
    public function test_captura_registra_insumo(array $datos): void
    {
        $this->assertContratoOk($this->postJson('/api/insumos', $datos), 201);

        $this->assertDatabaseHas('insumos', [
            'nombre' => $datos['nombre'],
            'unidad_medida' => $datos['unidad_medida'],
            'stock_actual' => 0,
            'stock_minimo' => $datos['stock_minimo'] ?? 0,
        ]);
    }

    /**
     * Edita un insumo existente y verifica que se guarden los datos nuevos.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-177
     *
     * @param  array<string, mixed> $datos Datos del formulario de edición.
     * @return void
     */
    #[DataProvider('ediciones')]
    public function test_edicion_actualiza_insumo(array $datos): void
    {
        $insumo = Insumo::create(['nombre' => 'Queso duro', 'unidad_medida' => 'kg', 'stock_minimo' => 1]);

        $this->assertContratoOk($this->putJson("/api/insumos/{$insumo->id}", $datos), 200);

        $this->assertDatabaseHas('insumos', ['id' => $insumo->id] + $datos);
    }
}
