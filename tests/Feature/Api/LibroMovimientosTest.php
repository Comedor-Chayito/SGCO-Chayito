<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\Compra;
use App\Models\Egreso;
use App\Models\SubcategoriaEgreso;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas del libro de movimientos contables (GET /api/movimientos-contables).
 */
class LibroMovimientosTest extends TestCase
{
    use RefreshDatabase;

    private User $contadora;

    private SubcategoriaEgreso $subcategoria;

    private int $siguienteEgreso = 1;

    /**
     * Prepara los roles, una contadora autenticada y una subcategoría de egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['contadora', 'cajero', 'mesero', 'cocinero', 'socio'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->contadora = User::factory()->create();
        $this->contadora->assignRole('contadora');
        Sanctum::actingAs($this->contadora);

        $this->subcategoria = SubcategoriaEgreso::create(['nombre' => 'Materia prima de prueba']);
    }

    /**
     * Registra una venta cobrada en la fecha indicada.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  float   $total   Total de la venta.
     * @param  string  $fecha   Fecha y hora del cobro.
     * @param  string  $metodo  Método de pago.
     * @return Venta Venta creada.
     */
    private function crearVenta(float $total, string $fecha, string $metodo = 'efectivo'): Venta
    {
        $comanda = Comanda::create([
            'usuario_id' => $this->contadora->id,
            'canal'      => 'para_llevar',
            'estado'     => 'pagada',
            'subtotal'   => $total,
        ]);

        $venta = Venta::create([
            'comanda_id'     => $comanda->id,
            'usuario_id'     => $this->contadora->id,
            'total'          => $total,
            'monto_recibido' => $total,
            'cambio'         => 0,
            'metodo_pago'    => $metodo,
        ]);
        $venta->forceFill(['created_at' => $fecha])->save();

        return $venta;
    }

    /**
     * Registra un egreso ficticio con la fecha de comprobante indicada.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  float   $montoTotal  Monto total con IVA.
     * @param  string  $fecha       Fecha del comprobante (YYYY-MM-DD).
     * @return Egreso Egreso creado.
     */
    private function crearEgreso(float $montoTotal, string $fecha): Egreso
    {
        $numero = $this->siguienteEgreso++;
        $costoNeto = round($montoTotal / 1.13, 2);

        return Egreso::create([
            'correlativo'            => sprintf('EG-%06d', $numero),
            'subcategoria_egreso_id' => $this->subcategoria->id,
            'usuario_id'             => $this->contadora->id,
            'proveedor'              => "Proveedor ficticio {$numero}",
            'numero_comprobante'     => "CCF-{$numero}",
            'fecha_comprobante'      => $fecha,
            'monto_total'            => $montoTotal,
            'costo_neto'             => $costoNeto,
            'iva_credito_fiscal'     => round($costoNeto * 0.13, 2),
        ]);
    }

    /**
     * Valida que el libro une ventas y egresos, ordenados por fecha descendente, con totales
     * y flujo neto, y que las compras no se cuentan como egresos.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return void
     */
    public function test_une_ventas_y_egresos_con_totales_y_flujo_neto(): void
    {
        $venta = $this->crearVenta(25.50, '2026-11-10 12:00:00', 'tarjeta');
        $this->crearVenta(10.00, '2026-11-12 13:00:00');
        $egreso = $this->crearEgreso(11.30, '2026-11-11');

        Compra::create([
            'usuario_id'         => $this->contadora->id,
            'fecha_compra'       => '2026-11-11',
            'monto_total'        => 500.00,
            'costo_neto'         => 442.48,
            'iva_credito_fiscal' => 57.52,
        ]);

        $respuesta = $this->getJson('/api/movimientos-contables');

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.movimientos.total', 3);

        $filas = $respuesta->json('data.movimientos.data');
        $this->assertSame(['2026-11-12', '2026-11-11', '2026-11-10'], array_column($filas, 'fecha'));
        $this->assertSame(['ingreso', 'egreso', 'ingreso'], array_column($filas, 'tipo'));

        $this->assertSame("Venta #{$venta->id}", $filas[2]['referencia']);
        $this->assertSame("Comanda #{$venta->comanda_id}", $filas[2]['concepto']);
        $this->assertSame('Tarjeta', $filas[2]['detalle']);
        $this->assertEquals(25.50, $filas[2]['monto']);

        $this->assertSame($egreso->correlativo, $filas[1]['referencia']);
        $this->assertSame($egreso->proveedor, $filas[1]['concepto']);
        $this->assertStringContainsString('Materia prima de prueba', $filas[1]['detalle']);

        $respuesta->assertJsonPath('data.totales.cantidad_ingresos', 2)
            ->assertJsonPath('data.totales.cantidad_egresos', 1);
        $totales = $respuesta->json('data.totales');
        $this->assertEquals(35.50, $totales['total_ingresos']);
        $this->assertEquals(11.30, $totales['total_egresos']);
        $this->assertEquals(24.20, $totales['flujo_neto']);
    }

    /**
     * Valida el filtro por rango de fechas, incluido el último día completo de ventas.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return void
     */
    public function test_filtra_por_rango_de_fechas(): void
    {
        $this->crearVenta(5.00, '2026-10-31 23:59:00');
        $this->crearVenta(7.00, '2026-11-30 23:59:00');
        $this->crearEgreso(3.00, '2026-11-01');
        $this->crearEgreso(4.00, '2026-12-01');

        $respuesta = $this->getJson('/api/movimientos-contables?desde=2026-11-01&hasta=2026-11-30');

        $respuesta->assertStatus(200)->assertJsonPath('data.movimientos.total', 2);
        $totales = $respuesta->json('data.totales');
        $this->assertEquals(7.00, $totales['total_ingresos']);
        $this->assertEquals(3.00, $totales['total_egresos']);
        $this->assertEquals(4.00, $totales['flujo_neto']);
    }

    /**
     * Valida el filtro por tipo: solo ingresos o solo egresos, con el otro total en cero.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return void
     */
    public function test_filtra_por_tipo(): void
    {
        $this->crearVenta(5.00, '2026-11-05 10:00:00');
        $this->crearEgreso(2.00, '2026-11-05');

        $ingresos = $this->getJson('/api/movimientos-contables?tipo=ingreso');
        $ingresos->assertStatus(200)
            ->assertJsonPath('data.movimientos.total', 1)
            ->assertJsonPath('data.movimientos.data.0.tipo', 'ingreso');
        $this->assertEquals(0, $ingresos->json('data.totales.total_egresos'));

        $egresos = $this->getJson('/api/movimientos-contables?tipo=egreso');
        $egresos->assertStatus(200)
            ->assertJsonPath('data.movimientos.total', 1)
            ->assertJsonPath('data.movimientos.data.0.tipo', 'egreso');
        $this->assertEquals(0, $egresos->json('data.totales.total_ingresos'));
    }

    /**
     * Valida que el libro pagina y que los totales cubren todo el filtro, no solo la página.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return void
     */
    public function test_pagina_y_totales_cubren_todo_el_filtro(): void
    {
        for ($i = 1; $i <= 22; $i++) {
            $this->crearVenta(1.00, sprintf('2026-11-%02d 10:00:00', $i));
        }

        $respuesta = $this->getJson('/api/movimientos-contables?page=2');

        $respuesta->assertStatus(200)
            ->assertJsonPath('data.movimientos.current_page', 2)
            ->assertJsonCount(2, 'data.movimientos.data');
        $this->assertEquals(22.00, $respuesta->json('data.totales.total_ingresos'));
    }

    /**
     * Valida el rechazo de filtros inválidos con 422.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @return void
     */
    public function test_rechaza_filtros_invalidos(): void
    {
        $this->getJson('/api/movimientos-contables?desde=2026-11-10&hasta=2026-11-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hasta']);

        $this->getJson('/api/movimientos-contables?tipo=retiro')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);
    }

    /**
     * Valida que sin sesión responde 401 y los roles operativos reciben 403 (CLAUDE.md §9).
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006 / RF-ADM-008
     *
     * @return void
     */
    public function test_acceso_restringido_a_contabilidad_y_socios(): void
    {
        foreach (['cajero', 'mesero', 'cocinero'] as $rol) {
            $usuario = User::factory()->create();
            $usuario->assignRole($rol);
            Sanctum::actingAs($usuario);

            $this->getJson('/api/movimientos-contables')->assertStatus(403);
        }

        $socio = User::factory()->create();
        $socio->assignRole('socio');
        Sanctum::actingAs($socio);
        $this->getJson('/api/movimientos-contables')->assertStatus(200);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/movimientos-contables')->assertStatus(401);
    }
}
