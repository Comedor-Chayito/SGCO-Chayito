<?php

namespace Tests\Unit\Inventario;

use App\Services\ConversionService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias de conversión de unidades de medida (Cuadrante 1 – CC-141).
 *
 * Caja negra: 4 conversiones entre unidades de la misma familia (masa, volumen, conteo).
 * Caja blanca: 3 ejecuciones sobre las ramas de misma unidad, familias distintas y unidad desconocida.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Inventario – RF-INV-003 / US-INV-01 / CC-141
 */
class ConversionServiceTest extends TestCase
{
    /**
     * Casos de caja negra: cantidad, unidad de origen, unidad de destino y resultado esperado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @return array<string, array{float, string, string, float}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-conv-01 masa kg a g' => [1.5, 'kg', 'g', 1500.0],
            'cn-conv-02 masa libra a onza' => [1.0, 'libra', 'onza', 16.0],
            'cn-conv-03 volumen ml a lt' => [250.0, 'ml', 'lt', 0.25],
            'cn-conv-04 conteo docena a unidad' => [2.0, 'docena', 'unidad', 24.0],
        ];
    }

    /**
     * Verifica que la conversión dentro de una familia devuelva la magnitud equivalente.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @param  float  $cantidad  Cantidad en la unidad de origen.
     * @param  string  $desde  Unidad de origen.
     * @param  string  $hasta  Unidad de destino.
     * @param  float  $esperado  Cantidad esperada en la unidad de destino.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_convierte_dentro_de_la_familia(
        float $cantidad,
        string $desde,
        string $hasta,
        float $esperado
    ): void {
        $this->assertSame($esperado, ConversionService::convertir($cantidad, $desde, $hasta));
    }

    /**
     * CB-CONV-01: rama de misma unidad tras normalizar alias ('GR ' ≡ 'g'); solo redondea a 6 decimales.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @return void
     */
    public function test_caja_blanca_alias_de_la_misma_unidad_no_convierte(): void
    {
        $this->assertSame(0.123457, ConversionService::convertir(0.1234567, 'GR ', 'g'));
    }

    /**
     * CB-CONV-02: rama de familias distintas (masa → volumen) lanza InvalidArgumentException.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @return void
     */
    public function test_caja_blanca_familias_distintas_lanzan_excepcion(): void
    {
        $this->assertFalse(ConversionService::sonCompatibles('kg', 'lt'));

        $this->expectException(InvalidArgumentException::class);

        ConversionService::convertir(1.0, 'kg', 'lt');
    }

    /**
     * CB-CONV-03: rama de unidad desconocida (familia null): no es compatible y se ofrece a sí misma.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @return void
     */
    public function test_caja_blanca_unidad_desconocida_no_es_compatible(): void
    {
        $this->assertFalse(ConversionService::sonCompatibles('taza', 'ml'));
        $this->assertSame(['taza'], ConversionService::unidadesCompatibles('taza'));

        $this->expectException(InvalidArgumentException::class);

        ConversionService::convertir(1.0, 'taza', 'ml');
    }
}
