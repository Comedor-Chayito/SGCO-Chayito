<?php

namespace App\Http\Requests;

class RegistrarAjusteInsumoRequest extends MovimientoInventarioRequest
{
    /**
     * Valida el delta firmado y el motivo obligatorio de un ajuste.
     *
     * @autor  Equipo de desarrollo
     * @fecha  2026-11-17
     * @módulo Inventario RF-INV-003 / CC-113 / CC-116
     *
     * @return array<string, mixed> Reglas del ajuste.
     */
    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'numeric', 'decimal:0,6', 'not_in:0',
                'between:-99999999.999999,99999999.999999'],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
