<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarIngredienteRequest;
use App\Http\Requests\AgregarIngredienteRequest;
use App\Models\Platillo;
use App\Models\Receta;
use App\Services\RecetaService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API para vincular ingredientes (insumos) a cada platillo (CC-46 / US-INV-01).
 * Los recursos están anidados bajo /platillos/{platillo}/recetas.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
class RecetaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de recetas.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  RecetaService $recetaService Servicio de lógica de negocio de recetas.
     */
    public function __construct(private readonly RecetaService $recetaService)
    {
    }

    /**
     * Lista todos los ingredientes (insumos + porción) de la receta de un platillo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Platillo $platillo Platillo cuya receta se consulta.
     * @return JsonResponse Lista de renglones de receta con sus insumos.
     */
    public function index(Platillo $platillo): JsonResponse
    {
        $recetas = $this->recetaService->listarPorPlatillo($platillo);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Receta del platillo obtenida correctamente.',
            'data'    => $recetas,
        ]);
    }

    /**
     * Agrega un insumo con su porción base a la receta estándar de un platillo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  AgregarIngredienteRequest $request  Datos validados del ingrediente.
     * @param  Platillo                  $platillo Platillo al que se agrega el ingrediente.
     * @return JsonResponse Renglón de receta creado con código HTTP 201.
     */
    public function store(AgregarIngredienteRequest $request, Platillo $platillo): JsonResponse
    {
        $receta = $this->recetaService->agregar($platillo, $request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Ingrediente agregado a la receta correctamente.',
            'data'    => $receta,
        ], 201);
    }

    /**
     * Actualiza la cantidad por porción de un ingrediente en la receta de un platillo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  ActualizarIngredienteRequest $request  Datos validados a actualizar.
     * @param  Platillo                     $platillo Platillo propietario de la receta.
     * @param  Receta                       $receta   Renglón de receta a modificar.
     * @return JsonResponse Renglón de receta actualizado.
     */
    public function update(
        ActualizarIngredienteRequest $request,
        Platillo $platillo,
        Receta $receta
    ): JsonResponse {
        $recetaActualizada = $this->recetaService->actualizar($receta, $request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Porción del ingrediente actualizada correctamente.',
            'data'    => $recetaActualizada,
        ]);
    }

    /**
     * Elimina un ingrediente de la receta estándar de un platillo (desvincula el insumo).
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Platillo $platillo Platillo propietario de la receta.
     * @param  Receta   $receta   Renglón de receta a eliminar.
     * @return JsonResponse Confirmación de eliminación.
     */
    public function destroy(Platillo $platillo, Receta $receta): JsonResponse
    {
        $this->recetaService->eliminar($receta);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Ingrediente eliminado de la receta correctamente.',
            'data'    => null,
        ]);
    }
}
