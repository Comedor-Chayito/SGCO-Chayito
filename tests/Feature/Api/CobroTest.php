<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del procesamiento de cobros (US-POS-03 / RF-POS-002 / CC-31).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002
 */
class CobroTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;
    protected Mesa $mesa;
    protected Platillo $platillo;
    protected Comanda $comanda;

    /**
     * Configuración inicial antes de cada prueba de cobro.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cajero = User::factory()->create(['id' => 1]);

        $this->mesa = Mesa::create([
            'numero'    => 3,
            'capacidad' => 4,
            'estado'    => 'ocupada',
        ]);

        $this->platillo = Platillo::create([
            'nombre'          => 'Pollo Asado',
            'descripcion'     => 'Pollo con guarnición',
            'precio_unitario' => 5.00,
            'disponible'      => true,
        ]);

        $this->comanda = Comanda::create([
            'mesa_id'       => $this->mesa->id,
            'usuario_id'    => $this->cajero->id,
            'canal'         => 'mesa',
            'estado'        => 'pendiente',
            'observaciones' => null,
            'subtotal'      => 10.00,
        ]);

        $this->comanda->detalles()->create([
            'platillo_id'     => $this->platillo->id,
            'cantidad'        => 2,
            'precio_unitario' => 5.00,
        ]);
    }

    /**
     * Valida que un cobro en efectivo se procesa, calcula el cambio y actualiza comanda a pagada.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_cobro_procesa_correctamente_con_efectivo_y_calcula_cambio(): void
    {
        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 20.00,
            'metodo_pago'    => 'efectivo',
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Cobro procesado correctamente.',
                'data'    => [
                    'comanda_id'     => $this->comanda->id,
                    'total'          => '10.00',
                    'monto_recibido' => '20.00',
                    'cambio'         => '10.00',
                    'metodo_pago'    => 'efectivo',
                ],
            ]);

        $this->assertDatabaseHas('ventas', [
            'comanda_id'     => $this->comanda->id,
            'total'          => 10.00,
            'monto_recibido' => 20.00,
            'cambio'         => 10.00,
            'metodo_pago'    => 'efectivo',
        ]);

        $this->assertDatabaseHas('comandas', [
            'id'     => $this->comanda->id,
            'estado' => 'pagada',
        ]);
    }

    /**
     * Valida que pagar con el monto exacto genera cambio de cero.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_cobro_con_monto_exacto_genera_cambio_cero(): void
    {
        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 10.00,
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.cambio', '0.00');

        $this->assertDatabaseHas('ventas', [
            'comanda_id' => $this->comanda->id,
            'cambio'     => 0.00,
        ]);
    }

    /**
     * Valida que se rechaza si el monto entregado no cubre el total de la comanda.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_cobro_rechaza_si_monto_recibido_es_insuficiente(): void
    {
        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 8.50,
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['monto_recibido'])
            ->assertJsonFragment([
                'monto_recibido' => [
                    'El monto recibido es insuficiente para cubrir el total de la comanda.',
                ],
            ]);

        $this->assertDatabaseMissing('ventas', [
            'comanda_id' => $this->comanda->id,
        ]);

        $this->assertDatabaseHas('comandas', [
            'id'     => $this->comanda->id,
            'estado' => 'pendiente',
        ]);
    }

    /**
     * Valida que se rechaza si la comanda indicada no existe en el sistema.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_cobro_rechaza_si_comanda_no_existe(): void
    {
        $payload = [
            'comanda_id'     => 99999,
            'monto_recibido' => 20.00,
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comanda_id']);
    }

    /**
     * Valida que no se puede volver a cobrar una comanda previamente pagada.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_cobro_rechaza_si_comanda_ya_esta_pagada(): void
    {
        $this->comanda->update(['estado' => 'pagada']);

        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 20.00,
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comanda_id'])
            ->assertJsonFragment([
                'comanda_id' => [
                    'Esta comanda ya ha sido pagada.',
                ],
            ]);
    }

    /**
     * Valida que no se puede cobrar una comanda que fue cancelada.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_cobro_rechaza_si_comanda_esta_cancelada(): void
    {
        $this->comanda->update(['estado' => 'cancelada']);

        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 20.00,
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comanda_id'])
            ->assertJsonFragment([
                'comanda_id' => [
                    'No se puede cobrar una comanda cancelada.',
                ],
            ]);
    }

    /**
     * Valida que el cobro soporta método de transferencia electrónica.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_cobro_soporta_metodo_transferencia(): void
    {
        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 10.00,
            'metodo_pago'    => 'transferencia',
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.metodo_pago', 'transferencia');

        $this->assertDatabaseHas('ventas', [
            'comanda_id'  => $this->comanda->id,
            'metodo_pago' => 'transferencia',
        ]);
    }

    /**
     * Valida que el cobro puede procesarse también a través del endpoint alternativo /api/ventas.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_cobro_funciona_con_endpoint_ventas(): void
    {
        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 15.00,
        ];

        $response = $this->postJson('/api/ventas', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.cambio', '5.00');
    }

    /**
     * Valida la consulta del comprobante digital o ticket generado tras el cobro.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @return void
     */
    public function test_obtener_comprobante_digital_por_id(): void
    {
        $venta = Venta::create([
            'comanda_id'     => $this->comanda->id,
            'usuario_id'     => $this->cajero->id,
            'total'          => 10.00,
            'monto_recibido' => 20.00,
            'cambio'         => 10.00,
            'metodo_pago'    => 'efectivo',
        ]);

        $response = $this->getJson('/api/cobros/' . $venta->id);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Comprobante obtenido.',
                'data'    => [
                    'id'         => $venta->id,
                    'comanda_id' => $this->comanda->id,
                    'total'      => '10.00',
                    'cambio'     => '10.00',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'total',
                    'monto_recibido',
                    'cambio',
                    'comanda' => [
                        'id',
                        'canal',
                        'mesa',
                        'detalles',
                    ],
                ],
            ]);
    }
}

