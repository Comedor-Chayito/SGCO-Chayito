<?php

namespace Tests\Feature\Seeders;

use App\Models\Compra;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\DashboardHistoricoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas del seeder histórico destinado a la visualización del dashboard.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 */
class DashboardHistoricoSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica que el seeder crea seis meses de información y no duplica datos al repetirse.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     */
    public function test_siembra_datos_historicos_idempotentes_para_el_dashboard(): void
    {
        $this->seed(DashboardHistoricoSeeder::class);

        $this->assertDatabaseCount('ventas', 30);
        $this->assertDatabaseCount('comanda_detalles', 30);
        $this->assertDatabaseCount('compras', 6);
        $this->assertSame(6, Venta::query()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") AS periodo')
            ->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")')
            ->get()
            ->count());
        $this->assertSame(6, Compra::query()
            ->select('fecha_compra')
            ->distinct()
            ->count('fecha_compra'));

        $this->seed(DashboardHistoricoSeeder::class);

        $this->assertDatabaseCount('ventas', 30);
        $this->assertDatabaseCount('comanda_detalles', 30);
        $this->assertDatabaseCount('compras', 6);
    }

    /**
     * Verifica que las ventas históricas alimentan ambas gráficas del contrato del dashboard.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     */
    public function test_datos_historicos_se_reflejan_en_las_graficas_del_dashboard(): void
    {
        $this->seed(DashboardHistoricoSeeder::class);
        $administrador = User::query()
            ->where('email', 'admin@sgco-chayito.local')
            ->firstOrFail();
        $fechaDesde = now()->startOfMonth()->subMonths(5)->toDateString();
        $fechaHasta = now()->toDateString();
        Sanctum::actingAs($administrador);

        $this->getJson("/api/reportes/dashboard?fecha_desde={$fechaDesde}&fecha_hasta={$fechaHasta}")
            ->assertOk()
            ->assertJsonCount(6, 'data.graficos.ventas_mensuales')
            ->assertJsonPath('data.graficos.ventas_mensuales.5.total', 430)
            ->assertJsonCount(5, 'data.graficos.rentabilidad_por_platillo')
            ->assertJsonFragment(['nombre' => 'Pollo Asado']);
    }
}
