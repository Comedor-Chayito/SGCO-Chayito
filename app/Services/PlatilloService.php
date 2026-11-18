<?php

namespace App\Services;

use App\Models\Platillo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de lógica de negocio del catálogo de platillos (CC-104 / CC-106 — US-ADM-03).
 *
 * Los precios históricos viven en comanda_detalles.precio_unitario y comandas.subtotal,
 * por eso modificar el precio de un platillo nunca toca registros ya emitidos.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-17
 * @módulo Administración – RF-ADM-008
 */
class PlatilloService
{
    /**
     * Lista todos los platillos del catálogo (disponibles y no disponibles).
     * Filtros opcionales: busqueda por nombre y disponible (true/false).
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  array<string, mixed> $filtros Filtros opcionales: busqueda, disponible.
     * @return Collection<int, Platillo> Platillos ordenados por nombre.
     */
    public function listar(array $filtros = []): Collection
    {
        $query = Platillo::query()->orderBy('nombre', 'asc');

        if (! empty($filtros['busqueda'])) {
            $query->where('nombre', 'like', '%' . trim($filtros['busqueda']) . '%');
        }

        if (isset($filtros['disponible']) && $filtros['disponible'] !== '') {
            $disponible = filter_var($filtros['disponible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($disponible !== null) {
                $query->where('disponible', $disponible);
            }
        }

        return $query->get();
    }

    /**
     * Crea un platillo en el catálogo. Por defecto queda disponible en el menú del día.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  array<string, mixed> $datos Datos validados del platillo.
     * @return Platillo El platillo creado.
     */
    public function crear(array $datos): Platillo
    {
        $platillo = Platillo::create([
            'nombre'          => trim($datos['nombre']),
            'descripcion'     => isset($datos['descripcion']) ? trim($datos['descripcion']) : null,
            'precio_unitario' => $datos['precio_unitario'],
            'disponible'      => $datos['disponible'] ?? true,
        ]);

        return $platillo->refresh();
    }

    /**
     * Actualiza nombre, descripción y precio de un platillo.
     * No modifica comanda_detalles ni comandas: el histórico conserva su precio original.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  Platillo             $platillo Platillo a modificar.
     * @param  array<string, mixed> $datos    Datos validados.
     * @return Platillo El platillo actualizado.
     */
    public function actualizar(Platillo $platillo, array $datos): Platillo
    {
        $platillo->update([
            'nombre'          => trim($datos['nombre']),
            'descripcion'     => isset($datos['descripcion']) ? trim($datos['descripcion']) : null,
            'precio_unitario' => $datos['precio_unitario'],
        ]);

        return $platillo->refresh();
    }

    /**
     * Activa o desactiva un platillo del menú del día sin borrar nada del histórico.
     * Un platillo no disponible deja de aparecer en el POS y no admite nuevas comandas.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-106
     *
     * @param  Platillo $platillo   Platillo a modificar.
     * @param  bool     $disponible Nuevo valor de disponibilidad.
     * @return Platillo El platillo con la disponibilidad actualizada.
     */
    public function cambiarDisponibilidad(Platillo $platillo, bool $disponible): Platillo
    {
        $platillo->update(['disponible' => $disponible]);

        return $platillo->refresh();
    }

    /**
     * Elimina un platillo que no tenga historial de ventas ni recetas vinculadas.
     * Si los tiene, se rechaza para conservar los históricos y se indica desactivarlo.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-17
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  Platillo $platillo Platillo a eliminar.
     * @return bool true si fue eliminado.
     *
     * @throws ValidationException Si el platillo tiene comandas o recetas asociadas.
     */
    public function eliminar(Platillo $platillo): bool
    {
        return DB::transaction(function () use ($platillo) {
            $platillo = Platillo::query()->lockForUpdate()->findOrFail($platillo->id);

            if ($platillo->comandaDetalles()->exists()) {
                throw ValidationException::withMessages([
                    'platillo' => 'No se puede eliminar un platillo con comandas registradas. '
                        . 'Desactívalo del menú para conservar el historial.',
                ]);
            }

            if ($platillo->recetas()->exists()) {
                throw ValidationException::withMessages([
                    'platillo' => 'No se puede eliminar un platillo con receta vinculada. '
                        . 'Desactívalo del menú o elimina primero su receta.',
                ]);
            }

            return (bool) $platillo->delete();
        });
    }
}
