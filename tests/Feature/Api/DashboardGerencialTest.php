<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\ComandaDetalle;
use App\Models\Compra;
use App\Models\Platillo;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de KPIs financieros y acceso restringido del dashboard gerencial.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
 */
class DashboardGerencialTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    private User $socio;

    private User $contadora;

    /**
     * Prepara los roles y usuarios que intervienen en la consulta gerencial.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->administrador = User::where('email', 'admin@sgco-chayito.local')->firstOrFail();
        $this->socio = User::factory()->create();
        $this->socio->assignRole('socio');
        $this->contadora = User::factory()->create();
        $this->contadora->assignRole('contadora');
    }

    /**
     * Verifica que el dashboard consolida ingresos, gastos netos, utilidad y margen bruto.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75
     */
    public function test_administrador_obtiene_kpis_de_salud_financiera_y_margen_bruto(): void
    {
        $hoy = now()->toDateString();
        $this->registrarVenta($this->administrador, 113, $hoy);
        $this->registrarVenta($this->administrador, 113, $hoy);
        $this->registrarCompra($this->administrador, 113, 100, $hoy);
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.periodo.fecha_desde', $hoy)
            ->assertJsonPath('data.kpis.ingresos', 226)
            ->assertJsonPath('data.kpis.gastos_netos', 100)
            ->assertJsonPath('data.kpis.utilidad_bruta', 126)
            ->assertJsonPath('data.kpis.margen_bruto_porcentaje', 55.75)
            ->assertJsonPath('data.kpis.ventas_registradas', 2)
            ->assertJsonPath('data.kpis.compras_registradas', 1);
    }

    /**
     * Verifica que el rango solicitado excluye ventas y compras fuera del periodo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75
     */
    public function test_dashboard_filtra_los_kpis_por_rango_de_fechas(): void
    {
        $fechaIncluida = now()->subDay()->toDateString();
        $fechaExcluida = now()->subMonths(2)->toDateString();
        $this->registrarVenta($this->administrador, 100, $fechaIncluida);
        $this->registrarVenta($this->administrador, 999, $fechaExcluida);
        $this->registrarCompra($this->administrador, 56.5, 50, $fechaIncluida);
        $this->registrarCompra($this->administrador, 113, 100, $fechaExcluida);
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$fechaIncluida}&fecha_hasta={$fechaIncluida}")
            ->assertOk()
            ->assertJsonPath('data.kpis.ingresos', 100)
            ->assertJsonPath('data.kpis.gastos_netos', 50)
            ->assertJsonPath('data.kpis.utilidad_bruta', 50)
            ->assertJsonPath('data.kpis.margen_bruto_porcentaje', 50);
    }

    /**
     * Verifica que el API entrega las series para las gráficas de ventas y rentabilidad estimada.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     */
    public function test_dashboard_incluye_graficas_de_ventas_y_rentabilidad_estimada_por_platillo(): void
    {
        $hoy = now()->toDateString();
        $sopa = Platillo::create([
            'nombre' => 'Sopa de prueba',
            'precio_unitario' => 40,
            'disponible' => true,
        ]);
        $pollo = Platillo::create([
            'nombre' => 'Pollo de prueba',
            'precio_unitario' => 40,
            'disponible' => true,
        ]);
        $this->registrarVenta($this->administrador, 80, $hoy, $sopa, 2);
        $this->registrarVenta($this->administrador, 40, $hoy, $pollo);
        $this->registrarCompra($this->administrador, 56.5, 50, $hoy);
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk()
            ->assertJsonCount(6, 'data.graficos.ventas_mensuales')
            ->assertJsonFragment([
                'periodo' => now()->format('Y-m'),
                'total' => 120,
            ])
            ->assertJsonPath('data.graficos.rentabilidad_por_platillo.0.nombre', 'Sopa de prueba')
            ->assertJsonPath('data.graficos.rentabilidad_por_platillo.0.unidades', 2)
            ->assertJsonPath('data.graficos.rentabilidad_por_platillo.0.ingresos', 80)
            ->assertJsonPath('data.graficos.rentabilidad_por_platillo.0.utilidad_estimada', 46.67)
            ->assertJsonPath('data.graficos.rentabilidad_por_platillo.1.nombre', 'Pollo de prueba')
            ->assertJsonPath('data.graficos.rentabilidad_por_platillo.1.utilidad_estimada', 23.33);
    }

    /**
     * Verifica que el rol Socio puede consultar los indicadores financieros.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-76
     */
    public function test_socio_puede_consultar_el_dashboard_gerencial(): void
    {
        Sanctum::actingAs($this->socio);

        $this->getJson('/api/reportes/dashboard')->assertOk();
    }

    /**
     * Verifica que Contadora no puede acceder al dashboard reservado para Socio y Administrador.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-76
     */
    public function test_contadora_no_puede_consultar_el_dashboard_gerencial(): void
    {
        Sanctum::actingAs($this->contadora);

        $this->getJson('/api/reportes/dashboard')->assertForbidden();
    }

    /**
     * Registra una venta con la fecha requerida para el escenario financiero de prueba.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75
     *
     * @param  User  $usuario  Persona que aparece como responsable de la venta.
     * @param  float  $total  Importe total de la venta.
     * @param  string  $fecha  Día que debe incluirse en el dashboard.
     * @param  Platillo|null  $platillo  Platillo facturado en la comanda de prueba.
     * @param  int  $cantidad  Unidades del platillo facturadas en la comanda.
     */
    private function registrarVenta(
        User $usuario,
        float $total,
        string $fecha,
        ?Platillo $platillo = null,
        int $cantidad = 1,
    ): void
    {
        $comanda = Comanda::create([
            'usuario_id' => $usuario->id,
            'canal' => 'para_llevar',
            'estado' => 'pagada',
            'subtotal' => $total,
        ]);
        $platillo ??= Platillo::create([
            'nombre' => "Platillo de prueba {$comanda->id}",
            'precio_unitario' => round($total / $cantidad, 2),
            'disponible' => true,
        ]);
        ComandaDetalle::create([
            'comanda_id' => $comanda->id,
            'platillo_id' => $platillo->id,
            'cantidad' => $cantidad,
            'precio_unitario' => round($total / $cantidad, 2),
        ]);
        $venta = Venta::create([
            'comanda_id' => $comanda->id,
            'usuario_id' => $usuario->id,
            'total' => $total,
            'monto_recibido' => $total,
            'cambio' => 0,
            'metodo_pago' => 'efectivo',
        ]);
        $venta->forceFill([
            'created_at' => Carbon::parse($fecha)->setTime(12, 0),
            'updated_at' => Carbon::parse($fecha)->setTime(12, 0),
        ])->save();
    }

    /**
     * Registra una compra ya desglosada para el escenario de consolidación financiera.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75
     *
     * @param  User  $usuario  Persona responsable del registro de compra.
     * @param  float  $montoTotal  Importe total con IVA incluido.
     * @param  float  $costoNeto  Base imponible que se reconoce como gasto neto.
     * @param  string  $fecha  Día que debe incluirse en el dashboard.
     */
    private function registrarCompra(User $usuario, float $montoTotal, float $costoNeto, string $fecha): void
    {
        Compra::create([
            'usuario_id' => $usuario->id,
            'fecha_compra' => $fecha,
            'monto_total' => $montoTotal,
            'costo_neto' => $costoNeto,
            'iva_credito_fiscal' => round($montoTotal - $costoNeto, 2),
        ]);
    }
}
