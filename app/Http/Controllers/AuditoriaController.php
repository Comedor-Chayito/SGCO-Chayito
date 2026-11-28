<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarAuditoriasRequest;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API de consulta de la bitácora de auditoría. Es de solo lectura (US-ADM-04 / CC-124).
 *
 * @autor  manuelmv15
 *
 * @fecha  2026-11-17
 *
 * @módulo Administración – US-ADM-04 / CC-124
 */
class AuditoriaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de auditoría.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-04 / CC-124
     *
     * @param  AuditoriaService  $auditoriaService  Servicio de la bitácora.
     */
    public function __construct(private readonly AuditoriaService $auditoriaService) {}

    /**
     * Devuelve la bitácora paginada, filtrada por usuario, acción, entidad y rango de fechas.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-04 / CC-124
     *
     * @param  ListarAuditoriasRequest  $request  Filtros validados.
     * @return JsonResponse Página de registros de auditoría.
     */
    public function index(ListarAuditoriasRequest $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Bitácora de auditoría obtenida correctamente.',
            'data' => $this->auditoriaService->listar($request->validated()),
        ]);
    }
}
