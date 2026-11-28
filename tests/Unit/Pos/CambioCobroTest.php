<?php

namespace Tests\Unit\Pos;

use App\Http\Requests\ProcesarCobroRequest;
use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\User;
use App\Services\CobroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AbreCajaDelDia;
use Tests\TestCase;

/**
 * Pruebas unitarias del cambio (vuelto) al cobrar una comanda (Cuadrante 1 – CC-142).
 *
 * Cambio = Monto recibido − Total de la comanda, redondeado a dos decimales.
 * Caja negra: 4 cobros en efectivo con distintos montos.
 * Caja blanca: 3 ejecuciones sobre caja sin abrir, comanda ya pagada y pago con tarjeta.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo POS – RF-POS-002 / US-POS-03 / CC-142
 */
class CambioCobroTest extends TestCase
{
    use AbreCajaDelDia;
    use RefreshDatabase;

    private CobroService $servicio;

    private User $cajero;

    /**
     * Resuelve el servicio y crea al cajero que procesa los cobros.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / CC-142
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(CobroService::class);
        $this->cajero = User::factory()->create();
    }

    /**
     * Casos de caja negra: total de la comanda, monto recibido en efectivo y cambio esperado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / CC-142
     *
     * @return array<string, array{float, float, string}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-cam-01 billete mayor al total' => [6.75, 10.00, '3.25'],
            'cn-cam-02 pago exacto' => [6.75, 6.75, '0.00'],
            'cn-cam-03 billete de veinte' => [13.40, 20.00, '6.60'],
            'cn-cam-04 cambio en centavos' => [0.35, 1.00, '0.65'],
        ];
    }

    /**
     * Verifica que el cobro en efectivo registre la venta con el cambio exacto y marque la comanda pagada.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / CC-142
     *
     * @param  float  $total  Subtotal de la comanda.
     * @param  float  $montoRecibido  Efectivo entregado por el cliente.
     * @param  string  $cambioEsperado  Cambio esperado con dos decimales.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_calcula_el_cambio_en_efectivo(
        float $total,
        float $montoRecibido,
        string $cambioEsperado
    ): void {
        $this->abrirCajaDelDia($this->cajero);
        $comanda = $this->crearComanda($total);

        $venta = $this->servicio->procesarCobro(
            $this->solicitud($comanda->id, 'efectivo', $montoRecibido),
            $this->cajero->id
        );

        $this->assertSame($cambioEsperado, $venta->cambio);
        $this->assertSame(number_format($total, 2, '.', ''), $venta->total);
        $this->assertSame('pagada', $comanda->fresh()->estado);
    }

    /**
     * CB-CAM-01: rama de caja sin abrir; el cobro se rechaza antes de tocar la comanda.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / US-POS-04 / CC-142
     *
     * @return void
     */
    public function test_caja_blanca_sin_apertura_no_se_cobra(): void
    {
        $comanda = $this->crearComanda(5.00);

        $this->assertCobroFalla($comanda, 'caja');
        $this->assertSame('pendiente', $comanda->fresh()->estado);
    }

    /**
     * CB-CAM-02: rama de comanda pagada o cancelada; no se puede cobrar dos veces.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / CC-142
     *
     * @return void
     */
    public function test_caja_blanca_comanda_ya_pagada_no_se_cobra(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $comanda = $this->crearComanda(5.00, 'pagada');

        $this->assertCobroFalla($comanda, 'comanda_id');
    }

    /**
     * CB-CAM-03: rama de método no efectivo; con tarjeta se cobra el total exacto, el cambio es cero
     * y se libera la mesa al no quedar otras comandas activas.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / CC-142
     *
     * @return void
     */
    public function test_caja_blanca_tarjeta_cobra_total_exacto_y_libera_mesa(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $mesa = Mesa::create(['numero' => 7, 'estado' => 'ocupada', 'activa' => true]);
        $comanda = $this->crearComanda(12.30, 'lista', $mesa->id);

        $venta = $this->servicio->procesarCobro(
            $this->solicitud($comanda->id, 'tarjeta', 50.00),
            $this->cajero->id
        );

        $this->assertSame('12.30', $venta->monto_recibido);
        $this->assertSame('0.00', $venta->cambio);
        $this->assertSame('libre', $mesa->fresh()->estado);
    }

    /**
     * Crea una comanda para llevar (o de mesa) con el subtotal y estado indicados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / CC-142
     *
     * @param  float  $subtotal  Total a cobrar.
     * @param  string  $estado  Estado inicial de la comanda.
     * @param  int|null  $mesaId  Mesa asociada, si la hay.
     * @return Comanda Comanda creada.
     */
    private function crearComanda(float $subtotal, string $estado = 'pendiente', ?int $mesaId = null): Comanda
    {
        return Comanda::create([
            'usuario_id' => $this->cajero->id,
            'mesa_id' => $mesaId,
            'canal' => $mesaId ? 'mesa' : 'para_llevar',
            'estado' => $estado,
            'subtotal' => $subtotal,
        ]);
    }

    /**
     * Construye una solicitud de cobro cuyos datos validados son los indicados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / CC-142
     *
     * @param  int  $comandaId  Comanda a cobrar.
     * @param  string  $metodoPago  efectivo, tarjeta o transferencia.
     * @param  float  $montoRecibido  Monto entregado.
     * @return ProcesarCobroRequest Solicitud simulada.
     */
    private function solicitud(int $comandaId, string $metodoPago, float $montoRecibido): ProcesarCobroRequest
    {
        $solicitud = Mockery::mock(ProcesarCobroRequest::class);
        $solicitud->shouldReceive('validated')->andReturn([
            'comanda_id' => $comandaId,
            'metodo_pago' => $metodoPago,
            'monto_recibido' => $montoRecibido,
        ]);

        return $solicitud;
    }

    /**
     * Afirma que cobrar la comanda lanza ValidationException en el campo indicado y no crea venta.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo POS – RF-POS-002 / CC-142
     *
     * @param  Comanda  $comanda  Comanda a cobrar.
     * @param  string  $campo  Campo que debe reportar el error.
     * @return void
     */
    private function assertCobroFalla(Comanda $comanda, string $campo): void
    {
        try {
            $this->servicio->procesarCobro($this->solicitud($comanda->id, 'efectivo', 10.00), $this->cajero->id);
            $this->fail("Se esperaba ValidationException en {$campo}.");
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey($campo, $excepcion->errors());
        }

        $this->assertDatabaseCount('ventas', 0);
    }
}
