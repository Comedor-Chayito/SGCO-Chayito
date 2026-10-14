<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcesarCobroRequest;
use App\Models\User;
use App\Services\CobroService;
use Illuminate\Http\JsonResponse;

class VentaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de cobros inyectado.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @param  CobroService $cobroService Servicio de lógica de negocio para cobros.
     */
    public function __construct(private readonly CobroService $cobroService)
    {
    }

    /**
     * Procesa el cobro de una comanda activa y genera el comprobante de venta.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @param  ProcesarCobroRequest $request Datos validados de la solicitud de cobro.
     * @return JsonResponse Respuesta estructurada con la venta creada y el cambio.
     */
    public function cobrar(ProcesarCobroRequest $request): JsonResponse
    {
        $usuarioId = auth('sanctum')->id() ?? auth()->id() ?? User::query()->value('id') ?? 1;

        $venta = $this->cobroService->procesarCobro($request, $usuarioId);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Cobro procesado correctamente.',
            'data'    => $venta,
        ], 201);
    }

    /**
     * Devuelve los datos de un comprobante de venta o ticket por su ID.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-002
     *
     * @param  int $id Identificador único de la venta.
     * @return JsonResponse Respuesta estructurada con la información del ticket.
     */
    public function mostrar(int $id): JsonResponse
    {
        $venta = $this->cobroService->obtenerComprobante($id);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Comprobante obtenido.',
            'data'    => $venta,
        ]);
    }
}

