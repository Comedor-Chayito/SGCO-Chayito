<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarEgresoRequest;
use App\Services\EgresoService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API para el registro de egresos (CC-13 / US-CON-01).
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005
 */
class EgresoController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de egresos.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 (CC-63)
     *
     * @param  EgresoService $egresoService Servicio de lógica de negocio de egresos.
     */
    public function __construct(private readonly EgresoService $egresoService)
    {
    }

    /**
     * Registra la factura de un proveedor como egreso con correlativo único.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 (CC-63)
     *
     * @param  RegistrarEgresoRequest $request Datos validados de la factura.
     * @return JsonResponse Egreso creado con código HTTP 201.
     */
    public function store(RegistrarEgresoRequest $request): JsonResponse
    {
        $egreso = $this->egresoService->registrar($request->validated(), $request->user());

        return response()->json([
            'status'  => 'ok',
            'message' => "Egreso {$egreso->correlativo} registrado correctamente.",
            'data'    => $egreso,
        ], 201);
    }
}
