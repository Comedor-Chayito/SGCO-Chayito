<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarMovimientosInsumoRequest;
use App\Http\Requests\RegistrarAjusteInsumoRequest;
use App\Http\Requests\RegistrarEntradaInsumoRequest;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Services\MovimientoInventarioService;
use Illuminate\Http\JsonResponse;

class MovimientoInventarioController extends Controller
{
    /**
     * Inyecta el servicio central de movimientos de bodega.
     *
     * @autor  Equipo de desarrollo
     *
     * @fecha  2026-11-17
     *
     * @módulo Inventario RF-INV-003 / CC-113
     *
     * @param  MovimientoInventarioService  $movimientos  Servicio transaccional de inventario.
     */
    public function __construct(private readonly MovimientoInventarioService $movimientos) {}

    /**
     * Registra una entrada a bodega con egreso opcional y actor autenticado.
     *
     * @autor  Equipo de desarrollo
     *
     * @fecha  2026-11-17
     *
     * @módulo Inventario RF-INV-003 / CC-113 / CC-115
     *
     * @param  RegistrarEntradaInsumoRequest  $request  Datos validados de la entrada.
     * @param  Insumo  $insumo  Insumo de la ruta.
     * @return JsonResponse Insumo actualizado y movimiento creado.
     */
    public function entrada(RegistrarEntradaInsumoRequest $request, Insumo $insumo): JsonResponse
    {
        $datos = $request->validated();
        $resultado = $this->movimientos->aplicar(
            $insumo, $datos['cantidad'], MovimientoInventario::TIPO_ENTRADA, $request->user()->id, $datos
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Entrada de inventario registrada correctamente.',
            'data' => $resultado,
        ], 201);
    }

    /**
     * Registra un ajuste justificado sin permitir saldo negativo.
     *
     * @autor  Equipo de desarrollo
     *
     * @fecha  2026-11-17
     *
     * @módulo Inventario RF-INV-003 / CC-113 / CC-116
     *
     * @param  RegistrarAjusteInsumoRequest  $request  Datos validados del ajuste.
     * @param  Insumo  $insumo  Insumo de la ruta.
     * @return JsonResponse Insumo actualizado y movimiento creado.
     */
    public function ajuste(RegistrarAjusteInsumoRequest $request, Insumo $insumo): JsonResponse
    {
        $datos = $request->validated();
        $resultado = $this->movimientos->aplicar(
            $insumo, $datos['cantidad'], MovimientoInventario::TIPO_AJUSTE, $request->user()->id, $datos
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Ajuste de inventario registrado correctamente.',
            'data' => $resultado,
        ], 201);
    }

    /**
     * Devuelve el paginador del historial de movimientos de un insumo.
     *
     * @autor  Equipo de desarrollo
     *
     * @fecha  2026-11-17
     *
     * @módulo Inventario RF-INV-003 / CC-113 / CC-117
     *
     * @param  ListarMovimientosInsumoRequest  $request  Consulta de paginacion validada.
     * @param  Insumo  $insumo  Insumo de la ruta.
     * @return JsonResponse Paginador con movimientos y metadatos.
     */
    public function index(ListarMovimientosInsumoRequest $request, Insumo $insumo): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Movimientos de inventario obtenidos correctamente.',
            'data' => $this->movimientos->listar($insumo, (int) ($request->validated()['page'] ?? 1)),
        ]);
    }
}
