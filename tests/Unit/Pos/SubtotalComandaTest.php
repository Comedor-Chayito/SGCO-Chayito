<?php

namespace Tests\Unit\Pos;

use App\Http\Requests\RegistrarComandaRequest;
use App\Models\Platillo;
use App\Models\User;
use App\Services\ComandaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas unitarias del subtotal de comanda en el punto de venta (Cuadrante 1 – CC-142).
 *
 * El subtotal es la suma de cantidad × precio vigente del platillo, redondeada a dos decimales.
 * Caja negra: 4 comandas con distintas combinaciones de ítems.
 * Caja blanca: 3 ejecuciones sobre platillo no disponible, precio cambiado y monto máximo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-27
 * @módulo POS – RF-POS-001 / US-POS-01 / CC-142
 */
class SubtotalComandaTest extends TestCase
{
    use RefreshDatabase;

    private ComandaService $servicio;

    private User $mesero;

    /**
     * Resuelve el servicio y crea al mesero que registra las comandas.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-001 / CC-142
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = app(ComandaService::class);
        $this->mesero = User::factory()->create();
    }

    /**
     * Casos de caja negra: lista de [precio unitario, cantidad] y subtotal esperado.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-001 / CC-142
     *
     * @return array<string, array{array<int, array{float, int}>, string}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-sub-01 un ítem con dos unidades' => [[[2.50, 2]], '5.00'],
            'cn-sub-02 tres ítems distintos' => [[[3.75, 1], [1.25, 2], [0.50, 3]], '7.75'],
            'cn-sub-03 un ítem con cantidad alta' => [[[1.10, 15]], '16.50'],
            'cn-sub-04 centavos acumulados' => [[[0.10, 3], [0.20, 1]], '0.50'],
        ];
    }

    /**
     * Verifica que el subtotal guardado en la comanda sea la suma de cantidad × precio.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-001 / CC-142
     *
     * @param  array<int, array{float, int}> $lineas            Precio unitario y cantidad por ítem.
     * @param  string                         $subtotalEsperado Subtotal esperado con dos decimales.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_calcula_subtotal_de_la_comanda(array $lineas, string $subtotalEsperado): void
    {
        $items = [];

        foreach ($lineas as $indice => [$precio, $cantidad]) {
            $platillo = Platillo::create(['nombre' => "Platillo {$indice}", 'precio_unitario' => $precio]);
            $items[] = ['platillo_id' => $platillo->id, 'cantidad' => $cantidad];
        }

        $comanda = $this->servicio->registrar($this->solicitud($items), $this->mesero->id);

        $this->assertSame($subtotalEsperado, $comanda->subtotal);
        $this->assertCount(count($lineas), $comanda->detalles);
    }

    /**
     * CB-SUB-01: rama de platillo no disponible; se rechaza y no se registra la comanda.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-001 / CC-142
     *
     * @return void
     */
    public function test_caja_blanca_platillo_no_disponible_se_rechaza(): void
    {
        $platillo = Platillo::create(['nombre' => 'Sopa', 'precio_unitario' => 3.00, 'disponible' => false]);

        $this->assertValidacionFalla(
            [['platillo_id' => $platillo->id, 'cantidad' => 1]],
            'items.0.platillo_id'
        );
    }

    /**
     * CB-SUB-02: rama de precio esperado; si el precio que vio el mesero ya no es el vigente, se rechaza.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-001 / CC-142
     *
     * @return void
     */
    public function test_caja_blanca_precio_esperado_distinto_se_rechaza(): void
    {
        $platillo = Platillo::create(['nombre' => 'Pollo', 'precio_unitario' => 4.25]);

        $this->assertValidacionFalla(
            [['platillo_id' => $platillo->id, 'cantidad' => 1, 'precio_esperado' => 4.00]],
            'items.0.precio_esperado'
        );
    }

    /**
     * CB-SUB-03: rama de monto máximo; un subtotal que no cabe en DECIMAL(10,2) se rechaza.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-001 / CC-142
     *
     * @return void
     */
    public function test_caja_blanca_subtotal_sobre_el_maximo_se_rechaza(): void
    {
        $platillo = Platillo::create(['nombre' => 'Banquete', 'precio_unitario' => 99999999.99]);

        $this->assertValidacionFalla([['platillo_id' => $platillo->id, 'cantidad' => 2]], 'items');
    }

    /**
     * Construye una solicitud de comanda para llevar cuyos datos validados son los ítems indicados.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-001 / CC-142
     *
     * @param  array<int, array<string, mixed>> $items Ítems de la comanda.
     * @return RegistrarComandaRequest Solicitud simulada.
     */
    private function solicitud(array $items): RegistrarComandaRequest
    {
        $solicitud = Mockery::mock(RegistrarComandaRequest::class);
        $solicitud->shouldReceive('validated')->andReturn([
            'canal' => 'para_llevar',
            'mesa_id' => null,
            'items' => $items,
        ]);

        return $solicitud;
    }

    /**
     * Afirma que registrar los ítems lanza ValidationException en el campo indicado y no crea comanda.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo POS – RF-POS-001 / CC-142
     *
     * @param  array<int, array<string, mixed>> $items Ítems de la comanda.
     * @param  string                           $campo Campo que debe reportar el error.
     * @return void
     */
    private function assertValidacionFalla(array $items, string $campo): void
    {
        try {
            $this->servicio->registrar($this->solicitud($items), $this->mesero->id);
            $this->fail("Se esperaba ValidationException en {$campo}.");
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey($campo, $excepcion->errors());
        }

        $this->assertDatabaseCount('comandas', 0);
    }
}
