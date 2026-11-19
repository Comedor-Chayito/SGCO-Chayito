<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LibroMovimientosService
{
    public const MOVIMIENTOS_POR_PAGINA = 20;

    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_EGRESO = 'egreso';

    /**
     * Lista el libro de movimientos: ventas cobradas (ingresos) y egresos registrados, del más
     * reciente al más antiguo, con los totales de todo el filtro (no solo de la página).
     * Las ventas se fechan por su cobro (created_at) y los egresos por su comprobante.
     * Flujo neto = Total de ingresos − Total de egresos (montos totales, con IVA).
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  array  $filtros  Filtros validados: 'desde', 'hasta' y 'tipo', todos opcionales.
     * @return array Arreglo con 'movimientos' (paginado) y 'totales'.
     */
    public function listar(array $filtros): array
    {
        $tipo = $filtros['tipo'] ?? null;
        $consultas = [];

        if ($tipo !== self::TIPO_EGRESO) {
            $consultas[] = $this->consultaIngresos($filtros);
        }
        if ($tipo !== self::TIPO_INGRESO) {
            $consultas[] = $this->consultaEgresos($filtros);
        }

        $union = array_shift($consultas);
        foreach ($consultas as $consulta) {
            $union->unionAll($consulta);
        }

        $movimientos = DB::query()
            ->fromSub($union, 'movimientos')
            ->orderByDesc('fecha')
            ->orderByDesc('momento')
            ->orderByDesc('origen_id')
            ->paginate(self::MOVIMIENTOS_POR_PAGINA);

        $movimientos->getCollection()->transform(fn (object $fila) => $this->darFormato($fila));

        return [
            'movimientos' => $movimientos,
            'totales'     => $this->calcularTotales($filtros, $tipo),
        ];
    }

    /**
     * Arma la consulta de ventas cobradas con las columnas comunes del libro.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  array  $filtros  Filtros validados del libro.
     * @return Builder Consulta de ingresos sin ordenar.
     */
    private function consultaIngresos(array $filtros): Builder
    {
        return $this->filtrarVentas(DB::table('ventas'), $filtros)
            ->selectRaw("'" . self::TIPO_INGRESO . "' AS tipo")
            ->selectRaw('DATE(ventas.created_at) AS fecha')
            ->selectRaw('ventas.created_at AS momento')
            ->selectRaw('ventas.id AS origen_id')
            ->selectRaw('ventas.comanda_id AS comanda_id')
            ->selectRaw('ventas.metodo_pago AS metodo_pago')
            ->selectRaw('NULL AS correlativo')
            ->selectRaw('NULL AS proveedor')
            ->selectRaw('NULL AS numero_comprobante')
            ->selectRaw('NULL AS subcategoria')
            ->selectRaw('ventas.total AS monto');
    }

    /**
     * Arma la consulta de egresos con las columnas comunes del libro.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  array  $filtros  Filtros validados del libro.
     * @return Builder Consulta de egresos sin ordenar.
     */
    private function consultaEgresos(array $filtros): Builder
    {
        $consulta = DB::table('egresos')
            ->leftJoin('subcategorias_egreso', 'subcategorias_egreso.id', '=', 'egresos.subcategoria_egreso_id');

        return $this->filtrarEgresos($consulta, $filtros)
            ->selectRaw("'" . self::TIPO_EGRESO . "' AS tipo")
            ->selectRaw('egresos.fecha_comprobante AS fecha')
            ->selectRaw('egresos.created_at AS momento')
            ->selectRaw('egresos.id AS origen_id')
            ->selectRaw('NULL AS comanda_id')
            ->selectRaw('NULL AS metodo_pago')
            ->selectRaw('egresos.correlativo AS correlativo')
            ->selectRaw('egresos.proveedor AS proveedor')
            ->selectRaw('egresos.numero_comprobante AS numero_comprobante')
            ->selectRaw('subcategorias_egreso.nombre AS subcategoria')
            ->selectRaw('egresos.monto_total AS monto');
    }

    /**
     * Aplica el rango de fechas a las ventas usando el día completo de cobro.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  Builder  $consulta  Consulta sobre la tabla ventas.
     * @param  array  $filtros  Filtros validados del libro.
     * @return Builder La misma consulta con el rango aplicado.
     */
    private function filtrarVentas(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when(
                $filtros['desde'] ?? null,
                fn (Builder $q, string $desde) => $q->where(
                    'ventas.created_at',
                    '>=',
                    Carbon::parse($desde)->startOfDay()
                )
            )
            ->when(
                $filtros['hasta'] ?? null,
                fn (Builder $q, string $hasta) => $q->where(
                    'ventas.created_at',
                    '<=',
                    Carbon::parse($hasta)->endOfDay()
                )
            );
    }

    /**
     * Aplica el rango de fechas a los egresos según la fecha del comprobante.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  Builder  $consulta  Consulta sobre la tabla egresos.
     * @param  array  $filtros  Filtros validados del libro.
     * @return Builder La misma consulta con el rango aplicado.
     */
    private function filtrarEgresos(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when(
                $filtros['desde'] ?? null,
                fn (Builder $q, string $desde) => $q->where('egresos.fecha_comprobante', '>=', $desde)
            )
            ->when(
                $filtros['hasta'] ?? null,
                fn (Builder $q, string $hasta) => $q->where('egresos.fecha_comprobante', '<=', $hasta)
            );
    }

    /**
     * Calcula los totales de ingresos, egresos y flujo neto de todo el filtro.
     * Si se filtra por un tipo, el otro total queda en cero.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  array  $filtros  Filtros validados del libro.
     * @param  string|null  $tipo  Tipo filtrado o null para ambos.
     * @return array Totales con cantidades y montos redondeados a 2 decimales.
     */
    private function calcularTotales(array $filtros, ?string $tipo): array
    {
        $ingresos = (object) ['cantidad' => 0, 'monto' => 0];
        $egresos = (object) ['cantidad' => 0, 'monto' => 0];

        if ($tipo !== self::TIPO_EGRESO) {
            $ingresos = $this->filtrarVentas(DB::table('ventas'), $filtros)
                ->selectRaw('COUNT(*) AS cantidad')
                ->selectRaw('COALESCE(SUM(total), 0) AS monto')
                ->first();
        }
        if ($tipo !== self::TIPO_INGRESO) {
            $egresos = $this->filtrarEgresos(DB::table('egresos'), $filtros)
                ->selectRaw('COUNT(*) AS cantidad')
                ->selectRaw('COALESCE(SUM(monto_total), 0) AS monto')
                ->first();
        }

        $totalIngresos = round((float) $ingresos->monto, 2);
        $totalEgresos = round((float) $egresos->monto, 2);

        return [
            'cantidad_ingresos' => (int) $ingresos->cantidad,
            'cantidad_egresos'  => (int) $egresos->cantidad,
            'total_ingresos'    => $totalIngresos,
            'total_egresos'     => $totalEgresos,
            'flujo_neto'        => round($totalIngresos - $totalEgresos, 2),
        ];
    }

    /**
     * Convierte una fila de la unión en el renglón que muestra el libro.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  object  $fila  Fila cruda de la consulta unida.
     * @return array Renglón con tipo, fecha, referencia, concepto, detalle y monto.
     */
    private function darFormato(object $fila): array
    {
        $esIngreso = $fila->tipo === self::TIPO_INGRESO;

        return [
            'tipo'       => $fila->tipo,
            'fecha'      => Carbon::parse($fila->fecha)->toDateString(),
            'origen_id'  => (int) $fila->origen_id,
            'referencia' => $esIngreso ? "Venta #{$fila->origen_id}" : $fila->correlativo,
            'concepto'   => $esIngreso ? "Comanda #{$fila->comanda_id}" : $fila->proveedor,
            'detalle'    => $esIngreso
                ? ucfirst((string) $fila->metodo_pago)
                : trim(($fila->subcategoria ?? '') . ' · Comprobante ' . $fila->numero_comprobante, ' ·'),
            'monto'      => round((float) $fila->monto, 2),
        ];
    }
}
