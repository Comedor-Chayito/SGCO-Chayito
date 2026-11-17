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
 * Pruebas de KPIs financieros, gráficas interactivas y acceso restringido del dashboard gerencial.
 *
 * @autor  CesarZV23005
 * @fecha  2026-10-25
 * @módulo Reportes – RF-REP-007 / US-REP-01 / CC-75 / CC-76 / CC-77
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
     * Verifica que un periodo sin ventas ni egresos devuelve ceros limpios y evita errores de división.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_en_periodo_vacio_retorna_ceros_y_evita_division_por_cero(): void
    {
        $hoy = now()->toDateString();
        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertEquals(0.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertEquals(0.0, (float) $response->json('data.kpis.gastos_netos'));
        $this->assertEquals(0.0, (float) $response->json('data.kpis.iva_credito_fiscal'));
        $this->assertEquals(0.0, (float) $response->json('data.kpis.utilidad_bruta'));
        $this->assertEquals(0.0, (float) $response->json('data.kpis.margen_bruto_porcentaje'));
        $this->assertSame(0, $response->json('data.kpis.ventas_registradas'));
        $this->assertSame(0, $response->json('data.kpis.egresos_registrados'));

        $this->assertEmpty($response->json('data.graficos.rentabilidad_por_platillo'));
        $this->assertEmpty($response->json('data.graficos.gastos_por_subcategoria'));
        $this->assertCount(6, $response->json('data.graficos.ventas_mensuales'));

        foreach ($response->json('data.graficos.ventas_mensuales') as $mes) {
            $this->assertEquals(0.0, (float) $mes['total']);
        }
    }

    /**
     * Verifica que cuando existen ventas pero ningún egreso, el margen bruto es del 100%.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_con_solo_ventas_sin_egresos_calcula_margen_al_cien_por_ciento(): void
    {
        $hoy = now()->toDateString();
        $this->registrarVenta($this->administrador, 150, $hoy);
        $this->registrarVenta($this->administrador, 100, $hoy);
        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk();

        $this->assertEquals(250.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertEquals(0.0, (float) $response->json('data.kpis.gastos_netos'));
        $this->assertEquals(250.0, (float) $response->json('data.kpis.utilidad_bruta'));
        $this->assertEquals(100.0, (float) $response->json('data.kpis.margen_bruto_porcentaje'));
        $this->assertSame(2, $response->json('data.kpis.ventas_registradas'));
        $this->assertSame(0, $response->json('data.kpis.egresos_registrados'));
        $this->assertEmpty($response->json('data.graficos.gastos_por_subcategoria'));
    }

    /**
     * Verifica que cuando existen egresos pero cero ventas, la utilidad es negativa y margen es 0%.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_con_solo_egresos_sin_ventas_calcula_utilidad_negativa_y_margen_cero(): void
    {
        $hoy = now()->toDateString();
        $this->registrarEgreso($this->administrador, 113, 100, $hoy, 'Carnes');
        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk();

        $this->assertEquals(0.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertEquals(100.0, (float) $response->json('data.kpis.gastos_netos'));
        $this->assertEquals(13.0, (float) $response->json('data.kpis.iva_credito_fiscal'));
        $this->assertEquals(-100.0, (float) $response->json('data.kpis.utilidad_bruta'));
        $this->assertEquals(0.0, (float) $response->json('data.kpis.margen_bruto_porcentaje'));
        $this->assertSame(0, $response->json('data.kpis.ventas_registradas'));
        $this->assertSame(1, $response->json('data.kpis.egresos_registrados'));
        $this->assertEmpty($response->json('data.graficos.rentabilidad_por_platillo'));
    }

    /**
     * Verifica que se refleja correctamente una pérdida operativa cuando los gastos superan los ingresos.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_calcula_perdida_operativa_cuando_gastos_superan_ingresos(): void
    {
        $hoy = now()->toDateString();
        $this->registrarVenta($this->administrador, 100, $hoy);
        $this->registrarEgreso($this->administrador, 169.50, 150.00, $hoy, 'Servicios básicos');
        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk();

        $this->assertEquals(100.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertEquals(150.0, (float) $response->json('data.kpis.gastos_netos'));
        $this->assertEquals(-50.0, (float) $response->json('data.kpis.utilidad_bruta'));
        $this->assertEquals(-50.0, (float) $response->json('data.kpis.margen_bruto_porcentaje'));
    }

    /**
     * Verifica el cálculo del punto de equilibrio exacto (utilidad cero y margen cero).
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_en_punto_de_equilibrio_utilidad_y_margen_cero(): void
    {
        $hoy = now()->toDateString();
        $this->registrarVenta($this->administrador, 200, $hoy);
        $this->registrarEgreso($this->administrador, 226, 200, $hoy, 'Abarrotes');
        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk();

        $this->assertEquals(200.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertEquals(200.0, (float) $response->json('data.kpis.gastos_netos'));
        $this->assertEquals(0.0, (float) $response->json('data.kpis.utilidad_bruta'));
        $this->assertEquals(0.0, (float) $response->json('data.kpis.margen_bruto_porcentaje'));
    }

    /**
     * Verifica que los límites exactos (00:00:00 y 23:59:59) se incluyen y los adyacentes se excluyen.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_limites_temporales_incluyen_inicio_y_fin_exactos_y_excluyen_adyacentes(): void
    {
        $fechaDesde = now()->subDays(6)->toDateString();
        $fechaHasta = now()->subDays(2)->toDateString();
        $fechaAnterior = now()->subDays(7)->toDateString();
        $fechaPosterior = now()->subDay()->toDateString();

        // 1. Venta en el límite inferior exacto (00:00:00 de fecha_desde) -> incluida
        $this->registrarVenta($this->administrador, 50, "{$fechaDesde} 00:00:00");

        // 2. Venta en el límite superior exacto (23:59:59 de fecha_hasta) -> incluida
        $this->registrarVenta($this->administrador, 70, "{$fechaHasta} 23:59:59");

        // 3. Venta 1 segundo antes de fecha_desde -> excluida
        $this->registrarVenta($this->administrador, 999, "{$fechaAnterior} 23:59:59");

        // 4. Venta 1 segundo después de fecha_hasta -> excluida
        $this->registrarVenta($this->administrador, 888, "{$fechaPosterior} 00:00:00");

        // Egresos dentro del periodo
        $this->registrarEgreso($this->administrador, 56.5, 50, $fechaDesde);
        $this->registrarEgreso($this->administrador, 56.5, 50, $fechaHasta);

        // Egresos fuera del periodo
        $this->registrarEgreso($this->administrador, 113, 100, $fechaAnterior);
        $this->registrarEgreso($this->administrador, 113, 100, $fechaPosterior);

        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}")
            ->assertOk();

        $this->assertEquals(120.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertEquals(100.0, (float) $response->json('data.kpis.gastos_netos'));
        $this->assertSame(2, $response->json('data.kpis.ventas_registradas'));
        $this->assertSame(2, $response->json('data.kpis.egresos_registrados'));
    }

    /**
     * Verifica que un periodo de un solo día (fecha_desde === fecha_hasta) consolida correctamente.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_periodo_de_un_solo_dia_consolida_correctamente(): void
    {
        $diaFoco = now()->subDays(3)->toDateString();
        $diaAnterior = now()->subDays(4)->toDateString();
        $diaPosterior = now()->subDays(2)->toDateString();

        $this->registrarVenta($this->administrador, 35, "{$diaFoco} 08:30:00");
        $this->registrarVenta($this->administrador, 40, "{$diaFoco} 19:15:00");
        $this->registrarVenta($this->administrador, 999, "{$diaAnterior} 12:00:00");
        $this->registrarVenta($this->administrador, 888, "{$diaPosterior} 12:00:00");

        $this->registrarEgreso($this->administrador, 28.25, 25, $diaFoco, 'Verduras y frutas');
        $this->registrarEgreso($this->administrador, 113, 100, $diaAnterior);
        $this->registrarEgreso($this->administrador, 113, 100, $diaPosterior);

        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$diaFoco}&fecha_hasta={$diaFoco}")
            ->assertOk();

        $this->assertEquals(75.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertEquals(25.0, (float) $response->json('data.kpis.gastos_netos'));
        $this->assertEquals(50.0, (float) $response->json('data.kpis.utilidad_bruta'));
        $this->assertSame(2, $response->json('data.kpis.ventas_registradas'));
        $this->assertSame(1, $response->json('data.kpis.egresos_registrados'));
    }

    /**
     * Verifica que las comandas pendientes, canceladas o no pagadas se excluyen de la rentabilidad.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_ignora_comandas_no_pagadas_en_rentabilidad_por_platillo(): void
    {
        $hoy = now()->toDateString();
        $platilloPagado = Platillo::create(['nombre' => 'Platillo Pagado', 'precio_unitario' => 20, 'disponible' => true]);
        $platilloPendiente = Platillo::create(['nombre' => 'Platillo Pendiente', 'precio_unitario' => 30, 'disponible' => true]);
        $platilloCancelado = Platillo::create(['nombre' => 'Platillo Cancelado', 'precio_unitario' => 40, 'disponible' => true]);

        // Comanda pagada con su venta
        $this->registrarVenta($this->administrador, 20, $hoy, $platilloPagado, 1);

        // Comanda pendiente sin venta pagada
        $comandaPendiente = Comanda::create([
            'usuario_id' => $this->administrador->id,
            'canal' => 'mesa',
            'estado' => 'pendiente',
            'subtotal' => 30,
        ]);
        ComandaDetalle::create([
            'comanda_id' => $comandaPendiente->id,
            'platillo_id' => $platilloPendiente->id,
            'cantidad' => 1,
            'precio_unitario' => 30,
        ]);

        // Comanda cancelada sin venta pagada
        $comandaCancelada = Comanda::create([
            'usuario_id' => $this->administrador->id,
            'canal' => 'para_llevar',
            'estado' => 'cancelada',
            'subtotal' => 40,
        ]);
        ComandaDetalle::create([
            'comanda_id' => $comandaCancelada->id,
            'platillo_id' => $platilloCancelado->id,
            'cantidad' => 1,
            'precio_unitario' => 40,
        ]);

        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk();

        $this->assertEquals(20.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertSame(1, $response->json('data.kpis.ventas_registradas'));

        $rentabilidad = $response->json('data.graficos.rentabilidad_por_platillo');
        $this->assertCount(1, $rentabilidad);
        $this->assertSame('Platillo Pagado', $rentabilidad[0]['nombre']);
        $this->assertSame(1, $rentabilidad[0]['unidades']);
        $this->assertEquals(20.0, (float) $rentabilidad[0]['ingresos']);
    }

    /**
     * Verifica que múltiples ventas del mismo platillo acumulan unidades e ingresos y ordenan por rentabilidad.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_acumula_multiples_ventas_del_mismo_platillo_y_ordena_por_rentabilidad(): void
    {
        $hoy = now()->toDateString();
        $pupusas = Platillo::create(['nombre' => 'Pupusas de Queso', 'precio_unitario' => 1, 'disponible' => true]);
        $sopa = Platillo::create(['nombre' => 'Sopa de Gallina', 'precio_unitario' => 5, 'disponible' => true]);
        $refresco = Platillo::create(['nombre' => 'Horchata', 'precio_unitario' => 1.5, 'disponible' => true]);

        // Pupusas: 2 ventas separadas -> 30 unidades ($30) + 20 unidades ($20) = 50 unidades ($50)
        $this->registrarVenta($this->administrador, 30, $hoy, $pupusas, 30);
        $this->registrarVenta($this->administrador, 20, $hoy, $pupusas, 20);

        // Sopa: 1 venta -> 6 unidades ($30)
        $this->registrarVenta($this->administrador, 30, $hoy, $sopa, 6);

        // Refresco: 1 venta -> 10 unidades ($15)
        $this->registrarVenta($this->administrador, 15, $hoy, $refresco, 10);

        // Egreso operativo de $19.00 costo neto para calcular utilidad proporcional
        $this->registrarEgreso($this->administrador, 21.47, 19, $hoy);

        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk();

        $this->assertEquals(95.0, (float) $response->json('data.kpis.ingresos'));
        $this->assertEquals(19.0, (float) $response->json('data.kpis.gastos_netos'));
        $this->assertEquals(76.0, (float) $response->json('data.kpis.utilidad_bruta'));

        $rentabilidad = $response->json('data.graficos.rentabilidad_por_platillo');
        $this->assertCount(3, $rentabilidad);

        // 1° Pupusas: mayor ingreso ($50) -> mayor utilidad estimada (76 * 50/95 = 40.00)
        $this->assertSame('Pupusas de Queso', $rentabilidad[0]['nombre']);
        $this->assertSame(50, $rentabilidad[0]['unidades']);
        $this->assertEquals(50.0, (float) $rentabilidad[0]['ingresos']);
        $this->assertEquals(40.0, (float) $rentabilidad[0]['utilidad_estimada']);

        // 2° Sopa: $30 ingreso -> 76 * 30/95 = 24.00
        $this->assertSame('Sopa de Gallina', $rentabilidad[1]['nombre']);
        $this->assertSame(6, $rentabilidad[1]['unidades']);
        $this->assertEquals(30.0, (float) $rentabilidad[1]['ingresos']);
        $this->assertEquals(24.0, (float) $rentabilidad[1]['utilidad_estimada']);

        // 3° Horchata: $15 ingreso -> 76 * 15/95 = 12.00
        $this->assertSame('Horchata', $rentabilidad[2]['nombre']);
        $this->assertSame(10, $rentabilidad[2]['unidades']);
        $this->assertEquals(15.0, (float) $rentabilidad[2]['ingresos']);
        $this->assertEquals(12.0, (float) $rentabilidad[2]['utilidad_estimada']);
    }

    /**
     * Verifica que los gastos netos se distribuyen por subcategoría en orden descendente y suman el 100%.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_gastos_por_subcategoria_ordena_descendente_y_verifica_participacion_porcentual(): void
    {
        $hoy = now()->toDateString();
        // $400 en Carnes, $300 en Aves, $200 en Lácteos, $100 en Limpieza -> Total: $1000
        $this->registrarEgreso($this->administrador, 452, 400, $hoy, 'Carnes');
        $this->registrarEgreso($this->administrador, 339, 300, $hoy, 'Aves');
        $this->registrarEgreso($this->administrador, 226, 200, $hoy, 'Lácteos');
        $this->registrarEgreso($this->administrador, 113, 100, $hoy, 'Limpieza');

        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk();

        $gastosPorSubcategoria = $response->json('data.graficos.gastos_por_subcategoria');
        $this->assertCount(4, $gastosPorSubcategoria);

        $this->assertSame('Carnes', $gastosPorSubcategoria[0]['nombre']);
        $this->assertEquals(400.0, (float) $gastosPorSubcategoria[0]['gasto_neto']);
        $this->assertEquals(40.0, (float) $gastosPorSubcategoria[0]['porcentaje']);

        $this->assertSame('Aves', $gastosPorSubcategoria[1]['nombre']);
        $this->assertEquals(300.0, (float) $gastosPorSubcategoria[1]['gasto_neto']);
        $this->assertEquals(30.0, (float) $gastosPorSubcategoria[1]['porcentaje']);

        $this->assertSame('Lácteos', $gastosPorSubcategoria[2]['nombre']);
        $this->assertEquals(200.0, (float) $gastosPorSubcategoria[2]['gasto_neto']);
        $this->assertEquals(20.0, (float) $gastosPorSubcategoria[2]['porcentaje']);

        $this->assertSame('Limpieza', $gastosPorSubcategoria[3]['nombre']);
        $this->assertEquals(100.0, (float) $gastosPorSubcategoria[3]['gasto_neto']);
        $this->assertEquals(10.0, (float) $gastosPorSubcategoria[3]['porcentaje']);

        // Suma de gastos netos por subcategoría coincide exactamente con KPI
        $sumaSubcategorias = array_sum(array_column($gastosPorSubcategoria, 'gasto_neto'));
        $this->assertEquals((float) $response->json('data.kpis.gastos_netos'), $sumaSubcategorias);

        // Suma de porcentajes es 100%
        $sumaPorcentajes = array_sum(array_column($gastosPorSubcategoria, 'porcentaje'));
        $this->assertEquals(100.0, $sumaPorcentajes);
    }

    /**
     * Verifica que el endpoint rechaza parámetros de fecha con formato inválido con error 422.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_rechaza_formato_de_fechas_invalido(): void
    {
        $hoy = now()->toDateString();
        Sanctum::actingAs($this->administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde=fecha-invalida&fecha_hasta={$hoy}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_desde' => 'La fecha inicial debe tener un formato válido.']);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta=otra-cosa")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta' => 'La fecha final debe tener un formato válido.']);
    }

    /**
     * Verifica que los roles operativos (cajero, mesero, cocinero, cocinera) reciben 403 Forbidden.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-76 / CC-77
     */
    public function test_dashboard_bloquea_roles_operativos_cajero_mesero_cocinero(): void
    {
        $cajero = User::factory()->create();
        $cajero->assignRole('cajero');

        $mesero = User::factory()->create();
        $mesero->assignRole('mesero');

        $cocinero = User::factory()->create();
        $cocinero->assignRole('cocinero');

        $cocinera = User::factory()->create();
        $cocinera->assignRole('cocinera');

        Sanctum::actingAs($cajero);
        $this->getJson('/api/reportes/dashboard')->assertForbidden();

        Sanctum::actingAs($mesero);
        $this->getJson('/api/reportes/dashboard')->assertForbidden();

        Sanctum::actingAs($cocinero);
        $this->getJson('/api/reportes/dashboard')->assertForbidden();

        Sanctum::actingAs($cocinera);
        $this->getJson('/api/reportes/dashboard')->assertForbidden();
    }

    /**
     * Verifica que una petición sin autenticación recibe 401 Unauthorized.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-76 / CC-77
     */
    public function test_dashboard_requiere_autenticacion(): void
    {
        $this->getJson('/api/reportes/dashboard')->assertUnauthorized();
    }

    /**
     * Verifica que las operaciones con importes fraccionarios cotidianos conservan la precisión en centavos.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_con_montos_fraccionarios_conserva_precision_en_centavos(): void
    {
        $hoy = now()->toDateString();
        // Venta 1: $14.35, Venta 2: $27.85 -> Ingresos = $42.20
        $this->registrarVenta($this->administrador, 14.35, $hoy);
        $this->registrarVenta($this->administrador, 27.85, $hoy);

        // Egreso 1: $45.20 total, $40.00 neto, $5.20 IVA
        $this->registrarEgreso($this->administrador, 45.20, 40.00, $hoy, 'Carnes');
        // Egreso 2: $1.13 total, $1.00 neto, $0.13 IVA
        $this->registrarEgreso($this->administrador, 1.13, 1.00, $hoy, 'Verduras y frutas');

        Sanctum::actingAs($this->administrador);

        $response = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk();

        // Ingresos: 14.35 + 27.85 = 42.20
        $this->assertEquals(42.20, (float) $response->json('data.kpis.ingresos'));
        // Gastos netos: 40.00 + 1.00 = 41.00
        $this->assertEquals(41.00, (float) $response->json('data.kpis.gastos_netos'));
        // IVA crédito fiscal: 5.20 + 0.13 = 5.33
        $this->assertEquals(5.33, (float) $response->json('data.kpis.iva_credito_fiscal'));
        // Utilidad bruta: 42.20 - 41.00 = 1.20
        $this->assertEquals(1.20, (float) $response->json('data.kpis.utilidad_bruta'));
        // Margen bruto: (1.20 / 42.20) * 100 = 2.8436... -> 2.84%
        $this->assertEquals(2.84, (float) $response->json('data.kpis.margen_bruto_porcentaje'));
    }

    /**
     * Verifica que la consulta al dashboard es de solo lectura y no genera efectos colaterales en la BD.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_es_idempotente_y_de_solo_lectura(): void
    {
        $hoy = now()->toDateString();
        $this->registrarVenta($this->administrador, 100, $hoy);
        $this->registrarEgreso($this->administrador, 113, 100, $hoy);

        $conteoVentasAntes = Venta::count();
        $conteoEgresosAntes = Egreso::count();
        $conteoComandasAntes = Comanda::count();

        Sanctum::actingAs($this->administrador);

        $respuesta1 = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk()
            ->json();

        $respuesta2 = $this->getJson("/api/reportes/dashboard?fecha_desde={$hoy}&fecha_hasta={$hoy}")
            ->assertOk()
            ->json();

        $this->assertSame($respuesta1, $respuesta2);
        $this->assertSame($conteoVentasAntes, Venta::count());
        $this->assertSame($conteoEgresosAntes, Egreso::count());
        $this->assertSame($conteoComandasAntes, Comanda::count());
    }

    /**
     * Verifica que la serie de 6 meses rellena con cero los meses intermedios que no tienen ventas.
     *
     * @autor  CesarZV23005
     * @fecha  2026-10-25
     * @módulo Reportes – RF-REP-007 / CC-77
     */
    public function test_dashboard_serie_mensual_rellena_con_cero_los_meses_sin_ventas(): void
    {
        $finPeriodo = now()->subMonth()->endOfMonth();
        $inicioPeriodo = $finPeriodo->copy()->startOfMonth();

        // Venta en el mes final del periodo
        $this->registrarVenta($this->administrador, 250, $inicioPeriodo->toDateString());
        // Venta 3 meses atrás
        $mesHace3 = $inicioPeriodo->copy()->subMonths(3);
        $this->registrarVenta($this->administrador, 150, $mesHace3->toDateString());

        Sanctum::actingAs($this->administrador);

        $response = $this->getJson(
            "/api/reportes/dashboard?fecha_desde={$inicioPeriodo->toDateString()}&fecha_hasta={$finPeriodo->toDateString()}"
        )->assertOk();

        $ventasMensuales = $response->json('data.graficos.ventas_mensuales');
        $this->assertCount(6, $ventasMensuales);

        // El mes final (índice 5) debe tener 250
        $this->assertSame($finPeriodo->format('Y-m'), $ventasMensuales[5]['periodo']);
        $this->assertEquals(250.0, (float) $ventasMensuales[5]['total']);

        // El mes hace 3 (índice 2) debe tener 150
        $this->assertSame($mesHace3->format('Y-m'), $ventasMensuales[2]['periodo']);
        $this->assertEquals(150.0, (float) $ventasMensuales[2]['total']);

        // Meses vacíos intermedios deben tener 0.0
        $this->assertEquals(0.0, (float) $ventasMensuales[0]['total']);
        $this->assertEquals(0.0, (float) $ventasMensuales[1]['total']);
        $this->assertEquals(0.0, (float) $ventasMensuales[3]['total']);
        $this->assertEquals(0.0, (float) $ventasMensuales[4]['total']);
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
     * @param  string  $fecha  Día o marca de tiempo que debe incluirse en el dashboard.
     * @param  Platillo|null  $platillo  Platillo facturado en la comanda de prueba.
     * @param  int  $cantidad  Unidades del platillo facturadas en la comanda.
     */
    private function registrarVenta(
        User $usuario,
        float $total,
        string $fecha,
        ?Platillo $platillo = null,
        int $cantidad = 1,
    ): void {
        $momento = str_contains($fecha, ':')
            ? Carbon::parse($fecha)
            : Carbon::parse($fecha)->setTime(12, 0);

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
        $comanda->forceFill([
            'created_at' => $momento,
            'updated_at' => $momento,
        ])->save();
        $venta->forceFill([
            'created_at' => $momento,
            'updated_at' => $momento,
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
        $subcategoriaId = SubcategoriaEgreso::where('nombre', $subcategoria)->value('id')
            ?? SubcategoriaEgreso::firstOrCreate(['nombre' => $subcategoria])->id;

        Egreso::create([
            'correlativo' => sprintf('EG-%06d', $numero),
            'subcategoria_egreso_id' => $subcategoriaId,
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
