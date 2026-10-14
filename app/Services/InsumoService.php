<?php

namespace App\Services;

use App\Models\Insumo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de lógica de negocio para la gestión de insumos de bodega (CC-45 / US-INV-01).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
class InsumoService
{
    /**
     * Lista todos los insumos, con filtro opcional por nombre.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  array<string, mixed> $filtros Filtros opcionales: busqueda.
     * @return Collection<int, Insumo>
     */
    public function listar(array $filtros = []): Collection
    {
        $query = Insumo::query()->orderBy('nombre', 'asc');

        if (! empty($filtros['busqueda'])) {
            $termino = '%' . trim($filtros['busqueda']) . '%';
            $query->where('nombre', 'like', $termino);
        }

        return $query->get();
    }

    /**
     * Crea un nuevo insumo en el catálogo de bodega.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  array<string, mixed> $datos Datos validados del insumo.
     * @return Insumo
     */
    public function crear(array $datos): Insumo
    {
        return Insumo::create([
            'nombre'        => trim($datos['nombre']),
            'unidad_medida' => trim($datos['unidad_medida']),
            'stock_actual'  => $datos['stock_actual'] ?? 0,
            'stock_minimo'  => $datos['stock_minimo'] ?? 0,
        ]);
    }

    /**
     * Actualiza los datos de un insumo existente.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Insumo               $insumo Instancia del insumo a modificar.
     * @param  array<string, mixed> $datos  Datos validados a actualizar.
     * @return Insumo
     */
    public function actualizar(Insumo $insumo, array $datos): Insumo
    {
        $insumo->update([
            'nombre'        => trim($datos['nombre']),
            'unidad_medida' => trim($datos['unidad_medida']),
            'stock_actual'  => $datos['stock_actual'],
            'stock_minimo'  => $datos['stock_minimo'],
        ]);

        return $insumo->fresh();
    }

    /**
     * Elimina un insumo del catálogo si no está vinculado a ninguna receta activa.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Insumo $insumo Insumo a eliminar.
     * @return bool
     * @throws ValidationException Si el insumo posee recetas o requisiciones vinculadas.
     */
    public function eliminar(Insumo $insumo): bool
    {
        if ($insumo->recetas()->exists()) {
            throw ValidationException::withMessages([
                'insumo' => 'No se puede eliminar el insumo porque está vinculado a recetas de platillos. '
                    . 'Primero elimina las relaciones en las recetas.',
            ]);
        }

        if ($insumo->requisicionDetalles()->exists()) {
            throw ValidationException::withMessages([
                'insumo' => 'No se puede eliminar el insumo porque aparece en requisiciones registradas.',
            ]);
        }

        return (bool) $insumo->delete();
    }
}
