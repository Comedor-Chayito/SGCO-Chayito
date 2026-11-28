<?php

namespace Tests\Feature\Api;

use App\Models\Egreso;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\SubcategoriaEgreso;
use App\Models\User;
use App\Services\MovimientoInventarioService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Suite integral de pruebas para entradas a bodega y movimientos de inventario.
 * Valida el cumplimiento del requerimiento funcional RF-INV-003, historia US-INV-04 y tareas CC-113 a CC-119.
 *
 * @autor  Equipo SGCO-Chayito
 *
 * @fecha  2026-11-18
 *
 * @módulo Inventario – US-INV-04 / RF-INV-003 / CC-113 / CC-114 / CC-115 / CC-116 / CC-117 / CC-119
 */
class MovimientoInventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    private User $socio;

    private User $cocinero;

    private User $cajero;

    private User $mesero;

    private User $contadora;

    private Insumo $tomate;

    private Insumo $aceite;

    private Egreso $egreso;

    /**
     * Prepara el entorno con roles oficiales, usuarios autenticables, insumos de prueba y factura de egreso.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Inventario – US-INV-04 / CC-119
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->administrador = User::factory()->create(['name' => 'Admin Bodega']);
        $this->administrador->assignRole('administrador');

        $this->socio = User::factory()->create(['name' => 'Socio Bodega']);
        $this->socio->assignRole('socio');

        $this->cocinero = User::factory()->create(['name' => 'Cocinero']);
        $this->cocinero->assignRole('cocinero');

        $this->cajero = User::factory()->create(['name' => 'Cajero']);
        $this->cajero->assignRole('cajero');

        $this->mesero = User::factory()->create(['name' => 'Mesero']);
        $this->mesero->assignRole('mesero');

        $this->contadora = User::factory()->create(['name' => 'Contadora']);
        $this->contadora->assignRole('contadora');

        $this->tomate = Insumo::create([
            'nombre' => 'Tomate de ensalada',
            'unidad_medida' => 'kg',
            'stock_actual' => 10.000000,
            'stock_minimo' => 2.000000,
        ]);

        $this->aceite = Insumo::create([
            'nombre' => 'Aceite vegetal',
            'unidad_medida' => 'lt',
            'stock_actual' => 5.000000,
            'stock_minimo' => 1.000000,
        ]);

        $subcategoria = SubcategoriaEgreso::create([
            'nombre' => 'Verduras e Insumos de Cocina',
            'activo' => true,
        ]);

        $this->egreso = Egreso::create([
            'correlativo' => 'EG-000001',
            'subcategoria_egreso_id' => $subcategoria->id,
            'usuario_id' => $this->administrador->id,
            'proveedor' => 'Distribuidora Central La Tiendita',
            'numero_comprobante' => 'FAC-987654',
            'fecha_comprobante' => '2026-11-18',
            'monto_total' => 113.00,
            'costo_neto' => 100.00,
            'iva_credito_fiscal' => 13.00,
            'descripcion' => 'Compra de insumos frescos para bodega',
        ]);

        Sanctum::actingAs($this->administrador);
    }

    // =========================================================================
    // SECCIÓN 1: Autenticación y Control de Acceso RBAC (CC-113 / CC-119)
    // =========================================================================

    /**
     * 1. Petición sin token a registro de entradas devuelve 401.
     */
    public function test_sin_autenticacion_rechaza_entradas_con_401(): void
    {
        $this->app['auth']->forgetGuards();

        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 5,
        ]);

        $respuesta->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'No autenticado. Token ausente o inválido.');
    }

    /**
     * 2. Petición sin token a registro de ajustes devuelve 401.
     */
    public function test_sin_autenticacion_rechaza_ajustes_con_401(): void
    {
        $this->app['auth']->forgetGuards();

        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => 2,
            'motivo' => 'Corrección de stock',
        ]);

        $respuesta->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'No autenticado. Token ausente o inválido.');
    }

    /**
     * 3. Petición sin token a consulta de movimientos devuelve 401.
     */
    public function test_sin_autenticacion_rechaza_consulta_movimientos_con_401(): void
    {
        $this->app['auth']->forgetGuards();

        $respuesta = $this->getJson("/api/insumos/{$this->tomate->id}/movimientos");

        $respuesta->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'No autenticado. Token ausente o inválido.');
    }

    /**
     * 4. Roles no autorizados reciben 403 al intentar registrar entradas a bodega.
     */
    public function test_roles_no_autorizados_reciben_403_al_registrar_entradas(): void
    {
        $rolesNoAutorizados = [$this->cocinero, $this->cajero, $this->mesero, $this->contadora];

        foreach ($rolesNoAutorizados as $usuario) {
            Sanctum::actingAs($usuario);

            $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
                'cantidad' => 5,
            ]);

            $respuesta->assertStatus(403)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('message', 'No tienes permisos suficientes para realizar esta acción.');
        }
    }

    /**
     * 5. Roles no autorizados reciben 403 al intentar registrar ajustes de inventario.
     */
    public function test_roles_no_autorizados_reciben_403_al_registrar_ajustes(): void
    {
        $rolesNoAutorizados = [$this->cocinero, $this->cajero, $this->mesero, $this->contadora];

        foreach ($rolesNoAutorizados as $usuario) {
            Sanctum::actingAs($usuario);

            $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
                'cantidad' => -1,
                'motivo' => 'Merma no autorizada',
            ]);

            $respuesta->assertStatus(403)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('message', 'No tienes permisos suficientes para realizar esta acción.');
        }
    }

    /**
     * 6. Roles no autorizados reciben 403 al intentar consultar el historial de movimientos.
     */
    public function test_roles_no_autorizados_reciben_403_al_consultar_movimientos(): void
    {
        $rolesNoAutorizados = [$this->cocinero, $this->cajero, $this->mesero, $this->contadora];

        foreach ($rolesNoAutorizados as $usuario) {
            Sanctum::actingAs($usuario);

            $respuesta = $this->getJson("/api/insumos/{$this->tomate->id}/movimientos");

            $respuesta->assertStatus(403)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('message', 'No tienes permisos suficientes para realizar esta acción.');
        }
    }

    /**
     * 7. El usuario con rol socio tiene acceso completo a entradas, ajustes e historial de movimientos.
     */
    public function test_socio_tiene_permisos_para_gestionar_movimientos_completos(): void
    {
        Sanctum::actingAs($this->socio);

        // Entrada
        $respEntrada = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 5,
        ]);
        $respEntrada->assertStatus(201)
            ->assertJsonPath('status', 'ok');

        // Ajuste
        $respAjuste = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => -1,
            'motivo' => 'Ajuste realizado por el socio',
        ]);
        $respAjuste->assertStatus(201)
            ->assertJsonPath('status', 'ok');

        // Historial
        $respHistorial = $this->getJson("/api/insumos/{$this->tomate->id}/movimientos");
        $respHistorial->assertStatus(200)
            ->assertJsonPath('status', 'ok');
    }

    // =========================================================================
    // SECCIÓN 2: Entradas a Bodega (CC-115 / CC-119)
    // =========================================================================

    /**
     * 8. Administrador registra entrada entera, actualiza stock y persiste movimiento (código 201).
     */
    public function test_administrador_registra_entrada_entera_y_actualiza_stock_con_codigo_201(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 15,
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Entrada de inventario registrada correctamente.')
            ->assertJsonPath('data.insumo.id', $this->tomate->id)
            ->assertJsonPath('data.insumo.stock_actual', '25.000000')
            ->assertJsonPath('data.insumo.estado_stock', 'normal')
            ->assertJsonPath('data.movimiento.tipo', MovimientoInventario::TIPO_ENTRADA)
            ->assertJsonPath('data.movimiento.cantidad', '15.000000')
            ->assertJsonPath('data.movimiento.stock_resultante', '25.000000')
            ->assertJsonPath('data.movimiento.usuario_id', $this->administrador->id)
            ->assertJsonPath('data.movimiento.usuario.id', $this->administrador->id)
            ->assertJsonPath('data.movimiento.usuario.name', $this->administrador->name);

        $this->assertDatabaseHas('insumos', [
            'id' => $this->tomate->id,
            'stock_actual' => '25.000000',
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $this->tomate->id,
            'usuario_id' => $this->administrador->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'cantidad' => '15.000000',
            'stock_resultante' => '25.000000',
        ]);
    }

    /**
     * 9. Administrador registra entrada con precisión de hasta seis decimales.
     */
    public function test_administrador_registra_entrada_con_precision_de_seis_decimales(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 2.123456,
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.insumo.stock_actual', '12.123456')
            ->assertJsonPath('data.movimiento.cantidad', '2.123456')
            ->assertJsonPath('data.movimiento.stock_resultante', '12.123456');

        $this->assertSame('12.123456', (string) $this->tomate->fresh()->stock_actual);
    }

    /**
     * 10. Administrador registra entrada vinculada a factura de egreso y motivo opcional.
     */
    public function test_administrador_registra_entrada_vinculada_a_factura_de_egreso_y_motivo(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 8.500000,
            'egreso_id' => $this->egreso->id,
            'motivo' => 'Recepción conforme de factura de verduras',
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.movimiento.egreso_id', $this->egreso->id)
            ->assertJsonPath('data.movimiento.motivo', 'Recepción conforme de factura de verduras')
            ->assertJsonPath('data.movimiento.egreso.id', $this->egreso->id)
            ->assertJsonPath('data.movimiento.egreso.correlativo', $this->egreso->correlativo)
            ->assertJsonPath('data.movimiento.egreso.proveedor', $this->egreso->proveedor)
            ->assertJsonPath('data.movimiento.egreso.numero_comprobante', $this->egreso->numero_comprobante);

        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $this->tomate->id,
            'egreso_id' => $this->egreso->id,
            'motivo' => 'Recepción conforme de factura de verduras',
        ]);
    }

    /**
     * 11. Entrada rechaza cantidad igual a cero o negativa devolviendo 422.
     */
    public function test_entrada_rechaza_cantidad_cero_o_negativa_con_codigo_422(): void
    {
        // Cantidad cero
        $respCero = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 0,
        ]);
        $this->assertErrorValidacionMovimiento($respCero, 'cantidad');

        // Cantidad negativa
        $respNegativa = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => -4.5,
        ]);
        $this->assertErrorValidacionMovimiento($respNegativa, 'cantidad');
    }

    /**
     * 12. Entrada rechaza cantidad con más de seis decimales o no numérica.
     */
    public function test_entrada_rechaza_cantidad_con_mas_de_seis_decimales_o_no_numerica(): void
    {
        // 7 decimales
        $respDecimales = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 1.1234567,
        ]);
        $this->assertErrorValidacionMovimiento($respDecimales, 'cantidad');

        // No numérica
        $respNoNumerica = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 'cinco_kilos',
        ]);
        $this->assertErrorValidacionMovimiento($respNoNumerica, 'cantidad');
    }

    /**
     * 13. Entrada rechaza egreso inexistente con 422.
     */
    public function test_entrada_rechaza_egreso_inexistente(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 5,
            'egreso_id' => 999999,
        ]);

        $this->assertErrorValidacionMovimiento($respuesta, 'egreso_id');
    }

    /**
     * 14. Entrada sobre insumo inexistente devuelve 404.
     */
    public function test_entrada_en_insumo_inexistente_retorna_404(): void
    {
        $respuesta = $this->postJson('/api/insumos/999999/entradas', [
            'cantidad' => 5,
        ]);

        $respuesta->assertStatus(404);
    }

    // =========================================================================
    // SECCIÓN 3: Ajustes de Inventario (CC-116 / CC-119)
    // =========================================================================

    /**
     * 15. Administrador registra ajuste positivo con motivo obligatorio (código 201).
     */
    public function test_administrador_registra_ajuste_positivo_con_motivo_obligatorio(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => 3.500000,
            'motivo' => 'Sobrante detectado en toma física semanal',
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Ajuste de inventario registrado correctamente.')
            ->assertJsonPath('data.insumo.stock_actual', '13.500000')
            ->assertJsonPath('data.movimiento.tipo', MovimientoInventario::TIPO_AJUSTE)
            ->assertJsonPath('data.movimiento.cantidad', '3.500000')
            ->assertJsonPath('data.movimiento.stock_resultante', '13.500000')
            ->assertJsonPath('data.movimiento.motivo', 'Sobrante detectado en toma física semanal');

        $this->assertSame('13.500000', (string) $this->tomate->fresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', [
            'insumo_id' => $this->tomate->id,
            'tipo' => MovimientoInventario::TIPO_AJUSTE,
            'cantidad' => '3.500000',
            'stock_resultante' => '13.500000',
            'motivo' => 'Sobrante detectado en toma física semanal',
        ]);
    }

    /**
     * 16. Administrador registra ajuste negativo por merma justificada.
     */
    public function test_administrador_registra_ajuste_negativo_por_merma(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => -2.250000,
            'motivo' => 'Desecho de producto por sobremaduración',
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.insumo.stock_actual', '7.750000')
            ->assertJsonPath('data.movimiento.tipo', MovimientoInventario::TIPO_AJUSTE)
            ->assertJsonPath('data.movimiento.cantidad', '-2.250000')
            ->assertJsonPath('data.movimiento.stock_resultante', '7.750000')
            ->assertJsonPath('data.movimiento.motivo', 'Desecho de producto por sobremaduración');

        $this->assertSame('7.750000', (string) $this->tomate->fresh()->stock_actual);
    }

    /**
     * 17. Ajuste negativo permite reducir el saldo exactamente a cero (código 201).
     */
    public function test_ajuste_negativo_permite_reducir_saldo_exactamente_a_cero(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => -10.000000,
            'motivo' => 'Pérdida total del lote por rotura de refrigerador',
        ]);

        $respuesta->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.insumo.stock_actual', '0.000000')
            ->assertJsonPath('data.insumo.estado_stock', 'agotado')
            ->assertJsonPath('data.movimiento.stock_resultante', '0.000000');

        $this->assertSame('0.000000', (string) $this->tomate->fresh()->stock_actual);
    }

    /**
     * 18. Ajuste negativo rechaza la operación si el delta dejaría saldo negativo (código 422).
     */
    public function test_ajuste_negativo_rechaza_operacion_que_deje_existencias_negativas(): void
    {
        // Stock actual es 10.000000; intentar restar 10.000001
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => -10.000001,
            'motivo' => 'Ajuste excesivo erróneo',
        ]);

        $this->assertErrorValidacionMovimiento($respuesta, 'cantidad');

        // El stock en base de datos debe conservarse intacto
        $this->assertSame('10.000000', (string) $this->tomate->fresh()->stock_actual);

        // No debe haberse registrado ningún movimiento
        $this->assertDatabaseMissing('movimientos_inventario', [
            'insumo_id' => $this->tomate->id,
            'motivo' => 'Ajuste excesivo erróneo',
        ]);
    }

    /**
     * 19. Ajuste rechaza cantidad igual a cero con 422.
     */
    public function test_ajuste_rechaza_cantidad_cero(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => 0,
            'motivo' => 'Ajuste neutro',
        ]);

        $this->assertErrorValidacionMovimiento($respuesta, 'cantidad');
    }

    /**
     * 20. Ajuste rechaza petición sin motivo o con motivo vacío (422).
     */
    public function test_ajuste_rechaza_peticion_sin_motivo_o_con_motivo_vacio(): void
    {
        // Sin motivo
        $respSinMotivo = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => 2,
        ]);
        $this->assertErrorValidacionMovimiento($respSinMotivo, 'motivo');

        // Motivo vacío
        $respVacio = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => 2,
            'motivo' => '',
        ]);
        $this->assertErrorValidacionMovimiento($respVacio, 'motivo');
    }

    /**
     * 21. Ajuste rechaza motivo que excede los 255 caracteres.
     */
    public function test_ajuste_rechaza_motivo_que_supera_longitud_maxima(): void
    {
        $respuesta = $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => 1,
            'motivo' => str_repeat('a', 256),
        ]);

        $this->assertErrorValidacionMovimiento($respuesta, 'motivo');
    }

    /**
     * 22. Ajuste en insumo inexistente retorna 404.
     */
    public function test_ajuste_en_insumo_inexistente_retorna_404(): void
    {
        $respuesta = $this->postJson('/api/insumos/999999/ajustes', [
            'cantidad' => 1,
            'motivo' => 'Ajuste en fantasma',
        ]);

        $respuesta->assertStatus(404);
    }

    // =========================================================================
    // SECCIÓN 4: Historial de Movimientos y Paginación (CC-117 / CC-119)
    // =========================================================================

    /**
     * 23. Administrador obtiene historial de movimientos ordenados descendentemente (más recientes primero).
     */
    public function test_administrador_obtiene_historial_ordenado_descendentemente(): void
    {
        // 1. Entrada (+5) -> stock 15
        $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 5,
        ])->assertStatus(201);

        // 2. Ajuste (+2) -> stock 17
        $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => 2,
            'motivo' => 'Ajuste positivo intermedio',
        ])->assertStatus(201);

        // 3. Ajuste (-3) -> stock 14
        $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => -3,
            'motivo' => 'Merma final',
        ])->assertStatus(201);

        $respuesta = $this->getJson("/api/insumos/{$this->tomate->id}/movimientos");

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Movimientos de inventario obtenidos correctamente.')
            ->assertJsonPath('data.total', 3);

        $listado = $respuesta->json('data.data');
        $this->assertCount(3, $listado);

        // El primer elemento debe ser el más reciente (Merma final)
        $this->assertSame('-3.000000', $listado[0]['cantidad']);
        $this->assertSame('14.000000', $listado[0]['stock_resultante']);
        $this->assertSame('Merma final', $listado[0]['motivo']);

        // El segundo elemento debe ser el ajuste intermedio (+2)
        $this->assertSame('2.000000', $listado[1]['cantidad']);
        $this->assertSame('17.000000', $listado[1]['stock_resultante']);

        // El tercer elemento debe ser la entrada (+5)
        $this->assertSame('5.000000', $listado[2]['cantidad']);
        $this->assertSame('15.000000', $listado[2]['stock_resultante']);
    }

    /**
     * 24. Historial incluye los diferentes tipos de movimiento con responsable y relaciones cargadas.
     */
    public function test_historial_incluye_los_diferentes_tipos_de_movimiento_con_responsables(): void
    {
        // Entrada con egreso
        $this->postJson("/api/insumos/{$this->tomate->id}/entradas", [
            'cantidad' => 10,
            'egreso_id' => $this->egreso->id,
            'motivo' => 'Entrada con factura',
        ])->assertStatus(201);

        // Ajuste con motivo
        $this->postJson("/api/insumos/{$this->tomate->id}/ajustes", [
            'cantidad' => -2,
            'motivo' => 'Merma operativa',
        ])->assertStatus(201);

        // Movimiento de venta mediante servicio
        app(MovimientoInventarioService::class)->aplicar(
            $this->tomate,
            -1.5,
            MovimientoInventario::TIPO_VENTA,
            $this->administrador->id
        );

        $respuesta = $this->getJson("/api/insumos/{$this->tomate->id}/movimientos");

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        $tipos = collect($respuesta->json('data.data'))->pluck('tipo')->all();
        $this->assertContains(MovimientoInventario::TIPO_ENTRADA, $tipos);
        $this->assertContains(MovimientoInventario::TIPO_AJUSTE, $tipos);
        $this->assertContains(MovimientoInventario::TIPO_VENTA, $tipos);

        // Validar que la relación usuario no expone campos sensibles (solo id y name)
        $movimientoEntrada = collect($respuesta->json('data.data'))->firstWhere('tipo', MovimientoInventario::TIPO_ENTRADA);
        $this->assertNotNull($movimientoEntrada['usuario']);
        $this->assertSame($this->administrador->id, $movimientoEntrada['usuario']['id']);
        $this->assertSame($this->administrador->name, $movimientoEntrada['usuario']['name']);
        $this->assertArrayNotHasKey('email', $movimientoEntrada['usuario']);
        $this->assertArrayNotHasKey('password', $movimientoEntrada['usuario']);

        // Validar que la relación egreso carga comprobante y proveedor
        $this->assertNotNull($movimientoEntrada['egreso']);
        $this->assertSame($this->egreso->correlativo, $movimientoEntrada['egreso']['correlativo']);
        $this->assertSame($this->egreso->proveedor, $movimientoEntrada['egreso']['proveedor']);
    }

    /**
     * 25. Paginación de movimientos devuelve 20 elementos por página.
     */
    public function test_historial_movimientos_paginacion_veinte_por_pagina(): void
    {
        // Generar 25 movimientos
        for ($i = 1; $i <= 25; $i++) {
            app(MovimientoInventarioService::class)->aplicar(
                $this->tomate,
                1.0,
                MovimientoInventario::TIPO_ENTRADA,
                $this->administrador->id,
                ['motivo' => "Entrada secuencial #{$i}"]
            );
        }

        // Página 1: 20 elementos
        $respPagina1 = $this->getJson("/api/insumos/{$this->tomate->id}/movimientos?page=1");
        $respPagina1->assertStatus(200)
            ->assertJsonPath('data.total', 25)
            ->assertJsonPath('data.per_page', 20)
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.last_page', 2);
        $this->assertCount(20, $respPagina1->json('data.data'));

        // Página 2: 5 elementos restantes
        $respPagina2 = $this->getJson("/api/insumos/{$this->tomate->id}/movimientos?page=2");
        $respPagina2->assertStatus(200)
            ->assertJsonPath('data.current_page', 2);
        $this->assertCount(5, $respPagina2->json('data.data'));
    }

    /**
     * 26. Historial rechaza número de página menor que uno con 422.
     */
    public function test_historial_rechaza_pagina_invalida_con_422(): void
    {
        $respuesta = $this->getJson("/api/insumos/{$this->tomate->id}/movimientos?page=0");

        $this->assertErrorValidacionMovimiento($respuesta, 'page');
    }

    /**
     * 27. Historial en insumo inexistente retorna 404.
     */
    public function test_historial_en_insumo_inexistente_retorna_404(): void
    {
        $respuesta = $this->getJson('/api/insumos/999999/movimientos');

        $respuesta->assertStatus(404);
    }

    // =========================================================================
    // SECCIÓN 5: Transiciones Reactivas de Alertas de Stock (US-INV-03 / US-INV-04)
    // =========================================================================

    /**
     * 28. Entrada reactiva saca a insumo en quiebre de stock del endpoint de alertas.
     */
    public function test_entrada_saca_reactivamente_a_insumo_de_alerta_de_stock(): void
    {
        // 1. Configurar insumo en estado crítico de alerta
        $this->aceite->update([
            'stock_actual' => 0.500000,
            'stock_minimo' => 2.000000,
        ]);

        // Verificar que aparece en alertas
        $alertasAntes = $this->getJson('/api/insumos/alertas');
        $alertasAntes->assertStatus(200);
        $idsAntes = collect($alertasAntes->json('data'))->pluck('id')->all();
        $this->assertContains($this->aceite->id, $idsAntes);

        // 2. Registrar entrada que supera el stock mínimo (0.5 + 5.0 = 5.5 > 2.0)
        $respEntrada = $this->postJson("/api/insumos/{$this->aceite->id}/entradas", [
            'cantidad' => 5.000000,
        ]);
        $respEntrada->assertStatus(201)
            ->assertJsonPath('data.insumo.estado_stock', 'normal');

        // 3. Verificar que ya no figura en el endpoint de alertas
        $alertasDespues = $this->getJson('/api/insumos/alertas');
        $alertasDespues->assertStatus(200);
        $idsDespues = collect($alertasDespues->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->aceite->id, $idsDespues);
    }

    /**
     * 29. Ajuste negativo por merma transiciona a insumo hacia alerta de stock bajo o agotado.
     */
    public function test_ajuste_negativo_transiciona_insumo_a_estado_alerta_o_agotado(): void
    {
        // Stock inicial 5.0, mínimo 1.0 -> Normal
        $respAjuste1 = $this->postJson("/api/insumos/{$this->aceite->id}/ajustes", [
            'cantidad' => -4.200000, // Nuevo stock: 0.8 <= 1.0 (Bajo)
            'motivo' => 'Merma por derrame',
        ]);
        $respAjuste1->assertStatus(201)
            ->assertJsonPath('data.insumo.estado_stock', 'bajo');

        // Verificar presencia en alertas
        $alertas = $this->getJson('/api/insumos/alertas');
        $alertaInsumo = collect($alertas->json('data'))->firstWhere('id', $this->aceite->id);
        $this->assertNotNull($alertaInsumo);
        $this->assertSame('bajo', $alertaInsumo['estado_stock']);

        // Ajuste adicional que lo deja en cero exacto -> Agotado
        $respAjuste2 = $this->postJson("/api/insumos/{$this->aceite->id}/ajustes", [
            'cantidad' => -0.800000,
            'motivo' => 'Consumo de residuo final',
        ]);
        $respAjuste2->assertStatus(201)
            ->assertJsonPath('data.insumo.estado_stock', 'agotado');
    }

    // =========================================================================
    // SECCIÓN 6: Servicio Transaccional MovimientoInventarioService
    // =========================================================================

    /**
     * 30. El servicio lanza AuthenticationException si no hay usuario autenticado ni parámetro explícito.
     */
    public function test_servicio_lanza_excepcion_de_autenticacion_si_no_hay_usuario(): void
    {
        $this->app['auth']->forgetGuards();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Se requiere un usuario para registrar el movimiento.');

        app(MovimientoInventarioService::class)->aplicar(
            $this->tomate,
            5.0,
            MovimientoInventario::TIPO_ENTRADA,
            null
        );
    }

    /**
     * Valida que una respuesta de error de movimiento cumpla el contrato oficial de 3 campos y contenga el campo fallido.
     *
     * @param  TestResponse  $respuesta
     * @param  string  $campo
     * @return void
     */
    private function assertErrorValidacionMovimiento($respuesta, string $campo): void
    {
        $respuesta->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Los datos del movimiento no son validos.');

        $errores = $respuesta->json('data.errores');
        $this->assertIsArray($errores);
        $this->assertArrayHasKey($campo, $errores);
    }
}
