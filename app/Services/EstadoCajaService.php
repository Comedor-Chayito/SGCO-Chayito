<?php

namespace App\Services;

use App\Models\AperturaCaja;
use App\Models\CierreCaja;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EstadoCajaService
{
    /**
     * Exige que la caja del día esté abierta y sin cierre antes de mover dinero
     * (cobros y retiros).
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @param  string|null  $fecha  Día comercial (YYYY-MM-DD); por defecto hoy.
     * @return void
     *
     * @throws ValidationException Si la caja no se ha abierto o ya se cerró.
     */
    public function asegurarAbierta(?string $fecha = null): void
    {
        $dia = $this->normalizarFecha($fecha);

        if (! AperturaCaja::whereDate('fecha', $dia)->exists()) {
            throw ValidationException::withMessages([
                'caja' => 'La caja de hoy no se ha abierto. Registra la apertura antes de continuar.',
            ]);
        }

        $this->asegurarNoCerrada($dia);
    }

    /**
     * Exige que la caja del día no tenga cierre registrado. Se usa al tomar comandas,
     * que pueden registrarse antes de la apertura y cobrarse después.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-001 / US-POS-04
     *
     * @param  string|null  $fecha  Día comercial (YYYY-MM-DD); por defecto hoy.
     * @return void
     *
     * @throws ValidationException Si la caja del día ya fue cerrada.
     */
    public function asegurarNoCerrada(?string $fecha = null): void
    {
        $dia = $this->normalizarFecha($fecha);

        if (CierreCaja::whereDate('fecha', $dia)->exists()) {
            throw ValidationException::withMessages([
                'caja' => 'La caja de hoy ya fue cerrada; no se admiten más operaciones.',
            ]);
        }
    }

    /**
     * Convierte la fecha recibida al día comercial en formato YYYY-MM-DD.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @param  string|null  $fecha  Fecha en cualquier formato reconocible o null para hoy.
     * @return string Fecha normalizada.
     */
    private function normalizarFecha(?string $fecha): string
    {
        return $fecha === null ? today()->toDateString() : Carbon::parse($fecha)->toDateString();
    }
}
