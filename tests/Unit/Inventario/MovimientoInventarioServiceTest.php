<?php

namespace Tests\Unit\Inventario;

use App\Exceptions\ValidacionMovimientoInventarioException;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\User;
use App\Services\MovimientoInventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas unitarias del registro de movimientos de inventario (Cuadrante 1 – CC-143).
 *
 * Caja negra: 4 movimientos, uno por tipo (entrada, ajuste, venta, requisición).
 * Caja blanca: 3 ejecuciones sobre signo inválido, ajuste sin motivo y saldo negativo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-27
 * @módulo Inventario – RF-INV-003 / US-INV-04 / CC-113 / CC-143
 */
class MovimientoInventarioServiceTest extends TestCase
{
    use RefreshDatabase;

    private MovimientoInventarioService $servicio;

    private User $bodeguero;

    /**
     * Resuelve el servicio y crea al responsable de los movimientos.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-143
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(MovimientoInventarioService::class);
        $this->bodeguero = User::factory()->create();
    }

    /**
     * Casos de caja negra: stock inicial, delta firmado, tipo, motivo y stock resultante esperado.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-143
     *
     * @return array<string, array{float, float, string, ?string, string}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-mov-01 entrada a bodega' => [2, 5, MovimientoInventario::TIPO_ENTRADA, null, '7.000000'],
            'cn-mov-02 ajuste por merma' => [3, -1.5, MovimientoInventario::TIPO_AJUSTE, 'Merma', '1.500000'],
            'cn-mov-03 salida por venta' => [1, -0.25, MovimientoInventario::TIPO_VENTA, null, '0.750000'],
            'cn-mov-04 requisición agota stock' => [10, -10, MovimientoInventario::TIPO_REQUISICION, null, '0.000000'],
        ];
    }

    /**
     * Verifica que el movimiento actualice el saldo y deje un registro con el delta y el saldo resultante.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-143
     *
     * @param  float       $stockInicial  Existencia antes del movimiento.
     * @param  float       $cantidad      Delta firmado.
     * @param  string      $tipo          Tipo de movimiento.
     * @param  string|null $motivo        Motivo (obligatorio en ajustes).
     * @param  string      $stockEsperado Existencia esperada con seis decimales.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_aplica_movimiento_y_lo_registra(
        float $stockInicial,
        float $cantidad,
        string $tipo,
        ?string $motivo,
        string $stockEsperado
    ): void {
        $insumo = $this->crearInsumo($stockInicial);

        $resultado = $this->servicio->aplicar($insumo, $cantidad, $tipo, $this->bodeguero->id, ['motivo' => $motivo]);

        $this->assertSame($stockEsperado, $insumo->fresh()->stock_actual);
        $this->assertSame($stockEsperado, $resultado['movimiento']->stock_resultante);
        $this->assertSame(number_format($cantidad, 6, '.', ''), $resultado['movimiento']->cantidad);
        $this->assertSame($tipo, $resultado['movimiento']->tipo);
    }

    /**
     * CB-MOV-01: rama de signo inválido; una entrada con cantidad negativa se rechaza.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_entrada_negativa_se_rechaza(): void
    {
        $this->assertMovimientoFalla(5, -1, MovimientoInventario::TIPO_ENTRADA, [], 'cantidad');
    }

    /**
     * CB-MOV-02: rama de motivo obligatorio; un ajuste con motivo en blanco se rechaza.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_ajuste_sin_motivo_se_rechaza(): void
    {
        $this->assertMovimientoFalla(5, -1, MovimientoInventario::TIPO_AJUSTE, ['motivo' => '   '], 'motivo');
    }

    /**
     * CB-MOV-03: rama de saldo negativo; una requisición mayor a la existencia se rechaza sin tocar el saldo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_requisicion_que_deja_saldo_negativo_se_rechaza(): void
    {
        $this->assertMovimientoFalla(2, -2.5, MovimientoInventario::TIPO_REQUISICION, [], 'cantidad');
    }

    /**
     * Registra un insumo en kilogramos con la existencia indicada.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-143
     *
     * @param  float $stock Existencia inicial.
     * @return Insumo Insumo creado.
     */
    private function crearInsumo(float $stock): Insumo
    {
        return Insumo::create(['nombre' => 'Frijol', 'unidad_medida' => 'kg', 'stock_actual' => $stock]);
    }

    /**
     * Afirma que el movimiento se rechaza en el campo indicado sin alterar saldo ni historial.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Inventario – RF-INV-003 / CC-143
     *
     * @param  float                $stock       Existencia inicial.
     * @param  float                $cantidad    Delta firmado.
     * @param  string               $tipo        Tipo de movimiento.
     * @param  array<string, mixed> $referencias Referencias del movimiento.
     * @param  string               $campo       Campo que debe reportar el error.
     * @return void
     */
    private function assertMovimientoFalla(
        float $stock,
        float $cantidad,
        string $tipo,
        array $referencias,
        string $campo
    ): void {
        $insumo = $this->crearInsumo($stock);

        try {
            $this->servicio->aplicar($insumo, $cantidad, $tipo, $this->bodeguero->id, $referencias);
            $this->fail("Se esperaba ValidacionMovimientoInventarioException en {$campo}.");
        } catch (ValidacionMovimientoInventarioException $excepcion) {
            $this->assertArrayHasKey($campo, $excepcion->errors());
        }

        $this->assertSame(number_format($stock, 6, '.', ''), $insumo->fresh()->stock_actual);
        $this->assertSame(0, MovimientoInventario::count());
    }
}
