<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\ComandaDetalle;
use App\Models\Compra;
use App\Models\Egreso;
use App\Models\Platillo;
use App\Models\SubcategoriaEgreso;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubcategoriaEgresoSeeder;
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
        $this->seed([RolePermissionSeeder::class, SubcategoriaEgresoSeeder::class]);

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
        $this->registrarEgreso($this->administrador, 113, 100, $hoy);
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.periodo.fecha_desde', $hoy)
            ->assertJsonPath('data.kpis.ingresos', 226)
            ->assertJsonPath('data.kpis.gastos_netos', 100)
            ->assertJsonPath('data.kpis.iva_credito_fiscal', 13)
            ->assertJsonPath('data.kpis.utilidad_bruta', 126)
            ->assertJsonPath('data.kpis.margen_bruto_porcentaje', 55.75)
            ->assertJsonPath('data.kpis.ventas_registradas', 2)
            ->assertJsonPath('data.kpis.egresos_registrados', 1);
    }

    /**
     * Verifica que el rango solicitado excluye ventas y egresos fuera del periodo.
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
        $this->registrarEgreso($this->administrador, 56.5, 50, $fechaIncluida);
        $this->registrarEgreso($this->administrador, 113, 100, $fechaExcluida);
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
        $this->registrarEgreso($this->administrador, 56.5, 50, $hoy);
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
     * Verifica que el gasto neto del periodo se agrupa por subcategoría de egreso con su porcentaje.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – RF-REP-007 / CC-71
     */
    public function test_dashboard_agrupa_gastos_netos_por_subcategoria(): void
    {
        $hoy = now()->toDateString();
        $this->registrarEgreso($this->administrador, 113, 100, $hoy, 'Aves');
        $this->registrarEgreso($this->administrador, 56.5, 50, $hoy, 'Aves');
        $this->registrarEgreso($this->administrador, 56.5, 50, $hoy, 'Lácteos');
        $this->registrarEgreso($this->administrador, 113, 100, now()->subMonths(2)->toDateString(), 'Carnes');
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk()
            ->assertJsonPath('data.kpis.gastos_netos', 200)
            ->assertJsonCount(2, 'data.graficos.gastos_por_subcategoria')
            ->assertJsonPath('data.graficos.gastos_por_subcategoria.0.nombre', 'Aves')
            ->assertJsonPath('data.graficos.gastos_por_subcategoria.0.egresos', 2)
            ->assertJsonPath('data.graficos.gastos_por_subcategoria.0.gasto_neto', 150)
            ->assertJsonPath('data.graficos.gastos_por_subcategoria.0.porcentaje', 75)
            ->assertJsonPath('data.graficos.gastos_por_subcategoria.1.nombre', 'Lácteos')
            ->assertJsonPath('data.graficos.gastos_por_subcategoria.1.porcentaje', 25);
    }

    /**
     * Verifica que las compras de la calculadora de IVA no se suman como gasto del dashboard,
     * para no contar dos veces una factura que también se registró como egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – RF-REP-007 / CC-71
     */
    public function test_dashboard_no_suma_compras_como_gastos(): void
    {
        $hoy = now()->toDateString();
        $this->registrarVenta($this->administrador, 100, $hoy);
        Compra::create([
            'usuario_id' => $this->administrador->id,
            'fecha_compra' => $hoy,
            'monto_total' => 113,
            'costo_neto' => 100,
            'iva_credito_fiscal' => 13,
        ]);
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk()
            ->assertJsonPath('data.kpis.gastos_netos', 0)
            ->assertJsonPath('data.kpis.egresos_registrados', 0)
            ->assertJsonPath('data.kpis.margen_bruto_porcentaje', 100)
            ->assertJsonCount(0, 'data.graficos.gastos_por_subcategoria');
    }

    /**
     * Verifica que el periodo se rechaza si llega solo una de las dos fechas, porque el
     * inicio por defecto podría quedar posterior al fin solicitado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – RF-REP-007 / CC-72
     */
    public function test_dashboard_exige_ambas_fechas_del_periodo(): void
    {
        $fecha = now()->subMonth()->toDateString();
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_hasta={$fecha}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_desde' => 'Indica la fecha inicial del periodo.']);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$fecha}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta' => 'Indica la fecha final del periodo.']);
    }

    /**
     * Verifica que el periodo rechaza rangos invertidos y fechas futuras.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – RF-REP-007 / CC-72
     */
    public function test_dashboard_rechaza_rangos_invertidos_y_fechas_futuras(): void
    {
        $hoy = now()->toDateString();
        $ayer = now()->subDay()->toDateString();
        $manana = now()->addDay()->toDateString();
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$ayer}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta']);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$manana}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta']);
    }

    /**
     * Verifica que sin fechas se consulta desde el primer día del mes hasta hoy.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – RF-REP-007 / CC-72
     */
    public function test_dashboard_sin_fechas_usa_el_mes_en_curso(): void
    {
        Sanctum::actingAs($this->administrador);

        $this->getJson('/api/reportes/dashboard')
            ->assertOk()
            ->assertJsonPath('data.periodo.fecha_desde', now()->startOfMonth()->toDateString())
            ->assertJsonPath('data.periodo.fecha_hasta', now()->toDateString());
    }

    /**
     * Verifica que la serie de ventas mensuales termina en el mes de la fecha final filtrada.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – RF-REP-007 / CC-72
     */
    public function test_ventas_mensuales_terminan_en_el_mes_del_periodo_filtrado(): void
    {
        $finPeriodo = now()->subMonths(3)->endOfMonth();
        $inicioPeriodo = $finPeriodo->copy()->startOfMonth();
        $this->registrarVenta($this->administrador, 150, $inicioPeriodo->toDateString());
        $this->registrarVenta($this->administrador, 70, now()->toDateString());
        Sanctum::actingAs($this->administrador);

        $this->getJson(
            "/api/reportes/dashboard?fecha_desde={$inicioPeriodo->toDateString()}"
            . "&fecha_hasta={$finPeriodo->toDateString()}"
        )
            ->assertOk()
            ->assertJsonCount(6, 'data.graficos.ventas_mensuales')
            ->assertJsonPath('data.graficos.ventas_mensuales.5.periodo', $finPeriodo->format('Y-m'))
            ->assertJsonPath('data.graficos.ventas_mensuales.5.total', 150)
            ->assertJsonPath(
                'data.graficos.ventas_mensuales.0.periodo',
                $finPeriodo->copy()->startOfMonth()->subMonths(5)->format('Y-m'),
            )
            ->assertJsonMissing(['periodo' => now()->format('Y-m')]);
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
     * Registra un egreso ya desglosado para el escenario de consolidación financiera.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – RF-REP-007 / CC-71
     *
     * @param  User  $usuario  Persona responsable del registro del egreso.
     * @param  float  $montoTotal  Importe total con IVA incluido.
     * @param  float  $costoNeto  Base imponible que se reconoce como gasto neto.
     * @param  string  $fecha  Fecha del comprobante que debe incluirse en el dashboard.
     * @param  string  $subcategoria  Nombre de la subcategoría con que se clasifica.
     */
    private function registrarEgreso(
        User $usuario,
        float $montoTotal,
        float $costoNeto,
        string $fecha,
        string $subcategoria = 'Abarrotes',
    ): void {
        $numero = Egreso::count() + 1;

        Egreso::create([
            'correlativo' => sprintf('EG-%06d', $numero),
            'subcategoria_egreso_id' => SubcategoriaEgreso::where('nombre', $subcategoria)->value('id'),
            'usuario_id' => $usuario->id,
            'proveedor' => 'Proveedor de prueba',
            'numero_comprobante' => "PRUEBA-{$numero}",
            'fecha_comprobante' => $fecha,
            'monto_total' => $montoTotal,
            'costo_neto' => $costoNeto,
            'iva_credito_fiscal' => round($montoTotal - $costoNeto, 2),
        ]);
    }
}
