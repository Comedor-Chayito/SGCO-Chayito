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

    /**
     * Valida el cálculo exacto de cambio con centavos y fracciones (CC-33).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-12
     * @módulo POS – US-POS-03 / CC-33
     *
     * @return void
     */
    public function test_calculo_cambio_con_centavos_y_fracciones(): void
    {
        // Actualizamos subtotal con centavos (ej. $7.35)
        $this->comanda->update(['subtotal' => 7.35]);

        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 10.00,
            'metodo_pago'    => 'efectivo',
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', '7.35')
            ->assertJsonPath('data.monto_recibido', '10.00')
            ->assertJsonPath('data.cambio', '2.65');

        $this->assertDatabaseHas('ventas', [
            'comanda_id'     => $this->comanda->id,
            'total'          => 7.35,
            'monto_recibido' => 10.00,
            'cambio'         => 2.65,
        ]);
    }

    /**
     * Valida el cálculo de cambio al pagar con billete de $20.00 (CC-33).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-12
     * @módulo POS – US-POS-03 / CC-33
     *
     * @return void
     */
    public function test_calculo_cambio_con_billete_de_veinte_dolares(): void
    {
        $this->comanda->update(['subtotal' => 12.50]);

        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 20.00,
            'metodo_pago'    => 'efectivo',
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', '12.50')
            ->assertJsonPath('data.monto_recibido', '20.00')
            ->assertJsonPath('data.cambio', '7.50');
    }

    /**
     * Valida que el pago exacto con centavos genera cambio $0.00 (CC-33).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-12
     * @módulo POS – US-POS-03 / CC-33
     *
     * @return void
     */
    public function test_calculo_cambio_con_pago_exacto_en_centavos(): void
    {
        $this->comanda->update(['subtotal' => 8.75]);

        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 8.75,
            'metodo_pago'    => 'efectivo',
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', '8.75')
            ->assertJsonPath('data.monto_recibido', '8.75')
            ->assertJsonPath('data.cambio', '0.00');
    }

    /**
     * Valida que si el monto recibido es tan solo 1 centavo menor al total, se rechaza (CC-33).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-12
     * @módulo POS – US-POS-03 / CC-33
     *
     * @return void
     */
    public function test_calculo_cambio_rechaza_centavo_menos_que_el_total(): void
    {
        $this->comanda->update(['subtotal' => 10.00]);

        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 9.99,
            'metodo_pago'    => 'efectivo',
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['monto_recibido']);
    }

    /**
     * Valida que al procesar el cobro, la comanda pasa a pagada y se libera la mesa si no hay más comandas activas (CC-34).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-13
     * @módulo POS – US-POS-03 / CC-34
     *
     * @return void
     */
    public function test_cambiar_estado_a_pagada_actualiza_comanda_y_libera_mesa(): void
    {
        $this->assertSame('ocupada', $this->mesa->fresh()->estado);
        $this->assertSame('pendiente', $this->comanda->fresh()->estado);

        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 15.00,
            'metodo_pago'    => 'efectivo',
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(201);
        $this->assertSame('pagada', $this->comanda->fresh()->estado);
        $this->assertSame('libre', $this->mesa->fresh()->estado);
    }

    /**
     * Valida que la mesa no se libera al cobrar si aún tiene otra comanda activa en la misma mesa (CC-34).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-13
     * @módulo POS – US-POS-03 / CC-34
     *
     * @return void
     */
    public function test_mesa_no_se_libera_si_tiene_otra_comanda_activa(): void
    {
        // Creamos una segunda comanda activa en la misma mesa
        $comanda2 = Comanda::create([
            'mesa_id'       => $this->mesa->id,
            'usuario_id'    => $this->cajero->id,
            'canal'         => 'mesa',
            'estado'        => 'en_cocina',
            'observaciones' => null,
            'subtotal'      => 6.00,
        ]);

        $payload = [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 10.00,
            'metodo_pago'    => 'efectivo',
        ];

        // Se cobra la primera comanda
        $response = $this->postJson('/api/cobros', $payload);
        $response->assertStatus(201);

        $this->assertSame('pagada', $this->comanda->fresh()->estado);
        // La mesa debe seguir ocupada porque comanda2 sigue activa
        $this->assertSame('ocupada', $this->mesa->fresh()->estado);

        // Se cobra la segunda comanda
        $response2 = $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda2->id,
            'monto_recibido' => 10.00,
            'metodo_pago'    => 'efectivo',
        ]);
        $response2->assertStatus(201);

        $this->assertSame('pagada', $comanda2->fresh()->estado);
        // Ahora sí la mesa debe quedar libre
        $this->assertSame('libre', $this->mesa->fresh()->estado);
    }

    /**
     * Valida que una comanda pagada se excluye de la cola activa de cocina (CC-34).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-13
     * @módulo POS – US-POS-03 / CC-34
     *
     * @return void
     */
    public function test_comanda_pagada_se_excluye_de_cola_de_cocina_activa(): void
    {
        // Inicialmente está pendiente y debe figurar en la cola activa
        $resActivasAntes = $this->getJson('/api/comandas/cocina');
        $resActivasAntes->assertStatus(200);
        $idsAntes = collect($resActivasAntes->json('data.activas'))->pluck('id')->all();
        $this->assertContains($this->comanda->id, $idsAntes);

        // Procesamos el cobro
        $this->postJson('/api/cobros', [
            'comanda_id'     => $this->comanda->id,
            'monto_recibido' => 10.00,
        ])->assertStatus(201);

        // Ya no debe figurar en la cola activa
        $resActivasDespues = $this->getJson('/api/comandas/cocina');
        $resActivasDespues->assertStatus(200);
        $idsDespues = collect($resActivasDespues->json('data.activas'))->pluck('id')->all();
        $this->assertNotContains($this->comanda->id, $idsDespues);

        // Pero sí debe figurar en completadas recientes
        $resCompletadas = $this->getJson('/api/comandas/cocina?estado=pagada');
        $resCompletadas->assertStatus(200);
        $idsCompletadas = collect($resCompletadas->json('data'))->pluck('id')->all();
        $this->assertContains($this->comanda->id, $idsCompletadas);
    }

    /**
     * Valida que se rechaza la actualización de estado a 'pagada' si la comanda ya está pagada (CC-34).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-13
     * @módulo POS – US-POS-03 / CC-34
     *
     * @return void
     */
    public function test_rechaza_actualizar_estado_a_pagada_si_ya_esta_pagada(): void
    {
        $this->comanda->update(['estado' => 'pagada']);

        $response = $this->patchJson('/api/comandas/' . $this->comanda->id . '/estado', [
            'estado' => 'pagada',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['estado'])
            ->assertJsonFragment([
                'estado' => [
                    'Esta comanda ya ha sido pagada.',
                ],
            ]);
    }

    /**
     * Valida que se rechaza la actualización de estado si la comanda se encuentra cancelada (CC-34).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-13
     * @módulo POS – US-POS-03 / CC-34
     *
     * @return void
     */
    public function test_rechaza_actualizar_estado_si_comanda_esta_cancelada(): void
    {
        $this->comanda->update(['estado' => 'cancelada']);

        $response = $this->patchJson('/api/comandas/' . $this->comanda->id . '/estado', [
            'estado' => 'pagada',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['estado'])
            ->assertJsonFragment([
                'estado' => [
                    'No se puede modificar el estado de una comanda cancelada.',
                ],
            ]);
    }

    /**
     * Valida que actualizar estado a pagada directamente vía endpoint PATCH libera la mesa (CC-34).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-13
     * @módulo POS – US-POS-03 / CC-34
     *
     * @return void
     */
    public function test_actualizar_estado_a_pagada_via_endpoint_libera_mesa(): void
    {
        $this->assertSame('ocupada', $this->mesa->fresh()->estado);

        $response = $this->patchJson('/api/comandas/' . $this->comanda->id . '/estado', [
            'estado' => 'pagada',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Estado de la comanda actualizado correctamente.',
                'data'    => [
                    'id'     => $this->comanda->id,
                    'estado' => 'pagada',
                ],
            ]);

        $this->assertSame('pagada', $this->comanda->fresh()->estado);
        $this->assertSame('libre', $this->mesa->fresh()->estado);
    }
}


