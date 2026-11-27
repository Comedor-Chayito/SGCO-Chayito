<?php

namespace Tests\Feature\Crud;

use App\Models\Insumo;
use App\Models\Platillo;
use App\Models\Receta;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Scripts de captura (5 corridas) y edición (5 corridas) de la pantalla de recetas (CC-177).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-27
 * @módulo Inventario – RF-INV-003 / US-INV-01 / CC-177
 */
class RecetaCrudTest extends CrudTestCase
{
    /**
     * Corridas de captura: unidad de bodega del insumo, porción y unidad de consumo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-177
     *
     * @return array<string, array{string, float, string}>
     */
    public static function capturas(): array
    {
        return [
            'cap-rec-01 gramos de insumo en kg' => ['kg', 200, 'g'],
            'cap-rec-02 mililitros de insumo en lt' => ['lt', 250, 'ml'],
            'cap-rec-03 unidades' => ['unidad', 2, 'unidad'],
            'cap-rec-04 onzas de insumo en libras' => ['libra', 4, 'onza'],
            'cap-rec-05 misma unidad decimal' => ['kg', 0.5, 'kg'],
        ];
    }

    /**
     * Corridas de edición: nueva porción y unidad de consumo para un insumo almacenado en kg.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-177
     *
     * @return array<string, array{float, string}>
     */
    public static function ediciones(): array
    {
        return [
            'edi-rec-01 aumentar gramos' => [150, 'g'],
            'edi-rec-02 pasar a kg' => [0.2, 'kg'],
            'edi-rec-03 pasar a onzas' => [8, 'onza'],
            'edi-rec-04 pasar a libras' => [1, 'libra'],
            'edi-rec-05 porción mínima' => [0.001, 'kg'],
        ];
    }

    /**
     * Captura un renglón de receta y verifica que quede vinculado al platillo con su porción.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-177
     *
     * @param  string $unidadBodega  Unidad de almacenamiento del insumo.
     * @param  float  $porcion       Cantidad por platillo.
     * @param  string $unidadConsumo Unidad de la porción.
     * @return void
     */
    #[DataProvider('capturas')]
    public function test_captura_agrega_ingrediente_a_la_receta(
        string $unidadBodega,
        float $porcion,
        string $unidadConsumo
    ): void {
        $platillo = Platillo::create(['nombre' => 'Platillo base', 'precio_unitario' => 3.00]);
        $insumo = Insumo::create(['nombre' => 'Insumo base', 'unidad_medida' => $unidadBodega]);

        $respuesta = $this->postJson("/api/platillos/{$platillo->id}/recetas", [
            'insumo_id' => $insumo->id,
            'cantidad_por_porcion' => $porcion,
            'unidad_consumo' => $unidadConsumo,
        ]);

        $this->assertContratoOk($respuesta, 201);
        $this->assertDatabaseHas('recetas', [
            'platillo_id' => $platillo->id,
            'insumo_id' => $insumo->id,
            'cantidad_por_porcion' => $porcion,
            'unidad_consumo' => $unidadConsumo,
        ]);
    }

    /**
     * Edita un renglón de receta existente y verifica la nueva porción y unidad.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-177
     *
     * @param  float  $porcion       Nueva cantidad por platillo.
     * @param  string $unidadConsumo Nueva unidad de la porción.
     * @return void
     */
    #[DataProvider('ediciones')]
    public function test_edicion_actualiza_renglon_de_receta(float $porcion, string $unidadConsumo): void
    {
        $platillo = Platillo::create(['nombre' => 'Platillo base', 'precio_unitario' => 3.00]);
        $insumo = Insumo::create(['nombre' => 'Carne de res', 'unidad_medida' => 'kg']);
        $receta = Receta::create([
            'platillo_id' => $platillo->id,
            'insumo_id' => $insumo->id,
            'cantidad_por_porcion' => 100,
            'unidad_consumo' => 'g',
        ]);

        $respuesta = $this->putJson("/api/platillos/{$platillo->id}/recetas/{$receta->id}", [
            'cantidad_por_porcion' => $porcion,
            'unidad_consumo' => $unidadConsumo,
        ]);

        $this->assertContratoOk($respuesta, 200);
        $this->assertDatabaseHas('recetas', [
            'id' => $receta->id,
            'cantidad_por_porcion' => $porcion,
            'unidad_consumo' => $unidadConsumo,
        ]);
    }
}
