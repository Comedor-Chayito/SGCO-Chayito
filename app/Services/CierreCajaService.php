<?php

namespace App\Services;

use App\Models\AperturaCaja;
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
     * El saldo inicial se toma de la apertura del día. La bitácora es inalterable una vez creada.
     *
     * Fórmula: Venta del Día = (Suma de Retiros + Saldo Final) − Saldo Inicial
     * Diferencia: venta_del_dia − total_ventas_efectivo, que equivale a efectivo contado menos
     * efectivo esperado en caja (positivo = sobrante, negativo = faltante).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  array  $datos  Datos validados: fecha, saldo_final, observaciones.
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
            $totalVentasTarjeta = round((float) ($ventasPorMetodo['tarjeta'] ?? 0), 2);
            $totalVentasTransferencia = round((float) ($ventasPorMetodo['transferencia'] ?? 0), 2);

            // Consolidar retiros parciales del día
            $totalRetiros = round(
                (float) RetiroParcial::whereBetween('created_at', [$inicioDia, $finDia])->sum('monto'),
                2
            );

            // El saldo inicial es el registrado en la apertura (CC-39), no uno digitado al cerrar
            $saldoInicial = round(
                (float) AperturaCaja::whereDate('fecha', $fecha)->value('saldo_inicial'),
                2
            );
            $saldoFinal = round((float) $datos['saldo_final'], 2);

            // Fórmula del negocio (CLAUDE.md §8)
            $ventaDelDia = round(($totalRetiros + $saldoFinal) - $saldoInicial, 2);

            // Contado menos esperado: sobrante positivo, faltante negativo
            $diferencia = round($ventaDelDia - $totalVentasEfectivo, 2);

            $cierre = CierreCaja::create([
                'usuario_id' => $usuarioId,
                'fecha' => $datos['fecha'],
                'saldo_inicial' => $saldoInicial,
                'total_ventas_efectivo' => $totalVentasEfectivo,
                'total_ventas_tarjeta' => $totalVentasTarjeta,
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

            return $this->soloNombreDeUsuario($cierre->load('usuario:id,name'));
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
        return $this->soloNombreDeUsuario(
            CierreCaja::with('usuario:id,name')
                ->where('fecha', $fecha)
                ->firstOrFail()
        );
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
        $cierres = CierreCaja::with('usuario:id,name')
            ->orderByDesc('fecha')
            ->paginate($porPagina);

        $cierres->getCollection()->each(fn (CierreCaja $cierre) => $this->soloNombreDeUsuario($cierre));

        return $cierres;
    }

    /**
     * Deja en el usuario del cierre solo su id y nombre, sin roles ni permisos.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-41)
     *
     * @param  CierreCaja  $cierre  Cierre con la relación de usuario cargada.
     * @return CierreCaja El mismo cierre, listo para serializar.
     */
    private function soloNombreDeUsuario(CierreCaja $cierre): CierreCaja
    {
        $cierre->usuario?->setAppends([]);

        return $cierre;
    }
}
