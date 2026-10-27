<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\Receta;
use App\Models\User;
use App\Services\InsumoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suite de pruebas integrales para las alertas de quiebre de stock contra el stock mínimo.
 * Valida de punta a punta el cumplimiento del criterio de aceptación CC-59 (US-INV-03 / RF-INV-004).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-59
 */
class AlertaStockTest extends TestCase
{
    use RefreshDatabase;

    protected InsumoService $insumoService;
    protected User $cajero;
    protected Mesa $mesa;

    /**
     * Configuración del entorno de prueba.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->insumoService = app(InsumoService::class);

        Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('cajero');

        $this->mesa = Mesa::create([
            'numero'    => 1,
            'capacidad' => 4,
            'estado'    => 'ocupada',
        ]);
    }

    /**
     * Valida que el endpoint de alertas retorna los insumos cuyo stock está en o bajo el mínimo (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_endpoint_alertas_retorna_insumos_en_o_por_debajo_del_stock_minimo(): void
    {
        $insumoBajo = Insumo::create([
            'nombre'        => 'Azúcar Morena',
            'unidad_medida' => 'kg',
            'stock_actual'  => 3.000,
            'stock_minimo'  => 5.000,
        ]);

        $insumoAgotado = Insumo::create([
            'nombre'        => 'Café de Grano',
            'unidad_medida' => 'kg',
            'stock_actual'  => 0.000,
            'stock_minimo'  => 2.000,
        ]);

        $insumoNormal = Insumo::create([
            'nombre'        => 'Sal Marina',
            'unidad_medida' => 'kg',
            'stock_actual'  => 10.000,
            'stock_minimo'  => 2.000,
        ]);

        $response = $this->getJson('/api/insumos/alertas');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Alertas de stock obtenidas correctamente.',
            ])
            ->assertJsonCount(2, 'data');

        $idsEnAlerta = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($insumoBajo->id, $idsEnAlerta);
        $this->assertContains($insumoAgotado->id, $idsEnAlerta);
        $this->assertNotContains($insumoNormal->id, $idsEnAlerta);
    }

    /**
     * Valida que los insumos con stock superior al mínimo son excluidos de la lista de alertas (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_endpoint_alertas_excluye_insumos_con_stock_normal(): void
    {
        Insumo::create([
            'nombre'        => 'Harina de Trigo',
            'unidad_medida' => 'kg',
            'stock_actual'  => 25.000,
            'stock_minimo'  => 10.000,
        ]);

        Insumo::create([
            'nombre'        => 'Aceite de Oliva',
            'unidad_medida' => 'lt',
            'stock_actual'  => 8.000,
            'stock_minimo'  => 4.000,
        ]);

        $response = $this->getJson('/api/insumos/alertas');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    /**
     * Valida que los insumos agotados (stock <= 0) aparecen primero en la lista de alertas (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_prioriza_insumos_agotados_primero_en_la_lista_de_alertas(): void
    {
        // Insumos bajos (stock > 0 pero <= minimo)
        $bajoB = Insumo::create([
            'nombre'        => 'Zanahorias',
            'unidad_medida' => 'kg',
            'stock_actual'  => 2.000,
            'stock_minimo'  => 5.000,
        ]);

        $bajoA = Insumo::create([
            'nombre'        => 'Cebollas',
            'unidad_medida' => 'kg',
            'stock_actual'  => 1.000,
            'stock_minimo'  => 3.000,
        ]);

        // Insumos agotados (stock <= 0)
        $agotadoB = Insumo::create([
            'nombre'        => 'Papas',
            'unidad_medida' => 'kg',
            'stock_actual'  => 0.000,
            'stock_minimo'  => 10.000,
        ]);

        $agotadoA = Insumo::create([
            'nombre'        => 'Aguacates',
            'unidad_medida' => 'kg',
            'stock_actual'  => 0.000,
            'stock_minimo'  => 5.000,
        ]);

        $response = $this->getJson('/api/insumos/alertas');

        $response->assertStatus(200);

        $nombres = collect($response->json('data'))->pluck('nombre')->all();

        // Los agotados deben ir primero ordenados alfabéticamente: Aguacates, Papas
        $this->assertSame('Aguacates', $nombres[0]);
        $this->assertSame('Papas', $nombres[1]);

        // Luego los bajos ordenados alfabéticamente: Cebollas, Zanahorias
        $this->assertSame('Cebollas', $nombres[2]);
        $this->assertSame('Zanahorias', $nombres[3]);
    }

    /**
     * Valida la correcta clasificación de estados: agotado, bajo y normal (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_clasifica_correctamente_estados_agotado_bajo_y_normal(): void
    {
        $agotadoCero = new Insumo(['stock_actual' => 0.000, 'stock_minimo' => 5.000]);
        $agotadoNegativo = new Insumo(['stock_actual' => -1.500, 'stock_minimo' => 5.000]);
        $bajo = new Insumo(['stock_actual' => 2.500, 'stock_minimo' => 5.000]);
        $normal = new Insumo(['stock_actual' => 8.000, 'stock_minimo' => 5.000]);

        $this->assertSame(
            InsumoService::ESTADO_STOCK_AGOTADO,
            $this->insumoService->evaluarExistencias($agotadoCero)
        );

        $this->assertSame(
            InsumoService::ESTADO_STOCK_AGOTADO,
            $this->insumoService->evaluarExistencias($agotadoNegativo)
        );

        $this->assertSame(
            InsumoService::ESTADO_STOCK_BAJO,
            $this->insumoService->evaluarExistencias($bajo)
        );

        $this->assertSame(
            InsumoService::ESTADO_STOCK_NORMAL,
            $this->insumoService->evaluarExistencias($normal)
        );
    }

    /**
     * Valida que existencias exactamente iguales al stock mínimo se consideran en alerta baja (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_stock_exactamente_igual_al_minimo_se_considera_en_alerta(): void
    {
        $insumoEnElLimite = Insumo::create([
            'nombre'        => 'Leche Entera',
            'unidad_medida' => 'lt',
            'stock_actual'  => 6.000,
            'stock_minimo'  => 6.000,
        ]);

        $this->assertSame(
            InsumoService::ESTADO_STOCK_BAJO,
            $this->insumoService->evaluarExistencias($insumoEnElLimite)
        );

        $response = $this->getJson('/api/insumos/alertas');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $insumoEnElLimite->id)
            ->assertJsonPath('data.0.estado_stock', 'bajo');
    }

    /**
     * Valida que si no hay quiebre de stock, el endpoint retorna un arreglo vacío (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_endpoint_alertas_retorna_arreglo_vacio_si_no_hay_quiebre_de_stock(): void
    {
        Insumo::create([
            'nombre'        => 'Pechuga de Pollo',
            'unidad_medida' => 'lb',
            'stock_actual'  => 30.000,
            'stock_minimo'  => 10.000,
        ]);

        $response = $this->getJson('/api/insumos/alertas');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Alertas de stock obtenidas correctamente.',
                'data'    => [],
            ]);
    }

    /**
     * Valida que el listado general y el detalle de insumos incluyen el atributo estado_stock (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_listado_general_y_detalle_de_insumos_incluyen_atributo_estado_stock(): void
    {
        $insumo = Insumo::create([
            'nombre'        => 'Frijoles Rojos',
            'unidad_medida' => 'lb',
            'stock_actual'  => 2.000,
            'stock_minimo'  => 5.000,
        ]);

        // Listado general GET /api/insumos
        $resIndex = $this->getJson('/api/insumos');
        $resIndex->assertStatus(200)
            ->assertJsonPath('data.0.id', $insumo->id)
            ->assertJsonPath('data.0.estado_stock', 'bajo');

        // Detalle específico GET /api/insumos/{id}
        $resShow = $this->getJson("/api/insumos/{$insumo->id}");
        $resShow->assertStatus(200)
            ->assertJsonPath('data.id', $insumo->id)
            ->assertJsonPath('data.estado_stock', 'bajo');
    }

    /**
     * Valida la transición reactiva de estados al actualizar el stock del insumo (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_transicion_reactiva_de_alertas_al_actualizar_stock(): void
    {
        $insumo = Insumo::create([
            'nombre'        => 'Mantequilla',
            'unidad_medida' => 'lb',
            'stock_actual'  => 15.000,
            'stock_minimo'  => 5.000,
        ]);

        // 1. Estado inicial: normal (no figura en alertas)
        $this->assertCount(0, $this->getJson('/api/insumos/alertas')->json('data'));

        // 2. Disminuye existencias a stock bajo: pasa a figurar en alertas
        $insumo->update(['stock_actual' => 4.000]);
        $resBajo = $this->getJson('/api/insumos/alertas');
        $resBajo->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $insumo->id)
            ->assertJsonPath('data.0.estado_stock', 'bajo');

        // 3. Se agota por completo (stock = 0): cambia a estado agotado
        $insumo->update(['stock_actual' => 0.000]);
        $resAgotado = $this->getJson('/api/insumos/alertas');
        $resAgotado->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $insumo->id)
            ->assertJsonPath('data.0.estado_stock', 'agotado');

        // 4. Se reabastece (compra de reposición): desaparece de alertas y vuelve a normal
        $insumo->update(['stock_actual' => 20.000]);
        $this->assertCount(0, $this->getJson('/api/insumos/alertas')->json('data'));
    }

    /**
     * Valida la evaluación de alertas en la frontera precisa de decimales y milésimas (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_evaluacion_de_alertas_con_precision_de_tres_decimales(): void
    {
        // Stock apenas 1 milésima por encima del mínimo (5.001 vs 5.000): NO debe ser alerta
        $insumoArriba = Insumo::create([
            'nombre'        => 'Queso Duro',
            'unidad_medida' => 'lb',
            'stock_actual'  => 5.001,
            'stock_minimo'  => 5.000,
        ]);

        $this->assertSame(
            InsumoService::ESTADO_STOCK_NORMAL,
            $this->insumoService->evaluarExistencias($insumoArriba)
        );

        // Stock apenas 1 milésima por debajo del mínimo (4.999 vs 5.000): SÍ debe ser alerta baja
        $insumoAbajo = Insumo::create([
            'nombre'        => 'Queso Fresco',
            'unidad_medida' => 'lb',
            'stock_actual'  => 4.999,
            'stock_minimo'  => 5.000,
        ]);

        $this->assertSame(
            InsumoService::ESTADO_STOCK_BAJO,
            $this->insumoService->evaluarExistencias($insumoAbajo)
        );

        $response = $this->getJson('/api/insumos/alertas');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $insumoAbajo->id);
    }

    /**
     * Valida que la deducción automática por cobro en POS activa la aparición del insumo en alertas (CC-59).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-59
     *
     * @return void
     */
    public function test_deduccion_de_inventario_por_cobro_activa_aparicion_en_endpoint_alertas(): void
    {
        // Insumo con stock apenas superior al mínimo
        $insumo = Insumo::create([
            'nombre'        => 'Costilla de Cerdo',
            'unidad_medida' => 'lb',
            'stock_actual'  => 10.500,
            'stock_minimo'  => 10.000,
        ]);

        $platillo = Platillo::create([
            'nombre'          => 'Costilla Asada',
            'precio_unitario' => 7.00,
            'disponible'      => true,
        ]);

        Receta::create([
            'platillo_id'          => $platillo->id,
            'insumo_id'            => $insumo->id,
            'cantidad_por_porcion' => 1.000, // 1 lb por plato
            'unidad_consumo'       => 'lb',
        ]);

        // Antes del cobro, no hay alertas
        $this->assertCount(0, $this->getJson('/api/insumos/alertas')->json('data'));

        // Se registra y cobra una comanda de 2 platos (deduce 2 lb de costilla)
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 14.00,
        ]);

        $comanda->detalles()->create([
            'platillo_id'     => $platillo->id,
            'cantidad'        => 2,
            'precio_unitario' => 7.00,
        ]);

        Sanctum::actingAs($this->cajero);
        $resCobro = $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 20.00,
        ]);

        $resCobro->assertStatus(201);

        // Stock resultante: 10.500 - 2.000 = 8.500 lb (menor a 10.000 lb)
        $this->assertEquals(8.500, (float) $insumo->fresh()->stock_actual);

        // Ahora debe figurar en el endpoint de alertas con estado 'bajo'
        $resAlertas = $this->getJson('/api/insumos/alertas');
        $resAlertas->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $insumo->id)
            ->assertJsonPath('data.0.estado_stock', 'bajo');
    }
}

