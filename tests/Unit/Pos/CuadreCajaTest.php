<?php

namespace Tests\Unit\Pos;

use App\Models\AperturaCaja;
use App\Models\Comanda;
use App\Models\RetiroParcial;
use App\Models\User;
use App\Models\Venta;
use App\Services\CierreCajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas unitarias del arqueo y cuadre de caja diario (Cuadrante 1 – CC-140).
 *
 * Fórmula: Venta del Día = (Suma de Retiros + Saldo Final) − Saldo Inicial;
 * Diferencia = Venta del Día − Ventas en efectivo (positivo sobrante, negativo faltante).
 *
 * Caja negra: 4 jornadas con cuadre exacto, sobrante, faltante y sin movimientos.
 * Caja blanca: 3 ejecuciones sobre comandas abiertas, métodos no efectivo y movimientos de otro día.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-27
 * @módulo POS – RF-POS-002 / US-POS-04 / CC-140
 */
class CuadreCajaTest extends TestCase
{
    use RefreshDatabase;

    private CierreCajaService $servicio;

    private User $cajero;

    /**
     * Resuelve el servicio y autentica al cajero, que queda como actor en la bitácora del cierre.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-002 / CC-140
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(CierreCajaService::class);
        $this->cajero = User::factory()->create();
        $this->actingAs($this->cajero);
    }

    /**
     * Casos de caja negra: saldo inicial, ventas en efectivo, retiros, saldo final contado,
     * venta del día esperada y diferencia esperada.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-002 / CC-140
     *
     * @return array<string, array{float, array<int, float>, array<int, float>, float, string, string}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-cua-01 cuadre exacto' => [50.00, [60.00, 40.00], [30.00], 120.00, '100.00', '0.00'],
            'cn-cua-02 sobrante en gaveta' => [50.00, [100.00], [], 155.00, '105.00', '5.00'],
            'cn-cua-03 faltante en gaveta' => [20.00, [40.25, 40.25], [10.00], 85.00, '75.00', '-5.50'],
            'cn-cua-04 jornada sin movimientos' => [25.00, [], [], 25.00, '0.00', '0.00'],
        ];
    }

    /**
     * Verifica que el cierre aplique la fórmula de venta del día y calcule la diferencia de arqueo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-002 / CC-140
     *
     * @param  float             $saldoInicial        Saldo registrado en la apertura.
     * @param  array<int, float> $ventasEfectivo      Totales de ventas cobradas en efectivo.
     * @param  array<int, float> $retiros             Montos de retiros parciales.
     * @param  float             $saldoFinal          Efectivo contado al cerrar.
     * @param  string            $ventaDelDiaEsperada Venta del día esperada.
     * @param  string            $diferenciaEsperada  Diferencia esperada.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_calcula_venta_del_dia_y_diferencia(
        float $saldoInicial,
        array $ventasEfectivo,
        array $retiros,
        float $saldoFinal,
        string $ventaDelDiaEsperada,
        string $diferenciaEsperada
    ): void {
        $this->abrirCaja($saldoInicial);

        foreach ($ventasEfectivo as $total) {
            $this->registrarVenta($total, 'efectivo');
        }

        foreach ($retiros as $monto) {
            RetiroParcial::create(['usuario_id' => $this->cajero->id, 'monto' => $monto, 'motivo' => 'Pago']);
        }

        $cierre = $this->servicio->cerrar(
            ['fecha' => today()->toDateString(), 'saldo_final' => $saldoFinal],
            $this->cajero->id
        );

        $this->assertSame($ventaDelDiaEsperada, $cierre->venta_del_dia);
        $this->assertSame($diferenciaEsperada, $cierre->diferencia);
        $this->assertSame(number_format($saldoInicial, 2, '.', ''), $cierre->saldo_inicial);
    }

    /**
     * CB-CUA-01: rama de comandas abiertas; no se puede cerrar con una comanda pendiente y no se guarda cierre.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-002 / CC-140
     *
     * @return void
     */
    public function test_caja_blanca_comanda_abierta_impide_el_cierre(): void
    {
        $this->abrirCaja(10.00);
        $pendiente = Comanda::create([
            'usuario_id' => $this->cajero->id,
            'canal' => 'para_llevar',
            'estado' => 'pendiente',
            'subtotal' => 5.00,
        ]);

        try {
            $this->servicio->cerrar(['fecha' => today()->toDateString(), 'saldo_final' => 10], $this->cajero->id);
            $this->fail('Se esperaba ValidationException por comanda abierta.');
        } catch (ValidationException $excepcion) {
            $this->assertStringContainsString("#{$pendiente->id}", $excepcion->errors()['caja'][0]);
        }

        $this->assertDatabaseCount('cierres_caja', 0);
    }

    /**
     * CB-CUA-02: rama de agrupación por método; tarjeta y transferencia se reportan aparte
     * y no entran en la diferencia de efectivo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-002 / CC-140
     *
     * @return void
     */
    public function test_caja_blanca_tarjeta_y_transferencia_no_afectan_el_arqueo(): void
    {
        $this->abrirCaja(0.00);
        $this->registrarVenta(10.00, 'efectivo');
        $this->registrarVenta(20.00, 'tarjeta');
        $this->registrarVenta(5.00, 'transferencia');

        $cierre = $this->servicio->cerrar(
            ['fecha' => today()->toDateString(), 'saldo_final' => 10.00],
            $this->cajero->id
        );

        $this->assertSame('10.00', $cierre->total_ventas_efectivo);
        $this->assertSame('20.00', $cierre->total_ventas_tarjeta);
        $this->assertSame('5.00', $cierre->total_ventas_transferencia);
        $this->assertSame('0.00', $cierre->diferencia);
    }

    /**
     * CB-CUA-03: rama de filtro por fecha y apertura ausente; ventas y retiros de ayer no se suman
     * y, sin apertura del día, el saldo inicial es cero.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-002 / CC-140
     *
     * @return void
     */
    public function test_caja_blanca_excluye_movimientos_de_otro_dia_sin_apertura(): void
    {
        $this->travel(-1)->days();
        $this->registrarVenta(99.00, 'efectivo');
        RetiroParcial::create(['usuario_id' => $this->cajero->id, 'monto' => 15.00, 'motivo' => 'Pago']);
        $this->travelBack();

        $cierre = $this->servicio->cerrar(
            ['fecha' => today()->toDateString(), 'saldo_final' => 0.00],
            $this->cajero->id
        );

        $this->assertSame('0.00', $cierre->saldo_inicial);
        $this->assertSame('0.00', $cierre->total_ventas_efectivo);
        $this->assertSame('0.00', $cierre->total_retiros);
        $this->assertSame('0.00', $cierre->diferencia);
    }

    /**
     * Registra la apertura de caja de hoy con el saldo inicial indicado.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-002 / CC-140
     *
     * @param  float $saldoInicial Saldo inicial de la gaveta.
     * @return void
     */
    private function abrirCaja(float $saldoInicial): void
    {
        AperturaCaja::create([
            'usuario_id' => $this->cajero->id,
            'fecha' => today()->toDateString(),
            'saldo_inicial' => $saldoInicial,
            'denominaciones' => [],
        ]);
    }

    /**
     * Registra una venta cobrada (con su comanda pagada) por el total y método indicados.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-002 / CC-140
     *
     * @param  float  $total      Total cobrado.
     * @param  string $metodoPago efectivo, tarjeta o transferencia.
     * @return void
     */
    private function registrarVenta(float $total, string $metodoPago): void
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->cajero->id,
            'canal' => 'para_llevar',
            'estado' => 'pagada',
            'subtotal' => $total,
        ]);

        Venta::create([
            'comanda_id' => $comanda->id,
            'usuario_id' => $this->cajero->id,
            'total' => $total,
            'monto_recibido' => $total,
            'cambio' => 0,
            'metodo_pago' => $metodoPago,
        ]);
    }
}
