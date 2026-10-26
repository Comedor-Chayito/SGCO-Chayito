<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas del desglose automático de costo neto e IVA crédito fiscal de compras.
 *
 * @autor  Jeferson De La Cruz / Cesar
 * @fecha  2026-10-25
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67 / CC-70
 */
class CompraIvaTest extends TestCase
{
    use RefreshDatabase;

    private User $contadora;

    /**
     * Prepara una persona contadora autenticada para registrar comprobantes de compra.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     */
    protected function setUp(): void
    {
        parent::setUp();

        $rolContadora = Role::findOrCreate('contadora', 'web');
        $this->contadora = User::factory()->create();
        $this->contadora->assignRole($rolContadora);
        Sanctum::actingAs($this->contadora);
    }

    /**
     * Verifica el costo neto de una compra cuyo monto total equivale exactamente a $113.00.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66
     */
    public function test_calcula_y_persiste_costo_neto_a_partir_del_monto_total(): void
    {
        $respuesta = $this->postJson('/api/compras', [
            'fecha_compra' => '2026-10-23',
            'monto_total' => 113,
        ]);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.monto_total', '113.00')
            ->assertJsonPath('data.costo_neto', '100.00')
            ->assertJsonPath('data.iva_credito_fiscal', '13.00');

        $this->assertDatabaseHas('compras', [
            'usuario_id' => $this->contadora->id,
            'fecha_compra' => '2026-10-23',
            'monto_total' => 113,
            'costo_neto' => 100,
            'iva_credito_fiscal' => 13,
        ]);
    }

    /**
     * Verifica que el IVA se calcula desde el costo neto exacto y se guarda automáticamente.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-67
     */
    public function test_calcula_iva_credito_fiscal_sin_aceptar_valores_enviados_por_el_cliente(): void
    {
        $respuesta = $this->postJson('/api/compras', [
            'fecha_compra' => '2026-10-23',
            'monto_total' => 100,
            'costo_neto' => 1,
            'iva_credito_fiscal' => 99,
        ]);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('data.costo_neto', '88.50')
            ->assertJsonPath('data.iva_credito_fiscal', '11.50');

        $this->assertDatabaseHas('compras', [
            'monto_total' => 100,
            'costo_neto' => 88.5,
            'iva_credito_fiscal' => 11.5,
        ]);
    }

    /**
     * Verifica que el listado entrega los datos persistidos de compra con su responsable.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     */
    public function test_lista_compras_con_su_desglose_tributario_y_responsable(): void
    {
        $this->postJson('/api/compras', [
            'fecha_compra' => '2026-10-22',
            'monto_total' => 56.5,
        ])->assertCreated();

        $this->getJson('/api/compras')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.0.fecha_compra', '2026-10-22')
            ->assertJsonPath('data.0.costo_neto', '50.00')
            ->assertJsonPath('data.0.iva_credito_fiscal', '6.50')
            ->assertJsonPath('data.0.usuario.id', $this->contadora->id);
    }

    /**
     * Verifica que el monto y fecha de la compra cumplen las reglas contables de entrada.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     */
    public function test_rechaza_compra_con_monto_invalido_o_fecha_futura(): void
    {
        $this->postJson('/api/compras', [
            'fecha_compra' => now()->addDay()->toDateString(),
            'monto_total' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors(['fecha_compra', 'monto_total']);
    }

    /**
     * Verifica que solo contadora o administrador pueden consultar y registrar compras.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     */
    public function test_rol_sin_permiso_no_puede_gestionar_compras(): void
    {
        $rolCocinero = Role::findOrCreate('cocinero', 'web');
        $cocinero = User::factory()->create();
        $cocinero->assignRole($rolCocinero);
        Sanctum::actingAs($cocinero);

        $this->getJson('/api/compras')->assertForbidden();
        $this->postJson('/api/compras', [
            'fecha_compra' => '2026-10-23',
            'monto_total' => 10,
        ])->assertForbidden();
    }

    /**
     * Verifica el cálculo con facturas de ejemplo reales (fraccionarias y de montos cotidianos).
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – RF-CON-006 / CC-70
     */
    public function test_desglose_iva_con_facturas_de_ejemplo_reales_de_compras(): void
    {
        $facturasEjemplo = [
            ['total' => 45.00,  'neto' => '39.82',  'iva' => '5.18'],
            ['total' => 84.75,  'neto' => '75.00',  'iva' => '9.75'],
            ['total' => 15.25,  'neto' => '13.50',  'iva' => '1.75'],
            ['total' => 199.99, 'neto' => '176.98', 'iva' => '23.01'],
            ['total' => 1.13,   'neto' => '1.00',   'iva' => '0.13'],
        ];

        foreach ($facturasEjemplo as $factura) {
            $resp = $this->postJson('/api/compras', [
                'fecha_compra' => '2026-10-25',
                'monto_total'  => $factura['total'],
            ]);

            $resp
                ->assertCreated()
                ->assertJsonPath('data.monto_total', number_format($factura['total'], 2, '.', ''))
                ->assertJsonPath('data.costo_neto', $factura['neto'])
                ->assertJsonPath('data.iva_credito_fiscal', $factura['iva']);
        }
    }

    /**
     * Verifica que en todas las compras registradas la suma de neto e IVA coincide con el monto total.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – RF-CON-006 / CC-70
     */
    public function test_invariante_suma_costo_neto_e_iva_credito_fiscal_en_compras(): void
    {
        $montos = [25.00, 78.50, 120.30, 250.00, 339.00];

        foreach ($montos as $monto) {
            $resp = $this->postJson('/api/compras', [
                'fecha_compra' => '2026-10-25',
                'monto_total'  => $monto,
            ]);

            $resp->assertCreated();

            $neto = (float) $resp->json('data.costo_neto');
            $iva  = (float) $resp->json('data.iva_credito_fiscal');

            $this->assertEquals($monto, round($neto + $iva, 2));
        }
    }
}
