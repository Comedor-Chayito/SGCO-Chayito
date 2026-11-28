<?php

namespace App\Services;

use App\Models\AperturaCaja;
use App\Models\CierreCaja;
use App\Models\RetiroParcial;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MovimientoCajaService
{
    /**
     * Arma el resumen de caja de un día: estado, saldo inicial, ventas por método de pago,
     * retiros, efectivo esperado en gaveta y la lista cronológica de movimientos.
     * Efectivo esperado = Saldo Inicial + Ventas en Efectivo − Retiros.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @param  string|null  $fecha  Día comercial (YYYY-MM-DD); por defecto hoy.
     * @return array Resumen con 'fecha', 'estado', 'totales' y 'movimientos'.
     */
    public function resumenDelDia(?string $fecha = null): array
    {
        $dia = $fecha === null ? today() : Carbon::parse($fecha);
        $inicioDia = $dia->copy()->startOfDay();
        $finDia = $dia->copy()->endOfDay();

        $apertura = AperturaCaja::with('usuario:id,name')->whereDate('fecha', $dia)->first();
        $cierre = CierreCaja::with('usuario:id,name')->whereDate('fecha', $dia)->first();
        $ventas = Venta::with('usuario:id,name')
            ->whereBetween('created_at', [$inicioDia, $finDia])
            ->get(['id', 'comanda_id', 'usuario_id', 'total', 'metodo_pago', 'created_at']);
        $retiros = RetiroParcial::with('usuario:id,name')
            ->whereBetween('created_at', [$inicioDia, $finDia])
            ->get(['id', 'usuario_id', 'monto', 'motivo', 'created_at']);

        $ventasPorMetodo = [];
        foreach (Venta::METODOS_PAGO as $metodo) {
            $ventasPorMetodo[$metodo] = round(
                (float) $ventas->where('metodo_pago', $metodo)->sum('total'),
                2
            );
        }
        $saldoInicial = round((float) ($apertura?->saldo_inicial ?? 0), 2);
        $totalRetiros = round((float) $retiros->sum('monto'), 2);
        $efectivoEsperado = round(
            $saldoInicial + $ventasPorMetodo[Venta::METODO_EFECTIVO] - $totalRetiros,
            2
        );

        return [
            'fecha' => $dia->toDateString(),
            'estado' => $cierre ? 'cerrada' : ($apertura ? 'abierta' : 'sin_abrir'),
            'totales' => [
                'saldo_inicial' => $saldoInicial,
                'ventas_por_metodo' => $ventasPorMetodo,
                'total_ventas' => round(array_sum($ventasPorMetodo), 2),
                'cantidad_ventas' => $ventas->count(),
                'total_retiros' => $totalRetiros,
                'efectivo_esperado' => $efectivoEsperado,
            ],
            'movimientos' => $this->listarMovimientos($apertura, $cierre, $ventas, $retiros),
        ];
    }

    /**
     * Une apertura, ventas, retiros y cierre en una sola lista ordenada por hora.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @param  AperturaCaja|null  $apertura  Apertura del día, si existe.
     * @param  CierreCaja|null  $cierre  Cierre del día, si existe.
     * @param  Collection  $ventas  Ventas del día con su cajero.
     * @param  Collection  $retiros  Retiros del día con su usuario.
     * @return array<int, array{tipo: string, hora: string, descripcion: string, monto: float, usuario: string|null}>
     */
    private function listarMovimientos(
        ?AperturaCaja $apertura,
        ?CierreCaja $cierre,
        $ventas,
        $retiros,
    ): array {
        $movimientos = collect();

        if ($apertura) {
            $movimientos->push($this->movimiento(
                'apertura',
                $apertura->created_at,
                'Apertura de caja (saldo inicial)',
                (float) $apertura->saldo_inicial,
                $apertura->usuario?->name,
            ));
        }

        foreach ($ventas as $venta) {
            $movimientos->push($this->movimiento(
                'venta',
                $venta->created_at,
                "Venta #{$venta->id} · Comanda #{$venta->comanda_id} · ".ucfirst($venta->metodo_pago),
                (float) $venta->total,
                $venta->usuario?->name,
                $venta->metodo_pago,
            ));
        }

        foreach ($retiros as $retiro) {
            $movimientos->push($this->movimiento(
                'retiro',
                $retiro->created_at,
                "Retiro: {$retiro->motivo}",
                (float) $retiro->monto,
                $retiro->usuario?->name,
            ));
        }

        if ($cierre) {
            $movimientos->push($this->movimiento(
                'cierre',
                $cierre->created_at,
                'Cierre de caja (saldo final contado)',
                (float) $cierre->saldo_final,
                $cierre->usuario?->name,
            ));
        }

        return $movimientos->sortBy('hora')->values()->all();
    }

    /**
     * Da forma uniforme a un renglón de la lista de movimientos.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @param  string  $tipo  apertura, venta, retiro o cierre.
     * @param  Carbon  $momento  Fecha y hora del movimiento.
     * @param  string  $descripcion  Texto legible del movimiento.
     * @param  float  $monto  Monto positivo del movimiento.
     * @param  string|null  $usuario  Nombre de quien lo registró.
     * @param  string|null  $metodoPago  Método de pago, solo para ventas.
     * @return array Renglón del movimiento.
     */
    private function movimiento(
        string $tipo,
        Carbon $momento,
        string $descripcion,
        float $monto,
        ?string $usuario,
        ?string $metodoPago = null,
    ): array {
        return [
            'tipo' => $tipo,
            'hora' => $momento->toIso8601String(),
            'descripcion' => $descripcion,
            'monto' => round($monto, 2),
            'usuario' => $usuario,
            'metodo_pago' => $metodoPago,
        ];
    }
}
