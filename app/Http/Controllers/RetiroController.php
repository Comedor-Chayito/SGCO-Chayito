<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarRetiroRequest;
use App\Services\RetiroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetiroController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de retiros inyectado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @param  RetiroService $retiroService Servicio de lógica de negocio para retiros.
     */
    public function __construct(private readonly RetiroService $retiroService)
    {
    }

    /**
     * Devuelve los retiros del turno actual del usuario autenticado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @param  Request $request Solicitud con el usuario autenticado.
     * @return JsonResponse Respuesta estructurada con la lista de retiros.
     */
    public function index(Request $request): JsonResponse
    {
        $retiros = $this->retiroService->listarDelTurno($request->user());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Retiros del turno obtenidos.',
            'data'    => $retiros,
        ]);
    }

    /**
     * Registra un retiro parcial de efectivo asociado al turno del usuario autenticado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
     *
     * @param  RegistrarRetiroRequest $request Datos validados del retiro.
     * @return JsonResponse Respuesta estructurada con el retiro creado (201).
     */
    public function store(RegistrarRetiroRequest $request): JsonResponse
    {
        $retiro = $this->retiroService->registrar($request->validated(), $request->user());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Retiro registrado correctamente.',
            'data'    => $retiro,
        ], 201);
    }
}
