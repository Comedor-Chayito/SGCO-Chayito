<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\ComandaDetalle;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * Consolida ingresos, gastos netos y rentabilidad para el dashboard gerencial.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 */
class DashboardGerencialService
{
    /**
     * Calcula los KPIs financieros de un periodo usando ventas y compras ya registradas.
     *
     * Gasto neto corresponde a la suma de costo_neto de las compras; de esta manera el IVA
     * crédito fiscal no se contabiliza como costo operativo. Margen bruto = utilidad / ingresos.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75
     *
     * @param  string|null  $fechaDesde  Inicio inclusivo del periodo; por defecto, primer día del mes.
     * @param  string|null  $fechaHasta  Fin inclusivo del periodo; por defecto, fecha actual.
     * @return array<string, array<string, float|int|string>> Periodo y KPIs financieros consolidados.
     */
    public function obtenerKpis(?string $fechaDesde, ?string $fechaHasta): array
    {
        $inicio = $fechaDesde
            ? Carbon::parse($fechaDesde)->startOfDay()
            : now()->startOfMonth();
        $fin = $fechaHasta
            ? Carbon::parse($fechaHasta)->endOfDay()
            : now()->endOfDay();

        $ingresos = round((float) Venta::query()
            ->whereBetween('created_at', [$inicio, $fin])
            ->sum('total'), 2);
        $gastosNetos = round((float) Compra::query()
            ->whereBetween('fecha_compra', [$inicio->toDateString(), $fin->toDateString()])
            ->sum('costo_neto'), 2);
        $utilidadBruta = round($ingresos - $gastosNetos, 2);
        $margenBruto = $ingresos > 0
            ? round(($utilidadBruta / $ingresos) * 100, 2)
            : 0.0;

        return [
            'periodo' => [
                'fecha_desde' => $inicio->toDateString(),
                'fecha_hasta' => $fin->toDateString(),
            ],
            'kpis' => [
                'ingresos' => $ingresos,
                'gastos_netos' => $gastosNetos,
                'utilidad_bruta' => $utilidadBruta,
                'margen_bruto_porcentaje' => $margenBruto,
                'ventas_registradas' => Venta::query()
                    ->whereBetween('created_at', [$inicio, $fin])
                    ->count(),
                'compras_registradas' => Compra::query()
                    ->whereBetween('fecha_compra', [$inicio->toDateString(), $fin->toDateString()])
                    ->count(),
            ],
            'graficos' => [
                'ventas_mensuales' => $this->obtenerVentasMensuales(),
                'rentabilidad_por_platillo' => $this->obtenerRentabilidadPorPlatillo(
                    $inicio,
                    $fin,
                    $utilidadBruta,
                    $ingresos,
                ),
            ],
        ];
    }

    /**
     * Obtiene las ventas de los últimos seis meses, incluyendo meses sin movimientos.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     *
     * @return array<int, array{periodo: string, etiqueta: string, total: float}> Serie mensual de ventas.
     */
    private function obtenerVentasMensuales(): array
    {
        $inicio = now()->startOfMonth()->subMonths(5);
        $fin = now()->endOfMonth();
        $meses = [];

        for ($indice = 0; $indice < 6; $indice++) {
            $fecha = $inicio->copy()->addMonths($indice);
            $meses[$fecha->format('Y-m')] = [
                'periodo' => $fecha->format('Y-m'),
                'etiqueta' => ucfirst($fecha->translatedFormat('M')),
                'total' => 0.0,
            ];
        }

        Venta::query()
            ->whereBetween('created_at', [$inicio, $fin])
            ->get(['total', 'created_at'])
            ->each(function (Venta $venta) use (&$meses): void {
                $periodo = $venta->created_at->format('Y-m');

                if (isset($meses[$periodo])) {
                    $meses[$periodo]['total'] = round($meses[$periodo]['total'] + (float) $venta->total, 2);
                }
            });

        return array_values($meses);
    }

    /**
     * Estima el aporte de utilidad de cada platillo según sus ingresos y el margen global.
     *
     * El costo por ingrediente aún no se almacena por platillo; por ello se distribuye la utilidad
     * bruta real del periodo de forma proporcional a los ingresos de cada platillo y se identifica
     * como estimación para no presentar un costo inexistente como dato contable exacto.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / US-REP-01
     *
     * @param  Carbon  $inicio  Inicio inclusivo del periodo consultado.
     * @param  Carbon  $fin  Fin inclusivo del periodo consultado.
     * @param  float  $utilidadBruta  Utilidad bruta real consolidada del periodo.
     * @param  float  $ingresos  Ingresos reales consolidados del periodo.
     * @return array<int, array{platillo_id: int, nombre: string, unidades: int, ingresos: float, utilidad_estimada: float}> Aporte por platillo.
     */
    private function obtenerRentabilidadPorPlatillo(
        Carbon $inicio,
        Carbon $fin,
        float $utilidadBruta,
        float $ingresos,
    ): array {
        $platillos = [];

        ComandaDetalle::query()
            ->with('platillo:id,nombre')
            ->whereHas('comanda', fn ($consulta) => $consulta
                ->where('estado', 'pagada')
                ->whereBetween('created_at', [$inicio, $fin]))
            ->get(['platillo_id', 'cantidad', 'precio_unitario'])
            ->each(function (ComandaDetalle $detalle) use (&$platillos): void {
                if (! $detalle->platillo) {
                    return;
                }

                $platilloId = $detalle->platillo_id;
                $ingresoDetalle = round((int) $detalle->cantidad * (float) $detalle->precio_unitario, 2);

                if (! isset($platillos[$platilloId])) {
                    $platillos[$platilloId] = [
                        'platillo_id' => $platilloId,
                        'nombre' => $detalle->platillo->nombre,
                        'unidades' => 0,
                        'ingresos' => 0.0,
                    ];
                }

                $platillos[$platilloId]['unidades'] += (int) $detalle->cantidad;
                $platillos[$platilloId]['ingresos'] = round(
                    $platillos[$platilloId]['ingresos'] + $ingresoDetalle,
                    2,
                );
            });

        return collect($platillos)
            ->map(function (array $platillo) use ($utilidadBruta, $ingresos): array {
                $proporcionIngresos = $ingresos > 0 ? $platillo['ingresos'] / $ingresos : 0;
                $platillo['utilidad_estimada'] = round($utilidadBruta * $proporcionIngresos, 2);

                return $platillo;
            })
            ->sortByDesc('utilidad_estimada')
            ->values()
            ->all();
    }
}
