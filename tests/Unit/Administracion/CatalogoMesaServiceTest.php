<?php

namespace Tests\Unit\Administracion;

use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\User;
use App\Services\MesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas unitarias del servicio del catálogo de mesas (Cuadrante 1 – CC-143).
 *
 * Caja negra: 4 altas de mesa con distintos números y capacidades.
 * Caja blanca: 3 ejecuciones sobre borrado físico, desactivación por historial y bloqueo por comanda abierta.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Administración – US-ADM-03 / CC-107 / CC-143
 */
class CatalogoMesaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MesaService $servicio;

    private User $mesero;

    /**
     * Resuelve el servicio y crea al usuario que registra comandas en las mesas.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-143
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = new MesaService;
        $this->mesero = User::factory()->create();
    }

    /**
     * Casos de caja negra: número y capacidad de la mesa.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-143
     *
     * @return array<string, array{int, ?int}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-mes-01 mesa de cuatro' => [1, 4],
            'cn-mes-02 sin capacidad' => [12, null],
            'cn-mes-03 capacidad máxima' => [3, 255],
            'cn-mes-04 capacidad mínima' => [99, 1],
        ];
    }

    /**
     * Verifica que toda mesa nueva quede libre y activa con el número y capacidad indicados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-143
     *
     * @param  int  $numero  Número de la mesa.
     * @param  int|null  $capacidad  Capacidad de comensales.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_crea_mesa_libre_y_activa(int $numero, ?int $capacidad): void
    {
        $mesa = $this->servicio->crear(['numero' => $numero, 'capacidad' => $capacidad]);

        $this->assertSame(
            [$numero, $capacidad, 'libre', true],
            [$mesa->numero, $mesa->capacidad, $mesa->estado, $mesa->activa]
        );
    }

    /**
     * CB-MES-01: rama sin comandas; la mesa se borra físicamente y el servicio devuelve null.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_mesa_sin_comandas_se_borra(): void
    {
        $mesa = $this->crearMesa(5);

        $this->assertNull($this->servicio->eliminar($mesa));
        $this->assertDatabaseMissing('mesas', ['id' => $mesa->id]);
    }

    /**
     * CB-MES-02: rama de historial; una mesa con comandas cerradas se desactiva en lugar de borrarse.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_mesa_con_historial_se_desactiva(): void
    {
        $mesa = $this->crearMesa(6);
        $this->crearComanda($mesa, 'pagada');

        $resultado = $this->servicio->eliminar($mesa);

        $this->assertFalse($resultado->activa);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'activa' => false]);
    }

    /**
     * CB-MES-03: rama de comanda abierta; no se puede desactivar una mesa en atención.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_mesa_con_comanda_abierta_no_se_desactiva(): void
    {
        $mesa = $this->crearMesa(8);
        $this->crearComanda($mesa, 'en_preparacion');

        try {
            $this->servicio->alternarActivo($mesa);
            $this->fail('Se esperaba ValidationException por comanda abierta.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('mesa', $excepcion->errors());
        }

        $this->assertTrue($mesa->fresh()->activa);
    }

    /**
     * Crea una mesa libre y activa.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-143
     *
     * @param  int  $numero  Número de la mesa.
     * @return Mesa Mesa creada.
     */
    private function crearMesa(int $numero): Mesa
    {
        return Mesa::create(['numero' => $numero, 'capacidad' => 4, 'estado' => 'libre', 'activa' => true]);
    }

    /**
     * Registra una comanda de mesa en el estado indicado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Administración – US-ADM-03 / CC-143
     *
     * @param  Mesa  $mesa  Mesa de la comanda.
     * @param  string  $estado  Estado de la comanda.
     * @return void
     */
    private function crearComanda(Mesa $mesa, string $estado): void
    {
        Comanda::create([
            'usuario_id' => $this->mesero->id,
            'mesa_id' => $mesa->id,
            'canal' => 'mesa',
            'estado' => $estado,
            'subtotal' => 4.00,
        ]);
    }
}
