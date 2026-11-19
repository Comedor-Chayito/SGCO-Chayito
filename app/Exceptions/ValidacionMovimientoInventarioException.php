<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ValidacionMovimientoInventarioException extends ValidationException
{
    /**
     * Conserva el contrato de tres campos al rechazar un movimiento de inventario.
     *
     * @autor  Equipo de desarrollo
     * @fecha  2026-11-17
     * @módulo Inventario RF-INV-003 / CC-113
     *
     * @return JsonResponse Errores de validacion dentro de data.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Los datos del movimiento no son validos.',
            'data' => ['errores' => $this->errors()],
        ], $this->status);
    }
}
