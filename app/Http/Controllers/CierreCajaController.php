<?php

namespace App\Http\Controllers;

use App\Http\Requests\CerrarCajaRequest;
use App\Models\User;
use App\Services\CierreCajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CierreCajaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de cierre de caja inyectado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  CierreCajaService  $cierreCajaService  Servicio de lógica de negocio para cierres.
     */
    public function __construct(private readonly CierreCajaService $cierreCajaService) {}

    /**
     * Ejecuta el cierre diario de caja y genera la bitácora inalterable.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  CerrarCajaRequest  $request  Datos validados del cierre.
     * @return JsonResponse Respuesta estructurada con el cierre creado (201).
     */
    public function cerrar(CerrarCajaRequest $request): JsonResponse
    {
        // TODO: Reemplazar por auth()->id() al implementar US-ADM-02
        $usuarioId = auth('sanctum')->id() ?? auth()->id() ?? User::query()->value('id') ?? 1;

        $cierre = $this->cierreCajaService->cerrar($request->validated(), $usuarioId);

        return response()->json([
            'status' => 'ok',
            'message' => 'Caja cerrada correctamente.',
            'data' => $cierre,
        ], 201);
    }

    /**
     * Devuelve el registro de cierre de caja para una fecha específica.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  string  $fecha  Fecha del cierre en formato Y-m-d.
     * @return JsonResponse Respuesta estructurada con los datos del cierre (200).
     */
    public function mostrar(string $fecha): JsonResponse
    {
        $cierre = $this->cierreCajaService->obtenerCierre($fecha);

        return response()->json([
            'status' => 'ok',
            'message' => 'Cierre de caja obtenido.',
            'data' => $cierre,
        ]);
    }

    /**
     * Lista el historial de cierres de caja de forma paginada.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  Request  $request  Solicitud HTTP con parámetro opcional 'por_pagina'.
     * @return JsonResponse Respuesta estructurada con la colección paginada (200).
     */
    public function historial(Request $request): JsonResponse
    {
        $porPagina = (int) $request->query('por_pagina', 15);
        $cierres = $this->cierreCajaService->listarCierres($porPagina);

        return response()->json([
            'status' => 'ok',
            'message' => 'Historial de cierres obtenido.',
            'data' => $cierres,
        ]);
    }
}
