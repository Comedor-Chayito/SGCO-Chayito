<?php

namespace Tests\Feature\Services;

use App\Models\Comanda;
use App\Models\Insumo;
use App\Models\Platillo;
use App\Models\Receta;
use App\Models\User;
use App\Services\DeduccionStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas unitarias/funcionales del servicio DeduccionStockService (CC-48 / US-INV-01).
 * Verifica el cálculo de porciones de receta multiplicado por las unidades vendidas.
 *
 * @autor  kristiankovic
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-003
 */
class DeduccionStockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DeduccionStockService $service;
    protected User $usuario;
    protected Insumo $carne;
    protected Insumo $pan;
    protected Insumo $tomate;
    protected Platillo $hamburguesa;
    protected Platillo $ensalada;
    protected Platillo $refresco;

    /**
     * Configuración inicial del entorno de prueba.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DeduccionStockService();
        $this->usuario = User::factory()->create();

        // Insumos de prueba
        $this->carne = Insumo::create([
            'nombre'        => 'Carne para Hamburguesa',
            'unidad_medida' => 'lb',
            'stock_actual'  => 50.00,
            'stock_minimo'  => 5.00,
        ]);

        $this->pan = Insumo::create([
            'nombre'        => 'Pan de Hamburguesa',
            'unidad_medida' => 'unidad',
            'stock_actual'  => 100.00,
            'stock_minimo'  => 10.00,
        ]);

        $this->tomate = Insumo::create([
            'nombre'        => 'Tomate Fresco',
            'unidad_medida' => 'lb',
            'stock_actual'  => 30.00,
            'stock_minimo'  => 3.00,
        ]);

        // Platillos
        $this->hamburguesa = Platillo::create([
            'nombre'          => 'Hamburguesa Especial',
            'descripcion'     => 'Hamburguesa con carne, pan y tomate',
            'precio_unitario' => 3.50,
            'disponible'      => true,
        ]);

        $this->ensalada = Platillo::create([
            'nombre'          => 'Ensalada Mixta',
            'descripcion'     => 'Ensalada fresca con tomate',
            'precio_unitario' => 2.00,
            'disponible'      => true,
        ]);

        $this->refresco = Platillo::create([
            'nombre'          => 'Refresco Natural',
            'descripcion'     => 'Bebida sin receta registrada en bodega',
            'precio_unitario' => 1.00,
            'disponible'      => true,
        ]);

        // Recetas estándar
        // Hamburguesa: 0.25 lb carne, 1 unidad pan, 0.10 lb tomate
        Receta::create([
            'platillo_id'          => $this->hamburguesa->id,
            'insumo_id'            => $this->carne->id,
            'cantidad_por_porcion' => 0.25,
        ]);

        Receta::create([
            'platillo_id'          => $this->hamburguesa->id,
            'insumo_id'            => $this->pan->id,
            'cantidad_por_porcion' => 1.00,
        ]);

        Receta::create([
            'platillo_id'          => $this->hamburguesa->id,
            'insumo_id'            => $this->tomate->id,
            'cantidad_por_porcion' => 0.10,
        ]);

        // Ensalada: 0.35 lb tomate
        Receta::create([
            'platillo_id'          => $this->ensalada->id,
            'insumo_id'            => $this->tomate->id,
            'cantidad_por_porcion' => 0.35,
        ]);
    }

    /**
     * Prueba que el cálculo multiplica la porción base por las unidades vendidas de un platillo.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function test_calcula_porciones_correctamente_para_un_platillo(): void
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->usuario->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 14.00,
        ]);

        // 4 hamburguesas vendidas
        $comanda->detalles()->create([
            'platillo_id'     => $this->hamburguesa->id,
            'cantidad'        => 4,
            'precio_unitario' => 3.50,
        ]);

        $consumos = $this->service->calcularPorcionesPorComanda($comanda);

        $this->assertCount(3, $consumos);

        $consumosPorId = collect($consumos)->keyBy('insumo_id');

        // Carne: 0.25 * 4 = 1.00 lb
        $this->assertEquals(1.00, $consumosPorId[$this->carne->id]['total_a_descontar']);
        $this->assertEquals('lb', $consumosPorId[$this->carne->id]['unidad_medida']);

        // Pan: 1.00 * 4 = 4.00 unidades
        $this->assertEquals(4.00, $consumosPorId[$this->pan->id]['total_a_descontar']);
        $this->assertEquals('unidad', $consumosPorId[$this->pan->id]['unidad_medida']);

        // Tomate: 0.10 * 4 = 0.40 lb
        $this->assertEquals(0.40, $consumosPorId[$this->tomate->id]['total_a_descontar']);
        $this->assertEquals('lb', $consumosPorId[$this->tomate->id]['unidad_medida']);
    }

    /**
     * Prueba que consolida las cantidades cuando varios platillos consumen el mismo insumo.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function test_consolida_insumos_compartidos_entre_multiples_platillos(): void
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->usuario->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 11.00,
        ]);

        // 2 hamburguesas (2 * 0.10 lb tomate = 0.20 lb)
        $comanda->detalles()->create([
            'platillo_id'     => $this->hamburguesa->id,
            'cantidad'        => 2,
            'precio_unitario' => 3.50,
        ]);

        // 2 ensaladas (2 * 0.35 lb tomate = 0.70 lb)
        $comanda->detalles()->create([
            'platillo_id'     => $this->ensalada->id,
            'cantidad'        => 2,
            'precio_unitario' => 2.00,
        ]);

        $consumos = $this->service->calcularPorcionesPorComanda($comanda);
        $consumosPorId = collect($consumos)->keyBy('insumo_id');

        // Tomate total esperado = 0.20 + 0.70 = 0.90 lb
        $this->assertArrayHasKey($this->tomate->id, $consumosPorId);
        $this->assertEquals(0.90, $consumosPorId[$this->tomate->id]['total_a_descontar']);

        // Verificar el desglose por platillo
        $desgloseTomate = $consumosPorId[$this->tomate->id]['desglose'];
        $this->assertCount(2, $desgloseTomate);

        $this->assertEquals('Hamburguesa Especial', $desgloseTomate[0]['platillo_nombre']);
        $this->assertEquals(2, $desgloseTomate[0]['unidades_vendidas']);
        $this->assertEquals(0.20, $desgloseTomate[0]['subtotal']);

        $this->assertEquals('Ensalada Mixta', $desgloseTomate[1]['platillo_nombre']);
        $this->assertEquals(2, $desgloseTomate[1]['unidades_vendidas']);
        $this->assertEquals(0.70, $desgloseTomate[1]['subtotal']);
    }

    /**
     * Prueba que los platillos sin receta no generan errores ni consumos vacíos.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function test_ignora_platillos_sin_receta_registrada(): void
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->usuario->id,
            'canal'      => 'para_llevar',
            'estado'     => 'pendiente',
            'subtotal'   => 3.00,
        ]);

        // Refresco no tiene recetas vinculadas
        $comanda->detalles()->create([
            'platillo_id'     => $this->refresco->id,
            'cantidad'        => 3,
            'precio_unitario' => 1.00,
        ]);

        $consumos = $this->service->calcularPorcionesPorComanda($comanda);

        $this->assertEmpty($consumos);
    }

    /**
     * Prueba el método calcularPorcionesPorItems con lista directa de platillos y cantidades.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function test_calcula_porciones_por_items_directos(): void
    {
        $items = [
            ['platillo_id' => $this->hamburguesa->id, 'cantidad' => 10],
        ];

        $consumos = $this->service->calcularPorcionesPorItems($items);
        $consumosPorId = collect($consumos)->keyBy('insumo_id');

        // Carne: 0.25 * 10 = 2.50 lb
        $this->assertEquals(2.50, $consumosPorId[$this->carne->id]['total_a_descontar']);
        // Pan: 1.00 * 10 = 10.00 unidades
        $this->assertEquals(10.00, $consumosPorId[$this->pan->id]['total_a_descontar']);
        // Tomate: 0.10 * 10 = 1.00 lb
        $this->assertEquals(1.00, $consumosPorId[$this->tomate->id]['total_a_descontar']);
    }

    /**
     * Prueba que descuenta correctamente las existencias de la bodega para una comanda (CC-49).
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function test_descuenta_stock_en_bodega_correctamente_por_comanda(): void
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->usuario->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 14.00,
        ]);

        // 4 hamburguesas (1 lb carne, 4 panes, 0.40 lb tomate)
        $comanda->detalles()->create([
            'platillo_id'     => $this->hamburguesa->id,
            'cantidad'        => 4,
            'precio_unitario' => 3.50,
        ]);

        $resultado = $this->service->descontarStockPorComanda($comanda);

        $this->assertCount(3, $resultado);

        // Verificar persistencia en base de datos
        // Carne: 50.00 - 1.00 = 49.00
        $this->assertEquals(49.00, (float) $this->carne->fresh()->stock_actual);
        // Pan: 100.00 - 4.00 = 96.00
        $this->assertEquals(96.00, (float) $this->pan->fresh()->stock_actual);
        // Tomate: 30.00 - 0.40 = 29.60
        $this->assertEquals(29.60, (float) $this->tomate->fresh()->stock_actual);

        // Verificar el retorno del método
        $resultadoPorId = collect($resultado)->keyBy('insumo_id');

        $this->assertEquals(50.00, $resultadoPorId[$this->carne->id]['stock_anterior']);
        $this->assertEquals(1.00, $resultadoPorId[$this->carne->id]['cantidad_descontada']);
        $this->assertEquals(49.00, $resultadoPorId[$this->carne->id]['stock_actual']);
        $this->assertFalse($resultadoPorId[$this->carne->id]['alerta_stock_bajo']);
    }

    /**
     * Prueba que detecta alerta de stock bajo cuando el stock resultante alcanza o baja del mínimo (CC-49).
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function test_activa_alerta_cuando_stock_queda_menor_o_igual_al_minimo(): void
    {
        // Forzar stock bajo en tomate: stock 3.20, mínimo 3.00
        $this->tomate->update([
            'stock_actual' => 3.20,
            'stock_minimo' => 3.00,
        ]);

        $comanda = Comanda::create([
            'usuario_id' => $this->usuario->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 2.00,
        ]);

        // 1 ensalada consume 0.35 lb de tomate -> stock queda en 2.85 (menor al mínimo de 3.00)
        $comanda->detalles()->create([
            'platillo_id'     => $this->ensalada->id,
            'cantidad'        => 1,
            'precio_unitario' => 2.00,
        ]);

        $resultado = $this->service->descontarStockPorComanda($comanda);
        $resultadoTomate = collect($resultado)->firstWhere('insumo_id', $this->tomate->id);

        $this->assertNotNull($resultadoTomate);
        $this->assertEquals(2.85, $resultadoTomate['stock_actual']);
        $this->assertTrue($resultadoTomate['alerta_stock_bajo']);
        $this->assertEquals(2.85, (float) $this->tomate->fresh()->stock_actual);
    }

    /**
     * Prueba que descontar por comanda sin recetas no altera stock en BD y retorna vacío (CC-49).
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function test_descontar_comanda_sin_recetas_retorna_vacio(): void
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->usuario->id,
            'canal'      => 'para_llevar',
            'estado'     => 'pendiente',
            'subtotal'   => 2.00,
        ]);

        $comanda->detalles()->create([
            'platillo_id'     => $this->refresco->id,
            'cantidad'        => 2,
            'precio_unitario' => 1.00,
        ]);

        $resultado = $this->service->descontarStockPorComanda($comanda);

        $this->assertEmpty($resultado);
        // Ningún insumo debe haber cambiado
        $this->assertEquals(50.00, (float) $this->carne->fresh()->stock_actual);
        $this->assertEquals(100.00, (float) $this->pan->fresh()->stock_actual);
        $this->assertEquals(30.00, (float) $this->tomate->fresh()->stock_actual);
    }

    /**
     * Prueba que descuenta existencias mediante lista directa de ítems (CC-49).
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @return void
     */
    public function test_descuenta_stock_por_items_directos(): void
    {
        $items = [
            ['platillo_id' => $this->hamburguesa->id, 'cantidad' => 2],
        ];

        $resultado = $this->service->descontarStockPorItems($items);

        $this->assertCount(3, $resultado);
        // 2 hamburguesas = 0.50 lb carne, 2 panes, 0.20 lb tomate
        $this->assertEquals(49.50, (float) $this->carne->fresh()->stock_actual);
        $this->assertEquals(98.00, (float) $this->pan->fresh()->stock_actual);
        $this->assertEquals(29.80, (float) $this->tomate->fresh()->stock_actual);
    }
}
