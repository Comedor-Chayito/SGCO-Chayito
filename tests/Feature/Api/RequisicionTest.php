<?php

namespace Tests\Feature\Api;

use App\Models\Insumo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas de creación de requisiciones internas desde áreas operativas.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-10-22
 *
 * @módulo Inventario – RF-INV-004 / CC-52
 */
class RequisicionTest extends TestCase
{
    use RefreshDatabase;

    private User $cocinero;

    private Insumo $arroz;

    private Insumo $aceite;

    /**
     * Prepara un solicitante autorizado y dos insumos de bodega.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     */
    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('cocinero');
        $this->cocinero = User::factory()->create();
        $this->cocinero->assignRole('cocinero');
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
}
