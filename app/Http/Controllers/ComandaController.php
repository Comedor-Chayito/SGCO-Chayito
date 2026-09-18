<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComandaRequest;
use App\Services\ComandaService;
use Illuminate\Http\JsonResponse;

class ComandaController extends Controller
{
    public function __construct(private readonly ComandaService $comandaService)
    {
    }

    /**
     * Registra una nueva comanda con su detalle de platillos.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  StoreComandaRequest $request Datos ya validados de la comanda.
     * @return JsonResponse
     */
    public function store(StoreComandaRequest $request): JsonResponse
    {
        $comanda = $this->comandaService->registrar($request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => 'Comanda registrada correctamente.',
            'data' => $comanda,
        ], 201);
    }
}
