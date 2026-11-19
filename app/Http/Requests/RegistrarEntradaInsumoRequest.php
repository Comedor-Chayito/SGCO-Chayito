<?php

namespace App\Http\Requests;

class RegistrarEntradaInsumoRequest extends MovimientoInventarioRequest
{
    /**
     * Valida una entrada positiva y su comprobante de egreso opcional.
     *
     * @autor  Equipo de desarrollo
     * @fecha  2026-11-17
     * @módulo Inventario RF-INV-003 / CC-113 / CC-115
     *
     * @return array<string, mixed> Reglas de la entrada.
     */
    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'numeric', 'decimal:0,6', 'gt:0', 'max:99999999.999999'],
            'egreso_id' => ['nullable', 'integer', 'exists:egresos,id'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
