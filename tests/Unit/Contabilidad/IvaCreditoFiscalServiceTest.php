<?php

namespace Tests\Unit\Contabilidad;

use App\Services\IvaCreditoFiscalService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias del desglose de IVA crédito fiscal (Cuadrante 1 – CC-139).
 *
 * Caja negra: 4 ejecuciones con montos de entrada y su desglose esperado según la fórmula del negocio.
 * Caja blanca: 3 ejecuciones que recorren el orden de redondeo, la coherencia neto + IVA y la constante.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Contabilidad – RF-CON-006 / US-CON-02 / CC-139
 */
class IvaCreditoFiscalServiceTest extends TestCase
{
    private IvaCreditoFiscalService $servicio;

    /**
     * Crea el servicio sin dependencias: el cálculo es puro y no toca la base de datos.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Contabilidad – RF-CON-006 / CC-139
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = new IvaCreditoFiscalService;
    }

    /**
     * Casos de caja negra: monto total con IVA incluido y desglose esperado a dos decimales.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Contabilidad – RF-CON-006 / CC-139
     *
     * @return array<string, array{float, float, float}> [monto total, costo neto, IVA crédito].
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-iva-01 factura típica de 113.00' => [113.00, 100.00, 13.00],
            'cn-iva-02 monto pequeño de 1.13' => [1.13, 1.00, 0.13],
            'cn-iva-03 monto con redondeo de 10.00' => [10.00, 8.85, 1.15],
            'cn-iva-04 monto alto de 99999.99' => [99999.99, 88495.57, 11504.42],
        ];
    }

    /**
     * Verifica que el desglose cumpla Costo Neto = Total / 1.13 e IVA = Neto × 0.13.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Contabilidad – RF-CON-006 / CC-139
     *
     * @param  float  $montoTotal  Monto del comprobante con IVA incluido.
     * @param  float  $costoNetoEsperado  Costo neto esperado.
     * @param  float  $ivaEsperado  IVA crédito fiscal esperado.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_desglosa_monto_total(
        float $montoTotal,
        float $costoNetoEsperado,
        float $ivaEsperado
    ): void {
        $resultado = $this->servicio->calcular($montoTotal);

        $this->assertSame(['costo_neto', 'iva_credito_fiscal'], array_keys($resultado));
        $this->assertSame($costoNetoEsperado, $resultado['costo_neto']);
        $this->assertSame($ivaEsperado, $resultado['iva_credito_fiscal']);
    }

    /**
     * CB-IVA-01: el IVA se calcula desde el neto exacto, no desde el neto ya redondeado.
     * Con 0.13 el neto exacto es 0.115044…; redondear primero daría IVA 0.02 y la suma 0.14.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Contabilidad – RF-CON-006 / CC-139
     *
     * @return void
     */
    public function test_caja_blanca_iva_se_obtiene_del_neto_exacto(): void
    {
        $resultado = $this->servicio->calcular(0.13);

        $this->assertSame(0.12, $resultado['costo_neto']);
        $this->assertSame(0.01, $resultado['iva_credito_fiscal']);
    }

    /**
     * CB-IVA-02: para todo monto entre 0.01 y 1000.00, costo neto + IVA reconstruye el total.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Contabilidad – RF-CON-006 / CC-139
     *
     * @return void
     */
    public function test_caja_blanca_neto_mas_iva_suma_el_total_en_todo_el_rango(): void
    {
        for ($centavos = 1; $centavos <= 100000; $centavos++) {
            $montoTotal = $centavos / 100;
            $resultado = $this->servicio->calcular($montoTotal);
            $suma = round($resultado['costo_neto'] + $resultado['iva_credito_fiscal'], 2);

            $this->assertSame(round($montoTotal, 2), $suma, "El desglose de {$montoTotal} no cuadra.");
        }
    }

    /**
     * CB-IVA-03: la tasa vive en la constante TASA_IVA (13 % El Salvador) y no hay IVA sobre cero.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Contabilidad – RF-CON-006 / CC-139
     *
     * @return void
     */
    public function test_caja_blanca_usa_la_constante_de_tasa_y_monto_cero(): void
    {
        $this->assertSame(0.13, IvaCreditoFiscalService::TASA_IVA);

        $resultado = $this->servicio->calcular(0.00);

        $this->assertSame(0.0, $resultado['costo_neto']);
        $this->assertSame(0.0, $resultado['iva_credito_fiscal']);
    }
}
