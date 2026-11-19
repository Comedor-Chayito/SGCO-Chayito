<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarMovimientosContablesRequest;
use App\Services\LibroMovimientosService;
use Illuminate\Http\JsonResponse;

class MovimientoContableController extends Controller
{
    /**
     * Inicializa el controlador con el servicio del libro de movimientos.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  LibroMovimientosService $libroMovimientosService Servicio que une ingresos y egresos.
     */
    public function __construct(private readonly LibroMovimientosService $libroMovimientosService)
    {
    }

    /**
     * Devuelve el libro de movimientos paginado con los totales del filtro.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo Contabilidad – RF-CON-006
     *
     * @param  ListarMovimientosContablesRequest $request Filtros validados de fecha y tipo.
     * @return JsonResponse Movimientos paginados y totales de ingresos, egresos y flujo neto.
     */
    public function index(ListarMovimientosContablesRequest $request): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'message' => 'Libro de movimientos obtenido.',
            'data'    => $this->libroMovimientosService->listar($request->validated()),
        ]);
    }
}
