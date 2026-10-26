<?php

namespace Tests\Feature\Api;

use App\Models\Compra;
use App\Models\Egreso;
use App\Models\SubcategoriaEgreso;
use App\Models\User;
use App\Services\IvaCreditoFiscalService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubcategoriaEgresoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas con facturas de ejemplo para certificar el desglose automatizado de IVA crédito fiscal.
 *
 * @autor  Cesar
 * @fecha  2026-10-25
 * @módulo Contabilidad – US-CON-02 / RF-CON-006 / CC-66 / CC-67 / CC-68 / CC-69 / CC-70
 */
class FacturaEjemploIvaTest extends TestCase
{
    use RefreshDatabase;

    private User $contadora;

    private User $administrador;

    private SubcategoriaEgreso $subcategoriaCarnes;

    private SubcategoriaEgreso $subcategoriaVerduras;

    private SubcategoriaEgreso $subcategoriaLacteos;

    private IvaCreditoFiscalService $ivaService;

    /**
     * Prepara el entorno con roles oficiales, subcategorías y servicio de IVA inyectado.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-02 / CC-70
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, SubcategoriaEgresoSeeder::class]);

        $this->contadora = User::factory()->create(['name' => 'Elena Contadora']);
        $this->contadora->assignRole('contadora');

        $this->administrador = User::factory()->create(['name' => 'Admin General']);
        $this->administrador->assignRole('administrador');

        $this->subcategoriaCarnes = SubcategoriaEgreso::where('nombre', 'Carnes')->firstOrFail();
        $this->subcategoriaVerduras = SubcategoriaEgreso::where('nombre', 'Verduras y frutas')->firstOrFail();
        $this->subcategoriaLacteos = SubcategoriaEgreso::where('nombre', 'Lácteos')->firstOrFail();

        $this->ivaService = app(IvaCreditoFiscalService::class);

        Sanctum::actingAs($this->contadora);
    }

    /**
     * Verifica facturas de ejemplo con montos exactos ($113.00, $56.50, $226.00, $1,130.00).
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-02 / CC-66 / CC-67 / CC-70
     */
    public function test_facturas_ejemplo_con_montos_exactos(): void
    {
        $facturas = [
            ['total' => 113.00,  'neto' => 100.00,  'iva' => 13.00],
            ['total' => 56.50,   'neto' => 50.00,   'iva' => 6.50],
            ['total' => 226.00,  'neto' => 200.00,  'iva' => 26.00],
            ['total' => 1130.00, 'neto' => 1000.00, 'iva' => 130.00],
        ];

        foreach ($facturas as $index => $factura) {
            $folio = 'FAC-EX-' . ($index + 1);

            // Registro en compras
            $respCompra = $this->postJson('/api/compras', [
                'fecha_compra' => '2026-10-24',
                'monto_total'  => $factura['total'],
            ]);

            $respCompra
                ->assertCreated()
                ->assertJsonPath('data.monto_total', number_format($factura['total'], 2, '.', ''))
                ->assertJsonPath('data.costo_neto', number_format($factura['neto'], 2, '.', ''))
                ->assertJsonPath('data.iva_credito_fiscal', number_format($factura['iva'], 2, '.', ''));

            // Registro en egresos (facturas de proveedores)
            $respEgreso = $this->postJson('/api/egresos', [
                'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
                'proveedor'              => 'Distribuidora Avícola S.A.',
                'numero_comprobante'     => $folio,
                'fecha_comprobante'      => '2026-10-24',
                'monto_total'            => $factura['total'],
            ]);

            $respEgreso
                ->assertCreated()
                ->assertJsonPath('data.monto_total', number_format($factura['total'], 2, '.', ''))
                ->assertJsonPath('data.costo_neto', number_format($factura['neto'], 2, '.', ''))
                ->assertJsonPath('data.iva_credito_fiscal', number_format($factura['iva'], 2, '.', ''));

            // Invariante: costo neto + IVA === monto total
            $this->assertEquals($factura['total'], round($factura['neto'] + $factura['iva'], 2));
        }
    }

    /**
     * Verifica facturas de ejemplo reales con montos fraccionarios habituales en compras del comedor.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-02 / CC-68 / CC-70
     */
    public function test_facturas_ejemplo_reales_con_montos_fraccionarios(): void
    {
        $facturasReales = [
            // Factura carnicería: $45.00 -> Neto: 45 / 1.13 = 39.82; IVA: 39.82 * 0.13 = 5.18
            [
                'proveedor'   => 'Carnes San Martín',
                'comprobante' => 'CCF-4501',
                'subcat'      => $this->subcategoriaCarnes->id,
                'total'       => 45.00,
                'neto'        => 39.82,
                'iva'         => 5.18,
            ],
            // Factura lácteos: $84.75 -> Neto: 84.75 / 1.13 = 75.00; IVA: 75 * 0.13 = 9.75
            [
                'proveedor'   => 'Quesería La Bendición',
                'comprobante' => 'FAC-8475',
                'subcat'      => $this->subcategoriaLacteos->id,
                'total'       => 84.75,
                'neto'        => 75.00,
                'iva'         => 9.75,
            ],
            // Factura verduras: $15.25 -> Neto: 15.25 / 1.13 = 13.50; IVA: 13.50 * 0.13 = 1.75
            [
                'proveedor'   => 'Mercado Central Puesto #4',
                'comprobante' => 'FAC-1525',
                'subcat'      => $this->subcategoriaVerduras->id,
                'total'       => 15.25,
                'neto'        => 13.50,
                'iva'         => 1.75,
            ],
            // Factura abarrotes con 99 centavos: $199.99 -> Neto: 199.99 / 1.13 = 176.98; IVA: 23.01
            [
                'proveedor'   => 'Super Selectos Mayorista',
                'comprobante' => 'CCF-19999',
                'subcat'      => $this->subcategoriaCarnes->id,
                'total'       => 199.99,
                'neto'        => 176.98,
                'iva'         => 23.01,
            ],
            // Factura mínima gravada: $1.13 -> Neto: 1.00; IVA: 0.13
            [
                'proveedor'   => 'Librería y Suministros El Centro',
                'comprobante' => 'FAC-00113',
                'subcat'      => $this->subcategoriaLacteos->id,
                'total'       => 1.13,
                'neto'        => 1.00,
                'iva'         => 0.13,
            ],
        ];

        foreach ($facturasReales as $factura) {
            $respuesta = $this->postJson('/api/egresos', [
                'subcategoria_egreso_id' => $factura['subcat'],
                'proveedor'              => $factura['proveedor'],
                'numero_comprobante'     => $factura['comprobante'],
                'fecha_comprobante'      => '2026-10-24',
                'monto_total'            => $factura['total'],
            ]);

            $respuesta
                ->assertCreated()
                ->assertJsonPath('data.monto_total', number_format($factura['total'], 2, '.', ''))
                ->assertJsonPath('data.costo_neto', number_format($factura['neto'], 2, '.', ''))
                ->assertJsonPath('data.iva_credito_fiscal', number_format($factura['iva'], 2, '.', ''));

            $this->assertDatabaseHas('egresos', [
                'numero_comprobante' => $factura['comprobante'],
                'monto_total'        => $factura['total'],
                'costo_neto'         => $factura['neto'],
                'iva_credito_fiscal' => $factura['iva'],
            ]);

            // Validación de integridad: neto + IVA == total
            $this->assertEquals($factura['total'], round($factura['neto'] + $factura['iva'], 2));
        }
    }

    /**
     * Verifica que el desglose de IVA ignora cualquier valor alterado o inyectado desde el cliente.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-02 / CC-67 / CC-70
     */
    public function test_ignora_valores_de_iva_y_neto_manipulados_por_el_cliente(): void
    {
        // En compras
        $respCompra = $this->postJson('/api/compras', [
            'fecha_compra'       => '2026-10-24',
            'monto_total'        => 113.00,
            'costo_neto'         => 50.00,   // Intento de manipulación
            'iva_credito_fiscal' => 63.00,   // Intento de manipulación
        ]);

        $respCompra
            ->assertCreated()
            ->assertJsonPath('data.costo_neto', '100.00')
            ->assertJsonPath('data.iva_credito_fiscal', '13.00');

        // En egresos
        $respEgreso = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor'              => 'Distribuidora Cárnica',
            'numero_comprobante'     => 'CCF-MANIP-01',
            'fecha_comprobante'      => '2026-10-24',
            'monto_total'            => 113.00,
            'costo_neto'             => 1.00,
            'iva_credito_fiscal'     => 112.00,
        ]);

        $respEgreso
            ->assertCreated()
            ->assertJsonPath('data.costo_neto', '100.00')
            ->assertJsonPath('data.iva_credito_fiscal', '13.00');
    }

    /**
     * Verifica que una batería extensa de facturas de ejemplo mantiene la suma sin descuadre en el servicio.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-02 / CC-66 / CC-67 / CC-70
     */
    public function test_bateria_de_facturas_mantiene_invariante_en_servicio(): void
    {
        $montosEjemplo = [
            0.01, 0.50, 1.00, 1.13, 2.26, 3.50, 4.99, 9.99,
            10.00, 15.25, 23.40, 25.00, 45.00, 50.00, 56.50,
            75.80, 84.75, 99.99, 100.00, 113.00, 120.50, 149.95,
            199.99, 226.00, 339.00, 450.00, 565.00, 1000.00,
            1130.00, 2260.00, 5000.00, 9999.99, 15480.95, 99999.99,
        ];

        foreach ($montosEjemplo as $monto) {
            $desglose = $this->ivaService->calcular($monto);

            $this->assertArrayHasKey('costo_neto', $desglose);
            $this->assertArrayHasKey('iva_credito_fiscal', $desglose);

            $suma = round($desglose['costo_neto'] + $desglose['iva_credito_fiscal'], 2);

            $this->assertEquals(
                round($monto, 2),
                $suma,
                "Descuadre en monto {$monto}: Neto={$desglose['costo_neto']}, IVA={$desglose['iva_credito_fiscal']}, Suma={$suma}"
            );
        }
    }

    /**
     * Verifica que la sumatoria mensual en el historial contable acumula exactamente las facturas de ejemplo.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-02 / CC-69 / CC-70
     */
    public function test_facturas_ejemplo_acumulan_totales_sin_descuadre_en_historial(): void
    {
        // Registrar lote de facturas de ejemplo
        $lote = [
            ['prov' => 'Prov 1', 'comp' => 'F-01', 'total' => 113.00], // Neto: 100, IVA: 13
            ['prov' => 'Prov 2', 'comp' => 'F-02', 'total' => 56.50],  // Neto: 50,  IVA: 6.50
            ['prov' => 'Prov 3', 'comp' => 'F-03', 'total' => 45.00],  // Neto: 39.82, IVA: 5.18
            ['prov' => 'Prov 4', 'comp' => 'F-04', 'total' => 84.75],  // Neto: 75,  IVA: 9.75
            ['prov' => 'Prov 5', 'comp' => 'F-05', 'total' => 25.00],  // Neto: 22.12, IVA: 2.88
        ];

        $totalEsperado = 324.25;
        $netoEsperado = 286.94;
        $ivaEsperado = 37.31;

        foreach ($lote as $item) {
            $this->postJson('/api/egresos', [
                'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
                'proveedor'              => $item['prov'],
                'numero_comprobante'     => $item['comp'],
                'fecha_comprobante'      => '2026-10-24',
                'monto_total'            => $item['total'],
            ])->assertCreated();
        }

        $respuesta = $this->getJson('/api/egresos');

        $respuesta
            ->assertOk()
            ->assertJsonPath('data.totales.cantidad', 5)
            ->assertJsonPath('data.totales.monto_total', fn ($v) => (float) $v === $totalEsperado)
            ->assertJsonPath('data.totales.costo_neto', fn ($v) => (float) $v === $netoEsperado)
            ->assertJsonPath('data.totales.iva_credito_fiscal', fn ($v) => (float) $v === $ivaEsperado);

        $this->assertEquals($totalEsperado, round($netoEsperado + $ivaEsperado, 2));
    }

    /**
     * Verifica que facturas con montos inválidos (0, negativos, decimales excedidos) son rechazadas con 422.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-02 / CC-70
     */
    public function test_rechaza_facturas_ejemplo_con_montos_invalidos(): void
    {
        // Monto 0
        $this->postJson('/api/compras', [
            'fecha_compra' => '2026-10-24',
            'monto_total'  => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('monto_total');

        // Monto negativo
        $this->postJson('/api/compras', [
            'fecha_compra' => '2026-10-24',
            'monto_total'  => -50.00,
        ])->assertUnprocessable()->assertJsonValidationErrors('monto_total');

        // Más de 2 decimales
        $this->postJson('/api/compras', [
            'fecha_compra' => '2026-10-24',
            'monto_total'  => 113.005,
        ])->assertUnprocessable()->assertJsonValidationErrors('monto_total');
    }
}

