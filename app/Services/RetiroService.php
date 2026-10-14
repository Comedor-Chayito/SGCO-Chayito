<?php

namespace App\Services;

use App\Models\CierreCaja;
use App\Models\RetiroParcial;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetiroService
{
    /**
     * Inicializa el servicio con el registro de auditoría inyectado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @param  AuditoriaService $auditoriaService Servicio de bitácora de auditoría.
     */
    public function __construct(private readonly AuditoriaService $auditoriaService)
    {
    }

    /**
     * Registra un retiro parcial de efectivo en el turno actual del cajero.
     * El turno es el día comercial: una vez cerrada la caja del día no se admiten retiros,
     * porque el cierre ya consolidó el total de retiros.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @param  array $datos   Datos validados: monto y motivo.
     * @param  User  $usuario Cajero o administrador que realiza el retiro.
     * @return RetiroParcial Retiro creado con el usuario cargado.
     *
     * @throws ValidationException Si la caja del día ya fue cerrada.
     * @throws \Throwable          Si falla la transacción en base de datos.
     */
    public function registrar(array $datos, User $usuario): RetiroParcial
    {
        if (CierreCaja::whereDate('fecha', today())->exists()) {
            throw ValidationException::withMessages([
                'caja' => 'La caja de hoy ya fue cerrada; no se admiten más retiros.',
            ]);
        }

        return DB::transaction(function () use ($datos, $usuario) {
            $retiro = RetiroParcial::create([
                'usuario_id' => $usuario->id,
                'monto'      => round((float) $datos['monto'], 2),
                'motivo'     => trim($datos['motivo']),
            ]);

            $this->auditoriaService->registrar('retiro_registrado', $retiro, null, $retiro->toArray());

            return $retiro->load('usuario:id,name');
        });
    }

    /**
     * Lista los retiros del turno actual (día comercial) del usuario, del más reciente al más antiguo.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @param  User $usuario Cajero o administrador que consulta su turno.
     * @return Collection<int, RetiroParcial>
     */
    public function listarDelTurno(User $usuario): Collection
    {
        return RetiroParcial::where('usuario_id', $usuario->id)
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->latest()
            ->get();
    }
}
