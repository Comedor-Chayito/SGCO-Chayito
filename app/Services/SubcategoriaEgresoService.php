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
}
