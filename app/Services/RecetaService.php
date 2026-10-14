<?php

namespace App\Services;

use App\Models\Platillo;
use App\Models\Receta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de lógica de negocio para vincular ingredientes (insumos) a platillos
 * mediante la tabla de recetas estándar (CC-46 / US-INV-01).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
class RecetaService
{
    /**
     * Lista todos los renglones de receta de un platillo con sus insumos cargados.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Platillo $platillo Platillo del cual se obtienen las recetas.
     * @return Collection<int, Receta>
     */
    public function listarPorPlatillo(Platillo $platillo): Collection
    {
        return $platillo
            ->recetas()
            ->with('insumo:id,nombre,unidad_medida,stock_actual')
            ->get();
    }

    /**
     * Agrega un insumo (con su porción base) a la receta de un platillo.
     * Valida que no exista ya el mismo insumo en el mismo platillo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Platillo             $platillo Platillo al que se le agrega el insumo.
     * @param  array<string, mixed> $datos    Datos validados: insumo_id, cantidad_por_porcion.
     * @return Receta
     * @throws ValidationException Si el insumo ya está en la receta del platillo.
     */
    public function agregar(Platillo $platillo, array $datos): Receta
    {
        $yaExiste = $platillo->recetas()
            ->where('insumo_id', $datos['insumo_id'])
            ->exists();

        if ($yaExiste) {
            throw ValidationException::withMessages([
                'insumo_id' => 'Este insumo ya está en la receta de este platillo. '
                    . 'Si deseas cambiar la porción, edita el renglón existente.',
            ]);
        }

        $receta = Receta::create([
            'platillo_id'          => $platillo->id,
            'insumo_id'            => $datos['insumo_id'],
            'cantidad_por_porcion' => $datos['cantidad_por_porcion'],
        ]);

        return $receta->load('insumo:id,nombre,unidad_medida,stock_actual');
    }

    /**
     * Actualiza la porción base de un renglón de receta existente.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Receta               $receta Renglón de receta a modificar.
     * @param  array<string, mixed> $datos  Datos validados: cantidad_por_porcion.
     * @return Receta
     */
    public function actualizar(Receta $receta, array $datos): Receta
    {
        $receta->update([
            'cantidad_por_porcion' => $datos['cantidad_por_porcion'],
        ]);

        return $receta->load('insumo:id,nombre,unidad_medida,stock_actual');
    }

    /**
     * Elimina un renglón de receta (desvincula un insumo de un platillo).
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Receta $receta Renglón de receta a eliminar.
     * @return bool
     */
    public function eliminar(Receta $receta): bool
    {
        return (bool) $receta->delete();
    }
}
