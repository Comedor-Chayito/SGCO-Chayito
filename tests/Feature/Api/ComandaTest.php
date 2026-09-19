<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del registro de comandas (US-POS-01 / RF-POS-001 / CC-25).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-001
 */
class ComandaTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;
    protected Mesa $mesa;
    protected Platillo $platilloA;
    protected Platillo $platilloB;
    protected Platillo $platilloNoDisponible;

    /**
     * Configuración inicial antes de cada prueba.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create(['id' => 1]);

        $this->mesa = Mesa::create([
            'numero'    => 1,
            'capacidad' => 4,
            'estado'    => 'libre',
        ]);

        $this->platilloA = Platillo::create([
            'nombre'          => 'Pollo Asado',
            'descripcion'     => 'Pollo al carbón',
            'precio_unitario' => 2.50,
            'disponible'      => true,
        ]);

        $this->platilloB = Platillo::create([
            'nombre'          => 'Carne Guisada',
            'descripcion'     => 'Carne de res con papas',
            'precio_unitario' => 3.00,
            'disponible'      => true,
        ]);

        $this->platilloNoDisponible = Platillo::create([
            'nombre'          => 'Pescado Frito Agotado',
            'descripcion'     => 'Agotado en cocina',
            'precio_unitario' => 4.00,
            'disponible'      => false,
        ]);
    }

    /**
     * Valida que una comanda puede registrarse exitosamente para consumo en mesa.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_puede_ser_registrada_con_datos_validos(): void
    {
        $payload = [
            'canal'         => 'mesa',
            'mesa_id'       => $this->mesa->id,
            'observaciones' => 'Sin cebolla',
            'items'         => [
                [
                    'platillo_id'   => $this->platilloA->id,
                    'cantidad'      => 2,
                    'observaciones' => 'Bien cocido',
                ],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Comanda registrada correctamente.',
                'data'    => [
                    'canal'    => 'mesa',
                    'mesa_id'  => $this->mesa->id,
                    'estado'   => 'pendiente',
                    'subtotal' => '5.00',
                ],
            ]);

        $this->assertDatabaseHas('comandas', [
            'canal'    => 'mesa',
            'mesa_id'  => $this->mesa->id,
            'estado'   => 'pendiente',
            'subtotal' => 5.00,
        ]);

        $this->assertDatabaseHas('comanda_detalles', [
            'platillo_id'    => $this->platilloA->id,
            'cantidad'       => 2,
            'precio_unitario' => 2.50,
            'observaciones'  => 'Bien cocido',
        ]);
    }

    /**
     * Valida que una orden para llevar no requiere mesa obligatoria.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_puede_registrarse_para_llevar_sin_mesa(): void
    {
        $payload = [
            'canal'         => 'para_llevar',
            'mesa_id'       => null,
            'observaciones' => 'Empacar para llevar',
            'items'         => [
                [
                    'platillo_id' => $this->platilloA->id,
                    'cantidad'    => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'ok',
                'data'   => [
                    'canal'   => 'para_llevar',
                    'mesa_id' => null,
                    'estado'  => 'pendiente',
                ],
            ]);

        $this->assertDatabaseHas('comandas', [
            'canal'   => 'para_llevar',
            'mesa_id' => null,
        ]);
    }

    /**
     * Valida que una orden por WhatsApp no requiere mesa obligatoria.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_puede_registrarse_por_whatsapp_sin_mesa(): void
    {
        $payload = [
            'canal'         => 'whatsapp',
            'mesa_id'       => null,
            'observaciones' => 'Pedido recibido por chat',
            'items'         => [
                [
                    'platillo_id' => $this->platilloB->id,
                    'cantidad'    => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'ok',
                'data'   => [
                    'canal'   => 'whatsapp',
                    'mesa_id' => null,
                    'estado'  => 'pendiente',
                ],
            ]);
    }

    /**
     * Valida que se rechaza si el canal es mesa pero no se envía mesa_id.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_rechaza_si_canal_mesa_no_especifica_mesa(): void
    {
        $payload = [
            'canal'   => 'mesa',
            'mesa_id' => null,
            'items'   => [
                ['platillo_id' => $this->platilloA->id, 'cantidad' => 1],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mesa_id']);
    }

    /**
     * Valida que se rechaza si la mesa especificada no existe.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_rechaza_si_mesa_no_existe(): void
    {
        $payload = [
            'canal'   => 'mesa',
            'mesa_id' => 9999,
            'items'   => [
                ['platillo_id' => $this->platilloA->id, 'cantidad' => 1],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mesa_id']);
    }

    /**
     * Valida que se rechaza si el platillo no existe en la base de datos.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_rechaza_si_platillo_no_existe(): void
    {
        $payload = [
            'canal'   => 'para_llevar',
            'mesa_id' => null,
            'items'   => [
                ['platillo_id' => 9999, 'cantidad' => 1],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.platillo_id']);
    }

    /**
     * Valida que se rechaza si el platillo no está disponible en el menú diario.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_rechaza_si_platillo_no_esta_disponible_en_menu_del_dia(): void
    {
        $payload = [
            'canal'   => 'para_llevar',
            'mesa_id' => null,
            'items'   => [
                ['platillo_id' => $this->platilloNoDisponible->id, 'cantidad' => 1],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.platillo_id'])
            ->assertJsonFragment([
                'items.0.platillo_id' => [
                    'El platillo seleccionado no está disponible en el menú del día.',
                ],
            ]);
    }

    /**
     * Valida que se rechaza si la cantidad es cero o negativa.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_rechaza_si_cantidad_es_cero_o_negativa(): void
    {
        $payloadCero = [
            'canal'   => 'para_llevar',
            'mesa_id' => null,
            'items'   => [
                ['platillo_id' => $this->platilloA->id, 'cantidad' => 0],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payloadCero);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.cantidad']);

        $payloadNegativo = [
            'canal'   => 'para_llevar',
            'mesa_id' => null,
            'items'   => [
                ['platillo_id' => $this->platilloA->id, 'cantidad' => -2],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payloadNegativo);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.cantidad']);
    }

    /**
     * Valida que se rechaza si no se incluye ningún platillo.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_rechaza_si_no_contiene_items(): void
    {
        $payload = [
            'canal'   => 'para_llevar',
            'mesa_id' => null,
            'items'   => [],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    /**
     * Valida que el subtotal se calcule sumando cantidad × precio unitario en BD.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_comanda_calcula_subtotal_correctamente(): void
    {
        // Platillo A: 2.50 * 2 = 5.00
        // Platillo B: 3.00 * 3 = 9.00
        // Subtotal esperado = 14.00
        $payload = [
            'canal'   => 'mesa',
            'mesa_id' => $this->mesa->id,
            'items'   => [
                ['platillo_id' => $this->platilloA->id, 'cantidad' => 2],
                ['platillo_id' => $this->platilloB->id, 'cantidad' => 3],
            ],
        ];

        $response = $this->postJson('/api/comandas', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.subtotal', '14.00');

        $this->assertDatabaseHas('comandas', [
            'subtotal' => 14.00,
        ]);
    }

    /**
     * Valida que listar comandas devuelve status ok y la estructura esperada para caja y cocina.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_listar_comandas_devuelve_estado_ok(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->usuario->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 5.00,
        ]);

        $response = $this->getJson('/api/comandas');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Comandas obtenidas.',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $comanda->id);
    }

    /**
     * Valida que listar comandas permite filtrar por estado.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @return void
     */
    public function test_listar_comandas_puede_filtrar_por_estado(): void
    {
        Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->usuario->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 5.00,
        ]);

        Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->usuario->id,
            'canal'      => 'mesa',
            'estado'     => 'en_cocina',
            'subtotal'   => 8.00,
        ]);

        $responsePendientes = $this->getJson('/api/comandas?estado=pendiente');
        $responsePendientes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'pendiente');

        $responseCocina = $this->getJson('/api/comandas?estado=en_cocina');
        $responseCocina->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'en_cocina');
    }
}

