<?php

namespace Tests\Unit\Inventario;

use App\Models\Comanda;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Platillo;
use App\Models\Receta;
use App\Models\User;
use App\Services\DeduccionInventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas unitarias de la deducción automática de stock por receta estándar (Cuadrante 1 – CC-141).
 *
 * Caja negra: 4 ventas con distintas porciones y unidades; se verifica el saldo final de bodega.
 * Caja blanca: 3 ejecuciones sobre las ramas de platillo sin receta, doble descuento y consolidación.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Inventario – RF-INV-003 / US-INV-01 / CC-141
 */
class DeduccionStockTest extends TestCase
{
    use RefreshDatabase;

    private DeduccionInventarioService $servicio;

    private User $cajero;

    /**
     * Resuelve el servicio desde el contenedor y crea el usuario que figura en los movimientos.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(DeduccionInventarioService::class);
        $this->cajero = User::factory()->create();
    }

    /**
     * Casos de caja negra: porción por platillo, unidad de consumo, unidad de bodega,
     * stock inicial, unidades vendidas y stock final esperado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @return array<string, array{float, string, string, float, int, string}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-ded-01 gramos sobre bodega en kg' => [200, 'g', 'kg', 5, 3, '4.400000'],
            'cn-ded-02 unidades sobre bodega en unidades' => [1, 'unidad', 'unidad', 100, 4, '96.000000'],
            'cn-ded-03 mililitros sobre bodega en litros' => [250, 'ml', 'lt', 2, 2, '1.500000'],
            'cn-ded-04 onzas sobre bodega en libras' => [4, 'onza', 'libra', 10, 2, '9.500000'],
        ];
    }

    /**
     * Verifica que al pagar la comanda se reste porción × unidades vendidas, convertida a la unidad de bodega.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @param  float  $porcion  Cantidad del insumo por platillo, en la unidad de consumo.
     * @param  string  $unidadConsumo  Unidad en la que la receta expresa la porción.
     * @param  string  $unidadBodega  Unidad en la que se almacena el insumo.
     * @param  float  $stockInicial  Existencia antes de la venta.
     * @param  int  $vendidas  Unidades vendidas del platillo.
     * @param  string  $stockEsperado  Existencia esperada con seis decimales.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_descuenta_porcion_por_unidades_vendidas(
        float $porcion,
        string $unidadConsumo,
        string $unidadBodega,
        float $stockInicial,
        int $vendidas,
        string $stockEsperado
    ): void {
        $insumo = $this->crearInsumo('Insumo de prueba', $unidadBodega, $stockInicial);
        $platillo = $this->crearPlatilloConReceta('Platillo de prueba', [[$insumo, $porcion, $unidadConsumo]]);
        $comanda = $this->crearComandaPagada([[$platillo, $vendidas]]);

        $resultado = $this->servicio->descontarPorComanda($comanda, $this->cajero->id);

        $this->assertCount(1, $resultado);
        $this->assertSame($stockEsperado, $insumo->fresh()->stock_actual);
        $this->assertSame(1, MovimientoInventario::where('comanda_id', $comanda->id)
            ->where('tipo', MovimientoInventario::TIPO_VENTA)->count());
    }

    /**
     * CB-DED-01: rama de consumos vacíos; un platillo sin receta no descuenta ni registra movimientos.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @return void
     */
    public function test_caja_blanca_platillo_sin_receta_no_descuenta(): void
    {
        $platillo = Platillo::create(['nombre' => 'Refresco sin receta', 'precio_unitario' => 1.00]);
        $comanda = $this->crearComandaPagada([[$platillo, 2]]);

        $this->assertSame([], $this->servicio->descontarPorComanda($comanda, $this->cajero->id));
        $this->assertSame(0, MovimientoInventario::count());
    }

    /**
     * CB-DED-02: rama de idempotencia; si la comanda ya tiene movimiento de venta, no descuenta otra vez.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @return void
     */
    public function test_caja_blanca_segundo_descuento_de_la_misma_comanda_se_ignora(): void
    {
        $insumo = $this->crearInsumo('Arroz', 'kg', 10);
        $platillo = $this->crearPlatilloConReceta('Casamiento', [[$insumo, 100, 'g']]);
        $comanda = $this->crearComandaPagada([[$platillo, 5]]);

        $this->servicio->descontarPorComanda($comanda, $this->cajero->id);
        $segundo = $this->servicio->descontarPorComanda($comanda, $this->cajero->id);

        $this->assertSame([], $segundo);
        $this->assertSame('9.500000', $insumo->fresh()->stock_actual);
        $this->assertSame(1, MovimientoInventario::where('comanda_id', $comanda->id)->count());
    }

    /**
     * CB-DED-03: rama de consolidación; el mismo insumo en dos platillos genera un solo movimiento
     * con la suma de ambos consumos, y se marca alerta cuando el saldo queda en o bajo el mínimo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / US-INV-03 / CC-141
     *
     * @return void
     */
    public function test_caja_blanca_insumo_repetido_se_consolida_y_alerta_stock_bajo(): void
    {
        $queso = $this->crearInsumo('Queso', 'kg', 1, 0.5);
        $pupusa = $this->crearPlatilloConReceta('Pupusa de queso', [[$queso, 50, 'g']]);
        $quesadilla = $this->crearPlatilloConReceta('Quesadilla', [[$queso, 100, 'g']]);
        $comanda = $this->crearComandaPagada([[$pupusa, 4], [$quesadilla, 3]]);

        $resultado = $this->servicio->descontarPorComanda($comanda, $this->cajero->id);

        $this->assertCount(1, $resultado);
        $this->assertSame(0.5, $resultado[0]['cantidad_descontada']);
        $this->assertTrue($resultado[0]['alerta_stock_bajo']);
        $this->assertSame('0.500000', $queso->fresh()->stock_actual);
        $this->assertSame(1, MovimientoInventario::where('insumo_id', $queso->id)->count());
    }

    /**
     * Registra un insumo de bodega con su existencia y mínimo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @param  string  $nombre  Nombre del insumo.
     * @param  string  $unidad  Unidad de almacenamiento.
     * @param  float  $stock  Existencia inicial.
     * @param  float  $stockMinimo  Existencia mínima para alerta.
     * @return Insumo Insumo creado.
     */
    private function crearInsumo(string $nombre, string $unidad, float $stock, float $stockMinimo = 0): Insumo
    {
        return Insumo::create([
            'nombre' => $nombre,
            'unidad_medida' => $unidad,
            'stock_actual' => $stock,
            'stock_minimo' => $stockMinimo,
        ]);
    }

    /**
     * Crea un platillo y le vincula los renglones de receta indicados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @param  string  $nombre  Nombre del platillo.
     * @param  array<int, array{Insumo, float, string}>  $renglones  Insumo, porción y unidad de consumo.
     * @return Platillo Platillo con receta.
     */
    private function crearPlatilloConReceta(string $nombre, array $renglones): Platillo
    {
        $platillo = Platillo::create(['nombre' => $nombre, 'precio_unitario' => 2.50]);

        foreach ($renglones as [$insumo, $porcion, $unidadConsumo]) {
            Receta::create([
                'platillo_id' => $platillo->id,
                'insumo_id' => $insumo->id,
                'cantidad_por_porcion' => $porcion,
                'unidad_consumo' => $unidadConsumo,
            ]);
        }

        return $platillo;
    }

    /**
     * Crea una comanda para llevar ya pagada con los platillos y cantidades indicados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Inventario – RF-INV-003 / CC-141
     *
     * @param  array<int, array{Platillo, int}>  $items  Platillo y unidades vendidas.
     * @return Comanda Comanda con sus detalles.
     */
    private function crearComandaPagada(array $items): Comanda
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->cajero->id,
            'canal' => 'para_llevar',
            'estado' => 'pagada',
            'subtotal' => 0,
        ]);

        foreach ($items as [$platillo, $cantidad]) {
            $comanda->detalles()->create([
                'platillo_id' => $platillo->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $platillo->precio_unitario,
            ]);
        }

        return $comanda;
    }
}
