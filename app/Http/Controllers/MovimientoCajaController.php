<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarMovimientosCajaRequest;
use App\Services\MovimientoCajaService;
use Illuminate\Http\JsonResponse;

class MovimientoCajaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de movimientos de caja.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @param  MovimientoCajaService $movimientoCajaService Servicio que arma el resumen del día.
     */
    public function __construct(private readonly MovimientoCajaService $movimientoCajaService)
    {
    }

    /**
     * Devuelve el resumen y los movimientos de caja del día solicitado (por defecto hoy).
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @param  ConsultarMovimientosCajaRequest $request Filtro opcional de fecha validado.
     * @return JsonResponse Resumen con estado, totales y movimientos.
     */
    public function index(ConsultarMovimientosCajaRequest $request): JsonResponse
    {
        $resumen = $this->movimientoCajaService->resumenDelDia($request->validated()['fecha'] ?? null);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Movimientos de caja obtenidos.',
            'data'    => $resumen,
        ]);
    }
}
