<?php

namespace Tests\Feature\Crud;

use App\Models\Mesa;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Scripts de captura (5 corridas) y edición (5 corridas) de la pantalla de mesas (CC-177).
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Administración – US-ADM-03 / CC-107 / CC-177
 */
class MesaCrudTest extends CrudTestCase
{
    /**
     * Corridas de captura: número y capacidad de la mesa nueva.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-177
     *
     * @return array<string, array{int, ?int}>
     */
    public static function capturas(): array
    {
        return [
            'cap-mes-01 mesa de dos' => [1, 2],
            'cap-mes-02 mesa de cuatro' => [2, 4],
            'cap-mes-03 sin capacidad' => [3, null],
            'cap-mes-04 mesa grande' => [10, 12],
            'cap-mes-05 capacidad máxima' => [20, 255],
        ];
    }

    /**
     * Corridas de edición: nuevo número y capacidad para una mesa existente.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-177
     *
     * @return array<string, array{int, ?int}>
     */
    public static function ediciones(): array
    {
        return [
            'edi-mes-01 cambiar número' => [15, 4],
            'edi-mes-02 subir capacidad' => [5, 8],
            'edi-mes-03 bajar capacidad' => [5, 2],
            'edi-mes-04 quitar capacidad' => [5, null],
            'edi-mes-05 número y capacidad' => [30, 6],
        ];
    }

    /**
     * Captura una mesa y verifica que quede libre y activa.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-177
     *
     * @param  int  $numero  Número de la mesa.
     * @param  int|null  $capacidad  Capacidad de comensales.
     * @return void
     */
    #[DataProvider('capturas')]
    public function test_captura_registra_mesa(int $numero, ?int $capacidad): void
    {
        $respuesta = $this->postJson('/api/mesas', ['numero' => $numero, 'capacidad' => $capacidad]);

        $this->assertContratoOk($respuesta, 201);
        $this->assertDatabaseHas('mesas', [
            'numero' => $numero,
            'capacidad' => $capacidad,
            'estado' => 'libre',
            'activa' => true,
        ]);
    }

    /**
     * Edita una mesa existente y verifica el número y la capacidad nuevos.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-177
     *
     * @param  int  $numero  Nuevo número.
     * @param  int|null  $capacidad  Nueva capacidad.
     * @return void
     */
    #[DataProvider('ediciones')]
    public function test_edicion_actualiza_mesa(int $numero, ?int $capacidad): void
    {
        $mesa = Mesa::create(['numero' => 5, 'capacidad' => 4, 'estado' => 'libre', 'activa' => true]);

        $respuesta = $this->putJson("/api/mesas/{$mesa->id}", ['numero' => $numero, 'capacidad' => $capacidad]);

        $this->assertContratoOk($respuesta, 200);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'numero' => $numero, 'capacidad' => $capacidad]);
    }
}
