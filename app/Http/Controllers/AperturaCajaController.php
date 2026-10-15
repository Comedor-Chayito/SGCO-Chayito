<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarAperturaCajaRequest;
use App\Models\User;
use App\Services\AperturaCajaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AperturaCajaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de apertura de caja inyectado.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @param  AperturaCajaService  $aperturaCajaService  Servicio de lógica de negocio para aperturas.
     */
    public function __construct(private readonly AperturaCajaService $aperturaCajaService) {}

    /**
     * Registra la apertura de caja y el saldo inicial del día por denominación.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @param  RegistrarAperturaCajaRequest  $request  Datos validados de la apertura.
     * @return JsonResponse Respuesta estructurada con la apertura creada (201).
     */
    public function store(RegistrarAperturaCajaRequest $request): JsonResponse
    {
        $usuarioId = auth('sanctum')->id() ?? auth()->id() ?? User::query()->value('id') ?? 1;

        $apertura = $this->aperturaCajaService->abrirCaja($request->validated(), $usuarioId);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Apertura de caja registrada correctamente.',
            'data'    => $apertura,
        ], 201);
    }

    /**
     * Devuelve el registro de apertura de caja del turno de hoy (o por fecha opcional).
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – RF-POS-002 / US-POS-04 (CC-39)
     *
     * @param  Request  $request  Solicitud HTTP con parámetro opcional 'fecha'.
     * @return JsonResponse Respuesta estructurada con la apertura o null.
     */
    public function obtenerDelTurno(Request $request): JsonResponse
    {
        $fecha = $request->query('fecha');
        $apertura = $this->aperturaCajaService->obtenerAperturaPorFecha($fecha);

        return response()->json([
            'status'  => 'ok',
            'message' => $apertura ? 'Apertura de caja obtenida.' : 'No hay apertura registrada para esta fecha.',
            'data'    => $apertura,
        ]);
    }
}
