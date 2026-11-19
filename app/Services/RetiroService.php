<?php

namespace App\Services;

use App\Models\RetiroParcial;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetiroService
{
    /**
     * Inicializa el servicio con el registro de auditoría y la verificación de caja inyectados.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @param  AuditoriaService  $auditoriaService  Servicio de bitácora de auditoría.
     * @param  EstadoCajaService $estadoCajaService Servicio que valida apertura y cierre de caja.
     */
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly EstadoCajaService $estadoCajaService,
    ) {
    }

    /**
     * Registra un retiro parcial de efectivo en el turno actual del cajero.
     * El turno es el día comercial: sin apertura no hay efectivo que retirar, y una vez cerrada
     * la caja del día no se admiten retiros, porque el cierre ya consolidó el total de retiros.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @param  array $datos   Datos validados: monto y motivo.
     * @param  User  $usuario Cajero o administrador que realiza el retiro.
     * @return RetiroParcial Retiro creado con el usuario cargado.
     *
     * @throws ValidationException Si la caja del día no está abierta o ya fue cerrada.
     * @throws \Throwable          Si falla la transacción en base de datos.
     */
    public function registrar(array $datos, User $usuario): RetiroParcial
    {
        $this->estadoCajaService->asegurarAbierta();

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
     * Lista todos los retiros del turno actual (día comercial), de cualquier usuario, del más
     * reciente al más antiguo. Son los mismos que resta el cierre de caja.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @return Collection<int, RetiroParcial> Retiros con el id y nombre de quien los registró.
     */
    public function listarDelTurno(): Collection
    {
        $retiros = RetiroParcial::with('usuario:id,name')
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->latest()
            ->get();

        // Del usuario solo interesa el nombre; sin rol ni permisos en la respuesta
        $retiros->each(fn (RetiroParcial $retiro) => $retiro->usuario?->setAppends([]));

        return $retiros;
    }
}
