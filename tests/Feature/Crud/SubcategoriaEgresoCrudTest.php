<?php

namespace Tests\Feature\Crud;

use App\Models\SubcategoriaEgreso;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Scripts de captura (5 corridas) y edición (5 corridas) de la pantalla de subcategorías de egreso (CC-177).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-27
 * @módulo Contabilidad – RF-CON-005 / CC-103 / CC-177
 */
class SubcategoriaEgresoCrudTest extends CrudTestCase
{
    /**
     * Corridas de captura: nombre enviado y nombre esperado tras recortar espacios.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Contabilidad – RF-CON-005 / CC-177
     *
     * @return array<string, array{string, string}>
     */
    public static function capturas(): array
    {
        return [
            'cap-sub-01 nombre simple' => ['Gas propano', 'Gas propano'],
            'cap-sub-02 con espacios' => ['  Mantenimiento ', 'Mantenimiento'],
            'cap-sub-03 con tilde' => ['Energía eléctrica', 'Energía eléctrica'],
            'cap-sub-04 con números' => ['Alquiler local 2', 'Alquiler local 2'],
            'cap-sub-05 nombre largo' => [str_repeat('a', 80), str_repeat('a', 80)],
        ];
    }

    /**
     * Corridas de edición: nombre nuevo para una subcategoría existente.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Contabilidad – RF-CON-005 / CC-177
     *
     * @return array<string, array{string, string}>
     */
    public static function ediciones(): array
    {
        return [
            'edi-sub-01 renombrar' => ['Limpieza general', 'Limpieza general'],
            'edi-sub-02 recortar espacios' => ['  Limpieza ', 'Limpieza'],
            'edi-sub-03 mismo nombre' => ['Artículos de limpieza', 'Artículos de limpieza'],
            'edi-sub-04 con tilde' => ['Higiene y sanitización', 'Higiene y sanitización'],
            'edi-sub-05 nombre corto' => ['Aseo', 'Aseo'],
        ];
    }

    /**
     * Captura una subcategoría y verifica que quede activa con el nombre normalizado.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Contabilidad – RF-CON-005 / CC-177
     *
     * @param  string $nombre   Nombre enviado.
     * @param  string $esperado Nombre guardado.
     * @return void
     */
    #[DataProvider('capturas')]
    public function test_captura_registra_subcategoria(string $nombre, string $esperado): void
    {
        $this->assertContratoOk($this->postJson('/api/subcategorias-egreso', ['nombre' => $nombre]), 201);

        $this->assertDatabaseHas('subcategorias_egreso', ['nombre' => $esperado, 'activo' => true]);
    }

    /**
     * Renombra una subcategoría existente y verifica el nombre nuevo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Contabilidad – RF-CON-005 / CC-177
     *
     * @param  string $nombre   Nombre enviado.
     * @param  string $esperado Nombre guardado.
     * @return void
     */
    #[DataProvider('ediciones')]
    public function test_edicion_renombra_subcategoria(string $nombre, string $esperado): void
    {
        $subcategoria = SubcategoriaEgreso::create(['nombre' => 'Artículos de limpieza', 'activo' => true]);

        $respuesta = $this->putJson("/api/subcategorias-egreso/{$subcategoria->id}", ['nombre' => $nombre]);

        $this->assertContratoOk($respuesta, 200);
        $this->assertDatabaseHas('subcategorias_egreso', ['id' => $subcategoria->id, 'nombre' => $esperado]);
    }
}
