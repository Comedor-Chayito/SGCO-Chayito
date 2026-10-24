<?php

namespace Tests\Feature\Api;

use App\Models\ExistenciaAreaOperativa;
use App\Models\Insumo;
use App\Models\Requisicion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas del ciclo de requisiciones internas: creación, aprobación, transferencia y validaciones.
 *
 * @autor  Jeferson De La Cruz / Alexis / Cesar
 *
 * @fecha  2026-10-23
 *
 * @módulo Inventario – RF-INV-004 / CC-51 / CC-52 / CC-53 / CC-54 / CC-55
 */
class RequisicionTest extends TestCase
{
    use RefreshDatabase;

    private User $cocinero;

    private User $administrador;

    private Insumo $arroz;

    private Insumo $aceite;

    /**
     * Prepara solicitante, administrador y dos insumos de bodega.
     *
     * @autor  Jeferson De La Cruz / Alexis
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52 / CC-53 / CC-54
     */
    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('cocinero');
        Role::findOrCreate('administrador');
        $this->cocinero = User::factory()->create();
        $this->cocinero->assignRole('cocinero');
        $this->administrador = User::factory()->create();
        $this->administrador->assignRole('administrador');
        Sanctum::actingAs($this->cocinero);

        $this->arroz = Insumo::create([
            'nombre' => 'Arroz',
            'unidad_medida' => 'kg',
            'stock_actual' => 20,
            'stock_minimo' => 5,
        ]);
        $this->aceite = Insumo::create([
            'nombre' => 'Aceite vegetal',
            'unidad_medida' => 'lt',
            'stock_actual' => 10,
            'stock_minimo' => 2,
        ]);
    }

    /**
     * Verifica que un cocinero registra una requisición pendiente con sus detalles.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     */
    public function test_cocinero_crea_requisicion_pendiente_por_area(): void
    {
        $respuesta = $this->postJson('/api/requisiciones', [
            'area_solicitante' => 'cocina',
            'observaciones' => 'Preparación de almuerzo.',
            'detalles' => [
                ['insumo_id' => $this->arroz->id, 'cantidad_solicitada' => 3.5],
                ['insumo_id' => $this->aceite->id, 'cantidad_solicitada' => 1],
            ],
        ]);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.area_solicitante', 'cocina')
            ->assertJsonCount(2, 'data.detalles');

        $this->assertDatabaseHas('requisiciones', [
            'usuario_solicita_id' => $this->cocinero->id,
            'area_solicitante' => 'cocina',
            'estado' => 'pendiente',
            'observaciones' => 'Preparación de almuerzo.',
        ]);
        $this->assertDatabaseHas('requisicion_detalles', [
            'insumo_id' => $this->arroz->id,
            'cantidad_solicitada' => 3.5,
        ]);
        $this->assertDatabaseHas('requisicion_detalles', [
            'insumo_id' => $this->aceite->id,
            'cantidad_solicitada' => 1,
        ]);
    }

    /**
     * Verifica que no se repite un insumo dentro de una misma requisición.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     */
    public function test_rechaza_insumos_repetidos_o_cantidades_invalidas(): void
    {
        $respuesta = $this->postJson('/api/requisiciones', [
            'area_solicitante' => 'servicio',
            'detalles' => [
                ['insumo_id' => $this->arroz->id, 'cantidad_solicitada' => 0],
                ['insumo_id' => $this->arroz->id, 'cantidad_solicitada' => 1],
            ],
        ]);

        $respuesta
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'detalles.0.insumo_id',
                'detalles.1.insumo_id',
                'detalles.0.cantidad_solicitada',
            ]);

        $this->assertDatabaseCount('requisiciones', 0);
    }

    /**
     * Verifica que la creación exige una sesión autenticada con rol autorizado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     */
    public function test_requisicion_exige_sesion_y_rol_de_cocina(): void
    {
        $payload = [
            'area_solicitante' => 'despacho',
            'detalles' => [
                ['insumo_id' => $this->arroz->id, 'cantidad_solicitada' => 1],
            ],
        ];

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/requisiciones', $payload)->assertUnauthorized();

        Role::findOrCreate('mesero', 'web');
        $mesero = User::factory()->create();
        $mesero->assignRole('mesero');
        Sanctum::actingAs($mesero);

        $this->postJson('/api/requisiciones', $payload)->assertForbidden();
    }

    /**
     * Verifica que bodega puede aprobar cantidades parciales y registra al responsable.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     */
    public function test_administrador_aprueba_cantidades_parciales_de_requisicion_pendiente(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);

        $respuesta = $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'aprobada',
            'detalles' => [
                ['id' => $requisicion->detalles[0]->id, 'cantidad_aprobada' => 3.5],
                ['id' => $requisicion->detalles[1]->id, 'cantidad_aprobada' => 0.5],
            ],
        ]);

        $respuesta
            ->assertOk()
            ->assertJsonPath('data.estado', 'aprobada')
            ->assertJsonPath('data.usuario_aprueba_id', $this->administrador->id)
            ->assertJsonPath('data.detalles.0.cantidad_aprobada', '3.500');

        $this->assertDatabaseHas('requisiciones', [
            'id' => $requisicion->id,
            'estado' => 'aprobada',
            'usuario_aprueba_id' => $this->administrador->id,
        ]);
        $this->assertDatabaseHas('requisicion_detalles', [
            'id' => $requisicion->detalles[1]->id,
            'cantidad_aprobada' => 0.5,
        ]);
        $this->assertDatabaseMissing('existencias_area_operativa', ['insumo_id' => $this->arroz->id]);
    }

    /**
     * Verifica que no se puede aprobar una cantidad superior a la solicitada.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     */
    public function test_administrador_no_puede_aprobar_cantidad_mayor_a_la_solicitada(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);

        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'aprobada',
            'detalles' => [
                ['id' => $requisicion->detalles[0]->id, 'cantidad_aprobada' => 5],
                ['id' => $requisicion->detalles[1]->id, 'cantidad_aprobada' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors("detalles.{$requisicion->detalles[0]->id}.cantidad_aprobada");

        $this->assertDatabaseHas('requisiciones', ['id' => $requisicion->id, 'estado' => 'pendiente']);
    }

    /**
     * Verifica que bodega puede rechazar y que un solicitante no puede resolver.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     */
    public function test_solo_administrador_resuelve_y_puede_rechazar_requisicion(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->cocinero);

        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'rechazada',
        ])->assertForbidden();

        Sanctum::actingAs($this->administrador);
        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'rechazada',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'rechazada')
            ->assertJsonPath('data.usuario_aprueba_id', $this->administrador->id);

        $this->assertDatabaseHas('requisiciones', [
            'id' => $requisicion->id,
            'estado' => 'rechazada',
            'usuario_aprueba_id' => $this->administrador->id,
        ]);
    }

    /**
     * Verifica que la transferencia descuenta bodega y acredita el área en una transacción.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-54
     */
    public function test_administrador_transfiere_stock_aprobado_al_area_operativa(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);
        $this->aprobarRequisicion($requisicion, 3.5, 0.5);

        $respuesta = $this->postJson("/api/requisiciones/{$requisicion->id}/transferir");

        $respuesta
            ->assertOk()
            ->assertJsonPath('data.estado', 'transferida');

        $this->assertDatabaseHas('insumos', ['id' => $this->arroz->id, 'stock_actual' => 16.5]);
        $this->assertDatabaseHas('insumos', ['id' => $this->aceite->id, 'stock_actual' => 9.5]);
        $this->assertDatabaseHas('existencias_area_operativa', [
            'area' => 'cocina',
            'insumo_id' => $this->arroz->id,
            'stock_actual' => 3.5,
        ]);
        $this->assertDatabaseHas('existencias_area_operativa', [
            'area' => 'cocina',
            'insumo_id' => $this->aceite->id,
            'stock_actual' => 0.5,
        ]);

        $this->postJson("/api/requisiciones/{$requisicion->id}/transferir")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    /**
     * Verifica que una transferencia sin existencias no altera ningún inventario.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-54
     */
    public function test_transferencia_sin_stock_suficiente_se_revierte_por_completo(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);
        $this->aprobarRequisicion($requisicion, 3.5, 0.5);
        $this->arroz->update(['stock_actual' => 1]);

        $this->postJson("/api/requisiciones/{$requisicion->id}/transferir")
            ->assertUnprocessable()
            ->assertJsonValidationErrors("detalles.{$requisicion->detalles[0]->id}.cantidad_aprobada");

        $this->assertDatabaseHas('requisiciones', ['id' => $requisicion->id, 'estado' => 'aprobada']);
        $this->assertDatabaseHas('insumos', ['id' => $this->arroz->id, 'stock_actual' => 1]);
        $this->assertDatabaseHas('insumos', ['id' => $this->aceite->id, 'stock_actual' => 10]);
        $this->assertDatabaseCount('existencias_area_operativa', 0);
    }

    /**
     * Verifica que la solicitud rechaza áreas no autorizadas, listas vacías e insumos inexistentes.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-52 / CC-55
     */
    public function test_crear_requisicion_rechaza_area_invalida_o_sin_detalles(): void
    {
        Sanctum::actingAs($this->cocinero);

        $this->postJson('/api/requisiciones', [
            'area_solicitante' => 'patio',
            'detalles' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['area_solicitante', 'detalles']);

        $this->postJson('/api/requisiciones', [
            'area_solicitante' => 'cocina',
            'detalles' => [
                ['insumo_id' => 99999, 'cantidad_solicitada' => 2],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('detalles.0.insumo_id');

        $this->assertDatabaseCount('requisiciones', 0);
    }

    /**
     * Verifica que un usuario administrador también tiene permiso para emitir requisiciones.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-52 / CC-55
     */
    public function test_administrador_tambien_puede_crear_requisicion(): void
    {
        Sanctum::actingAs($this->administrador);

        $respuesta = $this->postJson('/api/requisiciones', [
            'area_solicitante' => 'servicio',
            'observaciones' => 'Reabastecimiento de salón.',
            'detalles' => [
                ['insumo_id' => $this->aceite->id, 'cantidad_solicitada' => 2],
            ],
        ]);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.area_solicitante', 'servicio')
            ->assertJsonPath('data.usuario_solicita_id', $this->administrador->id);

        $this->assertDatabaseHas('requisiciones', [
            'usuario_solicita_id' => $this->administrador->id,
            'area_solicitante' => 'servicio',
            'estado' => 'pendiente',
        ]);
    }

    /**
     * Verifica que bodega lista las requisiciones ordenadas cronológicamente inverso con sus relaciones.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-55
     */
    public function test_administrador_lista_requisiciones_ordenadas_con_relaciones(): void
    {
        Carbon::setTestNow('2026-10-23 10:00:00');
        $req1 = Requisicion::create([
            'usuario_solicita_id' => $this->cocinero->id,
            'area_solicitante' => 'cocina',
            'estado' => 'pendiente',
        ]);
        $req1->detalles()->create(['insumo_id' => $this->arroz->id, 'cantidad_solicitada' => 1]);

        Carbon::setTestNow('2026-10-23 10:10:00');
        $req2 = Requisicion::create([
            'usuario_solicita_id' => $this->cocinero->id,
            'area_solicitante' => 'servicio',
            'estado' => 'aprobada',
            'usuario_aprueba_id' => $this->administrador->id,
        ]);
        $req2->detalles()->create(['insumo_id' => $this->aceite->id, 'cantidad_solicitada' => 2, 'cantidad_aprobada' => 1]);

        Carbon::setTestNow('2026-10-23 10:20:00');
        $req3 = Requisicion::create([
            'usuario_solicita_id' => $this->cocinero->id,
            'area_solicitante' => 'despacho',
            'estado' => 'pendiente',
        ]);
        $req3->detalles()->create(['insumo_id' => $this->arroz->id, 'cantidad_solicitada' => 3]);

        Sanctum::actingAs($this->administrador);

        $respuesta = $this->getJson('/api/requisiciones');

        Carbon::setTestNow();

        $respuesta
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Requisiciones obtenidas correctamente.')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $req3->id)
            ->assertJsonPath('data.1.id', $req2->id)
            ->assertJsonPath('data.2.id', $req1->id)
            ->assertJsonPath('data.1.usuario_aprueba.id', $this->administrador->id)
            ->assertJsonPath('data.0.detalles.0.insumo.id', $this->arroz->id);
    }

    /**
     * Verifica que solo usuarios con rol administrador pueden acceder a la lista de requisiciones.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-55
     */
    public function test_usuario_no_administrador_no_puede_listar_requisiciones(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/requisiciones')->assertUnauthorized();

        Sanctum::actingAs($this->cocinero);
        $this->getJson('/api/requisiciones')->assertForbidden();

        Role::findOrCreate('mesero', 'web');
        $mesero = User::factory()->create();
        $mesero->assignRole('mesero');
        Sanctum::actingAs($mesero);
        $this->getJson('/api/requisiciones')->assertForbidden();
    }

    /**
     * Verifica que no se puede resolver una requisición que ya fue aprobada o rechazada.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-55
     */
    public function test_rechaza_resolver_requisicion_que_ya_fue_resuelta(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);

        $this->aprobarRequisicion($requisicion, 2, 1);

        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'rechazada',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');

        $otraRequisicion = $this->crearRequisicionPendiente();
        $this->patchJson("/api/requisiciones/{$otraRequisicion->id}/resolucion", [
            'estado' => 'rechazada',
        ])->assertOk();

        $this->patchJson("/api/requisiciones/{$otraRequisicion->id}/resolucion", [
            'estado' => 'aprobada',
            'detalles' => [
                ['id' => $otraRequisicion->detalles[0]->id, 'cantidad_aprobada' => 1],
                ['id' => $otraRequisicion->detalles[1]->id, 'cantidad_aprobada' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    /**
     * Verifica validación cuando se omiten renglones o se envían identificadores ajenos en la aprobación.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-55
     */
    public function test_rechaza_aprobacion_si_faltan_renglones_o_contiene_renglones_ajenos(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);

        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'aprobada',
            'detalles' => [
                ['id' => $requisicion->detalles[0]->id, 'cantidad_aprobada' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('detalles');

        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'aprobada',
            'detalles' => [
                ['id' => $requisicion->detalles[0]->id, 'cantidad_aprobada' => 1],
                ['id' => 999999, 'cantidad_aprobada' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('detalles');
    }

    /**
     * Verifica que solo los estados 'aprobada' y 'rechazada' son válidos al resolver.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-55
     */
    public function test_rechaza_resolucion_con_estado_no_permitido(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);

        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'anulada',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    /**
     * Verifica que no se permite transferir requisiciones en estado pendiente o rechazada.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-54 / CC-55
     */
    public function test_rechaza_transferir_requisicion_que_no_esta_aprobada(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);

        $this->postJson("/api/requisiciones/{$requisicion->id}/transferir")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');

        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'rechazada',
        ])->assertOk();

        $this->postJson("/api/requisiciones/{$requisicion->id}/transferir")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }

    /**
     * Verifica que solo administradores pueden transferir insumos de bodega a áreas operativas.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-54 / CC-55
     */
    public function test_usuario_no_administrador_no_puede_transferir_stock(): void
    {
        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);
        $this->aprobarRequisicion($requisicion, 1, 1);

        Sanctum::actingAs($this->cocinero);
        $this->postJson("/api/requisiciones/{$requisicion->id}/transferir")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/requisiciones/{$requisicion->id}/transferir")->assertUnauthorized();
    }

    /**
     * Verifica que al transferir, si el área operativa ya tiene existencias del insumo, se acumulan.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-54 / CC-55
     */
    public function test_transferencia_acumula_stock_en_area_operativa_si_ya_existe_registro(): void
    {
        ExistenciaAreaOperativa::create([
            'area' => 'cocina',
            'insumo_id' => $this->arroz->id,
            'stock_actual' => 2.0,
        ]);

        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);
        $this->aprobarRequisicion($requisicion, 3.5, 1.0);

        $this->postJson("/api/requisiciones/{$requisicion->id}/transferir")->assertOk();

        $this->assertDatabaseHas('existencias_area_operativa', [
            'area' => 'cocina',
            'insumo_id' => $this->arroz->id,
            'stock_actual' => 5.5,
        ]);
        $this->assertDatabaseHas('existencias_area_operativa', [
            'area' => 'cocina',
            'insumo_id' => $this->aceite->id,
            'stock_actual' => 1.0,
        ]);
    }

    /**
     * Verifica que una transferencia con el stock exacto en bodega deja el stock en cero sin errores.
     *
     * @autor  Cesar
     * @fecha  2026-10-23
     * @módulo Inventario – RF-INV-004 / CC-54 / CC-55
     */
    public function test_transferencia_con_stock_exacto_en_bodega_deja_inventario_en_cero(): void
    {
        $this->arroz->update(['stock_actual' => 4.0]);
        $this->aceite->update(['stock_actual' => 1.0]);

        $requisicion = $this->crearRequisicionPendiente();
        Sanctum::actingAs($this->administrador);
        $this->aprobarRequisicion($requisicion, 4.0, 1.0);

        $this->postJson("/api/requisiciones/{$requisicion->id}/transferir")
            ->assertOk()
            ->assertJsonPath('data.estado', 'transferida');

        $this->assertDatabaseHas('insumos', ['id' => $this->arroz->id, 'stock_actual' => 0]);
        $this->assertDatabaseHas('insumos', ['id' => $this->aceite->id, 'stock_actual' => 0]);
        $this->assertDatabaseHas('existencias_area_operativa', [
            'area' => 'cocina',
            'insumo_id' => $this->arroz->id,
            'stock_actual' => 4.0,
        ]);
    }

    /**
     * Crea una requisición pendiente con dos detalles para probar el flujo de bodega.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
     *
     * @return Requisicion Requisición con detalles cargados.
     */
    private function crearRequisicionPendiente(): Requisicion
    {
        $requisicion = Requisicion::create([
            'usuario_solicita_id' => $this->cocinero->id,
            'area_solicitante' => 'cocina',
            'estado' => 'pendiente',
        ]);
        $requisicion->detalles()->createMany([
            ['insumo_id' => $this->arroz->id, 'cantidad_solicitada' => 4],
            ['insumo_id' => $this->aceite->id, 'cantidad_solicitada' => 1],
        ]);

        return $requisicion->load('detalles');
    }

    /**
     * Aprueba las dos cantidades del escenario de prueba indicado.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     *
     * @param  Requisicion  $requisicion  Requisición pendiente creada para la prueba.
     * @param  float  $cantidadArroz  Cantidad de arroz autorizada.
     * @param  float  $cantidadAceite  Cantidad de aceite autorizada.
     */
    private function aprobarRequisicion(Requisicion $requisicion, float $cantidadArroz, float $cantidadAceite): void
    {
        $this->patchJson("/api/requisiciones/{$requisicion->id}/resolucion", [
            'estado' => 'aprobada',
            'detalles' => [
                ['id' => $requisicion->detalles[0]->id, 'cantidad_aprobada' => $cantidadArroz],
                ['id' => $requisicion->detalles[1]->id, 'cantidad_aprobada' => $cantidadAceite],
            ],
        ])->assertOk();
    }
}
