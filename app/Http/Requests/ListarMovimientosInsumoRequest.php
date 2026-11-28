<?php

namespace App\Http\Requests;

class ListarMovimientosInsumoRequest extends MovimientoInventarioRequest
{
    /**
     * Valida la pagina solicitada del historial de un insumo.
     *
     * @autor  Equipo de desarrollo
     *
     * @fecha  2026-11-17
     *
     * @módulo Inventario RF-INV-003 / CC-113 / CC-117
     *
     * @return array<string, mixed> Reglas de paginacion.
     */
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1', 'max:2147483647']];
    }
}
