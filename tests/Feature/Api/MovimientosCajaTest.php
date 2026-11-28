<?php

namespace Tests\Feature\Api;

use App\Models\CierreCaja;
use App\Models\Comanda;
use App\Models\RetiroParcial;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\AbreCajaDelDia;
use Tests\TestCase;

/**
 * Pruebas del resumen de movimientos de caja del día (GET /api/movimientos-caja).
 */
class MovimientosCajaTest extends TestCase
{
    use AbreCajaDelDia;
    use RefreshDatabase;

    private User $cajero;

    /**
     * Prepara los roles y un cajero autenticado.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cajero', 'mesero', 'contadora', 'cocinero', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->cajero = User::factory()->create(['name' => 'Cajero Uno']);
        $this->cajero->assignRole('cajero');
        Sanctum::actingAs($this->cajero);
    }

    /**
     * Registra una venta cobrada con el método indicado.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002
     *
     * @param  float  $total  Total de la venta.
     * @param  string  $metodoPago  efectivo, tarjeta o transferencia.
     * @return Venta Venta creada.
     */
    private function crearVenta(float $total, string $metodoPago): Venta
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->cajero->id,
            'canal' => 'para_llevar',
            'estado' => 'pagada',
            'subtotal' => $total,
        ]);

        return Venta::create([
            'comanda_id' => $comanda->id,
            'usuario_id' => $this->cajero->id,
            'total' => $total,
            'monto_recibido' => $total,
            'cambio' => 0,
            'metodo_pago' => $metodoPago,
        ]);
    }

    /**
     * Valida los totales por método, los retiros de cualquier usuario y el efectivo esperado.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return void
     */
    public function test_resumen_del_dia_calcula_totales_y_efectivo_esperado(): void
    {
        $this->abrirCajaDelDia($this->cajero, 50.00);
        $this->crearVenta(10.50, 'efectivo');
        $this->crearVenta(4.25, 'efectivo');
        $this->crearVenta(8.00, 'tarjeta');
        $this->crearVenta(3.00, 'transferencia');

        $admin = User::factory()->create(['name' => 'Admin Dos']);
        RetiroParcial::create(['usuario_id' => $this->cajero->id, 'monto' => 5.00, 'motivo' => 'Caja fuerte']);
        RetiroParcial::create(['usuario_id' => $admin->id, 'monto' => 2.75, 'motivo' => 'Cambio']);

        $ventaAyer = $this->crearVenta(100.00, 'efectivo');
        $ventaAyer->forceFill(['created_at' => now()->subDay()])->save();

        $respuesta = $this->getJson('/api/movimientos-caja');

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.estado', 'abierta')
            ->assertJsonPath('data.fecha', today()->toDateString());

        $totales = $respuesta->json('data.totales');
        $this->assertEquals(50.00, $totales['saldo_inicial']);
        $this->assertEquals(14.75, $totales['ventas_por_metodo']['efectivo']);
        $this->assertEquals(8.00, $totales['ventas_por_metodo']['tarjeta']);
        $this->assertEquals(3.00, $totales['ventas_por_metodo']['transferencia']);
        $this->assertEquals(25.75, $totales['total_ventas']);
        $this->assertSame(4, $totales['cantidad_ventas']);
        $this->assertEquals(7.75, $totales['total_retiros']);
        // 50.00 + 14.75 − 7.75
        $this->assertEquals(57.00, $totales['efectivo_esperado']);

        $movimientos = collect($respuesta->json('data.movimientos'));
        $this->assertSame(1, $movimientos->where('tipo', 'apertura')->count());
        $this->assertSame(4, $movimientos->where('tipo', 'venta')->count());
        $this->assertSame(2, $movimientos->where('tipo', 'retiro')->count());
        $this->assertContains('Admin Dos', $movimientos->pluck('usuario')->all());
    }

    /**
     * Valida que el estado refleja una caja sin abrir y una caja cerrada.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return void
     */
    public function test_estado_refleja_caja_sin_abrir_y_cerrada(): void
    {
        $this->getJson('/api/movimientos-caja')
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'sin_abrir')
            ->assertJsonPath('data.movimientos', []);

        $this->abrirCajaDelDia($this->cajero);
        CierreCaja::create([
            'usuario_id' => $this->cajero->id,
            'fecha' => today()->toDateString(),
            'saldo_inicial' => 0,
            'total_ventas_efectivo' => 0,
            'total_ventas_tarjeta' => 0,
            'total_ventas_transferencia' => 0,
            'total_retiros' => 0,
            'saldo_final' => 0,
            'venta_del_dia' => 0,
            'diferencia' => 0,
        ]);

        $respuesta = $this->getJson('/api/movimientos-caja')->assertJsonPath('data.estado', 'cerrada');
        $this->assertSame(
            ['apertura', 'cierre'],
            collect($respuesta->json('data.movimientos'))->pluck('tipo')->all()
        );
    }

    /**
     * Valida la consulta de un día anterior y el rechazo de fechas futuras o mal formadas.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return void
     */
    public function test_filtra_por_fecha_y_rechaza_fechas_invalidas(): void
    {
        $ventaAyer = $this->crearVenta(9.00, 'efectivo');
        $ventaAyer->forceFill(['created_at' => now()->subDay()])->save();

        $this->getJson('/api/movimientos-caja?fecha='.today()->subDay()->toDateString())
            ->assertStatus(200)
            ->assertJsonPath('data.totales.cantidad_ventas', 1);

        $this->getJson('/api/movimientos-caja?fecha='.today()->addDay()->toDateString())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha']);

        $this->getJson('/api/movimientos-caja?fecha=18-11-2026')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha']);
    }

    /**
     * Valida que sin sesión responde 401 y los roles fuera de caja reciben 403 (CLAUDE.md §9).
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / RF-ADM-008
     *
     * @return void
     */
    public function test_acceso_restringido_a_roles_de_caja(): void
    {
        foreach (['mesero', 'contadora', 'cocinero'] as $rol) {
            $usuario = User::factory()->create();
            $usuario->assignRole($rol);
            Sanctum::actingAs($usuario);

            $this->getJson('/api/movimientos-caja')->assertStatus(403);
        }

        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        Sanctum::actingAs($admin);
        $this->getJson('/api/movimientos-caja')->assertStatus(200);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/movimientos-caja')->assertStatus(401);
    }
}
