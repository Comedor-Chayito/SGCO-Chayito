<?php

namespace Tests\Feature\Crud;

use App\Models\SubcategoriaEgreso;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Scripts de captura (5 corridas) de la pantalla de egresos (CC-177).
 *
 * Los egresos son históricos contables inmutables: la API no expone edición (no hay ruta PUT),
 * por eso esta pantalla solo tiene corridas de captura.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Contabilidad – RF-CON-005 / RF-CON-006 / CC-177
 */
class EgresoCrudTest extends CrudTestCase
{
    /**
     * Corridas de captura: monto total de la factura y desglose de IVA esperado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Contabilidad – RF-CON-005 / CC-177
     *
     * @return array<string, array{float, float, float}>
     */
    public static function capturas(): array
    {
        return [
            'cap-egr-01 factura de 113.00' => [113.00, 100.00, 13.00],
            'cap-egr-02 factura de 56.50' => [56.50, 50.00, 6.50],
            'cap-egr-03 factura de 10.00' => [10.00, 8.85, 1.15],
            'cap-egr-04 factura de 1.13' => [1.13, 1.00, 0.13],
            'cap-egr-05 factura de 250.00' => [250.00, 221.24, 28.76],
        ];
    }

    /**
     * Captura una factura de proveedor y verifica el correlativo y el desglose de IVA guardado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Contabilidad – RF-CON-005 / RF-CON-006 / CC-177
     *
     * @param  float  $montoTotal  Monto total de la factura.
     * @param  float  $costoNeto  Costo neto esperado.
     * @param  float  $iva  IVA crédito fiscal esperado.
     * @return void
     */
    #[DataProvider('capturas')]
    public function test_captura_registra_egreso(float $montoTotal, float $costoNeto, float $iva): void
    {
        $subcategoria = SubcategoriaEgreso::create(['nombre' => 'Materia prima', 'activo' => true]);

        $respuesta = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $subcategoria->id,
            'proveedor' => 'Proveedor ficticio de prueba',
            'numero_comprobante' => 'CCF-0001',
            'fecha_comprobante' => today()->toDateString(),
            'monto_total' => $montoTotal,
            'descripcion' => 'Compra de prueba',
        ]);

        $this->assertContratoOk($respuesta, 201)->assertJsonPath('data.correlativo', 'EG-000001');
        $this->assertDatabaseHas('egresos', [
            'monto_total' => $montoTotal,
            'costo_neto' => $costoNeto,
            'iva_credito_fiscal' => $iva,
        ]);
    }
}
