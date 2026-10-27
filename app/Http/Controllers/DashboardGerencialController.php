<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultarDashboardGerencialRequest;
use App\Services\DashboardGerencialService;
use Illuminate\Http\JsonResponse;

/**
 * Expone los indicadores financieros consolidados para socios y administración.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
 */
class DashboardGerencialController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de consolidación de KPIs.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
     *
     * @param  DashboardGerencialService  $dashboardGerencialService  Servicio de indicadores financieros.
     */
    public function __construct(private readonly DashboardGerencialService $dashboardGerencialService) {}

    /**
     * Devuelve los KPIs de salud financiera y margen bruto del periodo solicitado.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
     *
     * @param  ConsultarDashboardGerencialRequest  $request  Filtro temporal ya validado.
     * @return JsonResponse Respuesta estructurada con periodo e indicadores.
     */
    public function index(ConsultarDashboardGerencialRequest $request): JsonResponse
    {
        $filtro = $request->validated();

        return response()->json([
            'status' => 'ok',
            'message' => 'Indicadores gerenciales obtenidos correctamente.',
            'data' => $this->dashboardGerencialService->obtenerKpis(
                $filtro['fecha_desde'] ?? null,
                $filtro['fecha_hasta'] ?? null,
            ),
        ]);
    }
}
