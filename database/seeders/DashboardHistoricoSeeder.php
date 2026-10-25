<?php

namespace Database\Seeders;

use App\Models\Comanda;
use App\Models\ComandaDetalle;
use App\Models\Compra;
use App\Models\Platillo;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Genera datos ficticios e idempotentes para visualizar el dashboard gerencial.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 */
class DashboardHistoricoSeeder extends Seeder
{
    private const MARCADOR_DEMO = 'DEMO-DASHBOARD';

    /**
     * Inserta seis meses de ventas, platillos y compras ficticias para desarrollo local.
     *
     * El seeder no usa datos de personas ni proveedores reales. Puede ejecutarse varias veces:
     * el marcador de cada comanda y la combinación de fecha/monto de cada compra evitan duplicados.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PosSeeder::class,
        ]);

        $administrador = User::query()
            ->where('email', 'admin@sgco-chayito.local')
            ->firstOrFail();

        DB::transaction(function () use ($administrador): void {
            $inicio = now()->startOfMonth()->subMonths(5);
            $ventasMensuales = [];

            foreach (range(0, 5) as $indice) {
                $fecha = $inicio->copy()->addMonths($indice)->addDays(11)->setTime(12, 0);
                $ventasMensuales[$indice] = $this->registrarVentasDelMes(
                    $administrador,
                    $fecha,
                    $indice,
                );
            }

            $this->registrarComprasHistoricas($administrador, $inicio, $ventasMensuales);
        });
    }

    /**
     * Registra ventas demostrativas variadas por platillo para un mes determinado.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     *
     * @param  User  $administrador  Usuario técnico responsable de las ventas de demostración.
     * @param  Carbon  $fecha  Fecha histórica asignada a las ventas del mes.
     * @param  int  $indiceMes  Posición del mes dentro de la serie de seis meses.
     * @return float  Total de ingresos demostrativos del mes.
     */
    private function registrarVentasDelMes(User $administrador, Carbon $fecha, int $indiceMes): float
    {
        $totalMes = 0.0;

        foreach ($this->obtenerConfiguracionPlatillos() as $configuracion) {
            $platillo = Platillo::query()
                ->where('nombre', $configuracion['nombre'])
                ->firstOrFail();
            $cantidad = $configuracion['cantidad_base']
                + ($indiceMes * $configuracion['crecimiento_mensual']);
            $total = round($cantidad * (float) $platillo->precio_unitario, 2);
            $marcador = sprintf(
                '%s:%s:%s',
                self::MARCADOR_DEMO,
                $fecha->format('Y-m'),
                $platillo->id,
            );

            $comanda = Comanda::updateOrCreate(
                ['observaciones' => $marcador],
                [
                    'usuario_id' => $administrador->id,
                    'canal' => 'para_llevar',
                    'estado' => 'pagada',
                    'subtotal' => $total,
                ],
            );
            $this->asignarFechaHistorica($comanda, $fecha);

            $detalle = ComandaDetalle::updateOrCreate(
                [
                    'comanda_id' => $comanda->id,
                    'platillo_id' => $platillo->id,
                ],
                [
                    'cantidad' => $cantidad,
                    'precio_unitario' => $platillo->precio_unitario,
                    'observaciones' => 'Dato ficticio para visualización del dashboard.',
                ],
            );
            $this->asignarFechaHistorica($detalle, $fecha);

            $venta = Venta::updateOrCreate(
                ['comanda_id' => $comanda->id],
                [
                    'usuario_id' => $administrador->id,
                    'total' => $total,
                    'monto_recibido' => $total,
                    'cambio' => 0,
                    'metodo_pago' => 'efectivo',
                ],
            );
            $this->asignarFechaHistorica($venta, $fecha);

            $totalMes = round($totalMes + $total, 2);
        }

        return $totalMes;
    }

    /**
     * Registra una compra ficticia por mes para contextualizar gastos y margen bruto.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     *
     * @param  User  $administrador  Usuario técnico responsable de las compras de demostración.
     * @param  Carbon  $inicio  Primer mes de la serie histórica.
     * @param  array<int, float>  $ventasMensuales  Total de ventas generado por cada mes.
     * @return void
     */
    private function registrarComprasHistoricas(
        User $administrador,
        Carbon $inicio,
        array $ventasMensuales,
    ): void {
        foreach ($ventasMensuales as $indice => $totalVentas) {
            $fechaCompra = $inicio->copy()->addMonths($indice)->addDays(5);
            $costoNeto = round($totalVentas * 0.55, 2);
            $montoTotal = round($costoNeto * 1.13, 2);

            $compra = Compra::firstOrCreate(
                [
                    'usuario_id' => $administrador->id,
                    'fecha_compra' => $fechaCompra->toDateString(),
                    'monto_total' => $montoTotal,
                ],
                [
                    'costo_neto' => $costoNeto,
                    'iva_credito_fiscal' => round($montoTotal - $costoNeto, 2),
                ],
            );
            $this->asignarFechaHistorica($compra, $fechaCompra->setTime(9, 0));
        }
    }

    /**
     * Define los platillos y cantidades ficticias que dan variedad a las gráficas.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     *
     * @return array<int, array{nombre: string, cantidad_base: int, crecimiento_mensual: int}> Configuración de ventas ficticias.
     */
    private function obtenerConfiguracionPlatillos(): array
    {
        return [
            ['nombre' => 'Pollo Asado', 'cantidad_base' => 38, 'crecimiento_mensual' => 4],
            ['nombre' => 'Carne Guisada', 'cantidad_base' => 24, 'crecimiento_mensual' => 2],
            ['nombre' => 'Pescado Frito', 'cantidad_base' => 16, 'crecimiento_mensual' => 1],
            ['nombre' => 'Sopa de res', 'cantidad_base' => 20, 'crecimiento_mensual' => 3],
            ['nombre' => 'Pupusas de queso', 'cantidad_base' => 54, 'crecimiento_mensual' => 5],
        ];
    }

    /**
     * Ajusta las marcas de tiempo de un modelo para que pertenezca al periodo histórico.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     *
     * @param  object  $modelo  Modelo Eloquent que posee columnas de auditoría.
     * @param  Carbon  $fecha  Fecha histórica que se aplicará al modelo.
     * @return void
     */
    private function asignarFechaHistorica(object $modelo, Carbon $fecha): void
    {
        $modelo->forceFill([
            'created_at' => $fecha,
            'updated_at' => $fecha,
        ])->save();
    }
}
