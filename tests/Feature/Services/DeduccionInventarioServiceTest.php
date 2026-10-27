<?php

namespace Tests\Feature\Services;

use App\Models\Comanda;
use App\Models\Insumo;
use App\Models\Platillo;
use App\Models\Receta;
use App\Models\User;
use App\Services\DeduccionInventarioService;
use App\Services\RecetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del cálculo y descuento unificado de inventario por receta.
 *
 * @autor  Alexis
 *
 * @fecha  2026-10-22
 *
 * @módulo Inventario – RF-INV-003 / CC-47 / CC-48 / CC-49
 */
class DeduccionInventarioServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeduccionInventarioService $service;

    private User $usuario;

    private Insumo $carne;

    private Insumo $pan;

    private Insumo $tomate;

    private Platillo $hamburguesa;

    private Platillo $ensalada;

    private Platillo $refresco;

    /**
     * Prepara recetas con unidades de consumo distintas a las de bodega.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-003 / CC-47 / CC-48 / CC-49
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DeduccionInventarioService(new RecetaService);
        $this->usuario = User::factory()->create();

        $this->carne = Insumo::create([
            'nombre' => 'Carne molida',
            'unidad_medida' => 'kg',
            'stock_actual' => 5,
            'stock_minimo' => 1,
        ]);
        $this->pan = Insumo::create([
            'nombre' => 'Pan para hamburguesa',
            'unidad_medida' => 'unidad',
            'stock_actual' => 100,
            'stock_minimo' => 10,
        ]);
        $this->tomate = Insumo::create([
            'nombre' => 'Tomate',
            'unidad_medida' => 'kg',
            'stock_actual' => 3,
            'stock_minimo' => 1,
        ]);

        $this->hamburguesa = Platillo::create([
            'nombre' => 'Hamburguesa especial',
            'descripcion' => 'Hamburguesa con carne, pan y tomate.',
            'precio_unitario' => 3.50,
            'disponible' => true,
        ]);
        $this->ensalada = Platillo::create([
            'nombre' => 'Ensalada mixta',
            'descripcion' => 'Ensalada con tomate.',
            'precio_unitario' => 2,
            'disponible' => true,
        ]);
        $this->refresco = Platillo::create([
            'nombre' => 'Refresco natural',
            'descripcion' => 'Bebida sin receta.',
            'precio_unitario' => 1,
            'disponible' => true,
        ]);

        Receta::create([
            'platillo_id' => $this->hamburguesa->id,
            'insumo_id' => $this->carne->id,
            'cantidad_por_porcion' => 250,
            'unidad_consumo' => 'g',
        ]);
        Receta::create([
            'platillo_id' => $this->hamburguesa->id,
            'insumo_id' => $this->pan->id,
            'cantidad_por_porcion' => 1,
            'unidad_consumo' => 'unidad',
        ]);
        Receta::create([
            'platillo_id' => $this->hamburguesa->id,
            'insumo_id' => $this->tomate->id,
            'cantidad_por_porcion' => 100,
            'unidad_consumo' => 'g',
        ]);
        Receta::create([
            'platillo_id' => $this->ensalada->id,
            'insumo_id' => $this->tomate->id,
            'cantidad_por_porcion' => 350,
            'unidad_consumo' => 'g',
        ]);
    }

    /**
     * Verifica conversión y consolidación de un insumo usado por varios platillos.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-003 / CC-47 / CC-48
     */
    public function test_calcula_consumos_convertidos_y_consolida_insumos(): void
    {
        $comanda = $this->crearComanda();
        $comanda->detalles()->create([
            'platillo_id' => $this->hamburguesa->id,
            'cantidad' => 2,
            'precio_unitario' => 3.50,
        ]);
        $comanda->detalles()->create([
            'platillo_id' => $this->ensalada->id,
            'cantidad' => 2,
            'precio_unitario' => 2,
        ]);

        $consumos = collect($this->service->calcularConsumosPorComanda($comanda))
            ->keyBy('insumo_id');

        $this->assertCount(3, $consumos);
        $this->assertSame(0.5, $consumos[$this->carne->id]['total_a_descontar']);
        $this->assertSame(2.0, $consumos[$this->pan->id]['total_a_descontar']);
        $this->assertSame(0.9, $consumos[$this->tomate->id]['total_a_descontar']);
        $this->assertCount(2, $consumos[$this->tomate->id]['desglose']);
        $this->assertSame(0.1, $consumos[$this->tomate->id]['desglose'][0]['porcion_en_unidad_bodega']);
    }

    /**
     * Verifica que el descuento persiste cantidades convertidas y marca stock bajo.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-003 / CC-47 / CC-49
     */
    public function test_descuenta_stock_convertido_y_reporta_alerta(): void
    {
        $this->tomate->update([
            'stock_actual' => 0.45,
            'stock_minimo' => 0.4,
        ]);
        $comanda = $this->crearComanda();
        $comanda->detalles()->create([
            'platillo_id' => $this->hamburguesa->id,
            'cantidad' => 4,
            'precio_unitario' => 3.50,
        ]);

        $descontados = collect($this->service->descontarPorComanda($comanda))->keyBy('insumo_id');

        $this->assertSame(4.0, (float) $this->carne->fresh()->stock_actual);
        $this->assertSame(96.0, (float) $this->pan->fresh()->stock_actual);
        $this->assertSame(0.05, (float) $this->tomate->fresh()->stock_actual);
        $this->assertTrue($descontados[$this->tomate->id]['alerta_stock_bajo']);
        $this->assertSame(0.4, $descontados[$this->tomate->id]['cantidad_descontada']);
    }

    /**
     * Verifica que una comanda sin receta no modifica el inventario.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-003 / CC-47 / CC-48 / CC-49
     */
    public function test_comanda_sin_receta_no_genera_consumos_ni_descuentos(): void
    {
        $comanda = $this->crearComanda();
        $comanda->detalles()->create([
            'platillo_id' => $this->refresco->id,
            'cantidad' => 2,
            'precio_unitario' => 1,
        ]);

        $this->assertSame([], $this->service->calcularConsumosPorComanda($comanda));
        $this->assertSame([], $this->service->descontarPorComanda($comanda));
        $this->assertSame(5.0, (float) $this->carne->fresh()->stock_actual);
        $this->assertSame(100.0, (float) $this->pan->fresh()->stock_actual);
        $this->assertSame(3.0, (float) $this->tomate->fresh()->stock_actual);
    }

    /**
     * Crea una comanda pendiente para los escenarios de deducción de inventario.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-003 / CC-47 / CC-48 / CC-49
     *
     * @return Comanda Comanda de prueba lista para agregar detalles.
     */
    private function crearComanda(): Comanda
    {
        return Comanda::create([
            'usuario_id' => $this->usuario->id,
            'canal' => 'mesa',
            'estado' => 'pendiente',
            'subtotal' => 0,
        ]);
    }
}
