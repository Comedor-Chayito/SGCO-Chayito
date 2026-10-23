<?php

namespace Tests\Feature\Api;

use App\Models\Insumo;
use App\Models\Requisicion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas del ciclo de requisiciones internas: creación, aprobación y transferencia.
 *
 * @autor  Jeferson De La Cruz / Alexis
 *
 * @fecha  2026-10-22
 *
 * @módulo Inventario – RF-INV-004 / CC-52 / CC-53 / CC-54
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
            ->assertJsonPath('data.detalles.0.cantidad_aprobada', '3.50');

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
