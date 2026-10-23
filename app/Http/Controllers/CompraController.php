<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarCompraRequest;
use App\Services\CompraService;
use Illuminate\Http\JsonResponse;

/**
 * Orquesta el registro y consulta de compras con IVA crédito fiscal calculado.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */
class CompraController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de compras contables.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @param  CompraService  $compraService  Servicio que calcula y persiste compras.
     */
    public function __construct(private readonly CompraService $compraService) {}

    /**
     * Devuelve las compras registradas con sus valores de IVA crédito fiscal.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @return JsonResponse Lista estructurada de compras contables.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Compras obtenidas correctamente.',
            'data' => $this->compraService->listar(),
        ]);
    }

    /**
     * Registra una compra y guarda el costo neto e IVA calculados por el servicio.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
     *
     * @param  RegistrarCompraRequest  $request  Datos validados del comprobante de compra.
     * @return JsonResponse Compra creada con código HTTP 201.
     */
    public function store(RegistrarCompraRequest $request): JsonResponse
    {
        $compra = $this->compraService->registrar($request->validated(), $request->user()->id);

        return response()->json([
            'status' => 'ok',
            'message' => 'Compra registrada con IVA crédito fiscal calculado.',
            'data' => $compra,
        ], 201);
    }
}
