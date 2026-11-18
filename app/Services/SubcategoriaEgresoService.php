<?php

namespace App\Services;

use App\Models\SubcategoriaEgreso;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio de lógica de negocio del catálogo de subcategorías de egreso (CC-13 / US-CON-01).
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005
 */
class SubcategoriaEgresoService
{
    /**
     * Lista las subcategorías activas, ordenadas por nombre, para clasificar un egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 / CC-62
     *
     * @return Collection<int, SubcategoriaEgreso> Subcategorías con id y nombre.
     */
    public function listarActivas(): Collection
    {
        return SubcategoriaEgreso::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /**
     * Lista todas las subcategorías, activas e inactivas, para el panel de configuración.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @return Collection<int, SubcategoriaEgreso> Subcategorías con id, nombre y estado.
     */
    public function listarTodas(): Collection
    {
        return SubcategoriaEgreso::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'activo']);
    }

    /**
     * Crea una subcategoría de egreso activa.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @param  array{nombre: string} $datos Datos validados por CrearSubcategoriaEgresoRequest.
     * @return SubcategoriaEgreso Subcategoría creada.
     */
    public function crear(array $datos): SubcategoriaEgreso
    {
        return SubcategoriaEgreso::create([
            'nombre' => trim($datos['nombre']),
            'activo' => true,
        ]);
    }

    /**
     * Cambia el nombre de una subcategoría; los egresos ya registrados la siguen referenciando por id.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @param  SubcategoriaEgreso    $subcategoria Subcategoría a renombrar.
     * @param  array{nombre: string} $datos        Datos validados por RenombrarSubcategoriaEgresoRequest.
     * @return SubcategoriaEgreso Subcategoría con el nombre nuevo.
     */
    public function renombrar(SubcategoriaEgreso $subcategoria, array $datos): SubcategoriaEgreso
    {
        $subcategoria->update(['nombre' => trim($datos['nombre'])]);

        return $subcategoria->refresh();
    }

    /**
     * Activa o desactiva una subcategoría. No se borra porque los egresos históricos la referencian.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @param  SubcategoriaEgreso $subcategoria Subcategoría a alternar.
     * @return SubcategoriaEgreso Subcategoría con su nuevo estado.
     */
    public function alternarActivo(SubcategoriaEgreso $subcategoria): SubcategoriaEgreso
    {
        $subcategoria->update(['activo' => ! $subcategoria->activo]);

        return $subcategoria->refresh();
    }
}
