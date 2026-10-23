<?php

namespace App\Services;

/**
 * Calcula el costo neto e IVA crédito fiscal contenidos en una compra gravada.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */
class IvaCreditoFiscalService
{
    public const TASA_IVA = 0.13;

    /**
     * Desglosa un monto total con IVA incluido conforme a la tasa vigente de El Salvador.
     *
     * El IVA se obtiene desde el costo neto exacto y después se redondea; así el total
     * conserva coherencia aun en importes donde redondear el neto primero agregaría un centavo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @param  float  $montoTotal  Monto total del comprobante, con IVA incluido.
     * @return array{costo_neto: float, iva_credito_fiscal: float} Desglose monetario a dos decimales.
     */
    public function calcular(float $montoTotal): array
    {
        $costoNetoExacto = $montoTotal / (1 + self::TASA_IVA);

        return [
            'costo_neto' => round($costoNetoExacto, 2),
            'iva_credito_fiscal' => round($costoNetoExacto * self::TASA_IVA, 2),
        ];
    }
}
