<?php

namespace App\Services;

use App\Models\AperturaCaja;
use App\Models\CierreCaja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AperturaCajaService
{
    /**
     * Inicializa el servicio con el servicio de auditoría inyectado.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @param  AuditoriaService  $auditoriaService  Servicio para registrar bitácora de auditoría.
     */
    public function __construct(private readonly AuditoriaService $auditoriaService) {}

    /**
     * Registra la apertura de caja y el saldo inicial calculado por denominación.
     * La bitácora generada es inalterable y única por día comercial.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @param  array  $datos  Datos validados: fecha (opcional), denominaciones, observaciones.
     * @param  int    $usuarioId  ID del cajero o administrador que abre la caja.
     * @return AperturaCaja Registro creado con la relación de usuario cargada.
     *
     * @throws ValidationException Si ya existe apertura o cierre para la fecha.
     * @throws \Throwable Si falla la transacción en base de datos.
     */
    public function abrirCaja(array $datos, int $usuarioId): AperturaCaja
    {
        $fecha = isset($datos['fecha']) ? Carbon::parse($datos['fecha'])->toDateString() : today()->toDateString();

        if (CierreCaja::whereDate('fecha', $fecha)->exists()) {
            throw ValidationException::withMessages([
                'caja' => 'La caja de esta fecha ya fue cerrada; no se puede registrar una nueva apertura.',
            ]);
        }

        if (AperturaCaja::whereDate('fecha', $fecha)->exists()) {
            throw ValidationException::withMessages([
                'fecha' => 'Ya existe una apertura de caja registrada para esta fecha.',
            ]);
        }

        return DB::transaction(function () use ($datos, $fecha, $usuarioId) {
            $totalSaldoInicial = 0.00;
            $denominacionesDetalle = [];

            foreach ($datos['denominaciones'] as $item) {
                $denominacion = round((float) $item['denominacion'], 2);
                $cantidad = (int) $item['cantidad'];
                $subtotal = round($denominacion * $cantidad, 2);

                $totalSaldoInicial += $subtotal;

                $denominacionesDetalle[] = [
                    'denominacion' => $denominacion,
                    'tipo'         => $item['tipo'],
                    'cantidad'     => $cantidad,
                    'subtotal'     => $subtotal,
                ];
            }

            $apertura = AperturaCaja::create([
                'usuario_id'     => $usuarioId,
                'fecha'          => $fecha,
                'saldo_inicial'  => round($totalSaldoInicial, 2),
                'denominaciones' => $denominacionesDetalle,
                'observaciones'  => $datos['observaciones'] ?? null,
            ]);

            $this->auditoriaService->registrar(
                'apertura_caja',
                $apertura,
                null,
                $apertura->toArray()
            );

            return $apertura->load('usuario:id,name');
        });
    }

    /**
     * Obtiene la apertura de caja del día o de una fecha determinada.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @param  string|null  $fecha  Fecha en formato Y-m-d (por defecto hoy).
     * @return AperturaCaja|null Registro de apertura si existe, o null.
     */
    public function obtenerAperturaPorFecha(?string $fecha = null): ?AperturaCaja
    {
        $fechaConsulta = $fecha ?? today()->toDateString();

        return AperturaCaja::with('usuario:id,name')
            ->whereDate('fecha', $fechaConsulta)
            ->first();
    }
}
