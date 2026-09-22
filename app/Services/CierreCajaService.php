<?php

namespace App\Services;

use App\Models\CierreCaja;
use App\Models\RetiroParcial;
use App\Models\Venta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CierreCajaService
{
    /**
     * Inicializa el servicio con la dependencia de auditoría inyectada.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  AuditoriaService  $auditoriaService  Servicio para registrar la bitácora de auditoría.
     */
    public function __construct(private readonly AuditoriaService $auditoriaService) {}

    /**
     * Ejecuta el cierre diario de caja: consolida ventas, retiros y calcula el cuadre.
     * La bitácora generada es inalterable una vez creada.
     *
     * Fórmula: Venta del Día = (Suma de Retiros + Saldo Final) − Saldo Inicial
     * Diferencia: total_ventas_efectivo − venta_del_dia (positivo = sobrante, negativo = faltante)
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  array  $datos  Datos validados: fecha, saldo_inicial, saldo_final, observaciones.
     * @param  int  $usuarioId  ID del cajero/administrador que realiza el cierre.
     * @return CierreCaja Registro de cierre con la relación de usuario cargada.
     *
     * @throws \Throwable Si falla la transacción en base de datos.
     */
    public function cerrar(array $datos, int $usuarioId): CierreCaja
    {
        return DB::transaction(function () use ($datos, $usuarioId) {
            $fecha = Carbon::parse($datos['fecha']);
            $inicioDia = $fecha->copy()->startOfDay();
            $finDia = $fecha->copy()->endOfDay();

            // Consolidar ventas del día agrupadas por método de pago
            $ventasPorMetodo = Venta::whereBetween('created_at', [$inicioDia, $finDia])
                ->selectRaw('metodo_pago, SUM(total) as total_metodo')
                ->groupBy('metodo_pago')
                ->pluck('total_metodo', 'metodo_pago');

            $totalVentasEfectivo = round((float) ($ventasPorMetodo['efectivo'] ?? 0), 2);
            $totalVentasTransferencia = round((float) ($ventasPorMetodo['transferencia'] ?? 0), 2);

            // Consolidar retiros parciales del día
            $totalRetiros = round(
                (float) RetiroParcial::whereBetween('created_at', [$inicioDia, $finDia])->sum('monto'),
                2
            );

            $saldoInicial = round((float) $datos['saldo_inicial'], 2);
            $saldoFinal = round((float) $datos['saldo_final'], 2);

            // Fórmula del negocio (CLAUDE.md §8)
            $ventaDelDia = round(($totalRetiros + $saldoFinal) - $saldoInicial, 2);

            // Diferencia contra ventas en efectivo (sobrante positivo, faltante negativo)
            $diferencia = round($totalVentasEfectivo - $ventaDelDia, 2);

            $cierre = CierreCaja::create([
                'usuario_id' => $usuarioId,
                'fecha' => $datos['fecha'],
                'saldo_inicial' => $saldoInicial,
                'total_ventas_efectivo' => $totalVentasEfectivo,
                'total_ventas_transferencia' => $totalVentasTransferencia,
                'total_retiros' => $totalRetiros,
                'saldo_final' => $saldoFinal,
                'venta_del_dia' => $ventaDelDia,
                'diferencia' => $diferencia,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            // Registrar en la bitácora de auditoría
            $this->auditoriaService->registrar(
                'cierre_caja',
                $cierre,
                null,
                $cierre->toArray(),
            );

            return $cierre->load('usuario:id,name');
        });
    }

    /**
     * Obtiene el registro de cierre de caja para una fecha específica.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  string  $fecha  Fecha en formato Y-m-d.
     * @return CierreCaja Registro de cierre con usuario cargado.
     *
     * @throws ModelNotFoundException Si no existe cierre para esa fecha.
     */
    public function obtenerCierre(string $fecha): CierreCaja
    {
        return CierreCaja::with('usuario:id,name')
            ->where('fecha', $fecha)
            ->firstOrFail();
    }

    /**
     * Lista el historial de cierres de caja paginado, ordenado del más reciente al más antiguo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  int  $porPagina  Cantidad de registros por página.
     * @return LengthAwarePaginator Colección paginada de cierres.
     */
    public function listarCierres(int $porPagina = 15): LengthAwarePaginator
    {
        return CierreCaja::with('usuario:id,name')
            ->orderByDesc('fecha')
            ->paginate($porPagina);
    }
}
